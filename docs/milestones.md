# Implementation record

## Phase 0 · November 9, 2026

The checkout began at `3acb93a`, the revision reviewed by the supplied roadmap. The roadmap itself was the only untracked file. The game runs from a subdirectory on PHP. The original UI loaded Bootstrap, jQuery and GSAP from CDNs. A smoke test with PHP 8.2.12's built-in server returned HTTP 200 for `index.php`. One POST to `game.php` from a fresh guest returned six columns of three symbols, a zero payout and a wallet of 9,995.00. GET on the legacy endpoint had no result payload.

### Rules observed in the live PHP source

Each normal spin costs 5.00 of the guest's initial 10,000.00 credits. Each of the 18 independent cells draws uniformly from apple, orange, lemon, watermelon, grape, cherry, pear, pineapple, coconut, kiwi and strawberry. Three rows each contain six cells.

| Pattern | Live multiplier | Live credits at 5.00 | Legacy README and both simulators |
| --- | ---: | ---: | ---: |
| Maximal horizontal pair | 0.63 | 3.15 | 0.75215× |
| Horizontal three | 1.08 | 5.40 | 0.72× |
| Horizontal four | 1.18 | 5.90 | 1.02× |
| Horizontal five | 1.28 | 6.40 | 1.28× |
| Horizontal six | 10 | 50.00 | 10× |
| Three-cell diagonal | 1.08 | 5.40 | 0.72× |
| Four corners | 1.18 | 5.90 | 0.72× |

A maximal horizontal run may begin at any column. The eight diagonal paths move one column per row. Each distinct path pays once. The four outer corners comprise one award. Distinct patterns add even when they share cells.

### Reference boards

In the examples below, A through K denote the eleven fruit IDs in the order listed above. Slashes separate rows. Coordinates are zero-based.

| Rows | Expected live result |
| --- | --- |
| `ABCDEF / CDEFGH / EFGHIJ` | No awards, 0.00 returned. |
| `ABCCEF / CDEFGH / EFGHIJ` | One lemon pair at (0,2)–(0,3), 3.15 returned, −1.85 net. |
| `AAAAAA / AAAAAA / AAAAAA` | Three maximal sixes, eight diagonals and one four-corner award. The twelve awards return 199.10 in total. |

The theoretical mean gross return on uniformly distributed legacy base spins is 91.1325% of the stake. For a length-`k` horizontal segment, the probability of all its cells matching is `(1/11)^(k−1)`. Multiply by `10/11` for each adjacent cell that must differ to make the run maximal. Sum over all positions and rows, then add eight diagonal means and the one corner mean. This calculation excludes Corner Rush.

### Implementation checklist and decisions

| Milestone | Existing files and changes | Acceptance evidence to collect |
| --- | --- | --- |
| 1 · Shared rules | Extract the generator, evaluator and configuration from `game.php`. Adapt `sim.php` to use them. Route `sim.py` to PHP. Correct the rule descriptions in `README.md`. | Deterministic board fixtures, integer award sums and exact simulator counts. |
| 2 · Reliable Free Play | Adapt `game.php` to persist the guest, board, revision and receipts in MySQL. Adapt `ajax.js` to recover interrupted requests. | Duplicate and conflicting actions, stale revisions, refresh, new PHP session, poor wallet and two-tab tests. |
| 3–4 · Cabinet and Corner Rush | Adapt `index.php`, `style.css` and `ajax.js`. Show exact winning cells and per-spin totals. Add persistent lamps and three queued double-payout spins. | Responsive browser checks, bonus boundary/retry tests and a seeded economy report. Preserve a runnable Release 1 before adding wilds. |
| 5–7 · Score Attack and collections | Add a separately versioned fruit/wild configuration, final-board choices, run records and cosmetic progression. | Wild ambiguity fixtures, 30-spin and final-bonus checks, settled-event-only unlocks. |
| 8–9 · Daily and release | Add shared daily streams, official/practice attempts, completed-run sharing and a deployable package. | Cross-guest determinism, Toronto date boundaries, install/upgrade checks and package inventory. |

Use the live evaluator and paytable as the historical regression reference. The old PHP simulator included its upper bound and ran 10,001 spins against a 10,000-spin denominator. Both simulators reported award-event counts as probabilities even though one spin could earn multiple awards. The Python evaluator also used the first matching row to label identical rows. One PHP evaluator for gameplay and simulation removes all three sources of drift.

Keep authoritative board coordinates row-major and store one displayed credit as 100 integer units. Round half up once per individual award, then sum. Preserve the original payout examples PDF as historical material. Version any subsequent tuning separately and pin earned bonuses and finite runs to the rules under which they began.

## Shared engine and durable Free Play

- `php tests/engine.php` passed the legacy geometry, distinct coordinates, integer rounding, deterministic draw and simulator-parity fixtures.
- `node tests/reliable-actions.mjs <test-api> <second-worker-api>` passed using two independent PHP 8.2.12 processes against one dedicated MariaDB test database. Two new actions at the same revision produced one successful commit. Retrying the committed ID recovered its original board. Changing that ID's payload was rejected; reloading preserved the balance, an intervening action and the isolated guest saves.
- The same HTTP test played a naturally triggered bonus. On the two-worker run it took 19 additional normal spins to fill the lamps. Reading and retrying every bonus boundary preserved the three distinct payouts, final bonus total and normal-spin count.
- `php tests/corner-rush.php` covered a row and diagonal touching one lamp, a below-cost pair completing all lamps, a 3.15-credit wallet entering the earned bonus, a natural four-corner award and a bonus result that would otherwise fill all lamps. Reset and normal-spin requests during the queue were rejected. The controlled bonus returned 410.00 exactly.

## Release 1 · Fruit Arcade

The playable cabinet uses original local fruit artwork, persistent preferences and the server's cell coordinates for every winning pattern. The measured settings and seeded sample are in [simulation.md](simulation.md).

Ran `node tests/browser.mjs http://127.0.0.1:8766/simple-slot/` with Playwright Core and Chrome 156.0.8078.81. The harness accepts the browser executable in `SLOT_BROWSER`, the installed Playwright Core package path in `SLOT_PLAYWRIGHT` and an optional screenshot directory in `SLOT_SCREENSHOTS`. It uses disposable guest cookies against a test install.

The desktop (1440×960), narrow phone (320×740), landscape (844×390) and tablet (768×1024) checks each displayed all 18 symbols in six aligned columns. The narrow view initially cropped the third row. Explicit grid track sizing fixed it and all four sizes passed on rerun. The browser used emulated touch. Desktop and phone screenshots received a visual check; physical iOS/Android hardware was not exercised.

Dialog use, keyboard spin, repeated taps during a slow request, rotation while spinning and reveal produced exactly one action. The visible wallet, paid cells and restored result matched the server. A dropped response after a committed spin, followed by another tab's spin and a retry, kept the newer result and wallet. A stale request explained the other-tab update and recovered the current board.

## Release 2 · Score Attack and collections

The new fruit tiers and wild rules use `fruit-v2`. Free Play and the new finite runs share evaluation while keeping their own balances, scores and lamps. The scorecard compares records from the same machine, mode and rules version. Settled achievements unlock cabinet palettes.

- `node tests/challenges.mjs <test-api> score-only` completed all 30 normal spins with a respin on the first and final board. It checked the unchanged five reels, exact pending-board reload, idempotent keep/respin, all card metrics against independent totals, locked/equipped palettes and Free Play wallet isolation. The case passed again after `fruit-v2` became current.
- `php tests/challenge-boundaries.php` supplied a four-corner board as the final pending choice. Accepting it queued three double-payout bonus spins. The scorecard and completed-run achievement appeared only after the third. Unused charges and lamps expired.
- `node tests/browser-runs.mjs <test-game-url>` played a complete run through the cabinet in Chrome. It switched modes and refreshed a pending choice, selected a reel, played earned bonuses, checked the completed scorecard at 320px and 1440px, equipped Plum, reloaded, began a new run and tested both Resume and Abandon. The selected palette survived.

## Release 3 · Daily Challenge and sharing

- `node tests/challenges.mjs <test-api> all <second-worker-api>` raced two independent PHP workers for one guest's official daily reservation. Abandoning the winner led to a Practice start. Three other guests completed the shared daily. Two followed identical choices through their normal, respin and bonus boards. The third spent charges on different spins and still drew the same subsequent normal boards.
- With `SIMPLE_SLOT_CONFIG` pointing to the dedicated QA database, `php tests/daily-boundaries.php --database=simple_slot_qa` checked the 23- and 25-hour Toronto dates. It played an official run across midnight on the fall-back weekend with a server-side test clock. The stored board, date and rules survived. A new official day and its same-choice practice produced equal scores. The database retained exactly two official records across the three finished runs.
- `node tests/browser-sharing.mjs <test-game-url>` exercised the Daily countdown and fresh Official/Practice runs on an emulated phone. Their completed scorecards downloaded as separately labeled 1000×608 PNGs. The browser exposed selected result text when clipboard access failed. A simulated native share receiver obtained the same text and PNG bytes as the download.
- `node tests/browser.mjs <test-game-url>` also exercised hide/resume events during a moving reel. Another tab played while backgrounded. Returning refreshed the wallet without an extra spend. The lifecycle events were emulated in headless Chrome.

The three browser suites (`browser.mjs`, `browser-runs.mjs` and `browser-sharing.mjs`) also passed together against a fresh install extracted from the Release 3 ZIP, using its minified assets and a new database created from its migrations. The test served the archive's public directory at `http://127.0.0.1:8769/public/`, verifying the packaged game under a subdirectory.

## Install and upgrade checks

- A seeded legacy PHP session with 9,876.54 credits imported as 987,654 units and survived refresh. A real guest save captured at a Release 1 bonus boundary retained its wallet and three remaining spins after the rules upgrade. All three free spins used `fruit-v1`. Its next paid spin used `fruit-v2`.
- An installation of the Release 2 checkpoint (`ca33a7f`) played Free Play and saved a provisional Score Attack board. Applying only migration 003 and switching to the Release 3 package preserved both full states and the collection. Choosing Keep awarded exactly the earlier preview; the same guest could also start an official daily.
- A separate empty MariaDB database accepted the three SQL files supplied in the ZIP. The archive contains 29 allowlisted files. Two builds produced SHA-256 `6990084ec80a1a8faa2738844d287bf03a38d5dd9c49775df0ed68cb57f1264d`.

The Release 1 cabinet and rules are preserved at `48f13b0` and in `dist/simple-slot-release-1.zip`. The Release 2 checkpoint is `ca33a7f`. Release 3 is in `dist/simple-slot-release-3.zip`. All runtime checks used local servers. Public hosting, accounts, standings, installability and expansion machines remain outside this release.
