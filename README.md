# Simple Slot

This is a simple web-based slot machine game implemented using PHP, JavaScript (with Fetch API), HTML and CSS. It includes the game logic for determining wins and payouts.

## Setup and Running

To run this game, you'll need to have PHP installed on your server or local machine.

- Clone the repository to your local machine or just download the zip from the GitHub page.
- Set up your local server environment (you could use something like XAMPP or MAMP) and move the project to your server directory (like `htdocs` for XAMPP).
- Open your web browser and navigate to `localhost/directory-name/index.php`, where `directory-name` is the name of the folder where you stored the files.

## Game Rules

The slot machine has 3 rows and 6 columns, making a total of 18 slots. The symbols used are various fruits emojis. The game checks for winnings in rows, diagonals and four corners. 

## How to Play

Click on the "Spin" button to spin the slot machine. If the symbols line up in the correct pattern, a win is registered, and the payout is calculated. The current token balance and total payout are displayed on the webpage. 

The cost of each spin is 5 tokens. The payout rates for different win patterns are as follows:
- 2 in a row: 0.75215 * cost per spin
- 3 in a row: 0.72 * cost per spin
- 4 in a row: 1.02 * cost per spin
- 5 in a row: 1.28 * cost per spin
- 6 in a row: 10 * cost per spin
- Diagonal: 0.72 * cost per spin
- Four corners: 0.72 * cost per spin

## Files Included

1. **index.php**: This is the main file which contains the HTML structure of the game.
2. **style.css**: This file contains all the styling required for the game.
3. **game.php**: This is the backend of the game. It handles the game logic, including determining wins and calculating payouts.
4. **ajax.js**: This file handles the front-end JavaScript, including the Fetch API call to the game.php script and updating the front-end according to the response.