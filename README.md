# Simple Slot · CDTW Games

A six-reel fruit arcade for virtual credits. Play immediately as a guest. The cabinet keeps the wallet, lit corners and queued free spins on the server.

## Run the game

The verified local stack uses 64-bit PHP 8.2 with PDO MySQL and MariaDB 10.11 with InnoDB. A modern browser with JavaScript and first-party cookies is required. Production runs on PHP without Node or third-party runtime assets.

1. Create an empty database, for example `simple_slot_arcade`, using utf8mb4. Import the SQL migrations from `migrations/` in filename order into that database.
2. Give the game a dedicated database user with SELECT, INSERT, UPDATE and DELETE on that database. Use the database administrator for migrations.
3. Copy `config/example.php` outside the web document root, set the database name and credentials, then set `SIMPLE_SLOT_CONFIG` in PHP's environment to its absolute path. In a standard XAMPP checkout at `C:\xampp\htdocs\simple-slot`, the default location is `C:\xampp\simple-slot.config.php`.
4. Start MySQL and serve the game directory with PHP. From the repository run `php -S 127.0.0.1:8765 -t ..` and visit [the local cabinet](http://127.0.0.1:8765/simple-slot/). Apache can serve the same directory under its existing subdirectory name.
5. Use HTTPS on a public host. The guest cookie is HttpOnly, scoped to the game directory and marked Secure under HTTPS. Removing it loses access to that guest save. An existing PHP-session wallet is imported on the first Free Play visit. A new guest starts at 10,000.00 credits.

## Free Play and Corner Rush

A normal spin costs 5.00. The server draws three rows of six fruit IDs. A maximal horizontal group of two through six matching fruit can begin anywhere in a row. Each of the eight three-cell diagonals and the four outer corners can also pay. Individual awards add even when patterns overlap.

The current rules, `fruit-v2`, group the eleven fruits into three tiers. One wild ticket in a pool of 88 substitutes for an actual fruit in a match. One interpretation per row wins, and every paying pattern needs a real fruit. Six matching fruits return 65.00, 100.00 or 200.00 depending on the tier. The server rounds half up to the nearest hundredth once per award. The cabinet displays the charged cost, total return and net change separately. The [generated paytable](docs/rules.md) includes every current and historical payout.

A corner taking part in a settled normal award lights once and stays lit. Completing all four clears the meter and awards three free spins at double payouts. These spins have priority and cost zero, even with an empty wallet. Bonus spins leave the lamps alone. Refresh or return to the game to finish them. When the wallet is below the normal spin cost and the bonus has finished, use the free refill to begin a fresh game.

## Score Attack and collections

Choose Score Attack in the mode selector and start a run. You get 30 normal spins, two charges and a separate score. A board stays provisional while you have a charge. Choose **Keep** to collect the preview, or choose a reel and **Respin** to replace its three cells and collect the revised award. A respin can lower the result. The last normal board and all earned bonuses settle before the scorecard appears. Return to the mode to resume a pending decision. Abandon ends the run without recording a score.

Eight lifetime achievements unlock four cabinet palettes in addition to the starting Jade cabinet. The Collection shows conditions and progress. Equipping a palette affects the cabinet colours. A free refill or abandoned run retains settled achievements. Personal bests are compared within the same machine, mode and rules version.

## Verification

Run from the source directory:

```sh
php tests/engine.php
php tests/corner-rush.php
php tests/challenge-boundaries.php
php sim.php --rules=fruit-v2 --spins=100000 --seed=orchard-2026
php sim.php --rules=fruit-v2 --runs=2000 --seed=orchard-2026
node tests/reliable-actions.mjs
```

The HTTP checks use a separate local server at port 8766 with its own migrated QA database. They create disposable guests. See the [implementation record](docs/milestones.md) for legacy fixtures and [simulation report](docs/simulation.md) for measured returns and bonus pacing.
