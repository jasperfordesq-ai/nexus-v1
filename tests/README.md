# Project NEXUS Test Suite

This directory contains the PHP test harness for the Laravel 12 backend. The older `tests/run-api-tests.php` runner still exists for legacy API smoke checks, but the canonical backend test entrypoint is PHPUnit via `phpunit.xml`.

## Canonical Commands

```bash
# Main Laravel suites
vendor/bin/phpunit --testsuite=Laravel,LaravelMigrated --colors=always

# Integration-focused tests
vendor/bin/phpunit --testsuite=Integration --colors=always

# Static analysis gate
vendor/bin/phpstan analyse --no-progress --memory-limit=512M --error-format=github
```

On the Docker PHP profile, prefix the PHP commands with `docker exec nexus-php-app`.

## Suite Layout

```text
tests/
├── Laravel/
│   ├── Feature/       # HTTP, controller, console, and integration-style feature tests
│   ├── Integration/   # Cross-service and journey-level backend tests
│   ├── Migrated/      # Tests for migrated Laravel API/controller behavior
│   └── Unit/          # Unit and service-level tests
├── Core/              # Legacy/core helper tests retained where still relevant
├── Ai/                # AI-related tests
├── Helpers/           # Shared helpers
├── bootstrap.php      # PHPUnit bootstrap
└── run-api-tests.php  # Legacy API smoke runner, not the canonical coverage source
```

## Test Environment

`phpunit.xml` forces safe testing defaults:

- `APP_ENV=testing`
- `DB_NAME=nexus_test`
- `DB_DATABASE=nexus_test`
- array cache/session/mail drivers
- sync queue
- null broadcaster

Do not point tests at production or a real tenant database. If a test needs schema state, use the Laravel test helpers and migrations/schema dump rather than ad hoc writes to shared data.

## Writing Tests

- Prefer Laravel feature/integration tests for API behavior and tenant boundaries.
- Assert real outcomes: response status, database state, balance changes, emitted jobs/events, or translated output.
- Avoid `assertTrue(true)`, broad `assertContains([200, 403, 500])`, unconditional skips, or tests that only check `class_exists`.
- Tenant-scoped behavior must include a tenant-isolation assertion when the route or service crosses tenant data.
- Money, auth, federation, wallet, messaging, and GDPR paths should include regression tests for both success and failure cases.

## Frontend And E2E

React/Vitest tests live under `react-frontend/src/**/*.test.*`.

Playwright E2E tests live under `e2e/` and are run from the repository root:

```bash
npm run test:e2e
npx playwright test e2e/tests/smoke.spec.ts --grep '@smoke' --project=chromium-modern
```

See [../e2e/README.md](../e2e/README.md) and [../docs/TESTING.md](../docs/TESTING.md) for browser-test status and remaining debt.
