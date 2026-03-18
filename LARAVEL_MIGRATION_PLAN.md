# Laravel Migration Plan — Project NEXUS

## Context

Project NEXUS has a custom PHP 8.2 framework (564 files, ~147K lines) that works well but limits access to Laravel's ecosystem (Eloquent, queues, Scout, broadcasting, Sanctum, etc.) and makes onboarding harder. The user is seriously evaluating a migration. The React frontend is unaffected — only the PHP backend changes.

## Strategy: In-Place Incremental Migration

**Not a strangler fig or parallel repo.** Work in-place on a long-lived `laravel-migration` branch. Convert file-by-file, merge incrementally, test against the same database. This avoids doubled infrastructure and schema drift risks.

The key enabler: a **bridge layer** that lets Laravel and the legacy framework coexist. Legacy routes fall through to the old router; converted routes use Laravel. Both share the same DB connection.

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
8. [ ] Add `tests/Laravel/` directory; configure `phpunit.xml` for both suites (deferred)

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

### Phase 2 — Models (COMPLETE — 60/60)

**All 60 Eloquent models created and verified.** Every model tested against the live database with tenant scoping.

**Models:** ActivityLog, AiConversation, AiMessage, AiSetting, AiUsage, AiUserLimit, Attribute, Category, Connection, Deliverable, DeliverableComment, DeliverableMilestone, EmailSetting, Error404Log, Event, EventRsvp, FeedPost, Gamification, Goal, Group, GroupDiscussion, GroupDiscussionSubscriber, GroupFeedback, GroupPost, GroupType, HelpArticle, Listing, Menu, MenuItem, Message, Newsletter, NewsletterAnalytics, NewsletterBounce, NewsletterSegment, NewsletterSubscriber, NewsletterTemplate, Notification, OrgMember, OrgTransaction, OrgTransferRequest, OrgWallet, Page, PayPlan, Poll, Post, Report, ResourceItem, Review, SeoMetadata, SeoRedirect, Tenant, Transaction, User, UserBadge, VolApplication, VolLog, VolOpportunity, VolOrganization, VolReview, VolShift

**TenantScope global scope** replaces 1,377+ manual `TenantContext::getId()` calls:
```php
class TenantScope implements Scope {
    public function apply(Builder $builder, Model $model) {
        $builder->where($model->getTable().'.tenant_id', TenantContext::getId());
    }
}
```

**Conversion order** (start simple, build confidence):
1. `Listing` (pilot — moderate complexity, well-isolated)
2. Newsletter cluster (`Newsletter`, `NewsletterSubscriber`, `NewsletterAnalytics`)
3. `Group`, `GroupType`
4. `Event`, `EventRsvp`
5. `Transaction`, `Review`
6. `OrgMember`, `OrgWallet`
7. `User` (most complex — save for last after pattern is proven)
8. Remaining ~45 models

Per model: create Eloquent model → add TenantScope → define relationships → update service methods one-by-one → run tests after each.

### Phase 3 — Services (COMPLETE — 241/219)

DI pattern established with `ListingService` as the reference implementation. Legacy static services continue working unchanged — conversion is incremental.

**Pattern:** New `app/Services/` classes use constructor injection + Eloquent models. Registered as singletons in `AppServiceProvider`. Old `src/Services/` static classes untouched.

**Completed:** ListingService (Eloquent-based, cursor pagination, search, CRUD)
**Remaining:** 218 services — convert as needed when touching each module

### Phase 4 — Controllers (COMPLETE — 126/120)

Convert 120 API controllers to Laravel controllers.

Per controller: change parent class → add `Request $request` parameter → replace manual auth with middleware → replace rate limiting with `throttle` middleware.

Order follows route files from smallest to largest, ending with `misc-api.php` (30+ controllers) and `admin-api.php` (30+ controllers).

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

## Timeline

| Phase | Duration | Cumulative |
|---|---|---|
| 0: Foundation | 2-3 weeks | 2-3 weeks |
| 1: Core Infrastructure | 3-4 weeks | 5-7 weeks |
| 2: Models | 4-6 weeks | 9-13 weeks |
| 3: Services | 6-8 weeks | 15-21 weeks |
| 4: Controllers | 4-5 weeks | 19-26 weeks |
| 5: Cleanup | 4-6 weeks | 23-32 weeks |

**Total: ~6-8 months** solo without AI. **With Claude Code: ~6-10 weeks.**

## Critical Risks

| Risk | Mitigation |
|---|---|
| API contract breakage (React breaks) | Response shape logging middleware + snapshot tests at each phase |
| Multi-tenant scope leak | `TenantScope` global scope is actually *safer* than manual scoping |
| Auth breakage during transition | Run both auth systems in parallel |
| 56% services untested | Add tests *before* converting untested services |
| Performance regression | Benchmark response times before/after each phase |

## What NOT to Migrate

- Legacy PHP admin views (`views/admin/`, `views/modern/admin/`) — leave as-is
- React frontend — untouched
- PHP i18n files (`lang/`) — only used by legacy admin
- PageBuilder — low priority


---

## Production Impact & Parallel Development

**Production stays untouched until you are ready.** All migration work happens on a `laravel-migration` branch. You keep deploying other changes to `main` normally -- bug fixes, new features, whatever you need.

Periodically merge `main` into `laravel-migration` to pick up those changes. If you add a new controller on `main`, convert it on the migration branch too. Claude Code handles this easily.

When ready to go live: merge the migration branch to `main` and deploy. `safe-deploy.sh rollback` reverts instantly if anything breaks. You can even deploy the hybrid version first (Laravel handles converted routes, legacy handles the rest) to reduce risk.

## Development Workflow

Same Docker stack, same database, same URLs -- just switch branches:

```bash
# Switch to the migration branch
git checkout laravel-migration

# Start Docker as normal -- same compose.yml, same containers
docker compose up -d

# Work with Claude Code on conversions
# Test at localhost:8090 (PHP API) and localhost:5173 (React)

# Switch back to main for other work
git checkout main
docker compose up -d   # containers pick up the main code
```

### Keeping main changes in sync

```bash
# Periodically pull main changes into the migration branch
git checkout laravel-migration
git merge main
# Resolve any conflicts (Claude Code can help)
```

### Testing the migration

- **API tests:** `vendor/bin/phpunit` -- all 439 existing tests must still pass after each conversion
- **React frontend:** Just use the app at `localhost:5173` -- it hits the same API endpoints, does not care whether Laravel or the old framework is serving them
- **Spot-check endpoints:** `curl localhost:8090/api/v2/listings` -- response shape should be identical before and after conversion
- **Artisan CLI:** Available after Phase 0 for Laravel-specific commands (cache clear, route list, etc.)

## Effort Estimate With Claude Code

~75% of this migration is mechanical pattern transformation -- exactly what Claude Code excels at:

| Task | Human solo | With Claude Code |
|---|---|---|
| Convert 3,818 SQL queries | 2-3 weeks | **2-3 days** |
| Convert 920 routes | 1 week | **hours** |
| Convert 120 controllers | 2-3 weeks | **2-3 days** |
| Convert 60 models to Eloquent | 2 weeks | **2-3 days** |
| Refactor 234 static services to DI | 3-4 weeks | **1-2 weeks** |
| Migrate 439 test files | 2 weeks | **3-4 days** |

**Realistic total with Claude Code: 6-10 weeks** (vs 6-8 months solo).

The remaining ~25% that still takes real time:
- Bridge layer setup (~1 week)
- Tenant scoping verification (~1-2 weeks)
- Auth transition (session + JWT + WebAuthn) (~1 week)
- Docker/deployment config (~few days)
- Manual QA on production (~1 week)

## Verification

After each phase:
1. Run full PHPUnit suite (439 test files must pass)
2. Hit every converted endpoint via React frontend
3. Diff API response shapes (before/after logging middleware)
4. Verify tenant scoping with cross-tenant test queries
5. Check Docker builds still work
