# PHP Admin — Delete-Ready Checklist

> **Purpose:** Lists every legacy PHP admin file that can be safely deleted once the React admin panel at `react-frontend/src/admin/` reaches full functional parity.
>
> **Last updated:** 2026-02-14
>
> **Status overview:** The React admin panel currently has **4 fully built modules** (Dashboard, Users, Listings, Tenant Features) and **~60 placeholder routes** pointing back to the legacy PHP admin. All placeholders must be replaced with real React implementations before any Phase 2 deletions.

---

## Prerequisites

Before deleting ANY files, ensure ALL of the following are true:

- [ ] All React admin pages are fully functional (no `AdminPlaceholder` components remain)
- [ ] All V2 API endpoints (`/api/v2/admin/*`) are tested and verified in production
- [ ] Production is running React admin exclusively for all admin tasks
- [ ] Full backup of the codebase has been taken (git tag recommended)
- [ ] Legacy admin routes in `httpdocs/routes.php` have been removed or redirected
- [ ] The `AdminPlaceholder` component's "Open in Legacy Admin" links are no longer needed

---

## Phase 1: Safe to Delete Now (No React Dependency)

These are purely presentational files — admin views, admin-specific JS, and admin-specific CSS. They have **no shared business logic** and are only consumed by the legacy PHP admin UI. They can be deleted as soon as a backup is taken, since the React admin does not use them.

### Admin View Templates (`views/admin/`)

| File | Used by legacy controller |
|------|---------------------------|
| `views/admin/dashboard.php` | `AdminController@dashboard` |
| `views/admin/settings.php` | `AdminController@settings` |
| `views/admin/activity_log.php` | `AdminController@activityLogs` |
| `views/admin/image-settings.php` | `AdminController@imageSettings` |
| `views/admin/webp-converter.php` | `AdminController@webpConverter` |
| `views/admin/native-app.php` | `AdminController@nativeApp` |
| `views/admin/users/index.php` | `Admin\UserController@index` |
| `views/admin/users/edit.php` | `Admin\UserController@edit` |
| `views/admin/listings/index.php` | `Admin\ListingController@index` |
| `views/admin/categories/index.php` | `Admin\CategoryController@index` |
| `views/admin/categories/create.php` | `Admin\CategoryController@create` |
| `views/admin/categories/edit.php` | `Admin\CategoryController@edit` |
| `views/admin/attributes/index.php` | `Admin\AttributeController@index` |
| `views/admin/attributes/create.php` | `Admin\AttributeController@create` |
| `views/admin/attributes/edit.php` | `Admin\AttributeController@edit` |
| `views/admin/blog/index.php` | `Admin\BlogController@index` |
| `views/admin/blog/form.php` | `Admin\BlogController@create/edit` |
| `views/admin/blog/builder.php` | `Admin\BlogController@builder` |
| `views/admin/news/index.php` | `Admin\BlogController@index` (alias) |
| `views/admin/pages/index.php` | `Admin\PageController@index` |
| `views/admin/pages/list.php` | `Admin\PageController@index` (variant) |
| `views/admin/pages/builder.php` | `Admin\PageController@builder` |
| `views/admin/seo/index.php` | `Admin\SeoController@index` |
| `views/admin/404-errors/index.php` | `Admin\Error404Controller@index` |
| `views/admin/newsletters/index.php` | `Admin\NewsletterController@index` |
| `views/admin/newsletters/form.php` | `Admin\NewsletterController@create/edit` |
| `views/admin/newsletters/stats.php` | `Admin\NewsletterController@stats` |
| `views/admin/newsletters/analytics.php` | `Admin\NewsletterController@analytics` |
| `views/admin/newsletters/subscribers.php` | `Admin\NewsletterController@subscribers` |
| `views/admin/newsletters/segments.php` | `Admin\NewsletterController@segments` |
| `views/admin/newsletters/segment-form.php` | `Admin\NewsletterController@createSegment/editSegment` |
| `views/admin/newsletters/templates.php` | `Admin\NewsletterController@templates` |
| `views/admin/newsletters/template-form.php` | `Admin\NewsletterController@createTemplate/editTemplate` |
| `views/admin/newsletters/bounces.php` | `Admin\NewsletterController@bounces` |
| `views/admin/newsletters/resend.php` | `Admin\NewsletterController@resendForm` |
| `views/admin/newsletters/send-time.php` | `Admin\NewsletterController@sendTimeOptimization` |
| `views/admin/newsletters/diagnostics.php` | `Admin\NewsletterController@diagnostics` |
| `views/admin/gamification/analytics.php` | `Admin\GamificationController@analytics` |
| `views/admin/gamification/campaigns.php` | `Admin\GamificationController@campaigns` |
| `views/admin/gamification/custom-badges.php` | `Admin\CustomBadgeController@index` |
| `views/admin/gamification/custom-badge-form.php` | `Admin\CustomBadgeController@create/edit` |
| `views/admin/volunteering/approvals.php` | `Admin\VolunteeringController@approvals` |
| `views/admin/volunteering/organizations.php` | `Admin\VolunteeringController@organizations` |
| `views/admin/federation/index.php` | `Admin\FederationSettingsController@index` |
| `views/admin/federation/partnerships.php` | `Admin\FederationSettingsController@partnerships` |
| `views/admin/federation/dashboard.php` | `FederationAdminController@index` |
| `views/admin/federation/api-keys.php` | `Admin\FederationApiKeysController@index` |
| `views/admin/federation/api-keys-create.php` | `Admin\FederationApiKeysController@create` |
| `views/admin/federation/api-keys-show.php` | `Admin\FederationApiKeysController@show` |
| `views/admin/federation/data.php` | `Admin\FederationExportController@index` |
| `views/admin/federation/external-partners.php` | `Admin\FederationExternalPartnersController@index` |
| `views/admin/federation/external-partners-create.php` | `Admin\FederationExternalPartnersController@create` |
| `views/admin/federation/external-partners-show.php` | `Admin\FederationExternalPartnersController@show` |
| `views/admin/legal-documents/index.php` | `Admin\LegalDocumentsController@index` |
| `views/admin/legal-documents/show.php` | `Admin\LegalDocumentsController@show` |
| `views/admin/legal-documents/create.php` | `Admin\LegalDocumentsController@create` |
| `views/admin/legal-documents/edit.php` | `Admin\LegalDocumentsController@edit` |
| `views/admin/legal-documents/compliance.php` | `Admin\LegalDocumentsController@compliance` |
| `views/admin/legal-documents/acceptances.php` | `Admin\LegalDocumentsController@acceptances` |
| `views/admin/legal-documents/versions/create.php` | `Admin\LegalDocumentsController@createVersion` |
| `views/admin/legal-documents/versions/show.php` | `Admin\LegalDocumentsController@showVersion` |
| `views/admin/legal-documents/versions/edit.php` | `Admin\LegalDocumentsController@editVersion` |
| `views/admin/legal-documents/versions/compare-select.php` | `Admin\LegalDocumentsController@compareVersions` |
| `views/admin/legal-documents/versions/compare.php` | `Admin\LegalDocumentsController@compareVersions` |
| `views/admin/seed-generator/index.php` | `Admin\SeedGeneratorController@index` |
| `views/admin/seed-generator/preview.php` | `Admin\SeedGeneratorController@preview` |
| `views/admin/seed-generator/verification.php` | `Admin\SeedGeneratorVerificationController@index` |
| `views/admin/test-runner/dashboard.php` | `Admin\TestRunnerController@index` |
| `views/admin/test-runner/view.php` | `Admin\TestRunnerController@viewRun` |
| `views/admin/enterprise/gdpr/request-view.php` | `Admin\Enterprise\GdprRequestController@show` |
| `views/admin/enterprise/gdpr/consents.php` | `Admin\Enterprise\GdprConsentController@index` |
| `views/admin/enterprise/gdpr/audit.php` | `Admin\Enterprise\GdprAuditController@index` |

### Admin View Partials and Layouts

| File | Purpose |
|------|---------|
| `views/admin/partials/analytics_chart.php` | Shared chart partial for admin analytics views |
| `views/layouts/admin-header.php` | Admin page header layout |
| `views/layouts/admin-footer.php` | Admin page footer layout |
| `views/layouts/admin-page-header.php` | Admin page header with breadcrumbs |

### Admin JavaScript (`httpdocs/assets/js/`)

| File | Purpose |
|------|---------|
| `httpdocs/assets/js/admin-federation.js` | Federation admin page interactions |
| `httpdocs/assets/js/admin-search-modal.js` | Admin search overlay functionality |
| `httpdocs/assets/js/admin-sidebar.js` | Admin sidebar navigation toggle/collapse |

### Admin CSS (`httpdocs/assets/css/`)

| File | Purpose |
|------|---------|
| `httpdocs/assets/css/admin-federation.css` | Federation admin page styles |
| `httpdocs/assets/css/admin-federation.min.css` | Minified version |
| `httpdocs/assets/css/admin-gold-standard.css` | Admin gold-standard theme styles |
| `httpdocs/assets/css/admin-gold-standard.min.css` | Minified version |
| `httpdocs/assets/css/admin-header.css` | Admin header styles |
| `httpdocs/assets/css/admin-header.min.css` | Minified version |
| `httpdocs/assets/css/admin-menu-builder.css` | Menu builder page styles |
| `httpdocs/assets/css/admin-menu-builder.min.css` | Minified version |
| `httpdocs/assets/css/admin-menu-index.css` | Menu index page styles |
| `httpdocs/assets/css/admin-menu-index.min.css` | Minified version |
| `httpdocs/assets/css/admin-settings.css` | Admin settings page styles |
| `httpdocs/assets/css/admin-settings.min.css` | Minified version |
| `httpdocs/assets/css/admin-sidebar.css` | Admin sidebar navigation styles |
| `httpdocs/assets/css/admin-sidebar.min.css` | Minified version |

**Phase 1 total: 73 view files + 3 layout files + 3 JS files + 14 CSS files = 93 files**

---

## Phase 2: Delete After Full React Migration

These are the legacy PHP admin controllers. Each one must have a **fully functional React replacement** before deletion. The table maps each legacy controller to its React replacement status.

### Monolithic Admin Controllers (Non-Namespaced)

These controllers live in `src/Controllers/` (not in the `Admin/` subdirectory) but handle admin routes.

| Legacy PHP Controller | Admin Routes | React Replacement | Status |
|----------------------|--------------|-------------------|--------|
| `src/Controllers/AdminController.php` | `/admin/settings`, `/admin/activity-log`, `/admin/listings`, `/admin/image-settings`, `/admin/webp-converter`, `/admin/native-app`, `/admin/feed-algorithm`, `/admin/algorithm-settings`, `/admin/deliverability/*`, `/admin/group-*`, `/admin/smart-match-*`, `/admin/cron/*` | Multiple React modules needed | Placeholder |
| `src/Controllers/FederationAdminController.php` | `/admin/federation/dashboard` | `react-frontend/src/admin/modules/federation/` | Placeholder |
| `src/Controllers/NexusScoreController.php` | `/admin/nexus-score/analytics` | `react-frontend/src/admin/modules/nexus-score/` | Placeholder |

> **Note:** `AdminController.php` is a large monolithic controller handling many unrelated features. Consider splitting its business logic into services before deletion, as some methods may contain logic not yet extracted into dedicated services.

### Namespaced Admin Controllers (`src/Controllers/Admin/`)

| Legacy PHP Controller | Admin Routes | React Replacement | Status |
|----------------------|--------------|-------------------|--------|
| `src/Controllers/Admin/UserController.php` | `/admin/users/*` | `react-frontend/src/admin/modules/users/UserList.tsx` | **Built** (V2 API: `AdminUsersApiController`) |
| `src/Controllers/Admin/ListingController.php` | `/admin/listings/*` | `react-frontend/src/admin/modules/listings/ListingsAdmin.tsx` | **Built** (V2 API: `AdminListingsApiController`) |
| `src/Controllers/Admin/BlogController.php` | `/admin/blog/*`, `/admin/news/*` | Needs React module | Placeholder |
| `src/Controllers/Admin/BlogRestoreController.php` | `/admin/blog-restore/*` | Needs React module | Placeholder |
| `src/Controllers/Admin/CategoryController.php` | `/admin/categories/*` | Needs React module | Placeholder |
| `src/Controllers/Admin/AttributeController.php` | `/admin/attributes/*` | Needs React module | Placeholder |
| `src/Controllers/Admin/PageController.php` | `/admin/pages/*` | Needs React module | Placeholder |
| `src/Controllers/Admin/MenuController.php` | `/admin/menus/*` | Needs React module | Placeholder |
| `src/Controllers/Admin/SeoController.php` | `/admin/seo/*` | Needs React module | Placeholder |
| `src/Controllers/Admin/Error404Controller.php` | `/admin/404-errors/*` | Needs React module | Placeholder |
| `src/Controllers/Admin/NewsletterController.php` | `/admin/newsletters/*` | Needs React module | Placeholder |
| `src/Controllers/Admin/GamificationController.php` | `/admin/gamification/*` | Needs React module | Placeholder |
| `src/Controllers/Admin/CustomBadgeController.php` | `/admin/custom-badges/*` | Needs React module | Placeholder |
| `src/Controllers/Admin/TimebankingController.php` | `/admin/timebanking/*` | Needs React module | Placeholder |
| `src/Controllers/Admin/SmartMatchingController.php` | `/admin/smart-matching/*` | Needs React module | Placeholder |
| `src/Controllers/Admin/MatchApprovalsController.php` | `/admin/match-approvals/*` | Needs React module | Placeholder |
| `src/Controllers/Admin/BrokerControlsController.php` | `/admin/broker-controls/*` | Needs React module | Placeholder |
| `src/Controllers/Admin/MatchingDiagnosticController.php` | `/admin/matching-diagnostic` | Needs React module | Placeholder |
| `src/Controllers/Admin/VolunteeringController.php` | `/admin/volunteering/*` | Needs React module | Placeholder |
| `src/Controllers/Admin/GroupAdminController.php` | `/admin/groups/*` | Needs React module | Placeholder |
| `src/Controllers/Admin/PlanController.php` | `/admin/plans/*` | Needs React module | Placeholder |
| `src/Controllers/Admin/AiSettingsController.php` | `/admin/ai-settings/*` | Needs React module | Placeholder |
| `src/Controllers/Admin/CronJobController.php` | `/admin/cron-jobs/*` | Needs React module | Placeholder |
| `src/Controllers/Admin/LegalDocumentsController.php` | `/admin/legal-documents/*` | Needs React module | Placeholder |
| `src/Controllers/Admin/RolesController.php` | `/admin/enterprise/roles/*`, `/admin/enterprise/permissions` | Needs React module | Placeholder |
| `src/Controllers/Admin/PermissionApiController.php` | `/admin/api/permissions/*`, `/admin/api/roles/*`, `/admin/api/users/*/permissions` | Needs React module (or migrate to V2 API) | Placeholder |
| `src/Controllers/Admin/TestRunnerController.php` | `/admin/tests/*` | Needs React module | Placeholder |
| `src/Controllers/Admin/SeedGeneratorController.php` | `/admin/seed-generator/*` | Needs React module | Placeholder |
| `src/Controllers/Admin/SeedGeneratorVerificationController.php` | `/admin/seed-generator/verification`, `/admin/seed-generator/test` | Needs React module | Placeholder |
| `src/Controllers/Admin/EnterpriseController.php` | (enterprise routing) | Needs React module | Placeholder |

### Enterprise Admin Controllers (`src/Controllers/Admin/Enterprise/`)

| Legacy PHP Controller | Admin Routes | React Replacement | Status |
|----------------------|--------------|-------------------|--------|
| `src/Controllers/Admin/Enterprise/BaseEnterpriseController.php` | (base class for enterprise controllers) | N/A (base class) | See note below |
| `src/Controllers/Admin/Enterprise/EnterpriseDashboardController.php` | `/admin/enterprise` | Needs React module | Placeholder |
| `src/Controllers/Admin/Enterprise/GdprRequestController.php` | `/admin/enterprise/gdpr/*` | Needs React module | Placeholder |
| `src/Controllers/Admin/Enterprise/GdprConsentController.php` | `/admin/enterprise/gdpr/consents/*` | Needs React module | Placeholder |
| `src/Controllers/Admin/Enterprise/GdprBreachController.php` | `/admin/enterprise/gdpr/breaches/*` | Needs React module | Placeholder |
| `src/Controllers/Admin/Enterprise/GdprAuditController.php` | `/admin/enterprise/gdpr/audit/*` | Needs React module | Placeholder |
| `src/Controllers/Admin/Enterprise/MonitoringController.php` | `/admin/enterprise/monitoring/*` | Needs React module | Placeholder |
| `src/Controllers/Admin/Enterprise/ConfigController.php` | `/admin/enterprise/config/*` | Needs React module | Placeholder |
| `src/Controllers/Admin/Enterprise/SecretsController.php` | `/admin/enterprise/config/secrets/*` | Needs React module | Placeholder |

### Federation Admin Controllers (`src/Controllers/Admin/`)

| Legacy PHP Controller | Admin Routes | React Replacement | Status |
|----------------------|--------------|-------------------|--------|
| `src/Controllers/Admin/FederationSettingsController.php` | `/admin/federation`, `/admin/federation/partnerships` | Needs React module | Placeholder |
| `src/Controllers/Admin/FederationDirectoryController.php` | `/admin/federation/directory/*` | Needs React module | Placeholder |
| `src/Controllers/Admin/FederationAnalyticsController.php` | `/admin/federation/analytics/*` | Needs React module | Placeholder |
| `src/Controllers/Admin/FederationApiKeysController.php` | `/admin/federation/api-keys/*` | Needs React module | Placeholder |
| `src/Controllers/Admin/FederationExportController.php` | `/admin/federation/data`, `/admin/federation/export/*` | Needs React module | Placeholder |
| `src/Controllers/Admin/FederationImportController.php` | `/admin/federation/import/*` | Needs React module | Placeholder |
| `src/Controllers/Admin/FederationExternalPartnersController.php` | `/admin/federation/external-partners/*` | Needs React module | Placeholder |

**Phase 2 total: 3 monolithic controllers + 29 namespaced controllers + 9 enterprise controllers + 7 federation controllers = 48 PHP controllers**

### Route Cleanup Required

When deleting Phase 2 controllers, also remove their corresponding route definitions from `httpdocs/routes.php`:

- All `/admin/*` routes pointing to `Nexus\Controllers\Admin\*` controllers
- All `/admin/*` routes pointing to `Nexus\Controllers\AdminController`
- All `/admin/*` routes pointing to `Nexus\Controllers\FederationAdminController`
- The `/admin/nexus-score/analytics` route pointing to `Nexus\Controllers\NexusScoreController`
- All `/admin/api/*` routes (legacy admin API endpoints, NOT `/api/v2/admin/*`)

---

## Phase 3: Keep (Shared Dependencies)

These files **must NOT be deleted** even after full React migration because they contain business logic, services, or APIs that the React admin depends on.

### `src/Controllers/Admin/PermissionApiController.php`

> **Evaluate during migration.** This controller provides permission/role API endpoints at `/admin/api/permissions/*` and `/admin/api/roles/*`. If the React admin replaces these with V2 API equivalents, this can move to Phase 2. If the React admin calls these endpoints directly, it must be kept.

### `src/Controllers/Admin/Enterprise/BaseEnterpriseController.php`

> **Evaluate during migration.** This is a base class for all enterprise admin controllers. It can only be deleted after ALL enterprise controllers in Phase 2 have been deleted. If it contains shared utility methods used by V2 API controllers, extract those first.

---

## DO NOT DELETE

These are **V2 API controllers** used by the React admin panel. They are the **new** backend and must be preserved.

| File | Purpose | React Consumer |
|------|---------|----------------|
| `src/Controllers/Api/AdminConfigApiController.php` | V2 Admin config, features, modules, cache, jobs API | `TenantFeatures.tsx`, admin settings |
| `src/Controllers/Api/AdminDashboardApiController.php` | V2 Admin dashboard stats, trends, activity API | `AdminDashboard.tsx` |
| `src/Controllers/Api/AdminUsersApiController.php` | V2 Admin user CRUD, approve/suspend/ban/reactivate API | `UserList.tsx` |
| `src/Controllers/Api/AdminListingsApiController.php` | V2 Admin listing management, approve/delete API | `ListingsAdmin.tsx` |

### V2 API Routes (DO NOT DELETE from `httpdocs/routes.php`)

```
/api/v2/admin/dashboard/stats
/api/v2/admin/dashboard/trends
/api/v2/admin/dashboard/activity
/api/v2/admin/users (GET, POST)
/api/v2/admin/users/{id} (GET, PUT, DELETE)
/api/v2/admin/users/{id}/approve
/api/v2/admin/users/{id}/suspend
/api/v2/admin/users/{id}/ban
/api/v2/admin/users/{id}/reactivate
/api/v2/admin/users/{id}/reset-2fa
/api/v2/admin/listings (GET)
/api/v2/admin/listings/{id} (GET, DELETE)
/api/v2/admin/listings/{id}/approve
/api/v2/admin/config (GET)
/api/v2/admin/config/features (PUT)
/api/v2/admin/config/modules (PUT)
/api/v2/admin/cache/stats (GET)
/api/v2/admin/cache/clear (POST)
/api/v2/admin/jobs (GET)
/api/v2/admin/jobs/{id}/run (POST)
```

### React Admin Files (DO NOT DELETE)

All files under `react-frontend/src/admin/` are the active replacement admin panel:

```
react-frontend/src/admin/AdminLayout.tsx
react-frontend/src/admin/AdminRoute.tsx
react-frontend/src/admin/routes.tsx
react-frontend/src/admin/api/adminApi.ts
react-frontend/src/admin/api/types.ts
react-frontend/src/admin/components/AdminBreadcrumbs.tsx
react-frontend/src/admin/components/AdminHeader.tsx
react-frontend/src/admin/components/AdminSidebar.tsx
react-frontend/src/admin/components/ConfirmModal.tsx
react-frontend/src/admin/components/DataTable.tsx
react-frontend/src/admin/components/EmptyState.tsx
react-frontend/src/admin/components/PageHeader.tsx
react-frontend/src/admin/components/StatCard.tsx
react-frontend/src/admin/components/index.ts
react-frontend/src/admin/modules/AdminPlaceholder.tsx
react-frontend/src/admin/modules/config/TenantFeatures.tsx
react-frontend/src/admin/modules/dashboard/AdminDashboard.tsx
react-frontend/src/admin/modules/listings/ListingsAdmin.tsx
react-frontend/src/admin/modules/users/UserList.tsx
```

---

## Migration Progress Summary

| Category | Total | Built in React | Placeholder | % Complete |
|----------|-------|----------------|-------------|------------|
| Dashboard | 1 | 1 | 0 | 100% |
| Users | 1 | 1 | 0 | 100% |
| Listings | 1 | 1 | 0 | 100% |
| Tenant Features | 1 | 1 | 0 | 100% |
| Content (Blog, Pages, Menus, Categories, Attributes) | 5 | 0 | 5 | 0% |
| Engagement (Gamification, Badges, Campaigns) | 3 | 0 | 3 | 0% |
| Matching & Broker (Smart Matching, Approvals, Broker Controls) | 4 | 0 | 4 | 0% |
| Marketing (Newsletters) | 1 | 0 | 1 | 0% |
| Advanced (AI, Feed, SEO, 404s) | 4 | 0 | 4 | 0% |
| Financial (Timebanking, Plans) | 2 | 0 | 2 | 0% |
| Enterprise (Dashboard, Roles, GDPR, Monitoring, Config, Secrets) | 6 | 0 | 6 | 0% |
| Federation (Settings, Directory, Analytics, Keys, Data, Partners) | 6 | 0 | 6 | 0% |
| System (Settings, Cron, Activity Log, Tests, Seed, WebP, Images, App) | 8 | 0 | 8 | 0% |
| Community (Groups, Volunteering) | 2 | 0 | 2 | 0% |
| Other (Legal Documents, Blog Restore, Deliverability, Diagnostics, Nexus Score) | 5 | 0 | 5 | 0% |
| **TOTAL** | **50** | **4** | **46** | **8%** |

---

## Deletion Procedure

When ready to delete files from any phase:

1. **Create a git tag:** `git tag pre-admin-cleanup-phase-N`
2. **Remove route definitions** from `httpdocs/routes.php` for the relevant `/admin/*` paths
3. **Delete the files** listed in the phase
4. **Run tests:** `vendor/bin/phpunit` to verify no broken dependencies
5. **Build React frontend:** `cd react-frontend && npm run build` to verify no import errors
6. **Deploy to staging** and verify all React admin pages work correctly
7. **Deploy to production** only after staging verification passes
