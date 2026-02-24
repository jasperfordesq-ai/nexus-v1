# Admin Panel Users — Tenant 2 Audit

**Date:** 2026-02-24
**Reported by:** User (live admin panel observation)
**Priority:** P0 — breaks Tenant 2 users list for prospects

---

## Symptoms

- Admin Panel → Tenant 2 → Users page shows **test users from other tenants** and one new signup.
- **Real Tenant 2 users are NOT showing** at all.
- Issue introduced by sweeping code changes on 2026-02-24.

---

## Root Cause

### Primary: Super admin tenant bypass removes ALL tenant scoping (P0)

**Commit:** `634e76fe` — "enable super admin cross-tenant access for all moderation features"

This commit changed all 8 admin API controllers to bypass tenant scoping for super admins.
In `AdminUsersApiController::index()` (lines 75-85):

```php
if ($isSuperAdmin) {
    if ($filterTenantId) {
        $conditions[] = 'u.tenant_id = ?';
        $params[] = $filterTenantId;
    }
    // No tenant filter = all tenants for super admin  ← BUG: unsafe default
} else {
    $conditions[] = 'u.tenant_id = ?';
    $params[] = $tenantId;
}
```

**What happens:**
1. Super admin logs into Tenant 2 admin panel.
2. React sends `X-Tenant-ID: 2` header → `TenantContext::getId()` returns `2`.
3. `$isSuperAdmin = true` (JWT claim or DB lookup).
4. Frontend `UserList.tsx` does NOT send `?tenant_id=X` query param.
5. `$filterTenantId = null` → inner condition is skipped.
6. **NO tenant condition is added to the WHERE clause.**
7. Query returns ALL users across ALL tenants.
8. `ORDER BY created_at DESC LIMIT 20` shows newest users first.
9. Recently created test/seed users from other tenants fill the first page.
10. Real Tenant 2 users (created earlier) are pushed to later pages.

**Evidence:**
- `AdminUsersApiController.php:75-85` — no tenant filter for super admin without `?tenant_id`.
- `UserList.tsx:94-99` — params object has no `tenant_id` field.
- Same pattern confirmed in all 8 admin controllers.

### Secondary: Frontend never sends tenant_id param (P2)

`UserList.tsx` (line 94-99) constructs params without `tenant_id`:
```typescript
const params: UserListParams = {
  page,
  limit: 20,
  search: search || undefined,
  status: filter === 'all' ? undefined : filter,
};
```

While the `X-Tenant-ID` header IS sent (via `api.ts` line 251-253), the backend ignores
it for super admins in the `index()` method, relying only on `?tenant_id` query param.

### Tertiary: Same bug exists in all 8 admin controllers (P1)

All controllers modified in commit `634e76fe` have the identical pattern:
- AdminBrokerApiController
- AdminCommentsApiController
- AdminFeedApiController
- AdminGroupsApiController
- AdminListingsApiController
- AdminReportsApiController
- AdminReviewsApiController
- AdminUsersApiController

---

## Fix Strategy

### Backend Fix (all controllers)

Change the super admin default from "no tenant filter" to "current tenant context":

```php
if ($isSuperAdmin) {
    // Super admins default to current tenant; use ?tenant_id=all for cross-tenant
    $effectiveTenantId = $filterTenantId ?: $tenantId;
    if ($effectiveTenantId) {
        $conditions[] = 'u.tenant_id = ?';
        $params[] = $effectiveTenantId;
    }
} else {
    $conditions[] = 'u.tenant_id = ?';
    $params[] = $tenantId;
}
```

And update `$filterTenantId` parsing to support `?tenant_id=all`:
```php
$filterTenantIdRaw = $_GET['tenant_id'] ?? null;
$filterTenantId = ($filterTenantIdRaw && $filterTenantIdRaw !== 'all')
    ? (int) $filterTenantIdRaw
    : ($filterTenantIdRaw === 'all' ? null : null);
// i.e. ?tenant_id=all → null (show all), ?tenant_id=2 → 2, absent → use $tenantId
```

### Frontend Fix (UserList.tsx)

Pass `tenant_id` as a defensive measure so the backend always has an explicit scope.

---

## Files Changed (11 modified, 1 new)

| File | Change |
|------|--------|
| `src/Controllers/Api/BaseApiController.php` | **NEW METHOD** `resolveAdminTenantFilter()` — centralized tenant filter logic |
| `src/Controllers/Api/AdminUsersApiController.php` | Use `resolveAdminTenantFilter()` in `index()` |
| `src/Controllers/Api/AdminBrokerApiController.php` | Use `resolveAdminTenantFilter()` in 7 list/stats methods |
| `src/Controllers/Api/AdminCommentsApiController.php` | Use `resolveAdminTenantFilter()` in 4 methods |
| `src/Controllers/Api/AdminFeedApiController.php` | Use `resolveAdminTenantFilter()` in 5 methods |
| `src/Controllers/Api/AdminGroupsApiController.php` | Use `resolveAdminTenantFilter()` in 9 methods |
| `src/Controllers/Api/AdminListingsApiController.php` | Use `resolveAdminTenantFilter()` in `index()` |
| `src/Controllers/Api/AdminReportsApiController.php` | Use `resolveAdminTenantFilter()` in `index()` and `stats()` |
| `src/Controllers/Api/AdminReviewsApiController.php` | Use `resolveAdminTenantFilter()` in `index()` |
| `react-frontend/src/admin/api/types.ts` | Add `tenant_id` to `UserListParams` interface |
| `react-frontend/src/admin/modules/users/UserList.tsx` | Pass `tenant_id` from tenant context in API call |
| `tests/Unit/ResolveAdminTenantFilterTest.php` | **NEW** 12 regression tests for tenant filter logic |

**Net change:** -160 lines (216 insertions, 376 deletions) — the fix actually reduces code by consolidating duplicated logic.

---

## Verification Steps (Manual)

1. **Tenant 2 Users page** — Navigate to Admin → Users while on Tenant 2. Should show ONLY Tenant 2 users.
2. **Tenant 1 Users page** — Switch to Tenant 1 → Admin → Users. Should show only Tenant 1 users.
3. **Cross-tenant opt-in** — As super admin, manually add `?tenant_id=all` to the URL. Should show ALL tenants.
4. **Specific tenant filter** — Add `?tenant_id=3` to URL. Should show only Tenant 3 users.
5. **Regular admin** — Log in as a regular tenant admin (not super admin). Should always see only own tenant.
6. **Pagination** — Navigate pages. All pages should stay tenant-scoped.
7. **Search** — Search for a user. Results should be tenant-scoped.
8. **New signup** — Verify today's new signup appears under the correct tenant only.

---

## Test Results

### PHPUnit Regression Tests — ALL PASS

```
PHPUnit 10.5.63, PHP 8.2.12
Tests: 12, Assertions: 12

✔ Regular admin always scoped to own tenant
✔ Regular admin cannot override with query param
✔ Regular admin cannot use all tenants
✔ Super admin defaults to current tenant            ← KEY REGRESSION TEST
✔ Super admin defaults to current tenant for tenant 1
✔ Super admin defaults to current tenant for tenant 4
✔ Super admin can filter to specific tenant
✔ Super admin can filter to own tenant explicitly
✔ Super admin can explicitly request all tenants
✔ Non numeric tenant id treated as no filter
✔ Empty string tenant id defaults to current tenant
✔ Zero tenant id treated as no filter
```

### PHP Syntax Check — ALL PASS

All 9 modified PHP files pass `php -l` syntax validation.

### TypeScript Compilation — PASS

`tsc --noEmit` completes with zero errors.
