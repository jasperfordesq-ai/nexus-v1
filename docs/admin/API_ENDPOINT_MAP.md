# API Endpoint Map -- React Admin Migration

> **Purpose**: Complete map of all API endpoints needed for the React admin panel, with status of each (existing vs needed). This is the definitive reference for backend work required to support the React admin migration.
>
> **Generated**: 2026-02-14
>
> **Base URL**: `/api/v2/admin/` (all new endpoints follow this pattern)
>
> **Auth**: All endpoints require `Authorization: Bearer <token>` with admin role verification via `ApiAuth::authenticate()` + role check in `BaseApiController::requireAdmin()`

---

## Status Legend

| Status | Meaning |
|--------|---------|
| **EXISTING** | V2 API endpoint already exists and is wired in `routes.php` |
| **EXISTING_UNWIRED** | V2 API controller exists but route is NOT registered in `routes.php` |
| **PHP_SESSION** | Only accessible via PHP session auth (admin views), needs V2 API wrapper |
| **NEEDED** | Endpoint does not exist yet, must be created from scratch |
| **INTERNAL_API** | Exists as `/admin/api/*` (session-based), needs V2 migration |

---

## Existing V2 Admin API Endpoints

> **Note**: The `AdminConfigApiController` exists at `src/Controllers/Api/AdminConfigApiController.php` but its routes are **NOT registered** in `routes.php`. Routes must be added before these work.

| Status | Method | Path | Controller Method | Purpose |
|--------|--------|------|-------------------|---------|
| EXISTING_UNWIRED | GET | `/api/v2/admin/config` | `AdminConfigApiController@getConfig` | Get features & modules config |
| EXISTING_UNWIRED | PUT | `/api/v2/admin/config/features` | `AdminConfigApiController@updateFeature` | Toggle a feature flag |
| EXISTING_UNWIRED | PUT | `/api/v2/admin/config/modules` | `AdminConfigApiController@updateModule` | Toggle a module |
| EXISTING_UNWIRED | GET | `/api/v2/admin/cache/stats` | `AdminConfigApiController@cacheStats` | Get Redis cache statistics |
| EXISTING_UNWIRED | POST | `/api/v2/admin/cache/clear` | `AdminConfigApiController@clearCache` | Clear Redis cache |
| EXISTING_UNWIRED | GET | `/api/v2/admin/jobs` | `AdminConfigApiController@getJobs` | List background jobs |
| EXISTING_UNWIRED | POST | `/api/v2/admin/jobs/{id}/run` | `AdminConfigApiController@runJob` | Trigger a background job |

### Action Required

Add these routes to `httpdocs/routes.php`:

```php
// Admin Config API (V2)
$router->add('GET', '/api/v2/admin/config', 'Nexus\Controllers\Api\AdminConfigApiController@getConfig');
$router->add('PUT', '/api/v2/admin/config/features', 'Nexus\Controllers\Api\AdminConfigApiController@updateFeature');
$router->add('PUT', '/api/v2/admin/config/modules', 'Nexus\Controllers\Api\AdminConfigApiController@updateModule');
$router->add('GET', '/api/v2/admin/cache/stats', 'Nexus\Controllers\Api\AdminConfigApiController@cacheStats');
$router->add('POST', '/api/v2/admin/cache/clear', 'Nexus\Controllers\Api\AdminConfigApiController@clearCache');
$router->add('GET', '/api/v2/admin/jobs', 'Nexus\Controllers\Api\AdminConfigApiController@getJobs');
$router->add('POST', '/api/v2/admin/jobs/{id}/run', 'Nexus\Controllers\Api\AdminConfigApiController@runJob');
```

---

## Existing Internal Admin APIs (Session-Based)

These endpoints exist at `/admin/api/*` paths using PHP session auth. They need V2 wrappers with token auth.

| Status | Method | Current Path | Purpose | Migrate To |
|--------|--------|-------------|---------|-----------|
| INTERNAL_API | GET | `/admin/smart-matching/api/stats` | Smart matching stats | `/api/v2/admin/smart-matching/stats` |
| INTERNAL_API | GET | `/admin/match-approvals/api/stats` | Match approval stats | `/api/v2/admin/match-approvals/stats` |
| INTERNAL_API | GET | `/admin/cron-jobs/api/stats` | Cron job stats | `/api/v2/admin/cron-jobs/stats` |
| INTERNAL_API | GET | `/admin/404-errors/api/list` | 404 error list | `/api/v2/admin/404-errors` |
| INTERNAL_API | GET | `/admin/404-errors/api/top` | Top 404 errors | `/api/v2/admin/404-errors/top` |
| INTERNAL_API | GET | `/admin/404-errors/api/stats` | 404 error stats | `/api/v2/admin/404-errors/stats` |
| INTERNAL_API | GET | `/admin/api/search` | Admin command palette | `/api/v2/admin/search` |
| INTERNAL_API | GET | `/admin/api/realtime` | Real-time SSE stream | `/api/v2/admin/realtime/stream` |
| INTERNAL_API | GET | `/admin/api/realtime/poll` | Real-time polling | `/api/v2/admin/realtime/poll` |
| INTERNAL_API | GET | `/admin/api/permissions/check` | Check permission | `/api/v2/admin/permissions/check` |
| INTERNAL_API | GET | `/admin/api/permissions` | List all permissions | `/api/v2/admin/permissions` |
| INTERNAL_API | GET | `/admin/api/roles` | List all roles | `/api/v2/admin/roles` |
| INTERNAL_API | GET | `/admin/api/roles/{id}/permissions` | Role permissions | `/api/v2/admin/roles/{id}/permissions` |
| INTERNAL_API | GET | `/admin/api/users/{id}/permissions` | User permissions | `/api/v2/admin/users/{id}/permissions` |
| INTERNAL_API | GET | `/admin/api/users/{id}/roles` | User roles | `/api/v2/admin/users/{id}/roles` |
| INTERNAL_API | GET | `/admin/api/users/{id}/effective-permissions` | Effective perms | `/api/v2/admin/users/{id}/effective-permissions` |
| INTERNAL_API | POST | `/admin/api/users/{id}/roles` | Assign role | `/api/v2/admin/users/{id}/roles` |
| INTERNAL_API | DELETE | `/admin/api/users/{id}/roles/{roleId}` | Revoke role | `/api/v2/admin/users/{id}/roles/{roleId}` |
| INTERNAL_API | POST | `/admin/api/users/{id}/permissions` | Grant permission | `/api/v2/admin/users/{id}/permissions` |
| INTERNAL_API | DELETE | `/admin/api/users/{id}/permissions/{permId}` | Revoke permission | `/api/v2/admin/users/{id}/permissions/{permId}` |
| INTERNAL_API | GET | `/admin/api/audit/permissions` | Permission audit log | `/api/v2/admin/audit/permissions` |
| INTERNAL_API | GET | `/admin/api/stats/permissions` | Permission stats | `/api/v2/admin/permissions/stats` |
| INTERNAL_API | GET | `/api/admin/users/search` | User search (timebanking) | `/api/v2/admin/users/search` |
| INTERNAL_API | POST | `/admin/api/pages/{id}/blocks` | Save page blocks | `/api/v2/admin/pages/{id}/blocks` |
| INTERNAL_API | GET | `/admin/api/pages/{id}/blocks` | Get page blocks | `/api/v2/admin/pages/{id}/blocks` |
| INTERNAL_API | POST | `/admin/api/blocks/preview` | Preview block | `/api/v2/admin/blocks/preview` |
| INTERNAL_API | POST | `/admin/api/pages/{id}/settings` | Save page settings | `/api/v2/admin/pages/{id}/settings` |

---

## Endpoints Needed -- By Module

### 1. Dashboard

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/dashboard/stats` | -- | `{ users_count, listings_count, transactions_count, transaction_volume, pending_users_count }` | Replaces DB queries in `AdminController@index` |
| NEEDED | GET | `/api/v2/admin/dashboard/activity` | `?limit=20` | `{ data: [{ type, user, action, created_at }] }` | Recent activity log |
| NEEDED | GET | `/api/v2/admin/dashboard/trends` | `?months=6` | `{ data: [{ month, volume, users, listings }] }` | Monthly stats chart data |
| NEEDED | GET | `/api/v2/admin/dashboard/pending-users` | -- | `{ data: [User] }` | Users awaiting approval |
| NEEDED | GET | `/api/v2/admin/dashboard/recent-listings` | `?limit=10` | `{ data: [Listing] }` | Recent listings |
| NEEDED | GET | `/api/v2/admin/dashboard/recent-transactions` | `?limit=10` | `{ data: [Transaction] }` | Recent transactions |

---

### 2. User Management

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/users` | `?status=active&role=member&q=search&page=1&per_page=25` | `{ data: [User], meta: { total, page, per_page } }` | Filterable user list |
| NEEDED | GET | `/api/v2/admin/users/{id}` | -- | `{ data: { ...user, badges, permissions, login_history } }` | Full user detail |
| NEEDED | POST | `/api/v2/admin/users` | `{ name, email, role, ... }` | `{ data: User }` | Create user |
| NEEDED | PUT | `/api/v2/admin/users/{id}` | `{ name, email, role, ... }` | `{ data: User }` | Update user |
| NEEDED | DELETE | `/api/v2/admin/users/{id}` | -- | `{ success: true }` | Delete user |
| NEEDED | POST | `/api/v2/admin/users/{id}/approve` | -- | `{ data: User }` | Approve pending user (sends email) |
| NEEDED | POST | `/api/v2/admin/users/{id}/suspend` | `{ reason? }` | `{ data: User }` | Suspend user |
| NEEDED | POST | `/api/v2/admin/users/{id}/ban` | `{ reason? }` | `{ data: User }` | Ban user |
| NEEDED | POST | `/api/v2/admin/users/{id}/reactivate` | -- | `{ data: User }` | Reactivate user |
| NEEDED | POST | `/api/v2/admin/users/{id}/reset-2fa` | -- | `{ success: true }` | Reset 2FA |
| NEEDED | POST | `/api/v2/admin/users/{id}/badges` | `{ badge_key }` | `{ data: Badge }` | Award badge |
| NEEDED | DELETE | `/api/v2/admin/users/{id}/badges/{badgeKey}` | -- | `{ success: true }` | Remove badge |
| NEEDED | POST | `/api/v2/admin/users/badges/recheck-all` | -- | `{ checked: N, awarded: N }` | Recheck all user badges |
| NEEDED | POST | `/api/v2/admin/users/badges/bulk-award` | `{ badge_key, user_ids[] }` | `{ awarded: N }` | Bulk award badge |
| NEEDED | POST | `/api/v2/admin/users/{id}/impersonate` | -- | `{ token, redirect_url }` | Impersonate user |
| NEEDED | GET | `/api/v2/admin/users/search` | `?q=term&limit=10` | `{ data: [{ id, name, email }] }` | Quick user search (autocomplete) |

---

### 3. Content -- Blog

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/blog` | `?status=published&page=1` | `{ data: [Post], meta }` | Blog list with filters |
| NEEDED | GET | `/api/v2/admin/blog/{id}` | -- | `{ data: Post }` | Blog detail with builder content |
| NEEDED | POST | `/api/v2/admin/blog` | `{ title, slug, content, status, seo_* }` | `{ data: Post }` | Create blog post |
| NEEDED | PUT | `/api/v2/admin/blog/{id}` | `{ title, slug, content, status, seo_* }` | `{ data: Post }` | Update blog post |
| NEEDED | DELETE | `/api/v2/admin/blog/{id}` | -- | `{ success: true }` | Delete blog post |
| NEEDED | POST | `/api/v2/admin/blog/{id}/builder` | `{ blocks: [...] }` | `{ data: Post }` | Save GrapesJS builder content |
| NEEDED | GET | `/api/v2/admin/blog/{id}/builder` | -- | `{ data: { blocks } }` | Get builder content |
| NEEDED | POST | `/api/v2/admin/blog/{id}/duplicate` | -- | `{ data: Post }` | Duplicate post |

---

### 4. Content -- Pages (CMS)

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/pages` | `?status=published&page=1` | `{ data: [Page], meta }` | Page list |
| NEEDED | GET | `/api/v2/admin/pages/{id}` | -- | `{ data: Page }` | Page detail |
| NEEDED | POST | `/api/v2/admin/pages` | `{ title, slug, status }` | `{ data: Page }` | Create page |
| NEEDED | PUT | `/api/v2/admin/pages/{id}` | `{ title, slug, status }` | `{ data: Page }` | Update page |
| NEEDED | DELETE | `/api/v2/admin/pages/{id}` | -- | `{ success: true }` | Delete page |
| INTERNAL_API | GET | `/api/v2/admin/pages/{id}/blocks` | -- | `{ data: { blocks } }` | Get page blocks |
| INTERNAL_API | POST | `/api/v2/admin/pages/{id}/blocks` | `{ blocks: [...] }` | `{ success: true }` | Save page blocks |
| INTERNAL_API | POST | `/api/v2/admin/pages/{id}/settings` | `{ seo_*, schedule_* }` | `{ success: true }` | Save page settings |
| NEEDED | GET | `/api/v2/admin/pages/{id}/versions` | -- | `{ data: [Version] }` | Version history |
| NEEDED | POST | `/api/v2/admin/pages/{id}/versions/{vId}/restore` | -- | `{ data: Page }` | Restore version |
| NEEDED | POST | `/api/v2/admin/pages/{id}/duplicate` | -- | `{ data: Page }` | Duplicate page |
| NEEDED | POST | `/api/v2/admin/pages/reorder` | `{ ids: [1,2,3] }` | `{ success: true }` | Reorder pages |

---

### 5. Content -- Menus

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/menus` | -- | `{ data: [Menu] }` | Menu list |
| NEEDED | GET | `/api/v2/admin/menus/{id}` | -- | `{ data: { ...menu, items: [...] } }` | Menu with items |
| NEEDED | POST | `/api/v2/admin/menus` | `{ name, slug, location }` | `{ data: Menu }` | Create menu |
| NEEDED | PUT | `/api/v2/admin/menus/{id}` | `{ name, slug, location }` | `{ data: Menu }` | Update menu |
| NEEDED | DELETE | `/api/v2/admin/menus/{id}` | -- | `{ success: true }` | Delete menu |
| NEEDED | POST | `/api/v2/admin/menus/{id}/toggle` | -- | `{ data: { active: bool } }` | Toggle active |
| NEEDED | POST | `/api/v2/admin/menus/{id}/items` | `{ label, url, type, parent_id? }` | `{ data: MenuItem }` | Add menu item |
| NEEDED | PUT | `/api/v2/admin/menus/{id}/items/{itemId}` | `{ label, url }` | `{ data: MenuItem }` | Update item |
| NEEDED | DELETE | `/api/v2/admin/menus/{id}/items/{itemId}` | -- | `{ success: true }` | Delete item |
| NEEDED | POST | `/api/v2/admin/menus/{id}/items/reorder` | `{ items: [{ id, order, parent_id }] }` | `{ success: true }` | Reorder items |
| NEEDED | POST | `/api/v2/admin/menus/cache/clear` | -- | `{ success: true }` | Clear menu cache |

---

### 6. Categories

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/categories` | `?type=listing` | `{ data: [Category] }` | List categories |
| NEEDED | GET | `/api/v2/admin/categories/{id}` | -- | `{ data: Category }` | Category detail |
| NEEDED | POST | `/api/v2/admin/categories` | `{ name, type, icon?, color? }` | `{ data: Category }` | Create category |
| NEEDED | PUT | `/api/v2/admin/categories/{id}` | `{ name, type, icon?, color? }` | `{ data: Category }` | Update category |
| NEEDED | DELETE | `/api/v2/admin/categories/{id}` | -- | `{ success: true }` | Delete category |

---

### 7. Attributes

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/attributes` | -- | `{ data: [Attribute] }` | List attributes |
| NEEDED | POST | `/api/v2/admin/attributes` | `{ name, type, options? }` | `{ data: Attribute }` | Create attribute |
| NEEDED | PUT | `/api/v2/admin/attributes/{id}` | `{ name, type, options? }` | `{ data: Attribute }` | Update attribute |
| NEEDED | DELETE | `/api/v2/admin/attributes/{id}` | -- | `{ success: true }` | Delete attribute |

---

### 8. Listings Admin

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/listings` | `?status=pending&type=listing&page=1` | `{ data: [Listing], meta }` | Unified content directory |
| NEEDED | POST | `/api/v2/admin/listings/{id}/approve` | -- | `{ data: Listing }` | Approve listing |
| NEEDED | DELETE | `/api/v2/admin/listings/{id}` | -- | `{ success: true }` | Delete listing |

---

### 9. Gamification Admin

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/gamification/stats` | -- | `{ total_badges, total_xp, active_campaigns, ... }` | Dashboard stats |
| NEEDED | POST | `/api/v2/admin/gamification/recheck-all` | -- | `{ checked, awarded }` | Recheck all badges |
| NEEDED | POST | `/api/v2/admin/gamification/bulk-award` | `{ badge_key, user_ids[] }` | `{ awarded: N }` | Bulk award |
| NEEDED | POST | `/api/v2/admin/gamification/award-all` | `{ badge_key }` | `{ awarded: N }` | Award to all |
| NEEDED | POST | `/api/v2/admin/gamification/reset-xp` | `{ user_id }` | `{ success: true }` | Reset user XP |
| NEEDED | POST | `/api/v2/admin/gamification/clear-badges` | `{ user_id }` | `{ success: true }` | Clear user badges |
| NEEDED | GET | `/api/v2/admin/gamification/analytics` | `?period=30d` | `{ data: { badges_awarded, xp_earned, ... } }` | Analytics |
| NEEDED | GET | `/api/v2/admin/gamification/campaigns` | -- | `{ data: [Campaign] }` | Campaign list |
| NEEDED | GET | `/api/v2/admin/gamification/campaigns/{id}` | -- | `{ data: Campaign }` | Campaign detail |
| NEEDED | POST | `/api/v2/admin/gamification/campaigns` | `{ name, type, rules, ... }` | `{ data: Campaign }` | Create campaign |
| NEEDED | PUT | `/api/v2/admin/gamification/campaigns/{id}` | `{ name, type, rules, ... }` | `{ data: Campaign }` | Update campaign |
| NEEDED | DELETE | `/api/v2/admin/gamification/campaigns/{id}` | -- | `{ success: true }` | Delete campaign |
| NEEDED | POST | `/api/v2/admin/gamification/campaigns/{id}/activate` | -- | `{ data: Campaign }` | Activate |
| NEEDED | POST | `/api/v2/admin/gamification/campaigns/{id}/pause` | -- | `{ data: Campaign }` | Pause |
| NEEDED | POST | `/api/v2/admin/gamification/campaigns/{id}/run` | -- | `{ results: { ... } }` | Execute campaign |
| NEEDED | POST | `/api/v2/admin/gamification/campaigns/{id}/preview-audience` | -- | `{ count: N, sample: [User] }` | Preview audience |

**Custom Badges:**

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/custom-badges` | -- | `{ data: [Badge] }` | List custom badges |
| NEEDED | GET | `/api/v2/admin/custom-badges/{id}` | -- | `{ data: Badge }` | Badge detail with awardees |
| NEEDED | POST | `/api/v2/admin/custom-badges` | `{ name, key, icon, description, criteria }` | `{ data: Badge }` | Create badge |
| NEEDED | PUT | `/api/v2/admin/custom-badges/{id}` | `{ name, icon, description }` | `{ data: Badge }` | Update badge |
| NEEDED | DELETE | `/api/v2/admin/custom-badges/{id}` | -- | `{ success: true }` | Delete badge |
| NEEDED | POST | `/api/v2/admin/custom-badges/{id}/award` | `{ user_id }` | `{ success: true }` | Award to user |
| NEEDED | POST | `/api/v2/admin/custom-badges/{id}/revoke` | `{ user_id }` | `{ success: true }` | Revoke from user |

---

### 10. Smart Matching Admin

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| INTERNAL_API | GET | `/api/v2/admin/smart-matching/stats` | -- | `{ total_matches, avg_score, cache_size }` | Migrate from `/admin/smart-matching/api/stats` |
| NEEDED | GET | `/api/v2/admin/smart-matching/analytics` | `?period=30d` | `{ data: { matches_by_day, quality_scores } }` | Analytics |
| NEEDED | GET | `/api/v2/admin/smart-matching/config` | -- | `{ data: { weights, thresholds, ... } }` | Get config |
| NEEDED | PUT | `/api/v2/admin/smart-matching/config` | `{ weights, thresholds, ... }` | `{ data: Config }` | Update config |
| NEEDED | POST | `/api/v2/admin/smart-matching/clear-cache` | -- | `{ success: true }` | Clear match cache |
| NEEDED | POST | `/api/v2/admin/smart-matching/warmup-cache` | -- | `{ success: true, processed: N }` | Warmup cache |
| NEEDED | POST | `/api/v2/admin/smart-matching/run-geocoding` | -- | `{ success: true, geocoded: N }` | Run geocoding |

---

### 11. Match Approvals

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| INTERNAL_API | GET | `/api/v2/admin/match-approvals/stats` | -- | `{ pending, approved, rejected }` | Migrate from `/admin/match-approvals/api/stats` |
| NEEDED | GET | `/api/v2/admin/match-approvals` | `?status=pending&page=1` | `{ data: [Match], meta }` | Approval queue |
| NEEDED | GET | `/api/v2/admin/match-approvals/history` | `?page=1` | `{ data: [Match], meta }` | History |
| NEEDED | GET | `/api/v2/admin/match-approvals/{id}` | -- | `{ data: Match }` | Match detail |
| NEEDED | POST | `/api/v2/admin/match-approvals/{id}/approve` | `{ notes? }` | `{ data: Match }` | Approve |
| NEEDED | POST | `/api/v2/admin/match-approvals/{id}/reject` | `{ reason }` | `{ data: Match }` | Reject |
| NEEDED | POST | `/api/v2/admin/match-approvals/bulk-approve` | `{ ids: [...] }` | `{ approved: N }` | Bulk approve |
| NEEDED | POST | `/api/v2/admin/match-approvals/bulk-reject` | `{ ids: [...], reason }` | `{ rejected: N }` | Bulk reject |

---

### 12. Broker Controls

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/broker/stats` | -- | `{ exchanges_pending, flagged_messages, monitored_users }` | Dashboard stats |
| NEEDED | GET | `/api/v2/admin/broker/config` | -- | `{ data: Config }` | Get broker config |
| NEEDED | PUT | `/api/v2/admin/broker/config` | `{ ... }` | `{ data: Config }` | Update config |
| NEEDED | GET | `/api/v2/admin/broker/exchanges` | `?status=pending` | `{ data: [Exchange], meta }` | Exchange queue |
| NEEDED | GET | `/api/v2/admin/broker/exchanges/{id}` | -- | `{ data: Exchange }` | Exchange detail |
| NEEDED | POST | `/api/v2/admin/broker/exchanges/{id}/approve` | -- | `{ data: Exchange }` | Approve exchange |
| NEEDED | POST | `/api/v2/admin/broker/exchanges/{id}/reject` | `{ reason }` | `{ data: Exchange }` | Reject exchange |
| NEEDED | GET | `/api/v2/admin/broker/risk-tags` | -- | `{ data: [TaggedListing] }` | Risk-tagged listings |
| NEEDED | POST | `/api/v2/admin/broker/risk-tags/{listingId}` | `{ tags: [...] }` | `{ data: Tags }` | Apply risk tags |
| NEEDED | DELETE | `/api/v2/admin/broker/risk-tags/{listingId}/{tag}` | -- | `{ success: true }` | Remove tag |
| NEEDED | GET | `/api/v2/admin/broker/messages` | `?flagged=true` | `{ data: [Message], meta }` | Message review queue |
| NEEDED | POST | `/api/v2/admin/broker/messages/{id}/review` | -- | `{ data: Message }` | Mark reviewed |
| NEEDED | POST | `/api/v2/admin/broker/messages/{id}/flag` | `{ reason }` | `{ data: Message }` | Flag message |
| NEEDED | GET | `/api/v2/admin/broker/monitoring` | -- | `{ data: [MonitoredUser] }` | Monitored users |
| NEEDED | PUT | `/api/v2/admin/broker/monitoring/{userId}` | `{ level, reason }` | `{ data: MonitoredUser }` | Set monitoring |

---

### 13. Timebanking Analytics

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/timebanking/stats` | -- | `{ data: { total_hours, active_users, alerts_count } }` | Dashboard |
| NEEDED | GET | `/api/v2/admin/timebanking/alerts` | `?status=open&page=1` | `{ data: [Alert], meta }` | Abuse alerts |
| NEEDED | GET | `/api/v2/admin/timebanking/alerts/{id}` | -- | `{ data: Alert }` | Alert detail |
| NEEDED | PUT | `/api/v2/admin/timebanking/alerts/{id}` | `{ status }` | `{ data: Alert }` | Update alert status |
| NEEDED | POST | `/api/v2/admin/timebanking/run-detection` | -- | `{ alerts_generated: N }` | Run abuse detection |
| NEEDED | GET | `/api/v2/admin/timebanking/user-report/{id}` | -- | `{ data: UserReport }` | User activity report |
| NEEDED | POST | `/api/v2/admin/timebanking/adjust-balance` | `{ user_id, amount, reason }` | `{ data: { new_balance } }` | Admin balance adjust |
| NEEDED | GET | `/api/v2/admin/timebanking/org-wallets` | -- | `{ data: [OrgWallet] }` | Org wallets |
| NEEDED | POST | `/api/v2/admin/timebanking/org-wallets/{id}/initialize` | -- | `{ data: OrgWallet }` | Init wallet |
| NEEDED | POST | `/api/v2/admin/timebanking/org-wallets/initialize-all` | -- | `{ initialized: N }` | Init all |
| NEEDED | GET | `/api/v2/admin/timebanking/org-wallets/{id}/members` | -- | `{ data: [Member] }` | Org members |
| NEEDED | POST | `/api/v2/admin/timebanking/org-wallets/{id}/members` | `{ user_id, role }` | `{ data: Member }` | Add member |
| NEEDED | PUT | `/api/v2/admin/timebanking/org-wallets/{id}/members/{userId}` | `{ role }` | `{ data: Member }` | Update role |
| NEEDED | DELETE | `/api/v2/admin/timebanking/org-wallets/{id}/members/{userId}` | -- | `{ success: true }` | Remove member |
| NEEDED | POST | `/api/v2/admin/timebanking/org-wallets` | `{ name, org_id? }` | `{ data: OrgWallet }` | Create org wallet |

---

### 14. Volunteering Admin

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/volunteering/stats` | -- | `{ orgs_count, opps_count, pending_approvals }` | Overview |
| NEEDED | GET | `/api/v2/admin/volunteering/approvals` | -- | `{ data: [Org] }` | Pending org approvals |
| NEEDED | GET | `/api/v2/admin/volunteering/organizations` | -- | `{ data: [Org] }` | All organizations |
| NEEDED | POST | `/api/v2/admin/volunteering/organizations/{id}/approve` | -- | `{ data: Org }` | Approve org (sends email) |
| NEEDED | POST | `/api/v2/admin/volunteering/organizations/{id}/decline` | `{ reason? }` | `{ data: Org }` | Decline (sends email) |
| NEEDED | DELETE | `/api/v2/admin/volunteering/organizations/{id}` | -- | `{ success: true }` | Delete org |

---

### 15. Newsletters

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/newsletters` | `?status=sent&page=1` | `{ data: [Newsletter], meta }` | List newsletters |
| NEEDED | GET | `/api/v2/admin/newsletters/{id}` | -- | `{ data: Newsletter }` | Newsletter detail |
| NEEDED | POST | `/api/v2/admin/newsletters` | `{ subject, body, template_id?, segment_id? }` | `{ data: Newsletter }` | Create newsletter |
| NEEDED | PUT | `/api/v2/admin/newsletters/{id}` | `{ subject, body, ... }` | `{ data: Newsletter }` | Update newsletter |
| NEEDED | DELETE | `/api/v2/admin/newsletters/{id}` | -- | `{ success: true }` | Delete newsletter |
| NEEDED | GET | `/api/v2/admin/newsletters/{id}/preview` | -- | `{ data: { html } }` | Preview HTML |
| NEEDED | POST | `/api/v2/admin/newsletters/{id}/send` | `{ schedule_at? }` | `{ data: Newsletter }` | Send/schedule |
| NEEDED | POST | `/api/v2/admin/newsletters/{id}/send-test` | `{ email }` | `{ success: true }` | Send test |
| NEEDED | POST | `/api/v2/admin/newsletters/{id}/duplicate` | -- | `{ data: Newsletter }` | Duplicate |
| NEEDED | GET | `/api/v2/admin/newsletters/{id}/stats` | -- | `{ opens, clicks, bounces, unsubscribes }` | Stats |
| NEEDED | GET | `/api/v2/admin/newsletters/{id}/activity` | -- | `{ data: [Activity] }` | Activity log |
| NEEDED | GET | `/api/v2/admin/newsletters/analytics` | `?period=30d` | `{ data: AnalyticsData }` | Aggregate analytics |
| NEEDED | POST | `/api/v2/admin/newsletters/{id}/select-winner` | -- | `{ data: Newsletter }` | A/B test winner |
| NEEDED | POST | `/api/v2/admin/newsletters/recipient-count` | `{ segment_id?, filters? }` | `{ count: N }` | Live count |
| NEEDED | POST | `/api/v2/admin/newsletters/preview-recipients` | `{ segment_id? }` | `{ data: [{ email, name }] }` | Preview recipients |

**Subscribers:**

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/newsletters/subscribers` | `?page=1&q=search` | `{ data: [Subscriber], meta }` | List |
| NEEDED | POST | `/api/v2/admin/newsletters/subscribers` | `{ email, name }` | `{ data: Subscriber }` | Add subscriber |
| NEEDED | DELETE | `/api/v2/admin/newsletters/subscribers/{id}` | -- | `{ success: true }` | Remove |
| NEEDED | POST | `/api/v2/admin/newsletters/subscribers/sync` | -- | `{ synced: N }` | Sync members |
| NEEDED | GET | `/api/v2/admin/newsletters/subscribers/export` | -- | CSV download | Export |
| NEEDED | POST | `/api/v2/admin/newsletters/subscribers/import` | multipart file | `{ imported: N }` | Import |

**Segments:**

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/newsletters/segments` | -- | `{ data: [Segment] }` | List segments |
| NEEDED | GET | `/api/v2/admin/newsletters/segments/{id}` | -- | `{ data: Segment }` | Segment detail |
| NEEDED | POST | `/api/v2/admin/newsletters/segments` | `{ name, rules }` | `{ data: Segment }` | Create segment |
| NEEDED | PUT | `/api/v2/admin/newsletters/segments/{id}` | `{ name, rules }` | `{ data: Segment }` | Update segment |
| NEEDED | DELETE | `/api/v2/admin/newsletters/segments/{id}` | -- | `{ success: true }` | Delete segment |
| NEEDED | POST | `/api/v2/admin/newsletters/segments/{id}/preview` | -- | `{ count: N, sample: [...] }` | Preview |
| NEEDED | GET | `/api/v2/admin/newsletters/segments/suggestions` | -- | `{ data: [Suggestion] }` | Smart suggestions |

**Templates:**

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/newsletters/templates` | -- | `{ data: [Template] }` | List |
| NEEDED | GET | `/api/v2/admin/newsletters/templates/{id}` | -- | `{ data: Template }` | Detail |
| NEEDED | POST | `/api/v2/admin/newsletters/templates` | `{ name, html, category }` | `{ data: Template }` | Create |
| NEEDED | PUT | `/api/v2/admin/newsletters/templates/{id}` | `{ name, html }` | `{ data: Template }` | Update |
| NEEDED | DELETE | `/api/v2/admin/newsletters/templates/{id}` | -- | `{ success: true }` | Delete |
| NEEDED | POST | `/api/v2/admin/newsletters/templates/{id}/duplicate` | -- | `{ data: Template }` | Duplicate |
| NEEDED | GET | `/api/v2/admin/newsletters/templates/{id}/preview` | -- | `{ data: { html } }` | Preview |
| NEEDED | POST | `/api/v2/admin/newsletters/save-as-template` | `{ newsletter_id, name }` | `{ data: Template }` | Save from newsletter |

**Bounces:**

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/newsletters/bounces` | `?page=1` | `{ data: [Bounce], meta }` | Bounce list |
| NEEDED | POST | `/api/v2/admin/newsletters/bounces/{id}/unsuppress` | -- | `{ success: true }` | Unsuppress |
| NEEDED | POST | `/api/v2/admin/newsletters/bounces/{id}/suppress` | -- | `{ success: true }` | Suppress |

**Resend & Optimization:**

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | POST | `/api/v2/admin/newsletters/{id}/resend` | `{ subject? }` | `{ data: Newsletter }` | Resend to non-openers |
| NEEDED | GET | `/api/v2/admin/newsletters/{id}/resend-info` | -- | `{ non_openers: N }` | Resend info |
| NEEDED | GET | `/api/v2/admin/newsletters/send-time` | -- | `{ data: { recommendations, heatmap } }` | Optimal send time |
| NEEDED | GET | `/api/v2/admin/newsletters/{id}/client-preview` | -- | `{ data: { previews } }` | Email client previews |

**Diagnostics:**

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/newsletters/diagnostics` | -- | `{ data: DiagnosticResults }` | Run diagnostics |
| NEEDED | POST | `/api/v2/admin/newsletters/repair` | -- | `{ repaired: N }` | Repair issues |

---

### 16. Federation Admin

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/federation/stats` | -- | `{ partnerships, active_users, transactions }` | Dashboard |
| NEEDED | GET | `/api/v2/admin/federation/config` | -- | `{ data: Config }` | Settings |
| NEEDED | PUT | `/api/v2/admin/federation/config` | `{ ... }` | `{ data: Config }` | Update settings |
| NEEDED | POST | `/api/v2/admin/federation/toggle` | `{ enabled: bool }` | `{ success: true }` | Enable/disable |
| NEEDED | POST | `/api/v2/admin/federation/features/{key}` | `{ enabled: bool }` | `{ data: Feature }` | Toggle feature |

**Partnerships:**

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/federation/partnerships` | -- | `{ data: [Partnership] }` | List |
| NEEDED | POST | `/api/v2/admin/federation/partnerships` | `{ target_tenant_id }` | `{ data: Partnership }` | Request |
| NEEDED | POST | `/api/v2/admin/federation/partnerships/{id}/approve` | -- | `{ data: Partnership }` | Approve |
| NEEDED | POST | `/api/v2/admin/federation/partnerships/{id}/reject` | -- | `{ data: Partnership }` | Reject |
| NEEDED | PUT | `/api/v2/admin/federation/partnerships/{id}/permissions` | `{ permissions }` | `{ data: Partnership }` | Update permissions |
| NEEDED | DELETE | `/api/v2/admin/federation/partnerships/{id}` | -- | `{ success: true }` | Terminate |
| NEEDED | POST | `/api/v2/admin/federation/partnerships/{id}/counter-propose` | `{ terms }` | `{ data: Partnership }` | Counter-propose |

**Directory:**

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/federation/directory` | -- | `{ data: [Tenant] }` | Federation directory |
| NEEDED | GET | `/api/v2/admin/federation/directory/{id}` | -- | `{ data: Tenant }` | Tenant detail |
| NEEDED | GET | `/api/v2/admin/federation/directory/profile` | -- | `{ data: Profile }` | Own profile |
| NEEDED | PUT | `/api/v2/admin/federation/directory/profile` | `{ ... }` | `{ data: Profile }` | Update profile |

**Analytics:**

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/federation/analytics` | `?period=30d` | `{ data: Analytics }` | Analytics |
| NEEDED | GET | `/api/v2/admin/federation/analytics/export` | -- | CSV download | Export |

**API Keys:**

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/federation/api-keys` | -- | `{ data: [ApiKey] }` | List |
| NEEDED | POST | `/api/v2/admin/federation/api-keys` | `{ name, permissions }` | `{ data: ApiKey }` | Create (returns key once) |
| NEEDED | GET | `/api/v2/admin/federation/api-keys/{id}` | -- | `{ data: ApiKey }` | Detail |
| NEEDED | POST | `/api/v2/admin/federation/api-keys/{id}/suspend` | -- | `{ data: ApiKey }` | Suspend |
| NEEDED | POST | `/api/v2/admin/federation/api-keys/{id}/activate` | -- | `{ data: ApiKey }` | Activate |
| NEEDED | DELETE | `/api/v2/admin/federation/api-keys/{id}` | -- | `{ success: true }` | Revoke |
| NEEDED | POST | `/api/v2/admin/federation/api-keys/{id}/regenerate` | -- | `{ data: ApiKey }` | Regenerate |

**Data Import/Export:**

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/federation/export/users` | -- | CSV | Export users |
| NEEDED | GET | `/api/v2/admin/federation/export/partnerships` | -- | CSV | Export partnerships |
| NEEDED | GET | `/api/v2/admin/federation/export/transactions` | -- | CSV | Export transactions |
| NEEDED | GET | `/api/v2/admin/federation/export/audit` | -- | CSV | Export audit log |
| NEEDED | GET | `/api/v2/admin/federation/export/all` | -- | ZIP | Export all |
| NEEDED | POST | `/api/v2/admin/federation/import/users` | multipart file | `{ imported: N }` | Import users |
| NEEDED | GET | `/api/v2/admin/federation/import/template` | -- | CSV template | Download template |

**External Partners:**

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/federation/external-partners` | -- | `{ data: [Partner] }` | List |
| NEEDED | POST | `/api/v2/admin/federation/external-partners` | `{ name, url, ... }` | `{ data: Partner }` | Create |
| NEEDED | GET | `/api/v2/admin/federation/external-partners/{id}` | -- | `{ data: Partner }` | Detail |
| NEEDED | PUT | `/api/v2/admin/federation/external-partners/{id}` | `{ ... }` | `{ data: Partner }` | Update |
| NEEDED | POST | `/api/v2/admin/federation/external-partners/{id}/test` | -- | `{ success, latency }` | Test connection |
| NEEDED | POST | `/api/v2/admin/federation/external-partners/{id}/suspend` | -- | `{ data: Partner }` | Suspend |
| NEEDED | POST | `/api/v2/admin/federation/external-partners/{id}/activate` | -- | `{ data: Partner }` | Activate |
| NEEDED | DELETE | `/api/v2/admin/federation/external-partners/{id}` | -- | `{ success: true }` | Delete |

---

### 17. Enterprise (GDPR, Monitoring, Config)

**GDPR Requests:**

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/enterprise/gdpr/stats` | -- | `{ pending, in_progress, completed }` | Dashboard |
| NEEDED | GET | `/api/v2/admin/enterprise/gdpr/requests` | `?status=pending&page=1` | `{ data: [Request], meta }` | List |
| NEEDED | GET | `/api/v2/admin/enterprise/gdpr/requests/{id}` | -- | `{ data: Request }` | Detail |
| NEEDED | POST | `/api/v2/admin/enterprise/gdpr/requests` | `{ user_id, type, notes }` | `{ data: Request }` | Create |
| NEEDED | POST | `/api/v2/admin/enterprise/gdpr/requests/{id}/process` | -- | `{ data: Request }` | Start processing |
| NEEDED | POST | `/api/v2/admin/enterprise/gdpr/requests/{id}/complete` | -- | `{ data: Request }` | Complete |
| NEEDED | POST | `/api/v2/admin/enterprise/gdpr/requests/{id}/reject` | `{ reason }` | `{ data: Request }` | Reject |
| NEEDED | POST | `/api/v2/admin/enterprise/gdpr/requests/{id}/assign` | `{ admin_id }` | `{ data: Request }` | Assign |
| NEEDED | POST | `/api/v2/admin/enterprise/gdpr/requests/{id}/notes` | `{ note }` | `{ data: Request }` | Add note |
| NEEDED | POST | `/api/v2/admin/enterprise/gdpr/requests/{id}/export` | -- | `{ data: { download_url } }` | Generate export |
| NEEDED | POST | `/api/v2/admin/enterprise/gdpr/requests/bulk-process` | `{ ids: [...], action }` | `{ processed: N }` | Bulk process |

**GDPR Consents:**

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/enterprise/gdpr/consents` | -- | `{ data: [ConsentType] }` | List types |
| NEEDED | GET | `/api/v2/admin/enterprise/gdpr/consents/{id}` | -- | `{ data: ConsentType }` | Detail |
| NEEDED | POST | `/api/v2/admin/enterprise/gdpr/consents/types` | `{ name, description }` | `{ data: ConsentType }` | Create type |
| NEEDED | POST | `/api/v2/admin/enterprise/gdpr/consents/backfill` | `{ type_id }` | `{ backfilled: N }` | Backfill |
| NEEDED | GET | `/api/v2/admin/enterprise/gdpr/consents/export` | -- | CSV download | Export |

**GDPR Breaches:**

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/enterprise/gdpr/breaches` | -- | `{ data: [Breach] }` | List |
| NEEDED | GET | `/api/v2/admin/enterprise/gdpr/breaches/{id}` | -- | `{ data: Breach }` | Detail |
| NEEDED | POST | `/api/v2/admin/enterprise/gdpr/breaches` | `{ description, severity, ... }` | `{ data: Breach }` | Report |
| NEEDED | POST | `/api/v2/admin/enterprise/gdpr/breaches/{id}/escalate` | -- | `{ data: Breach }` | Escalate |

**GDPR Audit:**

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/enterprise/gdpr/audit` | `?page=1` | `{ data: [AuditEntry], meta }` | Audit log |
| NEEDED | GET | `/api/v2/admin/enterprise/gdpr/audit/export` | -- | CSV download | Export |
| NEEDED | POST | `/api/v2/admin/enterprise/gdpr/compliance-report` | -- | `{ data: Report }` | Generate report |

**Monitoring:**

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/enterprise/monitoring/health` | -- | `{ data: HealthCheck }` | System health |
| NEEDED | GET | `/api/v2/admin/enterprise/monitoring/requirements` | -- | `{ data: [Requirement] }` | System requirements |
| NEEDED | GET | `/api/v2/admin/enterprise/monitoring/logs` | `?level=error&page=1` | `{ data: [LogEntry], meta }` | Log viewer |
| NEEDED | GET | `/api/v2/admin/enterprise/monitoring/logs/{filename}` | -- | `{ data: LogContent }` | View log file |
| NEEDED | POST | `/api/v2/admin/enterprise/monitoring/logs/clear` | -- | `{ success: true }` | Clear logs |
| NEEDED | GET | `/api/v2/admin/enterprise/monitoring/logs/download` | -- | File download | Download logs |

**Configuration:**

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/enterprise/config` | -- | `{ data: Config }` | Full config |
| NEEDED | PUT | `/api/v2/admin/enterprise/config/{group}/{key}` | `{ value }` | `{ data: Setting }` | Update setting |
| NEEDED | GET | `/api/v2/admin/enterprise/config/export` | -- | JSON download | Export config |
| NEEDED | POST | `/api/v2/admin/enterprise/config/cache/clear` | -- | `{ success: true }` | Clear cache |
| NEEDED | GET | `/api/v2/admin/enterprise/config/validate` | -- | `{ data: ValidationResults }` | Validate config |
| NEEDED | PATCH | `/api/v2/admin/enterprise/config/features/{key}` | `{ enabled }` | `{ data: Feature }` | Toggle feature |

**Secrets:**

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/enterprise/secrets` | -- | `{ data: [{ key, created_at, last_rotated }] }` | List (no values!) |
| NEEDED | POST | `/api/v2/admin/enterprise/secrets` | `{ key, value }` | `{ success: true }` | Store secret |
| NEEDED | POST | `/api/v2/admin/enterprise/secrets/{key}/view` | -- | `{ data: { value } }` | View (audit logged) |
| NEEDED | POST | `/api/v2/admin/enterprise/secrets/{key}/rotate` | `{ value }` | `{ success: true }` | Rotate |
| NEEDED | DELETE | `/api/v2/admin/enterprise/secrets/{key}` | -- | `{ success: true }` | Delete |

**Roles & Permissions:**

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| INTERNAL_API | GET | `/api/v2/admin/roles` | -- | `{ data: [Role] }` | Migrate from `/admin/api/roles` |
| INTERNAL_API | GET | `/api/v2/admin/permissions` | -- | `{ data: [Permission] }` | Migrate from `/admin/api/permissions` |
| NEEDED | GET | `/api/v2/admin/roles/{id}` | -- | `{ data: Role }` | Role detail |
| NEEDED | POST | `/api/v2/admin/roles` | `{ name, permissions[] }` | `{ data: Role }` | Create role |
| NEEDED | PUT | `/api/v2/admin/roles/{id}` | `{ name, permissions[] }` | `{ data: Role }` | Update role |
| NEEDED | DELETE | `/api/v2/admin/roles/{id}` | -- | `{ success: true }` | Delete role |
| INTERNAL_API | GET | `/api/v2/admin/users/{id}/permissions` | -- | `{ data: [Permission] }` | Migrate |
| INTERNAL_API | GET | `/api/v2/admin/users/{id}/roles` | -- | `{ data: [Role] }` | Migrate |
| INTERNAL_API | GET | `/api/v2/admin/users/{id}/effective-permissions` | -- | `{ data: [Permission] }` | Migrate |
| INTERNAL_API | POST | `/api/v2/admin/users/{id}/roles` | `{ role_id }` | `{ success: true }` | Migrate |
| INTERNAL_API | DELETE | `/api/v2/admin/users/{id}/roles/{roleId}` | -- | `{ success: true }` | Migrate |
| INTERNAL_API | GET | `/api/v2/admin/permissions/check` | `?permission=X&user_id=Y` | `{ allowed: bool }` | Migrate |
| INTERNAL_API | GET | `/api/v2/admin/audit/permissions` | `?page=1` | `{ data: [AuditEntry] }` | Migrate |

---

### 18. Legal Documents

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/legal-documents` | -- | `{ data: [Document] }` | List |
| NEEDED | GET | `/api/v2/admin/legal-documents/{id}` | -- | `{ data: Document }` | Detail |
| NEEDED | POST | `/api/v2/admin/legal-documents` | `{ type, title }` | `{ data: Document }` | Create |
| NEEDED | PUT | `/api/v2/admin/legal-documents/{id}` | `{ title, ... }` | `{ data: Document }` | Update |
| NEEDED | GET | `/api/v2/admin/legal-documents/compliance` | -- | `{ data: ComplianceDashboard }` | Compliance stats |
| NEEDED | GET | `/api/v2/admin/legal-documents/{id}/versions` | -- | `{ data: [Version] }` | Version list |
| NEEDED | POST | `/api/v2/admin/legal-documents/{id}/versions` | `{ content, changes_summary }` | `{ data: Version }` | Create version |
| NEEDED | GET | `/api/v2/admin/legal-documents/{id}/versions/{vId}` | -- | `{ data: Version }` | Version detail |
| NEEDED | PUT | `/api/v2/admin/legal-documents/{id}/versions/{vId}` | `{ content }` | `{ data: Version }` | Update version |
| NEEDED | POST | `/api/v2/admin/legal-documents/{id}/versions/{vId}/publish` | -- | `{ data: Version }` | Publish |
| NEEDED | DELETE | `/api/v2/admin/legal-documents/{id}/versions/{vId}` | -- | `{ success: true }` | Delete version |
| NEEDED | POST | `/api/v2/admin/legal-documents/{id}/versions/{vId}/notify` | -- | `{ notified: N }` | Notify users |
| NEEDED | GET | `/api/v2/admin/legal-documents/{id}/versions/{vId}/acceptances` | -- | `{ data: [Acceptance] }` | Acceptance tracking |
| NEEDED | GET | `/api/v2/admin/legal-documents/{id}/compare` | `?v1=X&v2=Y` | `{ data: Diff }` | Version comparison |
| NEEDED | GET | `/api/v2/admin/legal-documents/{id}/export` | -- | CSV download | Export acceptances |

---

### 19. SEO

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/seo/settings` | -- | `{ data: SeoSettings }` | Global SEO settings |
| NEEDED | PUT | `/api/v2/admin/seo/settings` | `{ ... }` | `{ data: SeoSettings }` | Update settings |
| NEEDED | GET | `/api/v2/admin/seo/audit` | -- | `{ data: { score, issues } }` | SEO audit |
| NEEDED | GET | `/api/v2/admin/seo/bulk/{type}` | -- | `{ data: [{ id, title, seo_title, seo_description }] }` | Bulk edit data |
| NEEDED | PUT | `/api/v2/admin/seo/bulk` | `{ items: [{ id, seo_title, seo_description }] }` | `{ updated: N }` | Bulk save |
| NEEDED | GET | `/api/v2/admin/seo/redirects` | -- | `{ data: [Redirect] }` | Redirect list |
| NEEDED | POST | `/api/v2/admin/seo/redirects` | `{ from, to, code }` | `{ data: Redirect }` | Create redirect |
| NEEDED | DELETE | `/api/v2/admin/seo/redirects/{id}` | -- | `{ success: true }` | Delete redirect |
| NEEDED | GET | `/api/v2/admin/seo/organization` | -- | `{ data: OrgSchema }` | Organization schema |
| NEEDED | PUT | `/api/v2/admin/seo/organization` | `{ ... }` | `{ data: OrgSchema }` | Update org schema |
| NEEDED | POST | `/api/v2/admin/seo/ping-sitemaps` | -- | `{ success: true }` | Ping search engines |

---

### 20. 404 Error Tracking

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| INTERNAL_API | GET | `/api/v2/admin/404-errors` | `?page=1&resolved=false` | `{ data: [Error], meta }` | Migrate from `/admin/404-errors/api/list` |
| INTERNAL_API | GET | `/api/v2/admin/404-errors/top` | -- | `{ data: [TopError] }` | Migrate from `/admin/404-errors/api/top` |
| INTERNAL_API | GET | `/api/v2/admin/404-errors/stats` | -- | `{ total, resolved, ... }` | Migrate from `/admin/404-errors/api/stats` |
| NEEDED | POST | `/api/v2/admin/404-errors/{id}/resolve` | -- | `{ success: true }` | Mark resolved |
| NEEDED | POST | `/api/v2/admin/404-errors/{id}/unresolve` | -- | `{ success: true }` | Mark unresolved |
| NEEDED | DELETE | `/api/v2/admin/404-errors/{id}` | -- | `{ success: true }` | Delete error |
| NEEDED | POST | `/api/v2/admin/404-errors/{id}/redirect` | `{ target_url }` | `{ data: Redirect }` | Create redirect |
| NEEDED | POST | `/api/v2/admin/404-errors/bulk-redirect` | `{ ids: [...], target_url }` | `{ redirected: N }` | Bulk redirect |
| NEEDED | POST | `/api/v2/admin/404-errors/clean-old` | `{ days: 30 }` | `{ deleted: N }` | Clean old errors |

---

### 21. Plans & Pricing

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/plans` | -- | `{ data: [Plan] }` | List plans |
| NEEDED | GET | `/api/v2/admin/plans/{id}` | -- | `{ data: Plan }` | Plan detail |
| NEEDED | POST | `/api/v2/admin/plans` | `{ name, price, features }` | `{ data: Plan }` | Create plan |
| NEEDED | PUT | `/api/v2/admin/plans/{id}` | `{ name, price, features }` | `{ data: Plan }` | Update plan |
| NEEDED | DELETE | `/api/v2/admin/plans/{id}` | -- | `{ success: true }` | Delete plan |
| NEEDED | GET | `/api/v2/admin/plans/subscriptions` | -- | `{ data: [Subscription] }` | List subscriptions |
| NEEDED | POST | `/api/v2/admin/plans/assign` | `{ user_id, plan_id }` | `{ data: Subscription }` | Assign plan |
| NEEDED | GET | `/api/v2/admin/plans/comparison` | -- | `{ data: ComparisonTable }` | Plan comparison |

---

### 22. Cron Job Manager

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| INTERNAL_API | GET | `/api/v2/admin/cron-jobs/stats` | -- | `{ total, running, failed }` | Migrate from `/admin/cron-jobs/api/stats` |
| NEEDED | GET | `/api/v2/admin/cron-jobs` | -- | `{ data: [Job] }` | List jobs |
| NEEDED | POST | `/api/v2/admin/cron-jobs/{id}/run` | -- | `{ data: Job }` | Trigger job |
| NEEDED | POST | `/api/v2/admin/cron-jobs/{id}/toggle` | -- | `{ data: Job }` | Enable/disable |
| NEEDED | GET | `/api/v2/admin/cron-jobs/logs` | `?job_id=X&page=1` | `{ data: [Log], meta }` | Job logs |
| NEEDED | POST | `/api/v2/admin/cron-jobs/logs/clear` | -- | `{ deleted: N }` | Clear logs |
| NEEDED | GET | `/api/v2/admin/cron-jobs/settings` | -- | `{ data: Settings }` | Settings |
| NEEDED | PUT | `/api/v2/admin/cron-jobs/settings` | `{ ... }` | `{ data: Settings }` | Update settings |

---

### 23. AI Settings

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/ai/settings` | -- | `{ data: { provider, model, api_key_set } }` | Get config |
| NEEDED | PUT | `/api/v2/admin/ai/settings` | `{ provider, model, api_key }` | `{ data: Settings }` | Update config |
| NEEDED | POST | `/api/v2/admin/ai/test` | -- | `{ success: true, latency_ms }` | Test connection |
| NEEDED | POST | `/api/v2/admin/ai/initialize` | -- | `{ success: true }` | Initialize AI |

---

### 24. Settings

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/settings` | -- | `{ data: TenantSettings }` | Get all settings |
| NEEDED | PUT | `/api/v2/admin/settings` | `{ key: value, ... }` | `{ data: TenantSettings }` | Update settings |
| NEEDED | PUT | `/api/v2/admin/settings/tenant` | `{ name, logo, domain, ... }` | `{ data: Tenant }` | Update tenant branding |
| NEEDED | POST | `/api/v2/admin/settings/test-gmail` | -- | `{ success: true }` | Test Gmail API |

---

### 25. Groups Admin

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/groups` | `?page=1&type=hub` | `{ data: [Group], meta }` | List groups |
| NEEDED | GET | `/api/v2/admin/groups/analytics` | -- | `{ data: Analytics }` | Analytics |
| NEEDED | GET | `/api/v2/admin/groups/recommendations` | -- | `{ data: Recommendations }` | Recommendations config |
| NEEDED | GET | `/api/v2/admin/groups/settings` | -- | `{ data: Settings }` | Group system settings |
| NEEDED | PUT | `/api/v2/admin/groups/settings` | `{ ... }` | `{ data: Settings }` | Update settings |
| NEEDED | GET | `/api/v2/admin/groups/policies` | -- | `{ data: Policies }` | Group policies |
| NEEDED | PUT | `/api/v2/admin/groups/policies` | `{ ... }` | `{ data: Policies }` | Update policies |
| NEEDED | GET | `/api/v2/admin/groups/moderation` | `?page=1` | `{ data: [Flag], meta }` | Moderation queue |
| NEEDED | POST | `/api/v2/admin/groups/moderation/{flagId}` | `{ action }` | `{ data: Flag }` | Process flag |
| NEEDED | GET | `/api/v2/admin/groups/approvals` | -- | `{ data: [Approval] }` | Pending approvals |
| NEEDED | POST | `/api/v2/admin/groups/approvals/{id}` | `{ action }` | `{ data: Approval }` | Process approval |
| NEEDED | POST | `/api/v2/admin/groups/{id}/toggle-featured` | -- | `{ data: Group }` | Toggle featured |
| NEEDED | DELETE | `/api/v2/admin/groups/{id}` | -- | `{ success: true }` | Delete group |
| NEEDED | GET | `/api/v2/admin/groups/export` | -- | CSV download | Export groups |

---

### 26. Activity Log

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| NEEDED | GET | `/api/v2/admin/activity-log` | `?user_id=X&type=Y&page=1` | `{ data: [Entry], meta }` | Activity log with filters |

---

### 27. Search (Admin Command Palette)

| Status | Method | Path | Request | Response | Notes |
|--------|--------|------|---------|----------|-------|
| INTERNAL_API | GET | `/api/v2/admin/search` | `?q=term` | `{ data: { users, pages, settings } }` | Migrate from `/admin/api/search` |

---

## Summary Statistics

| Category | Count |
|----------|-------|
| **EXISTING_UNWIRED** (controller exists, route missing) | 7 |
| **INTERNAL_API** (session-based, needs V2 migration) | 27 |
| **NEEDED** (must create from scratch) | ~290 |
| **Total V2 endpoints for full admin** | ~324 |

---

## Implementation Strategy

### Phase 1: Foundation (Wire existing + Dashboard + Users)
1. Wire `AdminConfigApiController` routes in `routes.php`
2. Create `AdminDashboardApiController` -- dashboard stats, trends, activity
3. Create `AdminUsersApiController` -- full user CRUD + status actions + badges
4. Create `AdminSettingsApiController` -- tenant settings

**Estimated endpoints: ~30**

### Phase 2: Content Management
1. Create `AdminBlogApiController` -- blog CRUD + builder
2. Create `AdminPagesApiController` -- page CRUD + builder + versions
3. Create `AdminMenusApiController` -- menu CRUD + items
4. Create `AdminCategoriesApiController` -- category CRUD
5. Create `AdminListingsApiController` -- unified content directory

**Estimated endpoints: ~40**

### Phase 3: Platform Management
1. Create `AdminGamificationApiController` -- stats, badges, campaigns
2. Create `AdminTimebankingApiController` -- analytics, alerts, org wallets
3. Create `AdminSmartMatchingApiController` -- config, analytics, cache
4. Create `AdminBrokerApiController` -- exchanges, risk tags, monitoring
5. Create `AdminMatchApprovalsApiController` -- approval queue

**Estimated endpoints: ~70**

### Phase 4: Communication & Content
1. Create `AdminNewslettersApiController` -- full newsletter suite
2. Create `AdminVolunteeringApiController` -- org approvals
3. Create `AdminGroupsApiController` -- group management

**Estimated endpoints: ~80**

### Phase 5: Enterprise & Advanced
1. Create `AdminGdprApiController` -- requests, consents, breaches, audit
2. Create `AdminMonitoringApiController` -- health, logs
3. Create `AdminEnterpriseConfigApiController` -- config, secrets
4. Create `AdminRolesApiController` -- roles & permissions (migrate internal APIs)
5. Create `AdminFederationApiController` -- full federation admin suite

**Estimated endpoints: ~100**

### Phase 6: Utilities
1. Create `AdminSeoApiController` -- SEO settings, audit, redirects
2. Create `AdminError404ApiController` -- error tracking (migrate internal APIs)
3. Create `AdminCronJobsApiController` -- job management
4. Create `AdminPlansApiController` -- subscription plans
5. Create `AdminAiApiController` -- AI settings
6. Create `AdminLegalDocsApiController` -- legal document management

**Estimated endpoints: ~50**

---

## Conventions for New Endpoints

### Controller Pattern

All new admin API controllers should extend `BaseApiController` and follow this pattern:

```php
namespace Nexus\Controllers\Api;

class AdminUsersApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function index(): void
    {
        $this->requireAdmin();  // Token-based admin check
        // ... implementation
        $this->respondWithData($data);
    }
}
```

### Response Format

```json
{
    "data": { ... },
    "meta": {
        "total": 100,
        "page": 1,
        "per_page": 25,
        "last_page": 4
    }
}
```

### Error Format

```json
{
    "error": {
        "code": "VALIDATION_ERROR",
        "message": "Name is required",
        "field": "name"
    }
}
```

### Auth

All endpoints use `$this->requireAdmin()` which calls `ApiAuth::authenticate()` + checks admin role. Super admin endpoints additionally check `is_super_admin` flag.
