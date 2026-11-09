# Simple Slot · CDTW Games

A six-reel fruit arcade for virtual credits. Play immediately as a guest. The cabinet keeps the wallet, lit corners and queued free spins on the server.

## Run the game

The verified local stack uses 64-bit PHP 8.2 with PDO MySQL and MariaDB 10.11 with InnoDB. A modern browser with JavaScript and first-party cookies is required. Production runs on PHP without Node or third-party runtime assets.

1. Create an empty database, for example `simple_slot_arcade`, using utf8mb4. Import the SQL migrations from `migrations/` in filename order into that database.
2. Give the game a dedicated database user with SELECT, INSERT, UPDATE and DELETE on that database. Use the database administrator for migrations.
3. Copy `config/example.php` outside the web document root, set the database name and credentials, then set `SIMPLE_SLOT_CONFIG` in PHP's environment to its absolute path. In a standard XAMPP checkout at `C:\xampp\htdocs\simple-slot`, the default location is `C:\xampp\simple-slot.config.php`.
4. Start MySQL and serve the game directory with PHP. From the repository run `php -S 127.0.0.1:8765 -t ..` and visit [the local cabinet](http://127.0.0.1:8765/simple-slot/). Apache can serve the same directory under its existing subdirectory name.
5. Use HTTPS on a public host. The guest cookie is HttpOnly, scoped to the game directory and marked Secure under HTTPS. Removing it loses access to that guest save. An existing PHP-session wallet is imported on the first Free Play visit. A new guest starts at 10,000.00 credits.

## Release 1 · Free Play and Corner Rush

A normal spin costs 5.00. The server draws three rows of six fruit IDs. A maximal horizontal group of two through six matching fruit can begin anywhere in a row. Each of the eight three-cell diagonals and the four outer corners can also pay. Individual awards add even when patterns overlap.

The first-release rules use the live original equal-weight eleven fruits. At the fixed stake, a pair returns 3.15, a triple 5.40, four 5.90, five 6.40 and six 50.00. A diagonal returns 5.40 and four matching corners return 5.90. The server rounds half up to the nearest hundredth once per award. The cabinet displays the charged cost, total return and net change separately.

A corner taking part in a settled normal award lights once and stays lit. Completing all four clears the meter and awards three free spins at double payouts. These spins have priority and cost zero, even with an empty wallet. Bonus spins leave the lamps alone. Refresh or return to the game to finish them. When the wallet is below the normal spin cost and the bonus has finished, use the free refill to begin a fresh game.

## Verification

Run from the source directory:

```sh
php tests/engine.php
php tests/corner-rush.php
php sim.php --rules=fruit-v1 --spins=100000 --seed=orchard-2026
node tests/reliable-actions.mjs
```

The HTTP checks use a separate local server at port 8766 with its own migrated QA database. They create disposable guests. See the [implementation record](docs/milestones.md) for legacy fixtures and [simulation report](docs/simulation.md) for measured returns and bonus pacing.
