<?php 

// Starting session
session_start();

// If session doesn't have 'tokens', it is initialized with 10000
if(!isset($_SESSION['tokens'])){
    $_SESSION['tokens'] = 10000;
}

// If session doesn't have 'costPerSpin', it is initialized with 5
if(!isset($_SESSION['costPerSpin'])){
    $_SESSION['costPerSpin'] = 5;
}

// Checking if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
   
    // Checking if the cost per spin is greater than available tokens
    if($_SESSION['costPerSpin'] > $_SESSION['tokens']){
        // If not enough tokens, we return a message saying so
        echo json_encode(array('error' => 'Sorry you dont have enough tokens... Tokens: ' . $_SESSION['tokens']));
    }  else{
        // If enough tokens, we deduct the cost per spin from total tokens
        $_SESSION['tokens'] = $_SESSION['tokens'] - $_SESSION['costPerSpin']; 

        // Run the spin function and store the result in a variable
        $matrix3x6 = spin();
        // Check for line win
        $lineWins = checkForLineWin($matrix3x6);
        // Check for diagonal win
        $diagonalWins = checkForDiagonalWin($matrix3x6);
        // Check for four corner win
        $fourCornerWins = checkFourCorners($matrix3x6);
        // Calculate the total payout
        $payout = calculatePayout($lineWins, $diagonalWins, $fourCornerWins, $_SESSION['costPerSpin']);
        
        // Add the payout to the total tokens
        $_SESSION['tokens'] = $_SESSION['tokens'] + $payout['totalPayout'];


        $matrix_renewed = array();

        for ($reel = 0; $reel < 6; $reel++) {
            for ($row = 0; $row < 3; $row++) {
                $matrix_renewed['reel' . ($reel + 1)][$row] = $matrix3x6[$row][$reel];
            }
        }
      
        // Prepare the result array
        $result = array(
            'matrix' => $matrix_renewed,             // The 3x6 matrix from the spin
            'lineWins' => $lineWins,            // Line win results
            'diagonalWins' => $diagonalWins,    // Diagonal win results
            'cornerWins' => $fourCornerWins,    // Four corner win results
            'tokens' => $_SESSION['tokens'],    // Remaining token count after the spin
            'payout' => $payout                 // Details of the payout
        );
        //dump_die($result);
        //dump_die($result);
       //dump_die($result);
        // Return the result as JSON
        echo json_encode($result);
                
    }

}


function spin() {
    // Define the symbols
    $symbols = ['🍎', '🍊', '🍋', '🍉', '🍇', '🍒', '🍐', '🍍', '🥥', '🥝', '🍓']; // 11 symbols

    // Create a 3x6 matrix to represent the reels. Initially all positions are empty ("")
    $rows = array_fill(0, 3, array_fill(0, 6, ""));

    // Iterate over each row
    for ($i = 0; $i < 3; $i++) {
        // Iterate over each column
        for ($j = 0; $j < 6; $j++) {
            // Assign a random symbol from the $symbols array to each position in the matrix
            $rows[$i][$j] = $symbols[random_int(0, count($symbols) - 1)];
        }
    }

    // Return the filled matrix
    return $rows;
}
function checkForLineWin($matrix) {
    // Define winningLines as an empty array to hold the winning lines data.
    $winningLines = [];
    
    // List of possible symbols in the game.
    $symbols = ['🍎', '🍊', '🍋', '🍉', '🍇', '🍒', '🍐', '🍍', '🥥', '🥝', '🍓'];

    // Loop through each row in the matrix
    for ($row = 0; $row < count($matrix); $row++) {
        // Set the current symbol to the first symbol in the row
        $current_symbol = $matrix[$row][0];
        
        // Start counting the current sequence length
        $current_length = 1; 
        
        // Loop through the rest of the columns in the row
        for ($column = 1; $column < count($matrix[$row]); $column++) {
            // If the symbol is the same as the current symbol, increase the sequence length
            if ($matrix[$row][$column] == $current_symbol) {
                $current_length++;
            } else { 
                // If the symbol is different, check if the sequence length and the symbol make a winning combination
                if (in_array($current_length, range(2, 6)) && in_array($current_symbol, $symbols)) {
                    // If it's a winning combination, add it to the winningLines array
                    array_push($winningLines, ["row" => $row, "start" => $column - $current_length, "end" => $column - 1, "length" => $current_length, "symbol" => $current_symbol]);
                }
                // Start a new sequence with the new symbol
                $current_symbol = $matrix[$row][$column];
                $current_length = 1;
            }
        }
        // After checking the whole row, check if the last sequence is a winning line
        if (in_array($current_length, range(2, 6)) && in_array($current_symbol, $symbols)) {
            array_push($winningLines, ["row" => $row, "start" => count($matrix[$row]) - $current_length, "end" => count($matrix[$row]) - 1, "length" => $current_length, "symbol" => $current_symbol]);
        }
    }
    // Return the array of winning lines
    return $winningLines;
}
function checkForDiagonalWin($rows) {
    // Initialize an array to store the information about the winning lines.
    $winningLines = [];

    // Define the directions for which the diagonals need to be checked.
    $directions = [[1, 1], [1, -1]]; // Directions to check: [1, 1] for diagonals from top-left to bottom-right, [1, -1] for diagonals from top-right to bottom-left

    // Iterate over each direction
    foreach ($directions as $direction) {
        $dx = $direction[0];
        $dy = $direction[1];

        // Iterate over each column as the starting position of the diagonal
        for ($x = 0; $x < count($rows[0]); $x++) {
            $firstSymbol = $rows[0][$x]; // Get the first symbol of the diagonal
            $isWinningLine = true; // Initially assume that the diagonal is a winning line

            // Iterate over each row to check the diagonal
            for ($i = 1; $i < count($rows); $i++) {
                $y = $x + $i * $dy;
                // If the y position is out of bounds or the symbol doesn't match the first symbol, this diagonal is not a winning line
                if ($y < 0 || $y >= count($rows[$i]) || $rows[$i][$y] != $firstSymbol) {
                    $isWinningLine = false;
                    break;
                }
            }

            // If all symbols in the diagonal are the same, it is a winning line. So, add it to the winningLines array.
            if ($isWinningLine) {
                $winningLines[] = ['start' => [0, $x], 'end' => [2, $x + 2 * $dy], 'length' => 3, 'symbol' => $firstSymbol];
            }
        }
    }

    // Return the array of winning diagonals.
    return $winningLines;
}
function checkFourCorners($rows) {
    // Initialize an array to store information about the winning lines.
    $winningLines = [];

    // Define the positions of the four corners of the 3x6 matrix.
    $corners = [[0, 0], [0, 5], [2, 0], [2, 5]]; // Positions of the four corners

    // Take the symbol of the first corner
    $firstSymbol = $rows[$corners[0][0]][$corners[0][1]]; 

    // Initially assume that the four corners form a winning line
    $isWinningLine = true;

    // Iterate over each corner
    foreach ($corners as $corner) {
        // If a corner's symbol doesn't match the first corner's symbol, this isn't a winning line
        if ($rows[$corner[0]][$corner[1]] != $firstSymbol) {
            $isWinningLine = false;
            break;
        }
    }

    // If all corners have the same symbol, this is a winning line. So, add it to the winningLines array.
    if ($isWinningLine) {
        $winningLines[] = ['corners' => $corners, 'length' => 4, 'symbol' => $firstSymbol];
    }

    // Return the array of winning lines.
    return $winningLines;
}
function calculatePayout($lineWins, $diagonalWins, $fourCornerWins, $costPerSpin) {
    // Define the payouts for different win types and lengths
    $payouts = [2 => 0.63, 3 => 1.08, 4 => 1.18, 5 => 1.28, 6 => 10, 'diagonal' => 1.08, 'four_corner' => 1.18];
    // Initialize the total payout and payout details
    $totalPayout = 0;
    $payoutDetails = [];

    // Iterate over line wins
    foreach ($lineWins as $lineWin) {
        // Calculate payout for the line win
        $payout = $costPerSpin * $payouts[$lineWin['length']];

        // Add the payout to the total payout
        $totalPayout += $payout;

        // Add the payout details for the line win
        $payoutDetails[] = ['type' => 'line', 'symbol' => $lineWin['symbol'], 'length' => $lineWin['length'], 'payout' => $payout];
    }

    // Iterate over diagonal wins
    foreach ($diagonalWins as $diagonalWin) {
        // Calculate payout for the diagonal win
        $payout = $costPerSpin * $payouts['diagonal'];

        // Add the payout to the total payout
        $totalPayout += $payout;

        // Add the payout details for the diagonal win
        $payoutDetails[] = ['type' => 'diagonal', 'symbol' => $diagonalWin['symbol'], 'length' => $diagonalWin['length'], 'payout' => $payout];
    }

    // Iterate over four corner wins
    foreach ($fourCornerWins as $fourCornerWin) {
        // Calculate payout for the four corner win
        $payout = $costPerSpin * $payouts['four_corner'];

        // Add the payout to the total payout
        $totalPayout += $payout;

        // Add the payout details for the four corner win
        $payoutDetails[] = ['type' => 'four_corner', 'symbol' => $fourCornerWin['symbol'], 'length' => $fourCornerWin['length'], 'payout' => $payout];
    }

    // Return the total payout and payout details
    return ['totalPayout' => $totalPayout, 'payoutDetails' => $payoutDetails];
}

function  dump_die($data)
{
    echo '<pre>'; // Open pre-formatted HTML tag for better output readability

    var_dump($data); // Use var_dump() to display structured information about $data variable

    echo '</pre>'; // Close pre-formatted HTML tag

    die(); // Terminate the current script
}

?>





