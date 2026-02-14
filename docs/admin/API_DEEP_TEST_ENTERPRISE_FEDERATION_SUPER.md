# Admin API Deep Test: Enterprise, Federation, Super Admin & Other Modules

**Date:** 2026-02-14
**Auth user:** testadmin@nexus.test (user_id: 650, role: admin, tenant_id: 2)
**Base URL:** http://localhost:8090 (Docker)

---

## Summary

| Category | Total | Pass (200) | Auth Denied (403) | Server Error (500) |
|----------|-------|------------|-------------------|--------------------|
| Enterprise | 14 | 11 | 0 | 3 |
| Federation | 7 | 7 | 0 | 0 |
| Super Admin | 9 | 0 | 9 | 0 |
| Gamification | 3 | 3 | 0 | 0 |
| Groups | 4 | 1 | 0 | 3 |
| Timebanking | 4 | 2 | 0 | 2 |
| Newsletters | 5 | 5 | 0 | 0 |
| Volunteering | 3 | 3 | 0 | 0 |
| Deliverability | 3 | 3 | 0 | 0 |
| Tools | 4 | 4 | 0 | 0 |
| Listings | 1 | 0 | 0 | 1 |
| **TOTAL** | **57** | **39** | **9** | **9** |

- **39/57 endpoints return 200** with valid JSON `{data, meta}` structure
- **9 endpoints return 403** (Super Admin — requires `is_super_admin` flag; expected behavior)
- **9 endpoints return 500** (bugs — see Root Cause Analysis below)

---

## Enterprise Endpoints

| Endpoint | HTTP | Status | Notes |
|----------|------|--------|-------|
| `GET /api/v2/admin/enterprise/dashboard` | 500 | FAIL | `RedisCache::isConnected()` undefined |
| `GET /api/v2/admin/enterprise/roles` | 200 | PASS | Returns `{data, meta}` |
| `GET /api/v2/admin/enterprise/permissions` | 200 | PASS | Returns `{data, meta}` |
| `GET /api/v2/admin/enterprise/gdpr/dashboard` | 200 | PASS | Returns `{data, meta}` |
| `GET /api/v2/admin/enterprise/gdpr/requests` | 200 | PASS | Returns `{data, meta}` |
| `GET /api/v2/admin/enterprise/gdpr/consents` | 200 | PASS | Returns `{data, meta}` |
| `GET /api/v2/admin/enterprise/gdpr/breaches` | 200 | PASS | Returns `{data, meta}` |
| `GET /api/v2/admin/enterprise/gdpr/audit` | 200 | PASS | Returns `{data, meta}` |
| `GET /api/v2/admin/enterprise/monitoring` | 500 | FAIL | `RedisCache::isConnected()` undefined |
| `GET /api/v2/admin/enterprise/monitoring/health` | 500 | FAIL | `RedisCache::isConnected()` undefined |
| `GET /api/v2/admin/enterprise/monitoring/logs` | 200 | PASS | Returns `{data, meta}` |
| `GET /api/v2/admin/enterprise/config` | 200 | PASS | Returns `{data, meta}` |
| `GET /api/v2/admin/enterprise/config/secrets` | 200 | PASS | Returns `{data, meta}` |
| `GET /api/v2/admin/legal-documents` | 200 | PASS | Returns `{data, meta}` |

---

## Federation Endpoints

| Endpoint | HTTP | Status | Notes |
|----------|------|--------|-------|
| `GET /api/v2/admin/federation/settings` | 200 | PASS | Returns `{data, meta}` |
| `GET /api/v2/admin/federation/partnerships` | 200 | PASS | Returns `{data, meta}` |
| `GET /api/v2/admin/federation/directory` | 200 | PASS | Returns `{data, meta}` |
| `GET /api/v2/admin/federation/directory/profile` | 200 | PASS | Returns `{data, meta}` |
| `GET /api/v2/admin/federation/analytics` | 200 | PASS | Returns `{data, meta}` |
| `GET /api/v2/admin/federation/api-keys` | 200 | PASS | Returns `{data, meta}` |
| `GET /api/v2/admin/federation/data` | 200 | PASS | Returns `{data, meta}` |

**Federation: 7/7 PASS** -- all endpoints fully functional.

---

## Super Admin Endpoints

| Endpoint | HTTP | Status | Notes |
|----------|------|--------|-------|
| `GET /api/v2/admin/super/dashboard` | 403 | AUTH | `AUTH_INSUFFICIENT_PERMISSIONS` |
| `GET /api/v2/admin/super/tenants` | 403 | AUTH | `AUTH_INSUFFICIENT_PERMISSIONS` |
| `GET /api/v2/admin/super/tenants/hierarchy` | 403 | AUTH | `AUTH_INSUFFICIENT_PERMISSIONS` |
| `GET /api/v2/admin/super/users` | 403 | AUTH | `AUTH_INSUFFICIENT_PERMISSIONS` |
| `GET /api/v2/admin/super/audit` | 403 | AUTH | `AUTH_INSUFFICIENT_PERMISSIONS` |
| `GET /api/v2/admin/super/federation` | 403 | AUTH | `AUTH_INSUFFICIENT_PERMISSIONS` |
| `GET /api/v2/admin/super/federation/system-controls` | 403 | AUTH | `AUTH_INSUFFICIENT_PERMISSIONS` |
| `GET /api/v2/admin/super/federation/whitelist` | 403 | AUTH | `AUTH_INSUFFICIENT_PERMISSIONS` |
| `GET /api/v2/admin/super/federation/partnerships` | 403 | AUTH | `AUTH_INSUFFICIENT_PERMISSIONS` |

**Super Admin: 9/9 correctly return 403.** The test user (`testadmin@nexus.test`) has `role=admin` but does NOT have `is_super_admin=1`, so this is **expected and correct behavior**. The auth guard is working properly.

Response format: `{"success":false,"error":"Super admin access required","code":"AUTH_INSUFFICIENT_PERMISSIONS"}`

---

## Gamification Endpoints

| Endpoint | HTTP | Status | Notes |
|----------|------|--------|-------|
| `GET /api/v2/admin/gamification/stats` | 200 | PASS | Returns `{data, meta}` |
| `GET /api/v2/admin/gamification/badges` | 200 | PASS | Returns `{data, meta}` |
| `GET /api/v2/admin/gamification/campaigns` | 200 | PASS | Returns `{data, meta}` |

**Gamification: 3/3 PASS.**

---

## Groups Endpoints

| Endpoint | HTTP | Status | Notes |
|----------|------|--------|-------|
| `GET /api/v2/admin/groups` | 500 | FAIL | Unknown column `g.user_id` |
| `GET /api/v2/admin/groups/analytics` | 500 | FAIL | Unknown column `status` |
| `GET /api/v2/admin/groups/approvals` | 200 | PASS | Returns `{data, meta}` |
| `GET /api/v2/admin/groups/moderation` | 500 | FAIL | Unknown column `g.status` |

---

## Timebanking Endpoints

| Endpoint | HTTP | Status | Notes |
|----------|------|--------|-------|
| `GET /api/v2/admin/timebanking/stats` | 500 | FAIL | Unknown column `t.recipient_id` |
| `GET /api/v2/admin/timebanking/alerts` | 200 | PASS | Returns `{data, meta}` |
| `GET /api/v2/admin/timebanking/org-wallets` | 200 | PASS | Returns `{data, meta}` |
| `GET /api/v2/admin/timebanking/user-report` | 500 | FAIL | Unknown column `recipient_id` |

---

## Newsletter Endpoints

| Endpoint | HTTP | Status | Notes |
|----------|------|--------|-------|
| `GET /api/v2/admin/newsletters` | 200 | PASS | Returns `{data, meta}` |
| `GET /api/v2/admin/newsletters/subscribers` | 200 | PASS | Returns `{data, meta}` |
| `GET /api/v2/admin/newsletters/segments` | 200 | PASS | Returns `{data, meta}` |
| `GET /api/v2/admin/newsletters/templates` | 200 | PASS | Returns `{data, meta}` |
| `GET /api/v2/admin/newsletters/analytics` | 200 | PASS | Returns `{data, meta}` |

**Newsletters: 5/5 PASS.**

---

## Volunteering Endpoints

| Endpoint | HTTP | Status | Notes |
|----------|------|--------|-------|
| `GET /api/v2/admin/volunteering` | 200 | PASS | Returns `{data, meta}` |
| `GET /api/v2/admin/volunteering/approvals` | 200 | PASS | Returns `{data, meta}` |
| `GET /api/v2/admin/volunteering/organizations` | 200 | PASS | Returns `{data, meta}` |

**Volunteering: 3/3 PASS.**

---

## Deliverability Endpoints

| Endpoint | HTTP | Status | Notes |
|----------|------|--------|-------|
| `GET /api/v2/admin/deliverability/dashboard` | 200 | PASS | Returns `{data, meta}` |
| `GET /api/v2/admin/deliverability/analytics` | 200 | PASS | Returns `{data, meta}` |
| `GET /api/v2/admin/deliverability` | 200 | PASS | Returns `{data, meta}` |

**Deliverability: 3/3 PASS.**

---

## Tools Endpoints

| Endpoint | HTTP | Status | Notes |
|----------|------|--------|-------|
| `GET /api/v2/admin/tools/redirects` | 200 | PASS | Returns `{data, meta}` |
| `GET /api/v2/admin/tools/404-errors` | 200 | PASS | Returns `{data, meta}` |
| `GET /api/v2/admin/tools/webp-stats` | 200 | PASS | Returns `{data, meta}` |
| `GET /api/v2/admin/tools/blog-backups` | 200 | PASS | Returns `{data, meta}` |

**Tools: 4/4 PASS.**

---

## Listings Admin Endpoint

| Endpoint | HTTP | Status | Notes |
|----------|------|--------|-------|
| `GET /api/v2/admin/listings` | 500 | FAIL | Unknown column `l.hours_estimated` |

---

## Root Cause Analysis (9 Failing Endpoints)

### Bug 1: `RedisCache::isConnected()` does not exist (3 endpoints)

**Affected endpoints:**
- `GET /api/v2/admin/enterprise/dashboard` (line 168)
- `GET /api/v2/admin/enterprise/monitoring` (line 634)
- `GET /api/v2/admin/enterprise/monitoring/health` (line 673)

**Controller:** `src/Controllers/Api/AdminEnterpriseApiController.php`

**Root cause:** The controller calls `\Nexus\Services\RedisCache::isConnected()` and `\Nexus\Services\RedisCache::getMemoryUsage()`, but these methods do not exist on the `RedisCache` class.

**Available RedisCache methods:**
- `get()`, `set()`, `delete()`, `has()`, `remember()`, `clearTenant()`, `getStats()`

**Fix:** Replace `RedisCache::isConnected()` with a check using `RedisCache::getStats()`:
```php
// Instead of: $redisConnected = \Nexus\Services\RedisCache::isConnected();
$stats = \Nexus\Services\RedisCache::getStats();
$redisConnected = !empty($stats['enabled']);

// Instead of: $redisMemory = \Nexus\Services\RedisCache::getMemoryUsage();
$redisMemory = $stats['memory_used'] ?? 'N/A';
```

---

### Bug 2: `groups` table uses `owner_id`, not `user_id` (1 endpoint)

**Affected endpoint:** `GET /api/v2/admin/groups`

**Controller:** `src/Controllers/Api/AdminGroupsApiController.php` line 76

**Root cause:** The JOIN query uses `LEFT JOIN users u ON g.user_id = u.id` but the `groups` table has `owner_id`, not `user_id`.

**Fix:** Change `g.user_id` to `g.owner_id`:
```sql
LEFT JOIN users u ON g.owner_id = u.id
```

---

### Bug 3: `groups` table has no `status` column (2 endpoints)

**Affected endpoints:**
- `GET /api/v2/admin/groups/analytics` (line 139: `WHERE ... AND status = 'active'`)
- `GET /api/v2/admin/groups/moderation` (line 360/376: `g.status` in SELECT and WHERE)

**Controller:** `src/Controllers/Api/AdminGroupsApiController.php`

**Root cause:** The `groups` table does not have a `status` column. Instead it has `is_active` (tinyint).

**Actual schema:** `is_active tinyint(1) NOT NULL DEFAULT 1`

**Fix:**
- Analytics: Replace `AND status = 'active'` with `AND is_active = 1`
- Moderation: Replace `g.status` references with `CASE WHEN g.is_active = 1 THEN 'active' ELSE 'inactive' END as status` and remove the `IN ('flagged', 'suspended', 'under_review')` filter (these statuses don't exist)
- Index method (line 50-53): The status filter also references a non-existent `status` column — needs updating

---

### Bug 4: `transactions` table uses `receiver_id`, not `recipient_id` (2 endpoints)

**Affected endpoints:**
- `GET /api/v2/admin/timebanking/stats` (line 58: `JOIN users u ON t.recipient_id = u.id`)
- `GET /api/v2/admin/timebanking/user-report` (lines 460-464: `recipient_id` in subquery)

**Controller:** `src/Controllers/Api/AdminTimebankingApiController.php`

**Root cause:** The `transactions` table has `receiver_id` (NOT `recipient_id`).

**Fix:** Replace all `recipient_id` with `receiver_id`:
```sql
-- stats (line 58):
JOIN users u ON t.receiver_id = u.id

-- user-report (lines 460-464):
SELECT receiver_id, SUM(amount) as total, COUNT(*) as cnt
FROM transactions
WHERE tenant_id = ? AND status = 'completed'
GROUP BY receiver_id
) earned ON earned.receiver_id = u.id
```

---

### Bug 5: `listings` table has no `hours_estimated` column (1 endpoint)

**Affected endpoint:** `GET /api/v2/admin/listings`

**Controller:** `src/Controllers/Api/AdminListingsApiController.php` line 97

**Root cause:** The SELECT includes `l.hours_estimated` but the `listings` table does not have this column. The `listings` table has a `price` column (decimal) instead.

**Fix:** Remove `l.hours_estimated` from the SELECT, or replace with `l.price`:
```sql
-- Replace l.hours_estimated with l.price
SELECT l.id, l.title, l.description, l.type, l.status, l.created_at, l.updated_at,
       l.user_id, l.category_id, l.price,
       ...
```
And update the response formatter at line 124:
```php
'price' => $row['price'] ? (float) $row['price'] : null,
```

---

## Bug Summary Table

| # | Bug | Controller | Line(s) | Column Used | Correct Column | Endpoints |
|---|-----|-----------|---------|-------------|----------------|-----------|
| 1 | Missing method | AdminEnterpriseApiController | 168, 634, 673 | `RedisCache::isConnected()` | Use `getStats()['enabled']` | 3 |
| 2 | Wrong column | AdminGroupsApiController | 76 | `g.user_id` | `g.owner_id` | 1 |
| 3 | Missing column | AdminGroupsApiController | 139, 360, 376 | `g.status` | `g.is_active` | 2 |
| 4 | Wrong column | AdminTimebankingApiController | 58, 460-464 | `recipient_id` | `receiver_id` | 2 |
| 5 | Missing column | AdminListingsApiController | 97 | `l.hours_estimated` | `l.price` (or remove) | 1 |

All 9 failures are column-name mismatches or missing method calls -- straightforward fixes.
