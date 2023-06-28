<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Slot Machine</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div id="testing-purposes"></div>
    <div id="slot-machine">
        <div class="row">
            <div class="reel-container">
                <div class="reel" id="reel1"></div>
            </div>
            <div class="reel-container">
                <div class="reel" id="reel2"></div>
            </div>
            <div class="reel-container">
                <div class="reel" id="reel3"></div>
            </div>
            <div class="reel-container">
                <div class="reel" id="reel4"></div>
            </div>
            <div class="reel-container">
                <div class="reel" id="reel5"></div>
            </div>
            <div class="reel-container">
                <div class="reel" id="reel6"></div>
            </div>
        </div>
        <div class="row">
            <div class="reel-container">
                <div class="reel" id="reel7"></div>
            </div>
            <div class="reel-container">
                <div class="reel" id="reel8"></div>
            </div>
            <div class="reel-container">
                <div class="reel" id="reel9"></div>
            </div>
            <div class="reel-container">
                <div class="reel" id="reel10"></div>
            </div>
            <div class="reel-container">
                <div class="reel" id="reel11"></div>
            </div>
            <div class="reel-container">
                <div class="reel" id="reel12"></div>
            </div>
        </div>
        <div class="row">
            <div class="reel-container">
                <div class="reel" id="reel13"></div>
            </div>
            <div class="reel-container">
                <div class="reel" id="reel14"></div>
            </div>
            <div class="reel-container">
                <div class="reel" id="reel15"></div>
            </div>
            <div class="reel-container">
                <div class="reel" id="reel16"></div>
            </div>
            <div class="reel-container">
                <div class="reel" id="reel17"></div>
            </div>
            <div class="reel-container">
                <div class="reel" id="reel18"></div>
            </div>
        </div>



    </div>
    <button id="spin-button">Spin</button>


    <p id="token-counter">Tokens: 1000</p>
    <p id="spin-result"></p>
    <div id="tokens"></div>
    <p id="total-payout">Total Payout: 0.00</p>
    <table id="payouts-table">
        <tr>
            <th>Symbol</th>
            <th>Type</th>
            <th>Amount</th>
        </tr>
    </table>
    <script src="ajax.js"></script>
</body>

</html>