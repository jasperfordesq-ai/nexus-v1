# PHP Admin Feature Inventory

> **Generated:** 2026-02-14 | **Source:** Full scan of `httpdocs/routes.php`, all admin controllers, views, and service dependencies

This document is the EXHAUSTIVE inventory of every admin feature in the PHP backend, organized by domain. It serves as the ground-truth reference for React admin parity work.

---

## Table of Contents

1. [V2 API Routes (React Admin Panel)](#1-v2-api-routes-react-admin-panel)
2. [Legacy Admin Routes](#2-legacy-admin-routes)
3. [Super Admin Routes](#3-super-admin-routes)
4. [Controller Inventory](#4-controller-inventory)
5. [Admin View Files (Legacy)](#5-admin-view-files-legacy)
6. [Service Dependencies](#6-service-dependencies)
7. [Feature Domain Summary](#7-feature-domain-summary)

---

## 1. V2 API Routes (React Admin Panel)

Routes defined in `httpdocs/routes.php` lines 464-767. All require admin JWT auth via `$this->requireAdmin()`.

### 1.1 Dashboard (3 endpoints)

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/api/v2/admin/dashboard/stats` | AdminDashboardApiController | `stats` |
| GET | `/api/v2/admin/dashboard/trends` | AdminDashboardApiController | `trends` |
| GET | `/api/v2/admin/dashboard/activity` | AdminDashboardApiController | `activity` |

### 1.2 Users (14 endpoints)

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/api/v2/admin/users` | AdminUsersApiController | `index` |
| POST | `/api/v2/admin/users` | AdminUsersApiController | `store` |
| GET | `/api/v2/admin/users/{id}` | AdminUsersApiController | `show` |
| PUT | `/api/v2/admin/users/{id}` | AdminUsersApiController | `update` |
| DELETE | `/api/v2/admin/users/{id}` | AdminUsersApiController | `destroy` |
| POST | `/api/v2/admin/users/{id}/approve` | AdminUsersApiController | `approve` |
| POST | `/api/v2/admin/users/{id}/suspend` | AdminUsersApiController | `suspend` |
| POST | `/api/v2/admin/users/{id}/ban` | AdminUsersApiController | `ban` |
| POST | `/api/v2/admin/users/{id}/reactivate` | AdminUsersApiController | `reactivate` |
| POST | `/api/v2/admin/users/{id}/reset-2fa` | AdminUsersApiController | `reset2fa` |
| POST | `/api/v2/admin/users/{id}/badges` | AdminUsersApiController | `addBadge` |
| DELETE | `/api/v2/admin/users/{id}/badges/{badgeId}` | AdminUsersApiController | `removeBadge` |
| POST | `/api/v2/admin/users/{id}/impersonate` | AdminUsersApiController | `impersonate` |
| POST | `/api/v2/admin/users/badges/recheck-all` | AdminGamificationApiController | `recheckAll` |

### 1.3 Listings (4 endpoints)

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/api/v2/admin/listings` | AdminListingsApiController | `index` |
| GET | `/api/v2/admin/listings/{id}` | AdminListingsApiController | `show` |
| POST | `/api/v2/admin/listings/{id}/approve` | AdminListingsApiController | `approve` |
| DELETE | `/api/v2/admin/listings/{id}` | AdminListingsApiController | `destroy` |

### 1.4 Categories & Attributes (8 endpoints)

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/api/v2/admin/categories` | AdminCategoriesApiController | `index` |
| POST | `/api/v2/admin/categories` | AdminCategoriesApiController | `store` |
| PUT | `/api/v2/admin/categories/{id}` | AdminCategoriesApiController | `update` |
| DELETE | `/api/v2/admin/categories/{id}` | AdminCategoriesApiController | `destroy` |
| GET | `/api/v2/admin/attributes` | AdminCategoriesApiController | `listAttributes` |
| POST | `/api/v2/admin/attributes` | AdminCategoriesApiController | `storeAttribute` |
| PUT | `/api/v2/admin/attributes/{id}` | AdminCategoriesApiController | `updateAttribute` |
| DELETE | `/api/v2/admin/attributes/{id}` | AdminCategoriesApiController | `destroyAttribute` |

### 1.5 Config, Features & Modules (16 endpoints)

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/api/v2/admin/config` | AdminConfigApiController | `getConfig` |
| PUT | `/api/v2/admin/config/features` | AdminConfigApiController | `updateFeature` |
| PUT | `/api/v2/admin/config/modules` | AdminConfigApiController | `updateModule` |
| GET | `/api/v2/admin/settings` | AdminConfigApiController | `getSettings` |
| PUT | `/api/v2/admin/settings` | AdminConfigApiController | `updateSettings` |
| GET | `/api/v2/admin/config/ai` | AdminConfigApiController | `getAiConfig` |
| PUT | `/api/v2/admin/config/ai` | AdminConfigApiController | `updateAiConfig` |
| GET | `/api/v2/admin/config/feed-algorithm` | AdminConfigApiController | `getFeedAlgorithmConfig` |
| PUT | `/api/v2/admin/config/feed-algorithm` | AdminConfigApiController | `updateFeedAlgorithmConfig` |
| GET | `/api/v2/admin/config/images` | AdminConfigApiController | `getImageConfig` |
| PUT | `/api/v2/admin/config/images` | AdminConfigApiController | `updateImageConfig` |
| GET | `/api/v2/admin/config/seo` | AdminConfigApiController | `getSeoConfig` |
| PUT | `/api/v2/admin/config/seo` | AdminConfigApiController | `updateSeoConfig` |
| GET | `/api/v2/admin/config/native-app` | AdminConfigApiController | `getNativeAppConfig` |
| PUT | `/api/v2/admin/config/native-app` | AdminConfigApiController | `updateNativeAppConfig` |
| GET | `/api/v2/admin/cache/stats` | AdminConfigApiController | `cacheStats` |

### 1.6 Cache & Jobs (5 endpoints)

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| POST | `/api/v2/admin/cache/clear` | AdminConfigApiController | `clearCache` |
| GET | `/api/v2/admin/jobs` | AdminConfigApiController | `getJobs` |
| POST | `/api/v2/admin/jobs/{id}/run` | AdminConfigApiController | `runJob` |
| GET | `/api/v2/admin/system/cron-jobs` | AdminConfigApiController | `getCronJobs` |
| POST | `/api/v2/admin/system/cron-jobs/{id}/run` | AdminConfigApiController | `runCronJob` |

### 1.7 System (1 endpoint)

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/api/v2/admin/system/activity-log` | AdminDashboardApiController | `activity` |

### 1.8 Matching & Approvals (9 endpoints)

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/api/v2/admin/matching/config` | AdminMatchingApiController | `getConfig` |
| PUT | `/api/v2/admin/matching/config` | AdminMatchingApiController | `updateConfig` |
| POST | `/api/v2/admin/matching/cache/clear` | AdminMatchingApiController | `clearCache` |
| GET | `/api/v2/admin/matching/stats` | AdminMatchingApiController | `getStats` |
| GET | `/api/v2/admin/matching/approvals` | AdminMatchingApiController | `index` |
| GET | `/api/v2/admin/matching/approvals/stats` | AdminMatchingApiController | `approvalStats` |
| GET | `/api/v2/admin/matching/approvals/{id}` | AdminMatchingApiController | `show` |
| POST | `/api/v2/admin/matching/approvals/{id}/approve` | AdminMatchingApiController | `approve` |
| POST | `/api/v2/admin/matching/approvals/{id}/reject` | AdminMatchingApiController | `reject` |

### 1.9 Blog (6 endpoints)

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/api/v2/admin/blog` | AdminBlogApiController | `index` |
| POST | `/api/v2/admin/blog` | AdminBlogApiController | `store` |
| GET | `/api/v2/admin/blog/{id}` | AdminBlogApiController | `show` |
| PUT | `/api/v2/admin/blog/{id}` | AdminBlogApiController | `update` |
| DELETE | `/api/v2/admin/blog/{id}` | AdminBlogApiController | `destroy` |
| POST | `/api/v2/admin/blog/{id}/toggle-status` | AdminBlogApiController | `toggleStatus` |

### 1.10 Gamification (10 endpoints)

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/api/v2/admin/gamification/stats` | AdminGamificationApiController | `stats` |
| GET | `/api/v2/admin/gamification/badges` | AdminGamificationApiController | `badges` |
| POST | `/api/v2/admin/gamification/badges` | AdminGamificationApiController | `createBadge` |
| DELETE | `/api/v2/admin/gamification/badges/{id}` | AdminGamificationApiController | `deleteBadge` |
| GET | `/api/v2/admin/gamification/campaigns` | AdminGamificationApiController | `campaigns` |
| POST | `/api/v2/admin/gamification/campaigns` | AdminGamificationApiController | `createCampaign` |
| PUT | `/api/v2/admin/gamification/campaigns/{id}` | AdminGamificationApiController | `updateCampaign` |
| DELETE | `/api/v2/admin/gamification/campaigns/{id}` | AdminGamificationApiController | `deleteCampaign` |
| POST | `/api/v2/admin/gamification/recheck-all` | AdminGamificationApiController | `recheckAll` |
| POST | `/api/v2/admin/gamification/bulk-award` | AdminGamificationApiController | `bulkAward` |

### 1.11 Groups (7 endpoints)

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/api/v2/admin/groups` | AdminGroupsApiController | `index` |
| GET | `/api/v2/admin/groups/analytics` | AdminGroupsApiController | `analytics` |
| GET | `/api/v2/admin/groups/approvals` | AdminGroupsApiController | `approvals` |
| POST | `/api/v2/admin/groups/approvals/{id}/approve` | AdminGroupsApiController | `approveMember` |
| POST | `/api/v2/admin/groups/approvals/{id}/reject` | AdminGroupsApiController | `rejectMember` |
| GET | `/api/v2/admin/groups/moderation` | AdminGroupsApiController | `moderation` |
| DELETE | `/api/v2/admin/groups/{id}` | AdminGroupsApiController | `deleteGroup` |

### 1.12 Timebanking (6 endpoints)

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/api/v2/admin/timebanking/stats` | AdminTimebankingApiController | `stats` |
| GET | `/api/v2/admin/timebanking/alerts` | AdminTimebankingApiController | `alerts` |
| PUT | `/api/v2/admin/timebanking/alerts/{id}` | AdminTimebankingApiController | `updateAlert` |
| POST | `/api/v2/admin/timebanking/adjust-balance` | AdminTimebankingApiController | `adjustBalance` |
| GET | `/api/v2/admin/timebanking/org-wallets` | AdminTimebankingApiController | `orgWallets` |
| GET | `/api/v2/admin/timebanking/user-report` | AdminTimebankingApiController | `userReport` |

### 1.13 Enterprise (25 endpoints)

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/api/v2/admin/enterprise/dashboard` | AdminEnterpriseApiController | `dashboard` |
| GET | `/api/v2/admin/enterprise/roles` | AdminEnterpriseApiController | `roles` |
| POST | `/api/v2/admin/enterprise/roles` | AdminEnterpriseApiController | `createRole` |
| GET | `/api/v2/admin/enterprise/roles/{id}` | AdminEnterpriseApiController | `showRole` |
| PUT | `/api/v2/admin/enterprise/roles/{id}` | AdminEnterpriseApiController | `updateRole` |
| DELETE | `/api/v2/admin/enterprise/roles/{id}` | AdminEnterpriseApiController | `deleteRole` |
| GET | `/api/v2/admin/enterprise/permissions` | AdminEnterpriseApiController | `permissions` |
| GET | `/api/v2/admin/enterprise/gdpr/dashboard` | AdminEnterpriseApiController | `gdprDashboard` |
| GET | `/api/v2/admin/enterprise/gdpr/requests` | AdminEnterpriseApiController | `gdprRequests` |
| PUT | `/api/v2/admin/enterprise/gdpr/requests/{id}` | AdminEnterpriseApiController | `updateGdprRequest` |
| GET | `/api/v2/admin/enterprise/gdpr/consents` | AdminEnterpriseApiController | `gdprConsents` |
| GET | `/api/v2/admin/enterprise/gdpr/breaches` | AdminEnterpriseApiController | `gdprBreaches` |
| GET | `/api/v2/admin/enterprise/gdpr/audit` | AdminEnterpriseApiController | `gdprAudit` |
| GET | `/api/v2/admin/enterprise/monitoring` | AdminEnterpriseApiController | `monitoring` |
| GET | `/api/v2/admin/enterprise/monitoring/health` | AdminEnterpriseApiController | `healthCheck` |
| GET | `/api/v2/admin/enterprise/monitoring/logs` | AdminEnterpriseApiController | `logs` |
| GET | `/api/v2/admin/enterprise/config` | AdminEnterpriseApiController | `config` |
| PUT | `/api/v2/admin/enterprise/config` | AdminEnterpriseApiController | `updateConfig` |
| GET | `/api/v2/admin/enterprise/config/secrets` | AdminEnterpriseApiController | `secrets` |
| GET | `/api/v2/admin/legal-documents` | AdminEnterpriseApiController | `legalDocs` |
| POST | `/api/v2/admin/legal-documents` | AdminEnterpriseApiController | `createLegalDoc` |
| GET | `/api/v2/admin/legal-documents/{id}` | AdminEnterpriseApiController | `showLegalDoc` |
| PUT | `/api/v2/admin/legal-documents/{id}` | AdminEnterpriseApiController | `updateLegalDoc` |
| DELETE | `/api/v2/admin/legal-documents/{id}` | AdminEnterpriseApiController | `deleteLegalDoc` |

### 1.14 Broker Controls (8 endpoints)

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/api/v2/admin/broker/dashboard` | AdminBrokerApiController | `dashboard` |
| GET | `/api/v2/admin/broker/exchanges` | AdminBrokerApiController | `exchanges` |
| POST | `/api/v2/admin/broker/exchanges/{id}/approve` | AdminBrokerApiController | `approveExchange` |
| POST | `/api/v2/admin/broker/exchanges/{id}/reject` | AdminBrokerApiController | `rejectExchange` |
| GET | `/api/v2/admin/broker/risk-tags` | AdminBrokerApiController | `riskTags` |
| GET | `/api/v2/admin/broker/messages` | AdminBrokerApiController | `messages` |
| POST | `/api/v2/admin/broker/messages/{id}/review` | AdminBrokerApiController | `reviewMessage` |
| GET | `/api/v2/admin/broker/monitoring` | AdminBrokerApiController | `monitoring` |

### 1.15 Newsletters (9 endpoints)

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/api/v2/admin/newsletters` | AdminNewsletterApiController | `index` |
| POST | `/api/v2/admin/newsletters` | AdminNewsletterApiController | `store` |
| GET | `/api/v2/admin/newsletters/subscribers` | AdminNewsletterApiController | `subscribers` |
| GET | `/api/v2/admin/newsletters/segments` | AdminNewsletterApiController | `segments` |
| GET | `/api/v2/admin/newsletters/templates` | AdminNewsletterApiController | `templates` |
| GET | `/api/v2/admin/newsletters/analytics` | AdminNewsletterApiController | `analytics` |
| GET | `/api/v2/admin/newsletters/{id}` | AdminNewsletterApiController | `show` |
| PUT | `/api/v2/admin/newsletters/{id}` | AdminNewsletterApiController | `update` |
| DELETE | `/api/v2/admin/newsletters/{id}` | AdminNewsletterApiController | `destroy` |

### 1.16 Volunteering (3 endpoints)

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/api/v2/admin/volunteering` | AdminVolunteeringApiController | `index` |
| GET | `/api/v2/admin/volunteering/approvals` | AdminVolunteeringApiController | `approvals` |
| GET | `/api/v2/admin/volunteering/organizations` | AdminVolunteeringApiController | `organizations` |

### 1.17 Federation (8 endpoints)

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/api/v2/admin/federation/settings` | AdminFederationApiController | `settings` |
| GET | `/api/v2/admin/federation/partnerships` | AdminFederationApiController | `partnerships` |
| GET | `/api/v2/admin/federation/directory` | AdminFederationApiController | `directory` |
| GET | `/api/v2/admin/federation/directory/profile` | AdminFederationApiController | `profile` |
| GET | `/api/v2/admin/federation/analytics` | AdminFederationApiController | `analytics` |
| GET | `/api/v2/admin/federation/api-keys` | AdminFederationApiController | `apiKeys` |
| POST | `/api/v2/admin/federation/api-keys` | AdminFederationApiController | `createApiKey` |
| GET | `/api/v2/admin/federation/data` | AdminFederationApiController | `dataManagement` |

### 1.18 Pages, Menus, Plans (Content Management - 21 endpoints)

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/api/v2/admin/pages` | AdminContentApiController | `getPages` |
| POST | `/api/v2/admin/pages` | AdminContentApiController | `createPage` |
| GET | `/api/v2/admin/pages/{id}` | AdminContentApiController | `getPage` |
| PUT | `/api/v2/admin/pages/{id}` | AdminContentApiController | `updatePage` |
| DELETE | `/api/v2/admin/pages/{id}` | AdminContentApiController | `deletePage` |
| GET | `/api/v2/admin/menus` | AdminContentApiController | `getMenus` |
| POST | `/api/v2/admin/menus` | AdminContentApiController | `createMenu` |
| GET | `/api/v2/admin/menus/{id}` | AdminContentApiController | `getMenu` |
| PUT | `/api/v2/admin/menus/{id}` | AdminContentApiController | `updateMenu` |
| DELETE | `/api/v2/admin/menus/{id}` | AdminContentApiController | `deleteMenu` |
| GET | `/api/v2/admin/menus/{id}/items` | AdminContentApiController | `getMenuItems` |
| POST | `/api/v2/admin/menus/{id}/items` | AdminContentApiController | `createMenuItem` |
| POST | `/api/v2/admin/menus/{id}/items/reorder` | AdminContentApiController | `reorderMenuItems` |
| PUT | `/api/v2/admin/menu-items/{id}` | AdminContentApiController | `updateMenuItem` |
| DELETE | `/api/v2/admin/menu-items/{id}` | AdminContentApiController | `deleteMenuItem` |
| GET | `/api/v2/admin/plans` | AdminContentApiController | `getPlans` |
| POST | `/api/v2/admin/plans` | AdminContentApiController | `createPlan` |
| GET | `/api/v2/admin/plans/{id}` | AdminContentApiController | `getPlan` |
| PUT | `/api/v2/admin/plans/{id}` | AdminContentApiController | `updatePlan` |
| DELETE | `/api/v2/admin/plans/{id}` | AdminContentApiController | `deletePlan` |
| GET | `/api/v2/admin/subscriptions` | AdminContentApiController | `getSubscriptions` |

### 1.19 Tools (10 endpoints)

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/api/v2/admin/tools/redirects` | AdminToolsApiController | `getRedirects` |
| POST | `/api/v2/admin/tools/redirects` | AdminToolsApiController | `createRedirect` |
| DELETE | `/api/v2/admin/tools/redirects/{id}` | AdminToolsApiController | `deleteRedirect` |
| GET | `/api/v2/admin/tools/404-errors` | AdminToolsApiController | `get404Errors` |
| DELETE | `/api/v2/admin/tools/404-errors/{id}` | AdminToolsApiController | `delete404Error` |
| POST | `/api/v2/admin/tools/health-check` | AdminToolsApiController | `runHealthCheck` |
| GET | `/api/v2/admin/tools/webp-stats` | AdminToolsApiController | `getWebpStats` |
| POST | `/api/v2/admin/tools/webp-convert` | AdminToolsApiController | `runWebpConversion` |
| POST | `/api/v2/admin/tools/seed` | AdminToolsApiController | `runSeedGenerator` |
| GET | `/api/v2/admin/tools/blog-backups` | AdminToolsApiController | `getBlogBackups` |

### 1.20 Deliverability (8 endpoints)

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `/api/v2/admin/deliverability/dashboard` | AdminDeliverabilityApiController | `getDashboard` |
| GET | `/api/v2/admin/deliverability/analytics` | AdminDeliverabilityApiController | `getAnalytics` |
| GET | `/api/v2/admin/deliverability` | AdminDeliverabilityApiController | `getDeliverables` |
| POST | `/api/v2/admin/deliverability` | AdminDeliverabilityApiController | `createDeliverable` |
| GET | `/api/v2/admin/deliverability/{id}` | AdminDeliverabilityApiController | `getDeliverable` |
| PUT | `/api/v2/admin/deliverability/{id}` | AdminDeliverabilityApiController | `updateDeliverable` |
| DELETE | `/api/v2/admin/deliverability/{id}` | AdminDeliverabilityApiController | `deleteDeliverable` |
| POST | `/api/v2/admin/deliverability/{id}/comments` | AdminDeliverabilityApiController | `addComment` |

### 1.21 Super Admin Panel (V2 API - 36 endpoints)

Defined at `/api/v2/admin/super/*`. Requires super_admin or god role.

| Method | Route | Controller | Action |
|--------|-------|------------|--------|
| GET | `.../super/dashboard` | AdminSuperApiController | `dashboard` |
| GET | `.../super/tenants` | AdminSuperApiController | `tenantList` |
| GET | `.../super/tenants/hierarchy` | AdminSuperApiController | `tenantHierarchy` |
| POST | `.../super/tenants` | AdminSuperApiController | `tenantCreate` |
| GET | `.../super/tenants/{id}` | AdminSuperApiController | `tenantShow` |
| PUT | `.../super/tenants/{id}` | AdminSuperApiController | `tenantUpdate` |
| DELETE | `.../super/tenants/{id}` | AdminSuperApiController | `tenantDelete` |
| POST | `.../super/tenants/{id}/reactivate` | AdminSuperApiController | `tenantReactivate` |
| POST | `.../super/tenants/{id}/toggle-hub` | AdminSuperApiController | `tenantToggleHub` |
| POST | `.../super/tenants/{id}/move` | AdminSuperApiController | `tenantMove` |
| GET | `.../super/users` | AdminSuperApiController | `userList` |
| POST | `.../super/users` | AdminSuperApiController | `userCreate` |
| GET | `.../super/users/{id}` | AdminSuperApiController | `userShow` |
| PUT | `.../super/users/{id}` | AdminSuperApiController | `userUpdate` |
| POST | `.../super/users/{id}/grant-super-admin` | AdminSuperApiController | `userGrantSuperAdmin` |
| POST | `.../super/users/{id}/revoke-super-admin` | AdminSuperApiController | `userRevokeSuperAdmin` |
| POST | `.../super/users/{id}/grant-global-super-admin` | AdminSuperApiController | `userGrantGlobalSuperAdmin` |
| POST | `.../super/users/{id}/revoke-global-super-admin` | AdminSuperApiController | `userRevokeGlobalSuperAdmin` |
| POST | `.../super/users/{id}/move-tenant` | AdminSuperApiController | `userMoveTenant` |
| POST | `.../super/users/{id}/move-and-promote` | AdminSuperApiController | `userMoveAndPromote` |
| POST | `.../super/bulk/move-users` | AdminSuperApiController | `bulkMoveUsers` |
| POST | `.../super/bulk/update-tenants` | AdminSuperApiController | `bulkUpdateTenants` |
| GET | `.../super/audit` | AdminSuperApiController | `audit` |
| GET | `.../super/federation` | AdminSuperApiController | `federationOverview` |
| GET | `.../super/federation/system-controls` | AdminSuperApiController | `federationGetSystemControls` |
| PUT | `.../super/federation/system-controls` | AdminSuperApiController | `federationUpdateSystemControls` |
| POST | `.../super/federation/emergency-lockdown` | AdminSuperApiController | `federationEmergencyLockdown` |
| POST | `.../super/federation/lift-lockdown` | AdminSuperApiController | `federationLiftLockdown` |
| GET | `.../super/federation/whitelist` | AdminSuperApiController | `federationGetWhitelist` |
| POST | `.../super/federation/whitelist` | AdminSuperApiController | `federationAddToWhitelist` |
| DELETE | `.../super/federation/whitelist/{tenantId}` | AdminSuperApiController | `federationRemoveFromWhitelist` |
| GET | `.../super/federation/partnerships` | AdminSuperApiController | `federationPartnerships` |
| POST | `.../super/federation/partnerships/{id}/suspend` | AdminSuperApiController | `federationSuspendPartnership` |
| POST | `.../super/federation/partnerships/{id}/terminate` | AdminSuperApiController | `federationTerminatePartnership` |
| GET | `.../super/federation/tenant/{id}/features` | AdminSuperApiController | `federationGetTenantFeatures` |
| PUT | `.../super/federation/tenant/{id}/features` | AdminSuperApiController | `federationUpdateTenantFeature` |

**V2 API Total: ~206 endpoints across 19 controllers**

---

## 2. Legacy Admin Routes

Session-based PHP admin routes at `/admin/*`, defined in `httpdocs/routes.php` lines 1478-2256. All require `AdminAuth::check()`.

### 2.1 Dashboard & Core Admin (~20 routes)

| Route Pattern | Controller | Features |
|---------------|------------|----------|
| `/admin` | AdminController@index | Dashboard home |
| `/admin/activity-log` | AdminController@activityLogs | Activity log viewer |
| `/admin/settings` | AdminController@settings | General settings |
| `/admin/settings/update` | AdminController@saveSettings | Save settings |
| `/admin/settings/save-tenant` | AdminController@saveTenantSettings | Tenant-specific settings |
| `/admin/settings/test-gmail` | AdminController@testGmailConnection | Gmail API test |
| `/admin/settings/regenerate-css` | AdminController@regenerateMinifiedCSS | CSS regeneration |
| `/admin/image-settings` | AdminController@imageSettings | Image optimization config |
| `/admin/image-settings/save` | AdminController@saveImageSettings | Save image config |
| `/admin/native-app` | AdminController@nativeApp | FCM push / PWA settings |
| `/admin/native-app/test-push` | AdminController@sendTestPush | Test push notification |
| `/admin/feed-algorithm` | AdminController@feedAlgorithm | EdgeRank tuning |
| `/admin/feed-algorithm/save` | AdminController@saveFeedAlgorithm | Save algorithm settings |
| `/admin/algorithm-settings` | AdminController@algorithmSettings | MatchRank/CommunityRank |
| `/admin/algorithm-settings/save` | AdminController@saveAlgorithmSettings | Save algorithm config |
| `/admin/api/search` | AdminController@liveSearch | Command palette search |

### 2.2 Smart Matching (~8 routes)

| Route Pattern | Controller |
|---------------|------------|
| `/admin/smart-matching` | SmartMatchingController@index |
| `/admin/smart-matching/analytics` | SmartMatchingController@analytics |
| `/admin/smart-matching/configuration` | SmartMatchingController@configuration (GET+POST) |
| `/admin/smart-matching/clear-cache` | SmartMatchingController@clearCache |
| `/admin/smart-matching/warmup-cache` | SmartMatchingController@warmupCache |
| `/admin/smart-matching/run-geocoding` | SmartMatchingController@runGeocoding |
| `/admin/smart-matching/api/stats` | SmartMatchingController@apiStats |

### 2.3 Match Approvals (~6 routes)

| Route Pattern | Controller |
|---------------|------------|
| `/admin/match-approvals` | MatchApprovalsController@index |
| `/admin/match-approvals/history` | MatchApprovalsController@history |
| `/admin/match-approvals/{id}` | MatchApprovalsController@show |
| `/admin/match-approvals/approve` | MatchApprovalsController@approve |
| `/admin/match-approvals/reject` | MatchApprovalsController@reject |
| `/admin/match-approvals/api/stats` | MatchApprovalsController@apiStats |

### 2.4 Broker Controls (~14 routes)

| Route Pattern | Controller |
|---------------|------------|
| `/admin/broker-controls` | BrokerControlsController@index |
| `/admin/broker-controls/configuration` | BrokerControlsController@configuration (GET+POST) |
| `/admin/broker-controls/exchanges` | BrokerControlsController@exchanges |
| `/admin/broker-controls/exchanges/{id}` | BrokerControlsController@showExchange |
| `/admin/broker-controls/exchanges/{id}/approve` | BrokerControlsController@approveExchange |
| `/admin/broker-controls/exchanges/{id}/reject` | BrokerControlsController@rejectExchange |
| `/admin/broker-controls/risk-tags` | BrokerControlsController@riskTags |
| `/admin/broker-controls/risk-tags/{listingId}` | BrokerControlsController@tagListing (GET+POST) |
| `/admin/broker-controls/risk-tags/{listingId}/remove` | BrokerControlsController@removeTag |
| `/admin/broker-controls/messages` | BrokerControlsController@messages |
| `/admin/broker-controls/messages/{id}/review` | BrokerControlsController@reviewMessage |
| `/admin/broker-controls/messages/{id}/flag` | BrokerControlsController@flagMessage |
| `/admin/broker-controls/monitoring` | BrokerControlsController@userMonitoring |
| `/admin/broker-controls/monitoring/{userId}` | BrokerControlsController@setMonitoring |
| `/admin/broker-controls/stats` | BrokerControlsController@stats |

### 2.5 Users (~22 routes)

| Route Pattern | Controller |
|---------------|------------|
| `/admin/users` | UserController@index |
| `/admin/users/create` | UserController@create |
| `/admin/users/store` | UserController@store |
| `/admin/users/edit/{id}` | UserController@edit |
| `/admin/users/{id}/edit` | UserController@edit (REST alias) |
| `/admin/users/{id}/permissions` | UserController@permissions |
| `/admin/users/update` | UserController@update |
| `/admin/users/delete` | UserController@delete |
| `/admin/users/suspend` | UserController@suspend |
| `/admin/users/ban` | UserController@ban |
| `/admin/users/reactivate` | UserController@reactivate |
| `/admin/users/revoke-super-admin` | UserController@revokeSuperAdmin |
| `/admin/users/{id}/reset-2fa` | UserController@reset2fa |
| `/admin/approve-user` | UserController@approve |
| `/admin/users/badges/add` | UserController@addBadge |
| `/admin/users/badges/remove` | UserController@removeBadge |
| `/admin/users/badges/recheck` | UserController@recheckBadges |
| `/admin/users/badges/bulk-award` | UserController@bulkAwardBadge |
| `/admin/users/badges/recheck-all` | UserController@recheckAllBadges |
| `/admin/impersonate` | AuthController@impersonate |
| `/admin/stop-impersonating` | AuthController@stopImpersonating (GET+POST) |

### 2.6 Listings (~3 routes)

| Route Pattern | Controller |
|---------------|------------|
| `/admin/listings` | ListingController@index |
| `/admin/listings/delete/{id}` | ListingController@delete |
| `/admin/listings/approve/{id}` | ListingController@approve |

### 2.7 Categories & Attributes (~12 routes)

| Route Pattern | Controller |
|---------------|------------|
| `/admin/categories` | CategoryController@index |
| `/admin/categories/create` | CategoryController@create |
| `/admin/categories/store` | CategoryController@store |
| `/admin/categories/edit/{id}` | CategoryController@edit |
| `/admin/categories/update` | CategoryController@update |
| `/admin/categories/delete` | CategoryController@delete |
| `/admin/attributes` | AttributeController@index |
| `/admin/attributes/create` | AttributeController@create |
| `/admin/attributes/store` | AttributeController@store |
| `/admin/attributes/edit/{id}` | AttributeController@edit |
| `/admin/attributes/update` | AttributeController@update |
| `/admin/attributes/delete` | AttributeController@delete |

### 2.8 Blog / News (~16 routes)

| Route Pattern | Controller |
|---------------|------------|
| `/admin/news` (or `/admin/blog`) | BlogController@index |
| `/admin/news/create` | BlogController@create |
| `/admin/news/edit/{id}` | BlogController@edit |
| `/admin/news/builder/{id}` | BlogController@builder |
| `/admin/news/save-builder` | BlogController@saveBuilder |
| `/admin/news/update` | BlogController@update |
| `/admin/news/delete/{id}` | BlogController@delete |
| `/admin/blog/store` | BlogController@store |
| `/admin/blog-restore` | BlogRestoreController@index |
| `/admin/blog-restore/diagnostic` | BlogRestoreController@diagnostic |
| `/admin/blog-restore/upload` | BlogRestoreController@upload |
| `/admin/blog-restore/import` | BlogRestoreController@import |
| `/admin/blog-restore/export` | BlogRestoreController@downloadExport |

### 2.9 Pages (~12 routes)

| Route Pattern | Controller |
|---------------|------------|
| `/admin/pages` | PageController@index |
| `/admin/pages/create` | PageController@create |
| `/admin/pages/builder/{id}` | PageController@builder |
| `/admin/pages/preview/{id}` | PageController@preview |
| `/admin/pages/versions/{id}` | PageController@versions |
| `/admin/pages/duplicate/{id}` | PageController@duplicate |
| `/admin/pages/version-content/{id}` | PageController@versionContent |
| `/admin/pages/save` | PageController@save |
| `/admin/pages/restore-version` | PageController@restoreVersion |
| `/admin/pages/reorder` | PageController@reorder |
| `/admin/pages/delete` | PageController@delete |
| `/admin/api/pages/{id}/blocks` | PageController@saveBlocks / getBlocks |
| `/admin/api/blocks/preview` | PageController@previewBlock |
| `/admin/api/pages/{id}/settings` | PageController@saveSettings |

### 2.10 Menus (~14 routes)

| Route Pattern | Controller |
|---------------|------------|
| `/admin/menus` | MenuController@index |
| `/admin/menus/create` | MenuController@create (GET+POST) |
| `/admin/menus/builder/{id}` | MenuController@builder |
| `/admin/menus/update/{id}` | MenuController@update |
| `/admin/menus/toggle/{id}` | MenuController@toggleActive |
| `/admin/menus/delete/{id}` | MenuController@delete |
| `/admin/menus/item/add` | MenuController@addItem |
| `/admin/menus/item/{id}` | MenuController@getItem |
| `/admin/menus/item/update/{id}` | MenuController@updateItem |
| `/admin/menus/item/delete/{id}` | MenuController@deleteItem |
| `/admin/menus/items/reorder` | MenuController@reorder |
| `/admin/menus/cache/clear` | MenuController@clearCache |
| `/admin/menus/bulk` | MenuController@bulk |

### 2.11 Plans (~8 routes)

| Route Pattern | Controller |
|---------------|------------|
| `/admin/plans` | PlanController@index |
| `/admin/plans/create` | PlanController@create (GET+POST) |
| `/admin/plans/edit/{id}` | PlanController@edit (GET+POST) |
| `/admin/plans/delete/{id}` | PlanController@delete |
| `/admin/plans/subscriptions` | PlanController@subscriptions |
| `/admin/plans/assign` | PlanController@assignPlan |
| `/admin/plans/comparison` | PlanController@comparison |

### 2.12 Legal Documents (~19 routes)

| Route Pattern | Controller |
|---------------|------------|
| `/admin/legal-documents` | LegalDocumentsController@index |
| `/admin/legal-documents/create` | LegalDocumentsController@create |
| `/admin/legal-documents/compliance` | LegalDocumentsController@compliance |
| `/admin/legal-documents/{id}` | LegalDocumentsController@show |
| `/admin/legal-documents/{id}/edit` | LegalDocumentsController@edit |
| `/admin/legal-documents/{id}` (POST) | LegalDocumentsController@update |
| `/admin/legal-documents/{id}/versions/create` | LegalDocumentsController@createVersion |
| `/admin/legal-documents/{id}/versions` (POST) | LegalDocumentsController@storeVersion |
| `/admin/legal-documents/{id}/versions/{v}` | LegalDocumentsController@showVersion |
| `/admin/legal-documents/{id}/versions/{v}/edit` | LegalDocumentsController@editVersion |
| `/admin/legal-documents/{id}/versions/{v}/publish` | LegalDocumentsController@publishVersion |
| `/admin/legal-documents/{id}/versions/{v}/delete` | LegalDocumentsController@deleteVersion |
| `/admin/legal-documents/{id}/versions/{v}/notify` | LegalDocumentsController@notifyUsers |
| `/admin/legal-documents/{id}/versions/{v}/acceptances` | LegalDocumentsController@acceptances |
| `/admin/legal-documents/{id}/compare` | LegalDocumentsController@compareVersions |
| `/admin/legal-documents/{id}/export` | LegalDocumentsController@exportAcceptances |

### 2.13 Groups (~18 routes)

| Route Pattern | Controller |
|---------------|------------|
| `/admin/groups` | GroupAdminController@index |
| `/admin/groups/analytics` | GroupAdminController@analytics |
| `/admin/groups/recommendations` | GroupAdminController@recommendations |
| `/admin/groups/view` | GroupAdminController@view |
| `/admin/groups/settings` | GroupAdminController@settings (GET+POST) |
| `/admin/groups/policies` | GroupAdminController@policies (GET+POST) |
| `/admin/groups/moderation` | GroupAdminController@moderation |
| `/admin/groups/moderate-flag` | GroupAdminController@moderateFlag |
| `/admin/groups/approvals` | GroupAdminController@approvals |
| `/admin/groups/process-approval` | GroupAdminController@processApproval |
| `/admin/groups/manage-members` | GroupAdminController@manageMembers |
| `/admin/groups/batch-operations` | GroupAdminController@batchOperations |
| `/admin/groups/export` | GroupAdminController@export |
| `/admin/groups/toggle-featured` | GroupAdminController@toggleFeatured |
| `/admin/groups/delete` | GroupAdminController@delete |
| `/admin/group-locations` | AdminController@groupLocations (GET+POST) |
| `/admin/group-ranking` | AdminController@groupRanking |
| `/admin/group-ranking/update` | AdminController@updateFeaturedGroups |
| `/admin/group-ranking/toggle` | AdminController@toggleFeaturedGroup |
| `/admin/group-types` | AdminController@groupTypes (GET+POST) |
| `/admin/group-types/create` | AdminController@groupTypeForm |
| `/admin/group-types/edit/{id}` | AdminController@groupTypeForm (GET+POST) |

### 2.14 Gamification (~17 routes)

| Route Pattern | Controller |
|---------------|------------|
| `/admin/gamification` | GamificationController@index |
| `/admin/gamification/recheck-all` | GamificationController@recheckAll |
| `/admin/gamification/bulk-award` | GamificationController@bulkAward |
| `/admin/gamification/award-all` | GamificationController@awardToAll |
| `/admin/gamification/reset-xp` | GamificationController@resetXp |
| `/admin/gamification/clear-badges` | GamificationController@clearBadges |
| `/admin/gamification/analytics` | GamificationController@analytics |
| `/admin/gamification/campaigns` | GamificationController@campaigns |
| `/admin/gamification/campaigns/create` | GamificationController@createCampaign |
| `/admin/gamification/campaigns/edit/{id}` | GamificationController@editCampaign |
| `/admin/gamification/campaigns/save` | GamificationController@saveCampaign |
| `/admin/gamification/campaigns/activate` | GamificationController@activateCampaign |
| `/admin/gamification/campaigns/pause` | GamificationController@pauseCampaign |
| `/admin/gamification/campaigns/delete` | GamificationController@deleteCampaign |
| `/admin/gamification/campaigns/run` | GamificationController@runCampaign |
| `/admin/gamification/campaigns/preview-audience` | GamificationController@previewAudience |
| `/admin/custom-badges` + CRUD (8 routes) | CustomBadgeController |

### 2.15 Timebanking (~18 routes)

| Route Pattern | Controller |
|---------------|------------|
| `/admin/timebanking` | TimebankingController@index |
| `/admin/timebanking/alerts` | TimebankingController@alerts |
| `/admin/timebanking/alert/{id}` | TimebankingController@viewAlert |
| `/admin/timebanking/alert/{id}/status` | TimebankingController@updateAlertStatus |
| `/admin/timebanking/run-detection` | TimebankingController@runDetection |
| `/admin/timebanking/user-report/{id}` | TimebankingController@userReport |
| `/admin/timebanking/user-report` | TimebankingController@userReport |
| `/admin/timebanking/adjust-balance` | TimebankingController@adjustBalance |
| `/admin/timebanking/org-wallets` | TimebankingController@orgWallets |
| `/admin/timebanking/org-wallets/initialize` | TimebankingController@initializeOrgWallet |
| `/admin/timebanking/org-wallets/initialize-all` | TimebankingController@initializeAllOrgWallets |
| `/admin/timebanking/org-members/{id}` | TimebankingController@orgMembers |
| `/admin/timebanking/org-members/add` | TimebankingController@addOrgMember |
| `/admin/timebanking/org-members/update-role` | TimebankingController@updateOrgMemberRole |
| `/admin/timebanking/org-members/remove` | TimebankingController@removeOrgMember |
| `/admin/timebanking/create-org` | TimebankingController@createOrgForm / createOrg |

### 2.16 Newsletters (~50+ routes)

The newsletter module is the largest legacy admin domain. Key sub-features:

- **Newsletter CRUD**: create, edit, preview, send, send-test, duplicate, delete
- **Stats & Analytics**: per-newsletter stats, global analytics, activity tracking
- **Subscribers**: add, delete, sync, export, import
- **Segments**: CRUD + preview + smart suggestions
- **Templates**: CRUD + duplicate + preview + save-from-newsletter + load
- **Bounce Management**: bounces, suppress, unsuppress
- **Resend to Non-Openers**: resend form + execution
- **Send Time Optimization**: recommendations + heatmap
- **Email Client Preview**: client-specific rendering
- **Diagnostics & Repair**: diagnostics dashboard, repair tool

Controller: `NewsletterController` (~50 methods)

### 2.17 Federation (~40 routes across 6 controllers)

| Controller | Features |
|------------|----------|
| FederationSettingsController | Feature toggles, partnership management (request/approve/reject/terminate/counter-propose) |
| FederationDirectoryController | Community directory, profile management, partnership requests |
| FederationAnalyticsController | Analytics dashboard, API data, CSV export |
| FederationApiKeysController | API key lifecycle (create/suspend/activate/revoke/regenerate) |
| FederationExportController | Data export (users, partnerships, transactions, audit, all) |
| FederationImportController | User import with CSV template |
| FederationExternalPartnersController | External partner CRUD (create/update/test/suspend/activate/delete) |

### 2.18 SEO (~10 routes)

| Route Pattern | Controller |
|---------------|------------|
| `/admin/seo` | SeoController@index |
| `/admin/seo/store` | SeoController@store |
| `/admin/seo/audit` | SeoController@audit |
| `/admin/seo/bulk/{type}` | SeoController@bulkEdit |
| `/admin/seo/bulk/save` | SeoController@bulkSave |
| `/admin/seo/redirects` | SeoController@redirects |
| `/admin/seo/redirects/store` | SeoController@storeRedirect |
| `/admin/seo/redirects/delete` | SeoController@deleteRedirect |
| `/admin/seo/organization` | SeoController@organization |
| `/admin/seo/organization/save` | SeoController@saveOrganization |
| `/admin/seo/ping-sitemaps` | SeoController@pingSitemaps |

### 2.19 404 Error Tracking (~10 routes)

| Route Pattern | Controller |
|---------------|------------|
| `/admin/404-errors` | Error404Controller@index |
| `/admin/404-errors/api/list` | Error404Controller@apiList |
| `/admin/404-errors/api/top` | Error404Controller@topErrors |
| `/admin/404-errors/api/stats` | Error404Controller@stats |
| `/admin/404-errors/mark-resolved` | Error404Controller@markResolved |
| `/admin/404-errors/mark-unresolved` | Error404Controller@markUnresolved |
| `/admin/404-errors/delete` | Error404Controller@delete |
| `/admin/404-errors/search` | Error404Controller@search |
| `/admin/404-errors/create-redirect` | Error404Controller@createRedirect |
| `/admin/404-errors/bulk-redirect` | Error404Controller@bulkRedirect |
| `/admin/404-errors/clean-old` | Error404Controller@cleanOld |

### 2.20 Cron Jobs (~8 routes)

| Route Pattern | Controller |
|---------------|------------|
| `/admin/cron-jobs` | CronJobController@index |
| `/admin/cron-jobs/run/{id}` | CronJobController@run |
| `/admin/cron-jobs/toggle/{id}` | CronJobController@toggle |
| `/admin/cron-jobs/logs` | CronJobController@logs |
| `/admin/cron-jobs/setup` | CronJobController@setup |
| `/admin/cron-jobs/settings` | CronJobController@settings (GET+POST) |
| `/admin/cron-jobs/clear-logs` | CronJobController@clearLogs |
| `/admin/cron-jobs/api/stats` | CronJobController@apiStats |

### 2.21 Volunteering (~6 routes)

| Route Pattern | Controller |
|---------------|------------|
| `/admin/volunteering` | VolunteeringController@index |
| `/admin/volunteering/approvals` | VolunteeringController@approvals |
| `/admin/volunteering/organizations` | VolunteeringController@organizations |
| `/admin/volunteering/approve` | VolunteeringController@approve |
| `/admin/volunteering/decline` | VolunteeringController@decline |
| `/admin/volunteering/delete` | VolunteeringController@deleteOrg |

### 2.22 Deliverability (~12 routes)

| Route Pattern | Controller |
|---------------|------------|
| `/admin/deliverability` | AdminController@deliverabilityDashboard |
| `/admin/deliverability/list` | AdminController@deliverablesList |
| `/admin/deliverability/analytics` | AdminController@deliverabilityAnalytics |
| `/admin/deliverability/create` | AdminController@deliverableCreate |
| `/admin/deliverability/store` | AdminController@deliverableStore |
| `/admin/deliverability/view/{id}` | AdminController@deliverableView |
| `/admin/deliverability/edit/{id}` | AdminController@deliverableEdit |
| `/admin/deliverability/update/{id}` | AdminController@deliverableUpdate |
| `/admin/deliverability/delete/{id}` | AdminController@deliverableDelete |
| `/admin/deliverability/ajax/update-status` | AdminController@deliverableUpdateStatus |
| `/admin/deliverability/ajax/complete-milestone` | AdminController@milestoneComplete |
| `/admin/deliverability/ajax/add-comment` | AdminController@deliverableAddComment |

### 2.23 Enterprise (~50+ routes across 9 controllers)

| Sub-domain | Controller | Features |
|------------|------------|----------|
| Dashboard | EnterpriseDashboardController | Enterprise overview |
| GDPR Requests | GdprRequestController | Request lifecycle (create/process/complete/reject/assign/notes/export/bulk) |
| GDPR Consents | GdprConsentController | Consent types, backfill, tenant versions, export |
| GDPR Breaches | GdprBreachController | Breach reporting, escalation |
| GDPR Audit | GdprAuditController | Audit log, export, compliance report |
| Monitoring | MonitoringController | Health check, requirements, logs, real-time SSE/polling |
| Config | ConfigController | Settings, cache, validation, feature toggles, reset |
| Secrets | SecretsController | Vault CRUD (store/view/rotate/delete), vault test |
| Roles/Permissions | RolesController | Role CRUD, user assignment/revocation, audit log |

### 2.24 Permissions API (~12 routes)

| Route Pattern | Controller |
|---------------|------------|
| `/admin/api/permissions/check` | PermissionApiController@checkPermission |
| `/admin/api/permissions` | PermissionApiController@getAllPermissions |
| `/admin/api/roles` | PermissionApiController@getAllRoles |
| `/admin/api/roles/{roleId}/permissions` | PermissionApiController@getRolePermissions |
| `/admin/api/users/{userId}/permissions` | PermissionApiController@getUserPermissions |
| `/admin/api/users/{userId}/roles` | PermissionApiController@getUserRoles |
| `/admin/api/users/{userId}/effective-permissions` | PermissionApiController@getUserEffectivePermissions |
| `/admin/api/users/{userId}/roles` (POST) | PermissionApiController@assignRoleToUser |
| `/admin/api/users/{userId}/roles/{roleId}` (DELETE) | PermissionApiController@revokeRoleFromUser |
| `/admin/api/users/{userId}/permissions` (POST) | PermissionApiController@grantPermissionToUser |
| `/admin/api/users/{userId}/permissions/{permissionId}` (DELETE) | PermissionApiController@revokePermissionFromUser |
| `/admin/api/audit/permissions` | PermissionApiController@getAuditLog |
| `/admin/api/stats/permissions` | PermissionApiController@getPermissionStats |

### 2.25 Other Admin Features

| Route Pattern | Controller | Feature |
|---------------|------------|---------|
| `/admin/seed-generator` | SeedGeneratorController | Demo data generation |
| `/admin/seed-generator/verification` | SeedGeneratorVerificationController | Seed verification |
| `/admin/webp-converter` | AdminController@webpConverter | Image format conversion |
| `/admin/matching-diagnostic` | MatchingDiagnosticController | Match algorithm diagnostics |
| `/admin/ai-settings` | AiSettingsController | AI provider config |
| `/admin/nexus-score/analytics` | NexusScoreController | Score analytics |
| `/admin/tests` | TestRunnerController | API test runner |

**Legacy Admin Total: ~350+ routes across 37+ controllers**

---

## 3. Super Admin Routes

Session-based routes at `/super-admin/*`, defined in `httpdocs/routes.php` lines 14-79. Require super_admin role.

### 3.1 Dashboard

| Method | Route | Controller |
|--------|-------|------------|
| GET | `/super-admin` | SuperAdmin\DashboardController@index |
| GET | `/super-admin/dashboard` | SuperAdmin\DashboardController@index |

### 3.2 Tenant Management (10 routes)

| Method | Route | Controller |
|--------|-------|------------|
| GET | `/super-admin/tenants` | TenantController@index |
| GET | `/super-admin/tenants/hierarchy` | TenantController@hierarchy |
| GET | `/super-admin/tenants/create` | TenantController@create |
| POST | `/super-admin/tenants/store` | TenantController@store |
| GET | `/super-admin/tenants/{id}` | TenantController@show |
| GET | `/super-admin/tenants/{id}/edit` | TenantController@edit |
| POST | `/super-admin/tenants/{id}/update` | TenantController@update |
| POST | `/super-admin/tenants/{id}/delete` | TenantController@delete |
| POST | `/super-admin/tenants/{id}/reactivate` | TenantController@reactivate |
| POST | `/super-admin/tenants/{id}/toggle-hub` | TenantController@toggleHub |
| POST | `/super-admin/tenants/{id}/move` | TenantController@move |

### 3.3 User Management (12 routes)

| Method | Route | Controller |
|--------|-------|------------|
| GET | `/super-admin/users` | UserController@index |
| GET | `/super-admin/users/create` | UserController@create |
| POST | `/super-admin/users/store` | UserController@store |
| GET | `/super-admin/users/{id}` | UserController@show |
| GET | `/super-admin/users/{id}/edit` | UserController@edit |
| POST | `/super-admin/users/{id}/update` | UserController@update |
| POST | `/super-admin/users/{id}/grant-super-admin` | UserController@grantSuperAdmin |
| POST | `/super-admin/users/{id}/revoke-super-admin` | UserController@revokeSuperAdmin |
| POST | `/super-admin/users/{id}/grant-global-super-admin` | UserController@grantGlobalSuperAdmin |
| POST | `/super-admin/users/{id}/revoke-global-super-admin` | UserController@revokeGlobalSuperAdmin |
| POST | `/super-admin/users/{id}/move-tenant` | UserController@moveTenant |
| POST | `/super-admin/users/{id}/move-and-promote` | UserController@moveAndPromote |

### 3.4 Bulk Operations (3 routes)

| Method | Route | Controller |
|--------|-------|------------|
| GET | `/super-admin/bulk` | BulkController@index |
| POST | `/super-admin/bulk/move-users` | BulkController@moveUsers |
| POST | `/super-admin/bulk/update-tenants` | BulkController@updateTenants |

### 3.5 Audit (1 route)

| Method | Route | Controller |
|--------|-------|------------|
| GET | `/super-admin/audit` | AuditController@index |

### 3.6 Super Admin API (5 routes)

| Method | Route | Controller |
|--------|-------|------------|
| GET | `/super-admin/api/tenants` | TenantController@apiList |
| GET | `/super-admin/api/tenants/hierarchy` | TenantController@apiHierarchy |
| GET | `/super-admin/api/users/search` | UserController@apiSearch |
| GET | `/super-admin/api/bulk/users` | BulkController@apiGetUsers |
| GET | `/super-admin/api/audit` | AuditController@apiLog |

### 3.7 Federation Controls (14 routes)

| Method | Route | Controller |
|--------|-------|------------|
| GET | `/super-admin/federation` | FederationController@index |
| GET | `/super-admin/federation/system-controls` | FederationController@systemControls |
| POST | `/super-admin/federation/update-system-controls` | FederationController@updateSystemControls |
| POST | `/super-admin/federation/emergency-lockdown` | FederationController@emergencyLockdown |
| POST | `/super-admin/federation/lift-lockdown` | FederationController@liftLockdown |
| GET | `/super-admin/federation/whitelist` | FederationController@whitelist |
| POST | `/super-admin/federation/add-to-whitelist` | FederationController@addToWhitelist |
| POST | `/super-admin/federation/remove-from-whitelist` | FederationController@removeFromWhitelist |
| GET | `/super-admin/federation/partnerships` | FederationController@partnerships |
| POST | `/super-admin/federation/suspend-partnership` | FederationController@suspendPartnership |
| POST | `/super-admin/federation/terminate-partnership` | FederationController@terminatePartnership |
| GET | `/super-admin/federation/audit` | FederationController@auditLog |
| GET | `/super-admin/federation/tenant/{id}` | FederationController@tenantFeatures |
| POST | `/super-admin/federation/update-tenant-feature` | FederationController@updateTenantFeature |

**Super Admin Total: ~47 routes across 6 controllers**

---

## 4. Controller Inventory

### 4.1 V2 API Controllers (19 files)

All in `src/Controllers/Api/`:

| Controller | Methods | Domain |
|------------|---------|--------|
| AdminDashboardApiController | 3 | Dashboard stats, trends, activity |
| AdminUsersApiController | 13 | User CRUD, status, badges, impersonation |
| AdminListingsApiController | 4 | Listing moderation |
| AdminCategoriesApiController | 8 | Categories + attributes CRUD |
| AdminConfigApiController | 20+ | Config hub: features, modules, settings, AI, feed, images, SEO, native app, cache, cron jobs |
| AdminMatchingApiController | 9 | Match config, stats, approvals |
| AdminBlogApiController | 6 | Blog CRUD + toggle |
| AdminGamificationApiController | 10 | Badges, campaigns, recheck, bulk award |
| AdminGroupsApiController | 7 | Group list, analytics, approvals, moderation |
| AdminTimebankingApiController | 6 | Stats, alerts, balance adjust, org wallets |
| AdminEnterpriseApiController | 25 | RBAC, GDPR (4 sub-domains), monitoring, config, secrets, legal docs |
| AdminBrokerApiController | 8 | Exchange approval, risk tags, message monitoring |
| AdminNewsletterApiController | 9 | Newsletter CRUD, subscribers, segments, templates, analytics |
| AdminVolunteeringApiController | 3 | Volunteering overview, approvals, organizations |
| AdminFederationApiController | 8 | Settings, partnerships, directory, analytics, API keys, data |
| AdminContentApiController | 21 | Pages, menus, menu items, plans, subscriptions |
| AdminToolsApiController | 10 | Redirects, 404s, health check, WebP, seed, blog backups |
| AdminDeliverabilityApiController | 8 | Deliverables CRUD, dashboard, analytics, comments |
| AdminSuperApiController | 36 | Super admin: tenants, users, bulk ops, audit, federation controls |

### 4.2 Legacy Admin Controllers (37 files + 9 enterprise sub-controllers)

In `src/Controllers/Admin/`:

| Controller | Domain |
|------------|--------|
| AdminController (main) | Dashboard, settings, deliverability, WebP, images, feed algo, groups management, native app, algorithm settings |
| UserController | User CRUD, badge management, status management, 2FA reset |
| ListingController | Listing CRUD, approve/delete |
| CategoryController | Category CRUD |
| AttributeController | Attribute CRUD |
| BlogController | Blog/news CRUD with visual builder |
| BlogRestoreController | Blog backup/restore/diagnostic |
| PageController | CMS pages with block builder, versions |
| MenuController | Menu builder with drag-and-drop items |
| PlanController | Subscription plans CRUD |
| LegalDocumentsController | Legal docs with version control, publishing, acceptances |
| SmartMatchingController | Matching config, analytics, cache warmup, geocoding |
| MatchApprovalsController | Broker approval workflow |
| BrokerControlsController | Exchange approval, risk tags, message review, user monitoring |
| MatchingDiagnosticController | Match algorithm diagnostics |
| GroupAdminController | Group management, analytics, moderation, approvals, policies |
| GamificationController | Badge management, campaigns, XP reset, analytics |
| CustomBadgeController | Custom badge CRUD with award/revoke |
| TimebankingController | Abuse detection, alerts, balance adjust, org wallets, org members |
| SeoController | SEO meta, redirects, organization schema, sitemap ping |
| Error404Controller | 404 tracking, resolve/redirect, bulk ops, cleanup |
| NewsletterController (~50 methods) | Full newsletter lifecycle, subscribers, segments, templates, bounces, diagnostics |
| CronJobController | Job management, run, toggle, logs, settings |
| VolunteeringController | Volunteering approvals, organization management |
| AiSettingsController | AI provider configuration, testing |
| FederationSettingsController | Federation feature toggles, partnerships |
| FederationDirectoryController | Community directory, profile |
| FederationAnalyticsController | Federation analytics, export |
| FederationApiKeysController | API key lifecycle |
| FederationExportController | Data export (users, partnerships, transactions, audit) |
| FederationImportController | User import |
| FederationExternalPartnersController | External partner management |
| SeedGeneratorController | Demo data generation, preview, download |
| SeedGeneratorVerificationController | Seed verification, live testing |
| TestRunnerController | API test runner |
| RolesController | RBAC role management |
| PermissionApiController | Permission REST API |

Enterprise sub-controllers in `src/Controllers/Admin/Enterprise/`:

| Controller | Domain |
|------------|--------|
| BaseEnterpriseController | Base class |
| EnterpriseDashboardController | Enterprise dashboard |
| GdprRequestController | GDPR request lifecycle |
| GdprConsentController | Consent management |
| GdprBreachController | Breach reporting |
| GdprAuditController | GDPR audit trail |
| MonitoringController | APM, health, logs, real-time |
| ConfigController | Enterprise configuration |
| SecretsController | Secrets vault |

### 4.3 Super Admin Controllers (6 files)

In `src/Controllers/SuperAdmin/`:

| Controller | Domain |
|------------|--------|
| DashboardController | Platform dashboard |
| TenantController | Tenant CRUD + hierarchy + hub management |
| UserController | Cross-tenant user management, super admin grants |
| BulkController | Bulk user moves, tenant updates |
| AuditController | Platform audit log |
| FederationController | Platform-wide federation, emergency lockdown, whitelist |

---

## 5. Admin View Files (Legacy)

73 PHP view files in `views/admin/`:

| Directory | Files | Purpose |
|-----------|-------|---------|
| `views/admin/` (root) | dashboard.php, settings.php, image-settings.php, webp-converter.php, native-app.php, activity_log.php | Core admin pages |
| `views/admin/users/` | index.php, edit.php | User management |
| `views/admin/listings/` | index.php | Listing management |
| `views/admin/categories/` | index.php, create.php, edit.php | Category CRUD |
| `views/admin/attributes/` | index.php, create.php, edit.php | Attribute CRUD |
| `views/admin/blog/` | index.php, form.php, builder.php | Blog/news editor |
| `views/admin/news/` | index.php | News listing (alias) |
| `views/admin/pages/` | index.php, list.php, builder.php | CMS page builder |
| `views/admin/seo/` | index.php | SEO management |
| `views/admin/404-errors/` | index.php | 404 tracking |
| `views/admin/newsletters/` | index.php, form.php, analytics.php, subscribers.php, segments.php, segment-form.php, templates.php, template-form.php, bounces.php, resend.php, send-time.php, stats.php, diagnostics.php | Full newsletter UI (13 files) |
| `views/admin/federation/` | index.php, dashboard.php, partnerships.php, api-keys.php, api-keys-create.php, api-keys-show.php, data.php, external-partners.php, external-partners-create.php, external-partners-show.php | Federation UI (10 files) |
| `views/admin/legal-documents/` | index.php, create.php, edit.php, show.php, compliance.php, acceptances.php | Legal docs (6 files) |
| `views/admin/legal-documents/versions/` | create.php, edit.php, show.php, compare.php, compare-select.php | Version management (5 files) |
| `views/admin/gamification/` | analytics.php, campaigns.php, custom-badges.php, custom-badge-form.php | Gamification (4 files) |
| `views/admin/volunteering/` | approvals.php, organizations.php | Volunteering (2 files) |
| `views/admin/enterprise/gdpr/` | request-view.php, consents.php, audit.php | Enterprise GDPR (3 files) |
| `views/admin/seed-generator/` | index.php, preview.php, verification.php | Seed generator (3 files) |
| `views/admin/test-runner/` | dashboard.php, view.php | Test runner (2 files) |
| `views/admin/partials/` | analytics_chart.php | Shared partials (1 file) |

---

## 6. Service Dependencies

Key services used by admin controllers (identified via `use` statements):

| Service | Used By |
|---------|---------|
| `GamificationService` | AdminGamificationApiController, GamificationController, UserController |
| `SmartMatchingEngine` | AdminMatchingApiController, SmartMatchingController |
| `MatchApprovalWorkflowService` | AdminMatchingApiController, MatchApprovalsController |
| `BrokerControlConfigService` | AdminBrokerApiController, BrokerControlsController |
| `ListingRiskTagService` | AdminBrokerApiController, BrokerControlsController |
| `AbuseDetectionService` | TimebankingController |
| `NewsletterService` | AdminNewsletterApiController, NewsletterController |
| `FederationPartnershipService` | AdminFederationApiController, FederationSettingsController |
| `FederationDirectoryService` | AdminFederationApiController, FederationDirectoryController |
| `FederationAuditService` | FederationAnalyticsController, FederationController (super) |
| `FederationJwtService` | FederationApiKeysController |
| `GroupApprovalWorkflowService` | AdminGroupsApiController, GroupAdminController |
| `LegalDocumentService` | AdminEnterpriseApiController, LegalDocumentsController |
| `Enterprise\GdprService` | AdminEnterpriseApiController, GdprRequestController |
| `Enterprise\ConfigService` | AdminEnterpriseApiController, ConfigController |
| `Enterprise\PermissionService` | AdminEnterpriseApiController, RolesController, PermissionApiController |
| `PayPlanService` | AdminContentApiController, PlanController |
| `TokenService` | AdminUsersApiController (impersonation) |
| `RedisCache` | AdminConfigApiController (cache clearing) |
| `AiSettings` (model) | AdminConfigApiController (AI config) |
| `ActivityLog` (model) | AdminDashboardApiController, AdminUsersApiController, AdminListingsApiController |
| `TenantHierarchyService` | AdminSuperApiController, SuperAdmin controllers |
| `AuditLogService` | AdminSuperApiController, AuditController |

---

## 7. Feature Domain Summary

Cross-reference of all admin feature domains, their controllers (V2 + Legacy), and view status:

| # | Domain | V2 API Controller | Legacy Controller(s) | Legacy Views | V2 Endpoints |
|---|--------|-------------------|----------------------|--------------|--------------|
| 1 | **Dashboard** | AdminDashboardApiController | AdminController | dashboard.php | 3 |
| 2 | **Users** | AdminUsersApiController | Admin\UserController | users/ (2) | 14 |
| 3 | **Listings** | AdminListingsApiController | Admin\ListingController, AdminController | listings/ (1) | 4 |
| 4 | **Categories** | AdminCategoriesApiController | CategoryController | categories/ (3) | 4 |
| 5 | **Attributes** | AdminCategoriesApiController | AttributeController | attributes/ (3) | 4 |
| 6 | **Config/Settings** | AdminConfigApiController | AdminController | settings.php + 3 more | 16 |
| 7 | **Cache/Jobs** | AdminConfigApiController | CronJobController | -- | 5 |
| 8 | **Matching** | AdminMatchingApiController | SmartMatchingController | -- | 9 |
| 9 | **Match Approvals** | AdminMatchingApiController | MatchApprovalsController | -- | (in matching) |
| 10 | **Broker Controls** | AdminBrokerApiController | BrokerControlsController | -- | 8 |
| 11 | **Blog** | AdminBlogApiController | BlogController, BlogRestoreController | blog/ (3), news/ (1) | 6 |
| 12 | **Gamification** | AdminGamificationApiController | GamificationController, CustomBadgeController | gamification/ (4) | 10 |
| 13 | **Groups** | AdminGroupsApiController | GroupAdminController, AdminController | -- | 7 |
| 14 | **Timebanking** | AdminTimebankingApiController | TimebankingController | -- | 6 |
| 15 | **Enterprise (RBAC)** | AdminEnterpriseApiController | RolesController, PermissionApiController | -- | 7 |
| 16 | **Enterprise (GDPR)** | AdminEnterpriseApiController | 4x GDPR controllers | enterprise/gdpr/ (3) | 6 |
| 17 | **Enterprise (Monitoring)** | AdminEnterpriseApiController | MonitoringController | -- | 3 |
| 18 | **Enterprise (Config)** | AdminEnterpriseApiController | ConfigController, SecretsController | -- | 3 |
| 19 | **Legal Documents** | AdminEnterpriseApiController | LegalDocumentsController | legal-documents/ (11) | 5 |
| 20 | **Newsletters** | AdminNewsletterApiController | NewsletterController (~50 methods) | newsletters/ (13) | 9 |
| 21 | **Volunteering** | AdminVolunteeringApiController | VolunteeringController | volunteering/ (2) | 3 |
| 22 | **Federation** | AdminFederationApiController | 6x Federation controllers | federation/ (10) | 8 |
| 23 | **Pages (CMS)** | AdminContentApiController | PageController | pages/ (3) | 5 |
| 24 | **Menus** | AdminContentApiController | MenuController | -- | 8 |
| 25 | **Plans** | AdminContentApiController | PlanController | -- | 6 |
| 26 | **Tools (SEO/Redirects)** | AdminToolsApiController | SeoController | seo/ (1) | 3 |
| 27 | **Tools (404 Errors)** | AdminToolsApiController | Error404Controller | 404-errors/ (1) | 2 |
| 28 | **Tools (WebP/Health/Seed)** | AdminToolsApiController | AdminController, SeedGeneratorController | seed-generator/ (3), webp-converter.php | 5 |
| 29 | **Deliverability** | AdminDeliverabilityApiController | AdminController | -- | 8 |
| 30 | **Cron Jobs** | AdminConfigApiController | CronJobController | -- | 2 |
| 31 | **AI Settings** | AdminConfigApiController | AiSettingsController | -- | 2 |
| 32 | **Super Admin** | AdminSuperApiController | 6x SuperAdmin controllers | -- | 36 |

### Totals

| Category | Count |
|----------|-------|
| V2 API endpoints | ~206 |
| V2 API controllers | 19 |
| Legacy admin routes | ~350+ |
| Legacy admin controllers | 37 (+ 9 enterprise sub-controllers) |
| Super admin routes (session) | ~47 |
| Super admin controllers | 6 |
| Admin view files | 73 |
| Feature domains | 32 |

---

## Notes

- The V2 API (`/api/v2/admin/*`) is the backend for the React admin panel. All endpoints use JWT auth via `$this->requireAdmin()` from `BaseApiController`.
- The legacy admin routes (`/admin/*`) use PHP session auth via `AdminAuth::check()` and render PHP views.
- The super admin panel exists in both session-based (`/super-admin/*`) and V2 API (`/api/v2/admin/super/*`) forms.
- `AdminConfigApiController` is the largest V2 controller (1565 lines, 20+ methods) serving as the config hub for 6+ sub-domains.
- `NewsletterController` is the largest legacy controller (~50 methods) covering the full newsletter lifecycle.
- Some V2 endpoints are read-only summaries of features that have full CRUD in the legacy admin (e.g., volunteering has 3 V2 read endpoints vs 6 legacy routes with approve/decline/delete).
