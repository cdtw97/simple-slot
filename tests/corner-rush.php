<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../src/GameState.php';

function expectSame(mixed $actual, mixed $expected, string $description): void
{
    if ($actual !== $expected) {
        throw new RuntimeException($description . ' expected ' . json_encode($expected)
            . ', got ' . json_encode($actual));
    }
}

function fixture(array $rows): array
{
    $symbols = array_keys(Rules::get('fruit-v1')['symbols']);
    return array_map(static fn ($row) => array_map(
        static fn ($letter) => $symbols[ord($letter) - ord('A')], str_split($row)
    ), $rows);
}

function rejected(array &$game, string $action, string $reason, array $rules): void
{
    $before = $game;
    try {
        GameState::act($game, $action, [], $rules);
    } catch (GameError $e) {
        expectSame($e->reason, $reason, "rejected $action");
        expectSame($game, $before, "unchanged state after $action");
        return;
    }
    throw new RuntimeException("Unexpectedly allowed $action");
}

$rules = Rules::get('fruit-v1');
$none = fixture(['ABCDEF', 'CDEFGH', 'EFGHIJ']);
$pair = fixture(['AACDEF', 'CDEFGH', 'EFGHIJ']);
$overlap = fixture(['AACDEF', 'CAEFGH', 'EFAHIJ']);
$corners = fixture(['ABCDEA', 'CDEFGH', 'AFGHIA']);
$all = fixture(['AAAAAA', 'AAAAAA', 'AAAAAA']);

// A pair and a diagonal sharing a corner charge the same lamp once.
$game = GameState::initial('free');
GameState::settle($game, $overlap, false, $rules);
expectSame(count($game['result']['awards']), 2, 'row plus diagonal awards');
expectSame($game['lamps'], [true, false, false, false], 'one unique lamp');
GameState::settle($game, $none, false, $rules);
expectSame($game['lamps'], [true, false, false, false], 'lamp survives an empty spin');

// A below-stake pair completes the meter, leaving insufficient balance for another base spin.
$game = GameState::initial('free', 500);
$game['lamps'] = [false, true, true, true];
GameState::settle($game, $pair, false, $rules);
expectSame($game['walletUnits'], 315, 'partial return');
expectSame($game['result']['netUnits'], -185, 'net loss');
expectSame($game['result']['triggered'], true, 'qualifying low return');
expectSame($game['bonusRemaining'], 3, 'single earned bonus');
expectSame($game['lamps'], [false, false, false, false], 'trigger clears lamps');
$earned = $game;
rejected($game, 'reset_free_play', 'finish_bonus', $rules);
rejected($game, 'spin', 'finish_bonus', $rules);
GameState::act($game, 'advance_bonus', [], $rules);
expectSame($game['result']['costUnits'], 0, 'earned spin at a low balance');
expectSame($game['bonusRemaining'], 2, 'exactly one bonus consumed');

// Reload across each boundary, confirm the final multiplier, total and lamp state.
$game = $earned;
foreach ([$all, $none, $corners] as $i => $board) {
    $game = json_decode(json_encode($game, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    GameState::settle($game, $board, true, $rules);
    expectSame($game['result']['costUnits'], 0, 'bonus stake');
    expectSame($game['result']['payoutUnits'], [39820, 0, 1180][$i], 'double each award');
    expectSame($game['lamps'], [false, false, false, false], 'bonus never charges lamps');
    expectSame($game['result']['triggered'], false, 'bonus never retriggers');
    expectSame($game['bonusRemaining'], 2 - $i, 'remaining count');
}
expectSame($game['walletUnits'], 41315, 'final wallet after three free spins');
expectSame($game['result']['bonusFinished'], true, 'bonus summary');
expectSame($game['result']['bonusTotalUnits'], 41000, 'sum of bonus awards');

$game = GameState::initial('free');
GameState::settle($game, $corners, false, $rules);
expectSame($game['result']['payoutUnits'], 590, 'natural corners retain ordinary award');
expectSame($game['bonusRemaining'], 3, 'natural corners queue just one bonus');

$game = GameState::initial('free', 499);
$game['lamps'] = [true, false, false, true];
rejected($game, 'spin', 'insufficient_credits', $rules);
GameState::act($game, 'reset_free_play', [], $rules);
expectSame($game['walletUnits'], 1000000, 'insufficient balance refill');
expectSame($game['lamps'], [false, false, false, false], 'refill clears lamps');
expectSame($game['bonusRemaining'], 0, 'refill cannot replay previous bonus');

echo "Passed overlapping lamps, persistence, low-wallet trigger, free entry, exact multiplier, no retrigger, total, natural four corners, reset guards and refill.\n";
