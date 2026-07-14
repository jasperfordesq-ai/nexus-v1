# Retained Core and Helper Tests

Last reviewed: 2026-07-14

This directory contains 12 directly runnable PHPUnit test files for retained helpers under `app/Core/`. A further seven helper tests live in `tests/Helpers/`.

These directories are not named suites in `phpunit.xml`; the canonical CI suites are `Laravel`, `Integration`, and `LaravelMigrated`. Run the retained tests by path when changing one of the covered helpers.

## Current coverage

`tests/Core/` covers API error codes, audio uploads, authentication, trusted proxy/client-IP handling, CSRF, the database wrapper, email rendering, environment loading, mail delivery, menu management, rate limiting, and tenant context.

`tests/Helpers/` covers CORS, iCalendar output, image handling, navigation configuration, Sustainable Development Goal data, time formatting, and URL safety.

## Commands

```bash
# All retained core tests
vendor/bin/phpunit tests/Core --colors=always

# All retained helper tests
vendor/bin/phpunit tests/Helpers --colors=always

# One focused file
vendor/bin/phpunit tests/Core/ClientIpTest.php --colors=always
```

Database-backed files extend `App\Tests\DatabaseTestCase` and require the disposable `nexus_test` database. Pure helper files extend `App\Tests\TestCase`. The shared bootstrap and safety defaults are defined in `phpunit.xml` and `tests/bootstrap.php`; never point either command at a development or production database.

For canonical suite commands and test-writing policy, return to [../README.md](../README.md).
