# Project NEXUS - AI Assistant Guide

## 🔴 Agent Teams (Swarm Mode) — ENABLED

This project uses **Claude Opus 4.6 Agent Teams** (swarm mode) for large, multi-step tasks. Configuration:

```json
{ "CLAUDE_CODE_EXPERIMENTAL_AGENT_TEAMS": "1", "teammateMode": "in-process" }
```

**Rules for team/swarm mode:**

- **Use teams for large tasks**: When a task involves 3+ independent workstreams (e.g., multiple API controllers, multiple React modules, research + implementation), spawn teammate agents to work in parallel
- **Autonomous operation**: The user may be away or sleeping — work autonomously, make decisions, and complete tasks without waiting for confirmation on routine choices
- **Spawn as many agents as needed**: There is no artificial limit — create teammates for each independent workstream (e.g., one agent per API controller, one per React module)
- **Agent types**: Use the right agent for the job — `general-purpose` for implementation, `Explore` for codebase research, `Plan` for architecture, `feature-dev:code-reviewer` for reviews
- **Coordinate via task lists**: Use TeamCreate → TaskCreate → assign tasks to teammates → teammates mark complete
- **Quality over speed**: Each agent should follow all project conventions (tenant scoping, HeroUI components, TypeScript strict mode, etc.)
- **Report results**: When all agents complete, summarize what was done, what succeeded, and any issues found

**When NOT to use teams:**

- Single-file edits or small bug fixes
- Tasks that are inherently sequential (each step depends on the previous)
- Simple research questions

---

## Quick Reference

| Item | Value |
|------|-------|
| **Project** | Project NEXUS - Timebanking Platform |
| **Frontend Stack** | React 18 + TypeScript + HeroUI + Tailwind CSS 4 |
| **PHP Version** | 8.2+ (API backend only) |
| **Database** | MariaDB 10.11 (MySQL compatible) |
| **Cache** | Redis 7+ |
| **Production Server** | Azure VM `20.224.171.253` |
| **React Frontend URL** | <https://app.project-nexus.ie> |
| **PHP API URL** | <https://api.project-nexus.ie> |
| **Sales Site URL** | <https://project-nexus.ie> |
| **Legacy URLs** | <https://hour-timebank.ie> |
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
# Start everything
docker compose up -d

# Docker is the only dev environment
```

See [docs/LOCAL_DEV_SETUP.md](docs/LOCAL_DEV_SETUP.md) for full setup guide.

---

## MANDATORY RULES

### 🔴 REACT FRONTEND IS THE ONLY UI - CRITICAL

**The React frontend (`react-frontend/`) is the ONLY frontend.** The legacy PHP views (modern theme, civicone theme) have been deleted. Only PHP admin views remain at `views/admin/` and `views/modern/admin/`.

**Rules:**

- **ALL UI work** goes in the React frontend
- **UI stack**: React 18 + TypeScript + **HeroUI** (component library) + **Tailwind CSS 4** + Framer Motion
- **Icons**: Lucide React (`lucide-react`)
- Use HeroUI components (`@heroui/react`) as the primary building blocks — buttons, inputs, modals, cards, tables, dropdowns, etc.
- Use Tailwind CSS utility classes for layout, spacing, and custom styling
- Use CSS tokens in `react-frontend/src/styles/tokens.css` for light/dark theme variables
- **Do NOT** create custom CSS component files — use Tailwind utilities and HeroUI theming instead
- **Do NOT** create PHP views — the only PHP views are for the admin panels (`/admin-legacy/` and `/super-admin/`)

### React Frontend Styling Rules

```tsx
// CORRECT — use HeroUI components + Tailwind classes
import { Button, Card, Input } from "@heroui/react";

<Card className="p-4 gap-3">
  <Input label="Email" variant="bordered" />
  <Button color="primary" className="mt-2">Submit</Button>
</Card>

// CORRECT — use Tailwind utilities for layout
<div className="flex items-center gap-4 px-6 py-3">

// CORRECT — use design tokens for theme-aware colors
<div className="bg-[var(--color-surface)] text-[var(--color-text)]">

// WRONG — do NOT use inline styles
<div style={{ padding: '16px' }}>

// WRONG — do NOT create separate .css files for components
// (use Tailwind classes or tokens.css instead)
```

### Theme System

- `ThemeContext` manages `light`, `dark`, or `system` preference
- CSS tokens in `react-frontend/src/styles/tokens.css`
- HeroUI dark mode via `@custom-variant dark (&:is(.dark *))` in `index.css`
- Persists to `users.preferred_theme` via `PUT /api/v2/users/me/theme`
- Toggle in Navbar (sun/moon icon)

### General Principles

- **Do NOT default to the quickest solution**
- Prioritize maintainability and organization over speed
- Follow existing patterns in the codebase
- Ask if unsure about where code should live

---

## Project Overview

Project NEXUS is an enterprise multi-tenant community platform with many modules, including timebanking which enables communities to exchange services using time credits.

### Main Front-Facing Modules

- **Feed**: Social feed with posts, comments, likes, polls
- **Listings**: Service offers and requests marketplace
- **Messages**: Private messaging between members
- **Events**: Community events with RSVPs
- **Groups**: Community groups and discussions
- **Members**: Member directory and profiles
- **Connections**: User connections and networking
- **Wallet**: Time credit transactions and balance tracking
- **Volunteering**: Volunteer opportunities
- **Organizations**: Organization profiles and management
- **Blog**: Community blog/news
- **Resources**: Shared resources library
- **Goals**: Personal and community goals
- **Matches**: User/listing matching system
- **Reviews**: Member reviews and ratings
- **Search**: Global search across content
- **Leaderboard**: Gamification leaderboards
- **Achievements**: Badges and achievements system
- **Help**: Help center and documentation
- **AI Chat**: AI assistant widget

### Platform Features

- **Multi-tenant Architecture**: Hierarchical tenant management with super admin controls
- **Gamification**: Badges, achievements, XP, challenges, leaderboards
- **Federation**: Multi-community network with partnerships
- **PWA & Mobile**: Service worker, Capacitor Android app
- **Notifications**: In-app, email digests, and push notifications
- **React Frontend**: React 18 + HeroUI + Tailwind CSS 4 SPA at `react-frontend/`
- **Real-Time**: Pusher WebSockets for live updates, FCM for mobile push
- **Light/Dark Theme**: React frontend supports light/dark/system modes via `ThemeContext`, stored in `users.preferred_theme`, API: `PUT /api/v2/users/me/theme`

## Directory Structure

```text
project-nexus/
├── react-frontend/               # React 18 + HeroUI + Tailwind CSS 4 SPA (PRIMARY UI)
│   ├── src/
│   │   ├── components/           # Reusable UI components
│   │   ├── contexts/             # React contexts (Auth, Tenant, Toast, Theme, Notifications, Pusher)
│   │   ├── pages/                # Page components (27+ pages)
│   │   ├── lib/                  # API client, helpers
│   │   ├── hooks/                # Custom React hooks (useApi, usePageTitle, etc.)
│   │   ├── styles/               # CSS tokens (light/dark themes)
│   │   └── types/                # TypeScript types
│   ├── Dockerfile                # Frontend container
│   └── package.json              # Dependencies (Vite, HeroUI, Tailwind CSS 4, etc.)
├── src/                          # PHP source (PSR-4: Nexus\)
│   ├── Config/                   # Configuration
│   ├── Controllers/              # Request handlers
│   │   └── Api/                  # V2 API controllers (for React)
│   ├── Core/                     # Framework (Router, Database, Auth)
│   ├── Helpers/                  # Global helpers
│   ├── Middleware/               # Request middleware
│   ├── Models/                   # Data models (59+ files)
│   ├── Services/                 # Business logic (100+ services)
│   └── helpers.php               # Global functions
├── views/                        # PHP admin templates only
│   ├── admin/                    # Admin view dispatchers (served under /admin-legacy/)
│   ├── modern/admin/             # Admin panel views (150+ files)
│   └── super-admin/              # Super admin panel views
├── httpdocs/                     # Web root
│   ├── assets/                   # Admin CSS/JS, images
│   ├── index.php                 # Main entry point
│   ├── routes.php                # Route definitions
│   └── health.php                # Docker health check
├── compose.yml                   # Docker Compose (primary dev env)
├── Dockerfile                    # PHP app container
├── .env.docker                   # Docker environment
├── tests/                        # PHPUnit tests
├── migrations/                   # SQL migration files
├── scripts/                      # Build, deploy, maintenance
├── sales-site/                   # Sales/marketing landing page (project-nexus.ie)
│   ├── public/                   # Static HTML, CSS, favicon, robots.txt
│   ├── Dockerfile                # Nginx Alpine container
│   └── nginx.conf                # Nginx config (gzip, caching, security headers)
├── capacitor/                    # Mobile app (Capacitor)
├── docs/                         # Documentation
└── config/                       # App configuration
```

## Sales Site (`sales-site/`)

A standalone static marketing/landing page served at `project-nexus.ie`. **Not part of the React frontend or PHP backend.**

| Item | Value |
|------|-------|
| **Stack** | Static HTML + CSS + minimal vanilla JS |
| **Server** | Nginx Alpine (Docker) |
| **Local URL** | `http://localhost:3001` |
| **Production URL** | `https://project-nexus.ie` |
| **Container** | `nexus-sales-site` |
| **Port** | 3001 |

**Key files:**

| File | Purpose |
|------|---------|
| `sales-site/Dockerfile` | Nginx Alpine container definition |
| `sales-site/nginx.conf` | Gzip, caching headers, security headers |
| `sales-site/public/index.html` | Landing page (all sections) |
| `sales-site/public/styles.css` | Dark theme, responsive, CSS animations |
| `sales-site/public/favicon.svg` | SVG favicon |
| `sales-site/public/robots.txt` | SEO robots file |

**Development:** Edit files in `sales-site/public/` — the Docker volume mount in `compose.yml` auto-refreshes changes. No build step needed.

**Rules:**
- Do NOT add JavaScript frameworks (React, Next.js, etc.) — keep it plain HTML/CSS/JS
- Do NOT import from `react-frontend/` — the sales site is completely independent
- CTA links should point to `https://app.project-nexus.ie` (the React app)
- Design should match the React app's indigo/purple gradient palette

## Code Patterns & Conventions

### PHP Namespacing (PSR-4)

```php
// Core application code
namespace Nexus\Controllers;
namespace Nexus\Models;
namespace Nexus\Services;
namespace Nexus\Core;

// App-specific code
namespace App\CustomFeature;

// Tests
namespace Nexus\Tests;
```

### Database Access

Always use the `Database` class with prepared statements:

```php
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

// Simple query with tenant scoping
$tenantId = TenantContext::getId();
$stmt = Database::query(
    "SELECT * FROM users WHERE tenant_id = ? AND status = ?",
    [$tenantId, 'active']
);
$users = $stmt->fetchAll();

// Insert with last ID
Database::query(
    "INSERT INTO listings (title, user_id, tenant_id) VALUES (?, ?, ?)",
    [$title, $userId, $tenantId]
);
$newId = Database::lastInsertId();

// Transactions
Database::beginTransaction();
try {
    // Multiple queries
    Database::commit();
} catch (Exception $e) {
    Database::rollback();
    throw $e;
}
```

**CRITICAL**: Never pass arrays to query parameters. PDO cannot bind arrays directly:

```php
// WRONG - will throw InvalidArgumentException
Database::query("SELECT * FROM users WHERE id IN (?)", [[$id1, $id2]]);

// CORRECT - flatten the parameters
$placeholders = implode(',', array_fill(0, count($ids), '?'));
Database::query("SELECT * FROM users WHERE id IN ($placeholders)", $ids);
```

### Multi-Tenant Awareness

Always scope queries by tenant:

```php
use Nexus\Core\TenantContext;

// Get current tenant ID
$tenantId = TenantContext::getId();

// Get tenant settings
$siteName = TenantContext::getSetting('site_name');

// Check feature flags
if (TenantContext::hasFeature('gamification')) {
    // Feature-specific code
}

// Set tenant context (for cron jobs)
TenantContext::setById($specificTenantId);
```

### Service Pattern

Services use static methods for business logic:

```php
namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class ExampleService
{
    public static function getSomething(int $id): ?array
    {
        $tenantId = TenantContext::getId();
        $stmt = Database::query(
            "SELECT * FROM examples WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );
        return $stmt->fetch() ?: null;
    }

    public static function createSomething(array $data): int
    {
        $tenantId = TenantContext::getId();
        Database::query(
            "INSERT INTO examples (name, tenant_id) VALUES (?, ?)",
            [$data['name'], $tenantId]
        );
        return Database::lastInsertId();
    }
}
```

### Controller Pattern

```php
namespace Nexus\Controllers\Api;

use Nexus\Core\Database;

class ExampleApiController
{
    private function jsonResponse($data, $status = 200)
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit;
    }

    private function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }

    public function index()
    {
        $items = ExampleService::getAll();
        return $this->jsonResponse(['data' => $items]);
    }

    public function store()
    {
        $data = $this->getJsonInput();

        if (empty($data['name'])) {
            return $this->jsonResponse(['error' => 'Name required'], 400);
        }

        $id = ExampleService::createSomething($data);
        return $this->jsonResponse(['id' => $id], 201);
    }
}
```

### Authentication

```php
use Nexus\Core\Auth;
use Nexus\Core\AdminAuth;
use Nexus\Core\ApiAuth;
use Nexus\Core\Csrf;

// User authentication
$user = Auth::user();
if (!Auth::check()) {
    header('Location: /login');
    exit;
}

// Admin authentication (legacy PHP admin)
if (!AdminAuth::check()) {
    header('Location: /admin-legacy/login');
    exit;
}

// API authentication (token-based)
$user = ApiAuth::authenticate();

// CSRF protection (forms)
$token = Csrf::token();
Csrf::verify($_POST['csrf_token'] ?? '');
```

### Admin View Rendering (PHP)

Admin panels use PHP templates in `views/modern/admin/` and `views/super-admin/`:

```php
// Admin controller renders views from views/modern/admin/
$data = ['title' => 'Admin Page', 'items' => $items];
extract($data);
require __DIR__ . '/../../views/modern/admin/page.php';
```

Admin CSS lives in `httpdocs/assets/css/admin-*.css` and admin JS in `httpdocs/assets/js/admin-*.js`.

## CSS Architecture

The React frontend uses **Tailwind CSS 4** with the **HeroUI theme plugin**:

- **Entry point**: `react-frontend/src/index.css` — imports Tailwind, HeroUI plugin, and design tokens
- **Design tokens**: `react-frontend/src/styles/tokens.css` — CSS custom properties for light/dark themes
- **HeroUI plugin**: `react-frontend/src/hero.ts` — HeroUI Tailwind plugin configuration
- **Dark mode**: `@custom-variant dark (&:is(.dark *))` — class-based dark mode for HeroUI

```tsx
// Use Tailwind utilities for layout and spacing
<div className="flex items-center gap-4 p-6">

// Use HeroUI component props for component-level theming
<Button color="primary" variant="bordered" size="lg">

// Use CSS tokens for theme-aware custom values
<div className="bg-[var(--color-surface)] text-[var(--color-text)]">
```

## Testing

### PHPUnit Test Suites

```bash
# Run all tests
vendor/bin/phpunit

# Run specific suite
vendor/bin/phpunit --testsuite Unit
vendor/bin/phpunit --testsuite Integration
vendor/bin/phpunit --testsuite Feature
vendor/bin/phpunit --testsuite Services
vendor/bin/phpunit --testsuite Controllers

# Run API tests
php tests/run-api-tests.php
php tests/run-api-tests.php --suite=auth
```

### Test Environment

```php
// phpunit.xml sets:
APP_ENV=testing
DB_DATABASE=nexus_test
CACHE_DRIVER=array
SESSION_DRIVER=array
```

### Test Pattern

```php
namespace Nexus\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Nexus\Services\ExampleService;

class ExampleServiceTest extends TestCase
{
    public function testSomethingWorks(): void
    {
        $result = ExampleService::doSomething();
        $this->assertTrue($result);
    }
}
```

## Local Development

### Docker (Only Environment)

**Use Docker for all development.**

```bash
# Start the stack
docker compose up -d

# View logs
docker compose logs -f app

# Restart after config changes
docker compose restart app
```

### URLs (Docker)

| Service | URL | Purpose |
|---------|-----|---------|
| React Frontend | http://localhost:5173 | Primary UI (HeroUI + Tailwind) |
| React Admin | http://localhost:5173/admin | React admin panel (primary) |
| PHP API | http://localhost:8090 | Backend API |
| PHP Admin (Legacy) | http://localhost:8090/admin-legacy/ | PHP admin panel |
| phpMyAdmin | http://localhost:8091 | DB admin (needs `--profile tools`) |

### Database Access

```bash
# CLI access
docker exec -it nexus-php-db mysql -unexus -pnexus_secret nexus

# Or use phpMyAdmin
docker compose --profile tools up -d
# Then visit http://localhost:8091
```

### Theme Testing

Toggle light/dark mode via the sun/moon icon in the Navbar, or set `theme` in browser DevTools to test `light`, `dark`, and `system` preferences.

---

## Deployment

### Production Server (Azure) - PRIMARY

| Item | Value |
|------|-------|
| **Host** | `20.224.171.253` |
| **User** | `azureuser` |
| **SSH Key** | `C:\ssh-keys\project-nexus.pem` |
| **Deploy Path** | `/opt/nexus-php/` |
| **Method** | Git pull + Docker rebuild |
| **Plesk Panel** | <https://20.224.171.253:8443> |

#### Production Domains

| Domain | Container | Port | Purpose |
|--------|-----------|------|---------|
| `app.project-nexus.ie` | `nexus-react-prod` | 3000 | React Frontend |
| `api.project-nexus.ie` | `nexus-php-app` | 8090 | PHP API |
| `project-nexus.ie` | `nexus-sales-site` | 3001 | Sales/Marketing Site |

#### Deploy to Azure (Production)

Production uses **git pull** from GitHub (deploy key `ed25519` configured).

**🆕 Enhanced Features:**
- ✅ **Rollback capability** - One-command rollback to last successful deploy
- ✅ **Pre-deploy validation** - Disk space, files, containers, database checks
- ✅ **Post-deploy smoke tests** - API, frontend, database, container health
- ✅ **Deployment locking** - Prevents concurrent deploys
- ✅ **Comprehensive logging** - Timestamped logs in `/opt/nexus-php/logs/`

**Deployment from Windows (recommended):**

```bash
# 1. Push changes to GitHub (pre-push hook validates build)
git push origin main

# 2. Deploy to production
scripts\deploy-production.bat           # Full: git pull + rebuild + nginx + health check
scripts\deploy-production.bat quick     # Quick: git pull + restart (OPCache clear - DEFAULT)

# 3. Check status
scripts\deploy-production.bat status    # Current commit + containers + health checks

# 4. Rollback if needed (NEW!)
scripts\deploy-production.bat rollback  # Rollback to last successful deploy

# 5. View logs (NEW!)
scripts\deploy-production.bat logs      # Recent deployment logs + container logs

# 6. Update nginx only
scripts\deploy-production.bat nginx     # Update nginx config only
```

**Manual deployment on server (SSH):**
```bash
ssh -i "C:\ssh-keys\project-nexus.pem" azureuser@20.224.171.253
cd /opt/nexus-php

# Deploy modes
sudo bash scripts/safe-deploy.sh quick     # Quick: git pull + restart (DEFAULT)
sudo bash scripts/safe-deploy.sh full      # Full: git pull + rebuild --no-cache
sudo bash scripts/safe-deploy.sh rollback  # Rollback to last successful deploy (NEW!)
sudo bash scripts/safe-deploy.sh status    # Check deployment status (NEW!)

# Verification only (no changes)
sudo bash scripts/verify-deploy.sh
```

**📖 Full documentation:** See [scripts/DEPLOYMENT_README.md](scripts/DEPLOYMENT_README.md) for detailed deployment guide.

#### 🔴 Production Git Safety Rules (CRITICAL)

Production has git initialized at `/opt/nexus-php/` tracking `origin/main`.

**SAFE operations:**
```bash
sudo git fetch origin main                    # Safe — only fetches, changes nothing
sudo git log --oneline -5                     # Safe — read only
sudo git status                               # Safe — read only
sudo bash scripts/safe-deploy.sh              # Safe — restores compose.yml after pull
```

**DANGEROUS operations — NEVER run directly:**
```bash
sudo git reset --hard origin/main             # DANGER: Overwrites compose.yml with dev version!
sudo git clean -fd                            # DANGER: Deletes ALL 1173 untracked files (.env, uploads, configs)
sudo git checkout -- .                        # DANGER: Overwrites compose.yml
```

**Why compose.yml is dangerous:** The repo tracks `compose.yml` (local dev config). Production needs `compose.prod.yml` as its active `compose.yml`. A bare `git reset --hard` replaces the production compose with the dev one — wrong ports, wrong env vars, wrong container names. The deploy script and `safe-deploy.sh` always run `cp compose.prod.yml compose.yml` after any git reset.

**Production-only files (NOT in git, must never be deleted):**

| File | Purpose | Protected by |
|------|---------|-------------|
| `.env` | Database passwords, API keys, secrets | `.gitignore` (untracked) |
| `compose.yml` (active) | Production Docker config (copied from `compose.prod.yml`) | `safe-deploy.sh` restores after pull |
| `config/nginx/` | Nginx templates | Untracked |
| `httpdocs/uploads/` (228MB) | User-uploaded images and files | `.gitignore` (untracked) |
| `vendor/` | PHP dependencies (installed on server) | `.gitignore` (untracked) |
| `compose.yml.pre-deploy-backup` | Pre-deploy backup | Created by `safe-deploy.sh` |

**Deploy key:** ED25519 at `/home/azureuser/.ssh/id_ed25519` (read-only access to GitHub repo)

#### SSH to Azure

```bash
ssh -i "C:\ssh-keys\project-nexus.pem" azureuser@20.224.171.253

# View logs
ssh -i "C:\ssh-keys\project-nexus.pem" azureuser@20.224.171.253 "cd /opt/nexus-php && sudo docker compose logs -f app"
```

#### ⛔ THIS PROJECT'S CONTAINERS vs OTHER PROJECTS

**This project (`staging/`) owns ONLY these containers — everything prefixed `nexus-php-*` or `nexus-react-*` or `nexus-sales-*`:**

| Container | Ours? | Purpose |
|-----------|-------|---------|
| `nexus-php-app` | **YES** | PHP API (Apache) |
| `nexus-php-db` | **YES** | MariaDB |
| `nexus-php-redis` | **YES** | Redis |
| `nexus-react-prod` | **YES** | React Frontend |
| `nexus-sales-site` | **YES** | Sales/Marketing Site |
| `nexus-phpmyadmin` | **YES** | phpMyAdmin (local only) |

**NEVER deploy to, restart, or modify these — they belong to other projects:**

| Container | Project | Purpose |
|-----------|---------|---------|
| `nexus-backend-api` | asp.net-backend | .NET Core API |
| `nexus-backend-db` | asp.net-backend | PostgreSQL |
| `nexus-backend-rabbitmq` | asp.net-backend | RabbitMQ |
| `nexus-backend-llama` | asp.net-backend | Ollama AI |
| `nexus-frontend-dev` | nexus-modern-frontend | Next.js (IE) |
| `nexus-frontend-prod` | nexus-modern-frontend | Next.js (IE prod) |
| `nexus-uk-frontend-dev` | nexus-uk-frontend | Next.js (UK) |
| `nexus-civic-app` | nexus-civic | Node.js |
| `nexus-civic-db` | nexus-civic | PostgreSQL |

Protected directories on Azure: `/opt/nexus-backend/`, `/opt/nexus-modern-frontend/`, `/opt/nexus-uk-frontend/`

See [docs/new-production-server.md](docs/new-production-server.md) for full Azure documentation.

## Database Migrations

### Migration Files

Located in `/migrations/` with timestamp naming:

```
2026_01_22_add_layout_banner_feature_flag.sql
2026_01_20_add_federation_api_keys.sql
```

### Migration Best Practices

```sql
-- Always use IF EXISTS/IF NOT EXISTS
ALTER TABLE users ADD COLUMN IF NOT EXISTS new_field VARCHAR(255);

-- Make migrations idempotent
DROP INDEX IF EXISTS idx_users_email;
CREATE INDEX idx_users_email ON users(email);
```

### Running Migrations

```bash
php scripts/safe_migrate.php
```

## React Frontend

The React frontend is in `react-frontend/`. It's the **only UI**, built with:

- **Vite** — build tool and dev server
- **React 18** + **TypeScript** — UI framework
- **HeroUI** (`@heroui/react`) — component library (buttons, inputs, modals, cards, tables, etc.)
- **Tailwind CSS 4** (`tailwindcss` + `@tailwindcss/vite`) — utility-first CSS
- **Framer Motion** — animations (also HeroUI dependency)
- **Lucide React** — icon library
- **Lexical** — rich text editor
- **Recharts** — data visualization/charts
- **React Router v6** — client-side routing with tenant slug support

### 🔴 CRITICAL: Deployment Warning

**NEVER build locally and upload `dist/` to production!**

Local builds use wrong environment variables (`VITE_API_BASE=/api` instead of `https://api.project-nexus.ie/api`), which breaks the community selector on login/register pages.

**Always rebuild on the server:**

```bash
# Upload source files, then SSH to server:
cd /opt/nexus-php/react-frontend
sudo docker build --no-cache -f Dockerfile.prod \
  --build-arg VITE_API_BASE=https://api.project-nexus.ie/api \
  -t nexus-react-prod:latest .
sudo docker stop nexus-react-prod && sudo docker rm nexus-react-prod
sudo docker run -d --name nexus-react-prod --restart unless-stopped \
  -p 3000:80 --network nexus-php-internal nexus-react-prod:latest
```

See [docs/REACT_DEPLOYMENT.md](docs/REACT_DEPLOYMENT.md) for full instructions.

### Key Files

| File | Purpose |
|------|---------|
| `src/App.tsx` | Routes, providers, feature/module gates |
| `src/lib/api.ts` | API client with token refresh & interceptors |
| `src/types/api.ts` | TypeScript interfaces for API responses |
| `src/index.css` | Tailwind CSS 4 entry point, HeroUI plugin, design token imports |
| `src/hero.ts` | HeroUI Tailwind plugin configuration |
| `src/styles/tokens.css` | CSS custom properties for light/dark themes |

### React Contexts

| Context | File | Purpose |
|---------|------|---------|
| `AuthContext` | `src/contexts/AuthContext.tsx` | Authentication state, login/logout, user data |
| `TenantContext` | `src/contexts/TenantContext.tsx` | Tenant config, `hasFeature()`, `hasModule()` |
| `ToastContext` | `src/contexts/ToastContext.tsx` | Toast notifications (success/error/info) |
| `ThemeContext` | `src/contexts/ThemeContext.tsx` | Light/dark/system mode, persists to `users.preferred_theme` |
| `NotificationsContext` | `src/contexts/NotificationsContext.tsx` | Real-time notification state & unread counts |
| `PusherContext` | `src/contexts/PusherContext.tsx` | Pusher WebSocket connection for real-time events |

### React Hooks

| Hook | File | Purpose |
|------|------|---------|
| `useApi` | `src/hooks/useApi.ts` | GET requests with loading/error states |
| `useMutation` | `src/hooks/useMutation.ts` | POST/PUT/DELETE with loading/error |
| `usePaginatedApi` | `src/hooks/usePaginatedApi.ts` | Cursor-based pagination helper |
| `usePageTitle` | `src/hooks/usePageTitle.ts` | Sets `document.title` to "Page - Tenant" (all 41 pages) |
| `useToast` | via ToastContext | `showToast('message', 'success')` |
| `useAuth` | via AuthContext | Current user, `isAuthenticated` |
| `useTenant` | via TenantContext | `hasFeature()`, `hasModule()`, tenant settings |
| `useTheme` | via ThemeContext | `theme`, `setTheme('light' / 'dark' / 'system')` |
| `useNotifications` | via NotificationsContext | Notification list, unread count, mark-read |

### Key React Components

| Component | File | Purpose |
|-----------|------|---------|
| `Layout` | `src/components/layout/Layout.tsx` | Main page wrapper (Navbar + Footer + BackToTop + OfflineIndicator) |
| `Navbar` | `src/components/layout/Navbar.tsx` | Desktop nav with dropdowns, search overlay (Cmd+K) |
| `MobileDrawer` | `src/components/layout/MobileDrawer.tsx` | Mobile slide-out menu with search entry point |
| `Footer` | `src/components/layout/Footer.tsx` | Site footer |
| `FeatureGate` | `src/components/routing/FeatureGate.tsx` | Conditional render by `feature` or `module` prop |
| `ScrollToTop` | `src/components/routing/ScrollToTop.tsx` | Auto-scroll on route change (SPA fix) |
| `Breadcrumbs` | `src/components/navigation/Breadcrumbs.tsx` | Breadcrumb nav (used on 8+ detail/create pages) |
| `BackToTop` | `src/components/ui/BackToTop.tsx` | Floating scroll-to-top button (appears after 400px) |
| `OfflineIndicator` | `src/components/feedback/OfflineIndicator.tsx` | Offline/online status banner |
| `TransferModal` | `src/components/wallet/TransferModal.tsx` | Time credit transfer dialog |
| `CustomLegalDocument` | `src/components/legal/CustomLegalDocument.tsx` | Renders tenant-specific legal docs with section TOC |

### Legal Document System

Per-tenant custom legal documents (Terms, Privacy, Cookies, etc.) managed via admin and rendered on the React frontend.

**Architecture:**
- **DB tables:** `legal_documents` (per-tenant, keyed by `document_type`) + `legal_document_versions` (content, versioning, `is_current` flag)
- **API:** `LegalDocumentController.php` — `GET /api/v2/legal/{type}` returns current version, `GET /api/v2/legal/{type}/versions` returns version history, `GET /api/v2/legal/{type}/versions/{id}` returns specific version
- **React hook:** `useLegalDocument(type)` in `src/hooks/useLegalDocument.ts` — fetches custom doc, returns `{ document, loading }`. Waits for `TenantContext` to bootstrap before calling API (ensures `X-Tenant-ID` header is set)
- **Renderer:** `CustomLegalDocument` in `src/components/legal/CustomLegalDocument.tsx` — parses HTML content on `<h2>` boundaries into sections, renders TOC (when 4+ sections) + GlassCard per section with staggered animations
- **CSS:** `.legal-content` styles in `src/index.css` — handles `h2`, `h3`, `h4`, `p`, `ul`, `ol`, `strong`, `a`, `.legal-notice` callouts, nested lists, dark mode
- **Fallback:** Each legal page (Terms, Privacy, etc.) checks for a custom document first; if none exists, renders hardcoded default content

**Key details:**
- The `api.ts` response unwrapping uses `'data' in data ? data.data : data` (NOT `data.data ?? data`) — the `??` form treats `{data: null}` as nullish and returns the wrapper object instead of `null`
- `useLegalDocument` validates the response shape (`'id' in res.data && 'content' in res.data`) before setting state
- `CustomLegalDocument` detects documents with their own numbering (e.g. "1. Definitions") and uses those numbers in chips instead of auto-numbering; un-numbered sections show `·`
- Admin manages documents at `/admin-legacy/legal-documents` (PHP admin)
- Tenant 2 (hOUR Timebank) and Tenant 4 (Timebank Global) both have custom documents; Tenant 4's terms are comprehensive Platform Terms of Service (46K chars, 19 sections)

**Files:**
| File | Purpose |
|------|---------|
| `src/Controllers/LegalDocumentController.php` | API + admin CRUD |
| `src/hooks/useLegalDocument.ts` | React hook to fetch custom docs |
| `src/components/legal/CustomLegalDocument.tsx` | Section parser + renderer |
| `src/pages/public/TermsPage.tsx` | Terms page (custom or default) |
| `src/pages/public/PrivacyPage.tsx` | Privacy page (custom or default) |
| `src/pages/public/CookiesPage.tsx` | Cookies page (custom or default) |
| `src/pages/public/LegalVersionHistoryPage.tsx` | Version history timeline |
| `src/index.css` | `.legal-content` styles |

### Feature & Module Gating

The platform uses two gating mechanisms, both controlled per-tenant:

- **Features** (`tenants.features` JSON): Optional add-ons — `events`, `groups`, `gamification`, `goals`, `blog`, `resources`, `volunteering`, `exchange_workflow`, etc.
- **Modules** (`tenants.configuration.modules` JSON): Core platform functionality — `listings`, `wallet`, `messages`, `dashboard`, `feed`, etc.

```tsx
// In React components
const { hasFeature, hasModule } = useTenant();
if (hasFeature('gamification')) { /* show gamification UI */ }
if (hasModule('wallet')) { /* show wallet nav item */ }

// In App.tsx route definitions
<FeatureGate feature="events"><EventsPage /></FeatureGate>
<FeatureGate module="wallet"><WalletPage /></FeatureGate>
```

```php
// In PHP backend
if (TenantContext::hasFeature('gamification')) { /* ... */ }
```

Admin UI: `/admin/tenant-features` (React admin) — toggle switches for all features & modules per tenant. Clears Redis bootstrap cache on save.

### Admin Panel Routes

| Route Prefix | Purpose | Stack |
|--------------|---------|-------|
| `/admin/*` | React admin panel (primary) | React 18 + HeroUI + Tailwind CSS 4 |
| `/admin-legacy/*` | PHP admin panel | PHP controllers + `views/admin/` + `views/modern/admin/` |
| `/api/v2/admin/*` | Admin API endpoints (used by React admin) | PHP API controllers |
| `/super-admin/*` | Super admin PHP views | PHP controllers + `views/super-admin/` |

The React admin panel at `/admin` is the primary admin interface. PHP admin views are served at `/admin-legacy/` (view dispatchers in `views/admin/`, actual views in `views/modern/admin/`). `admin-legacy` is a reserved path in `tenant-routing.ts` to prevent slug collision.

### React Pages

All pages use `usePageTitle()` and are feature/module gated in `App.tsx`:

| Page | Route | Gate |
|------|-------|------|
| Dashboard | `/dashboard` | Module: `dashboard` |
| Listings | `/listings`, `/listings/:id` | Module: `listings` |
| Create Listing | `/listings/new`, `/listings/:id/edit` | Module: `listings` |
| Messages | `/messages`, `/messages/:id` | Module: `messages` |
| Wallet | `/wallet` | Module: `wallet` |
| Feed | `/feed` | Module: `feed` |
| Events | `/events`, `/events/:id` | Feature: `events` |
| Groups | `/groups`, `/groups/:id` | Feature: `groups` |
| Members | `/members` | — (protected) |
| Profile | `/profile/:id` | — (public) |
| Exchanges | `/exchanges`, `/exchanges/:id` | Feature: `exchange_workflow` |
| Request Exchange | `/listings/:id/request-exchange` | Feature: `exchange_workflow` |
| Notifications | `/notifications` | — (protected) |
| Settings | `/settings` | — (protected) |
| Search | `/search` | — (public) |
| Leaderboard | `/leaderboard` | Feature: `gamification` |
| Achievements | `/achievements` | Feature: `gamification` |
| Goals | `/goals` | Feature: `goals` |
| Volunteering | `/volunteering` | Feature: `volunteering` |
| Blog | `/blog`, `/blog/:slug` | Feature: `blog` |
| Resources | `/resources` | Feature: `resources` |
| Organisations | `/organisations`, `/organisations/:id` | Feature: `organisations` |
| Federation | `/federation/*` | Feature: `federation` |
| Help Center | `/help` | — (public) |
| About | `/about` | — (public) |
| Contact | `/contact` | — (public) |
| Home | `/` | — (public) |

### Running Tests & Building

```bash
cd react-frontend
npm test           # Run Vitest tests
npm run lint       # TypeScript check
npm run build      # Production build (Vite)
npm run dev        # Dev server (http://localhost:5173)
```

### API Endpoints (V2)

The React frontend uses V2 API endpoints at `/api/v2/*`. Grouped by feature:

**Core & Auth:**

| Endpoint | Controller |
|----------|------------|
| `/api/v2/tenant/bootstrap` | TenantBootstrapController |
| `/api/v2/tenants` | TenantBootstrapController |
| `/api/v2/platform/stats` | TenantBootstrapController |
| `/api/v2/categories` | CoreApiController |
| `/api/v2/realtime/config` | PusherAuthController |
| `/api/auth/login` | (existing auth) |
| `/api/auth/logout` | (existing auth) |

**Users & Profiles:**

| Endpoint | Controller |
|----------|------------|
| `/api/v2/users/me` | UsersApiController |
| `/api/v2/users/me/preferences` | UsersApiController |
| `/api/v2/users/me/theme` | UsersApiController |
| `/api/v2/users/me/avatar` | UsersApiController |
| `/api/v2/users/me/password` | UsersApiController |
| `/api/v2/users/me/notifications` | UsersApiController |
| `/api/v2/users/{id}` | UsersApiController |
| `/api/v2/users/{id}/listings` | UsersApiController |
| `/api/v2/connections` | ConnectionsApiController |

**Content & Social:**

| Endpoint | Controller |
|----------|------------|
| `/api/v2/listings` | ListingsApiController |
| `/api/v2/messages` | MessagesApiController |
| `/api/v2/events` | EventsApiController |
| `/api/v2/groups` | GroupsApiController |
| `/api/v2/feed` | SocialApiController |
| `/api/v2/blog` | BlogApiController |
| `/api/v2/resources` | ResourcesV2ApiController |
| `/api/v2/comments` | CommentsV2ApiController |
| `/api/v2/polls` | PollsApiController |
| `/api/v2/search` | SearchApiController |
| `/api/v2/notifications` | NotificationsApiController |

**Wallet & Exchanges:**

| Endpoint | Controller |
|----------|------------|
| `/api/v2/wallet/balance` | WalletApiController |
| `/api/v2/wallet/transactions` | WalletApiController |
| `/api/v2/wallet/transfer` | WalletApiController |
| `/api/v2/exchanges` | ExchangesApiController |
| `/api/v2/reviews` | ReviewsApiController |

**Gamification & Goals:**

| Endpoint | Controller |
|----------|------------|
| `/api/v2/gamification/profile` | GamificationV2ApiController |
| `/api/v2/gamification/badges` | GamificationV2ApiController |
| `/api/v2/gamification/leaderboard` | GamificationV2ApiController |
| `/api/v2/gamification/challenges` | GamificationV2ApiController |
| `/api/v2/goals` | GoalsApiController |
| `/api/v2/volunteering` | VolunteerApiController |

**Federation:**

| Endpoint | Controller |
|----------|------------|
| `/api/v2/federation/*` | FederationV2ApiController |

See `httpdocs/routes.php` for full V2 route definitions (50+ endpoints).

## Key Services Reference

120+ services in `src/Services/`. Most important ones by category:

**Core Platform:**

| Service | Purpose |
|---------|---------|
| `ListingService` | Listings CRUD & search |
| `MessageService` | Messages CRUD & conversations |
| `UserService` | User profiles & preferences |
| `WalletService` | Time credit transactions & balance |
| `TokenService` | JWT token management |
| `EventService` | Events CRUD & RSVPs |
| `GroupService` | Groups CRUD & membership |
| `FeedService` | Social feed posts & timeline |
| `CommentService` | Threaded comments (V2) |
| `ConnectionService` | User connections/friendships |
| `ReviewService` | Member reviews & ratings |
| `PollService` | Polls creation & voting |
| `GoalService` | Personal & community goals |
| `VolunteerService` | Volunteer opportunities & hours |
| `UploadService` | File uploads & media |

**Matching & Exchanges:**

| Service | Purpose |
|---------|---------|
| `SmartMatchingEngine` | AI-powered user/listing matching |
| `MatchApprovalWorkflowService` | Broker approval workflow for matches |
| `MatchLearningService` | ML feedback loop for match quality |
| `ExchangeWorkflowService` | Exchange lifecycle (request → complete) |
| `ListingRankingService` | Listing search ranking algorithm |

**Gamification:**

| Service | Purpose |
|---------|---------|
| `GamificationService` | XP, badges, levels, achievements |
| `LeaderboardService` | Leaderboard rankings |
| `LeaderboardSeasonService` | Seasonal leaderboard management |
| `StreakService` | Daily login/activity streaks |
| `DailyRewardService` | Daily reward claims |
| `XPShopService` | XP shop purchases |
| `ChallengeService` | Challenges & campaigns |

**Federation:**

| Service | Purpose |
|---------|---------|
| `FederationGateway` | Multi-community federation core |
| `FederationPartnershipService` | Partner community management |
| `FederationDirectoryService` | Cross-community directory |
| `FederationJwtService` | Federation JWT auth |
| `FederatedMessageService` | Cross-community messaging |
| `FederatedTransactionService` | Cross-community transactions |

**Notifications & Communication:**

| Service | Purpose |
|---------|---------|
| `NotificationDispatcher` | Central notification routing |
| `NotificationService` | In-app notifications |
| `PusherService` | Real-time WebSocket events |
| `WebPushService` | Browser push notifications |
| `FCMPushService` | Firebase Cloud Messaging (mobile) |
| `DigestService` | Email digest scheduling |
| `EmailTemplateBuilder` | Email template rendering |

**Admin & Security:**

| Service | Purpose |
|---------|---------|
| `AuditLogService` | Action logging |
| `AbuseDetectionService` | Content moderation |
| `TenantHierarchyService` | Multi-tenant hierarchy |
| `GroupApprovalWorkflowService` | Group membership approval |
| `TotpService` | TOTP two-factor auth |
| `RedisCache` | Redis caching layer |

## Key Models Reference

59+ models in `src/Models/`. Most important ones:

| Model | Table | Purpose |
|-------|-------|---------|
| `User` | `users` | User accounts |
| `Listing` | `listings` | Service offers/requests |
| `Transaction` | `transactions` | Time credit transfers |
| `FeedPost` | `feed_posts` | Social feed content |
| `Group` | `groups` | Community groups |
| `Event` | `events` | Events and RSVPs |
| `EventRsvp` | `event_rsvps` | Event attendance tracking |
| `Badge` | `user_badges` | Earned badges |
| `Tenant` | `tenants` | Tenant/community data |
| `Connection` | `connections` | User friendships/connections |
| `Notification` | `notifications` | In-app notifications |
| `Review` | `reviews` | Member reviews/ratings |
| `Poll` | `polls` | Polls and voting |
| `Goal` | `goals` | Personal/community goals |
| `Post` | `posts` | Blog posts |
| `Page` | `pages` | CMS pages |
| `Category` | `categories` | Content categorization |
| `ResourceItem` | `resource_items` | Shared resource files |
| `Gamification` | `gamification_*` | XP, levels, challenges |
| `Report` | `reports` | Content/user reports |
| `ActivityLog` | `activity_log` | User activity tracking |
| `VolOpportunity` | `vol_opportunities` | Volunteer opportunities |
| `VolApplication` | `vol_applications` | Volunteer applications |
| `VolLog` | `vol_logs` | Volunteer hours logged |
| `OrgWallet` | `org_wallets` | Organisation wallets |

## Common Tasks

### Add a New API Endpoint

1. Create controller in `src/Controllers/Api/`
2. Add route in `httpdocs/routes.php`
3. Add tests in `tests/Controllers/`

### Add a New Service

1. Create in `src/Services/`
2. Use static methods pattern
3. Always scope by tenant
4. Add unit tests

### Add a New Page (React Frontend)

1. Create page component in `react-frontend/src/pages/`
2. Use HeroUI components and Tailwind CSS for UI
3. Add route in `react-frontend/src/App.tsx` (with tenant slug support and FeatureGate if needed)
4. Add `usePageTitle()` hook for document title
5. Use `tenantPath()` for all internal links

### Add a Database Migration

1. Create SQL file in `/migrations/` with timestamp prefix
2. Use `IF EXISTS`/`IF NOT EXISTS` for safety
3. Run with `php scripts/safe_migrate.php`

## Security Checklist

- [ ] Use prepared statements (never concatenate SQL)
- [ ] Validate CSRF tokens on forms
- [ ] Scope all queries by tenant_id
- [ ] Use `htmlspecialchars()` for output
- [ ] Rate limit authentication endpoints
- [ ] Validate and sanitize all user input
- [ ] Never expose internal errors to users

## Accessibility (WCAG 2.1 AA)

All frontend work should meet WCAG 2.1 AA standards:

- Minimum 4.5:1 contrast ratio for text
- Focus indicators on all interactive elements (HeroUI provides these by default)
- Semantic HTML structure
- ARIA labels where needed
- Keyboard navigation support
- Screen reader compatibility
- Use HeroUI's built-in accessibility props (`aria-label`, `aria-describedby`, etc.)

## Environment Variables

Key `.env` variables (never commit):

```
DB_HOST=localhost
DB_NAME=nexus
DB_USER=user
DB_PASS=password

PUSHER_APP_ID=
PUSHER_KEY=
PUSHER_SECRET=

USE_GMAIL_API=true
GMAIL_CLIENT_ID=
GMAIL_CLIENT_SECRET=
GMAIL_REFRESH_TOKEN=

OPENAI_API_KEY=
```

## Git Commit Convention

```
feat: Add new feature
fix: Bug fix
docs: Documentation only
style: Formatting, no code change
refactor: Code restructuring
test: Adding tests
chore: Maintenance tasks

Example:
feat(wallet): Add time credit transfer confirmation modal

Co-Authored-By: Claude <noreply@anthropic.com>
```

## Useful Commands

```bash
# React Frontend Development
cd react-frontend
npm install              # Install dependencies
npm run dev              # Dev server (localhost:5173)
npm run build            # Production build
npm test                 # Run Vitest tests
npm run lint             # TypeScript check

# PHP Backend
composer install          # Install PHP dependencies
vendor/bin/phpunit       # Run all tests
php tests/run-api-tests.php  # API tests

# Database
php scripts/backup_database.php    # Backup
php scripts/safe_migrate.php       # Run migrations
php scripts/seed_database.php      # Seed data

# Deployment (Azure) — git-based with rollback, validation, smoke tests
git push origin main                         # Push to GitHub (pre-push hook validates build)
scripts\deploy-production.bat                # Full: git pull + rebuild + health check
scripts\deploy-production.bat quick          # Quick: git pull + restart (OPCache clear)
scripts\deploy-production.bat rollback       # Rollback to last successful deploy
scripts\deploy-production.bat status         # Check git commit + container status
scripts\deploy-production.bat logs           # View recent deployment logs
```

## Regression Prevention System

> **Full guide:** [docs/REGRESSION_PREVENTION.md](docs/REGRESSION_PREVENTION.md)
> **Audit report:** [docs/plans/REGRESSION_AUDIT_REPORT.md](docs/plans/REGRESSION_AUDIT_REPORT.md)

### 7 Layers of Protection

| Layer | Tool | When | Blocks? |
|-------|------|------|---------|
| 1. Pre-commit | Husky + lint-staged | Every `git commit` | Yes — TypeScript check on staged files, PHP syntax on staged `.php` |
| 2. Pre-push | Husky pre-push | Every `git push` | Yes — full `tsc --noEmit` + `npm run build` |
| 3. CI Pipeline | GitHub Actions (`ci.yml`) | Every push/PR to `main` | Yes — 5 stages (PHP tests, React build, Docker verify, drift detect, regression patterns) |
| 4. PR Enforcement | GitHub Actions (`pr-checks.yml`) | Every PR to `main` | Yes — fix PRs must include Root Cause + Prevention sections |
| 5. Runtime Validation | Zod schemas (`api-schemas.ts`) | Dev mode only | No — console.warn on shape mismatch, zero production overhead |
| 6. Local Scripts | `check-dockerfile-drift.sh`, `check-regression-patterns.sh` | On demand | No — informational |
| 7. Deploy Rules | `--no-cache`, OPCache restart, server-side build | Every deployment | Manual enforcement |

### Mandatory Rules (NEVER SKIP)

1. **`--no-cache` on production builds** — Docker reuses stale layers without it
2. **`docker restart nexus-php-app` after PHP deploys** — OPCache never re-reads files
3. **Never double-unwrap** — `response.data` IS the final data; `response.meta` IS the meta
4. **Every DELETE/UPDATE must include `AND tenant_id = ?`** on tenant-scoped tables
5. **Run `scripts/verify-feature.sh`** after any agent swarm builds features
6. **Dockerfile limits must match** between `Dockerfile` and `Dockerfile.prod`
7. **Never build React locally and upload `dist/`** — always rebuild on the server
8. **Fix PRs must explain Root Cause + Prevention** — enforced by CI

### Git Hooks (Husky)

```bash
# Pre-commit: lint-staged runs TypeScript + PHP syntax on staged files only
# Pre-push: full tsc --noEmit + npm run build (blocks push on failure)
# Commit template: .gitmessage (includes Root Cause/Prevention fields for fix commits)
```

### CI/CD Pipeline Stages

| Stage | Name | What it checks | Blocking? |
|-------|------|---------------|-----------|
| 1 | PHP Checks | Syntax + PHPUnit Unit + Services tests (with MariaDB + Redis) | BLOCKING |
| 2 | React Build | `tsc --noEmit` + Vitest + `npm run build` | BLOCKING |
| 3 | Docker Verify | Builds all 3 containers, health check | BLOCKING |
| 4 | Dockerfile Drift | Compares 6 PHP settings between `Dockerfile` and `Dockerfile.prod` | BLOCKING |
| 5 | Regression Patterns | `data.data ??` (BLOCKING), `as any` count (WARN at >20), unscoped DELETE (WARN) | MIXED |

### Zod Runtime Validation (Dev Only)

API responses are validated against Zod schemas in development mode:

```tsx
// Schemas defined in react-frontend/src/lib/api-schemas.ts
// Validation helper in react-frontend/src/lib/api-validation.ts
// Wired into: api.ts, AuthContext.tsx, TenantContext.tsx

// Dev mode: console.warn on schema mismatch (never throws)
// Production: validation code is tree-shaken out (zero overhead)
```

### Regression Prevention Files

| File | Purpose |
|------|---------|
| `.husky/pre-commit` | Lint-staged hook (TypeScript + PHP syntax on staged files) |
| `.husky/pre-push` | Full TypeScript + build check before push |
| `.gitmessage` | Commit template with Root Cause/Prevention for fix commits |
| `.github/workflows/ci.yml` | 5-stage CI pipeline |
| `.github/workflows/pr-checks.yml` | PR root cause enforcement for fix PRs |
| `.github/pull_request_template.md` | PR template with mandatory sections |
| `react-frontend/src/lib/api-schemas.ts` | Zod schemas for API responses |
| `react-frontend/src/lib/api-validation.ts` | Dev-only validation helper |
| `scripts/check-dockerfile-drift.sh` | Local Dockerfile alignment check |
| `scripts/check-regression-patterns.sh` | Local regression pattern scanner |
| `scripts/verify-feature.sh` | Post-swarm feature verification |
| `docs/REGRESSION_PREVENTION.md` | Full regression prevention guide |
| `docs/plans/REGRESSION_AUDIT_REPORT.md` | Original audit findings |

---

## Troubleshooting

### Common Issues (Azure Production)

1. **500 Error**: `ssh azureuser@20.224.171.253 "sudo docker compose -f /opt/nexus-php/compose.yml logs -f app"`
2. **Container Down**: `ssh azureuser@20.224.171.253 "cd /opt/nexus-php && sudo docker compose ps"`
3. **502 Bad Gateway**: Check if container is running and healthy
4. **Database Error**: `sudo docker exec nexus-php-app env | grep DB_` to verify credentials
5. **Session Issues**: Check Redis container is healthy

### Debug Commands (Azure)

```bash
# SSH to server
ssh -i "C:\ssh-keys\project-nexus.pem" azureuser@20.224.171.253

# View PHP logs
sudo docker compose -f /opt/nexus-php/compose.yml logs -f app

# View all container status
sudo docker ps --format "table {{.Names}}\t{{.Status}}"

# Test API health
curl http://127.0.0.1:8090/health.php

# Test frontend health
curl http://127.0.0.1:3000/health
```

### Debug Mode

Set in `/opt/nexus-php/.env`:

```
DEBUG=true
DB_PROFILING=true
```

## Contact & Resources

- **Documentation**: `/docs/` directory
- **API Tests**: `/tests/` directory
- **Deployment Guide**: [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) (complete, consolidated guide)
