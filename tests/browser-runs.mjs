import assert from 'node:assert/strict';
import {createRequire} from 'node:module';
import {mkdir} from 'node:fs/promises';
import {join} from 'node:path';

const {chromium} = createRequire(import.meta.url)(process.env.SLOT_PLAYWRIGHT || 'playwright-core');
const base = process.argv[2] || 'http://127.0.0.1:8766/simple-slot/';
const browser = await chromium.launch({executablePath: process.env.SLOT_BROWSER, headless: true});
const page = await browser.newPage({reducedMotion: 'reduce', hasTouch: true, viewport: {width: 320, height: 740}});
const errors = [];
page.on('pageerror', e => errors.push(e.message));
const state = async mode => (await (await page.request.get(new URL('game.php?mode=' + mode, base).href)).json()).state;
const primary = page.locator('#spin-button');
const mode = page.locator('#mode');
async function play(name, control = primary) {
    const reply = page.waitForResponse(response => response.request().method() === 'POST'
        && response.request().postDataJSON().action === name);
    await control.click();
    const answer = await reply;
    assert.equal(answer.status(), 200);
    const data = await answer.json();
    await page.waitForFunction(() => {
        const button = document.querySelector('#spin-button');
        return !button.disabled && !['skip', 'retry'].includes(button.dataset.action);
    });
    return data;
}
async function snap(name) {
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    if (!process.env.SLOT_SCREENSHOTS) return;
    await mkdir(process.env.SLOT_SCREENSHOTS, {recursive: true});
    await page.screenshot({path: join(process.env.SLOT_SCREENSHOTS, name + '.png'), fullPage: true});
}

try {
    await page.goto(base);
    await page.locator('#spin-button[data-action=spin]').waitFor();
    const wallet = await state('free');
    await mode.selectOption('score');
    await page.locator('#spin-button[data-action=start_run]').waitFor();
    await play('start_run');
    let settled;
    for (let ordinal = 1; ordinal <= 30; ordinal++) {
        const pending = await play('spin');
        assert.equal(pending.state.phase, 'awaiting_choice');
        if (ordinal === 1) {
            await snap('phone-choice');
            await mode.selectOption('free');
            await page.locator('#spin-button[data-action=spin]').waitFor();
            await mode.selectOption('score');
            await page.locator('#spin-button[data-action=keep]').waitFor();
            await page.reload();
            await page.locator('#spin-button[data-action=keep]').waitFor();
            assert.deepEqual(await state('score'), pending.state);
        }
        if (ordinal === 1 || ordinal === 30) {
            await page.locator('#respin-reel').selectOption(ordinal === 1 ? '2' : '4');
            settled = await play('respin', page.locator('#respin-button'));
        } else settled = await play('keep');
        while (settled.state.bonusRemaining) settled = await play('advance_bonus');
    }
    await page.locator('.scorecard').waitFor();
    assert.equal(settled.state.phase, 'run_complete');
    assert.equal(Number((await page.locator('.score-line strong').innerText()).replace(/[^\d.]/g, '')), settled.state.scoreUnits / 100);
    await snap('phone-scorecard');
    await page.setViewportSize({width: 1440, height: 960});
    await snap('desktop-scorecard');
    assert.deepEqual(await state('free'), wallet);
    await page.locator('#collection-button').click();
    const plum = page.locator('.palette-item').filter({has: page.getByText('Plum', {exact: true})});
    const equipped = await play('equip', plum.getByRole('button', {name: 'Equip'}));
    assert.equal(equipped.profile.palette, 'plum');
    await page.reload();
    await page.locator('.scorecard').waitFor();
    await page.locator('#collection-button').click();
    await plum.getByText('Equipped').waitFor();
    await page.keyboard.press('Escape');
    await play('start_run');
    await page.getByRole('button', {name: 'Abandon run', exact: true}).click();
    await page.getByRole('button', {name: 'Resume game'}).click();
    assert.equal((await state('score')).phase, 'idle');
    await page.getByRole('button', {name: 'Abandon run', exact: true}).click();
    assert.equal((await play('abandon_run', page.locator('dialog').getByRole('button', {name: 'Abandon run'}))).state.phase, 'not_started');
    assert.deepEqual(errors, []);
    console.log('Passed phone and desktop Score Attack, resume/reload, chosen reel, 30 spins, scorecard, collection/equip persistence, restart and confirmed abandon.');
} finally {
    await browser.close();
}
