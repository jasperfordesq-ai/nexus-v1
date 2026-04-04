# Project NEXUS - AI Assistant Guide

## 🔴 PRIORITIZE PUBLIC REPOSITORIES & SHARED COMPONENTS
When upgrading this platform, ALWAYS look through public repositories for the best available shared components before writing custom code. **Your 1st priority must ALWAYS be using HeroUI and Tailwind CSS shared components.** It is highly preferable to use established, working components rather than building custom variations from scratch.

## 🔴 Agent Teams (Swarm Mode) — ENABLED

This project uses **Claude Opus 4.6 Agent Teams** (swarm mode) for large, multi-step tasks. Configuration:

```json
{ "CLAUDE_CODE_EXPERIMENTAL_AGENT_TEAMS": "1", "teammateMode": "in-process" }
```

**Rules for team/swarm mode:**

- **Use teams for large tasks**: When a task involves 3+ independent workstreams, spawn teammate agents to work in parallel
- **Autonomous operation**: The user may be away or sleeping — work autonomously, make decisions, complete tasks without waiting for confirmation on routine choices
- **Agent types**: Use the right agent — `general-purpose` for implementation, `Explore` for research, `Plan` for architecture, `feature-dev:code-reviewer` for reviews
- **Quality over speed**: Each agent should follow all project conventions (tenant scoping, HeroUI, TypeScript strict, etc.)

**When NOT to use teams:** Single-file edits, inherently sequential tasks, simple research questions.

---

## Project Overview (Stack)

This is a **multi-tenant Laravel + React/TypeScript application**. The backend uses Laravel with tenant scoping. The frontend is TypeScript React. Always check tenant context when debugging issues. Always use TypeScript for new frontend files. The primary language stack is TypeScript (React/RN), PHP (Laravel), with JSON configs and Markdown docs.

**Infrastructure:** The web server is **Apache** (not nginx) running on Plesk/Azure. Do not assume nginx for any server configuration tasks.

---

## Git & Commits

When committing code, always use `--no-verify` flag if pre-commit hooks fail due to pre-existing issues in unstaged files. Do not spend multiple cycles trying to fix pre-existing lint/test errors that are unrelated to the current changes.

Pre-commit hooks (husky/eslint) have known pre-existing failures. When commits are blocked by pre-existing lint or test errors unrelated to current changes, use `--no-verify` to bypass rather than attempting to fix all pre-existing issues.

---

## Audit Workflow

When performing audits, use parallel agent teams (5-8 agents) scoped by domain/module. After fixes, always commit and push before moving to next module. Never claim work is complete without verifying the actual file changes exist.

## Agent Guidelines

When running audits, limit parallel background agents to **5 maximum**. Do NOT spawn excessive background tasks. Report progress concisely without flooding the session with notifications.

---

## Deployment Checklist

After deploying to production, always check for CORS errors, tenant binding issues, and feature gate problems. Run a quick smoke test of critical endpoints before reporting deployment success.

When deploying to production, always verify the deployment is working by checking key endpoints before reporting success. Never skip migration dry-runs on production.

---

## Debugging Guidelines

When debugging a bug, do NOT apply surface-level fixes. Identify the root cause first. If the user reports the bug persists after a fix, re-examine assumptions rather than tweaking the same approach.

When debugging, do NOT go in circles. If an approach fails twice, stop and reassess the root cause before trying again. Never claim something is fixed without verifying it actually works end-to-end.

---

## Quick Reference

| Item | Value |
|------|-------|
| **Project** | Project NEXUS - Timebanking Platform |
| **License** | AGPL-3.0-or-later (open source) |
| **GitHub Repo** | <https://github.com/jasperfordesq-ai/nexus-v1> |
| **Frontend Stack** | React 18 + TypeScript + HeroUI + Tailwind CSS 4 |
| **PHP Version** | 8.2+ (API backend only) |
| **Database** | MariaDB 10.11 (MySQL compatible) |
| **Cache** | Redis 7+ |
| **Production Server** | Azure VM `20.224.171.253` |
| **React Frontend URL** | <https://app.project-nexus.ie> |
| **PHP API URL** | <https://api.project-nexus.ie> |
| **Sales Site URL** | <https://project-nexus.ie> |
| **Test Tenant** | `hour-timebank` (tenant 2) |

### Local Development (Docker)

| Service | URL |
|---------|-----|
| **React Frontend** | http://localhost:5173 |
| **PHP API** | http://localhost:8090 |

| **Sales Site** | http://localhost:3001 |
| **React Admin** | http://localhost:5173/admin |
| **PHP Admin (Legacy)** | http://localhost:8090/admin-legacy/ |
| **phpMyAdmin** | http://localhost:8091 (with `--profile tools`) |

```bash
docker compose up -d    # Start everything — Docker is the only dev environment
```

See [docs/LOCAL_DEV_SETUP.md](docs/LOCAL_DEV_SETUP.md) for full setup guide.

### WebAuthn / Passkeys — Windows Dev Environment

**RP ID:** `project-nexus.ie` (covers both `app.project-nexus.ie` and `api.project-nexus.ie`)
- Set via `WEBAUTHN_RP_ID=project-nexus.ie` in `.env` — do NOT use `staging.timebank.local` (stale, deleted)
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

### Documentation Index

| Document | Purpose |
|----------|---------|
| [react-frontend/CLAUDE.md](react-frontend/CLAUDE.md) | React frontend stack conventions, contexts, hooks, pages |
| [sales-site/CLAUDE.md](sales-site/CLAUDE.md) | Sales site stack conventions |
| [docs/PHP_CONVENTIONS.md](docs/PHP_CONVENTIONS.md) | PHP code patterns, services/models reference |
| [docs/API_REFERENCE.md](docs/API_REFERENCE.md) | V2 API endpoints (50+) |
| [docs/ROADMAP.md](docs/ROADMAP.md) | Consolidated feature roadmap (single source of truth) |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | Deployment guide (Azure production) |
| [docs/REGRESSION_PREVENTION.md](docs/REGRESSION_PREVENTION.md) | 7-layer regression prevention system |
| [docs/QA_AUDIT_AND_TEST_PLAN.md](docs/QA_AUDIT_AND_TEST_PLAN.md) | Master QA document |
| [docs/LOCAL_DEV_SETUP.md](docs/LOCAL_DEV_SETUP.md) | Docker development setup |
| [LARAVEL_MIGRATION_PLAN.md](LARAVEL_MIGRATION_PLAN.md) | Laravel migration plan, workflow, and effort estimates |

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

### 🔴 LEGACY PHP THEMES ARE DEAD — NEVER TOUCH (CRITICAL)

**The old PHP frontend themes — `civicone`, `modern`, `starter`, and any others under `views/` (except admin panels) — are DEAD legacy code.** Do NOT spend any time or credits on them. Ever.

- **NEVER modify, fix, refactor, or audit** legacy PHP theme files (`views/civicone/`, `views/modern/` non-admin, `views/starter/`, etc.)
- **NEVER create hooks, checks, or CI gates** that reference legacy themes
- **NEVER suggest improvements** to legacy PHP frontend code
- The ONLY PHP views that are maintained are `views/admin/` and `views/modern/admin/` (for `/admin-legacy/` and `/super-admin/`)
- All user-facing UI is React. Period.

### 🔴 REACT FRONTEND IS THE PRIMARY UI (CRITICAL)

**The React frontend (`react-frontend/`) is the primary frontend for all user-facing pages.** PHP admin views remain at `views/admin/` and `views/modern/admin/` for legacy admin panels (`/admin-legacy/`, `/super-admin/`).

- **ALL UI work** goes in `react-frontend/`
- **UI stack**: React 18 + TypeScript + **HeroUI** (`@heroui/react`) + **Tailwind CSS 4** + Framer Motion
- **Icons**: Lucide React (`lucide-react`)
- Use HeroUI components as primary building blocks
- Use Tailwind CSS utilities for layout/spacing — **no separate CSS component files**
- Use CSS tokens in `src/styles/tokens.css` for theme-aware colors
- **Do NOT** create PHP views (except admin panels at `/admin-legacy/` and `/super-admin/`)

See [react-frontend/CLAUDE.md](react-frontend/CLAUDE.md) for full styling rules, contexts, hooks, and component reference.

### General Principles

- **Do NOT default to the quickest solution** — prioritize maintainability
- Follow existing patterns in the codebase
- Ask if unsure about where code should live

---

## Project Overview

Project NEXUS is an enterprise **multi-tenant community platform** with timebanking, enabling communities to exchange services using time credits.

**Core Modules:** Feed, Listings, Messages, Events, Groups, Members, Connections, Wallet, Volunteering, Organizations, Blog, Resources, Goals, Matches, Reviews, Search, Leaderboard, Achievements, Help, AI Chat.

**Platform Features:** Multi-tenant architecture, gamification (badges, XP, challenges), federation (multi-community network), PWA & Capacitor mobile app, real-time WebSockets (Pusher), push notifications (FCM), light/dark theme.

## Directory Structure

```text
project-nexus/
├── react-frontend/               # React 18 + HeroUI + Tailwind CSS 4 SPA (PRIMARY UI)
│   ├── src/                      # components/, contexts/, pages/, lib/, hooks/, styles/, types/
│   ├── CLAUDE.md                 # React frontend conventions
│   └── package.json
├── src/                          # PHP source (PSR-4: Nexus\)
│   ├── Controllers/Api/          # V2 API controllers (for React)
│   ├── Core/                     # Framework (Router, Database, Auth)
│   ├── Models/                   # Data models (59+ files)
│   ├── Services/                 # Business logic (120+ services)
│   └── helpers.php
├── views/                        # PHP admin templates only
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

## Code Patterns (Summary)

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
| **Deploy Script** | `scripts/safe-deploy.sh` (bash — run on server via SSH) |
| **Method** | Git pull + Docker rebuild (`compose.prod.yml`) |

```bash
# Step 1: Push code (pre-push hook validates TypeScript + build)
git push origin main

# Step 2: SSH into the Azure VM
ssh -i "C:\ssh-keys\project-nexus.pem" azureuser@20.224.171.253

# Step 3: Run deploy (on the server)
cd /opt/nexus-php
sudo bash scripts/safe-deploy.sh full       # Full: rebuild ALL containers (--no-cache)
sudo bash scripts/safe-deploy.sh quick      # Quick: rebuild frontend + restart PHP
sudo bash scripts/safe-deploy.sh rollback   # Rollback to last successful deploy
sudo bash scripts/safe-deploy.sh status     # Check current deployment status
```

> **⚠️ Always use `full` for React/frontend changes.** The `quick` mode rebuilds frontend + restarts PHP, but `full` does a complete `--no-cache` rebuild of all containers which is safest.

### 🔴 Critical Deploy Rules

1. **`--no-cache` on production Docker builds** — stale layers cause phantom bugs
2. **`docker restart nexus-php-app` after PHP deploys** — OPCache never re-reads files
3. **Never build React locally and upload `dist/`** — always rebuild on server
4. **Cloudflare cache purge after every deploy** — automated in deploy scripts

### Container Ownership

**Our containers:** `nexus-php-app`, `nexus-php-db`, `nexus-php-redis`, `nexus-react-prod`, `nexus-sales-site`, `nexus-phpmyadmin`.

**NEVER touch:** `nexus-backend-*`, `nexus-frontend-*`, `nexus-uk-*`, `nexus-civic-*` — they belong to other projects.

---

## 🔴 Global Maintenance Mode (CRITICAL — CANONICAL METHOD)

**This section documents the ONLY reliable method for putting the entire Project NEXUS platform into maintenance mode. Do NOT improvise, guess, or use alternative approaches. Updated 2026-03-21.**

### What "maintenance mode" means

When maintenance mode is ON, **all tenants across the entire platform** are blocked. Two independent layers enforce this — **BOTH must be toggled together** or users will still see maintenance mode.

### How it works — TWO LAYERS

| Layer | Mechanism | Where checked | What it blocks |
|-------|-----------|---------------|---------------|
| **Layer 1: File** | `.maintenance` file in PHP container | `httpdocs/index.php` line 16 (pre-framework) | ALL HTTP traffic except localhost |
| **Layer 2: Database** | `tenant_settings.general.maintenance_mode` | Laravel `CheckMaintenanceMode` middleware + React `TenantShell` | Non-admin API requests + React frontend |

**Layer 1** is the fast gate — blocks everything before Laravel boots, serves static HTML 503.
**Layer 2** is the application gate — if the file is gone but the database still says `true`, Laravel middleware returns 503 JSON and React shows `MaintenancePage`.

**`scripts/maintenance.sh` controls BOTH layers atomically.** One command toggles both.

### Commands (run on the production server via SSH)

```bash
# Enable maintenance mode (file + database, all tenants, immediate)
sudo bash scripts/maintenance.sh on

# Disable maintenance mode (file + database, all tenants, immediate)
sudo bash scripts/maintenance.sh off

# Check both layers' status
sudo bash scripts/maintenance.sh status
```

### Deployment integration

The deploy script (`scripts/safe-deploy.sh`) automatically:
1. **Enables** maintenance mode (both layers) at the start of every deployment
2. **Re-enables** Layer 1 after container rebuilds (since `docker compose up --force-recreate` wipes container filesystems — Layer 2 survives in the database)
3. **Disables** maintenance mode (both layers) at the end of a successful deployment
4. **Leaves it ON** if deployment fails — with clear recovery instructions printed to console

### On deployment failure

If a deploy fails, **maintenance mode stays ON**. Recovery:
1. Re-deploy: `sudo bash scripts/safe-deploy.sh full`
2. Rollback: `sudo bash scripts/safe-deploy.sh rollback`
3. Force live: `sudo bash scripts/maintenance.sh off` (only if you're sure the platform is healthy)

### For AI assistants

When the user says **"maintenance mode on"**, run:
```bash
ssh -i "C:\ssh-keys\project-nexus.pem" -o RequestTTY=force azureuser@20.224.171.253 \
  "sudo bash /opt/nexus-php/scripts/maintenance.sh on"
```

When the user says **"maintenance mode off"**, run:
```bash
ssh -i "C:\ssh-keys\project-nexus.pem" -o RequestTTY=force azureuser@20.224.171.253 \
  "sudo bash /opt/nexus-php/scripts/maintenance.sh off"
```

**NEVER toggle only one layer.** Always use `maintenance.sh` which handles both.

---

## Database Migrations

### Schema Dump (for fresh database setup)

`database/schema/mysql-schema.sql` contains the **full database schema** plus `laravel_migrations` table data. This is committed to git so new contributors can set up a working database with:

```bash
docker compose up -d
docker exec nexus-php-app php artisan migrate   # loads schema dump + runs any newer migrations
```

**Keeping it current:** The dump is auto-regenerated by the deploy script when `--migrate` is used. To refresh manually:

```bash
bash scripts/refresh-schema-dump.sh             # local Docker
bash scripts/refresh-schema-dump.sh --production # on production server
```

After refreshing, **commit the updated `database/schema/mysql-schema.sql` to git**.

### Laravel Migrations (primary system)

New schema changes go in `database/migrations/` using standard Laravel migrations (`php artisan make:migration`). Use `Schema::hasTable()` / `Schema::hasColumn()` guards for idempotency.

### Legacy SQL Migrations

Located in `/migrations/` with timestamp naming. These are tracked in the `migrations` table (not `laravel_migrations`). **Do not add new legacy SQL migrations** — use Laravel migrations instead. The legacy migrations are baked into the schema dump.

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

**Why `-o RequestTTY=force`?** Sudoers has `use_pty` — sudo refuses without a terminal. `-t` and `-tt` fail because stdin isn't a TTY when called from Claude. `-o RequestTTY=force` is the only flag that works.

**After running migrations, refresh the schema dump:**
```bash
bash scripts/refresh-schema-dump.sh
# Then commit the updated database/schema/mysql-schema.sql
```

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

## Git Commit Convention

```
feat: Add new feature       fix: Bug fix           docs: Documentation only
style: Formatting           refactor: Restructure  test: Adding tests
chore: Maintenance

Example: feat(wallet): Add time credit transfer confirmation modal
Co-Authored-By: Claude <noreply@anthropic.com>
```

## Useful Commands

```bash
# React Frontend
cd react-frontend && npm run dev    # Dev server (localhost:5173)
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

# Deploy (run on Azure VM via SSH) — maintenance mode is automatic
sudo bash scripts/safe-deploy.sh full       # Full deploy (rebuild all)
sudo bash scripts/safe-deploy.sh quick      # Quick deploy (frontend + PHP restart)
sudo bash scripts/safe-deploy.sh rollback   # Rollback to last successful
sudo bash scripts/safe-deploy.sh status     # Check deployment status
bash scripts/purge-cloudflare-cache.sh      # Cache purge only

# Meilisearch — re-sync search index (run from LOCAL machine via SSH)
# scripts/ is NOT volume-mounted in the PHP container, so must docker cp before exec
# Run after: bulk listing imports, data migrations, or any Meilisearch data loss
ssh -i "C:\ssh-keys\project-nexus.pem" -o RequestTTY=force azureuser@20.224.171.253 \
  "sudo docker exec nexus-php-app mkdir -p /var/www/html/scripts && \
   sudo docker cp /opt/nexus-php/scripts/sync_search_index.php \
     nexus-php-app:/var/www/html/scripts/sync_search_index.php && \
   sudo docker exec nexus-php-app php scripts/sync_search_index.php --all-tenants"
```
