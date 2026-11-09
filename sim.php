<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/src/Engine.php';

/** Percentiles use nearest rank. Histograms keep memory bounded for long samples. */
function percentiles(array $histogram): array
{
    $count = array_sum($histogram);
    if (!$count) {
        return [];
    }
    ksort($histogram, SORT_NUMERIC);
    $out = [];
    $seen = 0;
    foreach ($histogram as $value => $frequency) {
        $seen += $frequency;
        foreach ([10, 50, 90, 95, 99, 100] as $p) {
            if (!isset($out["p$p"]) && $seen >= (int)ceil($count * $p / 100)) {
                $out["p$p"] = $value;
            }
        }
    }
    return $out;
}

/** Counts by return size, including zero, with boundaries in the active rules. */
function awardSizes(array $histogram, array $rules): array
{
    $bins = ['zero'=>0, 'belowStake'=>0, 'stakeToBelowLargeWin'=>0, 'largeWinOrHigher'=>0];
    foreach ($histogram as $units => $count) {
        $bucket = match (true) {
            $units === 0 => 'zero', $units < $rules['stakeUnits'] => 'belowStake',
            $units < $rules['celebrations']['largeWinUnits'] => 'stakeToBelowLargeWin', default => 'largeWinOrHigher',
        };
        $bins[$bucket] += $count;
    }
    return $bins;
}

function simulate(array $rules, int $count, string $seed): array
{
    $normalDraw = Engine::stream($seed, 'free-play/normal');
    $bonusDraw = Engine::stream($seed, 'free-play/bonus');
    $empty = array_fill(0, 4, false);
    $lamps = $empty;
    $activations = $bonusSpins = $baseTotal = $bonusTotal = $normalHits = $bonusHits = $previous = 0;
    $events = $normalSizes = $bonusSizes = $intervals = [];
    $net = ['gain'=>0, 'even'=>0, 'loss'=>0];

    $record = static function (array $awards) use (&$events): void {
        foreach ($awards as $award) {
            $key = $award['type'] === 'row' ? 'row-' . $award['length'] : $award['type'];
            $events[$key] = ($events[$key] ?? 0) + 1;
        }
    };
    for ($spin = 1; $spin <= $count; $spin++) {
        $result = Engine::evaluate(Engine::board($rules, $normalDraw), $rules);
        $record($result['awards']);
        $units = $result['payoutUnits'];
        $baseTotal += $units;
        $normalSizes[$units] = ($normalSizes[$units] ?? 0) + 1;
        $normalHits += (int)($units > 0);
        $net[['loss', 'even', 'gain'][1 + ($units <=> $rules['stakeUnits'])]]++;
        if (!$rules['bonusSpins']) {
            continue;
        }
        $lamps = Engine::chargedLamps($lamps, $result['awards'], $rules);
        if (in_array(false, $lamps, true)) {
            continue;
        }
        $interval = $spin - $previous;
        $intervals[$interval] = ($intervals[$interval] ?? 0) + 1;
        $previous = $spin;
        $activations++;
        $lamps = $empty;
        for ($i = 0; $i < $rules['bonusSpins']; $i++) {
            $result = Engine::evaluate(Engine::board($rules, $bonusDraw), $rules, $rules['bonusMultiplier']);
            $record($result['awards']);
            $units = $result['payoutUnits'];
            $bonusTotal += $units;
            $bonusSpins++;
            $bonusHits += (int)($units > 0);
            $bonusSizes[$units] = ($bonusSizes[$units] ?? 0) + 1;
        }
    }
    $spins = $count + $bonusSpins;
    ksort($events);
    return [
        'rulesVersion'=>$rules['version'], 'seed'=>$seed, 'strategy'=>'Free Play; advance all earned bonuses',
        'actualSpins'=>$spins, 'normalSpins'=>$count, 'bonusSpins'=>$bonusSpins, 'awardEvents'=>$events,
        'eventsPerSettledSpin'=>array_sum($events) / $spins,
        'anyAwardPctAllSpins'=>100 * ($normalHits + $bonusHits) / $spins,
        'anyAwardPctNormalSpins'=>100 * $normalHits / $count,
        'normalSpinNetPct'=>array_map(static fn(int $n): float => 100 * $n / $count, $net),
        'spentUnits'=>$count * $rules['stakeUnits'], 'normalAwardUnits'=>$baseTotal, 'bonusAwardUnits'=>$bonusTotal,
        'grossReturnPctOfNormalStakes'=>100 * ($baseTotal + $bonusTotal) / ($count * $rules['stakeUnits']),
        'bonusShareOfTotalAwardsPct'=>($baseTotal + $bonusTotal) ? 100 * $bonusTotal / ($baseTotal + $bonusTotal) : 0,
        'normalAwardSizeCounts'=>awardSizes($normalSizes, $rules), 'bonusAwardSizeCounts'=>awardSizes($bonusSizes, $rules),
        'normalAwardUnitsPercentiles'=>percentiles($normalSizes), 'bonusAwardUnitsPercentiles'=>percentiles($bonusSizes),
        'cornerRushActivations'=>$activations,
        'meanNormalSpinsPerCompletedMeter'=>$activations ? $previous / $activations : null,
        'normalSpinsPerCompletedMeterPercentiles'=>percentiles($intervals),
    ];
}

/** Compare choices on paired normal boards, with independent draws for each bonus and respin. */
function simulateRuns(array $rules, int $count, string $seed, bool $rerollBlanks): array
{
    require_once __DIR__ . '/src/GameState.php';
    $scores = [];
    $scoreTotal = $respins = $bonuses = $normalSpins = $bonusSpins = 0;
    for ($run = 1; $run <= $count; $run++) {
        $game = GameState::initial('score');
        Challenge::act($game, 'start_run', [], $rules);
        while ($game['phase'] !== 'run_complete') {
            $spin = $game['normalUsed'];
            $input = [];
            $ordinal = null;
            if ($game['bonusRemaining']) {
                $action = 'advance_bonus';
                $ordinal = $rules['bonusSpins'] - $game['bonusRemaining'] + 1;
                $bonusSpins++;
            } elseif ($game['phase'] === 'awaiting_choice') {
                $action = 'keep';
                if ($rerollBlanks && $game['pending']['payoutUnits'] === 0) {
                    $action = 'respin';
                    $input = ['reel'=>2];
                }
            } else {
                $action = 'spin';
                $spin++;
            }
            $address = ['score', $rules['version'], $run, $action, $spin, $input['reel'] ?? null, $ordinal];
            Challenge::act($game, $action, $input, $rules, Engine::stream($seed, json_encode($address, JSON_THROW_ON_ERROR)));
        }
        $score = $game['scoreUnits'];
        $scores[$score] = ($scores[$score] ?? 0) + 1;
        $scoreTotal += $score;
        $normalSpins += $game['normalUsed'];
        $respins += $game['runStats']['respinsUsed'];
        $bonuses += $game['runStats']['bonuses'];
    }
    return [
        'strategy'=>$rerollBlanks ? 'respin reel three on the first two zero-return previews' : 'keep every board',
        'completedRuns'=>$count, 'normalSpins'=>$normalSpins, 'bonusSpins'=>$bonusSpins,
        'cornerRushActivations'=>$bonuses, 'respinsUsed'=>$respins,
        'meanScoreUnits'=>$scoreTotal / $count, 'scoreUnitsPercentiles'=>percentiles($scores),
    ];
}

try {
    $args = getopt('', ['rules:', 'spins:', 'runs:', 'seed:', 'board:', 'multiplier:', 'help']);
    if (isset($args['help'])) {
        echo "php sim.php [--rules=fruit-v2] [--spins=10000] [--seed=orchard-2026]\n";
        echo "php sim.php --runs=1000 [--rules=fruit-v2] [--seed=orchard-2026]\n";
        echo "php sim.php --board=FILE_OR_- [--rules=legacy-v1] [--multiplier=1]\n";
        exit;
    }
    $rules = Rules::get($args['rules'] ?? null);
    $seed = $args['seed'] ?? $rules['simulation']['seed'];
    if (isset($args['board'])) {
        $path = $args['board'] === '-' ? 'php://stdin' : $args['board'];
        $board = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $multiplier = filter_var($args['multiplier'] ?? 1, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1, 'max_range'=>2]]);
        if (!$multiplier) {
            throw new InvalidArgumentException('Choose a multiplier of 1 or 2.');
        }
        $report = Engine::evaluate($board, $rules, $multiplier);
    } elseif (isset($args['runs'])) {
        $count = filter_var($args['runs'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if (!$count || $rules['version'] !== 'fruit-v2') {
            throw new InvalidArgumentException('Use a positive run count and a rules version supporting Score Attack (fruit-v2).');
        }
        $report = ['rulesVersion'=>$rules['version'], 'seed'=>$seed, 'pairedStrategies'=>[
            simulateRuns($rules, $count, $seed, false), simulateRuns($rules, $count, $seed, true),
        ]];
    } else {
        $count = filter_var($args['spins'] ?? $rules['simulation']['normalSpins'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if (!$count) {
            throw new InvalidArgumentException('Use a positive integer number of spins.');
        }
        $report = simulate($rules, $count, $seed);
    }
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
