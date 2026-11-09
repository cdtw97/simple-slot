<?php
declare(strict_types=1);

/** Published versions are immutable. A save stores its version alongside the state. */
final class Rules
{
    public const CURRENT = 'fruit-v2';

    public static function get(?string $version = null): array
    {
        $version ??= self::CURRENT;
        if (!in_array($version, ['legacy-v1', 'fruit-v1', 'fruit-v2'], true)) {
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
        $rules = [
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
        if ($version === 'fruit-v2') {
            $tiers = [
                'common'=>[10, ['apple','orange','lemon','watermelon']],
                'medium'=>[8, ['grape','cherry','pear','pineapple']],
                'premium'=>[5, ['coconut','kiwi','strawberry']],
            ];
            foreach ($tiers as $tier => [$weight, $fruits]) {
                foreach ($fruits as $id) {
                    $rules['symbols'][$id]['tier'] = $tier;
                    $rules['symbols'][$id]['weight'] = $weight;
                }
            }
            $rules['wild'] = 'wild';
            $rules['symbols']['wild'] = ['name'=>'Wild', 'glyph'=>'★', 'weight'=>1, 'tier'=>'special'];
            $rules['paytableBps'] = [
                'common'=>['row'=>[2=>3600, 3=>9000, 4=>20000, 5=>50000, 6=>130000],
                    'diagonal'=>9000, 'corners'=>20000],
                'medium'=>['row'=>[2=>4500, 3=>11500, 4=>28000, 5=>80000, 6=>200000],
                    'diagonal'=>11500, 'corners'=>28000],
                'premium'=>['row'=>[2=>6800, 3=>18500, 4=>50000, 5=>150000, 6=>400000],
                    'diagonal'=>18500, 'corners'=>50000],
            ];
        }
        return $rules;
    }
}
