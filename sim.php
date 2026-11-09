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
        'normalAwardUnitsPercentiles'=>percentiles($normalSizes), 'bonusAwardUnitsPercentiles'=>percentiles($bonusSizes),
        'cornerRushActivations'=>$activations,
        'meanNormalSpinsPerCompletedMeter'=>$activations ? $previous / $activations : null,
        'normalSpinsPerCompletedMeterPercentiles'=>percentiles($intervals),
    ];
}

try {
    $args = getopt('', ['rules:', 'spins:', 'seed:', 'board:', 'multiplier:', 'help']);
    if (isset($args['help'])) {
        echo "php sim.php [--rules=fruit-v1] [--spins=10000] [--seed=orchard-2026]\n";
        echo "php sim.php --board=FILE_OR_- [--rules=legacy-v1] [--multiplier=1]\n";
        exit;
    }
    $rules = Rules::get($args['rules'] ?? null);
    if (isset($args['board'])) {
        $path = $args['board'] === '-' ? 'php://stdin' : $args['board'];
        $board = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $multiplier = filter_var($args['multiplier'] ?? 1, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1, 'max_range'=>2]]);
        if (!$multiplier) {
            throw new InvalidArgumentException('Choose a multiplier of 1 or 2.');
        }
        $report = Engine::evaluate($board, $rules, $multiplier);
    } else {
        $count = filter_var($args['spins'] ?? $rules['simulation']['normalSpins'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if (!$count) {
            throw new InvalidArgumentException('Use a positive integer number of spins.');
        }
        $report = simulate($rules, $count, $args['seed'] ?? $rules['simulation']['seed']);
    }
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
