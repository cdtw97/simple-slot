# Fruit Arcade measurements

Run from the project directory with 64-bit PHP 8.1 or later. The simulator and live endpoint both pass row-major symbol IDs into `Engine::evaluate`. For a supplied board, both return an ordered award list with the contributing coordinates and the total in hundredths of a credit.

```sh
php tests/engine.php
php sim.php --rules=legacy-v1 --spins=100000 --seed=orchard-2026
php sim.php --rules=fruit-v1 --spins=100000 --seed=orchard-2026
php sim.php --rules=legacy-v1 --board=board.json
```

The optional Python launcher accepts the same arguments and invokes the PHP executable on PATH. Use `--board=-` to read one JSON matrix from standard input. The normal spin count excludes free spins. The report counts award events and winning spins independently because a single board can pay several times. Payout percentiles include zeroes and use nearest rank.

## First release

The two commands above use the same 100,000 normal boards. Each cell independently samples one of eleven equally weighted fruit IDs. Normal board draws and bonus board draws use separate random streams so the comparison holds after a bonus.

| Measure | Legacy game | First release |
| --- | ---: | ---: |
| Rules version | legacy-v1 | fruit-v1 |
| Normal boards with any award | 77.70% | 77.70% |
| Normal boards returning more than the 5-credit cost | 43.78% | 43.78% |
| Normal boards breaking even | 0% | 0% |
| Median normal return | 3.15 credits | 3.15 credits |
| 95th percentile normal return | 11.70 credits | 11.70 credits |
| Corner Rush activations | 0 | 4,921 |
| Free spins | 0 | 14,763 |
| All awards divided by total paid stakes | 90.89% | 118.02% |
| Share of all awards from bonuses | 0% | 22.99% |

In this sample the meter filled in 18 normal spins at the median, 8 at the 10th percentile and 35 at the 90th. The mean over completed meter cycles was 20.32. The final partial cycle is excluded from those intervals. The three free spins at double payouts therefore stayed in the first release. The shared base paytable preserves the original payout distribution while Corner Rush supplies extra awards and a positive average virtual-credit change. Only three six-fruit normal rows appeared, so use the guaranteed six-fruit fixture to verify its payout.

## Repeatable draws

Daily uses the private, per-date seed stored in MySQL. Its draw context is the JSON list `["simple-slot/daily/v1", Toronto date, rules version, draw type, normal spin number, ...]`. For `normal`, there are no further elements. For `respin`, the last element is the zero-based selected reel. For `bonus`, it is the one-based bonus ordinal within the triggering normal spin. Each context starts an independent stream. Neither guest identity nor run ID enters the address.

Ordinary non-seeded gameplay uses PHP's `random_int`. For seeded draws, `Engine::stream` takes a seed and a context string. The input to HMAC-SHA256 is PHP's JSON encoding of the two-element list containing that context and an integer block counter starting at zero. Each digest supplies eight unsigned big-endian 32-bit words in order. Rejection sampling discards words in the incomplete top bucket before reducing modulo the sum of configured weights. Normal boards fill in row-major order and replacement reels fill top to bottom. Contexts must include the action-specific draw address when requesting independent boards.

## Tier and wild tuning

```sh
php sim.php --rules=fruit-v2 --spins=100000 --seed=orchard-2026
php sim.php --rules=fruit-v2 --runs=2000 --seed=orchard-2026
```

The weighted reel contains 88 tickets, including a single wild. Each common fruit has 10 tickets, each medium fruit has 8 and each premium fruit has 5. A six-fruit row returns 65.00, 100.00 or 200.00 credits by tier, up from 50.00 for all fruits in fruit-v1. The canonical tie-break compares wild assignments from left to right using the original fruit order of apple, orange, lemon, watermelon, grape, cherry, pear, pineapple, coconut, kiwi and strawberry.

The first candidate paid 2.25/2.85/4.25 credits for pairs and 5.00/6.25/10.00 for triples and diagonals. Across 100,000 normal boards with seed `orchard-2026`, it returned 142.51% of normal stakes including its free spins. Frequent short matches accounted for most awards. The shipped fruit-v2 reduces the three pair awards to 1.80/2.25/3.40 and the three triple/diagonal awards to 4.50/5.75/9.25, preserving the larger returns for long patterns.

| Free Play measure | fruit-v1 | fruit-v2 |
| --- | ---: | ---: |
| Normal boards with awards | 77.70% | 83.55% |
| Normal boards with net gains | 43.78% | 35.07% |
| Median normal-spin award | 3.15 | 3.60 |
| Normal-spin award at p95 / p99 | 11.70 / 14.85 | 11.50 / 16.80 |
| Corner Rush activations | 4,921 | 6,155 |
| Normal spins between activations at p10 / median / p90 | 8 / 18 / 35 | 7 / 15 / 28 |
| Bonus spins completed | 14,763 | 18,465 |
| Bonus share of all awards | 22.99% | 26.90% |
| All awards divided by normal stakes | 118.02% | 118.59% |

The Score Attack command drives the production challenge state machine through all choices and earned bonuses. Both strategies draw the same 60,000 normal boards across 2,000 paired 30-spin runs. Each run, normal index, bonus ordinal and respin reel selects its own stream. The reroll strategy uses its two available charges on the third reel at the first two zero-return previews. All other previews are kept.

| Score Attack measure | Keep all | Reroll zeros |
| --- | ---: | ---: |
| Mean final score | 170.21 | 173.59 |
| 10th percentile | 126.25 | 128.90 |
| Median | 166.60 | 170.05 |
| 90th percentile | 217.65 | 221.75 |
| 99th percentile | 269.40 | 275.10 |
| Corner Rush activations | 3,092 | 3,099 |
| Respins consumed | 0 | 3,937 |

The reroll strategy gained 3.38 points on average across this sample. These two straightforward strategies measure the effect of a specific decision rule; other choices can improve or discard a pending award.
