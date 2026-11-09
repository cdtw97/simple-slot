import assert from 'node:assert/strict';
import {action, request, successful} from './api-client.mjs';

const endpoint = process.argv[2] || 'http://127.0.0.1:8766/simple-slot/game.php';
const includeDaily = process.argv[3] !== 'score-only';

async function player(mode) {
    const jar = new Map();
    const wallet = successful(await request(endpoint, jar));
    const read = () => request(endpoint + '?mode=' + mode, jar);
    let current = await read();
    successful(current);
    return {
        jar, wallet, read,
        get state() { return current.state; },
        get response() { return current; },
        async step(name, extra = {}, replay = false) {
            const command = action(current, name, extra);
            current = await request(endpoint, jar, command);
            successful(current);
            if (replay) {
                const repeat = await request(endpoint, jar, command);
                assert.equal(repeat.replayed, true);
                assert.deepEqual(successful(repeat), current.state);
                assert.deepEqual(repeat.profile, current.profile, 'replayed receipt changed collection progress');
            }
            return current.state;
        },
    };
}

async function playNormal(p, ordinal, chooseColumn) {
    const priorScore = p.state.scoreUnits;
    const priorLamps = p.state.lamps;
    const priorProfile = p.response.profile;
    const charges = p.state.respinsLeft;
    let turn = await p.step('spin');
    assert.equal(turn.normalUsed, ordinal);
    const drawn = turn.board;
    if (charges > 0) {
        assert.equal(turn.phase, 'awaiting_choice');
        assert.equal(turn.scoreUnits, priorScore);
        assert.deepEqual(turn.lamps, priorLamps);
        assert.deepEqual(p.response.profile, priorProfile, 'preview advanced guest progression');
        if (ordinal === 1 || ordinal === 30) {
            assert.deepEqual(successful(await p.read()), turn, 'refresh changed the pending choice');
            const illegal = await request(endpoint, p.jar, action(p.response, 'spin'));
            assert.ok(illegal.status >= 400);
            assert.deepEqual(successful(await p.read()), turn);
        }
        const reel = chooseColumn(ordinal);
        turn = reel === null
            ? await p.step('keep', {}, ordinal === 2)
            : await p.step('respin', {reel}, true);
        assert.equal(turn.respinsLeft, charges - Number(reel !== null), 'choice consumed wrong charge count');
        for (let r = 0; r < 3; r++) {
            for (let c = 0; c < 6; c++) if (c !== reel) assert.equal(turn.board[r][c], drawn[r][c]);
        }
    }
    const finalBoard = turn.board;
    const normalAward = turn.result.payoutUnits;
    const awards = [...turn.result.awards];
    const triggered = turn.result.triggered;
    let largestAward = normalAward;
    assert.equal(turn.normalUsed, ordinal);
    assert.equal(turn.result.costUnits, 0);
    assert.equal(turn.scoreUnits, priorScore + normalAward);
    const bonusBoards = [];
    while (turn.bonusRemaining) {
        assert.notEqual(turn.phase, 'run_complete');
        const oldScore = turn.scoreUnits;
        turn = await p.step('advance_bonus');
        assert.equal(turn.normalUsed, ordinal);
        assert.equal(turn.scoreUnits, oldScore + turn.result.payoutUnits);
        awards.push(...turn.result.awards);
        largestAward = Math.max(largestAward, turn.result.payoutUnits);
        bonusBoards.push(turn.board);
    }
    return {drawn, finalBoard, normalAward, bonusBoards, scoreUnits: turn.scoreUnits, lamps: turn.lamps,
        triggered, largestAward, longestRow: Math.max(0, ...awards.filter(w => w.type === 'row').map(w => w.length)),
        diagonals: awards.filter(w => w.type === 'diagonal').length};
}

const respinAtEnds = n => n === 1 ? 2 : n === 30 ? 5 : null;
const score = await player('score');
const rejectedEquip = await request(endpoint, score.jar, action(score.response, 'equip', {palette: 'plum'}));
assert.equal(rejectedEquip.error?.code, 'palette_locked');
await score.step('start_run');
assert.equal(score.state.respinsLeft, 2);
const turns = [];
for (let n = 1; n <= 30; n++) turns.push(await playNormal(score, n, respinAtEnds));
assert.equal(score.state.phase, 'run_complete');
assert.equal(score.state.record.scoreUnits, score.state.scoreUnits);
assert.equal(score.state.record.respinsUsed, 2);
assert.equal(score.state.record.largestAwardUnits, Math.max(...turns.map(s => s.largestAward)));
assert.equal(score.state.record.longestRow, Math.max(...turns.map(s => s.longestRow)));
assert.equal(score.state.record.diagonals, turns.reduce((n,s) => n + s.diagonals, 0));
assert.equal(score.state.record.bonuses, turns.filter(s => s.triggered).length);
assert.equal(score.state.record.previousBestUnits, null);
assert.ok(score.state.record.newAchievements.includes('first_run'));
assert.deepEqual(successful(await request(endpoint, score.jar)), score.wallet, 'challenge altered Free Play');
const finishedProfile = score.response.profile;
const scoreBeforeEquip = score.state;
await score.step('equip', {palette: 'plum'}, true);
assert.deepEqual(score.state, {...scoreBeforeEquip, revision: scoreBeforeEquip.revision + 1});
assert.equal((await score.read()).profile.palette, 'plum');
assert.deepEqual(score.response.profile.stats, finishedProfile.stats, 'equip changed progression');
await score.step('start_run');
assert.equal(score.response.profile.palette, 'plum');
await score.step('abandon_run');
assert.ok(!score.state.record, 'abandoned run received a completed record');
assert.deepEqual(score.response.profile.achievements, finishedProfile.achievements);
console.log('Passed all 30 Score Attack spins, pending refresh, two chosen reel respins, retry, wallet isolation, all scorecard metrics, locked/equipped palettes, progression and abandon.');

if (includeDaily) {
    const [a, b, c] = await Promise.all([player('daily'), player('daily'), player('daily')]);
    await Promise.all([a.step('start_run'), b.step('start_run'), c.step('start_run')]);
    assert.ok(a.state.official && b.state.official && c.state.official);
    assert.equal(a.state.dailyDate, b.state.dailyDate);
    assert.match(a.state.dailyDate, /^\d{4}-\d{2}-\d{2}$/);
    assert.ok(Number.isFinite(Date.parse(a.response.daily.resetAt)));
    for (let n = 1; n <= 30; n++) {
        const [left, right, different] = await Promise.all([
            playNormal(a, n, respinAtEnds), playNormal(b, n, respinAtEnds),
            playNormal(c, n, t => t === 2 || t === 3 ? 0 : null),
        ]);
        assert.deepEqual(left, right, 'same daily choices must yield identical normal, respun and bonus results');
        assert.deepEqual(left.drawn, different.drawn, 'spending respins moved a later normal draw');
    }
    assert.equal(a.state.phase, 'run_complete');
    assert.equal(a.state.record.scoreUnits, b.state.record.scoreUnits);
    assert.equal(a.state.record.official, true);
    await a.step('start_run');
    assert.equal(a.state.official, false, 'previous official attempt was not reserved');
    await a.step('abandon_run');
    await a.step('start_run');
    assert.equal(a.state.official, false, 'abandon restored official eligibility');
    assert.deepEqual(successful(await request(endpoint, a.jar)), a.wallet);
    console.log('Passed three independent daily runs, all shared boards, same-choice score and bonuses, divergent respin stream isolation, official completion and practice after abandon.');
}
