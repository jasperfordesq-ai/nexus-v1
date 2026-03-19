# Laravel Migration Plan — Project NEXUS

## Context

Project NEXUS migrated from a custom PHP 8.2 framework to Laravel 12.54. The React frontend is unaffected — only the PHP backend changed.

## Strategy: In-Place Incremental Migration

**Not a strangler fig or parallel repo.** Work in-place on a long-lived `laravel-migration` branch. Convert file-by-file, merge incrementally, test against the same database.

The key enabler: a **bridge layer** that let Laravel and the legacy framework coexist. Legacy routes fell through to the old router; converted routes used Laravel. Both shared the same DB connection.

---

## Current Status (2026-03-19)

**Phases 0-5 are complete.** Laravel is the sole HTTP handler. The legacy framework no longer boots for any request. Performance improved from 8.4s/req (bridge mode) to ~100ms/req (pure Laravel + JIT + OPCache).

**What is genuinely Laravel (62% of services):**
- Entry point, routing, middleware pipeline (100%)
- 68 Eloquent models with automatic `HasTenantScope` (100%)
- ~154 services with real Eloquent/DI implementations (wallet, listings, users, search, auth, notifications, reviews, events, admin dashboards, categories)
- Events, broadcasting, queue dispatch
- Auth (Sanctum tokens)

**What is still legacy PHP wrapped by Laravel (28% of services):**
- ~70 pure wrapper services that delegate to `src/Services/` static methods (matching, collaborative filtering, embeddings, federation messaging, achievement analytics, group recommendations)
- These are algorithmic/integration-heavy — stable and rarely changed

**What is partially migrated (10% of services):**
- ~25 mixed services with some methods converted to Eloquent, some still delegating

### Legacy Code Remaining

| Category | Files | Lines | Status |
|----------|-------|-------|--------|
| `src/Services/` | 215 | ~100K | 62% genuinely migrated, 28% wrappers, 10% mixed |
| `src/Core/` | ~25 | ~15K | `TenantContext` + `Database` still critical; `Router`, `View` unused but referenced |
| `src/Helpers/` | 8 | ~3K | Still in use |
| `src/Models/` | 2 | <1K | `User.php` used by `SuperPanelAccess`, `EmailSettings.php` used by `Mailer` |
| `views/admin/` + `views/modern/admin/` | ~200 | -- | Legacy admin panels, intentionally NOT migrated |
| `views/emails/` | ~25 | -- | Email templates, still in use |

### Deleted (2026-03-19)

| Category | Files | Lines | Why safe |
|----------|-------|-------|----------|
| `src/Controllers/` | 80 | ~45K | All 1,223 routes served by Laravel controllers. Two blocking controllers (`CronController`, `NewsletterTrackingController`) relocated to `app/Services/CronJobRunner.php` and `app/Services/NewsletterTrackingService.php` before deletion. |
| `tests/Controllers/` | 142 | -- | Scaffold tests for deleted controllers (class_exists checks only) |

---

## Phases

### Phase 0 — Foundation (COMPLETE)

Install Laravel alongside the existing app without breaking anything.

1. [x] `composer require laravel/framework:^12.54 laravel/sanctum:^4.3` (--ignore-platform-reqs for PHP 8.2 compat)
2. [x] Created `bootstrap/app.php`, `config/` (laravel.php, database.php, cache.php, auth.php, sanctum.php), `artisan`
3. [x] **Bridge in index.php**: Laravel handles request first; if 404, falls through to legacy `Router::dispatch()`
4. [x] **Tenant middleware**: `App\Http\Middleware\ResolveTenant` delegates to existing `TenantContext::resolve()`
5. [x] **TenantScope**: `App\Scopes\TenantScope` + `App\Models\Concerns\HasTenantScope` trait for Eloquent models
6. [x] **Health check verified**: `/api/laravel/health` returns Laravel response, legacy routes unaffected
7. [x] DB bridge: `Database::setLaravelConnection()` shares Laravel's PDO with legacy code
8. [ ] ~~Add `tests/Laravel/` directory~~ (created but `TestCase` class has autoload issue — pre-existing)

**Files created:**
- `bootstrap/app.php` — Laravel application bootstrap
- `config/laravel.php`, `config/database.php`, `config/cache.php`, `config/auth.php`, `config/sanctum.php`
- `app/Http/Middleware/ResolveTenant.php` — Tenant middleware (delegates to TenantContext)
- `app/Scopes/TenantScope.php` — Eloquent global scope for tenant isolation
- `app/Models/Concerns/HasTenantScope.php` — Trait to add TenantScope to models
- `app/Providers/AppServiceProvider.php`, `routes/api.php`, `artisan`

**Modified:**
- `httpdocs/index.php` — Added Laravel bridge before legacy route loading
- `composer.json` / `composer.lock` — Added laravel/framework + laravel/sanctum + 56 dependencies

### Phase 1 — Route Migration (COMPLETE)

**1,224 API routes** migrated to Laravel's routes/api.php. All 14 legacy route files commented out. Still in legacy: ~600 non-API routes (super-admin, admin-legacy views, cron, closures).

Existing controllers still use `echo json_encode()` + `exit()` — Laravel routes call them directly. Controller conversion to return proper Laravel Response objects is a later phase.

### Phase 2 — Models (COMPLETE — 68 models)

All Eloquent models created with `HasTenantScope`. Tested against live database.

### Phase 3 — Services (IN PROGRESS — 62% genuine)

DI pattern established. **249 Laravel services** created (vs 215 legacy).

| Status | Count | Description |
|--------|-------|-------------|
| **Genuine** | ~154 | Real Eloquent/DI implementations (wallet, listings, users, search, auth, etc.) |
| **Wrappers** | ~70 | Delegate to legacy static services (matching, filtering, embeddings, federation) |
| **Mixed** | ~25 | Partially converted — good candidates to finish next |

### Phase 4 — Controllers (COMPLETE — 130 Laravel controllers)

All API controllers converted. Legacy controllers deleted (2026-03-19).

### Phase 5 — Activation (COMPLETE — 2026-03-18)

Laravel is now the sole HTTP handler. The legacy bridge pattern (boot both frameworks, fall through on 404) has been replaced with a pure Laravel entry point.

**What changed:**
- `httpdocs/index.php` — rewritten from 550-line legacy boot to 40-line Laravel entry point
- `bootstrap/app.php` — removed duplicate route loading (was loading 2,441 routes instead of 1,223)
- `config/cors.php` — added `v2/*` to CORS paths (routes use `/v2/...` not `/api/...`)
- `app/Http/Middleware/SecurityHeaders.php` — new middleware replacing inline headers from legacy index.php
- `Dockerfile` — OPCache tuning (256MB, 30K files, JIT tracing with 100MB buffer, preloading)
- `scripts/opcache-preload.php` — preloads Laravel framework + app classes into shared memory

**Performance:** 8.4s/request (bridge) → ~100ms (pure Laravel + JIT + OPCache preload)

**All 1,223 routes** served by Laravel across 126 controllers. Legacy PHP framework no longer boots for any request.

---

## Phase 6 — Legacy Cleanup (NOT STARTED)

Remaining work to make this a fully Laravel application. Ordered by risk and value.

### 6a. Finish mixed services (~25 services) — LOW RISK
Services that are partially converted. Some methods use Eloquent, some delegate. Finish the conversion.
- **Effort:** Small per service
- **Risk:** Low — partial logic already works in Laravel
- **Approach:** As you touch each service for bugs/features, finish the conversion

### 6b. Convert wrapper services (~70 services) — MEDIUM RISK
Pure wrappers that just call legacy static methods. Convert to Eloquent/DI.
- **Effort:** Medium — rewrite each with Eloquent
- **Risk:** Medium — algorithmic code (matching, filtering, embeddings) with low test coverage
- **Approach:** Incrementally, as you touch them. Do NOT bulk-rewrite without tests.
- **Key services:** SmartMatchingEngine, CollaborativeFilteringService, EmbeddingService, FederatedMessageService, AchievementAnalyticsService, GroupRecommendationEngine

### 6c. Remove dead framework classes — LOW RISK
Classes in `src/Core/` that are no longer called but could not be deleted yet due to entanglement.

| Class | Lines | Blocker |
|-------|-------|---------|
| `Router.php` | 9.4K | Used by `generate_openapi.php` |
| `View.php` | 4.6K | Was referenced by deleted controllers — **may now be deletable** (needs verification) |
| `MenuGenerator.php` | -- | Called directly by `MenuManager.php` lines 400-417 (no `class_exists` guard) |
| `DefaultMenus.php` | 13K | Called by `MenuManager.php` with `class_exists` guard, but also calls `MenuGenerator` internally |

**To unblock MenuGenerator/DefaultMenus deletion:** Rewrite `MenuManager.php` lines 400-417 to not delegate to `MenuGenerator`. Then both can go.

### 6d. Migrate core framework classes — HIGH VALUE, HIGH RISK
The two most critical legacy classes.

| Class | Lines | Why it matters |
|-------|-------|---------------|
| `TenantContext` | 802 | 7 resolution strategies, called everywhere. Both `src/Core/` and `app/Core/` versions exist. |
| `Database.php` | 9.4K | Raw PDO wrapper used by legacy services. Once wrapper services (6b) are gone, this has no callers. |

**Approach:** Do `Database.php` last — once all services use Eloquent/`DB::` facade, it becomes dead code. `TenantContext` can be rewritten as a proper Laravel singleton after that.

### 6e. Migrate legacy models — LOW EFFORT, BLOCKED

| Model | Blocker |
|-------|---------|
| `src/Models/User.php` | `SuperPanelAccess::canManageTenant()` calls `User::isGod()` |
| `src/Models/EmailSettings.php` | `App\Core\Mailer` imports it directly |

**Fix:** Move `isGod()` to `App\Models\User`, update `SuperPanelAccess`. Move `EmailSettings` logic to Eloquent model, update `Mailer`.

### 6f. Admin panel modernisation (OPTIONAL) — LOW PRIORITY
- `views/admin/` + `views/modern/admin/` (~200 files) serve `/admin-legacy/` and `/super-admin/`
- Could convert to Laravel Blade or React admin panel
- **Not recommended now** — works fine, low traffic, admin-only

---

## Test Coverage

| Suite | Tests | Status |
|-------|-------|--------|
| Unit | 249 | 12 pre-existing errors (`ResolveAdminTenantFilterTest`) |
| Services | 1,283 | 95 pre-existing errors (DB access to `nexus_test`), 56% coverage gap |
| Models | -- | Pre-existing DB access errors |
| Laravel | 17 | `TestCase` autoload issue (pre-existing) |
| Controllers | -- | **Deleted** (were scaffold tests for removed legacy controllers) |

**Priority:** Fix `nexus_test` database access to unblock service tests before converting more services.

---

## Critical Risks

| Risk | Mitigation |
|---|---|
| API contract breakage (React breaks) | Response shape logging + snapshot tests |
| Multi-tenant scope leak | `TenantScope` global scope is safer than manual scoping |
| Wrapper service rewrite breaks logic | Add tests BEFORE converting untested services |
| 56% services untested | Fix `nexus_test` DB access, add tests incrementally |
| `TenantContext` rewrite breaks everything | Do last, after all dependencies migrated |

## What NOT to Migrate

- Legacy PHP admin views (`views/admin/`, `views/modern/admin/`) — leave as-is (optional future work)
- React frontend — untouched
- PHP i18n files (`lang/`) — only used by legacy admin
- PageBuilder — low priority

---

## Production Impact & Parallel Development

All migration work happens on a `laravel-migration` branch. `main` remains the stable production branch. Periodically merge `main` into `laravel-migration` to stay current.

```bash
git checkout laravel-migration
git merge main
```

**NEVER merge `laravel-migration` into `main` without explicit user approval.**

## Verification

After each phase:
1. Run PHPUnit suites (Unit, Services, Models)
2. Hit converted endpoints via React frontend
3. Verify tenant scoping with cross-tenant test queries
4. Check Docker builds still work
