<?php
declare(strict_types=1);
require_once __DIR__ . '/src/Rules.php';
$displayBoard = Rules::get()['displayBoard'];

function icon(string $id): string {
    return '<svg class="icon" aria-hidden="true"><use href="assets/icons.svg#' . $id . '"/></svg>';
}
function cabinetFruit(string $id): string {
    return '<svg class="fruit" aria-hidden="true"><use href="assets/fruit.svg#' . $id . '"/></svg>';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#173d34">
    <title>Simple Slot · CDTW Games</title>
    <link rel="icon" href="assets/mark.svg" type="image/svg+xml">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="site-head"><div class="wrap head-inner">
    <a class="wordmark" href="./" aria-label="CDTW Games home"><img src="assets/mark.svg" width="35" height="35" alt="">CDTW<span>GAMES</span></a>
    <nav aria-label="Game tools">
        <button id="collection-button" class="nav-button" aria-label="Collection" disabled><?= icon('trophy') ?><span>Collection</span></button>
        <button id="guide-button" class="nav-button" aria-label="How to play"><?= icon('book') ?><span>How to play</span></button>
        <button id="mute-button" class="icon-button" aria-label="Mute sound" aria-pressed="false"><?= icon('volume') ?></button>
        <button id="settings-button" class="icon-button" aria-label="Settings"><?= icon('settings') ?></button>
    </nav>
</div></header>
<main class="wrap" id="play" tabindex="-1">
    <div class="game-heading"><div>
        <h1>Simple Slot<span class="title-dot">.</span></h1>
        <p>Match fruit across six reels. Light all four corners for a bonus.</p>
    </div><label id="mode-control" class="mode-control"><span class="mode-dot"></span>
        <select id="mode" aria-label="Game mode"><option value="free">Free Play</option><option value="score">Score Attack</option></select><?= icon('chevron') ?>
    </label></div>
    <p id="notice" class="notice" role="status" hidden></p>
    <div class="layout">
        <div>
        <section id="cabinet" class="cabinet" aria-label="Fruit Arcade slot machine">
            <div class="marquee" aria-hidden="true">
                <i class="bolt bolt-l"></i><i class="bolt bolt-r"></i>
                <div class="marquee-art"><b class="wings left"></b><span class="sparkle">✦</span>
                <?= cabinetFruit('cherry') ?><strong>Fruit Arcade</strong><span class="sparkle large">✦</span><b class="wings right"></b></div>
            </div>
            <div class="reel-housing"><div class="reel-screen"><div id="board" class="board" role="img" aria-label="Display reels, loading saved game">
                <?php foreach (array_keys($displayBoard[0]) as $col): ?>
                <div class="reel"><div class="strip"><?php foreach ($displayBoard as $row): ?>
                    <div class="cell"><?= cabinetFruit($row[$col]) ?></div>
                <?php endforeach; ?></div></div>
                <?php endforeach; ?>
            </div></div></div>
            <div class="console">
                <div class="counter"><div class="counter-label"><?= icon('coin') ?><span id="wallet-label">Credit balance</span></div>
                    <strong id="wallet">—</strong></div>
                <button type="button" class="spin" id="spin-button" disabled><?= icon('spin') ?><span>Loading</span></button>
                <div id="action-hint" class="cost">Loading game</div>
            </div>
            <div class="cabinet-base" aria-hidden="true"><i class="bolt"></i><div class="vent"></div><i class="bolt"></i></div>
        </section>
        <div id="mode-actions"></div>
        </div>
        <aside>
            <section class="info-card meter-card" id="meter-card">
                <div class="card-cap"><span class="cap-icon"><?= icon('bolt') ?></span><h2>Corner Rush</h2></div>
                <div class="lamp-row" id="lamps" aria-label="Corner progress">
                    <?php foreach (['Top left','Top right','Bottom left','Bottom right'] as $i => $name): ?>
                    <span class="lamp lamp-<?= $i ?>" aria-label="<?= $name ?> unlit"><i></i></span>
                    <?php endforeach; ?>
                </div>
                <div class="lamp-progress" id="lamp-progress">0 of 4 corners lit</div>
                <p id="lamp-copy">Land a winning match on each corner to earn three free spins at double payout.</p>
            </section>
            <section class="info-card result-card" id="result-card">
                <div class="card-cap"><span class="cap-icon"><?= icon('receipt') ?></span><h2>Spin result</h2></div>
                <strong id="result-title" class="result-title">Getting ready</strong>
                <p id="result-copy">Retrieving your game.</p>
                <dl class="result-stats" id="result-stats" hidden></dl>
                <div id="award-list"></div>
            </section>
            <button class="paytable-link" id="paytable-button"><?= icon('grid') ?>View paytable<?= icon('arrow') ?></button>
        </aside>
    </div>
    <p class="keyboard-hint"><kbd>Space</kbd> Spin or reveal</p>
</main>
<footer class="wrap"><span>CDTW GAMES</span><span>Virtual credits · Just for play</span></footer>
<dialog id="dialog" aria-labelledby="dialog-title"><div class="dialog-head"><h2 id="dialog-title"></h2><button id="dialog-close" class="icon-button" aria-label="Close"><?= icon('close') ?></button></div><div id="dialog-body"></div></dialog>
<div id="announce" class="sr-only" role="status" aria-live="polite" aria-atomic="true"></div>
<noscript><p>Enable JavaScript to play Simple Slot.</p></noscript>
<script type="module" src="ajax.js"></script>
</body></html>
