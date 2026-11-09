import assert from 'node:assert/strict';
import {createRequire} from 'node:module';
import {randomUUID} from 'node:crypto';

const {chromium} = createRequire(import.meta.url)(process.env.SLOT_PLAYWRIGHT || 'playwright-core');
const base = process.argv[2] || 'http://127.0.0.1:8766/simple-slot/';
const api = new URL('game.php?mode=daily', base).href;
const browser = await chromium.launch({executablePath: process.env.SLOT_BROWSER, headless: true});
const page = await browser.newPage({viewport: {width: 320, height: 740}, reducedMotion: 'reduce'});
const errors = [];
page.on('pageerror', error => errors.push(error.message));
await page.addInitScript(() => {
    Object.defineProperty(navigator, 'share', {configurable: true, value: undefined});
    Object.defineProperty(navigator, 'clipboard', {value: {writeText: () => Promise.reject(new Error('unavailable'))}});
});
try {
    await page.goto(base);
    await page.locator('#spin-button[data-action=spin]').waitFor();
    await page.locator('#mode').selectOption('daily');
    await page.locator('#spin-button[data-action=start_run]').waitFor();
    await page.locator('#daily-countdown').waitFor();
    for (const official of [true, false]) {
        await page.locator('#spin-button[data-action=start_run]').click();
        await page.locator('#spin-button[data-action=spin]').waitFor();
        assert.equal(await page.locator('#share-card').count(), 0);
        let game = await (await page.request.get(api)).json();
        assert.equal(game.state.official, official);
        // Produce a genuine server-confirmed scorecard with the same guest cookies.
        while (game.state.phase !== 'run_complete') {
            const s = game.state;
            const action = s.bonusRemaining ? 'advance_bonus' : s.phase === 'awaiting_choice' ? 'keep' : 'spin';
            const response = await page.request.post(api, {data: {action, mode: 'daily', csrf: game.csrf,
                expectedRevision: s.revision, actionId: randomUUID()}});
            assert.equal(response.status(), 200);
            game = await response.json();
        }
        await page.reload();
        await page.locator('#share-card').waitFor();
        if (!official) await page.evaluate(() => {
            // Exercise the native file-share branch without invoking an OS share target.
            Object.defineProperty(navigator, 'canShare', {value: data => data.files?.[0]?.type === 'image/png'});
            Object.defineProperty(navigator, 'share', {value: async data => {
                window.capturedShare = {text: data.text, name: data.files[0].name, size: data.files[0].size};
            }});
        });
        await page.locator('#share-card').click();
        await page.locator('#download-card').waitFor();
        const canvas = page.locator('canvas.share-canvas');
        const label = await canvas.getAttribute('aria-label');
        for (const text of ['CDTW Games', 'Daily Challenge', official ? 'Official' : 'Practice',
            game.state.dailyDate, game.state.rulesVersion, 'Corner Rush', 'Respins']) assert.ok(label.includes(text));
        const pngEvent = page.waitForEvent('download');
        await page.locator('#download-card').click();
        const file = await pngEvent;
        assert.ok(file.suggestedFilename().includes(official ? 'official' : 'practice'));
        const bytes = [];
        for await (const part of await file.createReadStream()) bytes.push(part);
        const png = Buffer.concat(bytes);
        assert.deepEqual([...png.subarray(0, 8)], [137,80,78,71,13,10,26,10]);
        assert.deepEqual([png.readUInt32BE(16), png.readUInt32BE(20)], [1000,608]);
        await page.getByRole('button', {name: 'Copy result'}).click();
        await page.locator('.share-text:visible').waitFor();
        assert.equal(await page.locator('.share-text').inputValue(), label);
        if (!official) {
            await page.getByRole('button', {name: 'Share…', exact: true}).click();
            const handoff = await page.evaluate(() => window.capturedShare);
            assert.equal(handoff.text, label);
            assert.equal(handoff.name, file.suggestedFilename());
            assert.equal(handoff.size, png.length);
        }
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
        await page.keyboard.press('Escape');
    }
    assert.deepEqual(errors, []);
    console.log('Passed official/practice UI, daily countdown, completed-only share, downloadable 1000×608 PNG, copy fallback and simulated native file handoff.');
} finally {
    await browser.close();
}
