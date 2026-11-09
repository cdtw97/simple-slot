<?php
declare(strict_types=1);
require_once __DIR__ . '/Engine.php';

final class GameError extends RuntimeException
{
    public function __construct(public string $reason, string $message, public int $status = 409)
    {
        parent::__construct($message);
    }
}

final class GameState
{
    public static function initial(string $mode, ?int $walletUnits = null): array
    {
        $rules = Rules::get();
        return [
            'id' => bin2hex(random_bytes(16)), 'mode' => $mode,
            'revision' => 0, 'phase' => $mode === 'free' ? 'idle' : 'not_started',
            'rulesVersion' => $rules['version'], 'board' => $rules['displayBoard'],
            'result' => null, 'pending' => null, 'record' => null,
            'walletUnits' => $mode === 'free' ? ($walletUnits ?? $rules['startingWalletUnits']) : null,
            'scoreUnits' => 0, 'normalUsed' => 0, 'respinsLeft' => 0,
            'lamps' => [false, false, false, false],
            'bonusRemaining' => 0, 'bonusTotalUnits' => 0,
        ];
    }

    public static function act(array &$state, string $action, array $input, array $rules): void
    {
        if ($action === 'advance_bonus') {
            if ($state['bonusRemaining'] <= 0) {
                throw new GameError('no_bonus', 'There are no free spins to play.');
            }
            self::settle($state, Engine::board($rules), true, $rules);
            return;
        }
        if ($state['phase'] !== 'idle') {
            throw new GameError('finish_bonus', 'Finish your earned free spins first.');
        }
        if ($action === 'reset_free_play') {
            if ($state['walletUnits'] >= $rules['stakeUnits']) {
                throw new GameError('reset_unavailable', 'A free refill becomes available below the spin cost.');
            }
            $revision = $state['revision'];
            $state = self::initial('free');
            $state['revision'] = $revision;
            return;
        }
        if ($action !== 'spin') {
            throw new GameError('invalid_action', 'Choose a valid game action.', 422);
        }
        if ($state['walletUnits'] < $rules['stakeUnits']) {
            throw new GameError('insufficient_credits', 'Refill your credits to start a new game.');
        }
        $state['normalUsed']++;
        self::settle($state, Engine::board($rules), false, $rules);
    }

    public static function settle(array &$state, array $board, bool $bonus, array $rules): void
    {
        $award = Engine::evaluate($board, $rules, $bonus ? $rules['bonusMultiplier'] : 1);
        $cost = !$bonus && $state['mode'] === 'free' ? $rules['stakeUnits'] : 0;
        $net = $award['payoutUnits'] - $cost;
        if ($state['mode'] === 'free') {
            $state['walletUnits'] += $net;
        } else {
            $state['scoreUnits'] += $award['payoutUnits'];
        }

        $lit = [];
        $triggered = false;
        if ($bonus) {
            $state['bonusRemaining']--;
            $state['bonusTotalUnits'] += $award['payoutUnits'];
        } else {
            $before = $state['lamps'];
            $state['lamps'] = Engine::chargedLamps($before, $award['awards'], $rules);
            foreach ($state['lamps'] as $i => $isLit) {
                if ($isLit && !$before[$i]) {
                    $lit[] = $i;
                }
            }
            $triggered = !in_array(false, $state['lamps'], true);
            if ($triggered) {
                $state['lamps'] = [false, false, false, false];
                $state['bonusRemaining'] = $rules['bonusSpins'];
                $state['bonusTotalUnits'] = 0;
            }
        }
        $state['phase'] = $state['bonusRemaining'] ? ($triggered ? 'bonus_ready' : 'bonus_in_progress') : 'idle';
        $state['board'] = $board;
        $state['result'] = $award + [
            'id' => bin2hex(random_bytes(12)), 'kind' => $bonus ? 'bonus' : 'normal',
            'costUnits' => $cost, 'netUnits' => $net, 'lit' => $lit, 'triggered' => $triggered,
            'bonusFinished' => $bonus && !$state['bonusRemaining'], 'bonusTotalUnits' => $state['bonusTotalUnits'],
        ];
        $state['pending'] = null;
    }
}
