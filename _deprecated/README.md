# Legacy Frontend Cleanup — Deprecated Files

**Date:** 2026-02-24
**Performed by:** Claude (automated cleanup)

## What Happened

The legacy PHP frontend themes (Civic 1 / civicone, Modern user-facing, Starter) were already
removed from `views/` prior to this cleanup. What remained were **orphaned controllers** and
**dead route definitions** that pointed to those removed views, causing "View Not Found" errors.

This cleanup moved 42 orphaned controllers and 8 orphaned view files to this `_deprecated/`
directory and removed 229 dead route definitions from `httpdocs/routes.php`.

## What Was Removed

### Controllers (42 files → `_deprecated/controllers/`)
Legacy frontend controllers that called `View::render()` on views that no longer exist.
All user-facing UI is now served by the React frontend at `react-frontend/`.

- 27 user-facing page controllers (Dashboard, Profile, Events, Groups, etc.)
- 15 federation legacy frontend controllers (FederatedEvent, FederatedGroup, etc.)

### Orphaned View Files (8 files → `_deprecated/views/`)
- `layouts/header.php` — proxy to non-existent modern layout
- `layouts/footer.php` — proxy to non-existent modern layout
- `layouts/consent_check.php` — orphaned middleware (not included by any file)
- `layouts/onboarding_check.php` — orphaned middleware (not included by any file)
- `layouts/admin-page-header.php` — empty stub
- `layouts/admin/header.php` — redirect file
- `layouts/admin/footer.php` — redirect file
- `components/group-recommendations-widget.php` — legacy widget

### Other
- `privacy-page.min.css` — empty 0-byte file at project root

### Routes (229 route definitions removed from `httpdocs/routes.php`)
All routes pointing to moved controllers were removed. Routes went from 1632 to 1407.

## What Was Kept

### Controllers kept in `src/Controllers/` (28 files)
- **AdminController.php** — admin dashboard (renders existing admin views)
- **AuthController.php** — login/logout (admin login still works)
- **ContactController.php** — form submission backend
- **CronController.php** — scheduled tasks
- **ExchangesController.php** — exchange workflow
- **FederationStreamController.php** — SSE real-time stream
- **MessageController.php** — messaging backend
- **NewsletterTrackingController.php** — email tracking
- **ReportController.php** — report generation
- **RobotsController.php** — robots.txt
- **SitemapController.php** — sitemap.xml
- **SocialAuthController.php** — OAuth
- **TotpController.php** — 2FA
- **UserPreferenceController.php** — preferences API
- **MasterController.php** — master tenant admin
- **OnboardingController.php** — admin onboarding
- 12 dual-purpose controllers (serve API endpoints AND broken views):
  AchievementsController, FeedController, GroupAnalyticsController,
  HelpController, InsightsController, LeaderboardController,
  LegalDocumentController, ListingController, NewsletterSubscriptionController,
  NexusScoreController, NotificationController, OrgWalletController

### Views kept
- `views/admin/` (73 files) — legacy admin panel at `/admin-legacy/`
- `views/modern/admin/` (177 files) — modern admin panel
- `views/super-admin/` (20 files) — super admin panel
- `views/emails/` (4 files) — email templates
- `views/auth/login.php` — admin login page
- `views/layouts/admin-header.php` + `admin-footer.php` — admin layout
- `views/error/`, `views/errors/`, `views/404.php`, `views/500.php` — error pages
- `views/newsletter/message.php` — newsletter template

### Routes kept (1407 total)
- Super admin: ~54 routes
- Admin legacy: ~484 routes
- API v2: ~577 routes
- API v1: ~10 routes
- Cron/infrastructure: ~13 routes
- Auth/login: ~33 routes
- Remaining legacy with API methods: ~236 routes

## How to Restore

If anything breaks, restore from this directory:

```bash
# Restore a single controller
cp _deprecated/controllers/SomeController.php src/Controllers/SomeController.php

# Restore all controllers
cp _deprecated/controllers/*.php src/Controllers/
cp _deprecated/controllers/federation/*.php src/Controllers/

# Restore view files
cp _deprecated/views/layouts/*.php views/layouts/
mkdir -p views/layouts/admin && cp _deprecated/views/layouts/admin/*.php views/layouts/admin/
mkdir -p views/components && cp _deprecated/views/components/*.php views/components/
```

For routes, use `git diff` to see what was removed and re-add as needed.

## Next Steps (Future Cleanup)

1. **Refactor dual-purpose controllers**: The 12 restored controllers have both API methods
   (working) and view-rendering methods (broken). Extract API methods into proper
   `Controllers/Api/` classes, then move the legacy shells to `_deprecated/`.

2. **Remove remaining broken view routes**: Routes like `/leaderboard`, `/achievements`,
   `/listings`, `/nexus-score` still exist but show "View Not Found". These can be
   removed once confirmed the React frontend handles them.

3. **Hard delete**: Once verified in staging AND production, delete this `_deprecated/`
   directory entirely.
