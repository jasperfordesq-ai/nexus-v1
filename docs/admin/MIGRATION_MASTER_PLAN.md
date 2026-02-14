# Admin Panel Migration — Master Plan

## Overview
Replace the legacy PHP admin panel (484+ routes, 47 controllers, 180+ views) with a React admin panel achieving full functional parity.

## Architecture Decision
**UI Library: HeroUI** (same as main frontend — no switching, minimize risk)
**Routing: React Router v6** with admin prefix routes
**State: React Context + useApi/useMutation hooks** (existing patterns)
**Auth: JWT Bearer tokens** via existing ApiAuth trait
**API: V2 JSON endpoints** — new AdminApiController classes

## Directory Structure
```
react-frontend/src/
├── admin/
│   ├── AdminApp.tsx              # Admin shell with sidebar + header
│   ├── AdminLayout.tsx           # Layout wrapper
│   ├── AdminRoute.tsx            # Admin auth guard component
│   ├── routes.tsx                # All admin route definitions
│   ├── api/
│   │   ├── adminApi.ts           # Admin API client functions
│   │   └── types.ts              # Admin-specific TypeScript types
│   ├── components/
│   │   ├── AdminSidebar.tsx      # Collapsible sidebar navigation
│   │   ├── AdminHeader.tsx       # Top bar with breadcrumbs + user menu
│   │   ├── AdminBreadcrumbs.tsx  # Breadcrumb component
│   │   ├── DataTable.tsx         # Reusable data table with sort/filter/paginate
│   │   ├── StatCard.tsx          # Dashboard stat card
│   │   ├── ConfirmModal.tsx      # Dangerous action confirmation
│   │   ├── FormField.tsx         # Form field wrapper
│   │   ├── StatusBadge.tsx       # Status indicator badges
│   │   └── EmptyState.tsx        # Empty state placeholder
│   └── modules/
│       ├── dashboard/
│       │   └── AdminDashboard.tsx
│       ├── users/
│       │   ├── UserList.tsx
│       │   ├── UserEdit.tsx
│       │   └── UserCreate.tsx
│       ├── listings/
│       │   └── ListingsAdmin.tsx
│       ├── content/
│       │   ├── BlogAdmin.tsx
│       │   ├── PagesAdmin.tsx
│       │   ├── MenusAdmin.tsx
│       │   ├── CategoriesAdmin.tsx
│       │   └── AttributesAdmin.tsx
│       ├── gamification/
│       │   ├── GamificationDashboard.tsx
│       │   ├── Campaigns.tsx
│       │   └── CustomBadges.tsx
│       ├── matching/
│       │   ├── SmartMatchingConfig.tsx
│       │   ├── MatchApprovals.tsx
│       │   └── BrokerControls.tsx
│       ├── timebanking/
│       │   ├── TimebankingDashboard.tsx
│       │   ├── OrgWallets.tsx
│       │   └── FraudAlerts.tsx
│       ├── newsletters/
│       │   ├── NewsletterList.tsx
│       │   ├── NewsletterEditor.tsx
│       │   ├── Subscribers.tsx
│       │   ├── Segments.tsx
│       │   └── Templates.tsx
│       ├── federation/
│       │   ├── FederationSettings.tsx
│       │   ├── Partnerships.tsx
│       │   ├── Directory.tsx
│       │   └── ApiKeys.tsx
│       ├── enterprise/
│       │   ├── EnterpriseDashboard.tsx
│       │   ├── Roles.tsx
│       │   ├── GdprDashboard.tsx
│       │   ├── Monitoring.tsx
│       │   └── Config.tsx
│       ├── legal/
│       │   └── LegalDocuments.tsx
│       ├── seo/
│       │   ├── SeoDashboard.tsx
│       │   └── Redirects.tsx
│       ├── settings/
│       │   └── AdminSettings.tsx
│       ├── system/
│       │   ├── CronJobs.tsx
│       │   ├── ActivityLog.tsx
│       │   └── SeedGenerator.tsx
│       └── super-admin/
│           ├── SuperAdminDashboard.tsx
│           ├── TenantManagement.tsx
│           └── PlatformUsers.tsx
```

## Phase Plan

### Phase 1: Foundation (Current — Complete)
- [x] Create feature branch
- [x] Inventory PHP admin (484+ routes, 47 controllers)
- [x] Map API endpoints
- [x] Create this master plan
- [x] Create progress log

### Phase 2: Admin Shell & Infrastructure
- [ ] Admin layout (sidebar + header + breadcrumbs)
- [ ] AdminRoute auth guard (redirects non-admins)
- [ ] Admin API client layer
- [ ] Reusable admin components (DataTable, StatCard, ConfirmModal, etc.)
- [ ] Wire admin routes into App.tsx
- [ ] V2 Admin API: dashboard stats endpoint

### Phase 3: Core Modules (Highest Priority)
Migration order based on usage frequency and risk:

#### 3.1 Dashboard
- Admin overview with key metrics
- Activity log
- Monthly trends chart
- API: GET /api/v2/admin/dashboard/stats

#### 3.2 User Management
- User list with filtering/search/pagination
- Create/edit user forms
- Status actions (approve, suspend, ban, reactivate)
- Badge management
- 2FA reset
- API: Full CRUD + action endpoints

#### 3.3 Tenant Features/Config (Already has V2 API)
- Feature/module toggle switches
- Cache management
- Background jobs

#### 3.4 Listings Administration
- Unified content directory
- Approve/delete actions
- Status filtering

#### 3.5 Categories & Attributes
- Simple CRUD forms

### Phase 4: Engagement & Matching Modules
#### 4.1 Gamification Hub
- Badge dashboard, campaigns, custom badges, analytics

#### 4.2 Smart Matching
- Algorithm config, analytics, cache management

#### 4.3 Match Approvals
- Approval queue, bulk actions, history

#### 4.4 Broker Controls
- Exchange management, risk tags, message review, monitoring

### Phase 5: Content & Marketing Modules
#### 5.1 Blog/News Admin
- Post CRUD with rich editor (Lexical replaces GrapesJS)

#### 5.2 Page Builder
- Page CRUD with Lexical editor

#### 5.3 Menu Builder
- Menu/item CRUD with drag-drop

#### 5.4 Newsletter System
- All newsletter functionality (creation, templates, segments, subscribers, analytics)

### Phase 6: Financial & Organization Modules
#### 6.1 Timebanking Dashboard
- Stats, alerts, user reports, balance adjustments

#### 6.2 Organization Wallets
- Wallet management, member management

#### 6.3 Plans & Subscriptions
- Plan CRUD, subscription management

#### 6.4 Volunteering Admin
- Organization approvals, management

### Phase 7: Enterprise & Compliance Modules
#### 7.1 Roles & Permissions
- RBAC management UI

#### 7.2 GDPR Compliance
- Data requests, consents, breaches, audit

#### 7.3 Legal Documents
- Document versioning, compliance tracking

#### 7.4 System Monitoring
- Health checks, logs, requirements

#### 7.5 Enterprise Config
- System configuration, secrets vault

### Phase 8: Advanced & Federation Modules
#### 8.1 Federation Settings
- Partnership management, directory, API keys, analytics

#### 8.2 SEO Management
- Global settings, audit, bulk edit, redirects

#### 8.3 404 Error Tracking
- Error monitoring, redirect creation

### Phase 9: System Tools
#### 9.1 Cron Jobs
- Job management, logs, manual triggers

#### 9.2 Activity Log
- Admin action audit trail

#### 9.3 Seed Generator
- Test data generation (dev only)

#### 9.4 Test Runner
- API test execution (dev only)

#### 9.5 Image/WebP Tools
- Image optimization (dev only)

#### 9.6 Deliverables
- Project deliverable tracking

### Phase 10: Super Admin
#### 10.1 Super Admin Dashboard
- Platform-wide overview

#### 10.2 Tenant Management
- Tenant CRUD, hierarchy, federation hub toggle

#### 10.3 Platform User Management
- Cross-tenant user management, role grants

#### 10.4 Bulk Operations
- Bulk user moves, tenant updates

### Phase 11: Cutover
- [ ] Complete parity report (100% coverage)
- [ ] Delete-ready checklist
- [ ] Smoke tests for all modules
- [ ] Cutover steps documented
- [ ] Rollback plan documented

## API Strategy

### Approach
Most PHP admin functions use direct DB queries via session-based auth. For React:

1. **Create V2 admin API controllers** that wrap existing service logic
2. **Use JWT Bearer auth** (same as all other V2 endpoints)
3. **`requireAdmin()` method** on BaseApiController checks for admin/super_admin/god roles
4. **Tenant scoping** automatic via TenantContext

### New Controllers Needed
- AdminDashboardApiController — stats, activity, trends
- AdminUsersApiController — full user CRUD + actions
- AdminListingsApiController — content directory + moderation
- AdminContentApiController — blog, pages, menus CRUD
- AdminGamificationApiController — badges, campaigns, analytics
- AdminMatchingApiController — smart matching config + approvals
- AdminBrokerApiController — broker controls
- AdminTimebankingApiController — timebanking + org wallets
- AdminNewsletterApiController — newsletter system
- AdminFederationApiController — federation admin
- AdminEnterpriseApiController — GDPR, roles, monitoring
- AdminLegalApiController — legal documents
- AdminSeoApiController — SEO management
- AdminSystemApiController — cron, activity log, settings
- SuperAdminApiController — platform-wide management

## Risk Assessment
- **High Risk**: User management (permissions, roles), GDPR (compliance), Newsletters (email sending)
- **Medium Risk**: Timebanking (financial), Federation (multi-tenant), Matching (algorithm config)
- **Low Risk**: Content CRUD, Categories, SEO, System tools

## Success Criteria
1. Every PHP admin route has a React equivalent or documented decommission
2. All admin actions work via V2 API with proper auth
3. No regression in admin capabilities
4. Build passes, admin routes reachable
