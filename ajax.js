import { byId, art, money, notice, element, dialog } from './ui/view.js';
import { displayReels, roll, skipRoll } from './ui/reels.js';
import { prefs, reduced, wakeAudio, sound } from './ui/preferences.js';
import { results, help, paytable } from './ui/results.js';
import { modeNames, runControls, collections } from './ui/runs.js';

const requestedMode = new URL(location.href).searchParams.get('mode');
let mode = ['score', 'daily'].includes(requestedMode) ? requestedMode : 'free';
byId('mode').value = mode;
let game = null;
let phase = 'loading';
let retained = null;
let refreshNeeded = false;
const spin = byId('spin-button');

function primary() {
    const s = game?.state;
    if (phase === 'rolling') return ['Reveal', 'skip', 'Reveal this result'];
    if (phase === 'loading') return ['Loading', null, 'Retrieving your save'];
    if (phase === 'sending') return ['One moment', null, 'Saving your action'];
    if (phase === 'failed')
        return ['Retry', 'retry', retained ? 'Recover this action' : 'Reconnect to your save'];
    if (s?.mode === 'daily' && ['not_started', 'run_complete'].includes(s.phase))
        return [
            game.daily.officialAvailable ? 'Start daily' : 'Play practice',
            'start_run',
            game.daily.officialAvailable
                ? 'Official attempt · ' + game.daily.date
                : 'Practice · ' + game.daily.date,
        ];
    if (s?.phase === 'not_started')
        return ['Start run', 'start_run', game.rules.runLength + ' normal spins'];
    if (s?.phase === 'run_complete') return ['Play again', 'start_run', 'Start a fresh run'];
    if (s?.phase === 'awaiting_choice')
        return ['Keep board', 'keep', 'Provisional score · ' + money(s.pending.payoutUnits)];
    if (s?.bonusRemaining)
        return ['Free spin', 'advance_bonus', 'Corner Rush · ' + s.bonusRemaining + ' left'];
    const nextRules = game.nextRules || game.rules;
    if (s?.mode === 'free' && s.walletUnits < nextRules.stakeUnits)
        return [
            'Reset credits',
            'confirm_reset',
            'Starting wallet · ' + money(nextRules.startingWalletUnits),
        ];
    return [
        'Spin',
        'spin',
        s?.mode === 'free'
            ? money(nextRules.stakeUnits) +
              ' credits per spin' +
              (game.nextRules ? ' · New paytable ' + nextRules.version : '')
            : 'Normal spin ' + (s.normalUsed + 1) + ' of ' + game.rules.runLength,
    ];
}
function controls() {
    const [label, action, hint] = primary();
    spin.disabled = !action;
    spin.replaceChildren(art(action === 'skip' ? 'play' : 'spin', true), element('span', null, label));
    spin.dataset.action = action || '';
    byId('action-hint').textContent = hint;
    byId('mode').disabled = !['ready', 'failed'].includes(phase) || Boolean(retained);
    byId('collection-button').disabled = !game || phase !== 'ready';
    byId('mode-actions')
        .querySelectorAll('button,select')
        .forEach((el) => (el.disabled = phase !== 'ready'));
    byId('guide-button').disabled = !game;
    byId('paytable-button').disabled = !game;
}
function present(data, announce = false) {
    game = data;
    document.documentElement.dataset.palette = data.profile.palette || 'jade';
    const s = game.state;
    displayReels(s.board, s.lamps, s.pending?.awards || s.result?.awards || []);
    results(s, game.rules, announce);
    runControls(game, mutate, () => {
        if (phase !== 'ready' || document.hidden || byId('dialog').open) return false;
        load();
        return true;
    });
}
async function request(payload = null) {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 16000);
    try {
        const opts = { cache: 'no-store', credentials: 'same-origin', signal: controller.signal };
        if (payload)
            Object.assign(opts, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
        const response = await fetch('game.php?mode=' + encodeURIComponent(mode), opts);
        const data = await response.json();
        if (!response.ok || data.error) {
            const e = new Error(data.error?.message || 'Your save could not be loaded.');
            e.code = data.error?.code;
            e.status = response.status;
            throw e;
        }
        if (!data.state || !data.rules || !data.csrf) throw new Error('Incomplete game response.');
        data.receivedAt = performance.now();
        return data;
    } finally {
        clearTimeout(timeout);
    }
}
async function load(message = '', entering = false) {
    phase = 'loading';
    controls();
    try {
        present(await request());
        retained = null;
        phase = 'ready';
        const activeRun = mode !== 'free' && !['not_started', 'run_complete'].includes(game.state.phase);
        notice(message || (entering && activeRun ? 'Resumed your saved ' + modeNames[mode] + ' run.' : ''));
    } catch {
        phase = 'failed';
        notice('Your save could not be loaded. Check your connection and press Retry.');
    } finally {
        controls();
    }
}
function makeId() {
    return Array.from(crypto.getRandomValues(new Uint8Array(16)), (n) =>
        n.toString(16).padStart(2, '0'),
    ).join('');
}
async function mutate(action, extras = {}, retry = false) {
    if ((!retry && phase !== 'ready') || (retry && phase !== 'failed')) return;
    const payload = retry
        ? retained
        : {
              mode,
              action,
              ...extras,
              csrf: game.csrf,
              actionId: makeId(),
              expectedRevision: game.state.revision,
          };
    if (!payload) return;
    retained = payload;
    phase = 'sending';
    controls();
    notice('');
    try {
        let data = await request(payload);
        const rolls = ['spin', 'respin', 'advance_bonus'].includes(payload.action);
        if (data.replayed || data.state.revision < game.state.revision) {
            data = await request();
        } else if (rolls) {
            sound('spin');
            phase = 'rolling';
            controls();
            await roll(
                game.state,
                data.state,
                Object.keys(data.rules.symbols),
                { fast: prefs.fast, reduced: reduced() },
                () => sound('stop'),
                payload.action === 'respin' ? payload.reel : null,
            );
        }
        present(data, true);
        const r = data.state.result;
        if ((rolls || payload.action === 'keep') && !data.state.pending && r) {
            if (r.triggered) sound('entry');
            else if (r.bonusFinished) sound('complete');
            else if (r.lit.length) sound('lamp');
            else if (r.payoutUnits >= data.rules.celebrations.largeWinUnits) sound('large');
            else if (r.payoutUnits) sound('win');
        }
        retained = null;
        phase = 'ready';
        if (payload.action === 'equip') notice('Cabinet palette equipped.');
    } catch (e) {
        if (e.status && e.status < 500) {
            retained = null;
            await load(
                e.code === 'stale_revision'
                    ? 'This game was updated in another tab. Your latest save is loaded.'
                    : e.message,
            );
        } else {
            phase = 'failed';
            notice(
                'The result could not be confirmed. Press Retry to recover this action without spending again.',
            );
        }
    } finally {
        controls();
        if (refreshNeeded && phase === 'ready' && !document.hidden) {
            refreshNeeded = false;
            await load();
        }
    }
}
function act() {
    wakeAudio();
    const action = primary()[1];
    if (action === 'skip') {
        skipRoll();
        return;
    }
    if (action === 'retry') {
        if (retained) mutate(null, {}, true);
        else load();
        return;
    }
    if (action === 'confirm_reset') {
        const button = element('button', 'basic', 'Reset Free Play');
        button.onclick = () => {
            byId('dialog').close();
            mutate('reset_free_play');
        };
        dialog(
            'Reset Free Play?',
            element(
                'p',
                null,
                'Restore ' +
                    money(game.rules.startingWalletUnits) +
                    ' credits and clear your corner lamps.',
            ),
            button,
        );
    } else if (action) mutate(action);
}
spin.onclick = act;
byId('mode').onchange = () => {
    if (byId('mode').disabled) return;
    mode = byId('mode').value;
    const url = new URL(location.href);
    if (mode === 'free') url.searchParams.delete('mode');
    else url.searchParams.set('mode', mode);
    history.replaceState(null, '', url);
    game = null;
    retained = null;
    byId('mode-actions').replaceChildren();
    load('', true);
};
byId('collection-button').onclick = () => game && collections(game, mutate);
byId('guide-button').onclick = () => game && help(game.nextRules || game.rules, mode);
byId('paytable-button').onclick = () => game && paytable(game.nextRules || game.rules);
document.addEventListener('keydown', (e) => {
    if (e.repeat && e.target === spin && [' ', 'Enter'].includes(e.key)) {
        e.preventDefault();
        return;
    }
    if (
        byId('dialog').open ||
        e.defaultPrevented ||
        e.ctrlKey ||
        e.metaKey ||
        e.altKey ||
        ![' ', 'Enter'].includes(e.key)
    )
        return;
    if (e.target.closest('a,button,summary,input,textarea,select,[contenteditable=true]')) return;
    if (e.target !== document.body && !byId('play').contains(e.target)) return;
    e.preventDefault();
    if (!e.repeat) act();
});
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        skipRoll();
        return;
    }
    if (phase === 'ready') load();
    else if (['rolling', 'sending'].includes(phase)) refreshNeeded = true;
});
window.addEventListener('pageshow', (e) => {
    if (e.persisted && phase === 'ready') load();
});
controls();
load();
