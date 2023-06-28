function displaySymbols(matrix) {
    // Loop over each row in the matrix
    for (let row = 0; row < matrix.length; row++) {
        // Loop over each column in the row
        for (let col = 0; col < matrix[row].length; col++) {
            // Compute the unique ID for each reel using row and column indices
            let reelId = 'reel' + ((row * matrix[row].length) + col + 1);
            // Retrieve the HTML element with this unique ID
            let reelElement = document.getElementById(reelId);
            // If the element exists, update its text content with the matrix symbol
            if (reelElement) {
                reelElement.textContent = matrix[row][col];
            }
        }
    }
}

function displayPayouts(payoutDetails) {
    // Get the payouts table HTML element
    let table = document.getElementById('payouts-table');
    // Clear the table contents
    table.innerHTML = "";

    // Loop over each payout detail
    for(let payout of payoutDetails) {
        // Create a new row in the table
        let row = table.insertRow(-1);
        // Insert new cells in the row
        let typeCell = row.insertCell(0);
        let symbolCell = row.insertCell(1);
        let lengthCell = row.insertCell(2);
        let payoutCell = row.insertCell(3);

        // Fill the cells with respective payout details
        typeCell.textContent = payout.type;
        symbolCell.textContent = payout.symbol;
        lengthCell.textContent = payout.length;
        payoutCell.textContent = payout.payout.toFixed(2);  // Format the payout to 2 decimal places
    }
}

// Event listener for the 'click' event on the spin button
document.getElementById('spin-button').addEventListener('click', function() {
    // Make a POST request to the 'game.php' file
    fetch('game.php', {
        method: 'POST'
    })
    .then(response => response.json())  // Parse the response as JSON
    .then(data => {
        // Extract total payout from the data and display it
        let totalPayout = data.payout.totalPayout;
        document.getElementById('total-payout').textContent = "Total Payout: " + totalPayout.toFixed(2);

        // Display payout details
        displayPayouts(data.payout.payoutDetails);

        // Update the token counter
        let tokens = data.tokens;
        document.getElementById('token-counter').textContent = "Tokens: " + tokens;

        // Display the slot machine symbols
        let matrix = data.matrix;
        displaySymbols(matrix);
    })
    .catch((error) => {
        // Log any errors to the console
        console.error('Error:', error);
    });
});