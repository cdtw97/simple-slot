<?php
declare(strict_types=1);

final class Collection
{
    // Each counter is advanced only while committing a new final result.
    private const BADGES = [
        'first_row' => ['First harvest', 'Earn a horizontal award', 'rows', 1],
        'first_diagonal' => ['On the diagonal', 'Earn a diagonal award', 'diagonals', 1],
        'first_corners' => ['Four of a kind', 'Earn a four-corner award', 'corners', 1],
        'first_rush' => ['Rush hour', 'Trigger Corner Rush', 'bonuses', 1],
        'first_run' => ['Full card', 'Complete a Score Attack', 'scoreRuns', 1],
        'all_fruits' => ['Fruit basket', 'Earn an award with every fruit', 'fruitCount', 11],
        'five_runs' => ['Regular player', 'Complete five challenge runs', 'completedRuns', 5],
        'six_row' => ['Sweet six', 'Earn a six-fruit row', 'longestRow', 6],
    ];
    private const PALETTES = [
        'jade' => ['Jade', null], 'citrus' => ['Citrus', 'first_row'],
        'twilight' => ['Twilight', 'first_rush'], 'plum' => ['Plum', 'first_run'],
        'berry' => ['Berry', 'five_runs'],
    ];

    public static function upgrade(array $profile): array
    {
        $profile += ['version' => 1, 'palette' => 'jade', 'achievements' => [], 'stats' => []];
        $profile['stats'] += ['rows' => 0, 'diagonals' => 0, 'corners' => 0, 'longestRow' => 0,
            'bonuses' => 0, 'scoreRuns' => 0, 'completedRuns' => 0, 'fruitCount' => 0, 'fruits' => []];
        return $profile;
    }

    public static function apply(array &$profile, array &$game): void
    {
        $result = $game['result'];
        $stats = &$profile['stats'];
        foreach ($result['awards'] as $award) {
            $key = ['row' => 'rows', 'diagonal' => 'diagonals', 'corners' => 'corners'][$award['type']];
            $stats[$key]++;
            if ($key === 'rows') {
                $stats['longestRow'] = max($stats['longestRow'], $award['length']);
            }
            if (!in_array($award['symbol'], $stats['fruits'], true)) {
                $stats['fruits'][] = $award['symbol'];
            }
        }
        $stats['fruitCount'] = count($stats['fruits']);
        $stats['bonuses'] += (int)$result['triggered'];
        if ($game['phase'] === 'run_complete') {
            $stats['completedRuns']++;
            $stats['scoreRuns'] += (int)($game['mode'] === 'score');
        }
        foreach (self::BADGES as $key => [, , $metric, $target]) {
            if ($stats[$metric] >= $target && !in_array($key, $profile['achievements'], true)) {
                $profile['achievements'][] = $key;
                if ($game['mode'] !== 'free') {
                    $game['runStats']['newAchievements'][] = $key;
                }
            }
        }
    }

    public static function equip(array &$profile, mixed $palette): void
    {
        if (!is_string($palette) || !isset(self::PALETTES[$palette])) {
            throw new GameError('invalid_palette', 'Choose a cabinet palette.', 422);
        }
        $needed = self::PALETTES[$palette][1];
        if ($needed && !in_array($needed, $profile['achievements'], true)) {
            throw new GameError('palette_locked', 'Earn the listed achievement to equip this palette.');
        }
        $profile['palette'] = $palette;
    }

    public static function view(array $profile): array
    {
        foreach (self::BADGES as $id => [$name, $description, $metric, $target]) {
            $profile['catalog'][] = compact('id', 'name', 'description', 'target') + [
                'earned' => in_array($id, $profile['achievements'], true),
                'progress' => min($profile['stats'][$metric], $target),
            ];
        }
        foreach (self::PALETTES as $id => [$name, $required]) {
            $profile['palettes'][] = compact('id', 'name') + [
                'condition' => $required ? self::BADGES[$required][1] : 'Starting palette',
                'unlocked' => !$required || in_array($required, $profile['achievements'], true),
            ];
        }
        return $profile;
    }
}
