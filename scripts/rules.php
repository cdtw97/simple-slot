<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../src/Engine.php';

function credits(int $units): string
{
    return number_format($units / 100, 2, '.', ',');
}

$doc = "# Fruit Arcade rules\n\n";
$doc .= "A normal Free Play spin costs 5.00 virtual credits. Every board has three rows and six reels. ";
$doc .= "A horizontal match pays once at its maximal length, starting at any reel. Each of the eight three-cell diagonals and the group of four corners may also pay. Overlapping patterns add. ";
$doc .= "Each award is rounded half up to hundredths of a credit after its multipliers, then the awards are summed.\n\n";
$doc .= "On a final normal board, each corner touched by an award lights once. Completing all four clears the lamps and queues three free spins at double payouts. Bonus spins leave lamps and normal spin counts unchanged. ";
$doc .= "The triggering rules stay in effect until the earned bonus finishes.\n";

foreach (array_unique([Rules::CURRENT, 'fruit-v1', 'legacy-v1']) as $version) {
    $rules = Rules::get($version);
    $doc .= "\n## $version\n\n";
    $doc .= "| Fruit | Draw weight per fruit | Pair | Three | Four | Five | Six | Diagonal | Corners |\n";
    $doc .= "| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |\n";
    $groups = [];
    foreach ($rules['symbols'] as $id => $symbol) {
        if ($id !== 'wild') {
            $groups[$symbol['tier'] . '-' . $symbol['weight']][] = $id;
        }
    }
    foreach ($groups as $members) {
        $fruit = $members[0];
        $columns = [implode(', ', $members), $rules['symbols'][$fruit]['weight']];
        foreach ([2,3,4,5,6] as $length) {
            $columns[] = credits(Engine::payoutUnits($rules, $fruit, 'row', $length));
        }
        $columns[] = credits(Engine::payoutUnits($rules, $fruit, 'diagonal', 3));
        $columns[] = credits(Engine::payoutUnits($rules, $fruit, 'corners', 4));
        $doc .= '| ' . implode(' | ', $columns) . " |\n";
    }
    $doc .= "\nCorner Rush free spins per activation: " . $rules['bonusSpins'] . ".\n";
    if (isset($rules['symbols']['wild'])) {
        $doc .= "\nWild draw weight: " . $rules['symbols']['wild']['weight'] . ". A wild may represent a fruit already in the pattern. All-wild patterns pay zero. In a row, the engine chooses one non-overlapping interpretation with the highest total payout. Ties use canonical fruit ID order. Each diagonal and the corners interpret wilds independently.\n";
    }
}
file_put_contents(__DIR__ . '/../docs/rules.md', $doc);
