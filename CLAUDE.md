# Project NEXUS - AI Assistant Guide

## Quick Reference

| Item | Value |
|------|-------|
| **Project** | Project NEXUS - Timebanking Platform |
| **PHP Version** | 8.2+ |
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
| **Legacy PHP Views** | http://localhost:8090/{tenant}/ |
| **phpMyAdmin** | http://localhost:8091 (with `--profile tools`) |

```bash
# Start everything
docker compose up -d

# Stop XAMPP first if running - Docker is the primary dev environment
```

See [docs/LOCAL_DEV_SETUP.md](docs/LOCAL_DEV_SETUP.md) for full setup guide.

---

## MANDATORY RULES

### 🔴 CIVICONE ULTIMATE SOURCE OF TRUTH - CRITICAL

#### FOR ALL CIVICONE COMPONENTS/PAGES: GOV.UK Frontend GitHub Repository is the ONLY source of truth

- **Repository:** <https://github.com/alphagov/govuk-frontend>
- **Components:** <https://github.com/alphagov/govuk-frontend/tree/main/packages/govuk-frontend/src/govuk/components>
- **Design System:** <https://design-system.service.gov.uk/>

**Mandatory process when working on ANY CivicOne file:**

1. Search GOV.UK Frontend GitHub for the component
2. Extract exact CSS/HTML/patterns from official source
3. Implement using official GOV.UK styles (no custom interpretations)
4. Document GitHub source in code comments
5. Test WCAG 2.1 AA compliance

**This rule overrides all other guidance for CivicOne.** When in doubt, always check the GOV.UK Frontend GitHub repository first.

See: `docs/CIVICONE_WCAG21AA_SOURCE_OF_TRUTH.md` for full specification.

---

### CSS Rules - CRITICAL

- **NEVER** write inline `<style>` blocks in PHP/HTML files
- **NEVER** use inline `style=""` attributes except for truly dynamic values (e.g., calculated widths)
- All CSS must go in `/httpdocs/assets/css/` with clear file names
- Create new CSS files for new components (e.g., `component-name.css`)
- CSS must be loaded via layout headers (`views/layouts/*/header.php`)
- Add new CSS files to `purgecss.config.js`

### Color Variables - MANDATORY

Always use CSS variables from `design-tokens.css` instead of hardcoded hex colors:

```css
/* CORRECT */
color: var(--color-primary-500);
background: var(--color-warning);
border-color: var(--color-gray-500);

/* WRONG - never use hardcoded colors in new code */
color: #6366f1;
background: #fbbf24;
```

See `/httpdocs/assets/css/design-tokens.css` for full palette.

> **Note**: Legacy files have hardcoded colors - do NOT mass-replace (risk of regressions).

### JavaScript Rules - CRITICAL

- **NEVER** write large inline `<script>` blocks in PHP files
- Extract JS to `/httpdocs/assets/js/` files
- Small event handlers (1-2 lines) in `onclick` are acceptable
- Anything more complex goes in external JS files

### Theme System - CRITICAL

- Theme is determined by **user preference** (not tenant or URL)
- Stored in `users.preferred_layout` column and `nexus_active_layout` session key
- Default theme is `modern`, alternative is `civicone`
- **Both themes must be kept in sync** - if you edit `views/modern/X.php`, check if `views/civicone/X.php` exists and needs the same change
- Before making changes to theme files, **always test on both themes**

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
- **React Frontend**: New SPA frontend (in development) at `react-frontend/`

## Directory Structure

```text
project-nexus/
├── react-frontend/               # React 18 SPA (NEW)
│   ├── src/
│   │   ├── components/           # Reusable UI components
│   │   ├── contexts/             # React contexts (Auth, Tenant, Toast)
│   │   ├── pages/                # Page components
│   │   ├── lib/                  # API client, helpers
│   │   ├── hooks/                # Custom React hooks
│   │   └── types/                # TypeScript types
│   ├── Dockerfile                # Frontend container
│   └── package.json              # Dependencies (Vite, HeroUI, etc.)
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
├── views/                        # PHP templates (legacy)
│   ├── civicone/                 # GOV.UK-based theme (WCAG 2.1 AA)
│   ├── modern/                   # Modern responsive theme
│   └── admin/                    # Admin panel views
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

// Admin authentication
if (!AdminAuth::check()) {
    header('Location: /admin/login');
    exit;
}

// API authentication (token-based)
$user = ApiAuth::authenticate();

// CSRF protection (forms)
$token = Csrf::token();
Csrf::verify($_POST['csrf_token'] ?? '');
```

### View Rendering

Use PHP templates with layout detection:

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

## JavaScript Conventions

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

## CSS Architecture

### Two Themes

1. **CivicOne** (`views/civicone/`, `httpdocs/assets/css/civicone/`)
   - GOV.UK Design System based
   - WCAG 2.1 AA compliant
   - Accessibility-first approach

2. **Modern** (`views/modern/`, `httpdocs/assets/css/modern/`)
   - Contemporary responsive design
   - Feature-rich UI

### File Organization

| Type | Location |
|------|----------|
| CSS | `/httpdocs/assets/css/` |
| JS | `/httpdocs/assets/js/` |
| PHP Views | `/views/{theme}/` |
| Partials | `/views/{theme}/partials/` |
| Layouts | `/views/layouts/{theme}/` |

### CSS Design Tokens

Use design tokens for consistency:

```css
/* Colors */
color: var(--color-text);
background: var(--color-background);
border-color: var(--color-border);
color: var(--color-primary-500);
background: var(--color-warning);

/* Spacing */
padding: var(--space-4);
margin: var(--space-2);
gap: var(--space-3);

/* Typography */
font-size: var(--font-size-body);
font-weight: var(--font-weight-bold);
```

### Tracking New CSS Files

When creating new CSS files, ensure they're included in the build pipeline:

```bash
# Check if your new CSS file is tracked
npm run css:discover

# Auto-add all missing CSS files to purgecss.config.js
npm run css:auto-config
```

**Why this matters:** CSS files not in `purgecss.config.js` won't be optimized for production, leading to missing styles in deployed builds.

### Build Commands

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

### ⚠️ CRITICAL: Design Tokens Protection

**Design token files are EXCLUDED from PurgeCSS** because PurgeCSS removes CSS variables (it thinks they're unused classes).

**Files protected:**

- `design-tokens.css` / `design-tokens.min.css`
- `desktop-design-tokens.css` / `desktop-design-tokens.min.css`
- `mobile-design-tokens.css` / `mobile-design-tokens.min.css`

**NEVER add these back to purgecss.config.js!**

See [CSS_BUILD_RULES.md](CSS_BUILD_RULES.md) for full details.

If styling breaks site-wide, check if design-tokens.min.css is corrupted:

```bash
npm run validate:design-tokens
# If corrupted, rebuild with:
npm run minify:css
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

### Docker (Primary Environment)

**Use Docker for all development.** Stop XAMPP if it's running.

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
| React Frontend | http://localhost:5173 | New SPA |
| PHP API | http://localhost:8090 | Backend API |
| Legacy PHP | http://localhost:8090/{tenant}/ | Traditional views |
| phpMyAdmin | http://localhost:8091 | DB admin (needs `--profile tools`) |

### Database Access

```bash
# CLI access
docker exec -it nexus-mysql-db mysql -unexus -pnexus_secret nexus

# Or use phpMyAdmin
docker compose --profile tools up -d
# Then visit http://localhost:8091
```

### Theme Testing (Legacy PHP Views)

To test both themes:

1. Change your user's `preferred_layout` in the database, OR
2. Switch via the UI theme toggle

**Important**: Always test changes on both themes before committing.

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

#### ⛔ PROTECTED SERVICES ON AZURE (DO NOT TOUCH)

The Azure server hosts a separate .NET platform. **NEVER modify these:**

| Container | Port | Purpose |
|-----------|------|---------|
| `nexus-backend-api` | 5080 | .NET Core API |
| `nexus-frontend-prod` | 5171 | Next.js Frontend |
| `nexus-backend-db` | 5432 | PostgreSQL |

Protected directories: `/opt/nexus-backend/`, `/opt/nexus-modern-frontend/`

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

## React Frontend

The new React frontend is in `react-frontend/`. It's a Vite + React 18 + TypeScript SPA.

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
| `src/lib/api.ts` | API client with token refresh |
| `src/contexts/AuthContext.tsx` | Authentication state |
| `src/contexts/TenantContext.tsx` | Tenant config/features |
| `src/App.tsx` | Routes and providers |

### Running Tests

```bash
cd react-frontend
npm test           # Run Vitest tests
npm run lint       # TypeScript check
```

### API Endpoints (V2)

The React frontend uses V2 API endpoints at `/api/v2/*`:

| Endpoint | Controller |
|----------|------------|
| `/api/v2/tenant/bootstrap` | TenantBootstrapController |
| `/api/v2/listings` | ListingsApiController |
| `/api/v2/messages` | MessagesApiController |
| `/api/v2/users/me` | UsersApiController |
| `/api/v2/events` | EventsApiController |
| `/api/v2/groups` | GroupsApiController |
| `/api/auth/login` | (existing auth) |
| `/api/auth/logout` | (existing auth) |

See `httpdocs/routes.php` for full V2 route definitions.

## Key Services Reference

| Service | Purpose |
|---------|---------|
| `GamificationService` | XP, badges, levels, achievements |
| `MatchingService` | User/listing matching algorithm |
| `WalletService` | Time credit transactions |
| `FederationGateway` | Multi-community federation |
| `PusherService` | Real-time notifications |
| `WebPushService` | Push notifications |
| `DigestService` | Email digests |
| `AuditLogService` | Action logging |
| `TokenService` | JWT token management (NEW) |
| `ListingService` | Listings CRUD (V2 API) |
| `MessageService` | Messages CRUD (V2 API) |
| `UserService` | User profiles (V2 API) |

## Key Models Reference

| Model | Table | Purpose |
|-------|-------|---------|
| `User` | `users` | User accounts |
| `Listing` | `listings` | Service offers/requests |
| `Transaction` | `transactions` | Time credit transfers |
| `FeedPost` | `feed_posts` | Social feed content |
| `Group` | `groups` | Community groups |
| `Event` | `events` | Events and RSVPs |
| `Badge` | `user_badges` | Earned badges |
| `Tenant` | `tenants` | Tenant/community data |

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

### Add a New View

1. Create in `views/civicone/` AND `views/modern/`
2. Or use layout detection: `views/' . layout() . '/page.php`
3. Use design tokens for styling
4. Ensure WCAG compliance

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

The CivicOne theme follows GOV.UK Design System standards:

- Minimum 4.5:1 contrast ratio for text
- Focus indicators on all interactive elements
- Semantic HTML structure
- ARIA labels where needed
- Keyboard navigation support
- Screen reader compatibility

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
# Development
composer install          # Install PHP dependencies
npm install              # Install Node dependencies
npm run build            # Full build (CSS, JS, images)
npm run lint             # Run all linters

# Testing
vendor/bin/phpunit       # Run all tests
php tests/run-api-tests.php  # API tests

# Database
php scripts/backup_database.php    # Backup
php scripts/safe_migrate.php       # Run migrations
php scripts/seed_database.php      # Seed data

# Deployment
npm run deploy:preview   # Dry run
npm run deploy           # Deploy last commit
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
