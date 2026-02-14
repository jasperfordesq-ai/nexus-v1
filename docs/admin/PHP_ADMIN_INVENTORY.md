# PHP Admin Panel Inventory

> **Purpose**: Complete inventory of all PHP admin modules, routes, controllers, views, and side effects. This document serves as the source of truth for migrating the admin panel to the React frontend.
>
> **Generated**: 2026-02-14
>
> **Source Files**: `httpdocs/routes.php`, `src/Controllers/Admin/`, `src/Controllers/AdminController.php`, `src/Controllers/SuperAdmin/`, `views/admin/`, `views/super-admin/`

---

## Summary

| Metric | Count |
|--------|-------|
| **Total admin routes** | 484+ |
| **Admin controllers (Admin namespace)** | 34 |
| **Super Admin controllers** | 6 |
| **Main AdminController methods** | 30+ |
| **Admin view files** | 72 |
| **Super Admin view files** | 20 |
| **Admin modules** | 30 |
| **Existing V2 admin API endpoints** | 7 (AdminConfigApiController only) |

### Controller Breakdown

| Namespace | Controllers | Notes |
|-----------|------------|-------|
| `Nexus\Controllers\AdminController` | 1 (monolith) | Dashboard, settings, deliverables, algorithm, groups, image tools |
| `Nexus\Controllers\Admin\*` | 34 files | Feature-specific controllers |
| `Nexus\Controllers\Admin\Enterprise\*` | 8 files | GDPR, monitoring, config, secrets |
| `Nexus\Controllers\SuperAdmin\*` | 6 files | Cross-tenant management |
| `Nexus\Controllers\Api\AdminConfigApiController` | 1 | Only existing V2 admin API |

---

## Module Inventory

### 1. Dashboard

| Item | Detail |
|------|--------|
| **Controller** | `AdminController@index` |
| **Route** | `GET /admin` |
| **View** | `views/admin/dashboard.php` |
| **Auth** | `checkAdmin()` -- requires admin/tenant_admin role, is_super_admin flag, or is_god flag |
| **DB Queries** | users count, listings count, transactions count, transaction volume (SUM), monthly stats (last 6 months), pending users, recent listings, recent transactions |
| **Side Effects** | None (read-only) |
| **Priority** | HIGH -- first page admins see |

---

### 2. User Management

| Item | Detail |
|------|--------|
| **Controller** | `Admin\UserController` |
| **Views** | `views/admin/users/index.php`, `views/admin/users/edit.php` |
| **Auth** | `requireAdmin()`, god-only for `revokeSuperAdmin` |

**Routes (20):**

| Method | Route | Action | Notes |
|--------|-------|--------|-------|
| GET | `/admin/users` | `index` | List with search, status filter, pagination |
| GET | `/admin/users/create` | `create` | Create user form |
| POST | `/admin/users/store` | `store` | Store new user |
| GET | `/admin/users/edit/{id}` | `edit` | Edit form with badges, permissions |
| GET | `/admin/users/{id}/edit` | `edit` | REST alias |
| GET | `/admin/users/{id}/permissions` | `permissions` | View user permissions |
| POST | `/admin/users/update` | `update` | Update user |
| POST | `/admin/users/delete` | `delete` | Delete user |
| POST | `/admin/users/suspend` | `suspend` | Suspend user |
| POST | `/admin/users/ban` | `ban` | Ban user |
| POST | `/admin/users/reactivate` | `reactivate` | Reactivate user |
| POST | `/admin/users/revoke-super-admin` | `revokeSuperAdmin` | God-only |
| POST | `/admin/users/{id}/reset-2fa` | `reset2fa` | Reset 2FA |
| POST | `/admin/approve-user` | `approve` | Approve pending user |
| POST | `/admin/users/badges/add` | `addBadge` | Add badge to user |
| POST | `/admin/users/badges/remove` | `removeBadge` | Remove badge |
| POST | `/admin/users/badges/recheck` | `recheckBadges` | Recheck single user badges |
| POST | `/admin/users/badges/bulk-award` | `bulkAwardBadge` | Bulk award badge |
| POST | `/admin/users/badges/recheck-all` | `recheckAllBadges` | Recheck all users |
| POST | `/admin/impersonate` | `AuthController@impersonate` | Impersonate user |

**Side Effects:** Emails on approve, badge awards, activity logging, session manipulation on impersonate

---

### 3. Content -- Blog / News

| Item | Detail |
|------|--------|
| **Controller** | `Admin\BlogController` |
| **Views** | `views/admin/blog/index.php`, `views/admin/blog/form.php`, `views/admin/blog/builder.php`, `views/admin/news/index.php` |
| **Auth** | `checkAdmin()` |

**Routes (14):**

| Method | Route | Action | Notes |
|--------|-------|--------|-------|
| GET | `/admin/news` | `index` | Blog list |
| GET | `/admin/news/create` | `create` | Create form |
| GET | `/admin/news/edit/{id}` | `edit` | Edit form |
| GET | `/admin/news/builder/{id}` | `builder` | GrapesJS visual builder |
| POST | `/admin/news/save-builder` | `saveBuilder` | Save builder content |
| POST | `/admin/news/update` | `update` | Update post |
| GET | `/admin/news/delete/{id}` | `delete` | Delete post |
| GET | `/admin/blog` | `index` | Legacy alias |
| GET | `/admin/blog/create` | `create` | Legacy alias |
| GET | `/admin/blog/edit/{id}` | `edit` | Legacy alias |
| GET | `/admin/blog/builder/{id}` | `builder` | Legacy alias |
| POST | `/admin/blog/store` | `store` | Store new post |
| POST | `/admin/blog/delete` | `delete` | Delete via POST |
| POST | `/admin/blog/update/{id}` | `update` | Update via POST |

**Additional: Blog Restore** (`Admin\BlogRestoreController`)

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/blog-restore` | `index` |
| GET | `/admin/blog-restore/diagnostic` | `diagnostic` |
| POST | `/admin/blog-restore/upload` | `upload` |
| POST | `/admin/blog-restore/import` | `import` |
| GET | `/admin/blog-restore/export` | `downloadExport` |

**Features:** GrapesJS visual page builder, SEO metadata, import/export, restore from backup

---

### 4. Content -- Pages (CMS)

| Item | Detail |
|------|--------|
| **Controller** | `Admin\PageController` |
| **Views** | `views/admin/pages/index.php`, `views/admin/pages/list.php`, `views/admin/pages/builder.php` |
| **Auth** | `checkAdmin()` |

**Routes (14):**

| Method | Route | Action | Notes |
|--------|-------|--------|-------|
| GET | `/admin/pages` | `index` | Page list |
| GET | `/admin/pages/create` | `create` | Create form |
| GET | `/admin/pages/builder/{id}` | `builder` | Visual page builder |
| GET | `/admin/pages/preview/{id}` | `preview` | Preview page |
| GET | `/admin/pages/versions/{id}` | `versions` | Version history |
| GET | `/admin/pages/duplicate/{id}` | `duplicate` | Duplicate page |
| GET | `/admin/pages/version-content/{id}` | `versionContent` | Get version content |
| POST | `/admin/pages/save` | `save` | Save page |
| POST | `/admin/pages/restore-version` | `restoreVersion` | Restore version |
| POST | `/admin/pages/reorder` | `reorder` | Reorder pages |
| POST | `/admin/pages/delete` | `delete` | Delete page |
| POST | `/admin/api/pages/{id}/blocks` | `saveBlocks` | Save blocks (V2 API) |
| GET | `/admin/api/pages/{id}/blocks` | `getBlocks` | Get blocks (V2 API) |
| POST | `/admin/api/pages/{id}/settings` | `saveSettings` | Save page settings |

**Features:** GrapesJS visual builder, version control (max 20), auto-save, publish scheduling, block-based API

---

### 5. Content -- Menus

| Item | Detail |
|------|--------|
| **Controller** | `Admin\MenuController` |
| **Auth** | `checkAdmin()` |

**Routes (14):**

| Method | Route | Action | Notes |
|--------|-------|--------|-------|
| GET | `/admin/menus` | `index` | Menu list |
| GET | `/admin/menus/create` | `create` | Create menu |
| POST | `/admin/menus/create` | `create` | Store menu |
| GET | `/admin/menus/builder/{id}` | `builder` | Menu builder |
| POST | `/admin/menus/update/{id}` | `update` | Update menu |
| POST | `/admin/menus/toggle/{id}` | `toggleActive` | Toggle active status |
| POST | `/admin/menus/delete/{id}` | `delete` | Delete menu |
| POST | `/admin/menus/item/add` | `addItem` | Add menu item |
| GET | `/admin/menus/item/{id}` | `getItem` | Get menu item |
| POST | `/admin/menus/item/update/{id}` | `updateItem` | Update item |
| POST | `/admin/menus/item/delete/{id}` | `deleteItem` | Delete item |
| POST | `/admin/menus/items/reorder` | `reorder` | Drag-drop reorder |
| POST | `/admin/menus/cache/clear` | `clearCache` | Clear menu cache |
| POST | `/admin/menus/bulk` | `bulk` | Bulk operations |

**Features:** Drag-drop reorder, menu items CRUD, visibility rules, cache management, bulk operations

---

### 6. Categories

| Item | Detail |
|------|--------|
| **Controller** | `Admin\CategoryController` |
| **Views** | `views/admin/categories/index.php`, `views/admin/categories/create.php`, `views/admin/categories/edit.php` |
| **Auth** | Role-based |

**Routes (5):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/categories` | `index` |
| GET | `/admin/categories/create` | `create` |
| POST | `/admin/categories/store` | `store` |
| GET | `/admin/categories/edit/{id}` | `edit` |
| POST | `/admin/categories/update` | `update` |
| POST | `/admin/categories/delete` | `delete` |

---

### 7. Attributes

| Item | Detail |
|------|--------|
| **Controller** | `Admin\AttributeController` |
| **Views** | `views/admin/attributes/index.php`, `views/admin/attributes/create.php`, `views/admin/attributes/edit.php` |
| **Auth** | Role-based |

**Routes (5):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/attributes` | `index` |
| GET | `/admin/attributes/create` | `create` |
| POST | `/admin/attributes/store` | `store` |
| GET | `/admin/attributes/edit/{id}` | `edit` |
| POST | `/admin/attributes/update` | `update` |
| POST | `/admin/attributes/delete` | `delete` |

---

### 8. Listings (Unified Content Directory)

| Item | Detail |
|------|--------|
| **Controller** | `Admin\ListingController` |
| **Views** | `views/admin/listings/index.php` |
| **Auth** | `checkAdmin()` |

**Routes (3):**

| Method | Route | Action | Notes |
|--------|-------|--------|-------|
| GET | `/admin/listings` | `index` | Unified directory: listings, events, polls, goals, resources, volunteer opps |
| POST | `/admin/listings/approve/{id}` | `approve` | Approve listing |
| POST | `/admin/listings/delete/{id}` | `delete` | Delete listing |

**Features:** Unified content directory with status filtering across multiple content types

---

### 9. Gamification

| Item | Detail |
|------|--------|
| **Controller** | `Admin\GamificationController` |
| **Views** | `views/admin/gamification/campaigns.php`, `views/admin/gamification/analytics.php`, `views/admin/gamification/custom-badges.php`, `views/admin/gamification/custom-badge-form.php` |
| **Services** | `GamificationService`, `AchievementAnalyticsService`, `AchievementCampaignService`, `ChallengeService` |
| **Auth** | `checkAdmin()` |

**Routes (16):**

| Method | Route | Action | Notes |
|--------|-------|--------|-------|
| GET | `/admin/gamification` | `index` | Dashboard with stats |
| POST | `/admin/gamification/recheck-all` | `recheckAll` | Recheck all user badges |
| POST | `/admin/gamification/bulk-award` | `bulkAward` | Bulk award badge |
| POST | `/admin/gamification/award-all` | `awardToAll` | Award to all users |
| POST | `/admin/gamification/reset-xp` | `resetXp` | Reset user XP |
| POST | `/admin/gamification/clear-badges` | `clearBadges` | Clear user badges |
| GET | `/admin/gamification/analytics` | `analytics` | Achievement analytics |
| GET | `/admin/gamification/campaigns` | `campaigns` | Campaign list |
| GET | `/admin/gamification/campaigns/create` | `createCampaign` | Create campaign form |
| GET | `/admin/gamification/campaigns/edit/{id}` | `editCampaign` | Edit campaign |
| POST | `/admin/gamification/campaigns/save` | `saveCampaign` | Save campaign |
| POST | `/admin/gamification/campaigns/activate` | `activateCampaign` | Activate campaign |
| POST | `/admin/gamification/campaigns/pause` | `pauseCampaign` | Pause campaign |
| POST | `/admin/gamification/campaigns/delete` | `deleteCampaign` | Delete campaign |
| POST | `/admin/gamification/campaigns/run` | `runCampaign` | Execute campaign |
| POST | `/admin/gamification/campaigns/preview-audience` | `previewAudience` | Preview target audience |

**Custom Badges** (`Admin\CustomBadgeController`, 9 routes):

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/custom-badges` | `index` |
| GET | `/admin/custom-badges/create` | `create` |
| POST | `/admin/custom-badges/store` | `store` |
| GET | `/admin/custom-badges/edit/{id}` | `edit` |
| POST | `/admin/custom-badges/update` | `update` |
| POST | `/admin/custom-badges/delete` | `delete` |
| POST | `/admin/custom-badges/award` | `award` |
| POST | `/admin/custom-badges/revoke` | `revoke` |
| GET | `/admin/custom-badges/awardees` | `getAwardees` |

---

### 10. Smart Matching

| Item | Detail |
|------|--------|
| **Controller** | `Admin\SmartMatchingController` |
| **Services** | `SmartMatchingEngine`, `SmartMatchingAnalyticsService` |
| **Auth** | `checkAdmin()` |

**Routes (8):**

| Method | Route | Action | Notes |
|--------|-------|--------|-------|
| GET | `/admin/smart-matching` | `index` | Dashboard |
| GET | `/admin/smart-matching/analytics` | `analytics` | Match analytics |
| GET | `/admin/smart-matching/configuration` | `configuration` | Algorithm config |
| POST | `/admin/smart-matching/configuration` | `configuration` | Save config |
| POST | `/admin/smart-matching/clear-cache` | `clearCache` | Clear match cache |
| POST | `/admin/smart-matching/warmup-cache` | `warmupCache` | Warmup cache |
| POST | `/admin/smart-matching/run-geocoding` | `runGeocoding` | Run geocoding batch |
| GET | `/admin/smart-matching/api/stats` | `apiStats` | Stats API |

**Additional:** `Admin\MatchingDiagnosticController` -- `GET /admin/matching-diagnostic`

**Features:** Algorithm weight configuration, cache management, geocoding batch processing

---

### 11. Match Approvals (Broker Workflow)

| Item | Detail |
|------|--------|
| **Controller** | `Admin\MatchApprovalsController` |
| **Services** | `MatchApprovalWorkflowService` |
| **Auth** | `checkAdmin()` |

**Routes (6):**

| Method | Route | Action | Notes |
|--------|-------|--------|-------|
| GET | `/admin/match-approvals` | `index` | Pending approvals queue |
| GET | `/admin/match-approvals/history` | `history` | Approval history |
| GET | `/admin/match-approvals/{id}` | `show` | View match detail |
| POST | `/admin/match-approvals/approve` | `approve` | Approve match |
| POST | `/admin/match-approvals/reject` | `reject` | Reject match |
| GET | `/admin/match-approvals/api/stats` | `apiStats` | Stats API endpoint |

**Features:** Bulk approve/reject, stats dashboard, broker workflow integration

---

### 12. Broker Controls

| Item | Detail |
|------|--------|
| **Controller** | `Admin\BrokerControlsController` |
| **Services** | `BrokerControlConfigService`, `ExchangeWorkflowService` |
| **Auth** | `checkAdmin()` |

**Routes (16):**

| Method | Route | Action | Notes |
|--------|-------|--------|-------|
| GET | `/admin/broker-controls` | `index` | Dashboard |
| GET | `/admin/broker-controls/configuration` | `configuration` | Config form |
| POST | `/admin/broker-controls/configuration` | `configuration` | Save config |
| GET | `/admin/broker-controls/exchanges` | `exchanges` | Exchange queue |
| GET | `/admin/broker-controls/exchanges/{id}` | `showExchange` | Exchange detail |
| POST | `/admin/broker-controls/exchanges/{id}/approve` | `approveExchange` | Approve exchange |
| POST | `/admin/broker-controls/exchanges/{id}/reject` | `rejectExchange` | Reject exchange |
| GET | `/admin/broker-controls/risk-tags` | `riskTags` | Risk tag list |
| GET | `/admin/broker-controls/risk-tags/{listingId}` | `tagListing` | Tag form |
| POST | `/admin/broker-controls/risk-tags/{listingId}` | `tagListing` | Save tags |
| POST | `/admin/broker-controls/risk-tags/{listingId}/remove` | `removeTag` | Remove tag |
| GET | `/admin/broker-controls/messages` | `messages` | Message review |
| POST | `/admin/broker-controls/messages/{id}/review` | `reviewMessage` | Mark reviewed |
| POST | `/admin/broker-controls/messages/{id}/flag` | `flagMessage` | Flag message |
| GET | `/admin/broker-controls/monitoring` | `userMonitoring` | User monitoring |
| POST | `/admin/broker-controls/monitoring/{userId}` | `setMonitoring` | Set monitoring level |
| GET | `/admin/broker-controls/stats` | `stats` | Statistics |

**Features:** Exchange approval, risk tagging, message review, user monitoring, statistics

---

### 13. Timebanking Analytics & Abuse Detection

| Item | Detail |
|------|--------|
| **Controller** | `Admin\TimebankingController` |
| **Services** | `AdminAnalyticsService`, `AbuseDetectionService` |
| **Auth** | `checkAdmin()` |

**Routes (18):**

| Method | Route | Action | Notes |
|--------|-------|--------|-------|
| GET | `/admin/timebanking` | `index` | Analytics dashboard |
| GET | `/admin/timebanking/alerts` | `alerts` | Abuse alerts |
| GET | `/admin/timebanking/alert/{id}` | `viewAlert` | Alert detail |
| POST | `/admin/timebanking/alert/{id}/status` | `updateAlertStatus` | Update alert status |
| POST | `/admin/timebanking/run-detection` | `runDetection` | Run abuse detection |
| GET | `/admin/timebanking/user-report/{id}` | `userReport` | User activity report |
| GET | `/admin/timebanking/user-report` | `userReport` | User report (no ID) |
| POST | `/admin/timebanking/adjust-balance` | `adjustBalance` | Admin balance adjust |
| GET | `/admin/timebanking/org-wallets` | `orgWallets` | Org wallets list |
| POST | `/admin/timebanking/org-wallets/initialize` | `initializeOrgWallet` | Init single org wallet |
| POST | `/admin/timebanking/org-wallets/initialize-all` | `initializeAllOrgWallets` | Init all org wallets |
| GET | `/admin/timebanking/org-members/{id}` | `orgMembers` | Org members |
| POST | `/admin/timebanking/org-members/add` | `addOrgMember` | Add org member |
| POST | `/admin/timebanking/org-members/update-role` | `updateOrgMemberRole` | Update member role |
| POST | `/admin/timebanking/org-members/remove` | `removeOrgMember` | Remove member |
| GET | `/admin/timebanking/create-org` | `createOrgForm` | Create org form |
| POST | `/admin/timebanking/create-org` | `createOrg` | Store org |
| GET | `/api/admin/users/search` | `userSearchApi` | User search API |

**Side Effects:** Balance adjustments, abuse detection triggers, org wallet initialization

---

### 14. Volunteering Admin

| Item | Detail |
|------|--------|
| **Controller** | `Admin\VolunteeringController` |
| **Views** | `views/admin/volunteering/approvals.php`, `views/admin/volunteering/organizations.php` |
| **Auth** | `checkAdmin()` |

**Routes (6):**

| Method | Route | Action | Notes |
|--------|-------|--------|-------|
| GET | `/admin/volunteering` | `index` | Overview |
| GET | `/admin/volunteering/approvals` | `approvals` | Pending org approvals |
| GET | `/admin/volunteering/organizations` | `organizations` | Org directory |
| POST | `/admin/volunteering/approve` | `approve` | Approve org (sends email) |
| POST | `/admin/volunteering/decline` | `decline` | Decline org (sends email) |
| POST | `/admin/volunteering/delete` | `deleteOrg` | Delete organization |

**Side Effects:** Email notifications on approve/decline

---

### 15. Newsletters

| Item | Detail |
|------|--------|
| **Controller** | `Admin\NewsletterController` |
| **Views** | 13 view files in `views/admin/newsletters/` |
| **Auth** | `checkAdmin()` |

**Routes (69):**

**Core CRUD (14):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/newsletters` | `index` |
| GET | `/admin/newsletters/create` | `create` |
| POST | `/admin/newsletters/store` | `store` |
| GET | `/admin/newsletters/edit/{id}` | `edit` |
| POST | `/admin/newsletters/update/{id}` | `update` |
| GET | `/admin/newsletters/preview/{id}` | `preview` |
| POST | `/admin/newsletters/send/{id}` | `send` |
| GET | `/admin/newsletters/send-direct/{id}` | `sendDirect` |
| POST | `/admin/newsletters/send-test/{id}` | `sendTest` |
| POST | `/admin/newsletters/delete` | `delete` |
| GET | `/admin/newsletters/duplicate/{id}` | `duplicate` |
| GET | `/admin/newsletters/stats/{id}` | `stats` |
| GET | `/admin/newsletters/activity/{id}` | `activity` |
| GET | `/admin/newsletters/analytics` | `analytics` |

**A/B Testing (1):**

| Method | Route | Action |
|--------|-------|--------|
| POST | `/admin/newsletters/select-winner/{id}` | `selectWinner` |

**AJAX (2):**

| Method | Route | Action |
|--------|-------|--------|
| POST | `/admin/newsletters/get-recipient-count` | `getRecipientCount` |
| POST | `/admin/newsletters/preview-recipients` | `previewRecipients` |

**Subscribers (6):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/newsletters/subscribers` | `subscribers` |
| POST | `/admin/newsletters/subscribers/add` | `addSubscriber` |
| POST | `/admin/newsletters/subscribers/delete` | `deleteSubscriber` |
| POST | `/admin/newsletters/subscribers/sync` | `syncMembers` |
| GET | `/admin/newsletters/subscribers/export` | `exportSubscribers` |
| POST | `/admin/newsletters/subscribers/import` | `importSubscribers` |

**Segments (8):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/newsletters/segments` | `segments` |
| GET | `/admin/newsletters/segments/create` | `createSegment` |
| POST | `/admin/newsletters/segments/store` | `storeSegment` |
| GET | `/admin/newsletters/segments/edit/{id}` | `editSegment` |
| POST | `/admin/newsletters/segments/update/{id}` | `updateSegment` |
| POST | `/admin/newsletters/segments/delete` | `deleteSegment` |
| POST | `/admin/newsletters/segments/preview` | `previewSegment` |
| GET | `/admin/newsletters/segments/suggestions` | `getSmartSuggestions` |
| POST | `/admin/newsletters/segments/from-suggestion` | `createFromSuggestion` |

**Templates (10):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/newsletters/templates` | `templates` |
| GET | `/admin/newsletters/templates/create` | `createTemplate` |
| POST | `/admin/newsletters/templates/store` | `storeTemplate` |
| GET | `/admin/newsletters/templates/edit/{id}` | `editTemplate` |
| POST | `/admin/newsletters/templates/update/{id}` | `updateTemplate` |
| POST | `/admin/newsletters/templates/delete` | `deleteTemplate` |
| GET | `/admin/newsletters/templates/duplicate/{id}` | `duplicateTemplate` |
| GET | `/admin/newsletters/templates/preview/{id}` | `previewTemplate` |
| POST | `/admin/newsletters/save-as-template` | `saveAsTemplate` |
| GET | `/admin/newsletters/get-templates` | `getTemplates` |
| GET | `/admin/newsletters/load-template/{id}` | `loadTemplate` |

**Bounces (2):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/newsletters/bounces` | `bounces` |
| POST | `/admin/newsletters/unsuppress` | `unsuppress` |
| POST | `/admin/newsletters/suppress` | `suppress` |

**Resend (3):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/newsletters/resend/{id}` | `resendForm` |
| POST | `/admin/newsletters/resend/{id}` | `resend` |
| GET | `/admin/newsletters/resend-info/{id}` | `getResendInfo` |

**Send Time Optimization (3):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/newsletters/send-time` | `sendTimeOptimization` |
| GET | `/admin/newsletters/send-time-recommendations` | `getSendTimeRecommendations` |
| GET | `/admin/newsletters/send-time-heatmap` | `getSendTimeHeatmap` |

**Client Preview (1):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/newsletters/client-preview/{id}` | `getEmailClientPreview` |

**Diagnostics (2):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/newsletters/diagnostics` | `diagnostics` |
| POST | `/admin/newsletters/repair` | `repair` |

**Features:** Template library, audience segments, send time optimization, A/B testing, bounce management, subscriber import/export, smart segment suggestions, email client preview, diagnostics & repair

---

### 16. Federation (Admin)

| Item | Detail |
|------|--------|
| **Controllers** | `Admin\FederationSettingsController`, `Admin\FederationDirectoryController`, `Admin\FederationAnalyticsController`, `Admin\FederationApiKeysController`, `Admin\FederationExportController`, `Admin\FederationImportController`, `Admin\FederationExternalPartnersController`, `FederationAdminController` |
| **Views** | 10 view files in `views/admin/federation/` |
| **Auth** | `checkAdmin()` |

**Routes (43):**

**Settings & Dashboard (14):**

| Method | Route | Action | Controller |
|--------|-------|--------|-----------|
| GET | `/admin/federation` | `index` | FederationSettingsController |
| GET | `/admin/federation/dashboard` | `index` | FederationAdminController |
| POST | `/admin/federation/dashboard/toggle` | `toggleFederation` | FederationAdminController |
| POST | `/admin/federation/dashboard/settings` | `updateSettings` | FederationAdminController |
| POST | `/admin/federation/update-feature` | `updateFeature` | FederationSettingsController |
| GET | `/admin/federation/partnerships` | `partnerships` | FederationSettingsController |
| POST | `/admin/federation/request-partnership` | `requestPartnership` | FederationSettingsController |
| POST | `/admin/federation/approve-partnership` | `approvePartnership` | FederationSettingsController |
| POST | `/admin/federation/reject-partnership` | `rejectPartnership` | FederationSettingsController |
| POST | `/admin/federation/update-partnership-permissions` | `updatePartnershipPermissions` | FederationSettingsController |
| POST | `/admin/federation/terminate-partnership` | `terminatePartnership` | FederationSettingsController |
| POST | `/admin/federation/counter-propose` | `counterPropose` | FederationSettingsController |
| POST | `/admin/federation/accept-counter-proposal` | `acceptCounterProposal` | FederationSettingsController |
| POST | `/admin/federation/withdraw-request` | `withdrawRequest` | FederationSettingsController |

**Directory (6):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/federation/directory` | `index` |
| GET | `/admin/federation/directory/api` | `api` |
| GET | `/admin/federation/directory/profile` | `profile` |
| POST | `/admin/federation/directory/update-profile` | `updateProfile` |
| POST | `/admin/federation/directory/request-partnership` | `requestPartnership` |
| GET | `/admin/federation/directory/{id}` | `show` |

**Analytics (3):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/federation/analytics` | `index` |
| GET | `/admin/federation/analytics/api` | `api` |
| GET | `/admin/federation/analytics/export` | `export` |

**API Keys (8):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/federation/api-keys` | `index` |
| GET | `/admin/federation/api-keys/create` | `create` |
| POST | `/admin/federation/api-keys/store` | `store` |
| GET | `/admin/federation/api-keys/{id}` | `show` |
| POST | `/admin/federation/api-keys/{id}/suspend` | `suspend` |
| POST | `/admin/federation/api-keys/{id}/activate` | `activate` |
| POST | `/admin/federation/api-keys/{id}/revoke` | `revoke` |
| POST | `/admin/federation/api-keys/{id}/regenerate` | `regenerate` |

**Data Import/Export (7):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/federation/data` | `index` |
| GET | `/admin/federation/export/users` | `exportUsers` |
| GET | `/admin/federation/export/partnerships` | `exportPartnerships` |
| GET | `/admin/federation/export/transactions` | `exportTransactions` |
| GET | `/admin/federation/export/audit` | `exportAudit` |
| GET | `/admin/federation/export/all` | `exportAll` |
| POST | `/admin/federation/import/users` | `importUsers` |
| GET | `/admin/federation/import/template` | `downloadTemplate` |

**External Partners (9):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/federation/external-partners` | `index` |
| GET | `/admin/federation/external-partners/create` | `create` |
| POST | `/admin/federation/external-partners/store` | `store` |
| GET | `/admin/federation/external-partners/{id}` | `show` |
| POST | `/admin/federation/external-partners/{id}/update` | `update` |
| POST | `/admin/federation/external-partners/{id}/test` | `test` |
| POST | `/admin/federation/external-partners/{id}/suspend` | `suspend` |
| POST | `/admin/federation/external-partners/{id}/activate` | `activate` |
| POST | `/admin/federation/external-partners/{id}/delete` | `delete` |

---

### 17. Enterprise Features

| Item | Detail |
|------|--------|
| **Controllers** | `Admin\Enterprise\EnterpriseDashboardController`, `Admin\Enterprise\GdprRequestController`, `Admin\Enterprise\GdprConsentController`, `Admin\Enterprise\GdprBreachController`, `Admin\Enterprise\GdprAuditController`, `Admin\Enterprise\MonitoringController`, `Admin\Enterprise\ConfigController`, `Admin\Enterprise\SecretsController`, `Admin\RolesController`, `Admin\PermissionApiController` |
| **Views** | `views/admin/enterprise/gdpr/` (3 files) |
| **Auth** | `checkAdmin()` / enterprise checks |

**Routes (65):**

**Dashboard (1):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/enterprise` | `dashboard` |

**GDPR Requests (11):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/enterprise/gdpr` | `dashboard` |
| GET | `/admin/enterprise/gdpr/requests` | `index` |
| GET | `/admin/enterprise/gdpr/requests/new` | `create` |
| GET | `/admin/enterprise/gdpr/requests/create` | `create` |
| POST | `/admin/enterprise/gdpr/requests` | `store` |
| GET | `/admin/enterprise/gdpr/requests/{id}` | `show` |
| POST | `/admin/enterprise/gdpr/requests/{id}/process` | `process` |
| POST | `/admin/enterprise/gdpr/requests/{id}/complete` | `complete` |
| POST | `/admin/enterprise/gdpr/requests/{id}/reject` | `reject` |
| POST | `/admin/enterprise/gdpr/requests/{id}/assign` | `assign` |
| POST | `/admin/enterprise/gdpr/requests/{id}/notes` | `addNote` |
| POST | `/admin/enterprise/gdpr/requests/{id}/generate-export` | `generateExport` |
| POST | `/admin/enterprise/gdpr/requests/bulk-process` | `bulkProcess` |

**GDPR Consents (7):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/enterprise/gdpr/consents` | `index` |
| POST | `/admin/enterprise/gdpr/consents/types` | `storeType` |
| POST | `/admin/enterprise/gdpr/consents/backfill` | `backfill` |
| GET | `/admin/enterprise/gdpr/consents/tenant-versions` | `getTenantVersions` |
| POST | `/admin/enterprise/gdpr/consents/tenant-version` | `updateTenantVersion` |
| DELETE | `/admin/enterprise/gdpr/consents/tenant-version/{slug}` | `removeTenantVersion` |
| GET | `/admin/enterprise/gdpr/consents/{id}` | `show` |
| GET | `/admin/enterprise/gdpr/consents/export` | `export` |

**GDPR Breaches (5):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/enterprise/gdpr/breaches` | `index` |
| GET | `/admin/enterprise/gdpr/breaches/report` | `create` |
| POST | `/admin/enterprise/gdpr/breaches` | `store` |
| GET | `/admin/enterprise/gdpr/breaches/{id}` | `show` |
| POST | `/admin/enterprise/gdpr/breaches/{id}/escalate` | `escalate` |

**GDPR Audit (3):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/enterprise/gdpr/audit` | `index` |
| GET | `/admin/enterprise/gdpr/audit/export` | `export` |
| POST | `/admin/enterprise/gdpr/export-report` | `complianceReport` |

**Monitoring & APM (8):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/enterprise/monitoring` | `dashboard` |
| GET | `/admin/enterprise/monitoring/health` | `healthCheck` |
| GET | `/admin/enterprise/monitoring/requirements` | `requirements` |
| GET | `/admin/enterprise/monitoring/logs` | `logs` |
| GET | `/admin/enterprise/monitoring/logs/download` | `logsDownload` |
| POST | `/admin/enterprise/monitoring/logs/clear` | `logsClear` |
| GET | `/admin/enterprise/monitoring/logs/{filename}` | `logView` |
| GET | `/admin/api/realtime` | `realtimeStream` |
| GET | `/admin/api/realtime/poll` | `realtimePoll` |

**Configuration (7):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/enterprise/config` | `dashboard` |
| POST | `/admin/enterprise/config/settings/{group}/{key}` | `updateSetting` |
| GET | `/admin/enterprise/config/export` | `export` |
| POST | `/admin/enterprise/config/cache/clear` | `clearCache` |
| GET | `/admin/enterprise/config/validate` | `validate` |
| PATCH | `/admin/enterprise/config/features/{key}` | `toggleFeature` |
| POST | `/admin/enterprise/config/features/reset` | `resetFeatures` |

**Secrets & Vault (5):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/enterprise/config/secrets` | `index` |
| POST | `/admin/enterprise/config/secrets` | `store` |
| POST | `/admin/enterprise/config/secrets/{key}/value` | `view` |
| POST | `/admin/enterprise/config/secrets/{key}/rotate` | `rotate` |
| DELETE | `/admin/enterprise/config/secrets/{key}` | `delete` |
| GET | `/admin/enterprise/config/vault/test` | `testVault` |

**Roles & Permissions (13):**

| Method | Route | Action | Controller |
|--------|-------|--------|-----------|
| GET | `/admin/enterprise/roles` | `index` | RolesController |
| GET | `/admin/enterprise/permissions` | `permissions` | RolesController |
| GET | `/admin/enterprise/roles/create` | `create` | RolesController |
| POST | `/admin/enterprise/roles` | `store` | RolesController |
| GET | `/admin/enterprise/audit/permissions` | `auditLog` | RolesController |
| GET | `/admin/enterprise/roles/{id}` | `show` | RolesController |
| GET | `/admin/enterprise/roles/{id}/edit` | `edit` | RolesController |
| PATCH | `/admin/enterprise/roles/{id}` | `update` | RolesController |
| PUT | `/admin/enterprise/roles/{id}` | `update` | RolesController |
| DELETE | `/admin/enterprise/roles/{id}` | `destroy` | RolesController |
| POST | `/admin/enterprise/roles/{id}/users/{userId}` | `assignToUser` | RolesController |
| DELETE | `/admin/enterprise/roles/{id}/users/{userId}` | `revokeFromUser` | RolesController |

**Permission API (12):**

| Method | Route | Action | Controller |
|--------|-------|--------|-----------|
| GET | `/admin/api/permissions/check` | `checkPermission` | PermissionApiController |
| GET | `/admin/api/permissions` | `getAllPermissions` | PermissionApiController |
| GET | `/admin/api/roles` | `getAllRoles` | PermissionApiController |
| GET | `/admin/api/roles/{roleId}/permissions` | `getRolePermissions` | PermissionApiController |
| GET | `/admin/api/users/{userId}/permissions` | `getUserPermissions` | PermissionApiController |
| GET | `/admin/api/users/{userId}/roles` | `getUserRoles` | PermissionApiController |
| GET | `/admin/api/users/{userId}/effective-permissions` | `getUserEffectivePermissions` | PermissionApiController |
| POST | `/admin/api/users/{userId}/roles` | `assignRoleToUser` | PermissionApiController |
| DELETE | `/admin/api/users/{userId}/roles/{roleId}` | `revokeRoleFromUser` | PermissionApiController |
| POST | `/admin/api/users/{userId}/permissions` | `grantPermissionToUser` | PermissionApiController |
| DELETE | `/admin/api/users/{userId}/permissions/{permissionId}` | `revokePermissionFromUser` | PermissionApiController |
| GET | `/admin/api/audit/permissions` | `getAuditLog` | PermissionApiController |
| GET | `/admin/api/stats/permissions` | `getPermissionStats` | PermissionApiController |

---

### 18. Legal Documents

| Item | Detail |
|------|--------|
| **Controller** | `Admin\LegalDocumentsController` |
| **Views** | 9 view files in `views/admin/legal-documents/` |
| **Auth** | `checkAdmin()` |

**Routes (19):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/legal-documents` | `index` |
| GET | `/admin/legal-documents/create` | `create` |
| POST | `/admin/legal-documents` | `store` |
| GET | `/admin/legal-documents/compliance` | `compliance` |
| GET | `/admin/legal-documents/{id}` | `show` |
| GET | `/admin/legal-documents/{id}/edit` | `edit` |
| POST | `/admin/legal-documents/{id}` | `update` |
| GET | `/admin/legal-documents/{id}/versions/create` | `createVersion` |
| POST | `/admin/legal-documents/{id}/versions` | `storeVersion` |
| GET | `/admin/legal-documents/{id}/versions/{versionId}` | `showVersion` |
| GET | `/admin/legal-documents/{id}/versions/{versionId}/edit` | `editVersion` |
| POST | `/admin/legal-documents/{id}/versions/{versionId}` | `updateVersion` |
| POST | `/admin/legal-documents/{id}/versions/{versionId}/publish` | `publishVersion` |
| POST | `/admin/legal-documents/{id}/versions/{versionId}/delete` | `deleteVersion` |
| POST | `/admin/legal-documents/{id}/versions/{versionId}/notify` | `notifyUsers` |
| GET | `/admin/legal-documents/{id}/versions/{versionId}/acceptances` | `acceptances` |
| GET | `/admin/legal-documents/{id}/compare` | `compareVersions` |
| GET | `/admin/legal-documents/{id}/export` | `exportAcceptances` |

**Features:** Version control, compliance dashboard, acceptance tracking, user notifications, version comparison, export

---

### 19. SEO Management

| Item | Detail |
|------|--------|
| **Controller** | `Admin\SeoController` |
| **Views** | `views/admin/seo/index.php` |
| **Auth** | `checkAdmin()` |

**Routes (10):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/seo` | `index` |
| POST | `/admin/seo/store` | `store` |
| GET | `/admin/seo/audit` | `audit` |
| GET | `/admin/seo/bulk/{type}` | `bulkEdit` |
| POST | `/admin/seo/bulk/save` | `bulkSave` |
| GET | `/admin/seo/redirects` | `redirects` |
| POST | `/admin/seo/redirects/store` | `storeRedirect` |
| POST | `/admin/seo/redirects/delete` | `deleteRedirect` |
| GET | `/admin/seo/organization` | `organization` |
| POST | `/admin/seo/organization/save` | `saveOrganization` |
| POST | `/admin/seo/ping-sitemaps` | `pingSitemaps` |

**Features:** Global settings, audit scoring, bulk editing, redirects, organization schema, sitemap ping

---

### 20. 404 Error Tracking

| Item | Detail |
|------|--------|
| **Controller** | `Admin\Error404Controller` |
| **Views** | `views/admin/404-errors/index.php` |
| **Auth** | `checkAdmin()` |

**Routes (11):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/404-errors` | `index` |
| GET | `/admin/404-errors/api/list` | `apiList` |
| GET | `/admin/404-errors/api/top` | `topErrors` |
| GET | `/admin/404-errors/api/stats` | `stats` |
| POST | `/admin/404-errors/mark-resolved` | `markResolved` |
| POST | `/admin/404-errors/mark-unresolved` | `markUnresolved` |
| POST | `/admin/404-errors/delete` | `delete` |
| GET | `/admin/404-errors/search` | `search` |
| POST | `/admin/404-errors/create-redirect` | `createRedirect` |
| POST | `/admin/404-errors/bulk-redirect` | `bulkRedirect` |
| POST | `/admin/404-errors/clean-old` | `cleanOld` |

**Features:** Error tracking, redirect creation from 404s, bulk operations, cleanup

---

### 21. Plans & Pricing

| Item | Detail |
|------|--------|
| **Controller** | `Admin\PlanController` |
| **Auth** | `checkAdmin()` |

**Routes (7):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/plans` | `index` |
| GET | `/admin/plans/create` | `create` |
| POST | `/admin/plans/create` | `create` (store) |
| GET | `/admin/plans/edit/{id}` | `edit` |
| POST | `/admin/plans/edit/{id}` | `edit` (update) |
| POST | `/admin/plans/delete/{id}` | `delete` |
| GET | `/admin/plans/subscriptions` | `subscriptions` |
| POST | `/admin/plans/assign` | `assignPlan` |
| GET | `/admin/plans/comparison` | `comparison` |

**Features:** Plan CRUD, subscription management, plan comparison, plan assignment

---

### 22. Cron Job Manager

| Item | Detail |
|------|--------|
| **Controller** | `Admin\CronJobController` |
| **Auth** | `checkAdmin()` |

**Routes (9):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/cron-jobs` | `index` |
| POST | `/admin/cron-jobs/run/{id}` | `run` |
| POST | `/admin/cron-jobs/toggle/{id}` | `toggle` |
| GET | `/admin/cron-jobs/logs` | `logs` |
| GET | `/admin/cron-jobs/setup` | `setup` |
| GET | `/admin/cron-jobs/settings` | `settings` |
| POST | `/admin/cron-jobs/settings` | `saveSettings` |
| POST | `/admin/cron-jobs/clear-logs` | `clearLogs` |
| GET | `/admin/cron-jobs/api/stats` | `apiStats` |

**Features:** Job listing, manual trigger, logs, settings, clear logs, stats API

---

### 23. AI Settings

| Item | Detail |
|------|--------|
| **Controller** | `Admin\AiSettingsController` |
| **Auth** | `checkAdmin()` |

**Routes (4):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/ai-settings` | `index` |
| POST | `/admin/ai-settings/save` | `save` |
| POST | `/admin/ai-settings/test` | `testProvider` |
| POST | `/admin/ai-settings/initialize` | `initialize` |

**Features:** AI provider config (OpenAI, etc.), test connection, initialize AI subsystem

---

### 24. Seed Generator

| Item | Detail |
|------|--------|
| **Controllers** | `Admin\SeedGeneratorController`, `Admin\SeedGeneratorVerificationController` |
| **Views** | `views/admin/seed-generator/index.php`, `views/admin/seed-generator/preview.php`, `views/admin/seed-generator/verification.php` |
| **Auth** | `checkAdmin()` |

**Routes (6):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/seed-generator` | `index` |
| GET | `/admin/seed-generator/verification` | `index` (Verification) |
| POST | `/admin/seed-generator/generate-production` | `generateProduction` |
| POST | `/admin/seed-generator/generate-demo` | `generateDemo` |
| GET | `/admin/seed-generator/preview` | `preview` |
| GET | `/admin/seed-generator/download` | `download` |
| GET | `/admin/seed-generator/test` | `runLiveTest` |

**Features:** Production/demo data generation, preview, download, verification, live testing

---

### 25. Deliverables Tracking

| Item | Detail |
|------|--------|
| **Controller** | `AdminController` (methods on main controller) |
| **Auth** | `checkAdmin()` |

**Routes (9):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/deliverability` | `deliverabilityDashboard` |
| GET | `/admin/deliverability/list` | `deliverablesList` |
| GET | `/admin/deliverability/analytics` | `deliverabilityAnalytics` |
| GET | `/admin/deliverability/create` | `deliverableCreate` |
| POST | `/admin/deliverability/store` | `deliverableStore` |
| GET | `/admin/deliverability/view/{id}` | `deliverableView` |
| GET | `/admin/deliverability/edit/{id}` | `deliverableEdit` |
| POST | `/admin/deliverability/update/{id}` | `deliverableUpdate` |
| POST | `/admin/deliverability/delete/{id}` | `deliverableDelete` |
| POST | `/admin/deliverability/ajax/update-status` | `deliverableUpdateStatus` |
| POST | `/admin/deliverability/ajax/complete-milestone` | `milestoneComplete` |
| POST | `/admin/deliverability/ajax/add-comment` | `deliverableAddComment` |

**Features:** Deliverable tracking, milestones, comments, analytics, status updates

---

### 26. Groups Admin

| Item | Detail |
|------|--------|
| **Controller** | `Admin\GroupAdminController` |
| **Auth** | `checkAdmin()` |

**Routes (16):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/groups` | `index` |
| GET | `/admin/groups/analytics` | `analytics` |
| GET | `/admin/groups/recommendations` | `recommendations` |
| GET | `/admin/groups/view` | `view` |
| GET | `/admin/groups/settings` | `settings` |
| POST | `/admin/groups/settings` | `saveSettings` |
| GET | `/admin/groups/policies` | `policies` |
| POST | `/admin/groups/policies` | `savePolicies` |
| GET | `/admin/groups/moderation` | `moderation` |
| POST | `/admin/groups/moderate-flag` | `moderateFlag` |
| GET | `/admin/groups/approvals` | `approvals` |
| POST | `/admin/groups/process-approval` | `processApproval` |
| POST | `/admin/groups/manage-members` | `manageMembers` |
| POST | `/admin/groups/batch-operations` | `batchOperations` |
| GET | `/admin/groups/export` | `export` |
| POST | `/admin/groups/toggle-featured` | `toggleFeatured` |
| POST | `/admin/groups/delete` | `delete` |

**Features:** Group analytics, recommendations, moderation, approval queue, batch operations, export, featured toggle

---

### 27. Settings & Configuration

| Item | Detail |
|------|--------|
| **Controller** | `AdminController` (methods on main controller) |
| **Views** | `views/admin/settings.php` |
| **Auth** | `checkAdmin()` |

**Routes (5):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/settings` | `settings` |
| POST | `/admin/settings/update` | `saveSettings` |
| POST | `/admin/settings/save-tenant` | `saveTenantSettings` |
| POST | `/admin/settings/test-gmail` | `testGmailConnection` |
| POST | `/admin/settings/regenerate-css` | `regenerateMinifiedCSS` |

**Features:** Tenant settings, Gmail API test, CSS regeneration

---

### 28. Algorithm & Feed Settings

| Item | Detail |
|------|--------|
| **Controller** | `AdminController` (methods on main controller) |
| **Auth** | `checkAdmin()` |

**Routes (4):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/feed-algorithm` | `feedAlgorithm` |
| POST | `/admin/feed-algorithm/save` | `saveFeedAlgorithm` |
| GET | `/admin/algorithm-settings` | `algorithmSettings` |
| POST | `/admin/algorithm-settings/save` | `saveAlgorithmSettings` |

**Features:** Feed algorithm (EdgeRank) config, algorithm weight settings (MatchRank for listings, CommunityRank for members)

---

### 29. Test Runner

| Item | Detail |
|------|--------|
| **Controller** | `Admin\TestRunnerController` |
| **Views** | `views/admin/test-runner/dashboard.php`, `views/admin/test-runner/view.php` |
| **Auth** | `checkAdmin()` |

**Routes (3):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/tests` | `index` |
| POST | `/admin/tests/run` | `runTests` |
| GET | `/admin/tests/view` | `viewRun` |

---

### 30. Image & WebP Tools

| Item | Detail |
|------|--------|
| **Controller** | `AdminController` (methods on main controller) |
| **Views** | `views/admin/webp-converter.php`, `views/admin/image-settings.php` |
| **Auth** | `checkAdmin()` |

**Routes (4):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/webp-converter` | `webpConverter` |
| POST | `/admin/webp-converter/convert` | `webpConvertBatch` |
| GET | `/admin/image-settings` | `imageSettings` |
| POST | `/admin/image-settings/save` | `saveImageSettings` |

---

### 31. Miscellaneous Admin Routes

**Activity Log:**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/activity-log` | `AdminController@activityLogs` |

**Group Location Tools:**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/group-locations` | `AdminController@groupLocations` |
| POST | `/admin/group-locations` | `AdminController@groupLocations` |
| GET | `/admin/geocode-groups` | `AdminController@geocodeGroups` |

**Group Ranking:**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/group-ranking` | `AdminController@groupRanking` |
| POST | `/admin/group-ranking/update` | `AdminController@updateFeaturedGroups` |
| POST | `/admin/group-ranking/toggle` | `AdminController@toggleFeaturedGroup` |

**Group Types:**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/group-types` | `AdminController@groupTypes` |
| POST | `/admin/group-types` | `AdminController@groupTypes` |
| GET | `/admin/group-types/create` | `AdminController@groupTypeForm` |
| GET | `/admin/group-types/edit/{id}` | `AdminController@groupTypeForm` |
| POST | `/admin/group-types/edit/{id}` | `AdminController@groupTypeForm` |

**Native App:**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/native-app` | `AdminController@nativeApp` |
| POST | `/admin/native-app/test-push` | `AdminController@sendTestPush` |

**Nexus Score:**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/nexus-score/analytics` | `NexusScoreController@adminAnalytics` |

**Live Search:**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/admin/api/search` | `AdminController@liveSearch` |

---

## Super Admin Panel

### 32. Super Admin

| Item | Detail |
|------|--------|
| **Controllers** | `SuperAdmin\DashboardController`, `SuperAdmin\TenantController`, `SuperAdmin\UserController`, `SuperAdmin\BulkController`, `SuperAdmin\AuditController`, `SuperAdmin\FederationController` |
| **Views** | 20 files in `views/super-admin/` |
| **Auth** | `requireSuperAdmin()` -- requires `is_super_admin` or `is_god` flag |

**Routes (31):**

**Dashboard (2):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/super-admin` | `DashboardController@index` |
| GET | `/super-admin/dashboard` | `DashboardController@index` |

**Tenant Management (10):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/super-admin/tenants` | `TenantController@index` |
| GET | `/super-admin/tenants/hierarchy` | `TenantController@hierarchy` |
| GET | `/super-admin/tenants/create` | `TenantController@create` |
| POST | `/super-admin/tenants/store` | `TenantController@store` |
| GET | `/super-admin/tenants/{id}` | `TenantController@show` |
| GET | `/super-admin/tenants/{id}/edit` | `TenantController@edit` |
| POST | `/super-admin/tenants/{id}/update` | `TenantController@update` |
| POST | `/super-admin/tenants/{id}/delete` | `TenantController@delete` |
| POST | `/super-admin/tenants/{id}/reactivate` | `TenantController@reactivate` |
| POST | `/super-admin/tenants/{id}/toggle-hub` | `TenantController@toggleHub` |
| POST | `/super-admin/tenants/{id}/move` | `TenantController@move` |

**User Management (8):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/super-admin/users` | `UserController@index` |
| GET | `/super-admin/users/create` | `UserController@create` |
| POST | `/super-admin/users/store` | `UserController@store` |
| GET | `/super-admin/users/{id}` | `UserController@show` |
| GET | `/super-admin/users/{id}/edit` | `UserController@edit` |
| POST | `/super-admin/users/{id}/update` | `UserController@update` |
| POST | `/super-admin/users/{id}/grant-super-admin` | `UserController@grantSuperAdmin` |
| POST | `/super-admin/users/{id}/revoke-super-admin` | `UserController@revokeSuperAdmin` |
| POST | `/super-admin/users/{id}/grant-global-super-admin` | `UserController@grantGlobalSuperAdmin` |
| POST | `/super-admin/users/{id}/revoke-global-super-admin` | `UserController@revokeGlobalSuperAdmin` |
| POST | `/super-admin/users/{id}/move-tenant` | `UserController@moveTenant` |
| POST | `/super-admin/users/{id}/move-and-promote` | `UserController@moveAndPromote` |

**Bulk Operations (3):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/super-admin/bulk` | `BulkController@index` |
| POST | `/super-admin/bulk/move-users` | `BulkController@moveUsers` |
| POST | `/super-admin/bulk/update-tenants` | `BulkController@updateTenants` |

**Audit (1):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/super-admin/audit` | `AuditController@index` |

**API (5):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/super-admin/api/tenants` | `TenantController@apiList` |
| GET | `/super-admin/api/tenants/hierarchy` | `TenantController@apiHierarchy` |
| GET | `/super-admin/api/users/search` | `UserController@apiSearch` |
| GET | `/super-admin/api/bulk/users` | `BulkController@apiGetUsers` |
| GET | `/super-admin/api/audit` | `AuditController@apiLog` |

### 33. Super Admin Federation

**Routes (14):**

| Method | Route | Action |
|--------|-------|--------|
| GET | `/super-admin/federation` | `FederationController@index` |
| GET | `/super-admin/federation/system-controls` | `FederationController@systemControls` |
| POST | `/super-admin/federation/update-system-controls` | `FederationController@updateSystemControls` |
| POST | `/super-admin/federation/emergency-lockdown` | `FederationController@emergencyLockdown` |
| POST | `/super-admin/federation/lift-lockdown` | `FederationController@liftLockdown` |
| GET | `/super-admin/federation/whitelist` | `FederationController@whitelist` |
| POST | `/super-admin/federation/add-to-whitelist` | `FederationController@addToWhitelist` |
| POST | `/super-admin/federation/remove-from-whitelist` | `FederationController@removeFromWhitelist` |
| GET | `/super-admin/federation/partnerships` | `FederationController@partnerships` |
| POST | `/super-admin/federation/suspend-partnership` | `FederationController@suspendPartnership` |
| POST | `/super-admin/federation/terminate-partnership` | `FederationController@terminatePartnership` |
| GET | `/super-admin/federation/audit` | `FederationController@auditLog` |
| GET | `/super-admin/federation/tenant/{id}` | `FederationController@tenantFeatures` |
| POST | `/super-admin/federation/update-tenant-feature` | `FederationController@updateTenantFeature` |

---

## View Files Inventory

### Admin Views (72 files)

```
views/admin/
  dashboard.php
  settings.php
  activity_log.php
  image-settings.php
  webp-converter.php
  native-app.php
  404-errors/index.php
  attributes/index.php, create.php, edit.php
  blog/index.php, form.php, builder.php
  categories/index.php, create.php, edit.php
  enterprise/gdpr/request-view.php, consents.php, audit.php
  federation/index.php, dashboard.php, partnerships.php,
    api-keys.php, api-keys-create.php, api-keys-show.php,
    data.php, external-partners.php, external-partners-create.php,
    external-partners-show.php
  gamification/campaigns.php, analytics.php, custom-badges.php,
    custom-badge-form.php
  legal-documents/index.php, create.php, edit.php, show.php,
    compliance.php, acceptances.php,
    versions/create.php, show.php, edit.php, compare-select.php, compare.php
  listings/index.php
  newsletters/index.php, form.php, analytics.php, stats.php,
    subscribers.php, segments.php, segment-form.php,
    templates.php, template-form.php, bounces.php,
    resend.php, send-time.php, diagnostics.php
  news/index.php
  pages/index.php, list.php, builder.php
  partials/analytics_chart.php
  seed-generator/index.php, preview.php, verification.php
  seo/index.php
  test-runner/dashboard.php, view.php
  users/index.php, edit.php
  volunteering/approvals.php, organizations.php
```

### Super Admin Views (20 files)

```
views/super-admin/
  dashboard.php
  partials/header.php, footer.php
  tenants/index.php, hierarchy.php, create.php, show.php, edit.php
  users/index.php, create.php, show.php, edit.php
  bulk/index.php
  audit/index.php
  federation/index.php, system-controls.php, partnerships.php,
    audit-log.php, tenant-features.php, whitelist.php
```

---

## Auth Patterns

| Pattern | Usage | Notes |
|---------|-------|-------|
| `checkAdmin()` | `AdminController` monolith | Session-based: checks `user_role`, `is_super_admin`, `is_admin`, `is_god` |
| `requireAdmin()` | `Admin\*` controllers | Calls `checkAdmin()` internally |
| `requireSuperAdmin()` | `SuperAdmin\*` controllers | Requires `is_super_admin` or `is_god` flag |
| `$this->requireAdmin()` | `AdminConfigApiController` (V2) | Token-based: uses `ApiAuth::authenticate()` + role check |
| `requireEnterprise()` | Enterprise controllers | Admin check + enterprise feature flag |

**For React migration:** All admin APIs must use token-based auth (`ApiAuth::authenticate()`) with admin role verification, matching the pattern established in `AdminConfigApiController`.

---

## Migration Priority

| Priority | Module | Reason |
|----------|--------|--------|
| P0 (Critical) | Dashboard, Users, Settings | Core admin functionality |
| P1 (High) | Listings, Categories, Gamification, Tenant Features | Day-to-day management |
| P2 (Medium) | Blog, Pages, Newsletters, Groups | Content management |
| P3 (Lower) | Federation, Enterprise, Legal Docs, SEO | Advanced features |
| P4 (Deferred) | Seed Generator, Test Runner, Image Tools, Deliverables | Dev/maintenance tools |
| Super Admin | All Super Admin routes | Separate UI scope, likely its own section |
