# glee_live

Gatepass Management System (GPMS) - Multi-tenant visitor gatepass system built with plain PHP + MySQL.

## Quick Start

1. Start MySQL (XAMPP), then run `composer install`
2. Copy `.env.example` to `.env` and configure DB credentials
3. Build database: `php bin/migrate.php up`
4. Start server: `composer serve` (or `php -S localhost:8000 -t public`)
5. Open http://localhost:8000

## Database Commands

- `php bin/migrate.php status` - View migration status
- `php bin/migrate.php up [N]` - Apply pending migrations
- `php bin/migrate.php down [N|all]` - Rollback migrations
- `php bin/migrate.php --db=name` - Override database from .env

## Testing

Run tests:

```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test suite
./vendor/bin/phpunit tests/Integration/AuthFlowTest.php
./vendor/bin/phpunit tests/Unit/ValidatorTest.php
```

