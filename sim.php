<?php 


// Setup the initial state
$costPerSpin = 5;
$totalSpins = 10000; // Adjust the number of spins to simulate here

$totalSpent = $totalSpins * $costPerSpin;
$totalWon = 0;

// Frequency and payout for each type of win

$statistics = array(
    "2" => array("payout" => 0, "occurrences" => 0),
    "3" => array("payout" => 0, "occurrences" => 0),
    "4" => array("payout" => 0, "occurrences" => 0),
    "5" => array("payout" => 0, "occurrences" => 0),
    "6" => array("payout" => 0, "occurrences" => 0),
    "diag" => array("payout" => 0, "occurrences" => 0),
    "four_corner" => array("payout" => 0, "occurrences" => 0)
);

//create the for loop.

for($i = 0; $i<=$totalSpins;$i++){

    $matrix3x6 = spin();
    $lineWins = checkForLineWin($matrix3x6);
    $diagonalWins = checkForDiagonalWin($matrix3x6);
    $fourCornerWins = checkFourCorners($matrix3x6);

    $payout = calculatePayout($lineWins, $diagonalWins, $fourCornerWins, $costPerSpin);
    if(!empty($payout['payoutDetails'])){
         for($i2 = 0; $i2 <= count($payout['payoutDetails'])-1;$i2++){

            //check for a line win
            if($payout['payoutDetails'][$i2]['type'] === "line"){
                //determin which type of line was won
                if($payout['payoutDetails'][$i2]['length'] === 2){
                  
                    $statistics['2']['payout'] = $statistics['2']['payout'] + $payout['payoutDetails'][$i2]['payout'];
                    $statistics['2']['occurrences'] = $statistics['2']['occurrences'] + 1;

                }
                if($payout['payoutDetails'][$i2]['length'] === 3){
                    $statistics['3']['payout'] = $statistics['3']['payout'] + $payout['payoutDetails'][$i2]['payout'];
                    $statistics['3']['occurrences'] = $statistics['3']['occurrences'] + 1;
                }
                if($payout['payoutDetails'][$i2]['length'] === 4){
                    $statistics['4']['payout'] = $statistics['4']['payout'] + $payout['payoutDetails'][$i2]['payout'];
                    $statistics['4']['occurrences'] = $statistics['4']['occurrences'] + 1;
                }
                if($payout['payoutDetails'][$i2]['length'] === 5){
                    $statistics['5']['payout'] = $statistics['5']['payout'] + $payout['payoutDetails'][$i2]['payout'];
                    $statistics['5']['occurrences'] = $statistics['5']['occurrences'] + 1;
                }
                if($payout['payoutDetails'][$i2]['length'] === 6){
                    $statistics['6']['payout'] = $statistics['6']['payout'] + $payout['payoutDetails'][$i2]['payout'];
                    $statistics['6']['occurrences'] = $statistics['6']['occurrences'] + 1;
                }
            }

            //check for a diagnol line
            if($payout['payoutDetails'][$i2]['type'] === "diagonal"){
                $statistics['diag']['payout'] = $statistics['diag']['payout'] + $payout['payoutDetails'][$i2]['payout'];
                $statistics['diag']['occurrences'] = $statistics['diag']['occurrences'] + 1;
                
            }

            //check for a four corner win
            if($payout['payoutDetails'][$i2]['type'] === "four_corner"){
                $statistics['four_corner']['payout'] = $statistics['four_corner']['payout'] + $payout['payoutDetails'][$i2]['payout'];
                $statistics['four_corner']['occurrences'] = $statistics['four_corner']['occurrences'] + 1;
            }
         }
    }
    //do nothing, no payout was given. 
    
}
echo "Payout Table:<br>";
$totalWon = 0;
foreach ($statistics as $key => $value) {
   // echo  ($key == "diag" ? "Diagnol" : "Length: $key") . "  Payout: " . number_format($value['payout'], 2) . "  Occurrences: " . number_format($value['occurrences'], 1). "<br>";
    echo "Type: $key" . "  Payout: " . number_format($value['payout'], 2) . "  Occurrences: " . number_format($value['occurrences'], 1). "<br>";
    $totalWon += $value['payout'];
}

echo "<br>Win Probabilities:<br>";
foreach ($statistics as $key => $value) {
    echo "Type: $key"  . "  Probability: " . number_format($value['occurrences'] / $totalSpins * 100, 5) . "%<br>";
}

echo "<br>Total Spent: ". number_format($totalSpent, 2) . "<br>";
echo "Total Won: ". number_format($totalWon, 2) . "<br>";
echo "Payout Percentage: " . number_format($totalWon / $totalSpent * 100, 6) . "<br>";
echo "Percentage Difference: " . number_format(($totalWon / $totalSpent * 100) - 100, 6) . "<br>";






function spin() {
   
    $symbols = ['🍎', '🍊', '🍋', '🍉', '🍇', '🍒', '🍐', '🍍', '🥥', '🥝', '🍓']; // 11 symbols

    $rows = array_fill(0, 3, array_fill(0, 6, "")); // Create 3x6 matrix to represent the reels
    for ($i = 0; $i < 3; $i++) {
        for ($j = 0; $j < 6; $j++) {
            $rows[$i][$j] = $symbols[random_int(0, count($symbols) - 1)]; // Assign random symbols to reels
        }
    }
    return $rows;
   
}
function checkForLineWin($matrix) {
$winningLines = [];
$symbols = ['🍎', '🍊', '🍋', '🍉', '🍇', '🍒', '🍐', '🍍', '🥥', '🥝', '🍓'];

for ($row = 0; $row < count($matrix); $row++) { // for each row
    $current_symbol = $matrix[$row][0]; // start with the first symbol
    $current_length = 1; // we have at least one symbol
    
    for ($column = 1; $column < count($matrix[$row]); $column++) { // check rest of the row
        if ($matrix[$row][$column] == $current_symbol) { // if symbol is same as current, increase length
            $current_length++;
        } else { // if symbol is different, check if we hads a win before, then reset
            if (in_array($current_length, range(2, 6)) && in_array($current_symbol, $symbols)) {
                array_push($winningLines, ["row" => $row, "start" => $column - $current_length, "end" => $column - 1, "length" => $current_length, "symbol" => $current_symbol]);
            }
            $current_symbol = $matrix[$row][$column];
            $current_length = 1;
        }
    }
    // check for a winning line at the end of the row
    if (in_array($current_length, range(2, 6)) && in_array($current_symbol, $symbols)) {
        array_push($winningLines, ["row" => $row, "start" => count($matrix[$row]) - $current_length, "end" => count($matrix[$row]) - 1, "length" => $current_length, "symbol" => $current_symbol]);
    }
}
return $winningLines;
}
function checkForDiagonalWin($rows) {
$winningLines = [];

$directions = [[1, 1], [1, -1]]; // Directions to check: [1, 1] for diagonals from top-left to bottom-right, [1, -1] for diagonals from top-right to bottom-left

// Iterate over directions
foreach ($directions as $direction) {
    $dx = $direction[0];
    $dy = $direction[1];

    // Iterate over starting positions
    for ($x = 0; $x < count($rows[0]); $x++) {
        $firstSymbol = $rows[0][$x]; // Take the first symbol of the line
        $isWinningLine = true;

        // Check each position in the line
        for ($i = 1; $i < count($rows); $i++) {
            $y = $x + $i * $dy;
            // If the y position is out of bounds or the symbol doesn't match the first one, it's not a winning line
            if ($y < 0 || $y >= count($rows[$i]) || $rows[$i][$y] != $firstSymbol) {
                $isWinningLine = false;
                break;
            }
        }

        // If all symbols in the line are the same, it's a winning line
        if ($isWinningLine) {
            $winningLines[] = ['start' => [0, $x], 'end' => [2, $x + 2 * $dy], 'length' => 3, 'symbol' => $firstSymbol];
        }
    }
}

return $winningLines;
}
function checkFourCorners($rows) {
$winningLines = [];

$corners = [[0, 0], [0, 5], [2, 0], [2, 5]]; // Positions of the four corners

$firstSymbol = $rows[$corners[0][0]][$corners[0][1]]; // Take the symbol of the first corner
$isWinningLine = true;

// Check each corner
foreach ($corners as $corner) {
    // If a corner's symbol doesn't match the first one, it's not a winning line
    if ($rows[$corner[0]][$corner[1]] != $firstSymbol) {
        $isWinningLine = false;
        break;
    }
}

// If all corners have the same symbol, it's a winning line
if ($isWinningLine) {
    $winningLines[] = ['corners' => $corners, 'length' => 4, 'symbol' => $firstSymbol];
}

return $winningLines;
}
function calculatePayout($lineWins, $diagonalWins, $fourCornerWins, $costPerSpin) {
$payouts = [2 => 0.75215, 3 => 0.72, 4 => 1.02, 5 => 1.28, 6 => 10, 'diagonal' => 0.72, 'four_corner' => 0.72];
$totalPayout = 0;
$payoutDetails = [];

// Analyze line wins
foreach ($lineWins as $lineWin) {
    $payout = $costPerSpin * $payouts[$lineWin['length']];
    $totalPayout += $payout;
    $payoutDetails[] = ['type' => 'line', 'symbol' => $lineWin['symbol'], 'length' => $lineWin['length'], 'payout' => $payout];
}

// Analyze diagonal wins
foreach ($diagonalWins as $diagonalWin) {
    $payout = $costPerSpin * $payouts['diagonal'];
    $totalPayout += $payout;
    $payoutDetails[] = ['type' => 'diagonal', 'symbol' => $diagonalWin['symbol'], 'length' => $diagonalWin['length'], 'payout' => $payout];
}

// Analyze four corner wins
foreach ($fourCornerWins as $fourCornerWin) {
    $payout = $costPerSpin * $payouts['four_corner'];
    $totalPayout += $payout;
    $payoutDetails[] = ['type' => 'four_corner', 'symbol' => $fourCornerWin['symbol'], 'length' => $fourCornerWin['length'], 'payout' => $payout];
}

// Return total payout and details
return ['totalPayout' => $totalPayout, 'payoutDetails' => $payoutDetails];
}

function  dump_die($data)
{
    echo '<pre>';
    var_dump($data);
    echo '</pre>';
    die();
}














?>