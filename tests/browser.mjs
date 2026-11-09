import assert from 'node:assert/strict';
import {createRequire} from 'node:module';
import {mkdir} from 'node:fs/promises';
import {join} from 'node:path';

// Optional QA dependency: install playwright-core and point SLOT_BROWSER at a Chromium executable.
const require = createRequire(import.meta.url);
const {chromium} = require(process.env.SLOT_PLAYWRIGHT || 'playwright-core');
const base = process.argv[2] || 'http://127.0.0.1:8766/simple-slot/';
const api = new URL('game.php', base).href;
const browser = await chromium.launch({executablePath: process.env.SLOT_BROWSER, headless: true});
const context = await browser.newContext({viewport: {width: 1440, height: 960}, hasTouch: true});
const page = await context.newPage();
const exceptions = [];
page.on('pageerror', e => exceptions.push(e.message));
const wallet = page.locator('#wallet');
const button = page.locator('#spin-button');
const getState = async () => (await (await context.request.get(api)).json()).state;
let posts = 0;
page.on('request', request => {
    if (request.method() === 'POST' && request.url().split('?')[0] === api) posts++;
});
const screenshotDirectory = process.env.SLOT_SCREENSHOTS;

try {
    const response = await page.goto(base);
    assert.equal(response.status(), 200);
    await page.locator('#spin-button:enabled').waitFor();
    const opening = await getState();
    const walletText = await wallet.innerText();
    assert.equal(Number(walletText.replace(/[^\d.]/g, '')), opening.walletUnits / 100);
    const initialButtonText = await button.innerText();
    const initialBoard = opening.board;
    if (screenshotDirectory) await mkdir(screenshotDirectory, {recursive: true});

    for (const [name, width, height] of [
        ['desktop', 1440, 960], ['phone', 320, 740], ['landscape', 844, 390], ['tablet', 768, 1024],
    ]) {
        await page.setViewportSize({width, height});
        await page.evaluate(() => document.fonts.ready);
        const sizes = await page.evaluate(() => {
            const bounds = document.querySelector('#board').getBoundingClientRect();
            const symbols = [...document.querySelectorAll('#board use[href*="fruit.svg"]')].map(use => {
                const rect = use.closest('svg').getBoundingClientRect();
                return {x: rect.x + rect.width / 2, y: rect.y + rect.height / 2, w: rect.width, h: rect.height};
            }).filter(symbol => symbol.x > bounds.left && symbol.x < bounds.right
                && symbol.y > bounds.top && symbol.y < bounds.bottom);
            return {width: document.documentElement.scrollWidth, bounds: bounds.toJSON(), symbols};
        });
        assert.ok(sizes.width <= width, `${name} overflows by ${sizes.width - width}px`);
        assert.ok(sizes.bounds.left >= 0 && sizes.bounds.right <= width + 1, `${name} clips the board`);
        assert.equal(sizes.symbols.length, 18, `${name} visible fruit count`);
        const unique = key => new Set(sizes.symbols.map(symbol => Math.round(symbol[key]))).size;
        assert.equal(unique('x'), 6, `${name} reel alignment`);
        assert.equal(unique('y'), 3, `${name} row alignment`);
        assert.ok(sizes.symbols.every(symbol => symbol.w > 12 && symbol.h > 12));
        if (screenshotDirectory) await page.screenshot({path: join(screenshotDirectory, name + '.png'), fullPage: true});
    }
    assert.deepEqual((await getState()).board, initialBoard);

    // Opening a dialog and pressing Space must leave the board and revision untouched.
    for (const selector of ['#guide-button', '#settings-button', '#paytable-button']) {
        await page.locator(selector).click();
        await page.locator('dialog[open]').waitFor();
        await page.locator('#dialog-title').click();
        await page.keyboard.press('Space');
        await page.keyboard.press('Escape');
        await page.locator('dialog[open]').waitFor({state: 'hidden'});
    }
    assert.equal(posts, 0);
    assert.equal((await getState()).revision, opening.revision);

    // Hold the request while tapping several times. Reveal must only finish that spin's animation.
    let releaseRequest;
    const gate = new Promise(resolve => { releaseRequest = resolve; });
    await page.route('**/game.php*', async route => {
        if (route.request().method() === 'POST') await gate;
        await route.continue();
    });
    await button.focus();
    const sent = page.waitForRequest(request => request.method() === 'POST');
    await page.keyboard.press('Enter');
    await sent;
    const bounds = await button.boundingBox();
    await page.touchscreen.tap(bounds.x + bounds.width / 2, bounds.y + bounds.height / 2);
    await page.keyboard.press('Space');
    assert.equal(posts, 1);
    assert.equal(await wallet.innerText(), walletText);
    releaseRequest();
    await page.waitForFunction(() => /reveal|skip/i.test(document.querySelector('#spin-button').innerText));
    assert.equal(await wallet.innerText(), walletText);
    await button.click();
    await page.waitForFunction(() => !/reveal|skip|loading|spinning/i.test(document.querySelector('#spin-button').innerText)
        && !document.querySelector('#spin-button').disabled);
    await page.unroute('**/game.php*');
    const result = await getState();
    assert.equal(result.revision, opening.revision + 1);
    assert.equal(posts, 1, 'skip started another action');
    assert.equal(Number((await wallet.innerText()).replace(/[^\d.]/g, '')), result.walletUnits / 100);
    assert.equal(result.result.costUnits, 500);
    assert.ok((await page.locator('#result-stats').innerText()).includes('5.00'));
    const displayed = await page.locator('#board .cell').evaluateAll(cells =>
        cells.map(cell => [Number(cell.dataset.row), Number(cell.dataset.col), cell.dataset.symbol, cell.classList.contains('paid')]));
    const paidCells = new Set(result.result.awards.flatMap(award => award.cells.map(([r,c]) => r + ',' + c)));
    assert.equal(displayed.length, 18);
    for (const [row, col, symbol, paid] of displayed) {
        assert.equal(symbol, result.board[row][col]);
        assert.equal(paid, paidCells.has(row + ',' + col));
    }
    await page.reload();
    await page.locator('#spin-button:enabled').waitFor();
    assert.deepEqual(await getState(), result);
    assert.equal(posts, 1);
    assert.ok(initialButtonText.trim().length);
    assert.deepEqual(exceptions, []);
    console.log('Passed desktop, 320px touch, landscape, tablet, reel alignment, dialogs, keyboard spin, slow-request tap lock, reveal, wallet settlement and refresh in ' + await browser.version());
} finally {
    await browser.close();
}
