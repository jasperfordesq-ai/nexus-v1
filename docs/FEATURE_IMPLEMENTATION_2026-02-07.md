# Feature Implementation Documentation - 2026-02-07

## Overview

Two features implemented based on feedback from Matt (Crewkerne Timebank):

1. **Broker Approval Workflow for Matches** - All matches require broker review before users see them
2. **Light/Dark Mode Toggle** - React frontend theme switching

---

## Feature 1: Broker Approval Workflow

### Purpose

Brokers/coordinators need to approve matches before members connect to ensure:
- Member suitability (mobility, mental health considerations)
- Activity is within insurance scheme coverage

### Design Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Scope | ALL matches require approval | No score threshold - every match goes through review |
| Rejection notification | YES, with reason | Users are notified when rejected so they understand why |
| Default state | Enabled | Broker approval is on by default for all tenants |

### Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                    MATCH GENERATION                             │
│        SmartMatchingEngine::findMatchesForUser()                │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    APPROVAL QUEUE                               │
│     MatchApprovalWorkflowService::submitForApproval()           │
│     - Creates record in match_approvals table                   │
│     - Notifies brokers/admins                                   │
└─────────────────────────────────────────────────────────────────┘
                              │
              ┌───────────────┴───────────────┐
              ▼                               ▼
┌─────────────────────────┐     ┌─────────────────────────┐
│        APPROVE          │     │         REJECT          │
│  - Status = approved    │     │  - Status = rejected    │
│  - Notify user          │     │  - Notify user w/reason │
│  - Match visible        │     │  - Match hidden         │
└─────────────────────────┘     └─────────────────────────┘
```

### Database Schema

**Table: `match_approvals`**

| Column | Type | Description |
|--------|------|-------------|
| id | INT AUTO_INCREMENT | Primary key |
| tenant_id | INT | Tenant isolation |
| user_id | INT | User who would receive this match |
| listing_id | INT | The matched listing |
| listing_owner_id | INT | Owner of the listing |
| match_score | DECIMAL(5,2) | Score at time of generation (0-100) |
| match_type | VARCHAR(50) | one_way, potential, mutual, cold_start |
| match_reasons | JSON | Array of match reasons |
| distance_km | DECIMAL(8,2) | Distance between parties |
| status | ENUM | pending, approved, rejected |
| submitted_at | TIMESTAMP | When submitted for approval |
| reviewed_by | INT | Admin/broker who reviewed |
| reviewed_at | TIMESTAMP | When reviewed |
| review_notes | TEXT | Notes from reviewer |

**Indexes:**
- `idx_tenant_status` - For listing pending approvals
- `idx_pending` - For dashboard queries
- `idx_user` - For user's pending matches
- `idx_listing` - For listing's pending matches

### Files Created

| File | Purpose |
|------|---------|
| `migrations/2026_02_07_match_approval_workflow.sql` | Database migration |
| `src/Services/MatchApprovalWorkflowService.php` | Core approval logic |
| `src/Controllers/Admin/MatchApprovalsController.php` | Admin dashboard controller |
| `views/modern/admin/match-approvals/index.php` | Modern theme dashboard |
| `views/civicone/admin/match-approvals/index.php` | CivicOne theme dashboard |

### Files Modified

| File | Change |
|------|--------|
| `src/Services/SmartMatchingEngine.php` | Added `isBrokerApprovalEnabled()` and modified `notifyNewMatches()` to route through approval |
| `httpdocs/routes.php` | Added admin routes for match approvals |

### Routes

| Method | Route | Controller Method | Purpose |
|--------|-------|-------------------|---------|
| GET | `/admin/match-approvals` | index | Dashboard with pending approvals |
| GET | `/admin/match-approvals/history` | history | Approval history |
| GET | `/admin/match-approvals/{id}` | show | Single approval detail |
| POST | `/admin/match-approvals/approve` | approve | Approve single/bulk matches |
| POST | `/admin/match-approvals/reject` | reject | Reject single/bulk matches |
| GET | `/admin/match-approvals/api/stats` | apiStats | JSON statistics |

### Service Methods

**MatchApprovalWorkflowService.php:**

```php
// Submission
submitForApproval(int $userId, int $listingId, array $matchData): ?int

// Actions
approveMatch(int $requestId, int $approvedBy, string $notes = ''): bool
rejectMatch(int $requestId, int $rejectedBy, string $reason = ''): bool
bulkApprove(array $requestIds, int $approvedBy, string $notes = ''): int
bulkReject(array $requestIds, int $rejectedBy, string $reason = ''): int

// Queries
getRequest(int $requestId): ?array
getPendingRequests(int $limit = 50, int $offset = 0): array
getApprovalHistory(array $filters = [], int $limit = 50, int $offset = 0): array
getStatistics(int $days = 30): array
getPendingCount(): int
isMatchApproved(int $userId, int $listingId): bool
```

### Notifications

| Event | Recipient | Message |
|-------|-----------|---------|
| Match submitted | Admins/Brokers | "X has been matched with 'Listing Title' and needs your approval" |
| Match approved | User | "Great news! You've been matched with 'Listing Title'" |
| Match rejected | User | "Unfortunately, the match with 'Listing Title' wasn't suitable. Reason: ..." |

### Access Control

Requires one of these roles:
- `super_admin`
- `admin`
- `tenant_admin`
- `broker`

### Configuration

Broker approval can be enabled/disabled per-tenant via:

1. **Admin UI** (recommended): Go to `/admin/smart-matching/configuration` and toggle "Require Broker Approval"
2. **Database**: Edit `tenants.configuration` JSON field

```json
{
  "algorithms": {
    "smart_matching": {
      "broker_approval_enabled": false
    }
  }
}
```

Default: `true` (approval required)

### Admin Menu Location

The Match Approvals dashboard is accessible via:

- **Community > Smart Systems > Match Approvals** in the admin navigation
- Direct URL: `/admin/match-approvals`

---

## Feature 2: Light/Dark Mode Toggle

### Purpose

Allow users to switch between light and dark themes in the React frontend.

### Design Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Default theme | Dark | Matches existing design |
| Modes supported | light, dark, system | Flexibility for user preference |
| Persistence | localStorage + backend | Works for both anonymous and authenticated users |
| Toggle location | Navbar | Easily accessible |

### Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                     ThemeContext                                │
│  - theme: 'light' | 'dark' | 'system'                          │
│  - resolvedTheme: 'light' | 'dark'                             │
│  - setTheme(theme): Promise<void>                              │
│  - toggleTheme(): Promise<void>                                │
└─────────────────────────────────────────────────────────────────┘
                              │
              ┌───────────────┼───────────────┐
              ▼               ▼               ▼
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│   localStorage  │ │   DOM Update    │ │   Backend API   │
│ nexus_theme     │ │ data-theme attr │ │ /users/me/theme │
└─────────────────┘ └─────────────────┘ └─────────────────┘
```

### Database Schema

**Column added to `users` table:**

| Column | Type | Default | Description |
|--------|------|---------|-------------|
| preferred_theme | ENUM('light', 'dark', 'system') | 'dark' | User's theme preference |

### CSS Token Structure

**Dark theme (default):**
```css
:root, :root[data-theme="dark"] {
  --background: #0a0a0f;
  --foreground: #ededed;
  --glass-bg: rgba(255, 255, 255, 0.05);
  /* ... */
}
```

**Light theme:**
```css
:root[data-theme="light"] {
  --background: #f8fafc;
  --foreground: #1e293b;
  --glass-bg: rgba(255, 255, 255, 0.7);
  /* ... */
}
```

### Files Created

| File | Purpose |
|------|---------|
| `react-frontend/src/contexts/ThemeContext.tsx` | Theme state management |

### Files Modified

| File | Change |
|------|--------|
| `react-frontend/src/styles/tokens.css` | Added light mode CSS variables |
| `react-frontend/src/contexts/index.ts` | Export ThemeProvider, useTheme |
| `react-frontend/src/App.tsx` | Wrapped with ThemeProvider |
| `react-frontend/src/components/layout/Navbar.tsx` | Added theme toggle button |
| `src/Controllers/Api/UsersApiController.php` | Added updateTheme method |
| `httpdocs/routes.php` | Added theme API route |
| `migrations/2026_02_07_match_approval_workflow.sql` | Added preferred_theme column |

### Routes

| Method | Route | Controller Method | Purpose |
|--------|-------|-------------------|---------|
| PUT | `/api/v2/users/me/theme` | updateTheme | Update user's theme preference |

### React Context API

**ThemeContext exports:**

```typescript
type ThemeMode = 'light' | 'dark' | 'system';
type ResolvedTheme = 'light' | 'dark';

interface ThemeContextValue {
  theme: ThemeMode;           // Current setting
  resolvedTheme: ResolvedTheme; // Actual applied theme
  isInitialized: boolean;
  isLoading: boolean;
  setTheme(theme: ThemeMode): Promise<void>;
  toggleTheme(): Promise<void>;
}

// Usage
const { theme, resolvedTheme, toggleTheme } = useTheme();
```

### Provider Hierarchy

```tsx
<ErrorBoundary>
  <HelmetProvider>
    <ThemeProvider>          {/* NEW */}
      <HeroUIProvider>
        <BrowserRouter>
          <ToastProvider>
            <TenantProvider>
              <AuthProvider>
                <NotificationsProvider>
                  {/* App content */}
                </NotificationsProvider>
              </AuthProvider>
            </TenantProvider>
          </ToastProvider>
        </BrowserRouter>
      </HeroUIProvider>
    </ThemeProvider>
  </HelmetProvider>
</ErrorBoundary>
```

### UI Components

**Navbar toggle button:**
- Location: Between brand and user actions
- Icon: Sun (when dark) / Moon (when light)
- Behavior: Toggles between light and dark

---

## Migration Instructions

### 1. Run Database Migration

```bash
# From project root
php scripts/safe_migrate.php

# Or manually:
mysql -u nexus -p nexus < migrations/2026_02_07_match_approval_workflow.sql
```

### 2. Build React Frontend

```bash
cd react-frontend
npm run build
```

### 3. Clear Caches

```bash
# If using Redis
redis-cli FLUSHDB

# Clear PHP opcache (restart PHP-FPM or Apache)
```

---

## Testing Checklist

### Broker Approval Workflow

- [ ] Navigate to `/admin/match-approvals` as admin
- [ ] Verify pending matches appear with user/listing details
- [ ] Approve a match - verify user receives notification
- [ ] Reject a match with reason - verify user receives notification with reason
- [ ] Test bulk approve/reject with multiple selections
- [ ] Verify statistics update correctly
- [ ] Test on both modern and civicone themes

### Light/Dark Mode Toggle

- [ ] Click sun/moon icon in Navbar - theme toggles
- [ ] Refresh page - theme persists
- [ ] Log out and log in - theme persists for authenticated user
- [ ] Test "system" mode follows OS preference
- [ ] Verify all pages render correctly in both themes
- [ ] Check contrast ratios meet WCAG AA (4.5:1)

---

## Rollback Instructions

### Feature 1: Broker Approval

To disable broker approval without removing code:

```sql
-- Disable for specific tenant
UPDATE tenants
SET configuration = JSON_SET(
  COALESCE(configuration, '{}'),
  '$.algorithms.smart_matching.broker_approval_enabled',
  false
)
WHERE id = ?;
```

### Feature 2: Light Mode

To revert to dark-only mode:

1. Remove ThemeProvider from App.tsx
2. Remove theme toggle from Navbar.tsx
3. Revert tokens.css to dark-only

---

## Related Documentation

- [ROADMAP.md](ROADMAP.md) - Feature status and priorities
- [CLAUDE.md](../CLAUDE.md) - Project conventions
- [GroupApprovalWorkflowService.php](../src/Services/GroupApprovalWorkflowService.php) - Pattern followed for approvals
