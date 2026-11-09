<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../src/Repository.php';

function verify(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
}

foreach ([
    ['2026-03-08T05:00:00Z', '2026-03-08', '2026-03-09T04:00:00+00:00', 23],
    ['2026-11-01T04:00:00Z', '2026-11-01', '2026-11-02T05:00:00+00:00', 25],
] as [$utc, $date, $midnight, $hours]) {
    $day = Daily::calendar(new DateTimeImmutable($utc));
    verify($day['date'] === $date && $day['resetAt'] === $midnight, 'wrong Toronto civil date or midnight');
    verify(strtotime($day['resetAt']) - strtotime($utc) === $hours * 3600, 'DST day length');
}
echo "Passed Toronto spring and fall DST day keys and reset countdowns.\n";

$testSchema = getopt('', ['database:'])['database'] ?? null;
if (!$testSchema) exit;
$db = Repository::connect();
verify($db->query('SELECT DATABASE()')->fetchColumn() === $testSchema, 'supply the intended test database name');
$hash = hash('sha256', random_bytes(32));
$before = new Repository($db, new DateTimeImmutable('2026-11-02T04:59:59Z'));
$after = new Repository($db, new DateTimeImmutable('2026-11-02T05:00:01Z'));

function play(Repository $repo, string $hash, array $save, string $command): array
{
    return $repo->load($hash, 'daily', ['mode' => 'daily', 'action' => $command,
        'actionId' => bin2hex(random_bytes(12)), 'expectedRevision' => $save['state']['revision']], null);
}

function finishDaily(Repository $repo, string $hash, array $save): array
{
    while ($save['state']['phase'] !== 'run_complete') {
        $command = match ($save['state']['phase']) {
            'awaiting_choice' => 'keep', 'bonus_ready', 'bonus_in_progress' => 'advance_bonus', 'idle' => 'spin',
            default => throw new RuntimeException('Unexpected run phase'),
        };
        $save = play($repo, $hash, $save, $command);
    }
    return $save;
}

$save = $before->load($hash, 'daily', null, null);
$save = play($before, $hash, $save, 'start_run');
$pending = play($before, $hash, $save, 'spin');
$newDay = $after->load($hash, 'daily', null, null);
verify($newDay['daily']['date'] === '2026-11-02', 'server date must advance');
verify($newDay['state'] === $pending['state'] && $pending['state']['phase'] === 'awaiting_choice',
    'unfinished old-date board and rules must survive midnight');
$finished = finishDaily($after, $hash, $newDay);
verify($finished['state']['record']['dailyDate'] === '2026-11-01', 'official record lost original date');
verify($finished['state']['record']['rulesVersion'] === $pending['state']['rulesVersion'], 'rules changed mid-run');

$newOfficial = play($after, $hash, $finished, 'start_run');
verify($newOfficial['state']['official'] && $newOfficial['state']['dailyDate'] === '2026-11-02', 'new official day');
$todayDone = finishDaily($after, $hash, $newOfficial);
$practice = play($after, $hash, $todayDone, 'start_run');
verify(!$practice['state']['official'], 'second attempt should be practice');
$practice = finishDaily($after, $hash, $practice);
verify($practice['state']['record']['official'] === false, 'practice scorecard must be labeled');
verify($practice['state']['record']['scoreUnits'] === $todayDone['state']['record']['scoreUnits'], 'same kept daily choices');
$records = $db->prepare('SELECT COUNT(*) FROM slot_records AS r JOIN slot_guests AS g ON g.id=r.guest_id
    WHERE g.secret_hash=? AND r.mode=?');
$records->execute([$hash, 'daily']);
verify((int)$records->fetchColumn() === 2, 'only completed official dates should have rows');
echo "Passed previously unfinished daily across midnight, a new official date, a matching practice score and two official records.\n";
