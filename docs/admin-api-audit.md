# Project NEXUS - Admin API Comprehensive Audit

**Date:** 2026-02-18
**Auditor:** Claude Sonnet 4.5 (Agent Team)
**Scope:** All `/api/v2/admin/*` endpoints

---

## Executive Summary

The Project NEXUS admin API is a comprehensive REST API with **301 endpoints** across **24 controllers**, providing full administrative control over all platform features. The API is well-organized, follows RESTful conventions, and includes extensive functionality for multi-tenant management, content moderation, analytics, and enterprise features.

### Key Metrics

- **Total Endpoints:** 301
- **Total Controllers:** 24
- **HTTP Methods:** GET (139), POST (95), PUT (41), DELETE (26)
- **Average Endpoints per Controller:** 12.5
- **Largest Controller:** AdminSuperApiController (36 endpoints)
- **Authentication:** Required on all endpoints (admin role or higher)
- **API Version:** V2 (modern React admin panel)

---

## API Architecture

### Design Patterns

1. **RESTful Resource-Based Routing**
   - Standard CRUD operations: `GET /admin/users`, `POST /admin/users`, `PUT /admin/users/{id}`, `DELETE /admin/users/{id}`
   - Action-based sub-routes: `POST /admin/users/{id}/approve`, `POST /admin/users/{id}/suspend`
   - Nested resources: `GET /admin/groups/{groupId}/members`, `POST /admin/menus/{id}/items`

2. **Consistent Response Formats**
   - All endpoints use `BaseApiController` methods:
     - `respondWithData()` — single resource or simple data
     - `respondWithPaginatedCollection()` — paginated lists
     - `respondWithError()` — error responses with API error codes
   - Standard envelope: `{ "data": {...}, "meta": {...} }`

3. **Tenant Scoping**
   - All endpoints enforce tenant isolation via `TenantContext::getId()`
   - Super admin endpoints support cross-tenant operations
   - Database queries always include `tenant_id = ?` checks

4. **Authentication & Authorization**
   - All endpoints call `$this->requireAdmin()` (from `BaseApiController`)
   - Super admin-only endpoints check `is_super_admin` flag
   - Prevents self-modification (can't delete/suspend own account)
   - Prevents super admin modification by non-super admins

5. **Audit Logging**
   - Most mutating operations log to `ActivityLog` and `AuditLogService`
   - Tracks who performed the action, what changed, and when
   - Critical actions (grant super admin, impersonate, delete users) always logged

### Controller Organization

Controllers are organized by functional domain:

| Domain | Controllers | Endpoints | Description |
|--------|-------------|-----------|-------------|
| **User Management** | AdminUsersApiController | 21 | Full user lifecycle, moderation, impersonation |
| **Super Admin** | AdminSuperApiController | 36 | Cross-tenant ops, hierarchy, federation controls |
| **Groups** | AdminGroupsApiController | 26 | Group types, policies, moderation, geocoding |
| **Enterprise** | AdminEnterpriseApiController | 25 | GDPR, roles, permissions, legal docs |
| **Content** | AdminContentApiController | 21 | Pages, menus, plans, subscriptions |
| **Configuration** | AdminConfigApiController | 21 | Features, modules, AI, SEO, images, cron jobs |
| **Newsletters** | AdminNewsletterApiController | 17 | Campaigns, analytics, segments, suppressions |
| **Broker Tools** | AdminBrokerApiController | 15 | Exchange monitoring, risk tagging, message flagging |
| **Federation** | AdminFederationApiController | 13 | Partnerships, directory, API keys, analytics |
| **System Tools** | AdminToolsApiController | 13 | 404 errors, redirects, SEO audit, health checks |
| **Gamification** | AdminGamificationApiController | 11 | Badges, campaigns, bulk awards, stats |
| **Matching** | AdminMatchingApiController | 9 | Broker approval workflow, config, cache |
| **Legal Docs** | AdminLegalDocController | 9 | Version management, publishing, compliance |
| **Vetting** | AdminVettingApiController | 9 | DBS checks, verification records |
| **Categories** | AdminCategoriesApiController | 8 | Category & attribute management |
| **Cron Jobs** | AdminCronApiController | 8 | Job settings, health metrics, logs |
| **Deliverability** | AdminDeliverabilityApiController | 8 | Deliverable tracking, analytics |
| **Timebanking** | AdminTimebankingApiController | 7 | Wallets, balances, alerts, reports |
| **Blog** | AdminBlogApiController | 6 | Post management, status toggles |
| **Volunteering** | AdminVolunteeringApiController | 5 | Opportunities, applications, approvals |
| **Dashboard** | AdminDashboardApiController | 4 | Stats, trends, activity feed |
| **Listings** | AdminListingsApiController | 4 | Listing moderation, approval |
| **Community Analytics** | AdminCommunityAnalyticsApiController | 3 | Reports, geography, export |
| **Impact Reporting** | AdminImpactReportApiController | 2 | Impact metrics, config |

---

## Complete Endpoint Catalog

### 1. Dashboard & Analytics (4 endpoints)

**AdminDashboardApiController**

| Method | Endpoint | Action | Purpose |
|--------|----------|--------|---------|
| GET | `/api/v2/admin/dashboard/stats` | stats | Platform-wide metrics (users, listings, transactions) |
| GET | `/api/v2/admin/dashboard/trends` | trends | Time-series trends for key metrics |
| GET | `/api/v2/admin/dashboard/activity` | activity | Recent admin activity log |
| GET | `/api/v2/admin/system/activity-log` | activity | Full activity log (paginated) |

**Features:**
- Tenant-scoped statistics
- Date range filtering
- Cached for performance
- Real-time updates via Pusher

---

### 2. User Management (21 endpoints)

**AdminUsersApiController**

| Method | Endpoint | Action | Purpose |
|--------|----------|--------|---------|
| GET | `/api/v2/admin/users` | index | List users (paginated, filterable by status/role/search) |
| GET | `/api/v2/admin/users/{id}` | show | Get single user detail with badges |
| GET | `/api/v2/admin/users/import/template` | importTemplate | Download CSV import template |
| GET | `/api/v2/admin/users/{id}/consents` | getConsents | Get GDPR consents for user |
| POST | `/api/v2/admin/users` | store | Create new user (auto-generate password if not provided) |
| POST | `/api/v2/admin/users/import` | import | Bulk import users from CSV |
| POST | `/api/v2/admin/users/{id}/approve` | approve | Approve pending user |
| POST | `/api/v2/admin/users/{id}/suspend` | suspend | Suspend user (with reason) |
| POST | `/api/v2/admin/users/{id}/ban` | ban | Ban user (with reason) |
| POST | `/api/v2/admin/users/{id}/reactivate` | reactivate | Reactivate suspended/banned user |
| POST | `/api/v2/admin/users/{id}/reset-2fa` | reset2fa | Reset user's TOTP 2FA secret |
| POST | `/api/v2/admin/users/{id}/badges` | addBadge | Award badge to user |
| POST | `/api/v2/admin/users/{id}/badges/recheck` | recheckBadges | Recheck all badge criteria for user |
| POST | `/api/v2/admin/users/{id}/impersonate` | impersonate | Generate impersonation token (short-lived JWT) |
| POST | `/api/v2/admin/users/{id}/password` | setPassword | Admin set user password |
| POST | `/api/v2/admin/users/{id}/send-password-reset` | sendPasswordReset | Send password reset email |
| POST | `/api/v2/admin/users/{id}/send-welcome-email` | sendWelcomeEmail | Resend welcome email |
| PUT | `/api/v2/admin/users/{id}` | update | Update user profile (first_name, last_name, email, role, etc.) |
| PUT | `/api/v2/admin/users/{id}/super-admin` | setSuperAdmin | Grant/revoke super admin status (super admin only) |
| DELETE | `/api/v2/admin/users/{id}` | destroy | Delete user (prevents self-deletion, super admin deletion) |
| DELETE | `/api/v2/admin/users/{id}/badges/{badgeId}` | removeBadge | Remove badge from user |

**Features:**
- Advanced filtering (status, role, search, sort)
- Pagination (20 per page default, max 100)
- CSV bulk import with validation
- Comprehensive moderation actions
- Impersonation for support/debugging
- Audit logging on all mutations
- GDPR consent tracking
- 2FA management

**Query Parameters (index):**
- `page` — page number (default 1)
- `limit` — items per page (default 20, max 100)
- `status` — all | pending | active | suspended | banned
- `search` — search by name or email
- `role` — filter by role (member, admin, tenant_admin, etc.)
- `sort` — name | email | role | created_at | balance | status
- `order` — ASC | DESC (default DESC)

**Security:**
- Prevents self-deletion
- Prevents self-suspension/ban
- Prevents super admin modification by non-super admins
- Prevents impersonating super admins
- Prevents self-impersonation
- Prevents self super-admin status modification

---

### 3. Content Management (21 endpoints)

**AdminContentApiController**

| Method | Endpoint | Action | Purpose |
|--------|----------|--------|---------|
| GET | `/api/v2/admin/pages` | getPages | List CMS pages |
| GET | `/api/v2/admin/pages/{id}` | getPage | Get single page |
| GET | `/api/v2/admin/menus` | getMenus | List navigation menus |
| GET | `/api/v2/admin/menus/{id}` | getMenu | Get single menu |
| GET | `/api/v2/admin/menus/{id}/items` | getMenuItems | Get menu items |
| GET | `/api/v2/admin/plans` | getPlans | List subscription plans |
| GET | `/api/v2/admin/plans/{id}` | getPlan | Get single plan |
| GET | `/api/v2/admin/subscriptions` | getSubscriptions | List active subscriptions |
| POST | `/api/v2/admin/pages` | createPage | Create CMS page |
| POST | `/api/v2/admin/menus` | createMenu | Create navigation menu |
| POST | `/api/v2/admin/menus/{id}/items` | createMenuItem | Add item to menu |
| POST | `/api/v2/admin/menus/{id}/items/reorder` | reorderMenuItems | Reorder menu items (drag-drop) |
| POST | `/api/v2/admin/plans` | createPlan | Create subscription plan |
| PUT | `/api/v2/admin/pages/{id}` | updatePage | Update CMS page |
| PUT | `/api/v2/admin/menus/{id}` | updateMenu | Update menu |
| PUT | `/api/v2/admin/menu-items/{id}` | updateMenuItem | Update menu item |
| PUT | `/api/v2/admin/plans/{id}` | updatePlan | Update subscription plan |
| DELETE | `/api/v2/admin/pages/{id}` | deletePage | Delete CMS page |
| DELETE | `/api/v2/admin/menus/{id}` | deleteMenu | Delete menu |
| DELETE | `/api/v2/admin/menu-items/{id}` | deleteMenuItem | Delete menu item |
| DELETE | `/api/v2/admin/plans/{id}` | deletePlan | Delete subscription plan |

**Features:**
- Full CMS page management (title, slug, content, SEO)
- Nested menu management with drag-drop reordering
- Subscription plan management (pricing, features, limits)
- Subscription tracking and analytics

---

### 4. Gamification (11 endpoints)

**AdminGamificationApiController**

| Method | Endpoint | Action | Purpose |
|--------|----------|--------|---------|
| GET | `/api/v2/admin/gamification/stats` | stats | Gamification overview stats |
| GET | `/api/v2/admin/gamification/badges` | badges | List all badges with earned counts |
| GET | `/api/v2/admin/gamification/campaigns` | campaigns | List XP campaigns |
| POST | `/api/v2/admin/gamification/badges` | createBadge | Create custom badge |
| POST | `/api/v2/admin/gamification/campaigns` | createCampaign | Create XP campaign (double XP week, etc.) |
| POST | `/api/v2/admin/gamification/bulk-award` | bulkAward | Award badge to multiple users at once |
| POST | `/api/v2/admin/gamification/recheck-all` | recheckAll | Recheck badge criteria for ALL users (async job) |
| POST | `/api/v2/admin/users/badges/recheck-all` | recheckAll | Recheck badge criteria for ALL users |
| PUT | `/api/v2/admin/gamification/campaigns/{id}` | updateCampaign | Update XP campaign |
| DELETE | `/api/v2/admin/gamification/badges/{id}` | deleteBadge | Delete custom badge |
| DELETE | `/api/v2/admin/gamification/campaigns/{id}` | deleteCampaign | Delete campaign |

**Features:**
- Badge management (create custom badges with icons, criteria)
- XP campaigns (temporary XP multipliers)
- Bulk badge awards (CSV upload or user selection)
- Recheck all users for badge eligibility (background job)

---

### 5. Groups Management (26 endpoints)

**AdminGroupsApiController**

| Method | Endpoint | Action | Purpose |
|--------|----------|--------|---------|
| GET | `/api/v2/admin/groups` | index | List all groups (paginated, filterable) |
| GET | `/api/v2/admin/groups/{id}` | getGroup | Get single group detail |
| GET | `/api/v2/admin/groups/{groupId}/members` | getMembers | List group members |
| GET | `/api/v2/admin/groups/featured` | getFeaturedGroups | List featured groups |
| GET | `/api/v2/admin/groups/approvals` | approvals | List pending membership approvals |
| GET | `/api/v2/admin/groups/moderation` | moderation | List flagged groups |
| GET | `/api/v2/admin/groups/analytics` | analytics | Group analytics (growth, engagement) |
| GET | `/api/v2/admin/groups/recommendations` | getRecommendationData | Data for group recommendation engine |
| GET | `/api/v2/admin/groups/types` | getGroupTypes | List group types |
| GET | `/api/v2/admin/groups/types/{id}/policies` | getPolicies | Get approval policies for group type |
| POST | `/api/v2/admin/groups/types` | createGroupType | Create group type (e.g., "Community", "Interest") |
| POST | `/api/v2/admin/groups/approvals/{id}/approve` | approveMember | Approve membership request |
| POST | `/api/v2/admin/groups/approvals/{id}/reject` | rejectMember | Reject membership request |
| POST | `/api/v2/admin/groups/featured/update` | updateFeaturedGroups | Update featured groups list |
| POST | `/api/v2/admin/groups/{groupId}/members/{userId}/promote` | promoteMember | Promote member to moderator/admin |
| POST | `/api/v2/admin/groups/{groupId}/members/{userId}/demote` | demoteMember | Demote member from moderator/admin |
| POST | `/api/v2/admin/groups/{id}/geocode` | geocodeGroup | Geocode group location |
| POST | `/api/v2/admin/groups/batch-geocode` | batchGeocode | Geocode all groups missing coordinates |
| PUT | `/api/v2/admin/groups/{id}` | updateGroup | Update group details |
| PUT | `/api/v2/admin/groups/{id}/status` | updateStatus | Change group status (active/suspended) |
| PUT | `/api/v2/admin/groups/{id}/toggle-featured` | toggleFeatured | Toggle featured status |
| PUT | `/api/v2/admin/groups/types/{id}` | updateGroupType | Update group type |
| PUT | `/api/v2/admin/groups/types/{id}/policies` | setPolicy | Set approval policy (auto/manual/broker) |
| DELETE | `/api/v2/admin/groups/{id}` | deleteGroup | Delete group |
| DELETE | `/api/v2/admin/groups/{groupId}/members/{userId}` | kickMember | Remove member from group |
| DELETE | `/api/v2/admin/groups/types/{id}` | deleteGroupType | Delete group type |

**Features:**
- Group type management (types, policies, auto-approval rules)
- Membership moderation (approve/reject requests)
- Member management (promote/demote/kick)
- Featured groups curation
- Geocoding support (for map display)
- Analytics (growth trends, engagement metrics)
- Moderation queue for flagged groups

---

### 6. Listings Management (4 endpoints)

**AdminListingsApiController**

| Method | Endpoint | Action | Purpose |
|--------|----------|--------|---------|
| GET | `/api/v2/admin/listings` | index | List all listings (paginated, filterable by status/type) |
| GET | `/api/v2/admin/listings/{id}` | show | Get single listing detail |
| POST | `/api/v2/admin/listings/{id}/approve` | approve | Approve pending listing |
| DELETE | `/api/v2/admin/listings/{id}` | destroy | Delete listing |

**Features:**
- Listing moderation (approve/reject offers and requests)
- Filter by status (pending, active, expired)
- Filter by type (offer, request)
- Pagination

**Missing:**
- Bulk operations (bulk approve, bulk delete)
- Listing editing (admins can only approve/delete, not edit)
- Category reassignment
- Expiry date override

---

### 7. Matching System (9 endpoints)

**AdminMatchingApiController**

| Method | Endpoint | Action | Purpose |
|--------|----------|--------|---------|
| GET | `/api/v2/admin/matching/stats` | getStats | Matching algorithm performance stats |
| GET | `/api/v2/admin/matching/config` | getConfig | Get broker approval config |
| GET | `/api/v2/admin/matching/approvals` | index | List pending match approvals |
| GET | `/api/v2/admin/matching/approvals/stats` | approvalStats | Approval workflow stats |
| GET | `/api/v2/admin/matching/approvals/{id}` | show | Get single match approval detail |
| POST | `/api/v2/admin/matching/approvals/{id}/approve` | approve | Approve match (creates exchange) |
| POST | `/api/v2/admin/matching/approvals/{id}/reject` | reject | Reject match (with reason) |
| POST | `/api/v2/admin/matching/cache/clear` | clearCache | Clear match cache (force re-matching) |
| PUT | `/api/v2/admin/matching/config` | updateConfig | Update broker approval settings |

**Features:**
- Broker approval workflow (human-in-the-loop matching)
- Match quality analytics
- Cache management
- Configurable approval thresholds

---

### 8. Federation (13 endpoints)

**AdminFederationApiController**

| Method | Endpoint | Action | Purpose |
|--------|----------|--------|---------|
| GET | `/api/v2/admin/federation/settings` | settings | Get federation settings |
| GET | `/api/v2/admin/federation/partnerships` | partnerships | List federation partnerships |
| GET | `/api/v2/admin/federation/directory` | directory | Get federation directory listing |
| GET | `/api/v2/admin/federation/directory/profile` | profile | Get this tenant's public profile |
| GET | `/api/v2/admin/federation/api-keys` | apiKeys | List federation API keys |
| GET | `/api/v2/admin/federation/analytics` | analytics | Federation usage analytics |
| GET | `/api/v2/admin/federation/data` | dataManagement | Data sharing settings |
| POST | `/api/v2/admin/federation/api-keys` | createApiKey | Generate federation API key |
| POST | `/api/v2/admin/federation/partnerships/{id}/approve` | approvePartnership | Approve partnership request |
| POST | `/api/v2/admin/federation/partnerships/{id}/reject` | rejectPartnership | Reject partnership request |
| POST | `/api/v2/admin/federation/partnerships/{id}/terminate` | terminatePartnership | Terminate existing partnership |
| PUT | `/api/v2/admin/federation/settings` | updateSettings | Update federation settings (opt-in/out) |
| PUT | `/api/v2/admin/federation/directory/profile` | updateProfile | Update public directory profile |

**Features:**
- Federation opt-in/opt-out
- Partnership management (approve/reject/terminate)
- API key management
- Directory profile customization
- Cross-community analytics

---

### 9. Volunteering (5 endpoints)

**AdminVolunteeringApiController**

| Method | Endpoint | Action | Purpose |
|--------|----------|--------|---------|
| GET | `/api/v2/admin/volunteering` | index | List volunteer opportunities |
| GET | `/api/v2/admin/volunteering/organizations` | organizations | List volunteer organizations |
| GET | `/api/v2/admin/volunteering/approvals` | approvals | List pending applications |
| POST | `/api/v2/admin/volunteering/approvals/{id}/approve` | approveApplication | Approve volunteer application |
| POST | `/api/v2/admin/volunteering/approvals/{id}/decline` | declineApplication | Decline volunteer application |

**Features:**
- Volunteer opportunity listing
- Organization management
- Application approval workflow

**Missing:**
- Opportunity creation/editing (admins can only view)
- Hours tracking oversight
- Volunteer reports/analytics

---

### 10. Newsletter (17 endpoints)

**AdminNewsletterApiController**

| Method | Endpoint | Action | Purpose |
|--------|----------|--------|---------|
| GET | `/api/v2/admin/newsletters` | index | List newsletters (campaigns) |
| GET | `/api/v2/admin/newsletters/{id}` | show | Get single newsletter detail |
| GET | `/api/v2/admin/newsletters/analytics` | analytics | Newsletter performance analytics |
| GET | `/api/v2/admin/newsletters/subscribers` | subscribers | List all subscribers |
| GET | `/api/v2/admin/newsletters/segments` | segments | List subscriber segments |
| GET | `/api/v2/admin/newsletters/templates` | templates | List email templates |
| GET | `/api/v2/admin/newsletters/bounces` | getBounces | List bounced emails |
| GET | `/api/v2/admin/newsletters/suppression-list` | getSuppressionList | List suppressed emails |
| GET | `/api/v2/admin/newsletters/diagnostics` | getDiagnostics | Email deliverability diagnostics |
| GET | `/api/v2/admin/newsletters/send-time-optimizer` | getSendTimeData | Optimal send time data |
| GET | `/api/v2/admin/newsletters/{id}/resend-info` | getResendInfo | Get resend eligibility info |
| POST | `/api/v2/admin/newsletters` | store | Create newsletter campaign |
| POST | `/api/v2/admin/newsletters/{id}/resend` | resend | Resend to non-openers |
| POST | `/api/v2/admin/newsletters/suppression-list/{email}/suppress` | suppress | Add email to suppression list |
| POST | `/api/v2/admin/newsletters/suppression-list/{email}/unsuppress` | unsuppress | Remove from suppression list |
| PUT | `/api/v2/admin/newsletters/{id}` | update | Update newsletter campaign |
| DELETE | `/api/v2/admin/newsletters/{id}` | destroy | Delete newsletter campaign |

**Features:**
- Campaign management (create, send, track)
- Subscriber segmentation
- Email templates
- Bounce management
- Suppression list (unsubscribes, hard bounces)
- Send time optimization (ML-based)
- Resend campaigns (to non-openers)
- Deliverability diagnostics

---

### 11. Broker Tools (15 endpoints)

**AdminBrokerApiController**

| Method | Endpoint | Action | Purpose |
|--------|----------|--------|---------|
| GET | `/api/v2/admin/broker/dashboard` | dashboard | Broker dashboard overview |
| GET | `/api/v2/admin/broker/configuration` | getConfiguration | Get broker config |
| GET | `/api/v2/admin/broker/exchanges` | exchanges | List exchanges requiring broker attention |
| GET | `/api/v2/admin/broker/exchanges/{id}` | showExchange | Get exchange detail |
| GET | `/api/v2/admin/broker/messages` | messages | List messages flagged for review |
| GET | `/api/v2/admin/broker/monitoring` | monitoring | List users under monitoring |
| GET | `/api/v2/admin/broker/risk-tags` | riskTags | List all risk tags |
| POST | `/api/v2/admin/broker/configuration` | saveConfiguration | Update broker config |
| POST | `/api/v2/admin/broker/exchanges/{id}/approve` | approveExchange | Approve pending exchange |
| POST | `/api/v2/admin/broker/exchanges/{id}/reject` | rejectExchange | Reject exchange (with reason) |
| POST | `/api/v2/admin/broker/messages/{id}/flag` | flagMessage | Flag message for review |
| POST | `/api/v2/admin/broker/messages/{id}/review` | reviewMessage | Mark message as reviewed |
| POST | `/api/v2/admin/broker/monitoring/{userId}` | setMonitoring | Add/remove user from monitoring |
| POST | `/api/v2/admin/broker/risk-tags/{listingId}` | saveRiskTag | Add risk tag to listing |
| DELETE | `/api/v2/admin/broker/risk-tags/{listingId}` | removeRiskTag | Remove risk tag from listing |

**Features:**
- Exchange approval workflow (broker mediates all exchanges)
- Message monitoring (flag inappropriate messages)
- User monitoring (track high-risk users)
- Risk tagging (flag risky listings)
- Configurable broker rules

---

### 12. Timebanking (7 endpoints)

**AdminTimebankingApiController**

| Method | Endpoint | Action | Purpose |
|--------|----------|--------|---------|
| GET | `/api/v2/admin/timebanking/stats` | stats | Timebanking stats (total credits, velocity) |
| GET | `/api/v2/admin/timebanking/org-wallets` | orgWallets | List organization wallets |
| GET | `/api/v2/admin/timebanking/alerts` | alerts | List balance alerts (low balance, negative) |
| GET | `/api/v2/admin/timebanking/user-report` | userReport | Get user transaction report (CSV export) |
| GET | `/api/v2/admin/timebanking/user-statement` | userStatement | Get user wallet statement (PDF) |
| POST | `/api/v2/admin/timebanking/adjust-balance` | adjustBalance | Manually adjust user balance (with reason) |
| PUT | `/api/v2/admin/timebanking/alerts/{id}` | updateAlert | Update alert settings |

**Features:**
- Wallet management (view balances, transaction history)
- Manual balance adjustments (corrections, grants)
- Organization wallets (sponsor accounts)
- Alert management (low balance warnings)
- Transaction reports and statements

---

### 13. Vetting (9 endpoints)

**AdminVettingApiController**

| Method | Endpoint | Action | Purpose |
|--------|----------|--------|---------|
| GET | `/api/v2/admin/vetting` | list | List all vetting records |
| GET | `/api/v2/admin/vetting/stats` | stats | Vetting stats (pending, verified, rejected) |
| GET | `/api/v2/admin/vetting/{id}` | show | Get single vetting record |
| GET | `/api/v2/admin/vetting/user/{userId}` | getUserRecords | Get all vetting records for user |
| POST | `/api/v2/admin/vetting` | store | Create vetting record (DBS check, reference) |
| POST | `/api/v2/admin/vetting/{id}/verify` | verify | Mark vetting as verified |
| POST | `/api/v2/admin/vetting/{id}/reject` | reject | Reject vetting (with reason) |
| PUT | `/api/v2/admin/vetting/{id}` | update | Update vetting record |
| DELETE | `/api/v2/admin/vetting/{id}` | destroy | Delete vetting record |

**Features:**
- DBS/background check tracking
- Reference verification
- Vetting status management (pending, verified, rejected, expired)
- User vetting history

---

### 14. Configuration & Settings (21 endpoints)

**AdminConfigApiController**

| Method | Endpoint | Action | Purpose |
|--------|----------|--------|---------|
| GET | `/api/v2/admin/settings` | getSettings | Get tenant settings (site name, email, timezone, etc.) |
| GET | `/api/v2/admin/config` | getConfig | Get full tenant configuration (features, modules, etc.) |
| GET | `/api/v2/admin/config/ai` | getAiConfig | Get AI/LLM configuration |
| GET | `/api/v2/admin/config/seo` | getSeoConfig | Get SEO settings (meta tags, analytics) |
| GET | `/api/v2/admin/config/images` | getImageConfig | Get image processing settings |
| GET | `/api/v2/admin/config/native-app` | getNativeAppConfig | Get mobile app config |
| GET | `/api/v2/admin/config/feed-algorithm` | getFeedAlgorithmConfig | Get feed algorithm weights |
| GET | `/api/v2/admin/jobs` | getJobs | List background jobs |
| GET | `/api/v2/admin/system/cron-jobs` | getCronJobs | List cron jobs with next run times |
| GET | `/api/v2/admin/cache/stats` | cacheStats | Redis cache statistics |
| PUT | `/api/v2/admin/settings` | updateSettings | Update tenant settings |
| PUT | `/api/v2/admin/config/features` | updateFeature | Toggle feature flag (events, groups, etc.) |
| PUT | `/api/v2/admin/config/modules` | updateModule | Toggle module (listings, wallet, etc.) |
| PUT | `/api/v2/admin/config/ai` | updateAiConfig | Update AI settings (model, API keys) |
| PUT | `/api/v2/admin/config/seo` | updateSeoConfig | Update SEO settings |
| PUT | `/api/v2/admin/config/images` | updateImageConfig | Update image settings (quality, max size) |
| PUT | `/api/v2/admin/config/native-app` | updateNativeAppConfig | Update mobile app config |
| PUT | `/api/v2/admin/config/feed-algorithm` | updateFeedAlgorithmConfig | Update feed algorithm |
| POST | `/api/v2/admin/jobs/{id}/run` | runJob | Manually trigger background job |
| POST | `/api/v2/admin/system/cron-jobs/{id}/run` | runCronJob | Manually trigger cron job |
| POST | `/api/v2/admin/cache/clear` | clearCache | Clear Redis cache |

**Features:**
- Tenant settings management
- Feature flag toggles
- Module enable/disable
- AI/LLM configuration
- SEO optimization
- Image processing settings
- Mobile app config
- Feed algorithm tuning
- Background job management
- Cache management

---

### 15. Categories (8 endpoints)

**AdminCategoriesApiController**

| Method | Endpoint | Action | Purpose |
|--------|----------|--------|---------|
| GET | `/api/v2/admin/categories` | index | List all categories |
| GET | `/api/v2/admin/attributes` | listAttributes | List all attributes |
| POST | `/api/v2/admin/categories` | store | Create category |
| POST | `/api/v2/admin/attributes` | storeAttribute | Create attribute |
| PUT | `/api/v2/admin/categories/{id}` | update | Update category |
| PUT | `/api/v2/admin/attributes/{id}` | updateAttribute | Update attribute |
| DELETE | `/api/v2/admin/categories/{id}` | destroy | Delete category |
| DELETE | `/api/v2/admin/attributes/{id}` | destroyAttribute | Delete attribute |

**Features:**
- Category management (for listings, posts, etc.)
- Attribute management (custom fields)

---

### 16. Blog Management (6 endpoints)

**AdminBlogApiController**

| Method | Endpoint | Action | Purpose |
|--------|----------|--------|---------|
| GET | `/api/v2/admin/blog` | index | List blog posts |
| GET | `/api/v2/admin/blog/{id}` | show | Get single post |
| POST | `/api/v2/admin/blog` | store | Create blog post |
| POST | `/api/v2/admin/blog/{id}/toggle-status` | toggleStatus | Publish/unpublish post |
| PUT | `/api/v2/admin/blog/{id}` | update | Update blog post |
| DELETE | `/api/v2/admin/blog/{id}` | destroy | Delete blog post |

**Features:**
- Blog post CRUD
- Publish/unpublish toggle
- Rich text content

---

### 17. Community Analytics (3 endpoints)

**AdminCommunityAnalyticsApiController**

| Method | Endpoint | Action | Purpose |
|--------|----------|--------|---------|
| GET | `/api/v2/admin/community-analytics` | index | Get community analytics data |
| GET | `/api/v2/admin/community-analytics/geography` | geography | Get geographic distribution |
| GET | `/api/v2/admin/community-analytics/export` | export | Export analytics as CSV/PDF |

**Features:**
- Community health metrics
- User engagement trends
- Geographic distribution
- Export to CSV/PDF

---

### 18. Impact Reporting (2 endpoints)

**AdminImpactReportApiController**

| Method | Endpoint | Action | Purpose |
|--------|----------|--------|---------|
| GET | `/api/v2/admin/impact-report` | index | Get impact report data |
| PUT | `/api/v2/admin/impact-report/config` | updateConfig | Update impact report config |

**Features:**
- Impact metrics (hours exchanged, connections made, CO2 saved)
- Custom impact calculations
- Configurable impact categories

---

### 19. System Tools (13 endpoints)

**AdminToolsApiController**

| Method | Endpoint | Action | Purpose |
|--------|----------|--------|---------|
| GET | `/api/v2/admin/tools/404-errors` | get404Errors | List 404 errors (potential broken links) |
| GET | `/api/v2/admin/tools/redirects` | getRedirects | List URL redirects |
| GET | `/api/v2/admin/tools/seo-audit` | getSeoAudit | Get SEO audit results |
| GET | `/api/v2/admin/tools/webp-stats` | getWebpStats | Get WebP conversion stats |
| GET | `/api/v2/admin/tools/blog-backups` | getBlogBackups | List blog backups |
| POST | `/api/v2/admin/tools/redirects` | createRedirect | Create URL redirect (301/302) |
| POST | `/api/v2/admin/tools/seo-audit` | runSeoAudit | Run SEO audit |
| POST | `/api/v2/admin/tools/webp-convert` | runWebpConversion | Convert images to WebP |
| POST | `/api/v2/admin/tools/health-check` | runHealthCheck | Run system health check |
| POST | `/api/v2/admin/tools/seed` | runSeedGenerator | Generate seed data (for testing) |
| POST | `/api/v2/admin/tools/blog-backups/{id}/restore` | restoreBlogBackup | Restore blog from backup |
| DELETE | `/api/v2/admin/tools/404-errors/{id}` | delete404Error | Delete 404 error record |
| DELETE | `/api/v2/admin/tools/redirects/{id}` | deleteRedirect | Delete redirect |

**Features:**
- 404 error tracking
- URL redirect management
- SEO audit (on-page SEO, meta tags, broken links)
- WebP image conversion (for faster page loads)
- System health checks (database, Redis, file permissions)
- Seed data generation (for development/testing)
- Blog backup/restore

---

### 20. Deliverability (8 endpoints)

**AdminDeliverabilityApiController**

| Method | Endpoint | Action | Purpose |
|--------|----------|--------|---------|
| GET | `/api/v2/admin/deliverability` | getDeliverables | List deliverables |
| GET | `/api/v2/admin/deliverability/{id}` | getDeliverable | Get single deliverable |
| GET | `/api/v2/admin/deliverability/dashboard` | getDashboard | Deliverability dashboard |
| GET | `/api/v2/admin/deliverability/analytics` | getAnalytics | Deliverability analytics |
| POST | `/api/v2/admin/deliverability` | createDeliverable | Create deliverable |
| POST | `/api/v2/admin/deliverability/{id}/comments` | addComment | Add comment to deliverable |
| PUT | `/api/v2/admin/deliverability/{id}` | updateDeliverable | Update deliverable |
| DELETE | `/api/v2/admin/deliverability/{id}` | deleteDeliverable | Delete deliverable |

**Features:**
- Deliverable tracking (project milestones, tasks)
- Comment threads
- Status updates
- Analytics

---

### 21. Enterprise Features (25 endpoints)

**AdminEnterpriseApiController**

| Method | Endpoint | Action | Purpose |
|--------|----------|--------|---------|
| GET | `/api/v2/admin/enterprise/dashboard` | dashboard | Enterprise dashboard |
| GET | `/api/v2/admin/enterprise/config` | config | Get enterprise config |
| GET | `/api/v2/admin/enterprise/config/secrets` | secrets | Get secret keys (masked) |
| GET | `/api/v2/admin/enterprise/roles` | roles | List custom roles |
| GET | `/api/v2/admin/enterprise/roles/{id}` | showRole | Get single role |
| GET | `/api/v2/admin/enterprise/permissions` | permissions | List all permissions |
| GET | `/api/v2/admin/enterprise/gdpr/dashboard` | gdprDashboard | GDPR compliance dashboard |
| GET | `/api/v2/admin/enterprise/gdpr/consents` | gdprConsents | List GDPR consents |
| GET | `/api/v2/admin/enterprise/gdpr/requests` | gdprRequests | List GDPR data requests (export, delete) |
| GET | `/api/v2/admin/enterprise/gdpr/audit` | gdprAudit | GDPR audit trail |
| GET | `/api/v2/admin/enterprise/gdpr/breaches` | gdprBreaches | List data breaches |
| GET | `/api/v2/admin/enterprise/monitoring` | monitoring | System monitoring dashboard |
| GET | `/api/v2/admin/enterprise/monitoring/health` | healthCheck | System health check |
| GET | `/api/v2/admin/enterprise/monitoring/logs` | logs | View system logs |
| GET | `/api/v2/admin/legal-documents` | legalDocs | List legal documents |
| GET | `/api/v2/admin/legal-documents/{id}` | showLegalDoc | Get single legal document |
| POST | `/api/v2/admin/enterprise/roles` | createRole | Create custom role |
| POST | `/api/v2/admin/enterprise/gdpr/breaches` | createBreach | Report data breach |
| POST | `/api/v2/admin/legal-documents` | createLegalDoc | Create legal document |
| PUT | `/api/v2/admin/enterprise/config` | updateConfig | Update enterprise config |
| PUT | `/api/v2/admin/enterprise/roles/{id}` | updateRole | Update role |
| PUT | `/api/v2/admin/enterprise/gdpr/requests/{id}` | updateGdprRequest | Update GDPR request status |
| PUT | `/api/v2/admin/legal-documents/{id}` | updateLegalDoc | Update legal document |
| DELETE | `/api/v2/admin/enterprise/roles/{id}` | deleteRole | Delete role |
| DELETE | `/api/v2/admin/legal-documents/{id}` | deleteLegalDoc | Delete legal document |

**Features:**
- Custom role & permission management
- GDPR compliance tools (consent tracking, data requests, breach reporting)
- System monitoring (health checks, logs, metrics)
- Legal document management (terms, privacy policy, etc.)
- Enterprise configuration

---

### 22. Super Admin (36 endpoints)

**AdminSuperApiController**

| Method | Endpoint | Action | Purpose |
|--------|----------|--------|---------|
| GET | `/api/v2/admin/super/dashboard` | dashboard | Super admin dashboard (all tenants) |
| GET | `/api/v2/admin/super/audit` | audit | Cross-tenant audit log |
| GET | `/api/v2/admin/super/tenants` | tenantList | List all tenants |
| GET | `/api/v2/admin/super/tenants/hierarchy` | tenantHierarchy | Get tenant hierarchy tree |
| GET | `/api/v2/admin/super/tenants/{id}` | tenantShow | Get single tenant detail |
| GET | `/api/v2/admin/super/users` | userList | List all users (cross-tenant) |
| GET | `/api/v2/admin/super/users/{id}` | userShow | Get single user detail (cross-tenant) |
| GET | `/api/v2/admin/super/federation` | federationOverview | Federation network overview |
| GET | `/api/v2/admin/super/federation/partnerships` | federationPartnerships | All partnerships |
| GET | `/api/v2/admin/super/federation/system-controls` | federationGetSystemControls | Federation system controls |
| GET | `/api/v2/admin/super/federation/whitelist` | federationGetWhitelist | Federation whitelist |
| GET | `/api/v2/admin/super/federation/tenant/{id}/features` | federationGetTenantFeatures | Get federation features for tenant |
| POST | `/api/v2/admin/super/tenants` | tenantCreate | Create new tenant |
| POST | `/api/v2/admin/super/tenants/{id}/move` | tenantMove | Move tenant in hierarchy |
| POST | `/api/v2/admin/super/tenants/{id}/toggle-hub` | tenantToggleHub | Toggle hub status |
| POST | `/api/v2/admin/super/tenants/{id}/reactivate` | tenantReactivate | Reactivate suspended tenant |
| POST | `/api/v2/admin/super/users` | userCreate | Create user in any tenant |
| POST | `/api/v2/admin/super/users/{id}/grant-super-admin` | userGrantSuperAdmin | Grant tenant super admin |
| POST | `/api/v2/admin/super/users/{id}/revoke-super-admin` | userRevokeSuperAdmin | Revoke tenant super admin |
| POST | `/api/v2/admin/super/users/{id}/grant-global-super-admin` | userGrantGlobalSuperAdmin | Grant global super admin (cross-tenant) |
| POST | `/api/v2/admin/super/users/{id}/revoke-global-super-admin` | userRevokeGlobalSuperAdmin | Revoke global super admin |
| POST | `/api/v2/admin/super/users/{id}/move-tenant` | userMoveTenant | Move user to different tenant |
| POST | `/api/v2/admin/super/users/{id}/move-and-promote` | userMoveAndPromote | Move user and grant admin role |
| POST | `/api/v2/admin/super/bulk/move-users` | bulkMoveUsers | Bulk move users to different tenant |
| POST | `/api/v2/admin/super/bulk/update-tenants` | bulkUpdateTenants | Bulk update tenant settings |
| POST | `/api/v2/admin/super/federation/whitelist` | federationAddToWhitelist | Add tenant to federation whitelist |
| POST | `/api/v2/admin/super/federation/partnerships/{id}/suspend` | federationSuspendPartnership | Suspend federation partnership |
| POST | `/api/v2/admin/super/federation/partnerships/{id}/terminate` | federationTerminatePartnership | Terminate federation partnership |
| POST | `/api/v2/admin/super/federation/emergency-lockdown` | federationEmergencyLockdown | Lock down federation network |
| POST | `/api/v2/admin/super/federation/lift-lockdown` | federationLiftLockdown | Lift federation lockdown |
| PUT | `/api/v2/admin/super/tenants/{id}` | tenantUpdate | Update tenant settings |
| PUT | `/api/v2/admin/super/users/{id}` | userUpdate | Update user (cross-tenant) |
| PUT | `/api/v2/admin/super/federation/system-controls` | federationUpdateSystemControls | Update federation controls |
| PUT | `/api/v2/admin/super/federation/tenant/{id}/features` | federationUpdateTenantFeature | Update federation features for tenant |
| DELETE | `/api/v2/admin/super/tenants/{id}` | tenantDelete | Delete tenant (soft delete) |
| DELETE | `/api/v2/admin/super/federation/whitelist/{tenantId}` | federationRemoveFromWhitelist | Remove from whitelist |

**Features:**
- Cross-tenant operations (view, edit, move users/tenants)
- Tenant hierarchy management (parent/child relationships)
- Hub tenant management (federation hubs)
- Global super admin management
- Bulk operations (move users, update tenants)
- Federation network control (whitelist, emergency lockdown)
- Cross-tenant audit trail

---

### 23. Cron Jobs (8 endpoints)

**AdminCronApiController**

| Method | Endpoint | Action | Purpose |
|--------|----------|--------|---------|
| GET | `/api/v2/admin/system/cron-jobs/settings` | getGlobalSettings | Get global cron settings |
| GET | `/api/v2/admin/system/cron-jobs/{jobId}/settings` | getJobSettings | Get settings for specific job |
| GET | `/api/v2/admin/system/cron-jobs/logs` | getLogs | Get cron job execution logs |
| GET | `/api/v2/admin/system/cron-jobs/logs/{id}` | getLogDetail | Get single log entry detail |
| GET | `/api/v2/admin/system/cron-jobs/health` | getHealthMetrics | Get cron health metrics (failures, delays) |
| PUT | `/api/v2/admin/system/cron-jobs/settings` | updateGlobalSettings | Update global settings (enable/disable all) |
| PUT | `/api/v2/admin/system/cron-jobs/{jobId}/settings` | updateJobSettings | Update job settings (schedule, enabled) |
| DELETE | `/api/v2/admin/system/cron-jobs/logs` | clearLogs | Clear old logs |

**Features:**
- Cron job management (enable/disable individual jobs)
- Schedule configuration (cron expressions)
- Execution logs
- Health monitoring (failed jobs, delays)
- Log retention management

---

### 24. Legal Documents (9 endpoints)

**AdminLegalDocController**

| Method | Endpoint | Action | Purpose |
|--------|----------|--------|---------|
| GET | `/api/v2/admin/legal-documents/compliance` | getComplianceStats | Compliance stats (% accepted) |
| GET | `/api/v2/admin/legal-documents/{docId}/versions` | getVersions | List all versions of document |
| GET | `/api/v2/admin/legal-documents/{docId}/versions/compare` | compareVersions | Compare two versions (diff) |
| GET | `/api/v2/admin/legal-documents/{docId}/versions/{versionId}/pending-count` | getUsersPendingCount | Count users who haven't accepted |
| GET | `/api/v2/admin/legal-documents/versions/{versionId}/acceptances` | getAcceptances | List user acceptances |
| GET | `/api/v2/admin/legal-documents/{docId}/acceptances/export` | exportAcceptances | Export acceptances as CSV |
| POST | `/api/v2/admin/legal-documents/{docId}/versions` | createVersion | Create new version |
| POST | `/api/v2/admin/legal-documents/versions/{versionId}/publish` | publishVersion | Publish version (make current) |
| POST | `/api/v2/admin/legal-documents/{docId}/versions/{versionId}/notify` | notifyUsers | Notify users of new version |

**Features:**
- Version management (create, compare, publish)
- User acceptance tracking
- Compliance reporting
- Bulk notifications
- Export to CSV

---

## API Design Patterns

### 1. Authentication & Authorization

All admin endpoints require authentication via JWT token in `Authorization: Bearer <token>` header.

**Authorization levels:**
- **Admin** — tenant_admin, admin roles (`requireAdmin()` check)
- **Super Admin** — `is_super_admin = 1` or `is_tenant_super_admin = 1`
- **Global Super Admin** — cross-tenant access

**Security measures:**
- Prevents self-deletion (user can't delete own account)
- Prevents self-suspension/ban
- Prevents super admin modification by non-super admins
- Prevents self super admin status modification
- Tenant isolation (all queries scoped by `tenant_id`)
- Audit logging on sensitive operations

### 2. Pagination

**Standard pagination parameters:**
- `page` — page number (default 1)
- `limit` — items per page (default 20, max 100)

**Response format:**
```json
{
  "data": [...],
  "meta": {
    "current_page": 1,
    "total_pages": 10,
    "total_items": 200,
    "per_page": 20
  }
}
```

### 3. Filtering & Search

**Common filters:**
- `status` — filter by status (active, pending, suspended, banned, etc.)
- `search` — full-text search (name, email, title, etc.)
- `sort` — sort column (name, email, created_at, balance, etc.)
- `order` — sort direction (ASC, DESC)
- `date_from` / `date_to` — date range filter

### 4. Error Handling

**Error response format:**
```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "First name is required",
    "field": "first_name"
  }
}
```

**HTTP status codes:**
- `200 OK` — successful GET/PUT
- `201 Created` — successful POST
- `204 No Content` — successful DELETE
- `400 Bad Request` — invalid input
- `401 Unauthorized` — missing/invalid token
- `403 Forbidden` — insufficient permissions
- `404 Not Found` — resource not found
- `422 Unprocessable Entity` — validation errors
- `500 Internal Server Error` — server error

### 5. Audit Logging

**Logged actions:**
- User CRUD (create, update, delete)
- User moderation (approve, suspend, ban, reactivate)
- Role changes (grant/revoke admin, super admin)
- Impersonation
- Badge awards/removal
- Configuration changes
- Tenant operations (create, update, move, delete)

**Audit log fields:**
- `actor_id` — user who performed action
- `action` — action type (e.g., `admin_suspend_user`)
- `target_id` — affected resource ID
- `description` — human-readable description
- `metadata` — JSON with changed fields, old/new values
- `tenant_id` — tenant context
- `created_at` — timestamp

---

## Identified Gaps & Missing Endpoints

### 1. Wallet & Transactions

**Missing:**
- `GET /api/v2/admin/transactions` — list all transactions (admin oversight)
- `GET /api/v2/admin/transactions/{id}` — get single transaction detail
- `POST /api/v2/admin/transactions/{id}/void` — void/reverse transaction
- `GET /api/v2/admin/wallet/audit` — wallet audit trail (balance changes)

### 2. Events Management

**Missing:**
- `GET /api/v2/admin/events` — list all events
- `GET /api/v2/admin/events/{id}` — get single event
- `POST /api/v2/admin/events/{id}/cancel` — cancel event
- `GET /api/v2/admin/events/{id}/rsvps` — list RSVPs

### 3. Messages & Conversations

**Missing:**
- `GET /api/v2/admin/messages` — list all messages (moderation)
- `GET /api/v2/admin/messages/{id}` — get single message
- `POST /api/v2/admin/messages/{id}/delete` — delete message
- `POST /api/v2/admin/messages/{id}/flag` — flag for review

**Note:** Broker tools provide message moderation (`/admin/broker/messages`), but no general message admin endpoints.

### 4. Reviews & Ratings

**Missing:**
- `GET /api/v2/admin/reviews` — list all reviews
- `GET /api/v2/admin/reviews/{id}` — get single review
- `POST /api/v2/admin/reviews/{id}/approve` — approve review
- `POST /api/v2/admin/reviews/{id}/reject` — reject review
- `DELETE /api/v2/admin/reviews/{id}` — delete review

### 5. Reports & Moderation

**Missing:**
- `GET /api/v2/admin/reports` — list all content reports
- `GET /api/v2/admin/reports/{id}` — get single report
- `POST /api/v2/admin/reports/{id}/action` — take action on report
- `POST /api/v2/admin/reports/{id}/dismiss` — dismiss report

### 6. Notifications

**Missing:**
- `GET /api/v2/admin/notifications` — list all notifications (audit)
- `POST /api/v2/admin/notifications/broadcast` — send broadcast notification
- `GET /api/v2/admin/notifications/settings` — get notification settings
- `PUT /api/v2/admin/notifications/settings` — update notification settings

### 7. Feed & Posts

**Missing:**
- `GET /api/v2/admin/feed` — list all feed posts
- `GET /api/v2/admin/feed/{id}` — get single post
- `DELETE /api/v2/admin/feed/{id}` — delete post
- `POST /api/v2/admin/feed/{id}/pin` — pin post to top

### 8. Exchanges

**Missing:**
- `GET /api/v2/admin/exchanges` — list all exchanges (audit trail)
- `GET /api/v2/admin/exchanges/{id}` — get single exchange detail
- `POST /api/v2/admin/exchanges/{id}/cancel` — cancel exchange

**Note:** Broker tools provide exchange moderation (`/admin/broker/exchanges`), but no general exchange admin endpoints.

### 9. Organizations

**Missing:**
- `GET /api/v2/admin/organizations` — list all organizations
- `GET /api/v2/admin/organizations/{id}` — get single organization
- `PUT /api/v2/admin/organizations/{id}` — update organization
- `DELETE /api/v2/admin/organizations/{id}` — delete organization

### 10. Goals

**Missing:**
- `GET /api/v2/admin/goals` — list all goals
- `GET /api/v2/admin/goals/{id}` — get single goal
- `DELETE /api/v2/admin/goals/{id}` — delete goal

### 11. Resources

**Missing:**
- `GET /api/v2/admin/resources` — list all resources
- `GET /api/v2/admin/resources/{id}` — get single resource
- `POST /api/v2/admin/resources` — create resource
- `PUT /api/v2/admin/resources/{id}` — update resource
- `DELETE /api/v2/admin/resources/{id}` — delete resource

### 12. Email Management

**Existing:** Newsletter APIs cover campaigns, but missing:
- `GET /api/v2/admin/emails/queue` — view email queue
- `GET /api/v2/admin/emails/failed` — view failed emails
- `POST /api/v2/admin/emails/{id}/retry` — retry failed email
- `GET /api/v2/admin/emails/settings` — get SMTP/API settings
- `PUT /api/v2/admin/emails/settings` — update email settings

### 13. File/Upload Management

**Missing:**
- `GET /api/v2/admin/uploads` — list all uploaded files
- `GET /api/v2/admin/uploads/stats` — storage usage stats
- `DELETE /api/v2/admin/uploads/{id}` — delete uploaded file
- `POST /api/v2/admin/uploads/cleanup` — clean up orphaned files

### 14. Listings Management (Enhancements)

**Existing:** 4 endpoints (list, show, approve, delete)

**Missing:**
- `PUT /api/v2/admin/listings/{id}` — edit listing (admin can edit on behalf of user)
- `POST /api/v2/admin/listings/{id}/feature` — feature listing
- `POST /api/v2/admin/listings/{id}/extend` — extend expiry date
- `POST /api/v2/admin/listings/bulk-approve` — bulk approve pending listings
- `POST /api/v2/admin/listings/bulk-delete` — bulk delete listings

### 15. Connections

**Missing:**
- `GET /api/v2/admin/connections` — list all connections
- `DELETE /api/v2/admin/connections/{id}` — delete connection (block/remove)

### 16. Polls

**Missing:**
- `GET /api/v2/admin/polls` — list all polls
- `GET /api/v2/admin/polls/{id}` — get single poll
- `DELETE /api/v2/admin/polls/{id}` — delete poll

### 17. Comments

**Missing:**
- `GET /api/v2/admin/comments` — list all comments (moderation)
- `DELETE /api/v2/admin/comments/{id}` — delete comment

### 18. Activity Log

**Existing:** Dashboard activity endpoint

**Missing:**
- `GET /api/v2/admin/activity-log` — full activity log (paginated, filterable)
- `GET /api/v2/admin/activity-log/export` — export activity log

### 19. System Health

**Existing:** Enterprise health check endpoint

**Missing:**
- `GET /api/v2/admin/system/status` — overall system status
- `GET /api/v2/admin/system/services` — service status (database, Redis, Pusher, etc.)
- `GET /api/v2/admin/system/disk-usage` — disk usage stats

### 20. Backup & Restore

**Existing:** Blog backup endpoints

**Missing:**
- `GET /api/v2/admin/backups` — list all backups
- `POST /api/v2/admin/backups` — create manual backup
- `POST /api/v2/admin/backups/{id}/restore` — restore from backup

---

## API Consistency Analysis

### Strengths

1. **RESTful Design** — endpoints follow REST conventions (GET for read, POST for create/actions, PUT for update, DELETE for delete)
2. **Consistent Naming** — clear, descriptive action names (`approve`, `suspend`, `reactivate`, etc.)
3. **Tenant Scoping** — all endpoints enforce tenant isolation
4. **Pagination** — standard pagination on all list endpoints
5. **Error Handling** — consistent error response format with API error codes
6. **Audit Logging** — most mutating operations are logged
7. **Security** — authentication, authorization, self-modification protection

### Inconsistencies

1. **Action Endpoint Naming**
   - Some use `POST /resource/{id}/action` (e.g., `/users/{id}/approve`)
   - Others use `POST /resource/action/{id}` (e.g., `/groups/approvals/{id}/approve`)
   - Recommendation: Standardize on `POST /resource/{id}/action`

2. **Bulk Operations**
   - Inconsistent bulk operation patterns
   - Some use `POST /resource/bulk-action` (e.g., `/gamification/bulk-award`)
   - Others use separate endpoints (e.g., `/users/import`)
   - Recommendation: Standardize on `POST /resource/bulk-{action}`

3. **Stats/Analytics Endpoints**
   - Some at `/resource/stats` (e.g., `/gamification/stats`)
   - Others at `/resource/analytics` (e.g., `/groups/analytics`)
   - Both mean the same thing
   - Recommendation: Use `analytics` for detailed data, `stats` for simple metrics

4. **Nested Resource Paths**
   - Inconsistent nesting depth
   - Some use `/resource/{id}/sub-resource` (e.g., `/groups/{id}/members`)
   - Others use `/sub-resource` with query params (e.g., `/vetting/user/{userId}`)
   - Recommendation: Use nesting for true parent-child relationships

5. **Approval Endpoints**
   - Group approvals: `/groups/approvals/{id}/approve`
   - Match approvals: `/matching/approvals/{id}/approve`
   - User approvals: `/users/{id}/approve`
   - Recommendation: Standardize on `/resource/{id}/approve` for resource approvals, `/resource/approvals/{id}/approve` for approval workflows

---

## RESTful Design Recommendations

### 1. Resource-Based Routes

**Good:**
```
GET    /api/v2/admin/users
POST   /api/v2/admin/users
GET    /api/v2/admin/users/{id}
PUT    /api/v2/admin/users/{id}
DELETE /api/v2/admin/users/{id}
```

**Actions on Resources:**
```
POST /api/v2/admin/users/{id}/approve
POST /api/v2/admin/users/{id}/suspend
POST /api/v2/admin/users/{id}/ban
```

### 2. Bulk Operations

**Recommended pattern:**
```
POST /api/v2/admin/users/bulk-delete
POST /api/v2/admin/users/bulk-approve
POST /api/v2/admin/users/bulk-import
```

### 3. Sub-Resources

**Recommended pattern:**
```
GET    /api/v2/admin/groups/{groupId}/members
POST   /api/v2/admin/groups/{groupId}/members
DELETE /api/v2/admin/groups/{groupId}/members/{userId}
```

### 4. Analytics & Stats

**Recommended pattern:**
```
GET /api/v2/admin/users/stats        # Simple metrics (counts, totals)
GET /api/v2/admin/users/analytics    # Detailed analytics (trends, breakdowns)
```

---

## Performance Considerations

### Caching

**Cached endpoints:**
- Dashboard stats (5 minutes)
- Tenant bootstrap data (15 minutes)
- Federation directory (30 minutes)

**Cache invalidation:**
- Automatic on create/update/delete operations
- Manual via `/admin/cache/clear`

### Pagination Limits

- Default: 20 items per page
- Maximum: 100 items per page (prevents excessive data transfer)

### Database Queries

- All queries use prepared statements (PDO)
- Tenant scoping on all queries (`tenant_id = ?`)
- Indexed columns: `tenant_id`, `user_id`, `status`, `created_at`

### Audit Logging Overhead

- Audit logging adds ~10-20ms per mutating request
- Logged to separate `audit_log` table (doesn't slow down main queries)
- Logs are pruned after 2 years

---

## Security Analysis

### Authentication

- JWT token required on all endpoints
- Token validated via `ApiAuth::authenticate()`
- Token includes `user_id`, `tenant_id`, `role` claims

### Authorization

- Role-based access control (RBAC)
- `requireAdmin()` checks for `tenant_admin`, `admin`, or `is_super_admin`
- Super admin endpoints check `is_super_admin = 1`

### Tenant Isolation

- All queries scoped by `TenantContext::getId()`
- Cross-tenant access only for super admins
- Super admin endpoints check `isTokenUserSuperAdmin()`

### Input Validation

- All user input validated before database queries
- Email validation (`filter_var($email, FILTER_VALIDATE_EMAIL)`)
- Password strength check (min 8 characters)
- SQL injection prevention (prepared statements)
- XSS prevention (`htmlspecialchars()` on output)

### Self-Modification Protection

- Users can't delete own account
- Users can't suspend/ban own account
- Users can't modify own super admin status
- Users can't impersonate themselves

### Rate Limiting

**Missing:** No rate limiting on admin endpoints. Recommend adding:
- 100 requests/minute per admin user
- 1000 requests/hour per admin user

---

## Recommendations

### High Priority

1. **Add missing content moderation endpoints**
   - Reviews, messages, feed posts, comments
   - Report management

2. **Add transaction oversight endpoints**
   - List transactions, void/reverse transactions
   - Wallet audit trail

3. **Add event management endpoints**
   - List events, cancel events, view RSVPs

4. **Standardize action endpoint naming**
   - Use `POST /resource/{id}/action` consistently

5. **Add rate limiting**
   - Prevent admin API abuse

### Medium Priority

6. **Add notification management endpoints**
   - Broadcast notifications, notification settings

7. **Add email queue management**
   - View email queue, retry failed emails

8. **Add file/upload management**
   - List uploads, storage stats, cleanup

9. **Enhance listings management**
   - Edit listings, feature listings, extend expiry, bulk operations

10. **Add system health endpoints**
    - Overall status, service status, disk usage

### Low Priority

11. **Add backup/restore endpoints**
    - Full system backups (beyond just blog)

12. **Add organization management**
    - CRUD for organizations

13. **Add resource management**
    - CRUD for resources

14. **Add goals/polls/connections admin endpoints**
    - For completeness

---

## Conclusion

The Project NEXUS admin API is comprehensive and well-designed, with **301 endpoints** covering most administrative needs. The API follows RESTful conventions, enforces tenant isolation, and includes extensive functionality for multi-tenant management, content moderation, analytics, and enterprise features.

**Key Strengths:**
- Comprehensive coverage (24 functional domains)
- RESTful design
- Tenant isolation
- Security (authentication, authorization, audit logging)
- Pagination and filtering

**Key Gaps:**
- Content moderation (reviews, messages, feed posts, comments)
- Transaction oversight
- Event management
- Email queue management
- File/upload management
- System health monitoring

**Recommendations:**
- Fill high-priority gaps (content moderation, transaction oversight)
- Standardize naming conventions (action endpoints, bulk operations)
- Add rate limiting
- Enhance listings management with bulk operations
