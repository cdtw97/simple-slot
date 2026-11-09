import { byId, element, art, money, signed, dialog } from './view.js';
import { focusAward } from './reels.js';

function details(awards, rules) {
    const target = byId('award-list');
    target.replaceChildren();
    if (!awards.length) return;
    const wrapper = element('details', 'win-details');
    wrapper.append(
        element(
            'summary',
            null,
            awards.length + (awards.length === 1 ? ' winning pattern' : ' winning patterns'),
        ),
    );
    const buttons = [];
    for (const a of awards) {
        const name = rules.symbols[a.symbol].name;
        const pattern =
            a.type === 'row' ? a.length + ' in a row' : a.type === 'corners' ? 'Four corners' : 'Diagonal';
        const button = element('button', 'award');
        button.type = 'button';
        button.append(
            art(a.symbol),
            element('span', null, pattern),
            element('b', null, money(a.payoutUnits)),
        );
        button.setAttribute(
            'aria-label',
            name +
                ', ' +
                pattern +
                ', ' +
                money(a.payoutUnits) +
                '. Highlight cells ' +
                a.cells.map(([r, c]) => 'row ' + (r + 1) + ' column ' + (c + 1)).join(', '),
        );
        button.setAttribute('aria-pressed', 'false');
        button.onclick = () => {
            const select = button.getAttribute('aria-pressed') !== 'true';
            buttons.forEach((other) => other.setAttribute('aria-pressed', 'false'));
            button.setAttribute('aria-pressed', String(select));
            focusAward(select ? a.id : null);
        };
        buttons.push(button);
        wrapper.append(button);
    }
    wrapper.ontoggle = () => {
        if (!wrapper.open) {
            focusAward();
            buttons.forEach((b) => b.setAttribute('aria-pressed', 'false'));
        }
    };
    target.append(wrapper);
}
function stat(label, value) {
    const row = element('div');
    row.append(element('dt', null, label), element('dd', null, value));
    return row;
}
export function results(state, rules, announce) {
    const { result: r, pending } = state;
    const free = state.mode === 'free';
    const awards = pending?.awards || r?.awards || [];
    const stats = byId('result-stats');
    const count = state.lamps.filter(Boolean).length;
    byId('wallet-label').textContent = free ? 'Credit balance' : 'Run score';
    byId('wallet').textContent = money(free ? state.walletUnits : state.scoreUnits);
    const bonus = state.bonusRemaining > 0;
    const charge = r?.lit?.length > 0;
    Array.from(byId('lamps').children).forEach((lamp, i) => {
        const on = state.lamps[i];
        lamp.classList.toggle('on', on);
        lamp.setAttribute(
            'aria-label',
            ['Top left', 'Top right', 'Bottom left', 'Bottom right'][i] + (on ? ' lit' : ' unlit'),
        );
    });
    byId('meter-card').classList.toggle('rush', bonus);
    byId('lamp-progress').textContent = bonus ? 'Corner Rush active' : count + ' of 4 corners lit';
    byId('lamp-copy').textContent = bonus
        ? state.bonusRemaining +
          ' free ' +
          (state.bonusRemaining === 1 ? 'spin' : 'spins') +
          ' left at ' +
          rules.bonusMultiplier +
          '×. Bonus total: ' +
          money(state.bonusTotalUnits) +
          '.'
        : r?.bonusFinished
          ? 'Corner Rush complete! Your free spins returned ' + money(r.bonusTotalUnits) + '.'
          : 'Land a winning match on each corner to earn ' +
            rules.bonusSpins +
            ' free spins at ' +
            rules.bonusMultiplier +
            '× payout.';
    let title = 'Ready to spin';
    let copy =
        'Press Spin to play. Match two or more fruit in a row, three diagonally, or all four corners.';
    if (!free && !r) {
        title = state.phase === 'not_started' ? 'Ready for a run' : 'Run started';
        copy = 'Build your score across ' + rules.runLength + ' normal spins and earned bonuses.';
    }
    let strong = false;
    if (pending) {
        title = 'Provisional result';
        copy = 'Keep this board or spend a charge to replace one reel. A respin can lower the award.';
        stats.replaceChildren(stat('Potential score', money(pending.payoutUnits)));
    } else if (r) {
        strong = r.payoutUnits >= rules.celebrations.largeWinUnits && (!free || r.netUnits > 0);
        title = r.payoutUnits ? 'Fruit match' : 'No matches';
        copy = r.payoutUnits ? 'Winning cells are marked on the reels.' : 'This board returned no award.';
        if (strong) title = 'Big win!';
        if (r.kind === 'bonus') copy = 'Corner Rush spin settled at ' + rules.bonusMultiplier + '×.';
        if (charge && state.phase !== 'run_complete')
            copy = count + ' of 4 corners lit. Your progress carries into the next spin.';
        if (r.triggered) {
            title = 'Corner Rush unlocked!';
            copy =
                'All four corners charged. Play your ' +
                rules.bonusSpins +
                ' free spins at ' +
                rules.bonusMultiplier +
                '×.';
        }
        if (r.bonusFinished) {
            title = 'Corner Rush complete';
            copy = 'Bonus total: ' + money(r.bonusTotalUnits) + '.';
        }
        if (state.phase === 'run_complete') {
            byId('lamp-copy').textContent =
                'This run has ended. Your next run starts with four unlit corners.';
            copy += ' Your scorecard is ready.';
        }
        const rows = free
            ? [
                  stat('Spin cost', money(r.costUnits)),
                  stat('Returned', money(r.payoutUnits)),
                  stat('Net change', signed(r.netUnits)),
              ]
            : [stat('Score awarded', money(r.payoutUnits))];
        stats.replaceChildren(...rows);
    } else {
        stats.replaceChildren();
    }
    stats.hidden = !r && !pending;
    byId('result-title').textContent = title;
    byId('result-copy').textContent = copy;
    byId('result-card').classList.toggle('strong', strong);
    details(awards, rules);
    if (announce)
        byId('announce').textContent = [
            title,
            copy,
            pending
                ? 'Potential score ' + money(pending.payoutUnits)
                : r
                  ? 'Award ' + money(r.payoutUnits) + (free ? '. Net change ' + signed(r.netUnits) : '')
                  : '',
        ].join('. ');
}
export function help(rules, mode) {
    const extra = [];
    if (mode === 'daily') {
        extra.push(
            element('h3', null, 'The daily challenge'),
            element(
                'p',
                null,
                'Everyone receives the same boards for the same choices on each date. The challenge changes at midnight in Toronto. Starting reserves your first attempt as official. You can finish it after midnight and play practice runs after your official attempt.',
            ),
        );
    }
    if (rules.wild)
        extra.push(
            element('h3', null, 'Wild fruit'),
            element(
                'p',
                null,
                'The gold W substitutes for a fruit. A pattern needs at least one matching real fruit. Each row uses its highest-paying interpretation, and a wild can participate separately in a diagonal or the four corners.',
            ),
        );
    if (mode !== 'free')
        extra.push(
            element('h3', null, 'Challenge runs'),
            element(
                'p',
                null,
                'Play ' +
                    rules.runLength +
                    ' normal spins with ' +
                    rules.respinCharges +
                    ' optional reel replacements. Keep a provisional board or choose a single reel to redraw. Only the final board awards score and lights corners. Bonuses count toward score and use no normal spins.',
            ),
        );
    dialog(
        'How to play',
        element('h3', null, 'Match the fruit'),
        element(
            'p',
            null,
            'A normal Free Play spin costs ' +
                money(rules.stakeUnits) +
                ' virtual credits. A row of two to six matching fruit can start on any reel. Only the longest unbroken group pays. A three-fruit diagonal and four matching corners also pay. Distinct patterns can overlap.',
        ),
        ...extra,
        element('h3', null, 'Charge Corner Rush'),
        element(
            'p',
            null,
            'Every outer corner in a winning pattern lights a lamp. Fill all four to earn ' +
                rules.bonusSpins +
                ' free spins with ' +
                rules.bonusMultiplier +
                '× payouts. Bonus spins do not charge lamps.',
        ),
        element('h3', null, 'Read a result'),
        element(
            'p',
            null,
            'Returned credits include every award. Net change includes your spin cost. Open the winning patterns and select one to trace its cells. Use Space or the Spin button to play, then Reveal to stop the animation. Your guest game saves automatically in this browser.',
        ),
    );
}
export function paytable(rules) {
    const children = [
        element(
            'p',
            null,
            'Awards in credits on a ' +
                money(rules.stakeUnits) +
                ' basis. Corner Rush multiplies each award by ' +
                rules.bonusMultiplier +
                '.',
        ),
    ];
    if (rules.wild) {
        const line = element('div', 'fruit-line');
        line.append(art('wild'), element('strong', null, 'Wild'));
        children.push(
            line,
            element(
                'p',
                null,
                'Substitutes for a real fruit in any paid pattern. All-wild patterns pay zero. A row uses one highest-paying assignment; ties follow the configured fruit order.',
            ),
        );
    }
    for (const [tier, values] of Object.entries(rules.paytableUnits)) {
        const fruits = Object.entries(rules.symbols).filter(([, s]) => s.tier === tier);
        children.push(element('h3', null, tier.charAt(0).toUpperCase() + tier.slice(1) + ' fruit'));
        const line = element('div', 'fruit-line');
        for (const [id, meta] of fruits) {
            const img = art(id);
            img.removeAttribute('aria-hidden');
            img.setAttribute('role', 'img');
            img.setAttribute('aria-label', meta.name);
            line.append(img);
        }
        children.push(line, element('p', null, fruits.map(([, s]) => s.name).join(', ')));
        const table = element('table', 'pay-table');
        for (const [label, value] of [
            ['Pattern', 'Payout'],
            ...Object.entries(values.row).map(([n, v]) => [n + ' in a row', money(v)]),
            ['3 on a diagonal', money(values.diagonal)],
            ['4 corners', money(values.corners)],
        ]) {
            const row = element('tr');
            const cell = label === 'Pattern' ? 'th' : 'td';
            row.append(element(cell, null, label), element(cell, null, value));
            table.append(row);
        }
        children.push(table);
    }
    children.push(element('p', null, 'Rules ' + rules.version));
    dialog('Paytable', ...children);
}
