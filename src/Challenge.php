<?php
declare(strict_types=1);

final class Challenge
{
    public static function start(array &$game, array $rules): void
    {
        if (!in_array($game['phase'], ['not_started', 'run_complete'], true)) {
            throw new GameError('active_run', 'Resume or abandon the current run first.');
        }
        $revision = $game['revision'];
        $game = GameState::initial($game['mode']);
        $game['revision'] = $revision;
        $game['rulesVersion'] = $rules['version'];
        $game['phase'] = 'idle';
        $game['respinsLeft'] = $rules['respinCharges'];
        $game['runStats'] = ['largestAwardUnits' => 0, 'bonuses' => 0, 'longestRow' => 0,
            'diagonals' => 0, 'respinsUsed' => 0, 'newAchievements' => []];
    }

    public static function act(array &$game, string $action, array $input, array $rules, ?callable $draw = null): void
    {
        if ($action === 'start_run') {
            self::start($game, $rules);
            return;
        }
        if (in_array($game['phase'], ['not_started', 'run_complete'], true)) {
            throw new GameError('no_active_run', 'Start a run to play.');
        }
        if ($action === 'abandon_run') {
            $revision = $game['revision'];
            $game = GameState::initial($game['mode']);
            $game['revision'] = $revision;
            return;
        }
        if ($game['bonusRemaining']) {
            if ($action !== 'advance_bonus') {
                throw new GameError('finish_bonus', 'Play your earned Corner Rush spins first.');
            }
            GameState::settle($game, Engine::board($rules, $draw), true, $rules);
        } elseif ($game['phase'] === 'awaiting_choice') {
            if (!in_array($action, ['keep', 'respin'], true)) {
                throw new GameError('choice_required', 'Keep the current board or select one reel to respin.');
            }
            $board = $game['board'];
            if ($action === 'respin') {
                $reel = $input['reel'] ?? null;
                if (!is_int($reel) || $reel < 0 || $reel >= $rules['columns'] || $game['respinsLeft'] < 1) {
                    throw new GameError('invalid_reel', 'Choose one of the six reels to respin.', 422);
                }
                foreach (Engine::reel($rules, $draw) as $row => $symbol) {
                    $board[$row][$reel] = $symbol;
                }
                $game['respinsLeft']--;
                $game['runStats']['respinsUsed']++;
            }
            GameState::settle($game, $board, false, $rules);
        } else {
            if ($action !== 'spin' || $game['normalUsed'] >= $rules['runLength']) {
                throw new GameError('invalid_action', 'Choose an available run action.', 422);
            }
            $game['normalUsed']++;
            $board = Engine::board($rules, $draw);
            if ($game['respinsLeft']) {
                $game['board'] = $board;
                $game['phase'] = 'awaiting_choice';
                $game['pending'] = Engine::evaluate($board, $rules);
                $game['result'] = null;
                return;
            }
            GameState::settle($game, $board, false, $rules);
        }
        $result = $game['result'];
        $stats = &$game['runStats'];
        $stats['largestAwardUnits'] = max($stats['largestAwardUnits'], $result['payoutUnits']);
        $stats['bonuses'] += (int)$result['triggered'];
        foreach ($result['awards'] as $award) {
            if ($award['type'] === 'row') {
                $stats['longestRow'] = max($stats['longestRow'], $award['length']);
            }
            $stats['diagonals'] += (int)($award['type'] === 'diagonal');
        }
        if ($game['normalUsed'] === $rules['runLength'] && !$game['bonusRemaining']) {
            $game['phase'] = 'run_complete';
            $game['lamps'] = [false, false, false, false];
            $game['respinsLeft'] = 0;
        }
    }
}
