# Admin V2 API Endpoint Map

> **Generated:** 2026-02-14
> **Source of truth:** `httpdocs/routes.php` lines 470-717 (180 route definitions)
> **Controllers:** 18 files in `src/Controllers/Api/Admin*ApiController.php`
> **Base class:** `BaseApiController` (862 lines) in `src/Controllers/Api/BaseApiController.php`

---

## Table of Contents

1. [Auth Level Summary](#auth-level-summary)
2. [Response Envelope](#response-envelope)
3. [Complete Endpoint Table](#complete-endpoint-table)
4. [Endpoint-to-React-Page Mapping](#endpoint-to-react-page-mapping)
5. [React Pages Without Dedicated API](#react-pages-without-dedicated-api)
6. [Controller Summary](#controller-summary)

---

## Auth Level Summary

All admin endpoints use one of two authorization methods from `BaseApiController`:

| Method | Allowed Roles | Usage |
|--------|--------------|-------|
| `requireAdmin()` | `admin`, `super_admin`, `god` | **ALL 180 endpoints** |
| `requireSuperAdmin()` | `super_admin`, `god` | **0 endpoints** (not used by any admin controller) |

**Key finding:** Every admin endpoint uses `requireAdmin()`. No endpoint restricts to super-admin only. This means any user with `admin` role has full access to every admin API endpoint, including enterprise config, secrets vault, GDPR management, and legal documents.

---

## Response Envelope

All admin controllers set `$isV2Api = true`, which wraps responses in the V2 envelope:

```json
// Success
{ "data": { ... }, "meta": { ... } }

// Error
{ "error": { "message": "...", "code": 400 } }

// Paginated
{ "data": [...], "meta": { "current_page": 1, "last_page": 5, "per_page": 20, "total": 100 } }
```

**Response helpers used:**
- `respondWithData($data)` -- single object or array
- `respondWithError($message, $code)` -- error responses
- `respondWithCollection($items)` -- array of items
- `respondWithPaginatedCollection($items, $total, $page, $perPage)` -- paginated list

---

## Complete Endpoint Table

### Dashboard (3 endpoints)

**Controller:** `AdminDashboardApiController` (237 lines)

| # | Method | Path | Action | Description |
|---|--------|------|--------|-------------|
| 1 | GET | `/api/v2/admin/dashboard/stats` | `stats` | Dashboard stats (users, listings, transactions, sessions) |
| 2 | GET | `/api/v2/admin/dashboard/trends` | `trends` | Monthly trend data (volume, transactions, new users) |
| 3 | GET | `/api/v2/admin/dashboard/activity` | `activity` | Recent activity log (paginated) |

---

### Users (15 endpoints)

**Controller:** `AdminUsersApiController` (677 lines)

| # | Method | Path | Action | Description |
|---|--------|------|--------|-------------|
| 4 | GET | `/api/v2/admin/users` | `index` | List users (paginated, filterable by status/role/search) |
| 5 | POST | `/api/v2/admin/users` | `store` | Create new user |
| 6 | GET | `/api/v2/admin/users/{id}` | `show` | Get user detail with badges |
| 7 | PUT | `/api/v2/admin/users/{id}` | `update` | Update user fields |
| 8 | DELETE | `/api/v2/admin/users/{id}` | `destroy` | Delete user (prevents self/super-admin deletion) |
| 9 | POST | `/api/v2/admin/users/{id}/approve` | `approve` | Approve pending user |
| 10 | POST | `/api/v2/admin/users/{id}/suspend` | `suspend` | Suspend user (with reason) |
| 11 | POST | `/api/v2/admin/users/{id}/ban` | `ban` | Ban user (with reason) |
| 12 | POST | `/api/v2/admin/users/{id}/reactivate` | `reactivate` | Reactivate suspended/banned user |
| 13 | POST | `/api/v2/admin/users/{id}/reset-2fa` | `reset2fa` | Reset user's 2FA (with reason) |
| 14 | POST | `/api/v2/admin/users/badges/recheck-all` | `recheckAll`* | Recheck all user badges |
| 15 | POST | `/api/v2/admin/users/{id}/badges` | `addBadge` | Award badge to user |
| 16 | DELETE | `/api/v2/admin/users/{id}/badges/{badgeId}` | `removeBadge` | Remove badge from user |
| 17 | POST | `/api/v2/admin/users/{id}/impersonate` | `impersonate` | Impersonate user (returns new token) |

*Note: Route 14 maps to `AdminGamificationApiController@recheckAll`, not `AdminUsersApiController`.

---

### Listings (4 endpoints)

**Controller:** `AdminListingsApiController` (233 lines)

| # | Method | Path | Action | Description |
|---|--------|------|--------|-------------|
| 18 | GET | `/api/v2/admin/listings` | `index` | List all listings (paginated, filterable) |
| 19 | GET | `/api/v2/admin/listings/{id}` | `show` | Get listing detail |
| 20 | POST | `/api/v2/admin/listings/{id}/approve` | `approve` | Approve pending listing |
| 21 | DELETE | `/api/v2/admin/listings/{id}` | `destroy` | Delete listing |

---

### Categories (4 endpoints)

**Controller:** `AdminCategoriesApiController` (448 lines)

| # | Method | Path | Action | Description |
|---|--------|------|--------|-------------|
| 22 | GET | `/api/v2/admin/categories` | `index` | List categories (filterable by type) |
| 23 | POST | `/api/v2/admin/categories` | `store` | Create category |
| 24 | PUT | `/api/v2/admin/categories/{id}` | `update` | Update category |
| 25 | DELETE | `/api/v2/admin/categories/{id}` | `destroy` | Delete category |

---

### Attributes (4 endpoints)

**Controller:** `AdminCategoriesApiController` (same file, 448 lines)

| # | Method | Path | Action | Description |
|---|--------|------|--------|-------------|
| 26 | GET | `/api/v2/admin/attributes` | `listAttributes` | List all attributes |
| 27 | POST | `/api/v2/admin/attributes` | `storeAttribute` | Create attribute |
| 28 | PUT | `/api/v2/admin/attributes/{id}` | `updateAttribute` | Update attribute |
| 29 | DELETE | `/api/v2/admin/attributes/{id}` | `destroyAttribute` | Delete attribute |

---

### Config & Features (6 endpoints)

**Controller:** `AdminConfigApiController` (1565 lines)

| # | Method | Path | Action | Description |
|---|--------|------|--------|-------------|
| 30 | GET | `/api/v2/admin/config` | `getConfig` | Get tenant features & modules |
| 31 | PUT | `/api/v2/admin/config/features` | `updateFeature` | Toggle a feature flag |
| 32 | PUT | `/api/v2/admin/config/modules` | `updateModule` | Toggle a module |

---

### Cache (2 endpoints)

**Controller:** `AdminConfigApiController`

| # | Method | Path | Action | Description |
|---|--------|------|--------|-------------|
| 33 | GET | `/api/v2/admin/cache/stats` | `cacheStats` | Redis cache statistics |
| 34 | POST | `/api/v2/admin/cache/clear` | `clearCache` | Clear tenant or all cache |

---

### Background Jobs (2 endpoints)

**Controller:** `AdminConfigApiController`

| # | Method | Path | Action | Description |
|---|--------|------|--------|-------------|
| 35 | GET | `/api/v2/admin/jobs` | `getJobs` | List background jobs |
| 36 | POST | `/api/v2/admin/jobs/{id}/run` | `runJob` | Execute a background job |

---

### Admin Settings (2 endpoints)

**Controller:** `AdminConfigApiController`

| # | Method | Path | Action | Description |
|---|--------|------|--------|-------------|
| 37 | GET | `/api/v2/admin/settings` | `getSettings` | Get all tenant settings |
| 38 | PUT | `/api/v2/admin/settings` | `updateSettings` | Update tenant settings |

---

### AI Config (2 endpoints)

**Controller:** `AdminConfigApiController`

| # | Method | Path | Action | Description |
|---|--------|------|--------|-------------|
| 39 | GET | `/api/v2/admin/config/ai` | `getAiConfig` | Get AI/OpenAI settings |
| 40 | PUT | `/api/v2/admin/config/ai` | `updateAiConfig` | Update AI config (encrypted key) |

---

### Feed Algorithm Config (2 endpoints)

**Controller:** `AdminConfigApiController`

| # | Method | Path | Action | Description |
|---|--------|------|--------|-------------|
| 41 | GET | `/api/v2/admin/config/feed-algorithm` | `getFeedAlgorithmConfig` | Get feed algorithm weights |
| 42 | PUT | `/api/v2/admin/config/feed-algorithm` | `updateFeedAlgorithmConfig` | Update feed algorithm weights |

---

### Image Config (2 endpoints)

**Controller:** `AdminConfigApiController`

| # | Method | Path | Action | Description |
|---|--------|------|--------|-------------|
| 43 | GET | `/api/v2/admin/config/images` | `getImageConfig` | Get image processing settings |
| 44 | PUT | `/api/v2/admin/config/images` | `updateImageConfig` | Update image settings |

---

### SEO Config (2 endpoints)

**Controller:** `AdminConfigApiController`

| # | Method | Path | Action | Description |
|---|--------|------|--------|-------------|
| 45 | GET | `/api/v2/admin/config/seo` | `getSeoConfig` | Get SEO metadata settings |
| 46 | PUT | `/api/v2/admin/config/seo` | `updateSeoConfig` | Update SEO settings |

---

### Native App Config (2 endpoints)

**Controller:** `AdminConfigApiController`

| # | Method | Path | Action | Description |
|---|--------|------|--------|-------------|
| 47 | GET | `/api/v2/admin/config/native-app` | `getNativeAppConfig` | Get Capacitor/PWA settings |
| 48 | PUT | `/api/v2/admin/config/native-app` | `updateNativeAppConfig` | Update native app settings |

---

### System / Cron Jobs (2 endpoints)

**Controller:** `AdminConfigApiController`

| # | Method | Path | Action | Description |
|---|--------|------|--------|-------------|
| 49 | GET | `/api/v2/admin/system/cron-jobs` | `getCronJobs` | List cron job definitions (20 jobs) |
| 50 | POST | `/api/v2/admin/system/cron-jobs/{id}/run` | `runCronJob` | Execute a cron job |

---

### System / Activity Log (1 endpoint)

**Controller:** `AdminDashboardApiController` (reuses `activity` method)

| # | Method | Path | Action | Description |
|---|--------|------|--------|-------------|
| 51 | GET | `/api/v2/admin/system/activity-log` | `activity` | Activity log (alias of dashboard/activity) |

---

### Matching (5 endpoints)

**Controller:** `AdminMatchingApiController` (511 lines)

| # | Method | Path | Action | Description |
|---|--------|------|--------|-------------|
| 52 | GET | `/api/v2/admin/matching/config` | `getConfig` | Get smart matching config |
| 53 | PUT | `/api/v2/admin/matching/config` | `updateConfig` | Update matching weights/settings |
| 54 | POST | `/api/v2/admin/matching/cache/clear` | `clearCache` | Clear match cache |
| 55 | GET | `/api/v2/admin/matching/stats` | `getStats` | Matching statistics & distributions |

---

### Match Approvals (5 endpoints)

**Controller:** `AdminMatchingApiController`

| # | Method | Path | Action | Description |
|---|--------|------|--------|-------------|
| 56 | GET | `/api/v2/admin/matching/approvals` | `index` | List match approvals (paginated) |
| 57 | GET | `/api/v2/admin/matching/approvals/stats` | `approvalStats` | Approval rate & timing stats |
| 58 | GET | `/api/v2/admin/matching/approvals/{id}` | `show` | Get approval detail |
| 59 | POST | `/api/v2/admin/matching/approvals/{id}/approve` | `approve` | Approve a match |
| 60 | POST | `/api/v2/admin/matching/approvals/{id}/reject` | `reject` | Reject a match (with reason) |

---

### Blog (6 endpoints)

**Controller:** `AdminBlogApiController` (367 lines)

| # | Method | Path | Action | Description |
|---|--------|------|--------|-------------|
| 61 | GET | `/api/v2/admin/blog` | `index` | List blog posts (paginated) |
| 62 | POST | `/api/v2/admin/blog` | `store` | Create blog post |
| 63 | GET | `/api/v2/admin/blog/{id}` | `show` | Get blog post detail |
| 64 | PUT | `/api/v2/admin/blog/{id}` | `update` | Update blog post |
| 65 | DELETE | `/api/v2/admin/blog/{id}` | `destroy` | Delete blog post |
| 66 | POST | `/api/v2/admin/blog/{id}/toggle-status` | `toggleStatus` | Toggle draft/published |

---

### Gamification (10 endpoints)

**Controller:** `AdminGamificationApiController` (499 lines)

| # | Method | Path | Action | Description |
|---|--------|------|--------|-------------|
| 67 | GET | `/api/v2/admin/gamification/stats` | `stats` | Gamification overview stats |
| 68 | GET | `/api/v2/admin/gamification/badges` | `badges` | List badge definitions |
| 69 | POST | `/api/v2/admin/gamification/badges` | `createBadge` | Create custom badge |
| 70 | DELETE | `/api/v2/admin/gamification/badges/{id}` | `deleteBadge` | Delete custom badge |
| 71 | GET | `/api/v2/admin/gamification/campaigns` | `campaigns` | List campaigns |
| 72 | POST | `/api/v2/admin/gamification/campaigns` | `createCampaign` | Create campaign |
| 73 | PUT | `/api/v2/admin/gamification/campaigns/{id}` | `updateCampaign` | Update campaign |
| 74 | DELETE | `/api/v2/admin/gamification/campaigns/{id}` | `deleteCampaign` | Delete campaign |
| 75 | POST | `/api/v2/admin/gamification/recheck-all` | `recheckAll` | Re-evaluate all badge eligibility |
| 76 | POST | `/api/v2/admin/gamification/bulk-award` | `bulkAward` | Award badge to multiple users |

---

### Groups (7 endpoints)

**Controller:** `AdminGroupsApiController` (456 lines)

| # | Method | Path | Action | Description |
|---|--------|------|--------|-------------|
| 77 | GET | `/api/v2/admin/groups` | `index` | List groups (paginated) |
| 78 | GET | `/api/v2/admin/groups/analytics` | `analytics` | Group analytics (totals, most active) |
| 79 | GET | `/api/v2/admin/groups/approvals` | `approvals` | Pending membership approvals |
| 80 | POST | `/api/v2/admin/groups/approvals/{id}/approve` | `approveMember` | Approve membership |
| 81 | POST | `/api/v2/admin/groups/approvals/{id}/reject` | `rejectMember` | Reject membership |
| 82 | GET | `/api/v2/admin/groups/moderation` | `moderation` | Reported/flagged groups |
| 83 | DELETE | `/api/v2/admin/groups/{id}` | `deleteGroup` | Delete group |

---

### Timebanking (6 endpoints)

**Controller:** `AdminTimebankingApiController` (496 lines)

| # | Method | Path | Action | Description |
|---|--------|------|--------|-------------|
| 84 | GET | `/api/v2/admin/timebanking/stats` | `stats` | Transaction stats, top earners/spenders |
| 85 | GET | `/api/v2/admin/timebanking/alerts` | `alerts` | Fraud alerts (paginated, filterable) |
| 86 | PUT | `/api/v2/admin/timebanking/alerts/{id}` | `updateAlert` | Update alert status |
| 87 | POST | `/api/v2/admin/timebanking/adjust-balance` | `adjustBalance` | Manual balance adjustment |
| 88 | GET | `/api/v2/admin/timebanking/org-wallets` | `orgWallets` | Organization wallet list |
| 89 | GET | `/api/v2/admin/timebanking/user-report` | `userReport` | User financial report (paginated) |

---

### Enterprise (22 endpoints)

**Controller:** `AdminEnterpriseApiController` (1029 lines)

| # | Method | Path | Action | Description |
|---|--------|------|--------|-------------|
| 90 | GET | `/api/v2/admin/enterprise/dashboard` | `dashboard` | Enterprise overview stats |
| 91 | GET | `/api/v2/admin/enterprise/roles` | `roles` | List roles with user counts |
| 92 | POST | `/api/v2/admin/enterprise/roles` | `createRole` | Create custom role |
| 93 | GET | `/api/v2/admin/enterprise/roles/{id}` | `showRole` | Get role detail |
| 94 | PUT | `/api/v2/admin/enterprise/roles/{id}` | `updateRole` | Update role & permissions |
| 95 | DELETE | `/api/v2/admin/enterprise/roles/{id}` | `deleteRole` | Delete role (prevents system role deletion) |
| 96 | GET | `/api/v2/admin/enterprise/permissions` | `permissions` | List all permission categories |
| 97 | GET | `/api/v2/admin/enterprise/gdpr/dashboard` | `gdprDashboard` | GDPR overview stats |
| 98 | GET | `/api/v2/admin/enterprise/gdpr/requests` | `gdprRequests` | List GDPR requests (paginated) |
| 99 | PUT | `/api/v2/admin/enterprise/gdpr/requests/{id}` | `updateGdprRequest` | Update GDPR request status |
| 100 | GET | `/api/v2/admin/enterprise/gdpr/consents` | `gdprConsents` | List consent records |
| 101 | GET | `/api/v2/admin/enterprise/gdpr/breaches` | `gdprBreaches` | List data breaches |
| 102 | GET | `/api/v2/admin/enterprise/gdpr/audit` | `gdprAudit` | GDPR audit log |
| 103 | GET | `/api/v2/admin/enterprise/monitoring` | `monitoring` | System health overview |
| 104 | GET | `/api/v2/admin/enterprise/monitoring/health` | `healthCheck` | Detailed health check |
| 105 | GET | `/api/v2/admin/enterprise/monitoring/logs` | `logs` | Error/activity logs (paginated) |
| 106 | GET | `/api/v2/admin/enterprise/config` | `config` | System configuration |
| 107 | PUT | `/api/v2/admin/enterprise/config` | `updateConfig` | Update system config |
| 108 | GET | `/api/v2/admin/enterprise/config/secrets` | `secrets` | List secrets (masked values) |
| 109 | GET | `/api/v2/admin/legal-documents` | `legalDocs` | List legal documents |
| 110 | POST | `/api/v2/admin/legal-documents` | `createLegalDoc` | Create legal document |
| 111 | GET | `/api/v2/admin/legal-documents/{id}` | `showLegalDoc` | Get legal document |
| 112 | PUT | `/api/v2/admin/legal-documents/{id}` | `updateLegalDoc` | Update legal document |
| 113 | DELETE | `/api/v2/admin/legal-documents/{id}` | `deleteLegalDoc` | Delete legal document |

---

### Broker Controls (8 endpoints)

**Controller:** `AdminBrokerApiController` (400 lines)

| # | Method | Path | Action | Description |
|---|--------|------|--------|-------------|
| 114 | GET | `/api/v2/admin/broker/dashboard` | `dashboard` | Broker overview (pending, unreviewed, risks) |
| 115 | GET | `/api/v2/admin/broker/exchanges` | `exchanges` | List exchange requests (paginated) |
| 116 | POST | `/api/v2/admin/broker/exchanges/{id}/approve` | `approveExchange` | Approve exchange |
| 117 | POST | `/api/v2/admin/broker/exchanges/{id}/reject` | `rejectExchange` | Reject exchange (with reason) |
| 118 | GET | `/api/v2/admin/broker/risk-tags` | `riskTags` | List risk-tagged listings |
| 119 | GET | `/api/v2/admin/broker/messages` | `messages` | List broker-relevant messages |
| 120 | POST | `/api/v2/admin/broker/messages/{id}/review` | `reviewMessage` | Mark message as reviewed |
| 121 | GET | `/api/v2/admin/broker/monitoring` | `monitoring` | List monitored users |

---

### Newsletters (9 endpoints)

**Controller:** `AdminNewsletterApiController` (292 lines)

| # | Method | Path | Action | Description |
|---|--------|------|--------|-------------|
| 122 | GET | `/api/v2/admin/newsletters` | `index` | List newsletters (paginated) |
| 123 | POST | `/api/v2/admin/newsletters` | `store` | Create newsletter |
| 124 | GET | `/api/v2/admin/newsletters/subscribers` | `subscribers` | List subscribers |
| 125 | GET | `/api/v2/admin/newsletters/segments` | `segments` | List segments |
| 126 | GET | `/api/v2/admin/newsletters/templates` | `templates` | List templates |
| 127 | GET | `/api/v2/admin/newsletters/analytics` | `analytics` | Newsletter analytics |
| 128 | GET | `/api/v2/admin/newsletters/{id}` | `show` | Get newsletter detail |
| 129 | PUT | `/api/v2/admin/newsletters/{id}` | `update` | Update newsletter |
| 130 | DELETE | `/api/v2/admin/newsletters/{id}` | `destroy` | Delete newsletter |

---

### Volunteering (3 endpoints)

**Controller:** `AdminVolunteeringApiController` (154 lines)

| # | Method | Path | Action | Description |
|---|--------|------|--------|-------------|
| 131 | GET | `/api/v2/admin/volunteering` | `index` | Overview stats + recent opportunities |
| 132 | GET | `/api/v2/admin/volunteering/approvals` | `approvals` | Pending volunteer applications |
| 133 | GET | `/api/v2/admin/volunteering/organizations` | `organizations` | Volunteer organizations |

---

### Federation (8 endpoints)

**Controller:** `AdminFederationApiController` (253 lines)

| # | Method | Path | Action | Description |
|---|--------|------|--------|-------------|
| 134 | GET | `/api/v2/admin/federation/settings` | `settings` | Federation configuration |
| 135 | GET | `/api/v2/admin/federation/partnerships` | `partnerships` | List partner communities |
| 136 | GET | `/api/v2/admin/federation/directory` | `directory` | Federation directory |
| 137 | GET | `/api/v2/admin/federation/directory/profile` | `profile` | Own community profile |
| 138 | GET | `/api/v2/admin/federation/analytics` | `analytics` | Federation analytics |
| 139 | GET | `/api/v2/admin/federation/api-keys` | `apiKeys` | List API keys |
| 140 | POST | `/api/v2/admin/federation/api-keys` | `createApiKey` | Generate new API key |
| 141 | GET | `/api/v2/admin/federation/data` | `dataManagement` | Data management overview |

---

### Content - Pages (5 endpoints)

**Controller:** `AdminContentApiController` (1298 lines)

| # | Method | Path | Action | Description |
|---|--------|------|--------|-------------|
| 142 | GET | `/api/v2/admin/pages` | `getPages` | List CMS pages |
| 143 | POST | `/api/v2/admin/pages` | `createPage` | Create page (auto-slug) |
| 144 | GET | `/api/v2/admin/pages/{id}` | `getPage` | Get page detail |
| 145 | PUT | `/api/v2/admin/pages/{id}` | `updatePage` | Update page |
| 146 | DELETE | `/api/v2/admin/pages/{id}` | `deletePage` | Delete page |

---

### Content - Menus (5 endpoints)

**Controller:** `AdminContentApiController`

| # | Method | Path | Action | Description |
|---|--------|------|--------|-------------|
| 147 | GET | `/api/v2/admin/menus` | `getMenus` | List menus with item counts |
| 148 | POST | `/api/v2/admin/menus` | `createMenu` | Create menu (auto-slug) |
| 149 | GET | `/api/v2/admin/menus/{id}` | `getMenu` | Get menu with nested items |
| 150 | PUT | `/api/v2/admin/menus/{id}` | `updateMenu` | Update menu |
| 151 | DELETE | `/api/v2/admin/menus/{id}` | `deleteMenu` | Delete menu and all items |

---

### Content - Menu Items (5 endpoints)

**Controller:** `AdminContentApiController`

| # | Method | Path | Action | Description |
|---|--------|------|--------|-------------|
| 152 | GET | `/api/v2/admin/menus/{id}/items` | `getMenuItems` | List items for a menu |
| 153 | POST | `/api/v2/admin/menus/{id}/items` | `createMenuItem` | Add item to menu |
| 154 | POST | `/api/v2/admin/menus/{id}/items/reorder` | `reorderMenuItems` | Reorder + re-parent items |
| 155 | PUT | `/api/v2/admin/menu-items/{id}` | `updateMenuItem` | Update a menu item |
| 156 | DELETE | `/api/v2/admin/menu-items/{id}` | `deleteMenuItem` | Delete a menu item |

---

### Content - Plans (6 endpoints)

**Controller:** `AdminContentApiController`

| # | Method | Path | Action | Description |
|---|--------|------|--------|-------------|
| 157 | GET | `/api/v2/admin/plans` | `getPlans` | List subscription plans |
| 158 | POST | `/api/v2/admin/plans` | `createPlan` | Create plan (auto-slug) |
| 159 | GET | `/api/v2/admin/plans/{id}` | `getPlan` | Get plan detail |
| 160 | PUT | `/api/v2/admin/plans/{id}` | `updatePlan` | Update plan |
| 161 | DELETE | `/api/v2/admin/plans/{id}` | `deletePlan` | Delete plan |
| 162 | GET | `/api/v2/admin/subscriptions` | `getSubscriptions` | List active subscriptions |

---

### Tools - SEO Redirects (3 endpoints)

**Controller:** `AdminToolsApiController` (550 lines)

| # | Method | Path | Action | Description |
|---|--------|------|--------|-------------|
| 163 | GET | `/api/v2/admin/tools/redirects` | `getRedirects` | List SEO redirects |
| 164 | POST | `/api/v2/admin/tools/redirects` | `createRedirect` | Create redirect rule |
| 165 | DELETE | `/api/v2/admin/tools/redirects/{id}` | `deleteRedirect` | Delete redirect |

---

### Tools - 404 Errors (2 endpoints)

**Controller:** `AdminToolsApiController`

| # | Method | Path | Action | Description |
|---|--------|------|--------|-------------|
| 166 | GET | `/api/v2/admin/tools/404-errors` | `get404Errors` | List tracked 404 errors |
| 167 | DELETE | `/api/v2/admin/tools/404-errors/{id}` | `delete404Error` | Delete 404 entry |

---

### Tools - Health & Utilities (5 endpoints)

**Controller:** `AdminToolsApiController`

| # | Method | Path | Action | Description |
|---|--------|------|--------|-------------|
| 168 | POST | `/api/v2/admin/tools/health-check` | `runHealthCheck` | Run system health checks |
| 169 | GET | `/api/v2/admin/tools/webp-stats` | `getWebpStats` | WebP conversion statistics |
| 170 | POST | `/api/v2/admin/tools/webp-convert` | `runWebpConversion` | Convert images to WebP |
| 171 | POST | `/api/v2/admin/tools/seed` | `runSeedGenerator` | Generate seed data |
| 172 | GET | `/api/v2/admin/tools/blog-backups` | `getBlogBackups` | List blog backup files |

---

### Deliverability (8 endpoints)

**Controller:** `AdminDeliverabilityApiController` (935 lines)

| # | Method | Path | Action | Description |
|---|--------|------|--------|-------------|
| 173 | GET | `/api/v2/admin/deliverability/dashboard` | `getDashboard` | Deliverability overview with stats |
| 174 | GET | `/api/v2/admin/deliverability/analytics` | `getAnalytics` | Analytics: priority/status/assignee breakdowns |
| 175 | GET | `/api/v2/admin/deliverability` | `getDeliverables` | List deliverables (paginated, filterable) |
| 176 | POST | `/api/v2/admin/deliverability` | `createDeliverable` | Create deliverable with history |
| 177 | GET | `/api/v2/admin/deliverability/{id}` | `getDeliverable` | Get deliverable with comments & history |
| 178 | PUT | `/api/v2/admin/deliverability/{id}` | `updateDeliverable` | Update deliverable (tracks changes) |
| 179 | DELETE | `/api/v2/admin/deliverability/{id}` | `deleteDeliverable` | Delete deliverable |
| 180 | POST | `/api/v2/admin/deliverability/{id}/comments` | `addComment` | Add comment to deliverable |

---

## Endpoint-to-React-Page Mapping

Maps each React admin page to the API endpoints it consumes. All paths below are prefixed with `/api/v2/admin`.

| React Route | Component | API Endpoints Used |
|-------------|-----------|-------------------|
| `/admin` (index) | `AdminDashboard` | `GET /dashboard/stats`, `GET /dashboard/trends`, `GET /dashboard/activity` |
| `/admin/users` | `UserList` | `GET /users` |
| `/admin/users/create` | `UserCreate` | `POST /users` |
| `/admin/users/:id/edit` | `UserEdit` | `GET /users/{id}`, `PUT /users/{id}`, `POST .../approve`, `POST .../suspend`, `POST .../ban`, `POST .../reactivate`, `POST .../reset-2fa`, `POST .../badges`, `DELETE .../badges/{badgeId}`, `POST .../impersonate` |
| `/admin/users/:id/permissions` | `PermissionBrowser` | `GET /enterprise/permissions` |
| `/admin/listings` | `ListingsAdmin` | `GET /listings`, `POST /listings/{id}/approve`, `DELETE /listings/{id}` |
| `/admin/blog` | `BlogAdmin` | `GET /blog`, `DELETE /blog/{id}`, `POST /blog/{id}/toggle-status` |
| `/admin/blog/create` | `BlogPostForm` | `POST /blog` |
| `/admin/blog/edit/:id` | `BlogPostForm` | `GET /blog/{id}`, `PUT /blog/{id}` |
| `/admin/pages` | `PagesAdmin` | `GET /pages`, `DELETE /pages/{id}` |
| `/admin/pages/builder/:id` | `PageBuilder` | `GET /pages/{id}`, `POST /pages`, `PUT /pages/{id}` |
| `/admin/menus` | `MenusAdmin` | `GET /menus`, `POST /menus`, `DELETE /menus/{id}` |
| `/admin/menus/builder/:id` | `MenuBuilder` | `GET /menus/{id}`, `GET /menus/{id}/items`, `POST /menus/{id}/items`, `POST .../items/reorder`, `PUT /menu-items/{id}`, `DELETE /menu-items/{id}` |
| `/admin/categories` | `CategoriesAdmin` | `GET /categories`, `POST /categories`, `PUT /categories/{id}`, `DELETE /categories/{id}` |
| `/admin/attributes` | `AttributesAdmin` | `GET /attributes`, `POST /attributes`, `PUT /attributes/{id}`, `DELETE /attributes/{id}` |
| `/admin/gamification` | `GamificationHub` | `GET /gamification/stats`, `GET /gamification/badges` |
| `/admin/gamification/campaigns` | `CampaignList` | `GET /gamification/campaigns`, `DELETE /gamification/campaigns/{id}` |
| `/admin/gamification/campaigns/create` | `CampaignForm` | `POST /gamification/campaigns` |
| `/admin/gamification/campaigns/edit/:id` | `CampaignForm` | `PUT /gamification/campaigns/{id}` |
| `/admin/gamification/analytics` | `GamificationAnalytics` | `GET /gamification/stats` |
| `/admin/custom-badges` | `CustomBadges` | `GET /gamification/badges`, `DELETE /gamification/badges/{id}` |
| `/admin/custom-badges/create` | `CreateBadge` | `POST /gamification/badges` |
| `/admin/smart-matching` | `SmartMatchingOverview` | `GET /matching/stats` |
| `/admin/smart-matching/analytics` | `MatchingAnalytics` | `GET /matching/stats` |
| `/admin/smart-matching/configuration` | `MatchingConfig` | `GET /matching/config`, `PUT /matching/config`, `POST /matching/cache/clear` |
| `/admin/match-approvals` | `MatchApprovals` | `GET /matching/approvals`, `GET /matching/approvals/stats`, `POST .../approve`, `POST .../reject` |
| `/admin/match-approvals/:id` | `MatchDetail` | `GET /matching/approvals/{id}`, `POST .../approve`, `POST .../reject` |
| `/admin/broker-controls` | `BrokerDashboard` | `GET /broker/dashboard` |
| `/admin/broker-controls/exchanges` | `ExchangeManagement` | `GET /broker/exchanges`, `POST .../approve`, `POST .../reject` |
| `/admin/broker-controls/risk-tags` | `RiskTags` | `GET /broker/risk-tags` |
| `/admin/broker-controls/messages` | `MessageReview` | `GET /broker/messages`, `POST .../review` |
| `/admin/broker-controls/monitoring` | `UserMonitoring` | `GET /broker/monitoring` |
| `/admin/newsletters` | `NewsletterList` | `GET /newsletters` |
| `/admin/newsletters/create` | `NewsletterForm` | `POST /newsletters` |
| `/admin/newsletters/edit/:id` | `NewsletterForm` | `GET /newsletters/{id}`, `PUT /newsletters/{id}` |
| `/admin/newsletters/subscribers` | `Subscribers` | `GET /newsletters/subscribers` |
| `/admin/newsletters/segments` | `Segments` | `GET /newsletters/segments` |
| `/admin/newsletters/templates` | `Templates` | `GET /newsletters/templates` |
| `/admin/newsletters/analytics` | `NewsletterAnalytics` | `GET /newsletters/analytics` |
| `/admin/ai-settings` | `AiSettings` | `GET /config/ai`, `PUT /config/ai` |
| `/admin/feed-algorithm` | `FeedAlgorithm` | `GET /config/feed-algorithm`, `PUT /config/feed-algorithm` |
| `/admin/algorithm-settings` | `AlgorithmSettings` | `GET /config/feed-algorithm` |
| `/admin/seo` | `SeoOverview` | `GET /config/seo` |
| `/admin/seo/audit` | `SeoAudit` | `GET /config/seo` |
| `/admin/seo/redirects` | `Redirects` | `GET /tools/redirects`, `POST /tools/redirects`, `DELETE /tools/redirects/{id}` |
| `/admin/404-errors` | `Error404Tracking` | `GET /tools/404-errors`, `DELETE /tools/404-errors/{id}` |
| `/admin/timebanking` | `TimebankingDashboard` | `GET /timebanking/stats` |
| `/admin/timebanking/alerts` | `FraudAlerts` | `GET /timebanking/alerts`, `PUT /timebanking/alerts/{id}` |
| `/admin/timebanking/user-report` | `UserReport` | `GET /timebanking/user-report` |
| `/admin/timebanking/org-wallets` | `OrgWallets` | `GET /timebanking/org-wallets` |
| `/admin/plans` | `PlansAdmin` | `GET /plans`, `DELETE /plans/{id}` |
| `/admin/plans/create` | `PlanForm` | `POST /plans` |
| `/admin/plans/edit/:id` | `PlanForm` | `GET /plans/{id}`, `PUT /plans/{id}` |
| `/admin/plans/subscriptions` | `Subscriptions` | `GET /subscriptions` |
| `/admin/enterprise` | `EnterpriseDashboard` | `GET /enterprise/dashboard` |
| `/admin/enterprise/roles` | `RoleList` | `GET /enterprise/roles` |
| `/admin/enterprise/roles/create` | `RoleForm` | `POST /enterprise/roles`, `GET /enterprise/permissions` |
| `/admin/enterprise/roles/:id` | `RoleForm` | `GET /enterprise/roles/{id}`, `PUT /enterprise/roles/{id}`, `GET /enterprise/permissions` |
| `/admin/enterprise/permissions` | `PermissionBrowser` | `GET /enterprise/permissions` |
| `/admin/enterprise/gdpr` | `GdprDashboard` | `GET /enterprise/gdpr/dashboard` |
| `/admin/enterprise/gdpr/requests` | `GdprRequests` | `GET /enterprise/gdpr/requests`, `PUT .../requests/{id}` |
| `/admin/enterprise/gdpr/consents` | `GdprConsents` | `GET /enterprise/gdpr/consents` |
| `/admin/enterprise/gdpr/breaches` | `GdprBreaches` | `GET /enterprise/gdpr/breaches` |
| `/admin/enterprise/gdpr/audit` | `GdprAuditLog` | `GET /enterprise/gdpr/audit` |
| `/admin/enterprise/monitoring` | `SystemMonitoring` | `GET /enterprise/monitoring` |
| `/admin/enterprise/monitoring/health` | `HealthCheck` | `GET /enterprise/monitoring/health` |
| `/admin/enterprise/monitoring/logs` | `ErrorLogs` | `GET /enterprise/monitoring/logs` |
| `/admin/enterprise/config` | `SystemConfig` | `GET /enterprise/config`, `PUT /enterprise/config` |
| `/admin/enterprise/config/secrets` | `SecretsVault` | `GET /enterprise/config/secrets` |
| `/admin/legal-documents` | `LegalDocList` | `GET /legal-documents` |
| `/admin/legal-documents/create` | `LegalDocForm` | `POST /legal-documents` |
| `/admin/legal-documents/:id` | `LegalDocForm` | `GET /legal-documents/{id}`, `PUT /legal-documents/{id}` |
| `/admin/federation` | `FederationSettings` | `GET /federation/settings` |
| `/admin/federation/partnerships` | `Partnerships` | `GET /federation/partnerships` |
| `/admin/federation/directory` | `PartnerDirectory` | `GET /federation/directory` |
| `/admin/federation/directory/profile` | `MyProfile` | `GET /federation/directory/profile` |
| `/admin/federation/analytics` | `FederationAnalytics` | `GET /federation/analytics` |
| `/admin/federation/api-keys` | `ApiKeys` | `GET /federation/api-keys` |
| `/admin/federation/api-keys/create` | `CreateApiKey` | `POST /federation/api-keys` |
| `/admin/federation/data` | `DataManagement` | `GET /federation/data` |
| `/admin/settings` | `AdminSettings` | `GET /settings`, `PUT /settings` |
| `/admin/tenant-features` | `TenantFeatures` | `GET /config`, `PUT /config/features`, `PUT /config/modules` |
| `/admin/cron-jobs` | `CronJobs` | `GET /system/cron-jobs`, `POST /system/cron-jobs/{id}/run` |
| `/admin/activity-log` | `ActivityLog` | `GET /system/activity-log` |
| `/admin/seed-generator` | `SeedGenerator` | `POST /tools/seed` |
| `/admin/webp-converter` | `WebpConverter` | `GET /tools/webp-stats`, `POST /tools/webp-convert` |
| `/admin/image-settings` | `ImageSettings` | `GET /config/images`, `PUT /config/images` |
| `/admin/native-app` | `NativeApp` | `GET /config/native-app`, `PUT /config/native-app` |
| `/admin/blog-restore` | `BlogRestore` | `GET /tools/blog-backups` |
| `/admin/groups` | `GroupList` | `GET /groups` |
| `/admin/groups/analytics` | `GroupAnalytics` | `GET /groups/analytics` |
| `/admin/groups/approvals` | `GroupApprovals` | `GET /groups/approvals`, `POST .../approve`, `POST .../reject` |
| `/admin/groups/moderation` | `GroupModeration` | `GET /groups/moderation`, `DELETE /groups/{id}` |
| `/admin/volunteering` | `VolunteeringOverview` | `GET /volunteering` |
| `/admin/volunteering/approvals` | `VolunteerApprovals` | `GET /volunteering/approvals` |
| `/admin/volunteering/organizations` | `VolunteerOrganizations` | `GET /volunteering/organizations` |
| `/admin/deliverability` | `DeliverabilityDashboard` | `GET /deliverability/dashboard` |
| `/admin/deliverability/list` | `DeliverablesList` | `GET /deliverability`, `DELETE /deliverability/{id}` |
| `/admin/deliverability/create` | `CreateDeliverable` | `POST /deliverability` |
| `/admin/deliverability/analytics` | `DeliverabilityAnalytics` | `GET /deliverability/analytics` |
| `/admin/matching-diagnostic` | `MatchingDiagnostic` | `GET /matching/stats` (with user_id/listing_id params) |
| `/admin/nexus-score/analytics` | `NexusScoreAnalytics` | `GET /gamification/stats` |
| `/admin/smart-match-users` | `SmartMatchUsers` | `GET /matching/stats` |
| `/admin/smart-match-monitoring` | `SmartMatchMonitoring` | `GET /matching/stats` |
| `/admin/tests` | `TestRunner` | No dedicated API (client-side only) |

---

## React Pages Without Dedicated API

These React admin pages exist but either reuse other endpoints or have no backend API:

| React Route | Component | Notes |
|-------------|-----------|-------|
| `/admin/tests` | `TestRunner` | Client-side test runner, no backend endpoint |
| `/admin/group-types` | `GroupList` (reused) | Shares route with `/admin/groups` |
| `/admin/group-ranking` | `GroupList` (reused) | Shares route with `/admin/groups` |
| `/admin/group-locations` | `GroupList` (reused) | Shares route with `/admin/groups` |
| `/admin/geocode-groups` | `GroupList` (reused) | Shares route with `/admin/groups` |
| `/admin/smart-match-users` | `SmartMatchUsers` | Reuses `GET /matching/stats` |
| `/admin/smart-match-monitoring` | `SmartMatchMonitoring` | Reuses `GET /matching/stats` |
| `/admin/matching-diagnostic` | `MatchingDiagnostic` | Reuses `GET /matching/stats` with params |
| `/admin/nexus-score/analytics` | `NexusScoreAnalytics` | Reuses `GET /gamification/stats` |
| `/admin/algorithm-settings` | `AlgorithmSettings` | Reuses `GET /config/feed-algorithm` |
| `/admin/seo/audit` | `SeoAudit` | Reuses `GET /config/seo` |
| `/admin/timebanking/create-org` | `OrgWallets` (reused) | Shares route with `/admin/timebanking/org-wallets` |

---

## Controller Summary

| Controller | File | Lines | Endpoints | Key Dependencies |
|-----------|------|-------|-----------|------------------|
| `AdminDashboardApiController` | `src/Controllers/Api/AdminDashboardApiController.php` | 237 | 3 | Direct DB queries |
| `AdminUsersApiController` | `src/Controllers/Api/AdminUsersApiController.php` | 677 | 14 | `UserService`, `GamificationService`, `TokenService` |
| `AdminListingsApiController` | `src/Controllers/Api/AdminListingsApiController.php` | 233 | 4 | Direct DB queries |
| `AdminCategoriesApiController` | `src/Controllers/Api/AdminCategoriesApiController.php` | 448 | 8 | Direct DB queries |
| `AdminConfigApiController` | `src/Controllers/Api/AdminConfigApiController.php` | 1565 | 20 | `RedisCache`, `AiSettings`, `TenantContext` |
| `AdminMatchingApiController` | `src/Controllers/Api/AdminMatchingApiController.php` | 511 | 10 | `SmartMatchingEngine`, `MatchApprovalWorkflowService`, `SmartMatchingAnalyticsService` |
| `AdminBlogApiController` | `src/Controllers/Api/AdminBlogApiController.php` | 367 | 6 | Direct DB queries |
| `AdminGamificationApiController` | `src/Controllers/Api/AdminGamificationApiController.php` | 499 | 10 | `GamificationService`, `AchievementCampaignService` |
| `AdminGroupsApiController` | `src/Controllers/Api/AdminGroupsApiController.php` | 456 | 7 | `GroupApprovalWorkflowService` |
| `AdminTimebankingApiController` | `src/Controllers/Api/AdminTimebankingApiController.php` | 496 | 6 | `AbuseDetectionService`, `WalletService`, `AuditLogService` |
| `AdminEnterpriseApiController` | `src/Controllers/Api/AdminEnterpriseApiController.php` | 1029 | 22 | Direct DB queries, Redis |
| `AdminBrokerApiController` | `src/Controllers/Api/AdminBrokerApiController.php` | 400 | 8 | Direct DB queries |
| `AdminNewsletterApiController` | `src/Controllers/Api/AdminNewsletterApiController.php` | 292 | 9 | `tableExists()` helper |
| `AdminVolunteeringApiController` | `src/Controllers/Api/AdminVolunteeringApiController.php` | 154 | 3 | `tableExists()` helper |
| `AdminFederationApiController` | `src/Controllers/Api/AdminFederationApiController.php` | 253 | 8 | `FederationGateway` |
| `AdminContentApiController` | `src/Controllers/Api/AdminContentApiController.php` | 1298 | 21 | Direct DB queries, slug helpers |
| `AdminToolsApiController` | `src/Controllers/Api/AdminToolsApiController.php` | 550 | 10 | Direct DB queries, file system |
| `AdminDeliverabilityApiController` | `src/Controllers/Api/AdminDeliverabilityApiController.php` | 935 | 8 | Direct DB queries, history tracking |
| **TOTAL** | | **9,950** | **180** | |

---

## Notes

1. **All endpoints are tenant-scoped** via `TenantContext::getId()` -- no cross-tenant data leakage.
2. **Graceful degradation:** `AdminVolunteeringApiController` and `AdminNewsletterApiController` use `tableExists()` to handle missing tables gracefully.
3. **Route alias:** `/api/v2/admin/system/activity-log` is an alias for `/api/v2/admin/dashboard/activity` (same controller method).
4. **Cross-controller routing:** `POST /api/v2/admin/users/badges/recheck-all` routes to `AdminGamificationApiController@recheckAll`, not `AdminUsersApiController`.
5. **Config controller is largest:** `AdminConfigApiController` at 1565 lines handles 20 endpoints spanning features, modules, cache, jobs, settings, AI, feed algorithm, images, SEO, native app, and cron jobs.
6. **No rate limiting** on admin endpoints -- all rely solely on `requireAdmin()` auth check.
7. **Enterprise controller defines 13 permission categories** as a PHP constant but does not enforce per-endpoint permissions -- all admin endpoints are accessible to any admin user regardless of their specific permissions.
