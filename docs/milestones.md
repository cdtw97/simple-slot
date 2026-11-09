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
