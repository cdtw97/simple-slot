<?php
declare(strict_types=1);

/** Published versions are immutable. A save stores its version alongside the state. */
final class Rules
{
    public const CURRENT = 'fruit-v1';

    public static function get(?string $version = null): array
    {
        $version ??= self::CURRENT;
        if (!in_array($version, ['legacy-v1', 'fruit-v1'], true)) {
            throw new InvalidArgumentException('Unknown rules version.');
        }
        $symbols = [];
        $glyphs = ['apple'=>'🍎', 'orange'=>'🍊', 'lemon'=>'🍋', 'watermelon'=>'🍉',
            'grape'=>'🍇', 'cherry'=>'🍒', 'pear'=>'🍐', 'pineapple'=>'🍍',
            'coconut'=>'🥥', 'kiwi'=>'🥝', 'strawberry'=>'🍓'];
        foreach ($glyphs as $id => $glyph) {
            $symbols[$id] = ['name'=>ucfirst($id), 'glyph'=>$glyph, 'weight'=>1, 'tier'=>'classic'];
        }
        $diagonals = [];
        foreach ([1, -1] as $step) {
            for ($col = 0; $col < 6; $col++) {
                $end = $col + 2 * $step;
                if ($end >= 0 && $end < 6) {
                    $diagonals[] = [[0, $col], [1, $col + $step], [2, $end]];
                }
            }
        }
        return [
            'version'=>$version, 'rows'=>3, 'columns'=>6, 'symbols'=>$symbols,
            'patterns'=>['minRow'=>2, 'diagonals'=>$diagonals,
                'corners'=>[[0, 0], [0, 5], [2, 0], [2, 5]]],
            'paytableBps'=>['classic'=>['row'=>[2=>6300, 3=>10800, 4=>11800, 5=>12800, 6=>100000],
                'diagonal'=>10800, 'corners'=>11800]],
            'stakeUnits'=>500, 'startingWalletUnits'=>1000000, 'unitsPerCredit'=>100,
            'bonusSpins'=>$version === 'legacy-v1' ? 0 : 3, 'bonusMultiplier'=>2,
            'runLength'=>30, 'respinCharges'=>2, 'wild'=>null,
            'celebrations'=>['largeWinUnits'=>2500],
            'simulation'=>['normalSpins'=>10000, 'seed'=>'orchard-2026'],
            'displayBoard'=>[
                ['apple', 'orange', 'lemon', 'watermelon', 'grape', 'cherry'],
                ['pear', 'pineapple', 'coconut', 'kiwi', 'strawberry', 'apple'],
                ['lemon', 'watermelon', 'grape', 'cherry', 'pear', 'pineapple'],
            ],
        ];
    }
}
