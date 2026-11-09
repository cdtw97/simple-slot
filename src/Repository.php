<?php
declare(strict_types=1);
require_once __DIR__ . '/GameState.php';
require_once __DIR__ . '/Collection.php';
require_once __DIR__ . '/Daily.php';

final class Repository
{
    public function __construct(private PDO $db, private ?DateTimeImmutable $instant = null) {}

    public static function connect(): PDO
    {
        $file = getenv('SIMPLE_SLOT_CONFIG') ?: dirname(__DIR__, 3) . '/simple-slot.config.php';
        if (!is_file($file)) {
            throw new RuntimeException('Database configuration is missing.');
        }
        $cfg = require $file;
        return new PDO($cfg['dsn'], $cfg['user'], $cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    private function query(string $sql, array $params): PDOStatement
    {
        $s = $this->db->prepare($sql);
        $s->execute($params);
        return $s;
    }

    private function profile(int $id, string $json): array
    {
        $stored = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $profile = Collection::upgrade($stored);
        if (!isset($stored['version'])) {
            // R1 retained exactly one receipt per spin. Derive collection history once
            // without replaying any wallet, lamp or bonus mutations.
            $receipts = $this->query('SELECT receipt_json FROM slot_actions WHERE guest_id=?', [$id]);
            while (($json = $receipts->fetchColumn()) !== false) {
                $old = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
                if ($old['mode'] === 'free' && $old['result']) {
                    Collection::apply($profile, $old);
                }
            }
            $this->query('UPDATE slot_guests SET profile_json=? WHERE id=?', [json_encode($profile, JSON_THROW_ON_ERROR), $id]);
        }
        return $profile;
    }

    private function finishRun(int $guest, array &$game): void
    {
        $date = $game['dailyDate'] ?? '';
        $official = $game['official'] ?? true;
        $params = [$guest, $game['mode'], 'fruit-arcade', $game['rulesVersion'], $date];
        $best = $this->query('SELECT MAX(score_units) FROM slot_records
            WHERE guest_id=? AND mode=? AND machine=? AND rules_version=? AND daily_key=? AND official=1', $params)->fetchColumn();
        $game['record'] = $game['runStats'] + [
            'id' => $game['id'], 'mode' => $game['mode'], 'machine' => 'fruit-arcade',
            'rulesVersion' => $game['rulesVersion'], 'scoreUnits' => $game['scoreUnits'],
            'dailyDate' => $date ?: null, 'official' => $official,
            'completedAt' => ($this->instant ?? new DateTimeImmutable())->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM),
            'previousBestUnits' => $best !== null ? (int)$best : null,
        ];
        if ($official) {
            $this->query('INSERT INTO slot_records
                (run_id,guest_id,mode,machine,rules_version,daily_key,official,score_units,record_json)
                VALUES (?,?,?,?,?,?,?,?,?)', array_merge([$game['id']], $params, [1, $game['scoreUnits'], json_encode($game['record'], JSON_THROW_ON_ERROR)]));
        }
    }

    private function dailyAvailable(int $id, string $date): bool
    {
        return !$this->query('SELECT run_id FROM slot_daily_attempts WHERE guest_id=? AND daily_key=?',
            [$id, $date])->fetchColumn();
    }

    private function dailyAction(int $id, array &$state, array $input, string $today): void
    {
        $action = $input['action'];
        if ($action === 'start_run') {
            $this->query('INSERT INTO slot_daily_definitions (daily_key,rules_version,seed_hex) VALUES (?,?,?)
                ON DUPLICATE KEY UPDATE daily_key=VALUES(daily_key)', [$today, Rules::CURRENT, bin2hex(random_bytes(32))]);
            $version = $this->query('SELECT rules_version FROM slot_daily_definitions WHERE daily_key=? FOR UPDATE', [$today])->fetchColumn();
            Challenge::start($state, Rules::get($version));
            $state['dailyDate'] = $today;
            $state['official'] = $this->dailyAvailable($id, $today);
            if ($state['official']) {
                // The guest lock serializes same-guest starts. The primary key protects
                // the reservation even when the run is abandoned or the server restarts.
                $this->query('INSERT INTO slot_daily_attempts (guest_id,daily_key,run_id) VALUES (?,?,?)',
                    [$id, $today, $state['id']]);
            }
            return;
        }
        $rules = Rules::get($state['rulesVersion']);
        $draw = null;
        if (isset($state['dailyDate']) && in_array($action, ['spin','advance_bonus','respin'], true)) {
            $seed = $this->query('SELECT seed_hex FROM slot_daily_definitions WHERE daily_key=?', [$state['dailyDate']])->fetchColumn();
            if (!$seed) {
                throw new RuntimeException('Missing pinned daily definition.');
            }
            $draw = Daily::stream($state, $rules, $action, $input, $seed);
        }
        Challenge::act($state, $action, $input, $rules, $draw);
    }

    private function act(int $id, array &$state, array &$profile, array $input, string $today): void
    {
        $action = $input['action'];
        if ($action === 'equip') {
            Collection::equip($profile, $input['palette'] ?? null);
            return;
        }
        $before = $state['result']['id'] ?? null;
        if ($state['mode'] === 'free') {
            if ($state['phase'] === 'idle') {
                // All supported versions share corner positions; retain charged lamps on upgrade.
                $state['rulesVersion'] = Rules::CURRENT;
            }
            GameState::act($state, $action, $input, Rules::get($state['rulesVersion']));
        } elseif ($state['mode'] === 'daily') {
            $this->dailyAction($id, $state, $input, $today);
        } else {
            $version = $action === 'start_run' ? Rules::CURRENT : $state['rulesVersion'];
            Challenge::act($state, $action, $input, Rules::get($version));
        }
        if ($state['result'] && $state['result']['id'] !== $before) {
            Collection::apply($profile, $state);
            if ($state['phase'] === 'run_complete') {
                $this->finishRun($id, $state);
            }
        }
    }

    public function load(string $hash, string $mode, ?array $input, ?int $legacyWallet): array
    {
        $this->db->beginTransaction();
        try {
            // All actions by a guest take this lock first, even across modes.
            if ($input === null) {
                $this->query("INSERT INTO slot_guests (secret_hash, profile_json) VALUES (?, '{}')
                    ON DUPLICATE KEY UPDATE id=id", [$hash]);
            }
            $guest = $this->query('SELECT id,profile_json FROM slot_guests WHERE secret_hash=? FOR UPDATE', [$hash])->fetch(PDO::FETCH_ASSOC);
            if (!$guest) {
                throw new GameError('unknown_guest', 'Reconnect to load your guest save.', 403);
            }
            $id = (int)$guest['id'];
            $daily = Daily::calendar($this->instant);
            $profile = $this->profile($id, $guest['profile_json']);
            if ($legacyWallet !== null && $mode !== 'free') {
                $this->query("INSERT INTO slot_games (guest_id,mode,state_json) VALUES (?,'free',?)
                    ON DUPLICATE KEY UPDATE guest_id=guest_id", [$id, json_encode(GameState::initial('free', $legacyWallet), JSON_THROW_ON_ERROR)]);
            }
            $stored = $this->query('SELECT state_json FROM slot_games WHERE guest_id=? AND mode=?', [$id, $mode])->fetchColumn();
            $state = $stored ? json_decode($stored, true, 512, JSON_THROW_ON_ERROR) : GameState::initial($mode, $legacyWallet);
            $replayed = false;
            if ($input !== null) {
                $payload = $input;
                unset($payload['csrf']);
                ksort($payload);
                $digest = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
                $prior = $this->query('SELECT payload_hash,receipt_json FROM slot_actions WHERE guest_id=? AND action_id=?',
                    [$id, $input['actionId']])->fetch(PDO::FETCH_ASSOC);
                if ($prior) {
                    if (!hash_equals($prior['payload_hash'], $digest)) {
                        throw new GameError('action_conflict', 'This action ID already belongs to a different request.');
                    }
                    $state = json_decode($prior['receipt_json'], true, 512, JSON_THROW_ON_ERROR);
                    $replayed = true;
                } else {
                    if ($state['revision'] !== $input['expectedRevision']) {
                        throw new GameError('stale_revision', 'This game changed in another tab. Reload its latest result.');
                    }
                    $this->act($id, $state, $profile, $input, $daily['date']);
                    $state['revision']++;
                    $this->query('UPDATE slot_guests SET profile_json=? WHERE id=?', [json_encode($profile, JSON_THROW_ON_ERROR), $id]);
                    $this->query('INSERT INTO slot_actions (guest_id,action_id,payload_hash,receipt_json) VALUES (?,?,?,?)',
                        [$id, $input['actionId'], $digest, json_encode($state, JSON_THROW_ON_ERROR)]);
                }
            }
            if (!$stored || ($input !== null && !$replayed)) {
                $this->query('INSERT INTO slot_games (guest_id,mode,state_json) VALUES (?,?,?)
                    ON DUPLICATE KEY UPDATE state_json=VALUES(state_json)', [$id, $mode, json_encode($state, JSON_THROW_ON_ERROR)]);
            }
            $daily['officialAvailable'] = $this->dailyAvailable($id, $daily['date']);
            $this->db->commit();
            return ['state' => $state, 'profile' => Collection::view($profile), 'daily' => $daily, 'replayed' => $replayed];
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
