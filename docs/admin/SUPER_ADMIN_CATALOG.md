# Super Admin Panel - Complete Feature Catalog

**Generated:** 2026-02-18
**System:** Project NEXUS - Multi-Tenant Hierarchy Management
**Access URL:** `/super-admin/*`

---

## Executive Summary

The Super Admin Panel is a **hierarchical infrastructure management system** for managing multi-tenant organizations. Unlike the regular admin panel (platform features), this panel manages the **tenant hierarchy itself** — creating sub-tenants, moving users between tenants, and controlling federation.

**Key Distinction:**
- **Platform Admin** (`/admin-legacy/*`, `/admin/*`): Manages features within ONE tenant
- **Super Admin** (`/super-admin/*`): Manages the TENANT HIERARCHY across multiple tenants

---

## Access Control System

### 🔐 Authentication & Authorization

**Middleware:** `SuperPanelAccess` (`src/Middleware/SuperPanelAccess.php`)

**Access Rules:**

| User Type | Tenant Type | Access Level | Scope |
|-----------|-------------|--------------|-------|
| **Master Super Admin** | Master Tenant (ID=1) + `is_tenant_super_admin=1` | **GLOBAL** | All tenants system-wide |
| **Regional Super Admin** | Hub Tenant (`allows_subtenants=1`) + `is_tenant_super_admin=1` | **SUBTREE** | Own tenant + all descendants |
| **Standard Admin** | Leaf Tenant (`allows_subtenants=0`) | **NONE** | No Super Panel access (Platform Admin only) |

**Key Concepts:**
- Same panel interface for all super admins
- Data shown is **filtered by hierarchy position**
- Master admins see everything; Regional admins see their subtree only
- `is_tenant_super_admin` flag is the gatekeeper (different from legacy `is_super_admin`)

---

## Route Inventory

### Dashboard & Overview

| Route | Method | Controller | View | Purpose |
|-------|--------|------------|------|---------|
| `/super-admin` | GET | `DashboardController@index` | `super-admin/dashboard.php` | Infrastructure overview dashboard |
| `/super-admin/dashboard` | GET | `DashboardController@index` | `super-admin/dashboard.php` | Same as above (alias) |

**Features:**
- Stats grid: Total tenants, active tenants, total users, super admins, hub tenants
- Tenant hierarchy table with indented names showing parent-child relationships
- Access scope display (Master/Regional, tenant path, scope)
- Quick actions: Create tenant, view super admins, back to Platform Admin

---

### Tenant Management

#### List & View Routes

| Route | Method | Controller | View | Purpose |
|-------|--------|------------|------|---------|
| `/super-admin/tenants` | GET | `TenantController@index` | `tenants/index.php` | List all visible tenants |
| `/super-admin/tenants/hierarchy` | GET | `TenantController@hierarchy` | `tenants/hierarchy.php` | Visual hierarchy tree view |
| `/super-admin/tenants/{id}` | GET | `TenantController@show` | `tenants/show.php` | View single tenant details |

**Index Features:**
- Search filter (name/domain)
- Hub tenants filter (checkbox)
- Status filter (active/inactive dropdown)
- Table columns: Tenant, Slug, Domain, Parent, Users, Hub status, Active status, Actions
- Quick actions per row: View, Edit, Add Sub-tenant (if Hub)

**Hierarchy View:**
- Visual tree with drag-and-drop support (planned)
- Shows depth levels and parent-child relationships

**Show (Detail) Features:**
- Full tenant details (name, slug, domain, tagline, description)
- SEO fields (meta_title, meta_description, og_image_url)
- Location info (country, service area, coordinates)
- Social media links (Facebook, Twitter, Instagram, LinkedIn, YouTube)
- Contact details (email, phone, address)
- Configuration: Hub status, max depth, active status
- Children list (direct sub-tenants)
- Admins list (tenant super admins)
- Breadcrumb trail showing hierarchy path
- Actions: Edit, Toggle Hub, Move, Reactivate/Deactivate

#### CRUD Routes

| Route | Method | Controller | View/Action | Purpose |
|-------|--------|------------|-------------|---------|
| `/super-admin/tenants/create` | GET | `TenantController@create` | `tenants/create.php` | Show create tenant form |
| `/super-admin/tenants/store` | POST | `TenantController@store` | Redirect | Create new tenant |
| `/super-admin/tenants/{id}/edit` | GET | `TenantController@edit` | `tenants/edit.php` | Show edit form |
| `/super-admin/tenants/{id}/update` | POST | `TenantController@update` | Redirect | Update tenant |
| `/super-admin/tenants/{id}/delete` | POST | `TenantController@delete` | Redirect | Deactivate/delete tenant |
| `/super-admin/tenants/{id}/reactivate` | POST | `TenantController@reactivate` | Redirect | Re-enable inactive tenant |
| `/super-admin/tenants/{id}/toggle-hub` | POST | `TenantController@toggleHub` | Redirect | Enable/disable sub-tenant capability |
| `/super-admin/tenants/{id}/move` | POST | `TenantController@move` | Redirect | Move tenant to new parent (re-parent) |

**Create Form Fields:**
- **Required:** Parent tenant (dropdown), Name, Slug
- **Optional:** Domain, Tagline, Description
- **Configuration:** Allow sub-tenants (checkbox), Max depth (number), Active status (checkbox)
- **Validation:** Slug must be lowercase, alphanumeric + hyphens only

**Edit Form Fields:**
- All create fields plus:
- **Contact:** Email, Phone, Address
- **SEO:** Meta title, Meta description, H1 headline, Hero intro, OG image URL, Robots directive
- **Location:** Location name, Country code, Service area, Latitude, Longitude
- **Social Media:** Facebook, Twitter, Instagram, LinkedIn, YouTube URLs
- **Legal:** Privacy text, Terms text (JSON configuration fields)
- **Platform Modules:** Listings, Groups, Wallet, Volunteering, Events, Resources, Polls, Goals, Blog, Help Center (feature flags)
- **Re-parent:** Move to new parent tenant (dropdown)

**Delete Behavior:**
- Soft delete by default (sets `is_active=0`)
- Optional hard delete (checkbox, requires confirmation)
- Cannot delete Master tenant (ID=1)
- Checks for children before allowing delete

---

### User Management

#### List & View Routes

| Route | Method | Controller | View | Purpose |
|-------|--------|------------|------|---------|
| `/super-admin/users` | GET | `UserController@index` | `users/index.php` | List users across tenants |
| `/super-admin/users?super_admins=1` | GET | `UserController@index` | `users/index.php` | Filter: Super admins only |
| `/super-admin/users/{id}` | GET | `UserController@show` | `users/show.php` | View user details |

**Index Features:**
- Filters: Search (name/email), Tenant (dropdown), Role (dropdown), Super admins only (flag)
- Table columns: Name, Email, Tenant, Role, Super Admin badge, Status, Actions
- Pagination: 100 users per page (limit)

**Show Features:**
- User profile: Name, Email, Phone, Location
- Tenant: Name, Domain, Parent path
- Role and super admin status badges
- Can manage flag (based on hierarchy permissions)
- Actions: Edit, Grant/Revoke Super Admin, Move Tenant, Move & Promote

#### CRUD & Permission Routes

| Route | Method | Controller | View/Action | Purpose |
|-------|--------|------------|-------------|---------|
| `/super-admin/users/create` | GET | `UserController@create` | `users/create.php` | Show create user form |
| `/super-admin/users/store` | POST | `UserController@store` | Redirect | Create new user |
| `/super-admin/users/{id}/edit` | GET | `UserController@edit` | `users/edit.php` | Show edit form |
| `/super-admin/users/{id}/update` | POST | `UserController@update` | Redirect | Update user details |
| `/super-admin/users/{id}/grant-super-admin` | POST | `UserController@grantSuperAdmin` | Redirect | Grant tenant super admin privileges |
| `/super-admin/users/{id}/revoke-super-admin` | POST | `UserController@revokeSuperAdmin` | Redirect | Revoke tenant super admin privileges |
| `/super-admin/users/{id}/grant-global-super-admin` | POST | `UserController@grantGlobalSuperAdmin` | Redirect | **GOD MODE ONLY**: Grant global super admin |
| `/super-admin/users/{id}/revoke-global-super-admin` | POST | `UserController@revokeGlobalSuperAdmin` | Redirect | **GOD MODE ONLY**: Revoke global super admin |
| `/super-admin/users/{id}/move-tenant` | POST | `UserController@moveTenant` | Redirect | Move user to different tenant |
| `/super-admin/users/{id}/move-and-promote` | POST | `UserController@moveAndPromote` | Redirect | **KEY WORKFLOW**: Move to Hub + grant Super Admin |

**Create Form Fields:**
- **Required:** Tenant (dropdown), First name, Email, Password
- **Optional:** Last name, Location, Phone
- **Configuration:** Role (dropdown), Is tenant super admin (checkbox - only for Hub tenants)

**Edit Form Fields:**
- First name, Last name, Email, Role, Location, Phone
- Super admin controls (Grant/Revoke buttons)
- Tenant move controls (dropdown + submit)
- Hub tenant promotion (Move & Promote combo button)

**Permission Hierarchy:**
- `is_tenant_super_admin`: Regional super admin (can manage own tenant + descendants)
- `is_super_admin`: **Global super admin** (GOD MODE - can access ALL tenants, only Master admins can grant/revoke)

**Move & Promote Workflow:**
- **Purpose:** Master admins create regional super admins
- **Process:**
  1. Moves user + ALL their content to target Hub tenant
  2. Grants `is_tenant_super_admin=1` and sets `role='tenant_admin'`
  3. Target tenant MUST be a Hub (`allows_subtenants=1`)
- **Use Case:** Promote a member to regional super admin for a specific subtree

---

### Bulk Operations

| Route | Method | Controller | View/Action | Purpose |
|-------|--------|------------|-------------|---------|
| `/super-admin/bulk` | GET | `BulkController@index` | `bulk/index.php` | Bulk operations dashboard |
| `/super-admin/bulk/move-users` | POST | `BulkController@moveUsers` | Redirect | Move multiple users to new tenant |
| `/super-admin/bulk/update-tenants` | POST | `BulkController@updateTenants` | Redirect | Bulk enable/disable tenants or Hub status |

**Bulk Move Users Features:**
- **Source tenant filter:** Dropdown to load users from specific tenant
- **User selection:** Multi-select checkboxes with live count
- **Target tenant:** Dropdown (all visible tenants)
- **Grant super admin:** Checkbox (only works if target is Hub)
- **Process:** Moves users + all their content (listings, posts, transactions, etc.)
- **Audit log:** Records all moved users with count

**Bulk Tenant Operations:**
- **Actions:**
  - Activate (set `is_active=1`)
  - Deactivate (set `is_active=0`)
  - Enable Hub (set `allows_subtenants=1`, `max_depth=2`)
  - Disable Hub (set `allows_subtenants=0`, `max_depth=0`)
- **Protection:** Cannot modify Master tenant (ID=1)
- **Permissions:** Must have manage access to each tenant
- **Audit log:** Records all updated tenants with action type

---

### Audit Logging

| Route | Method | Controller | View | Purpose |
|-------|--------|------------|------|---------|
| `/super-admin/audit` | GET | `AuditController@index` | `audit/index.php` | View audit log |

**Service:** `SuperAdminAuditService` (`src/Services/SuperAdminAuditService.php`)

**Features:**
- **Filters:** Action type, Target type (tenant/user/bulk), Search, Date range
- **Pagination:** 50 logs per page
- **Stats:** 30-day summary with counts by action type
- **Log fields:**
  - Action type (e.g., `tenant_created`, `user_moved`, `bulk_users_moved`)
  - Target type and ID
  - Actor (who performed the action)
  - Before/After data (JSON)
  - Description
  - Timestamp

**Audit Log Actions:**
- `tenant_created`, `tenant_updated`, `tenant_deleted`, `tenant_moved`, `tenant_hub_toggled`, `tenant_reactivated`
- `user_created`, `user_moved`, `super_admin_granted`, `super_admin_revoked`
- `bulk_users_moved`, `bulk_tenants_updated`

---

### Federation Control Center

**Note:** Federation is the multi-community network feature allowing partnerships between tenants.

#### Federation Routes

| Route | Method | Controller | View | Purpose |
|-------|--------|------------|------|---------|
| `/super-admin/federation` | GET | `FederationController@index` | `federation/index.php` | Federation control dashboard |
| `/super-admin/federation/system-controls` | GET | `FederationController@systemControls` | `federation/system-controls.php` | **MASTER ONLY**: Global kill switch & features |
| `/super-admin/federation/whitelist` | GET | `FederationController@whitelist` | `federation/whitelist.php` | Tenant whitelist management |
| `/super-admin/federation/partnerships` | GET | `FederationController@partnerships` | `federation/partnerships.php` | View all partnerships |
| `/super-admin/federation/audit` | GET | `FederationController@auditLog` | `federation/audit-log.php` | Federation activity log |
| `/super-admin/federation/tenant/{id}` | GET | `FederationController@tenantFeatures` | `federation/tenant-features.php` | Per-tenant federation settings |

**Dashboard Features:**
- **System status:** Federation ON/OFF, Whitelist count, Active partnerships, Pending requests
- **Emergency lockdown banner:** Shows if active, with lift button (Master only)
- **Global feature status grid:**
  - Cross-tenant profiles
  - Cross-tenant messaging
  - Cross-tenant transactions
  - Cross-tenant listings
  - Cross-tenant events
  - Cross-tenant groups
- **Whitelisted tenants table:** Last 5 with approval date
- **Recent partnerships table:** Last 5 with status badges
- **Critical events:** Recent high-severity federation events
- **Recent activity:** Last 10 audit log entries
- **30-day analytics:**
  - Total actions
  - Messages count
  - Transactions count
  - Profile views count
  - Critical events count
  - Most active partnerships

#### Federation Actions (AJAX)

| Route | Method | Controller | Purpose |
|-------|--------|------------|---------|
| `/super-admin/federation/update-system-controls` | POST | `FederationController@updateSystemControls` | **MASTER ONLY**: Update global feature toggles |
| `/super-admin/federation/emergency-lockdown` | POST | `FederationController@emergencyLockdown` | **MASTER ONLY**: Disable all federation immediately |
| `/super-admin/federation/lift-lockdown` | POST | `FederationController@liftLockdown` | **MASTER ONLY**: Re-enable federation after lockdown |
| `/super-admin/federation/add-to-whitelist` | POST | `FederationController@addToWhitelist` | Add tenant to whitelist (allow federation) |
| `/super-admin/federation/remove-from-whitelist` | POST | `FederationController@removeFromWhitelist` | Remove tenant from whitelist |
| `/super-admin/federation/suspend-partnership` | POST | `FederationController@suspendPartnership` | Suspend a partnership temporarily |
| `/super-admin/federation/terminate-partnership` | POST | `FederationController@terminatePartnership` | Terminate a partnership permanently |
| `/super-admin/federation/update-tenant-feature` | POST | `FederationController@updateTenantFeature` | Toggle federation feature for specific tenant |

**System Controls (Master Only):**
- **Global kill switch:** `federation_enabled` (master ON/OFF)
- **Whitelist mode:** `whitelist_mode_enabled` (only whitelisted tenants can federate)
- **Max federation level:** 0-4 (depth limit for partnerships)
- **Cross-tenant features:** Individual toggles for Profiles, Messaging, Transactions, Listings, Events, Groups

**Emergency Lockdown:**
- **Purpose:** Immediately disable all federation system-wide
- **Trigger:** Security incident, system maintenance, abuse detected
- **Effect:** All cross-tenant features disabled, all partnerships suspended
- **Records:** Reason, timestamp, actor in audit log
- **Critical event:** Logged at highest severity level

**Whitelist Management:**
- **Purpose:** Pre-approve tenants for federation in whitelist mode
- **Fields:** Tenant ID, Approved by (super admin), Notes, Approval timestamp
- **Effect:** Whitelisted tenants can request/accept partnerships when whitelist mode is ON
- **Use case:** Gradual federation rollout, trusted partners only

**Partnership Oversight:**
- **View all partnerships:** Across all tenants (filtered by hierarchy permissions)
- **Stats:** Active, Pending, Suspended, Terminated counts
- **Suspend:** Temporarily disable a partnership (reversible)
- **Terminate:** Permanently end a partnership (cannot be reversed without new request)
- **Use case:** Abuse moderation, policy violations

**Federation Audit Log:**
- **Categories:** System control, Whitelist, Partnership, Messaging, Transaction, Profile, Listing, Event, Group
- **Levels:** Info, Warning, Critical
- **Filters:** Category, Level, Date range, Search
- **Stats:** 30-day summary by category and level
- **Most active pairs:** Top 5 tenant pairs by cross-tenant activity

---

## API Endpoints

### Tenant APIs

| Route | Method | Controller | Response | Purpose |
|-------|--------|------------|----------|---------|
| `/super-admin/api/tenants` | GET | `TenantController@apiList` | JSON | Get all visible tenants |
| `/super-admin/api/tenants/hierarchy` | GET | `TenantController@apiHierarchy` | JSON | Get hierarchy tree structure |

**Response Format:**
```json
{
  "success": true,
  "tenants": [
    {
      "id": 1,
      "name": "Master Tenant",
      "slug": "master",
      "domain": "app.project-nexus.ie",
      "parent_id": null,
      "depth": 0,
      "path": "/1/",
      "allows_subtenants": true,
      "is_active": true,
      "user_count": 150,
      "direct_children": 5,
      "relationship": "self",
      "can_manage": true
    }
  ]
}
```

### User APIs

| Route | Method | Controller | Response | Purpose |
|-------|--------|------------|----------|---------|
| `/super-admin/api/users/search` | GET | `UserController@apiSearch` | JSON | Search users across tenants |

**Query Parameters:**
- `q`: Search query (name/email)
- `tenant_id`: Filter by tenant
- `limit`: Max results (default 20)

**Response Format:**
```json
{
  "success": true,
  "users": [
    {
      "id": 42,
      "first_name": "John",
      "last_name": "Doe",
      "email": "john@example.com",
      "tenant_id": 2,
      "tenant_name": "Regional Hub",
      "role": "tenant_admin",
      "is_tenant_super_admin": true
    }
  ]
}
```

### Bulk APIs

| Route | Method | Controller | Response | Purpose |
|-------|--------|------------|----------|---------|
| `/super-admin/api/bulk/users` | GET | `BulkController@apiGetUsers` | JSON | Get users for bulk selection |

**Query Parameters:**
- `tenant_id`: Filter by tenant
- `q`: Search query
- `limit`: Max results (default 100)

### Audit APIs

| Route | Method | Controller | Response | Purpose |
|-------|--------|------------|----------|---------|
| `/super-admin/api/audit` | GET | `AuditController@apiLog` | JSON | Get audit log entries |

**Query Parameters:**
- `action_type`: Filter by action
- `target_type`: Filter by target (tenant/user/bulk)
- `q`: Search query
- `limit`: Max results (default 50, max 100)
- `offset`: Pagination offset

---

## Services & Business Logic

### Core Services

| Service | File | Purpose |
|---------|------|---------|
| **TenantHierarchyService** | `src/Services/TenantHierarchyService.php` | Tenant CRUD, hierarchy operations, validation |
| **TenantVisibilityService** | `src/Services/TenantVisibilityService.php` | Hierarchy-based data filtering, access control |
| **SuperAdminAuditService** | `src/Services/SuperAdminAuditService.php` | Audit logging for all Super Admin actions |

### TenantHierarchyService Methods

| Method | Purpose |
|--------|---------|
| `createTenant($data, $parentId)` | Create new sub-tenant with hierarchy validation |
| `updateTenant($tenantId, $data)` | Update tenant details (all edit form fields) |
| `deleteTenant($tenantId, $hardDelete)` | Soft or hard delete tenant |
| `moveTenant($tenantId, $newParentId)` | Re-parent tenant (move in hierarchy) |
| `toggleSubtenantCapability($tenantId, $enable)` | Enable/disable Hub status |
| `assignTenantSuperAdmin($userId, $tenantId)` | Grant super admin privileges |
| `revokeTenantSuperAdmin($userId)` | Revoke super admin privileges |

**Validation Rules:**
- Cannot create deeper than `max_depth` of parent
- Cannot make a tenant with children into a leaf (non-Hub)
- Cannot move a tenant to its own descendant (circular reference)
- Cannot delete Master tenant (ID=1)
- Slug must be unique system-wide

### TenantVisibilityService Methods

| Method | Purpose |
|--------|---------|
| `getTenantList($filters)` | Get all tenants visible to current super admin |
| `getHierarchyTree()` | Get full hierarchy tree (filtered by permissions) |
| `getTenant($tenantId)` | Get single tenant if accessible |
| `getAvailableParents()` | Get Hub tenants that can have children |
| `getTenantAdmins($tenantId)` | Get super admins for a tenant |
| `getUserList($filters)` | Get users visible to current super admin |
| `getDashboardStats()` | Get stats for dashboard (counts) |

**Filtering Logic:**
- **Master admins:** See ALL tenants (no filtering)
- **Regional admins:** See own tenant + all descendants in the hierarchy path
- Uses SQL path matching: `WHERE path LIKE '/1/42/%' OR id = 42`

### SuperAdminAuditService Methods

| Method | Purpose |
|--------|---------|
| `log($actionType, $targetType, $targetId, $targetName, $before, $after, $description)` | Create audit log entry |
| `getLog($filters)` | Retrieve audit log with filters |
| `getStats($days)` | Get stats summary for N days |

**Audit Log Schema:**
- `action_type`: e.g., tenant_created, user_moved
- `target_type`: tenant, user, bulk
- `target_id`: ID of affected record
- `target_name`: Human-readable name
- `actor_id`: Who performed the action
- `before_data`: JSON snapshot before change
- `after_data`: JSON snapshot after change
- `description`: Human-readable summary
- `created_at`: Timestamp

---

## Federation Services

| Service | File | Purpose |
|---------|------|---------|
| **FederationFeatureService** | `src/Services/FederationFeatureService.php` | System controls, whitelist, global toggles |
| **FederationPartnershipService** | `src/Services/FederationPartnershipService.php` | Partnership CRUD, suspend, terminate |
| **FederationAuditService** | `src/Services/FederationAuditService.php` | Federation-specific audit logging |

### FederationFeatureService Methods

| Method | Purpose |
|--------|---------|
| `getSystemControls()` | Get all global federation settings |
| `triggerEmergencyLockdown($actorId, $reason)` | Disable all federation immediately |
| `liftEmergencyLockdown($actorId)` | Re-enable federation after lockdown |
| `getWhitelistedTenants()` | Get all whitelisted tenants |
| `addToWhitelist($tenantId, $approvedBy, $notes)` | Add tenant to whitelist |
| `removeFromWhitelist($tenantId, $removedBy)` | Remove tenant from whitelist |
| `isTenantWhitelisted($tenantId)` | Check if tenant is whitelisted |
| `enableTenantFeature($feature, $tenantId)` | Enable feature for specific tenant |
| `disableTenantFeature($feature, $tenantId)` | Disable feature for specific tenant |
| `getAllTenantFeatures($tenantId)` | Get all federation features for tenant |
| `clearCache()` | Clear Redis cache for federation settings |

**Global System Control Fields:**
- `federation_enabled` (boolean)
- `whitelist_mode_enabled` (boolean)
- `max_federation_level` (int 0-4)
- `cross_tenant_profiles_enabled` (boolean)
- `cross_tenant_messaging_enabled` (boolean)
- `cross_tenant_transactions_enabled` (boolean)
- `cross_tenant_listings_enabled` (boolean)
- `cross_tenant_events_enabled` (boolean)
- `cross_tenant_groups_enabled` (boolean)
- `emergency_lockdown_active` (boolean)
- `emergency_lockdown_reason` (text)
- `updated_by` (user_id)

### FederationPartnershipService Methods

| Method | Purpose |
|--------|---------|
| `getAllPartnerships($filters, $limit)` | Get all partnerships (filtered by hierarchy) |
| `getTenantPartnerships($tenantId)` | Get partnerships for specific tenant |
| `getStats()` | Get partnership counts by status |
| `suspendPartnership($partnershipId, $actorId, $reason)` | Temporarily suspend partnership |
| `terminatePartnership($partnershipId, $actorId, $reason)` | Permanently end partnership |

**Partnership Statuses:**
- `pending`: Request sent, awaiting approval
- `active`: Both tenants approved, partnership active
- `suspended`: Temporarily disabled by super admin
- `terminated`: Permanently ended

### FederationAuditService Methods

| Method | Purpose |
|--------|---------|
| `log($actionType, $tenantIdA, $tenantIdB, $actorId, $metadata, $level)` | Create federation audit entry |
| `getLog($filters)` | Retrieve federation audit log |
| `getStats($days)` | Get stats summary for N days |
| `getRecentCritical($limit)` | Get recent critical severity events |

**Audit Categories:**
- `system_control`: Global federation settings changes
- `whitelist`: Whitelist add/remove
- `partnership`: Partnership create/suspend/terminate
- `messaging`: Cross-tenant messages
- `transaction`: Cross-tenant time credit transfers
- `profile`: Cross-tenant profile views
- `listing`: Cross-tenant listing views
- `event`: Cross-tenant event RSVPs
- `group`: Cross-tenant group joins

**Severity Levels:**
- `INFO`: Normal operations
- `WARNING`: Configuration changes, suspensions
- `CRITICAL`: Emergency lockdown, security events, terminations

---

## Database Tables

### Tenant Hierarchy Tables

| Table | Primary Use | Key Columns |
|-------|-------------|-------------|
| `tenants` | Core tenant data | `id`, `name`, `slug`, `domain`, `parent_id`, `path`, `depth`, `allows_subtenants`, `max_depth`, `is_active` |
| `users` | User accounts | `id`, `tenant_id`, `email`, `role`, `is_tenant_super_admin`, `is_super_admin` |

**Hierarchy Fields:**
- `parent_id`: Direct parent tenant ID (null for Master)
- `path`: Full hierarchy path (e.g., `/1/42/99/`) for subtree queries
- `depth`: Distance from root (Master = 0)
- `allows_subtenants`: Can this tenant create sub-tenants? (Hub flag)
- `max_depth`: Maximum depth this tenant's children can go

### Audit Tables

| Table | Primary Use | Key Columns |
|-------|-------------|-------------|
| `super_admin_audit_log` | Super admin actions | `id`, `action_type`, `target_type`, `target_id`, `actor_id`, `before_data`, `after_data`, `description`, `created_at` |
| `federation_audit_log` | Federation activity | `id`, `action_type`, `category`, `level`, `tenant_id_a`, `tenant_id_b`, `actor_id`, `metadata`, `created_at` |

### Federation Tables

| Table | Primary Use | Key Columns |
|-------|-------------|-------------|
| `federation_system_control` | Global federation settings | `id`, `federation_enabled`, `whitelist_mode_enabled`, `max_federation_level`, `cross_tenant_*_enabled` fields, `emergency_lockdown_active` |
| `federation_tenant_whitelist` | Approved tenants for federation | `id`, `tenant_id`, `approved_by`, `notes`, `approved_at` |
| `federation_partnerships` | Tenant partnerships | `id`, `tenant_id`, `partner_tenant_id`, `status`, `federation_level`, `initiated_by`, `created_at` |

---

## View Components

### Partials

| File | Purpose |
|------|---------|
| `views/super-admin/partials/header.php` | Super Admin panel header with nav, breadcrumbs, user dropdown |
| `views/super-admin/partials/footer.php` | Super Admin panel footer with scripts |

**Header Features:**
- Super Admin branding with icon
- Main navigation: Dashboard, Tenants, Users, Bulk Operations, Audit, Federation
- User dropdown: Profile, Settings, Logout
- Access level badge (Master/Regional)
- Current tenant name display

### Full View Files

| Category | View Files | Count |
|----------|------------|-------|
| **Tenants** | `index.php`, `create.php`, `edit.php`, `show.php`, `hierarchy.php` | 5 |
| **Users** | `index.php`, `create.php`, `edit.php`, `show.php` | 4 |
| **Bulk** | `index.php` | 1 |
| **Audit** | `index.php` | 1 |
| **Federation** | `index.php`, `system-controls.php`, `whitelist.php`, `partnerships.php`, `audit-log.php`, `tenant-features.php` | 6 |
| **Partials** | `header.php`, `footer.php` | 2 |
| **Total** | | **19 views** |

---

## CSS & Styling

**Style Namespace:** All Super Admin styles use `super-*` prefix to avoid conflicts with Platform Admin.

**CSS Classes:**

| Class Prefix | Purpose | Examples |
|--------------|---------|----------|
| `super-page-*` | Page layout | `super-page-header`, `super-page-title`, `super-page-subtitle`, `super-page-actions` |
| `super-card-*` | Card components | `super-card`, `super-card-header`, `super-card-title`, `super-card-body` |
| `super-table-*` | Table styling | `super-table`, `super-table-link` |
| `super-btn-*` | Buttons | `super-btn`, `super-btn-primary`, `super-btn-secondary`, `super-btn-sm`, `super-btn-danger` |
| `super-form-*` | Forms | `super-form-group`, `super-form-label`, `super-form-input`, `super-form-select`, `super-form-help`, `super-form-checkbox` |
| `super-badge-*` | Status badges | `super-badge`, `super-badge-success`, `super-badge-danger`, `super-badge-warning`, `super-badge-info`, `super-badge-secondary`, `super-badge-purple` |
| `super-alert-*` | Alerts/Notices | `super-alert`, `super-alert-success`, `super-alert-danger` |
| `super-stats-*` | Statistics | `super-stats-grid`, `super-stat-card`, `super-stat-icon`, `super-stat-value`, `super-stat-label` |
| `super-breadcrumb-*` | Breadcrumbs | `super-breadcrumb`, `super-breadcrumb-sep` |

**Color Palette:**
- Primary: Purple/Indigo gradient (`#8b5cf6`, `#6366f1`)
- Success: Green (`#22c55e`)
- Danger: Red (`#dc2626`)
- Warning: Amber (`#f59e0b`)
- Info: Blue (`#3b82f6`)
- Secondary: Gray (`#6b7280`)

**Layout:**
- Responsive grid: `grid-template-columns: repeat(auto-fit, minmax(300px, 1fr))`
- Card padding: `1.5rem`
- Form spacing: `1rem` gaps
- Stats grid: 5 columns on desktop, responsive

---

## Security & Permissions

### Permission Checks

| Permission Check | Method | Used For |
|------------------|--------|----------|
| **Panel Access** | `SuperPanelAccess::check()` | Gate all Super Admin routes |
| **Tenant Access** | `SuperPanelAccess::canAccessTenant($tenantId)` | View tenant details |
| **Tenant Manage** | `SuperPanelAccess::canManageTenant($tenantId)` | Edit/delete tenant |
| **Create Subtenant** | `SuperPanelAccess::canCreateSubtenantUnder($parentId)` | Create new tenant |
| **GOD Mode** | `User::isGod()` | Grant/revoke global super admin |

### Hierarchical Access Rules

**Master Super Admin (Level: Master):**
- ✅ Can access ALL tenants
- ✅ Can manage ALL tenants (edit, delete, move)
- ✅ Can create tenants under ANY Hub tenant
- ✅ Can grant/revoke global super admin (GOD mode)
- ✅ Can control Federation system settings
- ✅ Can trigger emergency lockdown

**Regional Super Admin (Level: Regional):**
- ✅ Can access OWN tenant + all descendants
- ✅ Can manage own tenant + descendants (cannot manage parent or siblings)
- ✅ Can create sub-tenants under own tenant (if Hub)
- ❌ Cannot grant/revoke global super admin
- ❌ Cannot control Federation system settings
- ❌ Cannot trigger emergency lockdown
- ✅ Can view Federation partnerships in their scope
- ✅ Can manage whitelisted tenants in their scope

### CSRF Protection

**All POST routes require CSRF token:**
- Token generated: `Csrf::token()`
- Form field: `<?= Csrf::field() ?>`
- Verification: `Csrf::verifyOrDie()` (redirects with 403)
- AJAX verification: `Csrf::verifyOrDieJson()` (returns JSON 403)

### SQL Injection Protection

**All queries use prepared statements:**
```php
Database::query("SELECT * FROM tenants WHERE id = ?", [$tenantId]);
```

**Never concatenate user input into SQL.**

---

## Special Features

### 🔥 Move & Promote Workflow

**Purpose:** Master admins create regional super admins in one action.

**Route:** `POST /super-admin/users/{id}/move-and-promote`

**Process:**
1. Validates source tenant access (must be able to manage current tenant)
2. Validates target tenant access (must be able to access target)
3. Validates target is Hub tenant (`allows_subtenants=1`)
4. Moves user AND all their content to target tenant:
   - User record
   - Listings
   - Transactions
   - Posts
   - Comments
   - All user-generated content
5. Grants super admin privileges:
   - Sets `is_tenant_super_admin=1`
   - Sets `role='tenant_admin'`
6. Logs audit entry with before/after data

**Use Case:**
```
Scenario: Master admin wants to create a regional super admin for "Ireland Hub"

1. User "John Doe" is currently a member in "Dublin Timebank" (leaf tenant)
2. Master admin uses "Move & Promote" action
3. Selects target: "Ireland Hub" (Hub tenant, parent of Dublin)
4. System moves John + all his content to Ireland Hub
5. John is now super admin for Ireland Hub, can manage all Irish sub-tenants
```

### 🌲 Hierarchy Tree Query Pattern

**Path-based querying:**
```sql
-- Master admin (sees all):
SELECT * FROM tenants

-- Regional admin (sees subtree):
SELECT * FROM tenants
WHERE path LIKE '/1/42/%' OR id = 42
ORDER BY path
```

**Path format:** `/1/42/99/` = Master → Regional Hub → Sub-tenant

**Indented display:**
```php
$indentedName = str_repeat('— ', $depth) . $name;
```

**Result:**
```
Master Tenant
— Regional Hub Ireland
—— Dublin Timebank
——— South Dublin Community
—— Cork Timebank
— Regional Hub UK
—— London Timebank
```

### 🚨 Emergency Lockdown

**Trigger:** Security incident, abuse, system maintenance

**Effect:**
1. Sets `federation_enabled=0`
2. Sets all `cross_tenant_*_enabled=0`
3. Sets `emergency_lockdown_active=1`
4. Records `emergency_lockdown_reason`
5. Logs critical audit event
6. Displays red banner on all Federation pages

**Lift:**
1. Master admin confirms via modal
2. Sets `emergency_lockdown_active=0`
3. Re-enables `federation_enabled` (does NOT auto-restore feature toggles)
4. Logs critical audit event

**Use Case:**
```
Scenario: Spam attack detected in cross-tenant messaging

1. Master admin clicks "Emergency Lockdown" button
2. Enters reason: "Spam attack detected in messaging"
3. All federation features disabled immediately
4. All partnerships suspended
5. Banner shows on all pages
6. After cleanup: "Lift Lockdown" button re-enables federation
```

---

## Key Workflows

### Create New Tenant Workflow

1. **Navigate:** `/super-admin/tenants/create`
2. **Select parent:** Choose Hub tenant from dropdown (filtered by permissions)
3. **Fill details:** Name, Slug, Domain (optional), Tagline, Description
4. **Configure:** Allow sub-tenants (checkbox), Max depth (number), Active status
5. **Submit:** `POST /super-admin/tenants/store`
6. **Validation:**
   - Parent exists and allows sub-tenants
   - Slug is unique
   - Depth < parent's max_depth
   - User can create under this parent
7. **Success:** Redirect to tenant detail page
8. **Audit:** Log `tenant_created` action

### Grant Regional Super Admin Workflow

1. **Navigate:** `/super-admin/users/{id}/edit`
2. **Check tenant:** Must be Hub tenant or move user first
3. **Option A - User in Hub tenant:**
   - Click "Grant Super Admin" button
   - `POST /super-admin/users/{id}/grant-super-admin`
   - Sets `is_tenant_super_admin=1`
4. **Option B - User in leaf tenant:**
   - Use "Move & Promote" section
   - Select Hub tenant from dropdown
   - Click "Move to Hub & Grant Super Admin"
   - `POST /super-admin/users/{id}/move-and-promote`
   - Moves user + content + grants super admin
5. **Success:** User can now access Super Admin panel for their subtree
6. **Audit:** Log `super_admin_granted` action

### Bulk Move Users Workflow

1. **Navigate:** `/super-admin/bulk`
2. **Select source tenant:** Dropdown triggers AJAX load of users
3. **Select users:** Checkboxes (count displayed)
4. **Select target tenant:** Dropdown
5. **Optional:** Check "Grant Super Admin" (only works if target is Hub)
6. **Submit:** `POST /super-admin/bulk/move-users`
7. **Processing:**
   - Validates access to each user's source tenant
   - Validates access to target tenant
   - Moves each user + content via `User::moveTenant()`
   - If grant checkbox: sets `is_tenant_super_admin=1` for each
8. **Result:** Flash message with count moved + any errors
9. **Audit:** Log `bulk_users_moved` action with count and user list

### Federation Partnership Suspension Workflow

1. **Navigate:** `/super-admin/federation/partnerships`
2. **Find partnership:** Table with search/filters
3. **Click "Suspend" button:** Modal asks for reason
4. **Enter reason:** "Terms of service violation"
5. **Confirm:** `POST /super-admin/federation/suspend-partnership` (AJAX)
6. **Effect:**
   - Sets partnership status to `suspended`
   - Disables all cross-tenant features for this pair
   - Records reason and suspending super admin
7. **Audit:** Log critical `partnership_suspended` event
8. **UI update:** Row shows "Suspended" badge

---

## Differences from Platform Admin

| Aspect | Platform Admin (`/admin-legacy/*`, `/admin/*`) | Super Admin (`/super-admin/*`) |
|--------|-----------------------------------------------|-------------------------------|
| **Purpose** | Manage features within ONE tenant | Manage tenant HIERARCHY and federation |
| **Access** | Regular admins (`role='admin'`) | Super admins (`is_tenant_super_admin=1`) on Hub tenants |
| **Scope** | Single tenant only | Own tenant + descendants (Regional) or ALL tenants (Master) |
| **Manages** | Content, users, settings, features | Tenants, hierarchy, cross-tenant users, federation |
| **Cannot Do** | Create new tenants, move users between tenants | Cannot manage day-to-day platform content (use Platform Admin for that) |
| **UI Namespace** | `admin-*` CSS classes | `super-*` CSS classes |
| **Audit Log** | `audit_log` table (platform actions) | `super_admin_audit_log` table (hierarchy actions) |
| **Routes** | `/admin/*`, `/admin-legacy/*` | `/super-admin/*` |

**Key Insight:** Super Admin is for **infrastructure**, Platform Admin is for **operations**.

---

## Troubleshooting & Common Issues

### ❌ "Access Denied" on Super Admin Panel

**Cause:** User does not meet access criteria.

**Check:**
1. Is `is_tenant_super_admin=1` for the user?
2. Does user's tenant have `allows_subtenants=1` (or is it Master tenant)?
3. Is user's tenant active (`is_active=1`)?

**Fix:** Grant super admin via Master admin:
```sql
-- Check user's tenant
SELECT u.id, u.email, u.tenant_id, t.name, t.allows_subtenants, u.is_tenant_super_admin
FROM users u
JOIN tenants t ON u.tenant_id = t.id
WHERE u.id = 42;

-- Option A: Grant super admin (tenant must be Hub)
UPDATE users SET is_tenant_super_admin = 1, role = 'tenant_admin' WHERE id = 42;

-- Option B: Move to Hub first, then grant
-- Use Move & Promote workflow via UI
```

### ❌ Cannot Create Tenant - "Maximum depth exceeded"

**Cause:** Parent's `max_depth` prevents deeper nesting.

**Check:**
```sql
SELECT id, name, depth, max_depth FROM tenants WHERE id = 42;
```

**Fix:** Increase parent's `max_depth`:
```sql
UPDATE tenants SET max_depth = 3 WHERE id = 42;
```

**Or:** Choose a different parent higher in the hierarchy.

### ❌ User Moved but Content Missing

**Cause:** `User::moveTenant()` method may have failed mid-process.

**Check:**
```sql
-- Check user's current tenant
SELECT id, tenant_id FROM users WHERE id = 42;

-- Check if listings were moved
SELECT COUNT(*) FROM listings WHERE user_id = 42 AND tenant_id != (SELECT tenant_id FROM users WHERE id = 42);
```

**Fix:** Re-run bulk move or contact developer to check transaction logs.

### ❌ Federation Emergency Lockdown Won't Lift

**Cause:** Database record stuck.

**Check:**
```sql
SELECT emergency_lockdown_active, emergency_lockdown_reason FROM federation_system_control WHERE id = 1;
```

**Fix (Manual):**
```sql
UPDATE federation_system_control
SET emergency_lockdown_active = 0,
    emergency_lockdown_reason = NULL,
    updated_at = NOW()
WHERE id = 1;
```

**Note:** Always use UI "Lift Lockdown" button first for proper audit logging.

---

## Related Documentation

- **Tenant Hierarchy Design:** `docs/tenant-hierarchy-design.md` (if exists)
- **Federation System:** `docs/federation-guide.md` (if exists)
- **Platform Admin Docs:** See `/admin/` README or CLAUDE.md
- **API Documentation:** See CLAUDE.md "API Endpoints (V2)" section
- **Database Schema:** See `migrations/` folder for table definitions

---

## Summary Statistics

| Category | Count |
|----------|-------|
| **Controllers** | 6 (Dashboard, Tenant, User, Bulk, Audit, Federation) |
| **Routes** | 45+ (GET/POST combined) |
| **Views** | 19 PHP view files |
| **Services** | 6 core services (Hierarchy, Visibility, 2x Audit, 2x Federation) |
| **Database Tables** | 7 primary tables (tenants, users, 2x audit logs, 3x federation) |
| **API Endpoints** | 8 JSON APIs |
| **CRUD Operations** | Full CRUD for Tenants, Users; Bulk operations for both |
| **Permission Levels** | 2 (Master = global, Regional = subtree) |
| **Access Control Methods** | 5 (check, canAccessTenant, canManageTenant, canCreateSubtenantUnder, isGod) |

---

**End of Super Admin Catalog**

Generated by: Claude Sonnet 4.5
Date: 2026-02-18
Project: NEXUS Multi-Tenant Platform
