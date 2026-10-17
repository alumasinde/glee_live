# glee_live

Gatepass Management System (GPMS) - plain PHP + MySQL (multitenant).

## Quick start

1. Start MySQL (XAMPP control panel), then run `composer install` in this folder.
2. Copy `.env.example` to `.env` and set your DB credentials.
3. Build the database and default data: `php bin/migrate.php up`.
4. Serve the app: `php -S 127.0.0.1:8000 -t public public/index.php`.
5. Open http://localhost:8000 - or deploy under Apache as http://gpms.local.
6. Other commands: `php bin/migrate.php status`, `down [N|all]`, `--db=name`.


 *   php bin/migrate.php status
 *   php bin/migrate.php up [N]      apply all pending (or next N) migrations
 *   php bin/migrate.php down [N|all] roll back last N (default 1) or everything
 *   php bin/migrate.php --db=name   override DB_DATABASE from .env
 