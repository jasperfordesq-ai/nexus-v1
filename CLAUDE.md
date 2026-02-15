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
| **Legacy PHP Admin** | http://localhost:8090/admin-legacy/ |
| **Legacy PHP Views** | http://localhost:8090/{tenant}/ |
| **phpMyAdmin** | http://localhost:8091 (with `--profile tools`) |

```bash
# Start everything
docker compose up -d

# Docker is the only dev environment
```

See [docs/LOCAL_DEV_SETUP.md](docs/LOCAL_DEV_SETUP.md) for full setup guide.

---

## MANDATORY RULES

### 🔴 REACT FRONTEND IS THE PRIMARY UI - CRITICAL

**The React frontend (`react-frontend/`) is the ONLY active frontend.** The legacy PHP views (`views/modern/`, `views/civicone/`) are being decommissioned and kept for reference only.

**Rules:**

- **ALL new UI work** goes in the React frontend — never create new PHP views
- **UI stack**: React 18 + TypeScript + **HeroUI** (component library) + **Tailwind CSS 4** + Framer Motion
- **Icons**: Lucide React (`lucide-react`)
- Use HeroUI components (`@heroui/react`) as the primary building blocks — buttons, inputs, modals, cards, tables, dropdowns, etc.
- Use Tailwind CSS utility classes for layout, spacing, and custom styling
- Use CSS tokens in `react-frontend/src/styles/tokens.css` for light/dark theme variables
- **Do NOT** create custom CSS component files — use Tailwind utilities and HeroUI theming instead
- **Do NOT** build new pages or features in the legacy PHP frontend
- Legacy PHP views may still be referenced for business logic understanding, but should not be modified for UI purposes

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

### Theme System - CRITICAL

**React frontend (light/dark mode) — PRIMARY:**

- `ThemeContext` manages `light`, `dark`, or `system` preference
- CSS tokens in `react-frontend/src/styles/tokens.css`
- HeroUI dark mode via `@custom-variant dark (&:is(.dark *))` in `index.css`
- Persists to `users.preferred_theme` via `PUT /api/v2/users/me/theme`
- Toggle in Navbar (sun/moon icon)

<details>
<summary>Legacy PHP themes (reference only — being decommissioned)</summary>

- Theme determined by **user preference** (not tenant or URL)
- Stored in `users.preferred_layout` column and `nexus_active_layout` session key
- Default theme is `modern`, alternative is `civicone`
- CivicOne follows GOV.UK Design System (WCAG 2.1 AA)

</details>

### General Principles

- **Do NOT default to the quickest solution**
- Prioritize maintainability and organization over speed
- Follow existing patterns in the codebase
- Ask if unsure about where code should live
- **Default to React frontend** for any UI work — legacy PHP views are reference only

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
- **React Frontend**: Primary UI — React 18 + HeroUI + Tailwind CSS 4 SPA at `react-frontend/`
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
├── views/                        # PHP templates (LEGACY - reference only, being decommissioned)
│   ├── civicone/                 # GOV.UK-based theme (WCAG 2.1 AA)
│   ├── modern/                   # Modern responsive theme
│   └── admin/                    # Legacy admin views (served under /admin-legacy/)
├── httpdocs/                     # Web root
│   ├── assets/                   # CSS, JS, images
│   ├── index.php                 # Main entry point
│   ├── routes.php                # Route definitions
│   └── health.php                # Docker health check
├── compose.yml                   # Docker Compose (primary dev env)
├── Dockerfile                    # PHP app container
├── .env.docker                   # Docker environment
├── tests/                        # PHPUnit tests
├── migrations/                   # SQL migration files
├── scripts/                      # Build, deploy, maintenance
├── capacitor/                    # Mobile app (Capacitor)
├── docs/                         # Documentation
└── config/                       # App configuration
```

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

### View Rendering (Legacy — reference only)

> **Note:** New UI work goes in the React frontend, not PHP views. This section is kept for reference when maintaining legacy code.

<details>
<summary>Legacy PHP view patterns (click to expand)</summary>

```php
// In controller
$data = ['title' => 'Page Title', 'items' => $items];
extract($data);
require __DIR__ . '/../../views/' . layout() . '/page.php';

// In view - use layout helper
<?php if (is_civicone()): ?>
    <!-- GOV.UK Design System markup -->
<?php else: ?>
    <!-- Modern theme markup -->
<?php endif; ?>

// Image optimization
<?= webp_image($imagePath, 'Alt text', 'css-class') ?>
<?= webp_avatar($user['avatar'], $user['name'], 40) ?>
```

</details>

## JavaScript Conventions (Legacy PHP files)

> **Note:** These rules apply to legacy JS in `/httpdocs/assets/js/`. For React frontend, use TypeScript and follow React/HeroUI patterns.

<details>
<summary>Legacy JS rules (click to expand)</summary>

### NO Inline Styles

ESLint enforces class-based styling:

```javascript
// WRONG - will fail lint
element.style.display = 'none';
element.style.padding = '10px';

// CORRECT - use CSS classes
element.classList.add('hidden');
element.classList.remove('hidden');
element.classList.toggle('active');
```

### General Rules

```javascript
// Use const/let, never var
const items = [];
let count = 0;

// Console usage - warn/error only
console.warn('Warning message');
console.error('Error message');
// console.log() triggers lint warning

// Unused variables - prefix with underscore
function handler(_event) {
    // event not used but needed for signature
}
```

</details>

## CSS Architecture

### React Frontend (Primary)

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

### Legacy PHP CSS (reference only — being decommissioned)

<details>
<summary>Legacy CSS architecture (click to expand)</summary>

**Two Themes:**

1. **CivicOne** (`views/civicone/`, `httpdocs/assets/css/civicone/`) — GOV.UK Design System, WCAG 2.1 AA
2. **Modern** (`views/modern/`, `httpdocs/assets/css/modern/`) — Contemporary responsive design

**File Organization:**

| Type | Location |
|------|----------|
| CSS | `/httpdocs/assets/css/` |
| JS | `/httpdocs/assets/js/` |
| PHP Views | `/views/{theme}/` |
| Partials | `/views/{theme}/partials/` |
| Layouts | `/views/layouts/{theme}/` |

**Legacy CSS Design Tokens** (for PHP views, not React):

```css
color: var(--color-text);
background: var(--color-background);
padding: var(--space-4);
font-size: var(--font-size-body);
```

See `/httpdocs/assets/css/design-tokens.css` for full palette.

**Legacy Build Commands:**

```bash
npm run build:css        # Build all CSS (includes validation)
npm run build:css:purge  # Run PurgeCSS
npm run minify:css       # Minify CSS files (includes validation)
npm run lint:css         # Lint CSS
npm run lint:js          # Lint JavaScript
npm run css:discover     # Find untracked CSS files
npm run css:auto-config  # Auto-add CSS to purgecss config
npm run validate:design-tokens  # Check design tokens aren't corrupted
```

**Design Tokens Protection:** Design token files are EXCLUDED from PurgeCSS. NEVER add `design-tokens.css` back to `purgecss.config.js`.

</details>

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
| Legacy PHP Admin | http://localhost:8090/admin-legacy/ | Legacy admin (being decommissioned) |
| Legacy PHP | http://localhost:8090/{tenant}/ | Legacy views (reference only) |
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

**React frontend (primary):** Toggle light/dark mode via the sun/moon icon in the Navbar, or set `theme` in browser DevTools to test `light`, `dark`, and `system` preferences.

<details>
<summary>Legacy PHP theme testing (reference only)</summary>

To test both legacy themes:

1. Change your user's `preferred_layout` in the database, OR
2. Switch via the UI theme toggle

</details>

---

## Deployment

### Production Server (Azure) - PRIMARY

| Item | Value |
|------|-------|
| **Host** | `20.224.171.253` |
| **User** | `azureuser` |
| **SSH Key** | `C:\ssh-keys\project-nexus.pem` |
| **Deploy Path** | `/opt/nexus-php/` |
| **Method** | Docker containers |
| **Plesk Panel** | <https://20.224.171.253:8443> |

#### Production Domains

| Domain | Container | Port | Purpose |
|--------|-----------|------|---------|
| `app.project-nexus.ie` | `nexus-react-prod` | 3000 | React Frontend |
| `api.project-nexus.ie` | `nexus-php-app` | 8090 | PHP API |
| `project-nexus.ie` | `nexus-sales-site` | 3001 | Sales/Marketing Site |

#### Deploy to Azure (Production)

```bash
# Windows
scripts\deploy-production.bat           # Full deployment
scripts\deploy-production.bat quick     # Code sync + restart only
scripts\deploy-production.bat init      # First-time setup
scripts\deploy-production.bat status    # Check container status

# Linux/Git Bash
./scripts/deploy-production.sh          # Full deployment
./scripts/deploy-production.sh --quick  # Code sync + restart only
./scripts/deploy-production.sh --init   # First-time setup
./scripts/deploy-production.sh --nginx  # Update nginx config only
```

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

---

### ⚠️ Legacy Server (GCP) - DISCONTINUED - REFERENCE ONLY

> **DO NOT DEPLOY TO THIS SERVER.** The GCP server at `35.205.239.67` is discontinued.
> All deployments go to Azure. This section is kept for historical reference only.

<details>
<summary>Legacy GCP details (click to expand - for reference only)</summary>

| Item | Value |
|------|-------|
| **Host** | `jasper@35.205.239.67` |
| **SSH Key** | `~/.ssh/id_ed25519` |
| **Path** | `/var/www/vhosts/project-nexus.ie` |
| **Method** | rsync/SCP (no Docker) |

Legacy deploy commands (DO NOT USE):
- `npm run deploy:preview`
- `npm run deploy`
- `npm run deploy:changed`
- `npm run deploy:full`

</details>

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

## React Frontend (Primary UI)

The React frontend is in `react-frontend/`. It's the **primary and only active UI**, built with:

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
| `/admin-legacy/*` | Legacy PHP admin (being decommissioned) | PHP controllers + `views/admin/` |
| `/api/v2/admin/*` | Admin API endpoints (used by React admin) | PHP API controllers |
| `/super-admin/*` | Super admin PHP views | PHP controllers (unchanged) |

The React admin panel at `/admin` is the primary admin interface. Legacy PHP admin routes have been moved to `/admin-legacy/` and are being decommissioned. `admin-legacy` is a reserved path in `tenant-routing.ts` to prevent slug collision.

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
feat(civicone): Add GOV.UK compliant form validation

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

# Legacy CSS/JS (for legacy PHP views only)
npm run build            # Full build (CSS, JS, images)
npm run lint             # Run all linters

# Database
php scripts/backup_database.php    # Backup
php scripts/safe_migrate.php       # Run migrations
php scripts/seed_database.php      # Seed data

# Deployment (Azure)
scripts\deploy-production.bat          # Full deployment
scripts\deploy-production.bat quick    # Code sync + restart only
```

## Troubleshooting

### Common Issues (Azure Production)

1. **500 Error**: `ssh azureuser@20.224.171.253 "sudo docker compose -f /opt/nexus-php/compose.yml logs -f app"`
2. **Container Down**: `ssh azureuser@20.224.171.253 "cd /opt/nexus-php && sudo docker compose ps"`
3. **502 Bad Gateway**: Check if container is running and healthy
4. **Database Error**: `sudo docker exec nexus-php-app env | grep DB_` to verify credentials
5. **CSS Not Loading**: Run `npm run build:css`, clear browser cache
6. **Session Issues**: Check Redis container is healthy

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
