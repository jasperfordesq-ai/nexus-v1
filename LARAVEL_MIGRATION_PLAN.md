# Laravel Migration Record — Project NEXUS

> **Status:** Complete. The Laravel migration was merged to `main` on 2026-03-19 and is live in production. This file is retained as a short historical record and as current guidance for future backend work.

## Current Architecture

Project NEXUS now runs as a Laravel 12.54 application on PHP 8.2+.

- Laravel is the sole HTTP handler.
- PHP source lives under `app/` with PSR-4 namespace `App\`.
- `composer.json` no longer autoloads the former legacy PHP namespace.
- The top-level legacy `src/` directory has been removed.
- API routes are in `routes/api.php`; accessible frontend routes are in `routes/govuk-alpha.php` and `routes/govuk-alpha-parity/`.
- The React 19 frontend remains the primary UI; the accessible frontend is the approved HTML-first exception.

## Completed Migration Milestones

| Area | Result |
|------|--------|
| Entry point | `httpdocs/index.php` boots Laravel directly |
| Routing | Laravel routes handle the API and server-rendered accessible frontend |
| Middleware | Tenant resolution, CORS, maintenance mode, security headers, and auth are Laravel middleware |
| Controllers | API controllers live under `app/Http/Controllers/Api/` |
| Models | Eloquent models live under `app/Models/` and use Laravel patterns |
| Services | Services live under `app/Services/`; old delegation stubs have been removed or converted |
| Events/listeners | Laravel events/listeners are implemented under `app/Listeners/` and related namespaces |
| Auth | Sanctum, 2FA, WebAuthn/passkeys, and tenant-aware login are Laravel-backed |
| Tests | Laravel feature, integration, migrated, and unit tests live under `tests/Laravel/` |

## Schema And Migrations

New schema changes must use Laravel migrations in `database/migrations/`.

The full current schema dump is committed at:

```text
database/schema/mysql-schema.sql
```

Refresh it after schema changes:

```bash
bash scripts/refresh-schema-dump.sh
```

Commit both the Laravel migration and the refreshed schema dump. The legacy SQL files under `migrations/` are historical and should not be used for new schema work.

## Backend Contribution Rules

- Put new PHP code in `app/`.
- Follow existing Laravel services, controllers, models, and tests.
- Scope tenant data through the current tenant-aware models/services.
- Never add a new legacy PHP namespace or top-level `src/` code.
- Do not create PHP views for product UI. Use the React frontend or the approved accessible frontend.
- Verify backend changes with the relevant PHPUnit suite and PHPStan/Larastan.

## Historical Notes

The migration was performed in phases:

1. Laravel was introduced alongside the legacy custom PHP framework.
2. Routing, middleware, controllers, models, services, auth, and events were moved to Laravel.
3. The bridge/fallback layer was removed.
4. Legacy controllers, models, service delegates, and the top-level `src/` tree were deleted.

Older versions of this document contained a phase-by-phase worklist with references to remaining top-level legacy PHP files, legacy classes, stub services, and legacy admin panels. Those references are intentionally removed because they no longer describe the current codebase.
