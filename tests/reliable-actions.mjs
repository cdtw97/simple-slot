import assert from 'node:assert/strict';
import {action, request, successful} from './api-client.mjs';

// Run against an isolated test installation. Each execution creates two new test guests.
const endpoint = process.argv[2] || 'http://127.0.0.1:8766/simple-slot/game.php';
const secondWorker = process.argv[3] || endpoint;
const jar = new Map();

function spin(model) {
    return action(model, 'spin');
}

const start = await request(endpoint, jar);
const before = successful(start);
assert.equal(before.walletUnits, 1_000_000);
assert.equal(before.board.length, 3);
assert.ok(before.board.every(row => row.length === 6 && row.every(s => typeof s === 'string' && s.length > 0)));
assert.deepEqual(successful(await request(endpoint, jar)), before);

const originalAction = spin(start);
const first = await request(endpoint, jar, originalAction);
const settled = successful(first);
assert.equal(settled.revision, before.revision + 1);
assert.equal(settled.result.costUnits, 500);
assert.equal(settled.result.netUnits, settled.result.payoutUnits - 500);
assert.equal(settled.walletUnits, before.walletUnits + settled.result.netUnits);
assert.equal(settled.result.payoutUnits, settled.result.awards.reduce((sum, award) => sum + award.payoutUnits, 0));

// A response lost after commit is recoverable with the original revision and action ID.
const replay = await request(secondWorker, jar, originalAction);
assert.deepEqual(successful(replay), settled);
assert.equal(replay.replayed, true);
const conflict = await request(endpoint, jar, {...originalAction, expectedRevision: settled.revision});
assert.equal(conflict.error?.code, 'action_conflict', JSON.stringify(conflict));
const stale = await request(secondWorker, jar, spin(start));
assert.equal(stale.error?.code, 'stale_revision', JSON.stringify(stale));
assert.deepEqual(successful(await request(endpoint, jar)), settled);

// One normal spin may very rarely have already triggered Corner Rush. Advance the valid action.
const nextAction = () => ({
    ...spin(first),
    action: settled.bonusRemaining ? 'advance_bonus' : 'spin',
});
const race = await Promise.all([
    request(endpoint, new Map(jar), nextAction()),
    request(secondWorker, new Map(jar), nextAction()),
]);
assert.equal(race.filter(body => body.status === 200).length, 1, JSON.stringify(race));
assert.equal(race.filter(body => body.error?.code === 'stale_revision').length, 1, JSON.stringify(race));
const current = successful(await request(endpoint, jar));
assert.equal(current.revision, settled.revision + 1);
assert.equal(current.walletUnits, settled.walletUnits + current.result.netUnits);

// A retry after intervening play must never debit again or roll back stored state.
const late = await request(endpoint, jar, originalAction);
assert.deepEqual(successful(late), settled);
assert.equal(late.replayed, true);
assert.deepEqual(successful(await request(endpoint, jar)), current);

const independent = new Map();
const outsider = await request(endpoint, independent);
assert.notEqual(successful(outsider).id, current.id);
const foreignToken = await request(endpoint, independent, {...spin(outsider), csrf: start.csrf});
assert.ok(foreignToken.status >= 400 && foreignToken.status < 500);
assert.deepEqual(successful(await request(endpoint, independent)), outsider.state);
assert.deepEqual(successful(await request(endpoint, jar)), current);

jar.delete('PHPSESSID');
assert.deepEqual(successful(await request(endpoint, jar)), current);

// Exercise a real database-backed bonus chain, including replay of every bonus receipt.
let progress = await request(endpoint, jar);
let normals = 0;
while (progress.state.bonusRemaining !== 3 && normals < 1000) {
    const action = progress.state.bonusRemaining ? 'advance_bonus' : 'spin';
    progress = await request(endpoint, jar, {...spin(progress), action});
    successful(progress);
    if (action === 'spin') normals++;
}
assert.equal(progress.state.bonusRemaining, 3, 'Expected a natural bonus within the smoke-test limit');
let bonusTotal = 0;
for (let ordinal = 1; ordinal <= 3; ordinal++) {
    const old = progress.state;
    const advance = {...spin(progress), action: 'advance_bonus'};
    progress = await request(endpoint, jar, advance);
    const state = successful(progress);
    assert.equal(state.bonusRemaining, 3 - ordinal);
    assert.equal(state.normalUsed, old.normalUsed);
    assert.equal(state.walletUnits, old.walletUnits + state.result.payoutUnits);
    assert.equal(state.result.costUnits, 0);
    assert.deepEqual(state.lamps, [false, false, false, false]);
    bonusTotal += state.result.payoutUnits;
    assert.equal(state.result.bonusTotalUnits, bonusTotal);
    assert.deepEqual(successful(await request(secondWorker, jar, advance)), state);
    assert.deepEqual(successful(await request(endpoint, jar)), state);
}
assert.equal(progress.state.result.bonusFinished, true);
console.log(`Passed initial board, wallet, read-only GET, exact debit, lost response, changed payload, stale revision, competing actions, late replay, guest isolation, session renewal and all three persistent bonus boundaries (trigger after ${normals} further normal spins).`);
