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

### Documentation Index

| Document | Purpose |
|----------|---------|
| [react-frontend/CLAUDE.md](react-frontend/CLAUDE.md) | React frontend stack conventions, contexts, hooks, pages |
| [sales-site/CLAUDE.md](sales-site/CLAUDE.md) | Sales site stack conventions |
| [docs/PHP_CONVENTIONS.md](docs/PHP_CONVENTIONS.md) | PHP code patterns, services/models reference |
| [docs/API_REFERENCE.md](docs/API_REFERENCE.md) | V2 API endpoints (50+) |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | Deployment guide (Azure production) |
| [docs/REGRESSION_PREVENTION.md](docs/REGRESSION_PREVENTION.md) | 7-layer regression prevention system |
| [docs/QA_AUDIT_AND_TEST_PLAN.md](docs/QA_AUDIT_AND_TEST_PLAN.md) | Master QA document |
| [docs/LOCAL_DEV_SETUP.md](docs/LOCAL_DEV_SETUP.md) | Docker development setup |

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

## Database Migrations

Located in `/migrations/` with timestamp naming. Always use `IF EXISTS`/`IF NOT EXISTS` for idempotency.

### 🔴 Running migrations on production — CORRECT METHOD

`php scripts/safe_migrate.php` **does NOT work** on production. `scripts/` and `migrations/` are not volume-mounted into the PHP container, and `bootstrap.php` is not available inside it.

**Always run migrations directly via the DB container:**

```bash
# Step 1 — SCP the file to the server (from local machine)
scp -i "C:\ssh-keys\project-nexus.pem" migrations/your_file.sql \
    azureuser@20.224.171.253:/opt/nexus-php/migrations/

# Step 2 — Run it (from local machine, one-liner)
ssh -i "C:\ssh-keys\project-nexus.pem" -o RequestTTY=force azureuser@20.224.171.253 \
    "sudo docker exec -i nexus-php-db mysql -u nexus -pREDACTED_DB_PASS nexus \
    < /opt/nexus-php/migrations/your_file.sql; echo EXIT:\$?"

# Expected output: EXIT:0
```

**Why `-o RequestTTY=force`?** Sudoers has `use_pty` — sudo refuses without a terminal. `-t` and `-tt` fail because stdin isn't a TTY when called from Claude. `-o RequestTTY=force` is the only flag that works.

**Verify:**
```bash
ssh -i "C:\ssh-keys\project-nexus.pem" -o RequestTTY=force azureuser@20.224.171.253 \
    "sudo docker exec nexus-php-db mysql -u nexus -pREDACTED_DB_PASS nexus \
    -e \"SHOW TABLES LIKE 'your_table%';\""
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

1. Create SQL in `/migrations/` with timestamp prefix
2. Use `IF EXISTS`/`IF NOT EXISTS`
3. Run with `php scripts/safe_migrate.php`

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
php scripts/safe_migrate.php        # Run migrations
php scripts/backup_database.php     # Backup

# Deploy (run on Azure VM via SSH)
sudo bash scripts/safe-deploy.sh full       # Full deploy (rebuild all)
sudo bash scripts/safe-deploy.sh quick      # Quick deploy (frontend + PHP restart)
sudo bash scripts/safe-deploy.sh rollback   # Rollback to last successful
sudo bash scripts/safe-deploy.sh status     # Check deployment status
bash scripts/purge-cloudflare-cache.sh      # Cache purge only
```
