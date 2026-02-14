# React Admin Panel -- Parity Report

> **Last updated:** 2026-02-14
> **Branch:** `hotfix/prod-avatars-scroll-20260213`

---

## Summary

| Metric | Count |
|--------|-------|
| **Total PHP admin pages identified** | 48 |
| **Fully functional React pages** | 4 |
| **Placeholder routes registered** | 119 |
| **React routes with no PHP equivalent (new)** | 6 |
| **PHP pages with no React route** | 0 |
| **Route parity** | **100%** (all PHP pages have a corresponding React route) |
| **Functional parity** | **8.3%** (4 of 48 pages fully migrated) |

### What "Parity" Means

- **Route parity** = every PHP admin page has a corresponding React route (placeholder or functional).
- **Functional parity** = the React page is fully interactive with its own V2 API backend, replacing the legacy PHP page entirely.

---

## Parity Matrix

### 1. Dashboard

| # | PHP Page | React Route | Status | Notes |
|---|----------|-------------|--------|-------|
| 1 | `/admin` (AdminController@index) | `/admin` | COMPLETE | Stats cards, trend charts, activity log. 3 V2 API endpoints. |

### 2. Users

| # | PHP Page | React Route | Status | Notes |
|---|----------|-------------|--------|-------|
| 2 | `/admin/users` (UserController@index) | `/admin/users` | COMPLETE | DataTable, filter tabs, search, approve/suspend/ban/reactivate/reset-2fa. 10 V2 API endpoints. |
| 3 | `/admin/users/create` (UserController@create) | `/admin/users/create` | PLACEHOLDER | Links to legacy `/admin/users/create` |
| 4 | `/admin/users/edit/{id}` (UserController@edit) | `/admin/users/:id/edit` | PLACEHOLDER | Links to legacy `/admin/users/edit` |
| 5 | `/admin/users/{id}/permissions` (UserController@permissions) | `/admin/users/:id/permissions` | PLACEHOLDER | Links to legacy `/admin/enterprise/permissions` |

### 3. Listings

| # | PHP Page | React Route | Status | Notes |
|---|----------|-------------|--------|-------|
| 6 | `/admin/listings` (ListingController@index) | `/admin/listings` | COMPLETE | DataTable, status tabs, approve/delete actions. 4 V2 API endpoints. |

### 4. Content

| # | PHP Page | React Route | Status | Notes |
|---|----------|-------------|--------|-------|
| 7 | `/admin/blog` (BlogController@index) | `/admin/blog` | PLACEHOLDER | Blog post listing |
| 8 | `/admin/blog/create` (BlogController@create) | `/admin/blog/create` | PLACEHOLDER | Create blog post form |
| 9 | `/admin/blog/edit/{id}` (BlogController@edit) | `/admin/blog/edit/:id` | PLACEHOLDER | Edit blog post form |
| 10 | `/admin/pages` (PageController@index) | `/admin/pages` | PLACEHOLDER | CMS page listing |
| 11 | `/admin/pages/builder/{id}` (PageController@builder) | `/admin/pages/builder/:id` | PLACEHOLDER | Visual page builder |
| 12 | `/admin/menus` (MenuController@index) | `/admin/menus` | PLACEHOLDER | Menu manager |
| 13 | `/admin/menus/builder/{id}` (MenuController@builder) | `/admin/menus/builder/:id` | PLACEHOLDER | Menu item builder |
| 14 | `/admin/categories` (CategoryController@index) | `/admin/categories` | PLACEHOLDER | Category listing |
| 15 | `/admin/categories/create` (CategoryController@create) | `/admin/categories/create` | PLACEHOLDER | Create category form |
| 16 | `/admin/categories/edit/{id}` (CategoryController@edit) | `/admin/categories/edit/:id` | PLACEHOLDER | Edit category form |
| 17 | `/admin/attributes` (AttributeController@index) | `/admin/attributes` | PLACEHOLDER | Listing attributes |

### 5. Engagement (Gamification)

| # | PHP Page | React Route | Status | Notes |
|---|----------|-------------|--------|-------|
| 18 | `/admin/gamification` (GamificationController@index) | `/admin/gamification` | PLACEHOLDER | Gamification hub |
| 19 | `/admin/gamification/campaigns` (GamificationController@campaigns) | `/admin/gamification/campaigns` | PLACEHOLDER | Campaign listing |
| 20 | `/admin/gamification/campaigns/create` (GamificationController@createCampaign) | `/admin/gamification/campaigns/create` | PLACEHOLDER | Create campaign form |
| 21 | `/admin/gamification/campaigns/edit/{id}` (GamificationController@editCampaign) | `/admin/gamification/campaigns/edit/:id` | PLACEHOLDER | Edit campaign form |
| 22 | `/admin/gamification/analytics` (GamificationController@analytics) | `/admin/gamification/analytics` | PLACEHOLDER | Achievement analytics |
| 23 | `/admin/custom-badges` (CustomBadgeController@index) | `/admin/custom-badges` | PLACEHOLDER | Custom badge listing |
| 24 | `/admin/custom-badges/create` (CustomBadgeController@create) | `/admin/custom-badges/create` | PLACEHOLDER | Create badge form |

### 6. Matching and Broker

| # | PHP Page | React Route | Status | Notes |
|---|----------|-------------|--------|-------|
| 25 | `/admin/smart-matching` (SmartMatchingController@index) | `/admin/smart-matching` | PLACEHOLDER | Smart matching overview |
| 26 | `/admin/smart-matching/analytics` (SmartMatchingController@analytics) | `/admin/smart-matching/analytics` | PLACEHOLDER | Matching analytics |
| 27 | `/admin/smart-matching/configuration` (SmartMatchingController@configuration) | `/admin/smart-matching/configuration` | PLACEHOLDER | Algorithm config |
| 28 | `/admin/match-approvals` (MatchApprovalsController@index) | `/admin/match-approvals` | PLACEHOLDER | Match approval queue |
| 29 | `/admin/match-approvals/{id}` (MatchApprovalsController@show) | `/admin/match-approvals/:id` | PLACEHOLDER | Match detail view |
| 30 | `/admin/broker-controls` (BrokerControlsController@index) | `/admin/broker-controls` | PLACEHOLDER | Broker dashboard |
| -- | -- | `/admin/broker-controls/exchanges` | PLACEHOLDER | React-only sub-route (PHP uses tabs) |
| -- | -- | `/admin/broker-controls/risk-tags` | PLACEHOLDER | React-only sub-route (PHP uses tabs) |
| -- | -- | `/admin/broker-controls/messages` | PLACEHOLDER | React-only sub-route (PHP uses tabs) |
| -- | -- | `/admin/broker-controls/monitoring` | PLACEHOLDER | React-only sub-route (PHP uses tabs) |

### 7. Marketing (Newsletters)

| # | PHP Page | React Route | Status | Notes |
|---|----------|-------------|--------|-------|
| 31 | `/admin/newsletters` (NewsletterController@index) | `/admin/newsletters` | PLACEHOLDER | Newsletter listing |
| 32 | `/admin/newsletters/create` (NewsletterController@create) | `/admin/newsletters/create` | PLACEHOLDER | Create newsletter |
| 33 | `/admin/newsletters/edit/{id}` (NewsletterController@edit) | `/admin/newsletters/edit/:id` | PLACEHOLDER | Edit newsletter |
| 34 | `/admin/newsletters/subscribers` (NewsletterController@subscribers) | `/admin/newsletters/subscribers` | PLACEHOLDER | Subscriber management |
| 35 | `/admin/newsletters/segments` (NewsletterController@segments) | `/admin/newsletters/segments` | PLACEHOLDER | Audience segments |
| 36 | `/admin/newsletters/templates` (NewsletterController@templates) | `/admin/newsletters/templates` | PLACEHOLDER | Email templates |
| 37 | `/admin/newsletters/analytics` (NewsletterController@analytics) | `/admin/newsletters/analytics` | PLACEHOLDER | Campaign analytics |

### 8. Advanced

| # | PHP Page | React Route | Status | Notes |
|---|----------|-------------|--------|-------|
| 38 | `/admin/ai-settings` (AiSettingsController@index) | `/admin/ai-settings` | PLACEHOLDER | AI provider config |
| 39 | `/admin/feed-algorithm` (AdminController@feedAlgorithm) | `/admin/feed-algorithm` | PLACEHOLDER | Feed algorithm tuning |
| -- | -- | `/admin/algorithm-settings` | PLACEHOLDER | React-only sub-route |
| 40 | `/admin/seo` (SeoController@index) | `/admin/seo` | PLACEHOLDER | SEO overview |
| -- | -- | `/admin/seo/audit` | PLACEHOLDER | React-only sub-route (PHP uses tabs) |
| -- | -- | `/admin/seo/redirects` | PLACEHOLDER | React-only sub-route (PHP uses tabs) |
| 41 | `/admin/404-errors` (Error404Controller@index) | `/admin/404-errors` | PLACEHOLDER | 404 error tracking |

### 9. Financial (Timebanking)

| # | PHP Page | React Route | Status | Notes |
|---|----------|-------------|--------|-------|
| 42 | `/admin/timebanking` (TimebankingController@index) | `/admin/timebanking` | PLACEHOLDER | Transaction analytics |
| -- | -- | `/admin/timebanking/alerts` | PLACEHOLDER | Fraud alert listing |
| -- | -- | `/admin/timebanking/user-report` | PLACEHOLDER | User financial report |
| -- | -- | `/admin/timebanking/user-report/:id` | PLACEHOLDER | User-specific report |
| -- | -- | `/admin/timebanking/org-wallets` | PLACEHOLDER | Org wallet management |
| -- | -- | `/admin/timebanking/create-org` | PLACEHOLDER | Create org form |
| 43 | `/admin/plans` (PlanController@index) | `/admin/plans` | PLACEHOLDER | Plans listing |
| -- | -- | `/admin/plans/create` | PLACEHOLDER | Create plan form |
| -- | -- | `/admin/plans/edit/:id` | PLACEHOLDER | Edit plan form |
| -- | -- | `/admin/plans/subscriptions` | PLACEHOLDER | Subscription management |

### 10. Enterprise

| # | PHP Page | React Route | Status | Notes |
|---|----------|-------------|--------|-------|
| 44 | `/admin/enterprise` (EnterpriseDashboardController@dashboard) | `/admin/enterprise` | PLACEHOLDER | Enterprise overview |
| -- | `/admin/enterprise/roles` (RolesController@index) | `/admin/enterprise/roles` | PLACEHOLDER | Roles listing |
| -- | `/admin/enterprise/roles/create` (RolesController@create) | `/admin/enterprise/roles/create` | PLACEHOLDER | Create role form |
| -- | `/admin/enterprise/roles/{id}` (RolesController@show) | `/admin/enterprise/roles/:id` | PLACEHOLDER | View role detail |
| -- | `/admin/enterprise/roles/{id}/edit` (RolesController@edit) | `/admin/enterprise/roles/:id/edit` | PLACEHOLDER | Edit role form |
| -- | `/admin/enterprise/permissions` (RolesController@permissions) | `/admin/enterprise/permissions` | PLACEHOLDER | Permission browser |
| -- | `/admin/enterprise/gdpr` (GdprRequestController@dashboard) | `/admin/enterprise/gdpr` | PLACEHOLDER | GDPR dashboard |
| -- | `/admin/enterprise/gdpr/requests` (GdprRequestController@index) | `/admin/enterprise/gdpr/requests` | PLACEHOLDER | Data requests |
| -- | `/admin/enterprise/gdpr/consents` (GdprConsentController@index) | `/admin/enterprise/gdpr/consents` | PLACEHOLDER | Consent records |
| -- | `/admin/enterprise/gdpr/breaches` (GdprBreachController@index) | `/admin/enterprise/gdpr/breaches` | PLACEHOLDER | Data breaches |
| -- | `/admin/enterprise/gdpr/audit` (GdprAuditController@index) | `/admin/enterprise/gdpr/audit` | PLACEHOLDER | GDPR audit log |
| -- | `/admin/enterprise/monitoring` (MonitoringController@dashboard) | `/admin/enterprise/monitoring` | PLACEHOLDER | System monitoring |
| -- | `/admin/enterprise/monitoring/health` (MonitoringController@healthCheck) | `/admin/enterprise/monitoring/health` | PLACEHOLDER | Health check |
| -- | `/admin/enterprise/monitoring/logs` (MonitoringController@logs) | `/admin/enterprise/monitoring/logs` | PLACEHOLDER | Error logs |
| -- | `/admin/enterprise/config` (ConfigController@dashboard) | `/admin/enterprise/config` | PLACEHOLDER | System configuration |
| -- | `/admin/enterprise/config/secrets` (SecretsController@index) | `/admin/enterprise/config/secrets` | PLACEHOLDER | Secrets vault |
| 45 | `/admin/legal-documents` (LegalDocumentsController@index) | `/admin/legal-documents` | PLACEHOLDER | Legal docs listing |
| -- | `/admin/legal-documents/create` (LegalDocumentsController@create) | `/admin/legal-documents/create` | PLACEHOLDER | Create legal doc |
| -- | `/admin/legal-documents/{id}` (LegalDocumentsController@show) | `/admin/legal-documents/:id` | PLACEHOLDER | View legal doc |
| -- | `/admin/legal-documents/{id}/edit` (LegalDocumentsController@edit) | `/admin/legal-documents/:id/edit` | PLACEHOLDER | Edit legal doc |

### 11. Federation

| # | PHP Page | React Route | Status | Notes |
|---|----------|-------------|--------|-------|
| 46 | `/admin/federation` (FederationSettingsController@index) | `/admin/federation` | PLACEHOLDER | Federation settings |
| -- | `/admin/federation/partnerships` (FederationSettingsController@partnerships) | `/admin/federation/partnerships` | PLACEHOLDER | Partnership management |
| -- | `/admin/federation/directory` (FederationDirectoryController@index) | `/admin/federation/directory` | PLACEHOLDER | Partner directory |
| -- | `/admin/federation/directory/profile` (FederationDirectoryController@profile) | `/admin/federation/directory/profile` | PLACEHOLDER | My federation listing |
| -- | `/admin/federation/analytics` (FederationAnalyticsController@index) | `/admin/federation/analytics` | PLACEHOLDER | Federation analytics |
| -- | `/admin/federation/api-keys` (FederationApiKeysController@index) | `/admin/federation/api-keys` | PLACEHOLDER | API key management |
| -- | `/admin/federation/api-keys/create` (FederationApiKeysController@create) | `/admin/federation/api-keys/create` | PLACEHOLDER | Create API key |
| -- | `/admin/federation/data` (FederationExportController@index) | `/admin/federation/data` | PLACEHOLDER | Data import/export |

### 12. System

| # | PHP Page | React Route | Status | Notes |
|---|----------|-------------|--------|-------|
| -- | `/admin/settings` (AdminController@settings) | `/admin/settings` | PLACEHOLDER | Global settings |
| 47 | `/admin/tenant-features` (TenantFeaturesController) | `/admin/tenant-features` | COMPLETE | Feature/module toggles, cache management, background jobs. 7 V2 API endpoints. |
| -- | `/admin/cron-jobs` (CronJobController@index) | `/admin/cron-jobs` | PLACEHOLDER | Scheduled task manager |
| -- | `/admin/activity-log` (AdminController@activityLogs) | `/admin/activity-log` | PLACEHOLDER | Admin audit trail |
| -- | `/admin/tests` (TestRunnerController@index) | `/admin/tests` | PLACEHOLDER | API test runner |
| -- | `/admin/seed-generator` (SeedGeneratorController@index) | `/admin/seed-generator` | PLACEHOLDER | Seed data generator |
| 48 | `/admin/webp-converter` (AdminController@webpConverter) | `/admin/webp-converter` | PLACEHOLDER | Image converter |
| -- | `/admin/image-settings` (AdminController@imageSettings) | `/admin/image-settings` | PLACEHOLDER | Image optimization |
| -- | `/admin/native-app` (AdminController@nativeApp) | `/admin/native-app` | PLACEHOLDER | Mobile app config |
| -- | `/admin/blog-restore` (BlogRestoreController@index) | `/admin/blog-restore` | PLACEHOLDER | Blog restore/backup |

### 13. Community Tools

| # | PHP Page | React Route | Status | Notes |
|---|----------|-------------|--------|-------|
| -- | `/admin/groups` (GroupAdminController@index) | `/admin/groups` | PLACEHOLDER | Group listing |
| -- | `/admin/groups/analytics` (GroupAdminController@analytics) | `/admin/groups/analytics` | PLACEHOLDER | Group analytics |
| -- | `/admin/groups/approvals` (GroupAdminController@approvals) | `/admin/groups/approvals` | PLACEHOLDER | Group approvals |
| -- | `/admin/groups/moderation` (GroupAdminController@moderation) | `/admin/groups/moderation` | PLACEHOLDER | Content moderation |
| -- | `/admin/group-types` (AdminController@groupTypes) | `/admin/group-types` | PLACEHOLDER | Group type config |
| -- | `/admin/group-ranking` (AdminController@groupRanking) | `/admin/group-ranking` | PLACEHOLDER | Featured group ranking |
| -- | `/admin/group-locations` (AdminController@groupLocations) | `/admin/group-locations` | PLACEHOLDER | Group geo-location |
| -- | `/admin/geocode-groups` (AdminController@geocodeGroups) | `/admin/geocode-groups` | PLACEHOLDER | Batch geocoding |
| -- | `/admin/smart-match-users` (AdminController@smartMatchUsers) | `/admin/smart-match-users` | PLACEHOLDER | Smart match user view |
| -- | `/admin/smart-match-monitoring` (AdminController@smartMatchMonitoring) | `/admin/smart-match-monitoring` | PLACEHOLDER | Match monitoring |
| -- | `/admin/volunteering` (VolunteeringController@index) | `/admin/volunteering` | PLACEHOLDER | Volunteering overview |
| -- | `/admin/volunteering/approvals` (VolunteeringController@approvals) | `/admin/volunteering/approvals` | PLACEHOLDER | Volunteer approvals |
| -- | `/admin/volunteering/organizations` (VolunteeringController@organizations) | `/admin/volunteering/organizations` | PLACEHOLDER | Org management |

### 14. Deliverability

| # | PHP Page | React Route | Status | Notes |
|---|----------|-------------|--------|-------|
| -- | `/admin/deliverability` (AdminController@deliverabilityDashboard) | `/admin/deliverability` | PLACEHOLDER | Deliverability dashboard |
| -- | `/admin/deliverability/list` (AdminController@deliverablesList) | `/admin/deliverability/list` | PLACEHOLDER | All deliverables |
| -- | `/admin/deliverability/create` (AdminController@deliverableCreate) | `/admin/deliverability/create` | PLACEHOLDER | Create deliverable |
| -- | `/admin/deliverability/analytics` (AdminController@deliverabilityAnalytics) | `/admin/deliverability/analytics` | PLACEHOLDER | Deliverability analytics |

### 15. Diagnostics

| # | PHP Page | React Route | Status | Notes |
|---|----------|-------------|--------|-------|
| -- | `/admin/matching-diagnostic` (MatchingDiagnosticController@index) | `/admin/matching-diagnostic` | PLACEHOLDER | Matching diagnostic tool |
| -- | `/admin/nexus-score/analytics` (NexusScoreController@adminAnalytics) | `/admin/nexus-score/analytics` | PLACEHOLDER | Nexus Score analytics |

---

## Status Legend

| Status | Meaning |
|--------|---------|
| COMPLETE | Fully functional React page with dedicated V2 API backend. PHP page can be decommissioned. |
| PLACEHOLDER | React route exists and renders `AdminPlaceholder` component with "Migration In Progress" message and link to legacy PHP page. |
| NOT STARTED | No React route exists for this PHP page. |

---

## Infrastructure Parity

### Shared Components (React Admin)

| React Component | File | PHP Equivalent | Notes |
|----------------|------|----------------|-------|
| `AdminRoute` | `admin/AdminRoute.tsx` | `AdminAuth::check()` middleware | JWT-based role check (`admin`, `tenant_admin`, `super_admin`) |
| `AdminLayout` | `admin/AdminLayout.tsx` | `views/layouts/admin.php` | Sidebar + header + breadcrumbs + content outlet |
| `AdminSidebar` | `admin/components/AdminSidebar.tsx` | `views/admin/partials/sidebar.php` | Collapsible, mirrors PHP nav config, tenant-aware, feature-gated federation section |
| `AdminHeader` | `admin/components/AdminHeader.tsx` | `views/admin/partials/header.php` | Tenant name, user avatar, dropdown menu |
| `AdminBreadcrumbs` | `admin/components/AdminBreadcrumbs.tsx` | Manual breadcrumb HTML in each PHP view | Auto-generated from React Router location |
| `DataTable` | `admin/components/DataTable.tsx` | Custom `<table>` HTML per page | Reusable: sort, search, filter, paginate, bulk select. Built on HeroUI Table. |
| `StatCard` | `admin/components/StatCard.tsx` | Inline stat cards per page | Reusable: icon, value, trend arrow, loading skeleton |
| `PageHeader` | `admin/components/PageHeader.tsx` | `<h1>` + description per page | Reusable: title, description, action buttons |
| `ConfirmModal` | `admin/components/ConfirmModal.tsx` | `confirm()` / custom modals | Reusable: title, message, confirm/cancel actions |
| `StatusBadge` | `admin/components/DataTable.tsx` (exported) | `<span class="badge ...">` per page | Reusable: color-coded status chips |
| `EmptyState` | `admin/components/EmptyState.tsx` | Inline "no results" messages | Reusable: icon, title, description, action |
| `AdminPlaceholder` | `admin/modules/AdminPlaceholder.tsx` | N/A | Placeholder for unmigrated modules with link to legacy PHP |

### Infrastructure Advantages of React Admin

- **Lazy loading**: All page modules use `React.lazy()` with Suspense fallback
- **Consistent UX**: Every page uses the same layout, sidebar, breadcrumbs, and DataTable
- **HeroUI components**: Buttons, inputs, modals, tables, chips all from one design system
- **Tenant-aware**: Sidebar navigation uses `tenantPath()` for all links
- **Feature-gated**: Federation section conditionally shown based on `hasFeature('federation')`
- **Dark mode**: Inherits theme from `ThemeContext` (light/dark/system)

---

## API Parity

### V2 Admin API Endpoints (React Backend)

| Endpoint | Method | Controller | Legacy PHP Equivalent | Status |
|----------|--------|------------|----------------------|--------|
| `/api/v2/admin/dashboard/stats` | GET | AdminDashboardApiController | Inline queries in `AdminController@index` | LIVE |
| `/api/v2/admin/dashboard/trends` | GET | AdminDashboardApiController | Inline queries in `AdminController@index` | LIVE |
| `/api/v2/admin/dashboard/activity` | GET | AdminDashboardApiController | `AdminController@activityLogs` | LIVE |
| `/api/v2/admin/users` | GET | AdminUsersApiController | `Admin\UserController@index` | LIVE |
| `/api/v2/admin/users` | POST | AdminUsersApiController | `Admin\UserController@store` | LIVE |
| `/api/v2/admin/users/{id}` | GET | AdminUsersApiController | `Admin\UserController@edit` (view) | LIVE |
| `/api/v2/admin/users/{id}` | PUT | AdminUsersApiController | `Admin\UserController@update` | LIVE |
| `/api/v2/admin/users/{id}` | DELETE | AdminUsersApiController | `Admin\UserController@delete` | LIVE |
| `/api/v2/admin/users/{id}/approve` | POST | AdminUsersApiController | `Admin\UserController@approve` | LIVE |
| `/api/v2/admin/users/{id}/suspend` | POST | AdminUsersApiController | `Admin\UserController@suspend` | LIVE |
| `/api/v2/admin/users/{id}/ban` | POST | AdminUsersApiController | `Admin\UserController@ban` | LIVE |
| `/api/v2/admin/users/{id}/reactivate` | POST | AdminUsersApiController | `Admin\UserController@reactivate` | LIVE |
| `/api/v2/admin/users/{id}/reset-2fa` | POST | AdminUsersApiController | `Admin\UserController@reset2fa` | LIVE |
| `/api/v2/admin/listings` | GET | AdminListingsApiController | `Admin\ListingController@index` | LIVE |
| `/api/v2/admin/listings/{id}` | GET | AdminListingsApiController | Inline in listing views | LIVE |
| `/api/v2/admin/listings/{id}/approve` | POST | AdminListingsApiController | `Admin\ListingController@approve` | LIVE |
| `/api/v2/admin/listings/{id}` | DELETE | AdminListingsApiController | `Admin\ListingController@delete` | LIVE |
| `/api/v2/admin/config` | GET | AdminConfigApiController | `TenantFeaturesController@index` | LIVE |
| `/api/v2/admin/config/features` | PUT | AdminConfigApiController | `TenantFeaturesController@toggleFeature` | LIVE |
| `/api/v2/admin/config/modules` | PUT | AdminConfigApiController | `TenantFeaturesController@toggleModule` | LIVE |
| `/api/v2/admin/cache/stats` | GET | AdminConfigApiController | N/A (new) | LIVE |
| `/api/v2/admin/cache/clear` | POST | AdminConfigApiController | N/A (new) | LIVE |
| `/api/v2/admin/jobs` | GET | AdminConfigApiController | `Admin\CronJobController@index` | LIVE |
| `/api/v2/admin/jobs/{id}/run` | POST | AdminConfigApiController | `Admin\CronJobController@run` | LIVE |

**Total V2 admin API endpoints: 24 (registered as 30 routes in `routes.php` including method variants)**

### APIs Not Yet Built (needed for placeholder pages)

The following PHP admin sections will need new V2 API controllers when migrated:

| Future Controller | Covers | Est. Endpoints |
|-------------------|--------|----------------|
| AdminBlogApiController | Blog CRUD, builder | 6-8 |
| AdminPagesApiController | CMS pages, builder, blocks | 8-10 |
| AdminMenusApiController | Menus, items, reorder | 6-8 |
| AdminCategoriesApiController | Categories CRUD | 4-5 |
| AdminAttributesApiController | Attributes CRUD | 4-5 |
| AdminGamificationApiController | Hub, campaigns, custom badges, analytics | 10-12 |
| AdminMatchingApiController | Smart matching, approvals, broker controls | 12-15 |
| AdminNewsletterApiController | Newsletters, subscribers, segments, templates | 15-20 |
| AdminTimebankingApiController | Analytics, alerts, org wallets, user reports | 10-12 |
| AdminPlansApiController | Plans CRUD, subscriptions | 5-7 |
| AdminEnterpriseApiController | GDPR, monitoring, config, secrets, roles | 20-25 |
| AdminLegalDocsApiController | Legal docs, versions, compliance | 8-10 |
| AdminFederationApiController | Settings, partnerships, directory, API keys | 10-12 |
| AdminGroupsApiController | Groups, analytics, approvals, moderation | 8-10 |
| AdminVolunteeringApiController | Overview, approvals, organizations | 4-6 |
| AdminSeoApiController | SEO overview, audit, redirects, 404s | 6-8 |
| AdminDeliverabilityApiController | Dashboard, list, analytics | 5-7 |

**Estimated total: ~140-170 new API endpoints needed for full functional parity.**

---

## Completed Pages -- Detail

### 1. Admin Dashboard (`/admin`)

**React file:** `react-frontend/src/admin/modules/dashboard/AdminDashboard.tsx`
**API controller:** `src/Controllers/Api/AdminDashboardApiController.php`

| Feature | PHP | React |
|---------|-----|-------|
| Total users stat | Inline query | `GET /api/v2/admin/dashboard/stats` |
| Active listings stat | Inline query | `GET /api/v2/admin/dashboard/stats` |
| Monthly transactions stat | Inline query | `GET /api/v2/admin/dashboard/stats` |
| Pending approvals stat | Inline query | `GET /api/v2/admin/dashboard/stats` |
| Trend charts (7/30 day) | N/A | `GET /api/v2/admin/dashboard/trends` |
| Recent activity log | Separate page | `GET /api/v2/admin/dashboard/activity` |

### 2. User Management (`/admin/users`)

**React file:** `react-frontend/src/admin/modules/users/UserList.tsx`
**API controller:** `src/Controllers/Api/AdminUsersApiController.php`

| Feature | PHP | React |
|---------|-----|-------|
| User listing with search | Server-rendered table | DataTable with client-side search |
| Filter by status (all/active/pending/suspended/banned) | Query params | Tab-based filter |
| Approve user | POST form | API call + toast |
| Suspend user | POST form | ConfirmModal + API call |
| Ban user | POST form | ConfirmModal + API call |
| Reactivate user | POST form | API call + toast |
| Reset 2FA | POST form | ConfirmModal + API call |
| Pagination | Server-side | Client-side via DataTable |
| Sort by name/email/date | N/A (fixed order) | Column sorting |

### 3. Listings Admin (`/admin/listings`)

**React file:** `react-frontend/src/admin/modules/listings/ListingsAdmin.tsx`
**API controller:** `src/Controllers/Api/AdminListingsApiController.php`

| Feature | PHP | React |
|---------|-----|-------|
| Listing table with search | Server-rendered table | DataTable with search |
| Filter by status | Query params | Tab-based filter |
| Approve listing | POST form | API call + toast |
| Delete listing | POST form | ConfirmModal + API call |
| View listing detail | Separate page | Detail link |

### 4. Tenant Features (`/admin/tenant-features`)

**React file:** `react-frontend/src/admin/modules/config/TenantFeatures.tsx`
**API controller:** `src/Controllers/Api/AdminConfigApiController.php`

| Feature | PHP | React |
|---------|-----|-------|
| Feature toggle switches | PHP form + POST | Toggle switch + PUT API |
| Module toggle switches | PHP form + POST | Toggle switch + PUT API |
| Cache statistics | N/A | `GET /api/v2/admin/cache/stats` |
| Clear cache | N/A | `POST /api/v2/admin/cache/clear` |
| Background job listing | Separate cron page | `GET /api/v2/admin/jobs` |
| Run background job | Separate cron page | `POST /api/v2/admin/jobs/{id}/run` |

---

## Next Steps

### Migration Priority (recommended order)

**Tier 1 -- High Impact / High Traffic** (migrate first):
1. **Settings** (`/admin/settings`) -- most-visited after dashboard
2. **Blog Posts** (`/admin/blog`) -- content creation is daily workflow
3. **Cron Jobs** (`/admin/cron-jobs`) -- operational necessity
4. **Activity Log** (`/admin/activity-log`) -- compliance and debugging

**Tier 2 -- Matching and Financial** (broker-critical):
5. **Match Approvals** (`/admin/match-approvals`) -- daily broker workflow
6. **Broker Controls** (`/admin/broker-controls`) -- exchange oversight
7. **Timebanking** (`/admin/timebanking`) -- transaction monitoring
8. **Smart Matching** (`/admin/smart-matching`) -- algorithm tuning

**Tier 3 -- Content Management** (editorial workflow):
9. **Pages + Builder** (`/admin/pages`) -- CMS functionality
10. **Categories** (`/admin/categories`) -- content organization
11. **Menus** (`/admin/menus`) -- navigation management
12. **Newsletters** (`/admin/newsletters`) -- marketing campaigns

**Tier 4 -- Engagement** (gamification):
13. **Gamification Hub** (`/admin/gamification`) -- badge/XP management
14. **Campaigns** (`/admin/gamification/campaigns`) -- challenge creation
15. **Custom Badges** (`/admin/custom-badges`) -- badge design

**Tier 5 -- Enterprise and Compliance** (admin-only):
16. **GDPR Dashboard** (`/admin/enterprise/gdpr`) -- data protection
17. **Roles & Permissions** (`/admin/enterprise/roles`) -- RBAC
18. **Legal Documents** (`/admin/legal-documents`) -- legal compliance
19. **System Monitoring** (`/admin/enterprise/monitoring`) -- ops visibility
20. **System Config** (`/admin/enterprise/config`) -- configuration

**Tier 6 -- Remaining** (lower frequency):
21. Federation settings and management
22. Groups admin and moderation
23. Volunteering admin
24. Plans and subscriptions
25. SEO and 404 tracking
26. AI settings
27. Deliverability tracking
28. Utility tools (WebP converter, image settings, seed generator, etc.)

### Per-Page Migration Checklist

For each placeholder page being migrated to a fully functional React page:

1. **API**: Create `AdminXxxApiController` in `src/Controllers/Api/` with JSON endpoints
2. **Routes**: Register V2 API routes in `httpdocs/routes.php` under the `API V2 - ADMIN` section
3. **React page**: Replace `AdminPlaceholder` with a full page component using `DataTable`, `StatCard`, etc.
4. **Update routes.tsx**: Change from `P(...)` placeholder to `Lazy` import of the new component
5. **Test**: Verify data loads, actions work, error states display
6. **Remove legacy**: Mark the PHP admin page as deprecated (do not delete until stable)

### Estimated Effort to 100% Functional Parity

| Category | Pages | Est. API Endpoints | Est. Effort |
|----------|-------|--------------------|-------------|
| Tier 1 (High Impact) | 4 | 15-20 | 2-3 days |
| Tier 2 (Matching/Financial) | 4 | 25-30 | 3-4 days |
| Tier 3 (Content) | 4 | 25-30 | 3-4 days |
| Tier 4 (Engagement) | 3 | 10-12 | 2-3 days |
| Tier 5 (Enterprise) | 5 | 30-40 | 4-5 days |
| Tier 6 (Remaining) | 24 | 40-50 | 5-7 days |
| **Total** | **44 remaining** | **~145-182** | **~19-26 days** |

---

## File Reference

| File | Purpose |
|------|---------|
| `react-frontend/src/admin/routes.tsx` | All admin route definitions (4 functional + 119 placeholder) |
| `react-frontend/src/admin/AdminRoute.tsx` | Auth guard (admin/tenant_admin/super_admin) |
| `react-frontend/src/admin/AdminLayout.tsx` | Layout shell (sidebar + header + content) |
| `react-frontend/src/admin/components/AdminSidebar.tsx` | Collapsible sidebar navigation |
| `react-frontend/src/admin/components/AdminHeader.tsx` | Top header bar |
| `react-frontend/src/admin/components/AdminBreadcrumbs.tsx` | Auto-generated breadcrumbs |
| `react-frontend/src/admin/components/DataTable.tsx` | Reusable sortable/searchable table + StatusBadge |
| `react-frontend/src/admin/components/StatCard.tsx` | Metric card with trend |
| `react-frontend/src/admin/components/PageHeader.tsx` | Page title + description + actions |
| `react-frontend/src/admin/components/ConfirmModal.tsx` | Confirmation dialog |
| `react-frontend/src/admin/components/EmptyState.tsx` | No-data state |
| `react-frontend/src/admin/modules/AdminPlaceholder.tsx` | Placeholder for unmigrated pages |
| `react-frontend/src/admin/modules/dashboard/AdminDashboard.tsx` | Dashboard (COMPLETE) |
| `react-frontend/src/admin/modules/users/UserList.tsx` | User management (COMPLETE) |
| `react-frontend/src/admin/modules/listings/ListingsAdmin.tsx` | Listings admin (COMPLETE) |
| `react-frontend/src/admin/modules/config/TenantFeatures.tsx` | Feature/module toggles (COMPLETE) |
| `src/Controllers/Api/AdminDashboardApiController.php` | Dashboard V2 API (3 endpoints) |
| `src/Controllers/Api/AdminUsersApiController.php` | Users V2 API (10 endpoints) |
| `src/Controllers/Api/AdminListingsApiController.php` | Listings V2 API (4 endpoints) |
| `src/Controllers/Api/AdminConfigApiController.php` | Config V2 API (7 endpoints) |
| `httpdocs/routes.php` | Route definitions (30 V2 admin API routes) |
