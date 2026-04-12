# Service Layer — Static → DI Migration Guide

Status: in progress (TD9). This document describes the historical static/instance
split in `app/Services/`, the direction we are moving (instance + DI), and the
mechanical conversion pattern used to migrate a service.

## Background

Project NEXUS started as a procedural PHP application with a custom service
locator. Most services were written as **fully static classes** — a namespace of
globally-accessible functions. When the codebase was ported to Laravel 12, the
container and binding infrastructure became available, but the services
themselves were left as-is. The result is a ~50/50 split across the ~233
services in `app/Services/`:

- Some services are fully static (no constructor, every public method is
  `public static function ...`).
- Some services are fully instance-based (constructor, methods on `$this`),
  resolved via the Laravel container.
- A few are hybrids — instance methods alongside leftover statics.

## Why move to instance + DI

1. **Testability.** Static methods cannot be mocked without `Mockery::mock('alias:...')`,
   which requires runkit-style magic, breaks in parallel test runs, and
   interacts badly with PHPUnit process isolation. Instance methods are trivial
   to stub with standard Mockery or PHPUnit prophecy.
2. **Laravel idiom.** Controllers, jobs, listeners, and console commands all
   receive dependencies through the container. Calling static methods inside a
   constructor-injected controller is inconsistent and hides real dependencies.
3. **Explicit contracts.** A constructor parameter documents what a class
   actually depends on. A static call buried inside a 300-line method does not.
4. **Future-proofing.** Interfaces, decorators, and per-tenant strategy objects
   all require instance dispatch. Static calls foreclose those options.

We are not deprecating static methods platform-wide in a single PR. The
migration is incremental: one service at a time, fully converted end-to-end
(service + every caller + register in container), committed, tested.

## Migration pattern (before / after)

### Before (static)

```php
// app/Services/AuditLogService.php
class AuditLogService
{
    public static function log($action, $orgId = null, $userId = null, $details = [])
    {
        $tenantId = TenantContext::getId();
        return DB::table('org_audit_log')->insertGetId([
            'tenant_id' => $tenantId,
            'action'    => $action,
            // ...
        ]);
    }

    public static function logUserCreated($adminId, $targetId, $email = '')
    {
        return self::log('admin_user_created', null, $adminId, [
            'created_email' => $email,
        ], $targetId);
    }
}

// Caller
AuditLogService::logUserCreated($adminId, $newUserId, $email);
```

### After (instance + DI)

```php
// app/Services/AuditLogService.php
class AuditLogService
{
    public function log($action, $orgId = null, $userId = null, $details = [])
    {
        $tenantId = TenantContext::getId();
        return DB::table('org_audit_log')->insertGetId([
            'tenant_id' => $tenantId,
            'action'    => $action,
            // ...
        ]);
    }

    public function logUserCreated($adminId, $targetId, $email = '')
    {
        return $this->log('admin_user_created', null, $adminId, [
            'created_email' => $email,
        ], $targetId);
    }
}

// app/Providers/AppServiceProvider.php
$this->app->singleton(AuditLogService::class, fn () => new AuditLogService());

// Controller caller (preferred)
class AdminUsersController extends BaseApiController
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function create(Request $request): JsonResponse
    {
        // ...
        $this->auditLogService->logUserCreated($adminId, $newUserId, $email);
    }
}

// One-off caller (no constructor available — service that's itself still static, or legacy context)
app(AuditLogService::class)->log('admin_bulk_delete', null, $adminId, [...]);
```

Key mechanical changes:

1. `public static function X(...)` → `public function X(...)`.
2. Internal `self::other(...)` → `$this->other(...)`.
3. `private static` constants stay static (they are compile-time data, not
   state). `private static` *methods* should be converted to `private function`
   for consistency.
4. Register as a singleton in `AppServiceProvider::register()`:
   `$this->app->singleton(FooService::class, fn () => new FooService());`
   (Most of our services are stateless — singleton is safe and avoids
   per-request construction overhead.)
5. Update every caller:
   - **Controllers with a constructor**: add the service as a `private readonly`
     property and use `$this->fooService->method(...)`.
   - **One-off / legacy / non-constructor contexts**: `app(FooService::class)->method(...)`.
6. Grep: `grep -rn 'FooService::' app/ src/ httpdocs/` — should return only
   `::class` usages, constants, and the singleton registration.
7. `php -l` every modified file.

## Pure-data helpers may remain static

If a method takes no external state and returns a computed value from its
arguments alone (e.g. `AuditLogService::getActionLabel($action)` which looks up
a `const` map), it is fine to leave it `public static`. These are equivalent to
enum accessors and do not interact with the container.

## How to pick the next service to convert

Use `scripts/audit-static-services.php` to see the list of services still using
static methods, sorted by count. Then choose a candidate based on:

1. **Call-site count.** Run `grep -r 'FooService::' app/ | wc -l`. Prefer services
   with 5–30 callers — fewer than that is noise, more than that will balloon
   the PR.
2. **Blast radius.** Is it called from a single controller, or from dozens
   scattered through the codebase? A tightly-scoped service (e.g. an admin-only
   feature) is easier to land cleanly.
3. **Test coverage.** If the service has a PHPUnit test suite, the conversion
   is verifiable. If not, consider adding a smoke test first so you can detect
   regressions.
4. **Complexity of internal state.** Services with private static caches or
   in-process memoization need care — a singleton instance behaves the same
   way but the semantics must be audited.
5. **Existing registration.** Many services are already `$this->app->singleton()`'d
   in `AppServiceProvider` even though they are only called statically —
   those are the cheapest wins.

Avoid for now:
- Services with `Mockery::mock('alias:...')` in their tests — the tests need
  to be rewritten before the conversion.
- Services called from legacy `src/` files (all but ~43 have been deleted;
  check before starting).
- Services that expose a lot of `const` values read statically in views —
  those constants can stay.

## Conversion checklist

Per service:

- [ ] Read the service end-to-end. Note every `self::` call.
- [ ] Change `public static function` → `public function` on every public method
      (except pure-data helpers).
- [ ] Change `private static function` → `private function` for consistency.
- [ ] Convert every `self::methodName(...)` → `$this->methodName(...)`.
- [ ] Leave `self::CONST_NAME` and `self::ARRAY_CONST` unchanged.
- [ ] Ensure the singleton is registered in `AppServiceProvider::register()`.
      Many services already are.
- [ ] `grep -rn 'MyService::' app/ src/ httpdocs/` and update every caller:
  - Controller with existing constructor → add `private readonly MyService $myService`
    constructor parameter and rewrite calls as `$this->myService->...`.
  - Controller without a constructor, legacy service, or any other one-off →
    `app(MyService::class)->...`.
- [ ] `php -l` every modified file.
- [ ] Run the service's PHPUnit test file, e.g.
      `docker exec nexus-php-app vendor/bin/phpunit tests/Laravel/Unit/Services/MyServiceTest.php`.
      If the tests fail because they use `MyService::method(...)`, update them
      to call `app(MyService::class)->method(...)` or `(new MyService())->method(...)`.
- [ ] Run `scripts/audit-static-services.php` — the count should decrease.
- [ ] Commit with message `refactor(services): convert MyService from static to instance/DI`.

## Already converted (reference)

- `AuditLogService` — 33 static methods → 0. Callers: 5 controllers.
- `TenantSettingsService` — all public statics → instance. Callers: 4
  controllers, 4 services, 1 core helper.

See `scripts/audit-static-services.php` for the current remaining count.
