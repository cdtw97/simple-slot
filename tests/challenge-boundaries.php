<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../src/GameState.php';
require_once __DIR__ . '/../src/Challenge.php';
require_once __DIR__ . '/../src/Collection.php';

function check(bool $ok, string $label): void
{
    if (!$ok) throw new RuntimeException($label);
}

// Stage normal spin 30 as an undecided board whose ordinary corner award must start a bonus.
$rules = Rules::get();
$game = GameState::initial('score');
Challenge::start($game, $rules);
$game['normalUsed'] = 30;
$game['phase'] = 'awaiting_choice';
$game['board'] = [
    ['apple', 'orange', 'lemon', 'watermelon', 'grape', 'apple'],
    ['lemon', 'watermelon', 'grape', 'cherry', 'pear', 'pineapple'],
    ['apple', 'cherry', 'pear', 'pineapple', 'coconut', 'apple'],
];
$game['pending'] = Engine::evaluate($game['board'], $rules);
$profile = Collection::upgrade([]);
check($game['scoreUnits'] === 0 && !$game['record'], 'undecided final spin must not score');
Challenge::act($game, 'keep', [], $rules);
Collection::apply($profile, $game);
check($game['bonusRemaining'] === 3 && $game['phase'] === 'bonus_ready', 'last normal spin must earn three spins');
check($profile['stats']['scoreRuns'] === 0, 'completed run counted before bonus');
check(count($game['result']['awards']) === 1 && $game['result']['awards'][0]['type'] === 'corners', 'natural corner');
$expectedScore = $game['result']['payoutUnits'];
for ($i = 1; $i <= 3; $i++) {
    Challenge::act($game, 'advance_bonus', [], $rules, static fn ($min, $max) => $min);
    Collection::apply($profile, $game);
    $expectedScore += $game['result']['payoutUnits'];
    check($game['normalUsed'] === 30 && $game['scoreUnits'] === $expectedScore, 'bonus score or spin count');
    check($game['bonusRemaining'] === 3 - $i && !$game['result']['triggered'], 'unwanted retrigger');
    check(($game['phase'] === 'run_complete') === ($i === 3), 'scorecard boundary');
}
check($profile['stats']['scoreRuns'] === 1 && $profile['stats']['completedRuns'] === 1, 'one completed run');
check(count($profile['achievements']) === count(array_unique($profile['achievements'])), 'duplicate achievement');
check($game['respinsLeft'] === 0 && !in_array(true, $game['lamps'], true), 'expire unused resources');
check(in_array('first_run', $profile['achievements'], true), 'first completed Score Attack');

echo "Passed final normal choice, natural corner trigger, three bonus boundaries, score aggregation, expired charges and settled achievements.\n";
