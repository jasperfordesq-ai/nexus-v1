# Project NEXUS — Agent Guide

Universal guide for all AI coding agents (Claude Code, Codex, GitHub Copilot, Cursor, etc.).
This is the single source of truth for project conventions, rules, and workflows.

---

## Quick Reference

| Item | Value |
|------|-------|
| **Project** | Project NEXUS - Timebanking Platform |
| **License** | AGPL-3.0-or-later (open source) |
| **GitHub Repo** | <https://github.com/jasperfordesq-ai/nexus-v1> |
| **Frontend Stack** | React 19 + TypeScript + HeroUI v3 + Tailwind CSS 4 |
| **PHP Version** | 8.2+ (API backend only) |
| **Database** | MariaDB 10.11 (MySQL compatible) |
| **Cache** | Redis 7+ |
| **Production Server** | Azure VM `20.224.171.253` |
| **React Frontend URL** | <https://app.project-nexus.ie> |
| **Accessible Frontend URL** | <https://accessible.project-nexus.ie> |
| **PHP API URL** | <https://api.project-nexus.ie> |
| **Sales Site URL** | <https://project-nexus.ie> |
| **Test Tenant** | `hour-timebank` (tenant 2) |

---

## Project Overview

Project NEXUS is an enterprise **multi-tenant community platform** with timebanking, enabling communities to exchange services using time credits.

**Core Modules:** Feed, Listings, Messages, Events, Groups, Members, Connections, Wallet, Volunteering, Organizations, Blog, Resources, Goals, Matches, Reviews, Search, Leaderboard, Achievements, Help, AI Chat.

**Platform Features:** Multi-tenant architecture, gamification (badges, XP, challenges), federation (multi-community network), PWA & Capacitor mobile app, real-time WebSockets (Pusher), push notifications (FCM), light/dark theme.

**Infrastructure:** The web server is **Apache** (not nginx) running on Plesk/Azure. Do not assume nginx for any server configuration tasks.

---

## Directory Structure

```text
project-nexus/
├── react-frontend/               # React 19 + HeroUI v3 + Tailwind CSS 4 SPA (PRIMARY UI)
│   ├── src/                      # components/, contexts/, pages/, lib/, hooks/, styles/, types/
│   ├── CLAUDE.md                 # React frontend conventions
│   └── package.json
├── src/                          # PHP source (PSR-4: Nexus\)
│   ├── Controllers/Api/          # V2 API controllers (for React)
│   ├── Core/                     # Framework (Router, Database, Auth)
│   ├── Models/                   # Data models (59+ files)
│   ├── Services/                 # Business logic (120+ services)
│   └── helpers.php
├── views/                        # PHP admin templates only (DEAD — see rules below)
├── httpdocs/                     # Web root (index.php, routes.php, health.php)
├── sales-site/                   # Static marketing site (project-nexus.ie)
│   └── CLAUDE.md                 # Sales site conventions
├── tests/                        # PHPUnit tests
├── migrations/                   # SQL migration files
├── scripts/                      # Build, deploy, maintenance
├── docs/                         # Documentation
├── compose.yml                   # Docker Compose (primary dev env)
└── Dockerfile                    # PHP app container
```

---

## Documentation Index

| Document | Purpose |
|----------|---------|
| [react-frontend/CLAUDE.md](react-frontend/CLAUDE.md) | React frontend stack conventions, contexts, hooks, pages |
| [sales-site/CLAUDE.md](sales-site/CLAUDE.md) | Sales site stack conventions |
| [docs/govuk-alpha/RESEARCH.md](docs/govuk-alpha/RESEARCH.md) | GOV.UK Alpha frontend architecture, official repos, licensing, and branding limits |
| [docs/PHP_CONVENTIONS.md](docs/PHP_CONVENTIONS.md) | PHP code patterns, services/models reference |
| [docs/API_REFERENCE.md](docs/API_REFERENCE.md) | V2 API endpoints (50+) |
| [docs/ROADMAP.md](docs/ROADMAP.md) | Consolidated feature roadmap (single source of truth) |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | Deployment guide (Azure production) |
| [docs/REGRESSION_PREVENTION.md](docs/REGRESSION_PREVENTION.md) | 7-layer regression prevention system |
| [docs/QA_AUDIT_AND_TEST_PLAN.md](docs/QA_AUDIT_AND_TEST_PLAN.md) | Master QA document |
| [docs/LOCAL_DEV_SETUP.md](docs/LOCAL_DEV_SETUP.md) | Docker development setup |
| [LARAVEL_MIGRATION_PLAN.md](LARAVEL_MIGRATION_PLAN.md) | Laravel migration plan, workflow, and effort estimates |
| [BACKUP.md](BACKUP.md) | Full backup system — private repo for machine transfers (gitignored from public) |

---

## Local Development (Native Windows PHP + Native Vite)

| Service | URL |
|---------|-----|
| **React Frontend** | http://127.0.0.1:5173 |
| **PHP API** | http://127.0.0.1:8088 |
| **Shared web root** | http://127.0.0.1/ -> `C:\platforms\htdocs` |
| **React Admin** | http://127.0.0.1:5173/admin |
| **Docker DB** | 127.0.0.1:3307 -> MariaDB 3306 |
| **Docker Redis** | 127.0.0.1:6379 |
| **Meilisearch** | http://127.0.0.1:7700 |

```bash
npm run dev:native      # Start Docker data services + native Apache + native Vite
npm run dev:frontend    # Start only native Windows Vite on http://127.0.0.1:5173
npm run dev:accessible-frontend  # Start accessible frontend dev server
```

**Important:** Routine local development uses native Windows Apache/PHP through Laragon and native Windows Vite. Docker is only for data services by default: MariaDB, Redis, and Meilisearch. Do not use Docker PHP or Docker Vite for routine local work on Windows unless explicitly testing containers; Windows bind mounts made both painfully slow.

The default NEXUS API is served by Laragon Apache on `127.0.0.1:8088`, with document root `C:\platforms\htdocs\staging\httpdocs`. The global website root is `C:\platforms\htdocs`, served on `127.0.0.1:80`, so other sites can live beside `staging`.

Docker PHP, queue, sales, and frontend are opt-in profiles only:

```bash
docker compose --profile docker-php up -d app
docker compose --profile docker-frontend up -d frontend
```

### WebAuthn / Passkeys — Windows Dev Environment

**RP ID:** `project-nexus.ie` (covers `app.project-nexus.ie`, `api.project-nexus.ie`, and `accessible.project-nexus.ie`)
- Production/Azure: set `WEBAUTHN_RP_ID=project-nexus.ie` in `.env`
- Local dev: set `WEBAUTHN_RP_ID=localhost`
- Production Docker env (`compose.prod.yml`) derives it correctly from HTTP_ORIGIN fallback

**Windows Hello requirement:** Chrome uses the native Windows WebAuthn API which requires `WbioSrvc` (Windows Biometric Service) to be **running** at the moment the passkey dialog opens. This service idles down and stops automatically — if it's stopped, Chrome's "Choose a passkey" dialog will show NO "This Windows device" / Windows Hello option.

**Fix:** Run `scripts/setup-wbio-keepalive.ps1` once as Administrator on your dev machine. This sets service auto-recovery and a scheduled task to restart WbioSrvc every 5 minutes.
- Manual restart: `Start-Service WbioSrvc` in PowerShell
- Check status: `(Get-Service WbioSrvc).Status`

**If "This Windows device" never appears in Chrome's passkey dialog:**
The NGC folder (`C:\Users\{user}\AppData\Local\Microsoft\Ngc`) must exist — this is where Windows Hello credentials are stored. If it doesn't exist, Windows Hello is NOT enrolled and Chrome has no platform authenticator to offer.
- Diagnostic: `Test-Path "$env:LOCALAPPDATA\Microsoft\Ngc"` — must be `True`
- Fix: Open Settings > Accounts > Sign-in options > PIN (Windows Hello) and set it up. A regular Windows sign-in PIN is NOT the same as Windows Hello PIN and does NOT support WebAuthn.
- WbioSrvc stops immediately when no biometric hardware and no NGC credentials exist — this is a symptom of the above, not the cause.

**Key files:**
- `react-frontend/src/lib/webauthn.ts` — all WebAuthn frontend logic (SimpleWebAuthn wrapper)
- `react-frontend/src/components/security/BiometricSettings.tsx` — passkey settings UI
- `src/Controllers/Api/WebAuthnApiController.php` — registration/auth endpoints
- `src/Services/WebAuthnChallengeStore.php` — Redis/file challenge storage

---

## LARAVEL MIGRATION — STATUS

The Laravel migration has been **merged to `main`** (2026-03-19) and is live in production. The `laravel-migration` branch no longer exists.

- **Phases 0–5 are complete**: Laravel 12.54 is the sole HTTP handler, routing, middleware, controllers, and auth
- **All 223 services are native Laravel implementations** — zero stubs remain (47 converted + 45 dead stubs deleted on 2026-03-21)
- **43 legacy `src/` files** remain (39 deleted 2026-03-21) — kept alive only by admin views and `app/Core/ImageUploader.php`
- **5 Event Listeners** in `app/Listeners/` are fully implemented (completed 2026-03-21)
- All new schema changes use Laravel migrations in `database/migrations/` (5 Laravel, 190 legacy SQL)
- See [LARAVEL_MIGRATION_PLAN.md](LARAVEL_MIGRATION_PLAN.md) for the full remaining work breakdown

---

## MANDATORY RULES

### 🔴 PRIORITIZE PUBLIC REPOSITORIES & SHARED COMPONENTS

When upgrading this platform, ALWAYS look through public repositories for the best available shared components before writing custom code. **Your 1st priority must ALWAYS be using HeroUI and Tailwind CSS shared components.** It is highly preferable to use established, working components rather than building custom variations from scratch.

---

### 🔴 NO HARDCODED STRINGS — ALL USER-FACING TEXT MUST USE TRANSLATIONS (CRITICAL)

**NEVER write hardcoded English strings in email templates or end-user React frontend output.** This has regressed repeatedly — every new feature ships with inline English, making the platform untranslatable.

**Rules:**
- **PHP emails/services:** Every user-facing string MUST use `__('emails.section.key')` with keys in `lang/en/emails.json`
- **React frontend (end-user UI):** Every label MUST use `t('key')` with keys in the appropriate namespace — applies to everything under `react-frontend/src/pages/`, `src/components/`, etc.
- **When adding a new email:** Add ALL translation keys to `lang/en/emails.json` FIRST, then reference them with `__()`

**What counts as hardcoded:** Subject lines, greetings ("Hi {name},"), button text ("View Profile"), footer text ("All rights reserved"), info card labels, body paragraphs, notice text, page titles. All of these must be translated — in emails, React frontend, and the admin panel.

**CI enforcement:** `scripts/check-i18n.sh` runs in pre-push and CI.

---

### 🔴 EMAIL & NOTIFICATION LOCALE — MUST WRAP IN LocaleContext (CRITICAL)

Every user-facing `__('emails...')`, `__('notifications...')`, or `__('svc_notifications...')` call MUST render in the **recipient's** `preferred_language` — not the HTTP caller's locale, not the queue worker's default, not `config('app.locale')`. Without the wrap, Laravel's `__()` resolves against `App::getLocale()` at call time, so emails dispatched from cron/queues go out in English regardless of what the recipient chose.

**Rules:**
- Every service/listener/job that renders a notification MUST wrap the render + send block in `App\I18n\LocaleContext::withLocale($recipient, fn () => {...})`
- Every user SELECT / eager-load feeding a notification MUST include `preferred_language` (or use `User::findByIdSelectColumns`, which already returns it)
- **Admin fanouts / attendee loops:** wrap INSIDE the per-recipient loop — the subject line must also render in each admin's language, not just the body
- **Queue jobs:** pass `preferred_language` into the job payload and wrap the `handle()` body — queue workers boot once with a default locale and never change it otherwise
- `LocaleContext::withLocale()` accepts a string locale code, a User-like object with `->preferred_language`, or null (no-op). It restores the prior locale in a `finally` block, so exceptions can't leak the switched locale

**Before (leaks caller locale to recipient):**
```php
foreach ($admins as $admin) {
    $subject = __('emails.report.subject'); // renders in caller's locale
    $mailer->send($admin->email, $subject, $body);
}
```

**After (renders in each admin's `preferred_language`):**
```php
use App\I18n\LocaleContext;

foreach ($admins as $admin) {
    LocaleContext::withLocale($admin, function () use ($admin, $mailer, $body) {
        $subject = __('emails.report.subject');
        $mailer->send($admin->email, $subject, $body);
    });
}
```

Regression test: `tests/Laravel/Feature/I18n/EmailLocaleIntegrationTest.php`.

---

### 🔴 GLOBAL PLATFORM — NO LOCALE-SPECIFIC VALIDATION (CRITICAL)

Project NEXUS is a **global platform** serving timebanks worldwide. It is NOT an Irish-only product.

- **NEVER use `Validator::isIrishPhone()`** — use `Validator::isPhone()` for international E.164 format
- **NEVER validate phone numbers against Irish patterns** (no `+353`, `08x`, `00353` checks)
- **NEVER add Irish-specific placeholders** in forms — use neutral international examples like `+1 555 123 4567`
- **NEVER hardcode Ireland/Dublin as a default location** — maps default to a neutral global center
- **No locale-specific location validation** — `validateIrishLocation()` is legacy, do not call it
- The `isIrishPhone()` method in `Validator.php` is **kept only for legacy PHP admin views** — do not use it anywhere else

---

### 🔴 OPEN SOURCE — AGPL-3.0 (CRITICAL)

This project is **publicly released** under AGPL-3.0-or-later at <https://github.com/jasperfordesq-ai/nexus-v1>.

- **Every new source file** (PHP, TS, TSX) MUST have this SPDX header:

  ```text
  // Copyright © 2024–2026 Jasper Ford
  // SPDX-License-Identifier: AGPL-3.0-or-later
  // Author: Jasper Ford
  // See NOTICE file for attribution and acknowledgements.
  ```

  PHP: after `<?php`. TS/TSX: first lines. Run `node scripts/add-spdx-headers.mjs` to batch-add, `node scripts/check-spdx.mjs` to verify.

- **Attribution on every page** — Footer, mobile drawer, auth pages must show AGPL Section 7(b) attribution. Do NOT remove.
- **About page contributors** from `react-frontend/src/data/contributors.json` — rendered programmatically, never hardcoded.
- **NOTICE file** contains authoritative legal terms (Section 7 a–f). Do NOT modify without understanding implications.
- **Never commit secrets** — `.gitignore` protects `.env`, uploads, vendor. The repo is PUBLIC.

---

### 🔴 LEGACY PHP THEMES ARE DEAD — NEVER TOUCH (CRITICAL)

**ALL `views/` content is DEAD legacy code.** `/admin-legacy/` and `/super-admin/` have been decommissioned. Do NOT spend any time or credits on any PHP views. Ever.

- **NEVER modify, fix, refactor, or audit** any PHP view files under `views/` (including `views/admin/`, `views/modern/admin/`, `views/civicone/`, `views/starter/`, etc.)
- **NEVER create hooks, checks, or CI gates** that reference legacy views
- **NEVER suggest improvements** to any legacy PHP view code
- All user-facing UI is React. Period.

---

### 🔴 REACT FRONTEND IS THE PRIMARY UI (CRITICAL)

**The React frontend (`react-frontend/`) is the sole frontend for all pages.** There are no maintained PHP views.

- **ALL UI work** goes in `react-frontend/`
- **UI stack**: React 19 + TypeScript + **HeroUI v3** (`@heroui/react`) + **Tailwind CSS 4** (Framer Motion being removed as part of HeroUI v3 migration)
- **Icons**: Lucide React (`lucide-react`)
- Use HeroUI components as primary building blocks
- Use Tailwind CSS utilities for layout/spacing — **no separate CSS component files**
- Use CSS tokens in `src/styles/tokens.css` for theme-aware colors
- **Do NOT** create PHP views

See [react-frontend/CLAUDE.md](react-frontend/CLAUDE.md) for full styling rules, contexts, hooks, and component reference.

### Accessible Frontend GOV.UK Alpha

The accessible frontend is an explicitly approved new UI track that complements, but does not replace, `react-frontend/`. It is the only maintained exception to the React-primary UI rule and is intended for users who benefit from a highly accessible, HTML-first experience.

- Keep it isolated under root-level `accessible-frontend/`, `app/Http/Controllers/GovukAlpha/`, and `/{tenantSlug}/alpha/...` routes.
- Preferred public subdomain: `accessible.project-nexus.ie`.
- Deploy it through the Laravel/PHP blue-green app container, not the React container. Run `npm run build:accessible-frontend`, `npm run test:accessible-frontend:php`, and `npm run test:accessible-frontend:a11y` before deployment.
- Use official `govuk-frontend` first, currently `govuk-frontend@6.1.0` unless a newer stable version is verified from npm/GitHub.
- Use official GOV.UK Frontend markup/classes/Sass/JS with HTML-first progressive enhancement; do not use unofficial React GOV.UK libraries as the foundation.
- Do not use the GOV.UK crown, GOV.UK logotype, GOV.UK header identity, GDS Transport, or wording that implies this is an official UK government service.
- Do not use deprecated GOV.UK repos/packages: `govuk_template`, `govuk_elements`, or `govuk_frontend_toolkit`.
- All user-facing strings must use `lang/en/govuk_alpha.php`.
- Preserve tenant context, module gates, and AGPL Section 7(b) attribution on every alpha page.

See [docs/govuk-alpha/RESEARCH.md](docs/govuk-alpha/RESEARCH.md) for the architecture decision and source list.

---

### 🔴 NEVER AUTO-DEPLOY (CRITICAL)

**NEVER start a deployment unless the user explicitly tells you to deploy.** Completing a task (code changes, bug fix, feature implementation, audit fix, etc.) does NOT imply "deploy it." Always stop after committing/pushing and wait for the user to give a direct deployment instruction.

No agent may initiate SSH, run `bluegreen-deploy.sh` / `safe-deploy.sh`, or trigger any production deployment autonomously.

---

### 🔴 NEVER AUTO-PUSH TO BACKUP REPO (CRITICAL)

**NEVER push to the `backup` remote (`nexus-v1-backup`) unless the user explicitly tells you to.** The backup repo is private and contains credentials, secrets, and all gitignored files.

See [BACKUP.md](BACKUP.md) for the full backup system documentation.

---

## General Principles

- **Do NOT default to the quickest solution** — prioritize maintainability
- Follow existing patterns in the codebase
- Ask if unsure about where code should live
- **Don't hallucinate fixes** — never claim you've fixed something you haven't actually edited
- **Don't go in circles when debugging** — if an approach fails twice, stop and reassess the root cause
- **Verify before claiming complete** — after deploying a fix, verify it actually resolved the issue

---

## HeroUI Migration Workflow

For any HeroUI v2/v3 migration, component API question, or related React code change, check the official HeroUI v3 docs before giving migration advice or editing components.

- Prefer official HeroUI v3 migration docs over memory, especially for `Select`, `Dropdown`, `Accordion`, `Progress`, `DateInput`, `TimeInput`, modals, hooks, and styling.
- Use the project-installed HeroUI skills in `.agents/skills/` as persistent local guidance, and use `https://heroui.com/react/llms.txt` as the lightweight docs index.
- For broader static reference, use `https://heroui.com/react/llms-full.txt`; for narrower reference, use `https://heroui.com/react/llms-components.txt` or `https://heroui.com/react/llms-patterns.txt`.
- Treat broad renames as suspicious until verified against the docs, because many v3 components use compound APIs rather than simple find-and-replace migrations.
- In progress updates or final summaries, state which HeroUI docs were checked when HeroUI migration work was involved.

---

## Git & Commits

**Commit directly to `main`.** Do not create feature branches or PRs — this is a solo project and the branch-per-change workflow adds unnecessary overhead.

When committing code, always use `--no-verify` flag if pre-commit hooks fail due to pre-existing issues in unstaged files. Do not spend multiple cycles trying to fix pre-existing lint/test errors that are unrelated to the current changes.

Pre-commit hooks (husky/eslint) have known pre-existing failures. When commits are blocked by pre-existing lint or test errors unrelated to current changes, use `--no-verify` to bypass rather than attempting to fix all pre-existing issues.

### Git Commit Convention

```
feat: Add new feature       fix: Bug fix           docs: Documentation only
style: Formatting           refactor: Restructure  test: Adding tests
chore: Maintenance

Example: feat(wallet): Add time credit transfer confirmation modal
Co-Authored-By: Claude <noreply@anthropic.com>
```

---

## Code Patterns

Full PHP patterns with examples: [docs/PHP_CONVENTIONS.md](docs/PHP_CONVENTIONS.md)

### Multi-Tenant Awareness (CRITICAL)

**Always scope queries by tenant:**

```php
$tenantId = TenantContext::getId();
$stmt = Database::query(
    "SELECT * FROM users WHERE tenant_id = ? AND status = ?",
    [$tenantId, 'active']
);
```

**CRITICAL**: Never pass arrays to query parameters. Use `implode(',', array_fill(...))` for IN clauses.

### Key Patterns

- **Services**: Static methods, always scope by `TenantContext::getId()`
- **Controllers**: `jsonResponse()` + `getJsonInput()` helpers
- **Authentication**: `Auth::user()`, `ApiAuth::authenticate()` (token-based), `Csrf::token()`
- **Feature gating**: `TenantContext::hasFeature('events')` (PHP) / `useTenant().hasFeature('events')` (React)

---

## Validation Commands

### Backend CI gates

```bash
vendor/bin/phpunit --testsuite=Laravel,LaravelMigrated --colors=always
vendor/bin/phpstan analyse --no-progress --memory-limit=512M --error-format=github
```

### React / Frontend checks

```bash
cd react-frontend && npx tsc --noEmit
cd react-frontend && npm run build
cd react-frontend && npm test
```

### i18n checks (run after any locale changes)

```bash
npm run check:i18n:baseline
npm run check:i18n:gaps
```

### E2E / Browser tests

```bash
npm run test:e2e
npx playwright test e2e/tests/smoke.spec.ts --grep '@smoke' --project=chromium-modern
```

E2E defaults to `http://localhost:5173`; use `E2E_BASE_URL=...` only when deliberately targeting another environment. Run Playwright from the root dependency tree — do not keep a nested `e2e/node_modules` alongside root Playwright.

### Accessible frontend

```bash
npm run build:accessible-frontend
npm run test:accessible-frontend:php
npm run test:accessible-frontend:a11y
```

---

## Testing

```bash
# PHP
vendor/bin/phpunit                          # All tests
vendor/bin/phpunit --testsuite Unit         # Unit only
vendor/bin/phpunit --testsuite Services     # Services only
php tests/run-api-tests.php                 # API tests

# React
cd react-frontend
npm test                                    # Vitest
npm run lint                                # TypeScript check (tsc --noEmit)
npm run build                               # Production build
```

Test environment: `APP_ENV=testing`, `DB_DATABASE=nexus_test`, `CACHE_DRIVER=array`.

---

## Deployment

Full deployment guide: [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md)

| Item | Value |
|------|-------|
| **Host** | `20.224.171.253` (Azure VM) |
| **SSH** | `ssh -i "C:\ssh-keys\project-nexus.pem" azureuser@20.224.171.253` |
| **Deploy Path** | `/opt/nexus-php/` |
| **Deploy Script** | `scripts/deploy/bluegreen-deploy.sh` (canonical production deploy engine) |
| **Legacy Wrapper** | `scripts/safe-deploy.sh` (compatibility shim only; production delegates to blue-green) |
| **Method** | Zero-downtime blue/green switch via Apache route file |

```bash
# Step 1: Push code
git push origin main

# Step 2: Deploy with the canonical blue-green engine
ssh -i "C:\ssh-keys\project-nexus.pem" -o RequestTTY=force azureuser@20.224.171.253 \
  "cd /opt/nexus-php && sudo bash scripts/deploy/bluegreen-deploy.sh deploy --detach"

# Step 3: Check progress
ssh -i "C:\ssh-keys\project-nexus.pem" -o RequestTTY=force azureuser@20.224.171.253 \
  "cd /opt/nexus-php && sudo bash scripts/deploy/bluegreen-deploy.sh logs"

# Other modes
sudo bash scripts/deploy/bluegreen-deploy.sh rollback --detach # Rollback to previous color
sudo bash scripts/deploy/bluegreen-deploy.sh status            # Show active color + deploy status
sudo bash scripts/deploy/bluegreen-deploy.sh logs              # Blue-green specific log tail
sudo bash scripts/deploy/bluegreen-deploy.sh logs -f           # Follow blue-green log live
sudo bash scripts/deploy/bluegreen-deploy.sh monitor           # Live monitor dashboard
```

> **ALWAYS use `--detach` for deploys.** Docker builds take 10+ minutes. Without `--detach`, SSH will timeout mid-build.
>
> **How it works on production:** `bluegreen-deploy.sh` builds and tests the inactive color while the active color keeps serving live traffic. The Apache route file is atomically swapped after smoke checks pass: **zero downtime, no maintenance window.**

### 🔴 Critical Deploy Rules

1. **Blue-green builds the inactive color** — the live color keeps serving; no maintenance window required
2. **Never build React locally and upload `dist/`** — always rebuild on server inside the container image
3. **Cloudflare cache purge after every deploy** — automated, fires after traffic switch + smoke tests pass
4. **Do not use `safe-deploy.sh quick/full` as the normal production command** — those modes are legacy maintenance-mode fallback paths only

### Container Ownership

**Our containers (production — blue-green):** `nexus-blue-php-app`, `nexus-blue-react`, `nexus-blue-sales`, `nexus-blue-php-queue`, `nexus-blue-php-scheduler`, `nexus-green-php-app`, `nexus-green-react`, `nexus-green-sales`, `nexus-green-php-queue`, `nexus-green-php-scheduler`, `nexus-php-db`, `nexus-php-redis`, `nexus-meilisearch`.

**Legacy single-color containers** (may still exist on first migration): `nexus-php-app`, `nexus-react-prod`, `nexus-sales-site`, `nexus-php-queue`, `nexus-php-scheduler`.

**NEVER touch:** `nexus-backend-*`, `nexus-frontend-*`, `nexus-uk-*`, `nexus-civic-*` — they belong to other projects.

---

## 🔴 Global Maintenance Mode (CRITICAL — CANONICAL METHOD)

**This section documents the ONLY reliable method for putting the entire Project NEXUS platform into maintenance mode. Do NOT improvise, guess, or use alternative approaches. Updated 2026-03-21.**

When maintenance mode is ON, **all tenants across the entire platform** are blocked. Two independent layers enforce this — **BOTH must be toggled together**.

| Layer | Mechanism | Where checked | What it blocks |
|-------|-----------|---------------|---------------|
| **Layer 1: File** | `.maintenance` file in PHP container | `httpdocs/index.php` line 16 (pre-framework) | ALL HTTP traffic except localhost |
| **Layer 2: Database** | `tenant_settings.general.maintenance_mode` | Laravel `CheckMaintenanceMode` middleware + React `TenantShell` | Non-admin API requests + React frontend |

**`scripts/maintenance.sh` controls BOTH layers atomically.** One command toggles both.

```bash
# Enable maintenance mode (file + database, all tenants, immediate)
sudo bash scripts/maintenance.sh on

# Disable maintenance mode (file + database, all tenants, immediate)
sudo bash scripts/maintenance.sh off

# Check both layers' status
sudo bash scripts/maintenance.sh status
```

**Blue-green path (production):** The deploy script does NOT use maintenance mode. The inactive color builds and tests while the live color keeps serving.

**Migrations run automatically.** As of 2026-05-03, `bluegreen-deploy.sh deploy` runs `php artisan migrate --force` against the new color before the traffic switch. Pass `--no-migrate` only for emergency rollback deploys.

**NEVER toggle only one layer.** Always use `maintenance.sh` which handles both.

---

## Database & Migrations

- Before writing code that queries tables, verify actual column names via schema inspection — do not assume.
- When generating migrations, check for FK column type consistency (signed vs unsigned int) against referenced tables.
- Use current Laravel 12 migration APIs; avoid deprecated patterns.

### Schema Dump (for fresh database setup)

`database/schema/mysql-schema.sql` contains the **full database schema** plus `laravel_migrations` table data. This is committed to git so new contributors can set up a working database with:

```bash
docker compose up -d
docker exec nexus-php-app php artisan migrate   # loads schema dump + runs any newer migrations
```

**Keeping it current:** To refresh manually:

```bash
bash scripts/refresh-schema-dump.sh             # local Docker
bash scripts/refresh-schema-dump.sh --production # on production server
```

After refreshing, **commit the updated `database/schema/mysql-schema.sql` to git**.

### Laravel Migrations (primary system)

New schema changes go in `database/migrations/` using standard Laravel migrations (`php artisan make:migration`). Use `Schema::hasTable()` / `Schema::hasColumn()` guards for idempotency.

### Legacy SQL Migrations

Located in `/migrations/` with timestamp naming. **Do not add new legacy SQL migrations** — use Laravel migrations instead.

### 🔴 Running migrations on production — CORRECT METHOD

**Laravel migrations (preferred):**
```bash
ssh -i "C:\ssh-keys\project-nexus.pem" -o RequestTTY=force azureuser@20.224.171.253 \
    "sudo docker exec nexus-php-app php artisan migrate --force"
```

**Legacy SQL migrations (if needed):**
```bash
# Step 1 — SCP the file to the server (from local machine)
scp -i "C:\ssh-keys\project-nexus.pem" migrations/your_file.sql \
    azureuser@20.224.171.253:/opt/nexus-php/migrations/

# Step 2 — Run it (from local machine, one-liner)
ssh -i "C:\ssh-keys\project-nexus.pem" -o RequestTTY=force azureuser@20.224.171.253 \
    "sudo docker exec -i nexus-php-db mysql -u nexus -pYOUR_DB_PASS nexus \
    < /opt/nexus-php/migrations/your_file.sql; echo EXIT:\$?"
```

**Why `-o RequestTTY=force`?** Sudoers has `use_pty` — sudo refuses without a terminal. `-t` and `-tt` fail when stdin isn't a TTY. `-o RequestTTY=force` is the only flag that works.

**After running migrations, refresh the schema dump:**
```bash
bash scripts/refresh-schema-dump.sh
# Then commit the updated database/schema/mysql-schema.sql
```

### Migration workflow (Makefile wrappers)

Prefer the checked-in wrappers for raw SQL migrations:
- `make migrate FILE=...`
- `make migrate-dry FILE=...`
- `make migrate-prod FILE=...`
- `make migrate-prod-dry FILE=...`
- `make drift-check`

---

## Regression Prevention

Full guide: [docs/REGRESSION_PREVENTION.md](docs/REGRESSION_PREVENTION.md)

**7 layers:** Pre-commit hooks (Husky + lint-staged) → Pre-push (tsc + build) → CI pipeline (5 stages) → PR enforcement → Zod runtime validation (dev only) → Local scripts → Deploy rules.

### Mandatory Rules (NEVER SKIP)

1. **`--no-cache` on production builds**
2. **Restart `nexus-php-app` after PHP deploys** (OPCache)
3. **Never double-unwrap** — `response.data` IS the final data
4. **Every DELETE/UPDATE must include `AND tenant_id = ?`** on tenant-scoped tables
5. **Dockerfile limits must match** between `Dockerfile` and `Dockerfile.prod`
6. **Fix PRs must explain Root Cause + Prevention** — enforced by CI

---

## Common Tasks

### Add a New API Endpoint

1. Create controller in `src/Controllers/Api/`
2. Add route in `httpdocs/routes.php`
3. Add tests in `tests/Controllers/`

### Add a New Service

1. Create in `src/Services/` using static methods pattern
2. Always scope by tenant — see [docs/PHP_CONVENTIONS.md](docs/PHP_CONVENTIONS.md)
3. Add unit tests

### Add a New Page (React Frontend)

1. Create page in `react-frontend/src/pages/`
2. Use HeroUI + Tailwind CSS — see [react-frontend/CLAUDE.md](react-frontend/CLAUDE.md)
3. Add route in `App.tsx` with `FeatureGate` if needed
4. Add `usePageTitle()` hook
5. Use `tenantPath()` for internal links

### Add a Database Migration

1. Create migration: `php artisan make:migration add_foo_to_bar_table`
2. Use `Schema::hasTable()` / `Schema::hasColumn()` guards for idempotency
3. Run locally: `docker exec nexus-php-app php artisan migrate`
4. Refresh schema dump: `bash scripts/refresh-schema-dump.sh`
5. Commit both the migration file AND the updated `database/schema/mysql-schema.sql`

---

## Security Checklist

- [ ] Prepared statements (never concatenate SQL)
- [ ] CSRF tokens on forms
- [ ] Scope all queries by `tenant_id`
- [ ] `htmlspecialchars()` for output
- [ ] Rate limit auth endpoints
- [ ] Validate/sanitize all input
- [ ] Never expose internal errors

## Accessibility (WCAG 2.1 AA)

Minimum 4.5:1 contrast, focus indicators, semantic HTML, ARIA labels, keyboard navigation, screen reader support. HeroUI provides built-in accessibility props.

## Environment Variables

Key `.env` variables (never commit — repo is PUBLIC):

```
DB_HOST, DB_NAME, DB_USER, DB_PASS
PUSHER_APP_ID, PUSHER_KEY, PUSHER_SECRET
USE_GMAIL_API, GMAIL_CLIENT_ID, GMAIL_CLIENT_SECRET, GMAIL_REFRESH_TOKEN
OPENAI_API_KEY
```

---

## Useful Commands

```bash
# React Frontend
npm run dev:frontend                # Native Vite dev server (localhost:5173)
npm run build                       # Production build
npm test                            # Vitest
npm run lint                        # TypeScript check

# PHP Backend
vendor/bin/phpunit                  # All tests
php tests/run-api-tests.php         # API tests

# Database
docker exec nexus-php-app php artisan migrate  # Run migrations (local)
bash scripts/refresh-schema-dump.sh            # Refresh schema dump after migrations
php scripts/backup_database.php                # Backup

# Maintenance Mode (run on Azure VM via SSH)
sudo bash scripts/maintenance.sh on         # Enable (all tenants, immediate)
sudo bash scripts/maintenance.sh off        # Disable (platform goes live)
sudo bash scripts/maintenance.sh status     # Check current status

# Deploy (run on Azure VM via SSH) — ALWAYS use --detach to survive SSH disconnects
sudo bash scripts/deploy/bluegreen-deploy.sh deploy --detach   # Zero-downtime production deploy
sudo bash scripts/deploy/bluegreen-deploy.sh rollback --detach # Rollback to previous color
sudo bash scripts/deploy/bluegreen-deploy.sh status            # Show active color + deploy status
sudo bash scripts/deploy/bluegreen-deploy.sh logs              # Tail blue-green deploy log
sudo bash scripts/deploy/bluegreen-deploy.sh logs -f           # Follow blue-green log live
sudo bash scripts/deploy/bluegreen-deploy.sh monitor           # Live deploy dashboard
bash scripts/purge-cloudflare-cache.sh                         # Cache purge only

# Meilisearch — re-sync search index (run from LOCAL machine via SSH)
# scripts/ is NOT volume-mounted in the PHP container, so must docker cp before exec
# Run after: bulk listing imports, data migrations, or any Meilisearch data loss
ssh -i "C:\ssh-keys\project-nexus.pem" -o RequestTTY=force azureuser@20.224.171.253 \
  "sudo docker exec nexus-php-app mkdir -p /var/www/html/scripts && \
   sudo docker cp /opt/nexus-php/scripts/sync_search_index.php \
     nexus-php-app:/var/www/html/scripts/sync_search_index.php && \
   sudo docker exec nexus-php-app php scripts/sync_search_index.php --all-tenants"

# Backup (full project snapshot to private repo)
# See BACKUP.md for full documentation
git checkout full-backup                    # Switch to backup branch
git merge main --no-edit                    # Merge latest source
# (swap .gitignore to minimal version — see BACKUP.md)
git add -A && git commit --no-verify -m "chore: backup snapshot $(date +%Y-%m-%d)"
git checkout main                           # Switch back
git push backup full-backup                 # Push ONLY when user says to

# Prerender operations
sudo bash scripts/prerender-tenants.sh --force              # Re-render everything
sudo bash scripts/prerender-tenants.sh --tenant hour-timebank  # Re-render one tenant
sudo bash scripts/prerender-tenants.sh --routes /about,/blog   # Re-render specific routes
sudo docker stop nexus-prerender-worker                        # Stop a stuck worker
```
