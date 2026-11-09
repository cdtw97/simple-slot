<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../src/Engine.php';

function same($expected, $actual, string $what): void
{
    if ($expected !== $actual) {
        throw new RuntimeException("$what: expected " . json_encode($expected) . ', received ' . json_encode($actual));
    }
}

$rules = Rules::get('legacy-v1');
$blank = $rules['displayBoard'];
same(['awards'=>[], 'payoutUnits'=>0], Engine::evaluate($blank, $rules), 'quiet board');

foreach ([2=>315, 3=>540, 4=>590, 5=>640, 6=>5000] as $length => $amount) {
    $board = $blank;
    $start = min(2, 6 - $length);
    $cells = [];
    for ($c = $start; $c < $start + $length; $c++) {
        $board[1][$c] = 'orange';
        $cells[] = [1, $c];
    }
    $awards = Engine::evaluate($board, $rules)['awards'];
    same(1, count($awards), 'one maximal row');
    same([$cells, $amount, 'orange', 'row', $length],
        [$awards[0]['cells'], $awards[0]['payoutUnits'], $awards[0]['symbol'], $awards[0]['type'], $awards[0]['length']], 'interior and trailing runs');
}
$board = $blank;
$board[1] = ['kiwi','kiwi','lemon','kiwi','kiwi','pear'];
$result = Engine::evaluate($board, $rules);
same([[[1,0],[1,1]], [[1,3],[1,4]]], array_column($result['awards'], 'cells'), 'separated pairs');
same(630, $result['payoutUnits'], 'separated pair value');

$diagonals = [];
for ($c = 0; $c < 4; $c++) {
    $diagonals[] = [[0,$c], [1,$c+1], [2,$c+2]];
    $diagonals[] = [[0,$c+2], [1,$c+1], [2,$c]];
}
foreach ($diagonals as $cells) {
    $board = $blank;
    foreach ($cells as [$r, $c]) {
        $board[$r][$c] = 'kiwi';
    }
    $award = array_values(array_filter(Engine::evaluate($board, $rules)['awards'], static fn($a) => $a['type'] === 'diagonal'));
    same([$cells], array_column($award, 'cells'), 'one exact diagonal');
    same(540, $award[0]['payoutUnits'], 'diagonal value');
}
$board = $blank;
foreach ([[0,5],[1,0],[2,1]] as [$r, $c]) {
    $board[$r][$c] = 'coconut';
}
same(0, Engine::evaluate($board, $rules)['payoutUnits'], 'a wrapped diagonal cannot win');

$board = array_fill(0, 3, array_fill(0, 6, 'apple'));
$result = Engine::evaluate($board, $rules);
same([3,8,1], array_values(array_count_values(array_column($result['awards'], 'type'))), 'overlapping pattern counts');
same([[0,0],[0,5],[2,0],[2,5]], $result['awards'][11]['cells'], 'four corners in order');
same(19910, $result['payoutUnits'], 'additive line, diagonal, corner awards');
same(12, count(array_unique(array_column($result['awards'], 'id'))), 'unique IDs per spin, including identical rows');
same($result, Engine::evaluate($board, $rules), 'repeatable award lists');
same($result, Engine::evaluate($board, Rules::get('fruit-v1')), 'first release retains legacy base payouts');
same(39820, Engine::evaluate($board, $rules, 2)['payoutUnits'], 'bonus award rounding and sum');
same([true,true,true,true], Engine::chargedLamps([false,false,false,false], $result['awards'], $rules), 'overlap charges each corner once');

$fraction = $rules;
$fraction['paytableBps']['classic']['row'][2] = 6310;
same(316, Engine::payoutUnits($fraction, 'kiwi', 'row', 2), 'round half up');
same(631, Engine::payoutUnits($fraction, 'kiwi', 'row', 2, 2), 'multiply before rounding');
$a = Engine::board($rules, Engine::stream('sample', 'normal/17'));
same($a, Engine::board($rules, Engine::stream('sample', 'normal/17')), 'same seed and draw namespace');
same(false, $a === Engine::board($rules, Engine::stream('sample', 'bonus/17')), 'distinct draw namespace');

// Exercise the actual CLI entry point on the same explicit board used by game settlement.
$proc = proc_open([PHP_BINARY, __DIR__ . '/../sim.php', '--rules=legacy-v1', '--board=-'],
    [['pipe','r'],['pipe','w'],['pipe','w']], $pipes);
fwrite($pipes[0], json_encode($board));
fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
fclose($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[2]);
same(0, proc_close($proc), 'CLI exit ' . $stderr);
same($result, json_decode($stdout, true, 512, JSON_THROW_ON_ERROR), 'simulator/game evaluator parity');
echo "Engine fixtures passed: legacy geometry, all eight diagonals, integer payouts, stable draws, simulator parity.\n";

$tiers = Rules::get('fruit-v2');
same([
    ['cherry','pineapple','grape','orange','lemon','orange'],
    ['orange','grape','orange','strawberry','coconut','grape'],
    ['watermelon','grape','lemon','cherry','orange','orange'],
], Engine::board($tiers, Engine::stream('release-vector', 'normal/17')), 'pinned weighted draw sequence');
same(array_column($result['awards'], 'cells'), array_column(Engine::evaluate($board, $tiers)['awards'], 'cells'),
    'boards without wilds preserve legacy geometry');
$allWild = array_fill(0, 3, array_fill(0, 6, 'wild'));
same(['awards'=>[], 'payoutUnits'=>0], Engine::evaluate($allWild, $tiers), 'all wilds cannot qualify for any award');

function firstRow(array $row, array $rules): array
{
    $matrix = $rules['displayBoard'];
    $matrix[0] = $row;
    return array_values(array_filter(Engine::evaluate($matrix, $rules)['awards'],
        static fn(array $a): bool => $a['type'] === 'row' && $a['cells'][0][0] === 0));
}
$awards = firstRow(['apple','wild','kiwi','lemon','orange','coconut'], $tiers);
same(['kiwi'], array_column($awards, 'symbol'), 'higher-paying pair wins');
same([[0,1],[0,2]], $awards[0]['cells'], 'wild participates once in a row');
same(340, $awards[0]['payoutUnits'], 'premium pair value');
$awards = firstRow(['orange','wild','apple','lemon','orange','coconut'], $tiers);
same(['apple'], array_column($awards, 'symbol'), 'canonical fruit order breaks equal ties even if apple appears later');
$awards = firstRow(['apple','wild','apple','kiwi','wild','kiwi'], $tiers);
same(['apple','kiwi'], array_column($awards, 'symbol'), 'best total across two wilds');
same([450,925], array_column($awards, 'payoutUnits'), 'two independently awarded maximal triples');

$board = [
    ['wild','apple','lemon','grape','orange','strawberry'],
    ['lemon','orange','kiwi','pear','grape','coconut'],
    ['wild','kiwi','orange','cherry','grape','strawberry'],
];
$awards = Engine::evaluate($board, $tiers)['awards'];
$sharedCorner = array_values(array_filter($awards, static fn($a) => in_array([0,0], $a['cells'], true)));
same(['apple','orange','strawberry'], array_column($sharedCorner, 'symbol'), 'three pattern types interpret one wild independently');
$board[1][1] = 'kiwi';
$board[2][5] = 'cherry';
$sharedCorner = array_filter(Engine::evaluate($board, $tiers)['awards'], static fn($a) => in_array([0,0], $a['cells'], true));
same(['row'], array_column($sharedCorner, 'type'), 'different real fruits reject mixed diagonal and corners');
echo "Wild fixtures passed: highest return, canonical tie, multi-wild optimization, independent interpretations and all-wild exclusion.\n";
