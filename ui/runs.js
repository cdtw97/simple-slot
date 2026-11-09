import { byId, element, art, money, signed, dialog } from './view.js';
import { shareCard } from './share.js';

export const modeNames = { free: 'Free Play', score: 'Score Attack', daily: 'Daily Challenge' };
const paletteSwatches = {
    jade: '#286654',
    citrus: '#996033',
    twilight: '#465979',
    plum: '#77506a',
    berry: '#a34c51',
};

let clockTimer;

function dailyClock(game, onReset) {
    const { daily, state } = game;
    const group = element('div', 'daily-info');
    const pinnedDate = state.dailyDate || daily.date;
    const isActive = state.phase !== 'not_started';
    const official = isActive ? state.official : daily.officialAvailable;
    group.append(
        element('strong', null, pinnedDate + ' · ' + (official ? 'Official attempt' : 'Practice')),
    );
    const time = element('span');
    time.id = 'daily-countdown';
    group.append(time);
    const remaining = Date.parse(daily.resetAt) - Date.parse(daily.serverNow);
    function tick() {
        const seconds = Math.max(0, Math.ceil((remaining - (performance.now() - game.receivedAt)) / 1000));
        const hours = String(Math.floor(seconds / 3600)).padStart(2, '0');
        const minutes = String(Math.floor(seconds / 60) % 60).padStart(2, '0');
        const secs = String(seconds % 60).padStart(2, '0');
        time.textContent = 'Next daily in ' + hours + ':' + minutes + ':' + secs + ' · Midnight Toronto';
        if (seconds === 0 && time.isConnected && onReset()) clearInterval(clockTimer);
    }
    tick();
    clockTimer = setInterval(() => (time.isConnected ? tick() : clearInterval(clockTimer)), 1000);
    return group;
}

function button(label, callback) {
    const el = element('button', 'basic', label);
    el.type = 'button';
    el.onclick = callback;
    return el;
}
function info(label, value) {
    const group = element('div');
    group.append(element('dt', null, label), element('dd', null, value));
    return group;
}
function header(title) {
    const head = element('div', 'run-heading');
    head.append(element('h2', null, title));
    return head;
}
function recordCard(record, profile) {
    const section = element('section', 'run-panel scorecard');
    section.append(header(modeNames[record.mode] + ' scorecard'));
    const scoreLine = element('div', 'score-line');
    scoreLine.append(
        element('strong', null, money(record.scoreUnits)),
        element('span', null, 'final score'),
    );
    section.append(scoreLine);
    if (record.mode === 'daily' && !record.official) {
        section.append(
            element(
                'p',
                null,
                'Practice score. Only your first attempt counts toward the official daily record.',
            ),
        );
    } else if (record.previousBestUnits === null || record.previousBestUnits === undefined) {
        section.append(element('p', null, 'Your first completed score under these rules.'));
    } else {
        const delta = record.scoreUnits - record.previousBestUnits;
        section.append(
            element(
                'p',
                null,
                'Previous best ' +
                    money(record.previousBestUnits) +
                    ' · ' +
                    (delta === 0 ? 'Personal best tied' : signed(delta) + ' points'),
            ),
        );
    }
    const stats = element('dl', 'record-stats');
    stats.append(
        info('Largest spin', money(record.largestAwardUnits)),
        info('Corner Rush bonuses', record.bonuses),
        info('Longest row', record.longestRow),
        info('Diagonal awards', record.diagonals),
        info('Respins used', record.respinsUsed),
    );
    section.append(stats);
    const names = record.newAchievements.map(
        (id) => profile.catalog?.find((item) => item.id === id)?.name || id,
    );
    section.append(
        element(
            'p',
            'record-achievements',
            names.length ? 'Unlocked this run: ' + names.join(', ') : 'No new achievements this run.',
        ),
    );
    const share = button('Share scorecard', () => shareCard(record));
    share.id = 'share-card';
    section.append(share);
    return section;
}
export function runControls(game, mutate, onReset) {
    clearInterval(clockTimer);
    const target = byId('mode-actions');
    target.replaceChildren();
    const { state: s, rules, profile } = game;
    if (s.mode === 'free') return;
    if (s.phase === 'run_complete' && s.record) {
        const card = recordCard(s.record, profile);
        if (s.mode === 'daily') card.querySelector('.run-heading').after(dailyClock(game, onReset));
        target.append(card);
        return;
    }
    const panel = element('section', 'run-panel');
    const head = header(modeNames[s.mode]);
    panel.append(head);
    if (s.mode === 'daily') panel.append(dailyClock(game, onReset));
    if (s.phase === 'not_started') {
        panel.append(
            element(
                'p',
                null,
                'Play ' +
                    rules.runLength +
                    ' normal spins and every earned Corner Rush. Spend up to ' +
                    rules.respinCharges +
                    ' charges to replace a reel before settling a normal board. Your Free Play credits stay separate.',
            ),
        );
        if (s.mode === 'daily') {
            const text = game.daily.officialAvailable
                ? 'Starting reserves your official attempt. Later runs for this date are practice.'
                : 'Your official attempt for this date has been used. You can still play for practice.';
            panel.append(element('p', null, text));
        }
    } else {
        const abandon = button('Abandon run', () => {
            const confirm = button('Abandon run', () => {
                byId('dialog').close();
                mutate('abandon_run');
            });
            const cancel = button('Resume game', () => byId('dialog').close());
            const actions = element('div', 'dialog-actions');
            actions.append(cancel, confirm);
            dialog(
                'Abandon this run?',
                element(
                    'p',
                    null,
                    'This ends the run without a completed score. Your earned achievements stay in your collection.' +
                        (s.mode === 'daily' && s.official ? ' Abandoning uses your official attempt.' : ''),
                ),
                actions,
            );
        });
        abandon.classList.add('text-button');
        head.append(abandon);
        if (s.phase === 'awaiting_choice') {
            const choice = element('div', 'choice');
            const label = element('label', null, 'Reel to replace');
            const select = element('select');
            select.id = 'respin-reel';
            for (let i = 0; i < 6; i++) {
                const opt = element('option', null, 'Reel ' + (i + 1));
                opt.value = i;
                select.append(opt);
            }
            label.htmlFor = select.id;
            const reroll = button('Respin reel', () => mutate('respin', { reel: Number(select.value) }));
            reroll.id = 'respin-button';
            choice.append(label, select, reroll);
            panel.append(
                element(
                    'p',
                    null,
                    'Keep the preview with the cabinet button, or replace one reel. The new board settles immediately.',
                ),
                choice,
            );
        }
        const stats = element('dl', 'run-stats');
        stats.append(
            info('Normal spins used', s.normalUsed + ' / ' + rules.runLength),
            info('Respin charges left', s.respinsLeft),
        );
        panel.append(stats);
    }
    target.append(panel);
}
export function collections(game, mutate) {
    const { profile: p } = game;
    const palettes = element('div', 'palettes');
    for (const palette of p.palettes || []) {
        const equipped = palette.id === p.palette;
        const item = element('div', 'palette-item');
        const swatch = element('i', 'swatch');
        swatch.style.backgroundColor = paletteSwatches[palette.id] || '#527361';
        const copy = element('div');
        copy.append(element('strong', null, palette.name), element('small', null, palette.condition));
        item.append(swatch, copy);
        if (equipped) item.append(element('span', 'earned', 'Equipped'));
        else if (palette.unlocked) {
            item.append(
                button('Equip', () => {
                    byId('dialog').close();
                    mutate('equip', { palette: palette.id });
                }),
            );
        } else item.append(element('span', 'locked', 'Locked'));
        palettes.append(item);
    }
    const achievements = element('div', 'achievements');
    for (const item of p.catalog || []) {
        const line = element('div', 'achievement');
        const name = element('div', 'achievement-title');
        name.append(
            art('trophy', true),
            element('strong', null, item.name),
            element(
                'span',
                item.earned ? 'earned' : 'locked',
                item.earned ? 'Earned' : item.progress + ' / ' + item.target,
            ),
        );
        line.append(name, element('p', null, item.description));
        achievements.append(line);
    }
    dialog(
        'Collection',
        element('h3', null, 'Cabinet palettes'),
        palettes,
        element('h3', null, 'Achievements'),
        achievements,
    );
}
