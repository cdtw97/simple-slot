import {byId, element, art} from './view.js';

const boardEl = byId('board');
const corners = ['tl', 'tr', 'bl', 'br'];
let activeAnimations = [];
let currentAwards = [];
let finalCells = [];

function cell(symbol, row, col, lamps) {
    const el = element('div', 'cell');
    if ((row === 0 || row === 2) && (col === 0 || col === 5)) {
        const index = (row === 2 ? 2 : 0) + (col === 5 ? 1 : 0);
        el.classList.add('corner', corners[index]);
        if (lamps[index]) el.classList.add('lit');
    }
    el.dataset.row = row;
    el.dataset.col = col;
    el.dataset.symbol = symbol;
    el.append(art(symbol));
    return el;
}
function strips() {
    return Array.from({length: 6}, () => {
        const reel = element('div','reel');
        const strip = element('div','strip');
        reel.append(strip);
        return {reel,strip};
    });
}
export function focusAward(id = null) {
    const selected = currentAwards.find(a => a.id === id);
    for (const row of finalCells) for (const el of row) el.classList.remove('focus-award','dim');
    if (!selected) return;
    for (const row of finalCells) for (const el of row) if (el.classList.contains('paid')) el.classList.add('dim');
    for (const [r,c] of selected.cells) {
        finalCells[r][c].classList.remove('dim');
        finalCells[r][c].classList.add('focus-award');
    }
}
export function displayReels(board, lamps, awards) {
    currentAwards = awards;
    finalCells = board.map(() => []);
    const reels = strips();
    for (let col = 0; col < reels.length; col++) {
        board.forEach((row,r) => {
            const el = cell(row[col],r,col,lamps);
            finalCells[r][col] = el;
            reels[col].strip.append(el);
        });
    }
    for (const award of awards) for (const [r,c] of award.cells) finalCells[r][c].classList.add('paid');
    boardEl.replaceChildren(...reels.map(x => x.reel));
    boardEl.setAttribute('aria-label',board.map((row,r) => 'Row ' + (r+1) + ': ' + row.join(', ')).join('. '));
}
export function finishReels() {
    activeAnimations.forEach(animation => animation.finish());
}
export async function roll(previous, next, symbols, {fast,reduced}, stopSound, column = null) {
    if (reduced || document.hidden || !Element.prototype.animate) return;
    const reels = strips();
    boardEl.replaceChildren(...reels.map(x => x.reel));
    const moving = [];
    reels.forEach(({strip}, col) => {
        const values = previous.board.map(row => row[col]);
        if (column !== null && col !== column) {
            values.forEach((value,r) => strip.append(cell(value,r,col,previous.lamps)));
            return;
        }
        const buffer = fast ? 3 : 8 + col;
        for (let i = 0; i < buffer; i++) values.push(symbols[(i * 3 + col) % symbols.length]);
        values.push(...next.board.map(row => row[col]));
        values.forEach((value,i) => {
            const row = i < 3 ? i : i >= values.length - 3 ? i - values.length + 3 : -1;
            strip.append(cell(value,row,col,previous.lamps));
        });
        // A percentage of the full strip keeps the landing position exact through resizing.
        const end = -100 * (values.length - 3) / values.length;
        const animation = strip.animate([{transform:'translateY(0)'},{transform:'translateY(' + end + '%)'}],
            {duration:fast ? 255 + col * 27 : 1140 + col * 105,easing:'cubic-bezier(.18,.55,.20,1)',fill:'forwards'});
        moving.push(animation);
        animation.finished.then(() => { if (!document.hidden && !animation._skipped) stopSound(); }).catch(() => {});
    });
    activeAnimations = moving;
    await Promise.all(moving.map(animation => animation.finished.catch(() => {})));
    activeAnimations = [];
}
export function skipRoll() {
    activeAnimations.forEach(animation => { animation._skipped = true; });
    finishReels();
}
