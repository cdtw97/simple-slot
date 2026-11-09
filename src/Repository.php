<?php
declare(strict_types=1);
require_once __DIR__ . '/GameState.php';

final class Repository
{
    public function __construct(private PDO $db) {}

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
            $profile = json_decode($guest['profile_json'], true, 512, JSON_THROW_ON_ERROR);
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
                    if ($mode === 'free' && $state['phase'] === 'idle') {
                        // All supported versions share corner positions; retain charged lamps on upgrade.
                        $state['rulesVersion'] = Rules::CURRENT;
                    }
                    GameState::act($state, $input['action'], $input, Rules::get($state['rulesVersion']));
                    $state['revision']++;
                    $this->query('INSERT INTO slot_actions (guest_id,action_id,payload_hash,receipt_json) VALUES (?,?,?,?)',
                        [$id, $input['actionId'], $digest, json_encode($state, JSON_THROW_ON_ERROR)]);
                }
            }
            if (!$stored || ($input !== null && !$replayed)) {
                $this->query('INSERT INTO slot_games (guest_id,mode,state_json) VALUES (?,?,?)
                    ON DUPLICATE KEY UPDATE state_json=VALUES(state_json)', [$id, $mode, json_encode($state, JSON_THROW_ON_ERROR)]);
            }
            $this->db->commit();
            return ['state' => $state, 'profile' => (object)$profile, 'daily' => null, 'replayed' => $replayed];
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
