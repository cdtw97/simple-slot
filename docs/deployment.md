# Install, upgrade and roll back

Extract the ZIP outside the document root. Upload the **contents of `public/`** into the desired game directory under the host's document root. Keep the setup and documentation directories private. All game links and asset requests resolve relative to the game directory.

1. Select 64-bit PHP 8.2 with PDO MySQL. Enable HTTPS. Create the database with utf8mb4 encoding and InnoDB support.
2. Import the numbered SQL files from `setup/migrations/` in order using a migration account. Configure a separate runtime account with SELECT, INSERT, UPDATE and DELETE on this database.
3. Copy `setup/config.example.php` outside the document root, supply the database connection settings and set `SIMPLE_SLOT_CONFIG` to that file's absolute path in the PHP environment. On XAMPP with the project one directory below htdocs, the fallback is `C:\xampp\simple-slot.config.php`.
4. Open the cabinet in the intended subdirectory with a fresh browser profile. Confirm a 10,000.00 wallet, play a spin and refresh. Verify the balance, board and settled result survive. Keep database and PHP errors in private server logs.
5. Back up the database and previous runtime directory before upgrading. Close active write traffic briefly, apply new numbered migrations, switch the complete runtime directory together and reopen traffic. Preserve the game URL, database and guest cookie path.

For a rollback, stop write traffic. Restore a matched application and database snapshot if any guest has begun using a new rules version or save format. An older runtime cannot finish a game pinned to a newer rules registry. Restoring an earlier database snapshot also restores wallet, run and achievement states to that time. Retain the newer snapshot for reconciliation.

For the source checkout, the SQL lives in `migrations/` and the sample configuration in `config/example.php`. The local PHP development server binds to loopback. Use the public package on a production web server. The source repository contains developer verification tools and historical examples.

## Rebuild from source

The release builder uses PHP 8.2 with ZipArchive and the standalone esbuild 0.28.2 executable. Put esbuild on PATH or set `SIMPLE_SLOT_ESBUILD` to its absolute path. From the project directory run:

```sh
php scripts/package.php
```

The command writes `dist/simple-slot-release-3.zip` and prints its SHA-256 digest. Optional arguments select an archive basename and output directory, such as `php scripts/package.php preview ./dist`. The builder keeps readable modules in the source tree, bundles the module entry point into one minified JavaScript file, and minifies the stylesheet. The game host serves the resulting static assets directly.

The archive uses a fixed inventory, byte ordering, timestamps and file permissions. The builder reopens it to check the exact list and contents before replacing the output file. Repeated builds of the same sources with the same toolchain produce the same digest.
