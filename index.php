<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Slot Machine</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="container">
        <div class="d-flex justify-content-center align-items-center">
            <h2>Simple Slot</h2>
        </div>
    </div>
    <div class="container">

        <div class="d-flex justify-content-center align-items-center">
        
            <div id="slot-machine" class="d-flex justify-content-around">
                <div class="reel-container" id="reel1">
                    <ul class="reel"></ul>
                </div>
                <div class="reel-container" id="reel2">
                    <ul class="reel"></ul>
                </div>
                <div class="reel-container" id="reel3">
                    <ul class="reel"></ul>
                </div>
                <div class="reel-container" id="reel4">
                    <ul class="reel"></ul>
                </div>
                <div class="reel-container" id="reel5">
                    <ul class="reel"></ul>
                </div>
                <div class="reel-container" id="reel6">
                    <ul class="reel"></ul>
                </div>
            </div>
        </div>

        <div class="text-center mt-5">
            <button id="spin-button" class="btn btn-primary">Spin</button>
        </div>

        <div class="mt-5">
            <h3>Winning Lines:</h3>
            <ul id="winning-lines" class="list-group"></ul>
        </div>

        <div class="mt-5">
            <h3>Tokens: <span id="token-amount">0</span></h3>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js" integrity="sha256-2Pmvv0kuTBOenSvLm6bvfBSSHrUJ+3A7x6P5Ebd07/g=" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.9.1/gsap.min.js"></script>
    <script src="ajax.js"></script>
</body>
</html>