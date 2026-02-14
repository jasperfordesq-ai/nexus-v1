# File Map: 2026-02-07 Feature Implementation

## Quick Reference

```
project-nexus/
│
├── migrations/
│   └── 2026_02_07_match_approval_workflow.sql    [NEW] DB schema
│
├── src/
│   ├── Controllers/
│   │   └── Admin/
│   │       └── MatchApprovalsController.php      [NEW] Admin dashboard
│   │
│   │   └── Api/
│   │       └── UsersApiController.php            [MODIFIED] +updateTheme()
│   │
│   └── Services/
│       ├── MatchApprovalWorkflowService.php      [NEW] Approval logic
│       └── SmartMatchingEngine.php               [MODIFIED] +approval integration
│
├── views/                                          (legacy PHP admin panel — being migrated to React)
│   ├── modern/
│   │   └── admin/
│   │       └── match-approvals/
│   │           └── index.php                     [NEW] Admin panel dashboard (PHP)
│   │
│   └── civicone/
│       └── admin/
│           └── match-approvals/
│               └── index.php                     [NEW] Admin panel dashboard (PHP)
│
├── httpdocs/
│   └── routes.php                                [MODIFIED] +approval & theme routes
│
├── react-frontend/
│   └── src/
│       ├── contexts/
│       │   ├── ThemeContext.tsx                  [NEW] Theme state
│       │   └── index.ts                          [MODIFIED] +ThemeProvider export
│       │
│       ├── components/
│       │   └── layout/
│       │       └── Navbar.tsx                    [MODIFIED] +theme toggle
│       │
│       ├── styles/
│       │   └── tokens.css                        [MODIFIED] +light mode vars
│       │
│       └── App.tsx                               [MODIFIED] +ThemeProvider wrapper
│
└── docs/
    ├── ROADMAP.md                                [UPDATED] Feature status
    ├── FEATURE_IMPLEMENTATION_2026-02-07.md      [NEW] This documentation
    └── FEATURE_FILE_MAP_2026-02-07.md            [NEW] This file map
```

## File Details

### New Files (7)

| File | Lines | Purpose |
|------|-------|---------|
| `migrations/2026_02_07_match_approval_workflow.sql` | 55 | Database schema for approvals + theme column |
| `src/Services/MatchApprovalWorkflowService.php` | ~500 | Core approval workflow service |
| `src/Controllers/Admin/MatchApprovalsController.php` | ~220 | Admin dashboard controller |
| `views/modern/admin/match-approvals/index.php` | ~450 | Admin panel view (PHP — being migrated to React) |
| `views/civicone/admin/match-approvals/index.php` | ~150 | Admin panel view (PHP — being migrated to React) |
| `react-frontend/src/contexts/ThemeContext.tsx` | ~180 | React theme context |
| `docs/FEATURE_IMPLEMENTATION_2026-02-07.md` | ~350 | Feature documentation |

### Modified Files (7)

| File | Changes |
|------|---------|
| `src/Services/SmartMatchingEngine.php` | Added `isBrokerApprovalEnabled()` method, modified `notifyNewMatches()` to route through approval |
| `src/Controllers/Api/UsersApiController.php` | Added `updateTheme()` method (~45 lines) |
| `httpdocs/routes.php` | Added 7 new routes (6 for approvals, 1 for theme) |
| `react-frontend/src/contexts/index.ts` | Added ThemeProvider and type exports |
| `react-frontend/src/components/layout/Navbar.tsx` | Added theme toggle button, imported Sun/Moon icons and useTheme |
| `react-frontend/src/styles/tokens.css` | Restructured for dual themes, added light mode variables |
| `react-frontend/src/App.tsx` | Wrapped app with ThemeProvider |

## Route Map

### Admin Routes (Match Approvals)

```
/admin/match-approvals
├── GET  /                    → index()      Dashboard
├── GET  /history             → history()    History list
├── GET  /{id}                → show()       Detail view
├── POST /approve             → approve()    Approve action
├── POST /reject              → reject()     Reject action
└── GET  /api/stats           → apiStats()   JSON stats
```

### API Routes (Theme)

```
/api/v2/users/me
└── PUT  /theme               → updateTheme()  Update preference
```

## Database Changes

### New Table: `match_approvals`

```sql
CREATE TABLE match_approvals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    listing_id INT NOT NULL,
    listing_owner_id INT NOT NULL,
    match_score DECIMAL(5,2),
    match_type VARCHAR(50),
    match_reasons JSON,
    distance_km DECIMAL(8,2),
    status ENUM('pending', 'approved', 'rejected'),
    submitted_at TIMESTAMP,
    reviewed_by INT,
    reviewed_at TIMESTAMP,
    review_notes TEXT,
    -- Indexes and constraints...
);
```

### Modified Table: `users`

```sql
ALTER TABLE users
ADD COLUMN preferred_theme ENUM('light', 'dark', 'system') DEFAULT 'dark';
```

## Component Dependency Graph

### PHP Backend

```
SmartMatchingEngine
    └── MatchApprovalWorkflowService
            ├── NotificationDispatcher
            ├── AuditLogService
            └── Database

MatchApprovalsController
    └── MatchApprovalWorkflowService
            └── (same as above)
```

### React Frontend

```
App
└── ThemeProvider (ThemeContext)
        ├── localStorage (nexus_theme)
        ├── DOM (data-theme attribute)
        └── API (/api/v2/users/me/theme)

Navbar
└── useTheme()
        └── toggleTheme()
```

## CSS Token Categories

### Theme-Dependent (in tokens.css)

| Token | Dark Value | Light Value |
|-------|------------|-------------|
| `--background` | #0a0a0f | #f8fafc |
| `--foreground` | #ededed | #1e293b |
| `--glass-bg` | rgba(255,255,255,0.05) | rgba(255,255,255,0.7) |
| `--glass-border` | rgba(255,255,255,0.10) | rgba(0,0,0,0.08) |
| `--shadow-md` | rgba(0,0,0,0.4) | rgba(0,0,0,0.12) |
| `--color-primary` | #6366f1 | #4f46e5 |

### Theme-Independent (shared)

| Token | Value |
|-------|-------|
| `--radius-md` | 12px |
| `--transition-base` | 200ms ease |
| `--z-modal` | 500 |
