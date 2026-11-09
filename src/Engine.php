<?php
declare(strict_types=1);
require_once __DIR__ . '/Rules.php';

final class Engine
{
    /** Rejection sampling over SHA-256 HMAC blocks. Contexts are independent streams. */
    public static function stream(string $seed, string $context): Closure
    {
        $counter = 0;
        $words = [];
        return static function (int $min, int $max) use ($seed, $context, &$counter, &$words): int {
            $range = $max - $min + 1;
            if ($range <= 0 || $range > 4294967296) {
                throw new InvalidArgumentException('Invalid draw range.');
            }
            $limit = intdiv(4294967296, $range) * $range;
            do {
                if (!$words) {
                    $address = json_encode([$context, $counter++], JSON_THROW_ON_ERROR);
                    $bytes = hash_hmac('sha256', $address, $seed, true);
                    $words = array_reverse(unpack('N*', $bytes));
                }
                $word = array_pop($words);
            } while ($word >= $limit);
            return $min + $word % $range;
        };
    }

    private static function drawSymbols(int $count, array $rules, ?callable $draw): array
    {
        $draw ??= 'random_int';
        $total = array_sum(array_column($rules['symbols'], 'weight'));
        $result = [];
        for ($i = 0; $i < $count; $i++) {
            $ticket = $draw(1, $total);
            foreach ($rules['symbols'] as $id => $symbol) {
                $ticket -= $symbol['weight'];
                if ($ticket <= 0) {
                    $result[] = $id;
                    break;
                }
            }
        }
        return $result;
    }

    public static function board(array $rules, ?callable $draw = null): array
    {
        $symbols = self::drawSymbols($rules['rows'] * $rules['columns'], $rules, $draw);
        return array_chunk($symbols, $rules['columns']);
    }

    public static function reel(array $rules, ?callable $draw = null): array
    {
        return self::drawSymbols($rules['rows'], $rules, $draw);
    }

    public static function payoutUnits(array $rules, string $symbol, string $type, int $length, int $multiplier = 1): int
    {
        $table = $rules['paytableBps'][$rules['symbols'][$symbol]['tier']];
        $basisPoints = $type === 'row' ? $table['row'][$length] : $table[$type];
        return intdiv($rules['stakeUnits'] * $basisPoints * $multiplier + 5000, 10000);
    }

    private static function award(array $rules, string $type, string $symbol, array $cells, int $multiplier): array
    {
        $length = count($cells);
        [$firstRow, $firstCol] = $cells[0];
        [, $lastCol] = $cells[$length - 1];
        return [
            'id'=>"$type-$firstRow-$firstCol-$lastCol", 'type'=>$type, 'symbol'=>$symbol,
            'length'=>$length, 'cells'=>$cells,
            'payoutUnits'=>self::payoutUnits($rules, $symbol, $type, $length, $multiplier),
        ];
    }

    private static function rowAwards(array $row, array $original, int $r, array $rules, int $multiplier): array
    {
        $awards = [];
        for ($start = 0; $start < count($row); $start = $end) {
            $symbol = $row[$start];
            $end = $start + 1;
            while ($end < count($row) && $row[$end] === $symbol) {
                $end++;
            }
            $length = $end - $start;
            if ($length < $rules['patterns']['minRow']
                || !in_array($symbol, array_slice($original, $start, $length), true)) {
                continue;
            }
            $cells = [];
            for ($c = $start; $c < $end; $c++) {
                $cells[] = [$r, $c];
            }
            $awards[] = self::award($rules, 'row', $symbol, $cells, $multiplier);
        }
        return $awards;
    }

    private static function bestRow(array $row, int $r, array $rules, int $multiplier): array
    {
        $wild = $rules['wild'];
        $wildColumns = array_keys($row, $wild, true);
        if (!$wildColumns) {
            return self::rowAwards($row, $row, $r, $rules, $multiplier);
        }
        // The registry's symbol order and left-to-right wild positions define the tie-break.
        $fruits = array_intersect(array_keys($rules['symbols']), array_diff($row, [$wild]));
        $candidates = [$row];
        foreach ($wildColumns as $c) {
            $expanded = [];
            foreach ($candidates as $candidate) {
                foreach ($fruits as $fruit) {
                    $candidate[$c] = $fruit;
                    $expanded[] = $candidate;
                }
            }
            $candidates = $expanded;
        }
        $best = [];
        $highest = -1;
        foreach ($candidates as $candidate) {
            $awards = self::rowAwards($candidate, $row, $r, $rules, $multiplier);
            $total = array_sum(array_column($awards, 'payoutUnits'));
            if ($total > $highest) {
                $highest = $total;
                $best = $awards;
            }
        }
        return $best;
    }

    public static function evaluate(array $board, array $rules, int $multiplier = 1): array
    {
        if (!array_is_list($board) || count($board) !== $rules['rows'] || $multiplier < 1) {
            throw new InvalidArgumentException('Expected a row-major board and a positive multiplier.');
        }
        $awards = [];
        foreach ($board as $r => $row) {
            if (!is_array($row) || !array_is_list($row) || count($row) !== $rules['columns']) {
                throw new InvalidArgumentException('Unexpected board dimensions.');
            }
            foreach ($row as $symbol) {
                if (!is_string($symbol) || !isset($rules['symbols'][$symbol])) {
                    throw new InvalidArgumentException('Unrecognized board symbol.');
                }
            }
            array_push($awards, ...self::bestRow($row, $r, $rules, $multiplier));
        }
        $groups = ['diagonal'=>$rules['patterns']['diagonals'], 'corners'=>[$rules['patterns']['corners']]];
        foreach ($groups as $type => $patterns) {
            foreach ($patterns as $cells) {
                $symbol = null;
                foreach ($cells as [$r, $c]) {
                    $cell = $board[$r][$c];
                    if ($cell === $rules['wild']) {
                        continue;
                    }
                    $symbol ??= $cell;
                    if ($cell !== $symbol) {
                        continue 2;
                    }
                }
                if ($symbol !== null) {
                    $awards[] = self::award($rules, $type, $symbol, $cells, $multiplier);
                }
            }
        }
        return ['awards'=>$awards, 'payoutUnits'=>array_sum(array_column($awards, 'payoutUnits'))];
    }

    /** Lamps in configured board-corner order, before checking for a full set. */
    public static function chargedLamps(array $lamps, array $awards, array $rules): array
    {
        foreach ($awards as $award) {
            foreach ($rules['patterns']['corners'] as $i => $corner) {
                if (in_array($corner, $award['cells'], true)) {
                    $lamps[$i] = true;
                }
            }
        }
        return $lamps;
    }
}
