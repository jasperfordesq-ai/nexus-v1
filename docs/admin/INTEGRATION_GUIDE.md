# React Admin Panel -- Integration Guide

Screen-by-screen mapping of every React admin page to its API endpoints, PHP controllers, services, models, and database tables.

---

## Architecture Overview

```
React Admin Page
  |
  v
adminApi.ts  (typed API client wrapper)
  |
  v
api.ts  (core HTTP client: token injection, refresh, tenant header)
  |
  v
GET/POST/PUT/DELETE  /api/v2/admin/*
  |
  v
PHP V2 Controller  (extends BaseApiController)
  |-- requireAdmin()  -->  Bearer token auth + role check
  |-- TenantContext::getId()  -->  tenant scoping
  |
  v
Service / Model / Direct Database::query()
  |
  v
MariaDB  (tenant-scoped queries)
```

### Authentication Flow

| Layer | Mechanism |
|-------|-----------|
| **React guard** | `AdminRoute.tsx` -- checks `useAuth()` for `role in (admin, tenant_admin, super_admin)` or `is_admin / is_super_admin` flags. Redirects non-admins to `/dashboard`. |
| **HTTP header** | Every request carries `Authorization: Bearer <access_token>` and `X-Tenant-ID: <id>` via `api.ts`. |
| **PHP auth** | `BaseApiController::requireAdmin()` calls `requireAuth()` (resolves Bearer token via `ApiAuth` trait), then checks `getAuthenticatedUserRole()` is one of `admin`, `super_admin`, `god`. Returns 403 with `AUTH_INSUFFICIENT_PERMISSIONS` on failure. |
| **Tenant scoping** | `TenantContext::getId()` is used in every query to enforce row-level tenant isolation. |

### Response Envelope (V2)

All admin controllers set `protected bool $isV2Api = true`, which activates the V2 envelope format.

**Success (single resource):**

```json
{
  "data": { ... },
  "meta": { "base_url": "https://api.project-nexus.ie" }
}
```

**Success (paginated collection):**

```json
{
  "data": [ ... ],
  "meta": {
    "base_url": "https://api.project-nexus.ie",
    "page": 1,
    "per_page": 20,
    "total": 142,
    "total_pages": 8,
    "has_more": true
  }
}
```

**Error:**

```json
{
  "errors": [
    { "code": "VALIDATION_ERROR", "message": "First name is required", "field": "first_name" }
  ]
}
```

The React `api.ts` client normalises both envelopes into `ApiResponse<T>` with `{ success, data, error, meta }`.

### Pagination Pattern

| Side | How it works |
|------|-------------|
| **PHP** | `respondWithPaginatedCollection($items, $total, $page, $perPage)` produces `meta.page`, `meta.per_page`, `meta.total`, `meta.total_pages`, `meta.has_more`. |
| **React** | `PaginatedResponse<T>` type expects `{ data: T[], meta: { current_page, last_page, per_page, total } }`. Components read `meta.total` for counts and pass `page` / `pageSize` to `DataTable`. |
| **Query params** | `?page=1&limit=20` -- both accepted on all paginated endpoints. `limit` is clamped to `[1, 100]`. |

---

## Screen-by-Screen Mapping

### 1. Admin Dashboard (`/admin`)

| Item | Value |
|------|-------|
| **React component** | `react-frontend/src/admin/modules/dashboard/AdminDashboard.tsx` |
| **Route definition** | `<Route index element={<AdminDashboard />} />` in `routes.tsx` |
| **Page title** | `usePageTitle('Admin Dashboard')` |

#### API Calls

All three fire in parallel via `Promise.all` on mount and on "Refresh" button press.

| Call | Endpoint | PHP Controller | PHP Method |
|------|----------|---------------|------------|
| Stats | `GET /v2/admin/dashboard/stats` | `AdminDashboardApiController` | `stats()` |
| Trends | `GET /v2/admin/dashboard/trends?months=6` | `AdminDashboardApiController` | `trends()` |
| Activity | `GET /v2/admin/dashboard/activity?page=1&limit=10` | `AdminDashboardApiController` | `activity()` |

#### Data Flow

**Stats card grid (4 cards):**

| Card | Maps to API field | DB query |
|------|------------------|----------|
| Total Users | `total_users` | `SELECT COUNT(*) FROM users WHERE tenant_id = ?` |
| Total Listings | `total_listings` | `SELECT COUNT(*) FROM listings WHERE tenant_id = ?` |
| Total Transactions | `total_transactions` | `SELECT COUNT(*) FROM transactions WHERE tenant_id = ?` |
| Total Volume (hours) | `total_hours_exchanged` | `SELECT SUM(amount) FROM transactions WHERE tenant_id = ?` |

Additional stats returned (used for quick-action cards): `pending_users`, `pending_listings`, `active_users`, `active_listings`, `new_users_this_month`, `new_listings_this_month`.

**Monthly Trends chart:**

The `trends` endpoint builds a month-by-month array for the last N months (default 6, max 24). Each entry contains:
- `users` -- count of new user registrations (`users.created_at`)
- `listings` -- count of new listings (`listings.created_at`)
- `transactions` -- count of transactions (`transactions.created_at`)
- `hours` -- sum of transaction amounts (`transactions.amount`)

React renders a horizontal bar chart for volume per month.

**Recent Activity feed:**

Uses `respondWithPaginatedCollection`. Joins `activity_log` with `users` to get `user_name`, `user_email`, `user_avatar`. Renders as a timeline list. "View all" links to `/admin/activity-log`.

#### DB Tables Queried

- `users` (counts, registrations per month)
- `listings` (counts, creations per month)
- `transactions` (counts, volume per month) -- wrapped in try/catch (table may not exist)
- `activity_log` joined with `users` (recent actions) -- wrapped in try/catch

#### Shared Components Used

- `PageHeader` -- title "Dashboard", description, Refresh button
- `StatCard` -- 4 metric cards with icons (Users, ListChecks, ArrowLeftRight, Clock)

---

### 2. User Management (`/admin/users`)

| Item | Value |
|------|-------|
| **React component** | `react-frontend/src/admin/modules/users/UserList.tsx` |
| **Route definition** | `<Route path="users" element={<UserList />} />` in `routes.tsx` |
| **Page title** | `usePageTitle('Admin - Users')` |

#### API Calls

| Action | Endpoint | PHP Controller | PHP Method |
|--------|----------|---------------|------------|
| List users | `GET /v2/admin/users?page=&limit=&status=&search=&role=&sort=&order=` | `AdminUsersApiController` | `index()` |
| Get user detail | `GET /v2/admin/users/{id}` | `AdminUsersApiController` | `show()` |
| Create user | `POST /v2/admin/users` | `AdminUsersApiController` | `store()` |
| Update user | `PUT /v2/admin/users/{id}` | `AdminUsersApiController` | `update()` |
| Delete user | `DELETE /v2/admin/users/{id}` | `AdminUsersApiController` | `destroy()` |
| Approve user | `POST /v2/admin/users/{id}/approve` | `AdminUsersApiController` | `approve()` |
| Suspend user | `POST /v2/admin/users/{id}/suspend` | `AdminUsersApiController` | `suspend()` |
| Ban user | `POST /v2/admin/users/{id}/ban` | `AdminUsersApiController` | `ban()` |
| Reactivate user | `POST /v2/admin/users/{id}/reactivate` | `AdminUsersApiController` | `reactivate()` |
| Reset 2FA | `POST /v2/admin/users/{id}/reset-2fa` | `AdminUsersApiController` | `reset2fa()` |
| Add badge | `POST /v2/admin/users/{id}/badges` | -- (placeholder) | -- |
| Remove badge | `DELETE /v2/admin/users/{id}/badges/{badgeId}` | -- (placeholder) | -- |
| Recheck all badges | `POST /v2/admin/users/badges/recheck-all` | -- (placeholder) | -- |
| Impersonate | `POST /v2/admin/users/{id}/impersonate` | -- (placeholder) | -- |

**Note:** Endpoints marked "(placeholder)" are defined in `adminApi.ts` but do not yet have registered PHP routes.

#### Data Flow

1. **Status tabs** -- `Tabs` component with keys: `all`, `pending`, `active`, `suspended`, `banned`. Changing tab resets `page=1` and updates `?filter=` search param.
2. **Search** -- `DataTable` provides a search input. Debounced, fires `loadUsers()` with `search` param. PHP searches across `first_name`, `last_name`, `email`, and `CONCAT(first_name, ' ', last_name)`.
3. **Sort** -- Whitelisted server-side: `name`, `email`, `role`, `created_at`, `balance`, `status`. `DataTable` also supports client-side sort fallback.
4. **Pagination** -- `DataTable` passes `page` / `pageSize=20` to API.
5. **Actions** -- Per-row dropdown menu (`UserActionsMenu`). Actions trigger `ConfirmModal`, then call the appropriate API endpoint. On success, `loadUsers()` is re-invoked to refresh the list.

#### PHP Logic Details

**Status mapping (PHP -> React):**

```
is_approved = 0           --> "pending"
is_approved = 1, status NULL or "active"  --> "active"
status = "suspended"      --> "suspended"
status = "banned"         --> "banned"
```

**Safeguards:**
- Cannot delete/suspend/ban yourself (`$id === $adminId` check)
- Cannot delete/suspend/ban super admins (`is_super_admin` check)
- Deleting, suspending, banning, approving, and resetting 2FA all log to `ActivityLog`

**User creation (`store`):**
- Validates `first_name`, `last_name`, `email`, `password` (min 8 chars)
- Checks duplicate email via `User::findByEmail()`
- Creates user via `User::create()`, then auto-approves via `User::updateAdminFields($id, $role, 1)`

**2FA reset (`reset2fa`):**
- Clears `totp_secret` and `totp_backup_codes` columns on the users table
- Wrapped in try/catch for environments where these columns may not exist

#### DB Tables Queried

- `users` (main query with computed `name`, `listing_count` subquery)
- `listings` (subquery: `SELECT COUNT(*) FROM listings WHERE user_id = u.id AND status = 'active'`)
- `activity_log` (for audit logging via `ActivityLog::log()`)

#### Models Used

- `Nexus\Models\User` -- `findByEmail()`, `findById()`, `create()`, `updateAdminFields()`
- `Nexus\Models\ActivityLog` -- `log($userId, $action, $description)`

#### Shared Components Used

- `PageHeader` -- title "Users", "Add User" button
- `DataTable` -- columns: User (avatar + name + email), Role (Chip), Status (StatusBadge), Balance, Joined, Actions
- `StatusBadge` -- color-coded Chip for status
- `ConfirmModal` -- destructive action confirmation with contextual title/message/color

---

### 3. Content Directory (`/admin/listings`)

| Item | Value |
|------|-------|
| **React component** | `react-frontend/src/admin/modules/listings/ListingsAdmin.tsx` |
| **Route definition** | `<Route path="listings" element={<ListingsAdmin />} />` in `routes.tsx` |
| **Page title** | `usePageTitle('Admin - Content')` |

#### API Calls

| Action | Endpoint | PHP Controller | PHP Method |
|--------|----------|---------------|------------|
| List content | `GET /v2/admin/listings?page=&status=&type=&search=&sort=&order=` | `AdminListingsApiController` | `index()` |
| Get listing detail | `GET /v2/admin/listings/{id}` | `AdminListingsApiController` | `show()` |
| Approve listing | `POST /v2/admin/listings/{id}/approve` | `AdminListingsApiController` | `approve()` |
| Delete listing | `DELETE /v2/admin/listings/{id}` | `AdminListingsApiController` | `destroy()` |

#### Data Flow

1. **Status tabs** -- `Tabs` with keys: `all`, `pending`, `active`, `inactive`. Inactive maps to `IN ('inactive', 'expired', 'closed')` in PHP.
2. **Type filter** -- available via query param `type` (values: `listing`, `event`, `poll`, `goal`, `resource`, `volunteer`). Not exposed as a UI tab but supported by the API.
3. **Search** -- searches `title` and `description` fields via `LIKE`.
4. **Sort** -- Whitelisted: `title`, `type`, `status`, `created_at`, `user_name`.
5. **Actions** -- Approve button (only shown for `pending` items) and Delete button. Both trigger `ConfirmModal`.

#### PHP Logic Details

**Approve action:** Sets `listings.status = 'active'` and logs via `ActivityLog`.

**Delete action:** Hard deletes the row from `listings` and logs via `ActivityLog`.

**Response fields per item:**
- `id`, `title`, `description`, `type`, `status`
- `user_id`, `user_name` (from `users` JOIN), `user_email`, `user_avatar`
- `category_id`, `category_name` (from `categories` JOIN)
- `hours_estimated`, `created_at`, `updated_at`

#### DB Tables Queried

- `listings` (main table)
- `users` (LEFT JOIN for author info)
- `categories` (LEFT JOIN for category name)
- `activity_log` (audit logging)

#### Shared Components Used

- `PageHeader` -- title "Content Directory"
- `DataTable` -- columns: Title, Type (color-coded Chip), Author, Status (StatusBadge), Created, Actions
- `StatusBadge` -- status indicator
- `ConfirmModal` -- approve/delete confirmation

---

### 4. Tenant Features (`/admin/tenant-features`)

| Item | Value |
|------|-------|
| **React component** | `react-frontend/src/admin/modules/config/TenantFeatures.tsx` |
| **Route definition** | `<Route path="tenant-features" element={<TenantFeatures />} />` in `routes.tsx` |
| **Page title** | `usePageTitle('Admin - Tenant Features')` |

#### API Calls

All three fire in parallel on mount.

| Action | Endpoint | PHP Controller | PHP Method |
|--------|----------|---------------|------------|
| Get config | `GET /v2/admin/config` | `AdminConfigApiController` | `getConfig()` |
| Toggle feature | `PUT /v2/admin/config/features` | `AdminConfigApiController` | `updateFeature()` |
| Toggle module | `PUT /v2/admin/config/modules` | `AdminConfigApiController` | `updateModule()` |
| Get cache stats | `GET /v2/admin/cache/stats` | `AdminConfigApiController` | `cacheStats()` |
| Clear cache | `POST /v2/admin/cache/clear` | `AdminConfigApiController` | `clearCache()` |
| Get jobs | `GET /v2/admin/jobs` | `AdminConfigApiController` | `getJobs()` |
| Run job | `POST /v2/admin/jobs/{id}/run` | `AdminConfigApiController` | `runJob()` |

#### Data Flow

**Features section (left column, 2/3 width):**

Iterates over `config.features` (Record<string, boolean>) and renders a `Switch` for each. Feature metadata (label + description) is defined in `FEATURE_META` constant on the React side.

Known features with defaults:

| Feature | Default | Description |
|---------|---------|-------------|
| `events` | true | Community events with RSVPs |
| `groups` | true | Community groups and discussions |
| `gamification` | false | Badges, achievements, XP, leaderboards |
| `goals` | false | Personal and community goals |
| `blog` | true | Community blog/news posts |
| `resources` | false | Shared resource library |
| `volunteering` | false | Volunteer opportunities and hours |
| `exchange_workflow` | false | Structured exchange requests with broker approval |
| `organisations` | false | Organization profiles and management |
| `federation` | false | Multi-community network and partnerships |
| `connections` | true | User connections and friend requests |
| `reviews` | true | Member reviews and ratings |
| `polls` | false | Community polls and voting |
| `direct_messaging` | true | Private messaging between members |

**Modules section (below features):**

Known modules with defaults:

| Module | Default | Description |
|--------|---------|-------------|
| `listings` | true | Service offers and requests marketplace |
| `wallet` | true | Time credit transactions and balance |
| `messages` | true | Messaging system |
| `dashboard` | true | Member dashboard |
| `feed` | true | Social activity feed |
| `notifications` | true | In-app notifications |
| `profile` | true | User profiles |
| `settings` | true | User settings |

**Cache sidebar (right column, 1/3 width):**

Displays Redis connection status, memory used, and key count from `cacheStats()`. "Clear Tenant Cache" button calls `clearCache({ type: 'tenant' })`.

**Background Jobs sidebar:**

Lists known jobs (currently 3 hardcoded): Email Digest Sender, Badge Award Checker, Login Streak Updater. Each has a "Run" button that triggers `POST /v2/admin/jobs/{id}/run`.

#### PHP Logic Details

**Config read (`getConfig`):**
1. Reads `tenants.features` (JSON column) and merges with `FEATURE_DEFAULTS`
2. Reads `tenants.configuration` (JSON column), extracts `modules` key, and merges with `MODULE_DEFAULTS`
3. Returns combined `{ tenant_id, features, modules }`

**Feature toggle (`updateFeature`):**
1. Validates feature name against `FEATURE_DEFAULTS` whitelist
2. Reads current `tenants.features` JSON, sets the key, writes back
3. Clears Redis bootstrap cache via `RedisCache::delete('tenant_bootstrap', $tenantId)`

**Module toggle (`updateModule`):**
1. Validates module name against `MODULE_DEFAULTS` whitelist
2. Reads current `tenants.configuration` JSON, updates `modules` key, writes back
3. Clears Redis bootstrap cache

**Cache clear (`clearCache`):**
- `type: 'tenant'` -- clears current tenant via `RedisCache::clearTenant($tenantId)`
- `type: 'all'` -- iterates tenant IDs 1-5 and clears each

**Cache stats (`cacheStats`):**
- Uses `RedisCache::getStats()` to get `enabled`, `memory_used`, `total_keys`

#### DB Tables Queried

- `tenants` -- `features` JSON column, `configuration` JSON column
- Redis -- cache stats, key deletion

#### Services Used

- `Nexus\Services\RedisCache` -- `delete()`, `clearTenant()`, `getStats()`

#### Shared Components Used

- `PageHeader` -- title "Tenant Features & Modules", Refresh button

---

### 5. Placeholder Pages (Not Yet Migrated)

All other admin routes render the `AdminPlaceholder` component, which displays:
- A "Migration In Progress" message with a construction icon
- A button linking to the legacy PHP admin page at `{API_BASE}{legacyPath}`

These pages are defined in `routes.tsx` using the `P()` helper function.

#### Placeholder Categories and Route Count

| Category | Routes | Examples |
|----------|--------|----------|
| Content | 10 | Blog, Pages, Menus, Categories, Attributes |
| Engagement | 7 | Gamification Hub, Campaigns, Custom Badges, Analytics |
| Matching & Broker | 10 | Smart Matching, Match Approvals, Broker Controls |
| Marketing | 7 | Newsletters, Subscribers, Segments, Templates |
| Advanced | 5 | AI Settings, Feed Algorithm, SEO, 404 Tracking |
| Financial | 8 | Timebanking, Fraud Alerts, Org Wallets, Plans |
| Enterprise | 15 | Roles, GDPR, Legal Documents, Monitoring, Config |
| Federation | 8 | Settings, Partnerships, Directory, API Keys |
| System | 8 | Settings, Cron Jobs, Activity Log, Tests, Seed Generator |
| Community Tools | 13 | Groups, Volunteering, Smart Match Users |
| Deliverability | 4 | Dashboard, List, Create, Analytics |
| Diagnostic | 2 | Matching Diagnostic, Nexus Score Analytics |

#### API Endpoints Already Defined in `adminApi.ts` (Awaiting React Pages)

These API client functions exist but their corresponding React pages are still placeholders:

| API Group | Functions | Status |
|-----------|-----------|--------|
| `adminCategories` | `list`, `create`, `update`, `delete` | Client only (no PHP route registered) |
| `adminAttributes` | `list`, `create`, `update`, `delete` | Client only |
| `adminGamification` | `getStats`, `recheckAll`, `bulkAward`, `listCampaigns`, `createCampaign`, `updateCampaign`, `deleteCampaign` | Client only |
| `adminMatching` | `getConfig`, `updateConfig`, `getApprovals`, `approveMatch`, `rejectMatch`, `clearCache` | Client only |
| `adminTimebanking` | `getStats`, `getAlerts`, `updateAlertStatus`, `adjustBalance`, `getOrgWallets` | Client only |
| `adminSystem` | `getCronJobs`, `runCronJob`, `getActivityLog` | Client only |

---

## Shared Components Reference

All shared components are in `react-frontend/src/admin/components/` and re-exported from `index.ts`.

### AdminLayout

| Prop | Type | Description |
|------|------|-------------|
| -- (wrapper) | -- | Provides sidebar + header + breadcrumbs + `<Outlet />` content area |

Manages `sidebarCollapsed` state (boolean). Sidebar is 64px when collapsed, 256px when expanded. Main content has `ml-64` / `ml-16` offset and `pt-16` for the fixed header.

**File:** `react-frontend/src/admin/AdminLayout.tsx`

### AdminRoute

Route guard component. Renders `<Outlet />` if user is authenticated and has an admin role, otherwise redirects.

**Role check logic:**
```
role === 'admin' || role === 'tenant_admin' || role === 'super_admin'
|| user.is_admin === true || user.is_super_admin === true
```

**File:** `react-frontend/src/admin/AdminRoute.tsx`

### AdminSidebar

| Prop | Type | Description |
|------|------|-------------|
| `collapsed` | `boolean` | Whether sidebar is in collapsed (icon-only) mode |
| `onToggle` | `() => void` | Toggle collapse state |

Renders collapsible navigation sections using `useAdminNav()` hook. Mirrors the PHP admin navigation structure. Sections auto-expand based on current URL. Federation section is conditionally rendered via `hasFeature('federation')`.

**File:** `react-frontend/src/admin/components/AdminSidebar.tsx`

### AdminHeader

| Prop | Type | Description |
|------|------|-------------|
| `sidebarCollapsed` | `boolean` | Adjusts left offset to match sidebar width |

Fixed top bar with "Back to site" button, tenant name, notification bell, and user avatar dropdown (Profile, Sign Out).

**File:** `react-frontend/src/admin/components/AdminHeader.tsx`

### AdminBreadcrumbs

| Prop | Type | Description |
|------|------|-------------|
| `items?` | `BreadcrumbItem[]` | Optional manual breadcrumbs; auto-generates from URL if omitted |

Auto-generates breadcrumbs from the URL path. Strips tenant slug prefix. Maps URL segments to human-readable labels via `SEGMENT_LABELS` lookup. Skips numeric IDs.

**File:** `react-frontend/src/admin/components/AdminBreadcrumbs.tsx`

### PageHeader

| Prop | Type | Description |
|------|------|-------------|
| `title` | `string` | Page heading (h1) |
| `description?` | `string` | Subtitle text |
| `actions?` | `ReactNode` | Right-aligned action buttons |

**File:** `react-frontend/src/admin/components/PageHeader.tsx`

### StatCard

| Prop | Type | Description |
|------|------|-------------|
| `label` | `string` | Metric label |
| `value` | `string \| number` | Metric value (auto-formatted with `toLocaleString`) |
| `icon` | `LucideIcon` | Icon component |
| `trend?` | `number` | Percentage trend (shows up/down arrow) |
| `trendLabel?` | `string` | Text after trend percentage |
| `color?` | `'primary' \| 'success' \| 'warning' \| 'danger' \| 'secondary'` | Theme color |
| `loading?` | `boolean` | Shows skeleton placeholder |

**File:** `react-frontend/src/admin/components/StatCard.tsx`

### DataTable

Generic, reusable admin data table built on HeroUI `Table`.

| Prop | Type | Description |
|------|------|-------------|
| `columns` | `Column<T>[]` | Column definitions (key, label, sortable, render, width) |
| `data` | `T[]` | Row data array |
| `keyField?` | `string` | Unique row key field (default: `'id'`) |
| `isLoading?` | `boolean` | Shows spinner |
| `searchable?` | `boolean` | Show search input (default: true) |
| `searchPlaceholder?` | `string` | Search input placeholder |
| `totalItems?` | `number` | Total count for pagination display |
| `page?` | `number` | Current page |
| `pageSize?` | `number` | Items per page (default: 20) |
| `onPageChange?` | `(page: number) => void` | Page change handler |
| `onSearch?` | `(query: string) => void` | Search handler |
| `onRefresh?` | `() => void` | Refresh button handler |
| `selectable?` | `boolean` | Enable multi-select rows |
| `onSelectionChange?` | `(keys: Set<string>) => void` | Selection change handler |
| `topContent?` | `ReactNode` | Extra content in header area |
| `emptyContent?` | `ReactNode` | Custom empty state |

**Column interface:**

```typescript
interface Column<T> {
  key: string;
  label: string;
  sortable?: boolean;
  render?: (item: T) => ReactNode;
  width?: number;
}
```

Supports client-side sorting via `SortDescriptor`. Pagination via HeroUI `Pagination` component.

**File:** `react-frontend/src/admin/components/DataTable.tsx`

### StatusBadge

| Prop | Type | Description |
|------|------|-------------|
| `status` | `string` | Status string (mapped to color) |

Color mapping:

| Status | Color |
|--------|-------|
| active, approved, completed, published, sent | `success` (green) |
| pending | `warning` (yellow) |
| draft, inactive, idle | `default` (gray) |
| scheduled | `primary` (blue) |
| suspended, banned, rejected, failed | `danger` (red) |

Defined alongside `DataTable` and exported from the same file.

### ConfirmModal

| Prop | Type | Description |
|------|------|-------------|
| `isOpen` | `boolean` | Modal visibility |
| `onClose` | `() => void` | Close handler |
| `onConfirm` | `() => void` | Confirm handler |
| `title` | `string` | Modal heading |
| `message` | `string` | Body text |
| `confirmLabel?` | `string` | Confirm button text (default: "Confirm") |
| `confirmColor?` | `'danger' \| 'warning' \| 'primary'` | Confirm button color (default: "danger") |
| `isLoading?` | `boolean` | Disables buttons, shows spinner |

Used for all destructive actions (delete, ban, suspend) and approval actions.

**File:** `react-frontend/src/admin/components/ConfirmModal.tsx`

### EmptyState

| Prop | Type | Description |
|------|------|-------------|
| `icon?` | `LucideIcon` | Icon (default: `Inbox`) |
| `title` | `string` | Heading |
| `description?` | `string` | Subtext |
| `actionLabel?` | `string` | CTA button text |
| `onAction?` | `() => void` | CTA handler |

**File:** `react-frontend/src/admin/components/EmptyState.tsx`

### AdminPlaceholder

| Prop | Type | Description |
|------|------|-------------|
| `title` | `string` | Module name |
| `description?` | `string` | Module description |
| `legacyPath?` | `string` | Path to legacy PHP admin page (e.g., `/admin/blog`) |

Shows a "Migration In Progress" card with a link to the legacy PHP admin.

**File:** `react-frontend/src/admin/modules/AdminPlaceholder.tsx`

---

## PHP Route Registry

All admin V2 routes are registered in `httpdocs/routes.php`. Current registrations:

```php
// Dashboard
$router->add('GET',  '/api/v2/admin/dashboard/stats',    'AdminDashboardApiController@stats');
$router->add('GET',  '/api/v2/admin/dashboard/trends',   'AdminDashboardApiController@trends');
$router->add('GET',  '/api/v2/admin/dashboard/activity', 'AdminDashboardApiController@activity');

// Users
$router->add('GET',    '/api/v2/admin/users',                 'AdminUsersApiController@index');
$router->add('POST',   '/api/v2/admin/users',                 'AdminUsersApiController@store');
$router->add('GET',    '/api/v2/admin/users/{id}',            'AdminUsersApiController@show');
$router->add('PUT',    '/api/v2/admin/users/{id}',            'AdminUsersApiController@update');
$router->add('DELETE', '/api/v2/admin/users/{id}',            'AdminUsersApiController@destroy');
$router->add('POST',   '/api/v2/admin/users/{id}/approve',    'AdminUsersApiController@approve');
$router->add('POST',   '/api/v2/admin/users/{id}/suspend',    'AdminUsersApiController@suspend');
$router->add('POST',   '/api/v2/admin/users/{id}/ban',        'AdminUsersApiController@ban');
$router->add('POST',   '/api/v2/admin/users/{id}/reactivate', 'AdminUsersApiController@reactivate');
$router->add('POST',   '/api/v2/admin/users/{id}/reset-2fa',  'AdminUsersApiController@reset2fa');

// Listings
$router->add('GET',    '/api/v2/admin/listings',              'AdminListingsApiController@index');
$router->add('GET',    '/api/v2/admin/listings/{id}',         'AdminListingsApiController@show');
$router->add('POST',   '/api/v2/admin/listings/{id}/approve', 'AdminListingsApiController@approve');
$router->add('DELETE', '/api/v2/admin/listings/{id}',         'AdminListingsApiController@destroy');

// Config
$router->add('GET', '/api/v2/admin/config',          'AdminConfigApiController@getConfig');
$router->add('PUT', '/api/v2/admin/config/features',  'AdminConfigApiController@updateFeature');
$router->add('PUT', '/api/v2/admin/config/modules',   'AdminConfigApiController@updateModule');

// Cache
$router->add('GET',  '/api/v2/admin/cache/stats', 'AdminConfigApiController@cacheStats');
$router->add('POST', '/api/v2/admin/cache/clear', 'AdminConfigApiController@clearCache');

// Jobs
$router->add('GET',  '/api/v2/admin/jobs',          'AdminConfigApiController@getJobs');
$router->add('POST', '/api/v2/admin/jobs/{id}/run', 'AdminConfigApiController@runJob');
```

---

## Adding New Admin Pages

Step-by-step guide for developers migrating a placeholder admin page to a fully functional React module.

### Step 1: Create the React Component

Create the page component in the module directory:

```
react-frontend/src/admin/modules/{module}/{PageName}.tsx
```

Follow the established pattern:

```tsx
import { useState, useCallback, useEffect } from 'react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminNewModule } from '../../api/adminApi';
import { DataTable, PageHeader, type Column } from '../../components';
import type { NewModuleItem } from '../../api/types';

export function NewModulePage() {
  usePageTitle('Admin - New Module');
  const toast = useToast();
  const [items, setItems] = useState<NewModuleItem[]>([]);
  const [loading, setLoading] = useState(true);

  const loadItems = useCallback(async () => {
    setLoading(true);
    const res = await adminNewModule.list();
    if (res.success && res.data) {
      // Handle both array and paginated response shapes
      const data = res.data as unknown;
      if (Array.isArray(data)) {
        setItems(data);
      } else if (data && typeof data === 'object') {
        const pd = data as { data: NewModuleItem[]; meta?: { total: number } };
        setItems(pd.data || []);
      }
    }
    setLoading(false);
  }, []);

  useEffect(() => { loadItems(); }, [loadItems]);

  const columns: Column<NewModuleItem>[] = [
    // Define columns...
  ];

  return (
    <div>
      <PageHeader title="New Module" description="Description here" />
      <DataTable columns={columns} data={items} isLoading={loading} />
    </div>
  );
}

export default NewModulePage;
```

### Step 2: Add Route in `routes.tsx`

Replace the placeholder `P()` call with a lazy-loaded component:

```tsx
// At the top of routes.tsx, add the lazy import:
const NewModulePage = lazy(() => import('./modules/{module}/{PageName}'));

// Replace the placeholder route:
// BEFORE:
<Route path="new-module" element={<P title="New Module" path="/admin/new-module" />} />

// AFTER:
<Route path="new-module" element={<Lazy><NewModulePage /></Lazy>} />
```

### Step 3: Add API Endpoints to `adminApi.ts`

```typescript
export const adminNewModule = {
  list: (params: { page?: number; status?: string; search?: string } = {}) =>
    api.get<PaginatedResponse<NewModuleItem>>(
      `/v2/admin/new-module${buildQuery(params)}`
    ),

  get: (id: number) =>
    api.get<NewModuleItem>(`/v2/admin/new-module/${id}`),

  create: (data: CreateNewModulePayload) =>
    api.post<NewModuleItem>('/v2/admin/new-module', data),

  update: (id: number, data: Partial<CreateNewModulePayload>) =>
    api.put<NewModuleItem>(`/v2/admin/new-module/${id}`, data),

  delete: (id: number) =>
    api.delete(`/v2/admin/new-module/${id}`),
};
```

### Step 4: Add Types to `types.ts`

```typescript
export interface NewModuleItem {
  id: number;
  name: string;
  status: 'active' | 'pending' | 'inactive';
  created_at: string;
}

export interface CreateNewModulePayload {
  name: string;
  // ... other fields
}
```

### Step 5: Create PHP V2 Controller

Create `src/Controllers/Api/AdminNewModuleApiController.php`:

```php
<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiErrorCodes;
use Nexus\Models\ActivityLog;

class AdminNewModuleApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function index(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        // Query with tenant scoping
        $total = (int) Database::query(
            "SELECT COUNT(*) as cnt FROM new_module WHERE tenant_id = ?",
            [$tenantId]
        )->fetch()['cnt'];

        $items = Database::query(
            "SELECT * FROM new_module WHERE tenant_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?",
            [$tenantId, $limit, $offset]
        )->fetchAll();

        $this->respondWithPaginatedCollection($items, $total, $page, $limit);
    }
}
```

Key rules:
- Set `protected bool $isV2Api = true`
- Call `$this->requireAdmin()` at the start of every method
- Scope all queries with `TenantContext::getId()`
- Use `respondWithData()` for single resources
- Use `respondWithPaginatedCollection()` for lists
- Use `respondWithError()` for errors with `ApiErrorCodes` constants
- Log admin actions via `ActivityLog::log()`

### Step 6: Register Routes in `httpdocs/routes.php`

Add routes in the admin V2 section:

```php
// New Module
$router->add('GET',    '/api/v2/admin/new-module',      'Nexus\Controllers\Api\AdminNewModuleApiController@index');
$router->add('GET',    '/api/v2/admin/new-module/{id}',  'Nexus\Controllers\Api\AdminNewModuleApiController@show');
$router->add('POST',   '/api/v2/admin/new-module',       'Nexus\Controllers\Api\AdminNewModuleApiController@store');
$router->add('PUT',    '/api/v2/admin/new-module/{id}',  'Nexus\Controllers\Api\AdminNewModuleApiController@update');
$router->add('DELETE', '/api/v2/admin/new-module/{id}',  'Nexus\Controllers\Api\AdminNewModuleApiController@destroy');
```

### Step 7: Verify TypeScript Compilation

Run the TypeScript compiler to catch any type errors before committing:

```bash
cd react-frontend
npx tsc --noEmit
```

Also run the linter:

```bash
npm run lint
```

### Checklist

- [ ] React component created in `admin/modules/{module}/`
- [ ] `usePageTitle()` hook added
- [ ] Route updated in `routes.tsx` (lazy import, replaced placeholder)
- [ ] API functions added to `adminApi.ts`
- [ ] TypeScript types added to `types.ts`
- [ ] PHP controller extends `BaseApiController` with `$isV2Api = true`
- [ ] `requireAdmin()` called in every controller method
- [ ] All queries scoped by `TenantContext::getId()`
- [ ] Routes registered in `httpdocs/routes.php`
- [ ] Admin actions logged via `ActivityLog::log()`
- [ ] `npx tsc --noEmit` passes
- [ ] `npm run lint` passes

---

## Key File Reference

| Purpose | File Path |
|---------|-----------|
| Admin API client | `react-frontend/src/admin/api/adminApi.ts` |
| Admin TypeScript types | `react-frontend/src/admin/api/types.ts` |
| Admin route definitions | `react-frontend/src/admin/routes.tsx` |
| Admin route guard | `react-frontend/src/admin/AdminRoute.tsx` |
| Admin layout shell | `react-frontend/src/admin/AdminLayout.tsx` |
| Shared components barrel | `react-frontend/src/admin/components/index.ts` |
| Core API client | `react-frontend/src/lib/api.ts` |
| PHP route registry | `httpdocs/routes.php` |
| PHP base controller | `src/Controllers/Api/BaseApiController.php` |
| Dashboard API controller | `src/Controllers/Api/AdminDashboardApiController.php` |
| Users API controller | `src/Controllers/Api/AdminUsersApiController.php` |
| Listings API controller | `src/Controllers/Api/AdminListingsApiController.php` |
| Config API controller | `src/Controllers/Api/AdminConfigApiController.php` |
| User model | `src/Models/User.php` |
| ActivityLog model | `src/Models/ActivityLog.php` |
| Redis cache service | `src/Services/RedisCache.php` |
