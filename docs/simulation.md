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

Ordinary non-seeded gameplay uses PHP's `random_int`. For seeded draws, `Engine::stream` takes a seed and a context string. The input to HMAC-SHA256 is PHP's JSON encoding of the two-element list containing that context and an integer block counter starting at zero. Each digest supplies eight unsigned big-endian 32-bit words in order. Rejection sampling discards words in the incomplete top bucket before reducing modulo the sum of configured weights. Normal boards fill in row-major order and replacement reels fill top to bottom. Contexts must include the action-specific draw address when requesting independent boards.
