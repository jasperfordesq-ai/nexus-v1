# Project NEXUS - AI Assistant Guide

## Quick Reference

| Item | Value |
|------|-------|
| **Project** | Project NEXUS - Timebanking Platform |
| **PHP Version** | 8.1+ |
| **Database** | MySQL 8.0 |
| **Cache** | Redis 7+ |
| **Live URL** | https://project-nexus.ie |
| **Alt URL** | https://hour-timebank.ie |
| **Local Dev** | http://staging.timebank.local/ |
| **Test Tenant** | `hour-timebank` (tenant 2) |

---

## MANDATORY RULES

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

## Directory Structure

```
/home/user/hour-timebank/
├── src/                          # Main PHP source (PSR-4: Nexus\)
│   ├── Config/                   # Configuration (ai.php, pusher.php, config.php)
│   ├── Controllers/              # Request handlers (Admin/, Api/)
│   ├── Core/                     # Framework (Router, Database, Auth, Mailer)
│   ├── Helpers/                  # Global helpers (ImageUploader, etc.)
│   ├── Middleware/               # Request middleware
│   ├── Models/                   # Data models (59+ files)
│   ├── Services/                 # Business logic (94+ services)
│   └── helpers.php               # Global functions (layout(), webp_image())
├── app/                          # App-specific classes (PSR-4: App\)
├── views/                        # PHP templates
│   ├── civicone/                 # GOV.UK-based theme (WCAG 2.1 AA)
│   ├── modern/                   # Modern responsive theme
│   ├── skeleton/                 # Component library
│   ├── admin/                    # Admin panel views
│   └── [feature]/                # Feature-specific views
├── httpdocs/                     # Web root
│   ├── assets/                   # CSS, JS, images
│   ├── index.php                 # Main entry point
│   ├── routes.php                # Route definitions (1000+ routes)
│   └── sw.js                     # Service Worker (PWA)
├── tests/                        # PHPUnit tests
├── migrations/                   # SQL migration files
├── scripts/                      # Build, deploy, maintenance (100+ files)
├── capacitor/                    # Mobile app (Capacitor)
├── docs/                         # Documentation
├── config/                       # App configuration
└── bootstrap.php                 # Application initialization
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
$token = Csrf::getToken();
Csrf::validate($_POST['csrf_token'] ?? '');
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
npm run build:css        # Build all CSS
npm run build:css:purge  # Run PurgeCSS
npm run minify:css       # Minify CSS files
npm run lint:css         # Lint CSS
npm run lint:js          # Lint JavaScript
npm run css:discover     # Find untracked CSS files
npm run css:auto-config  # Auto-add CSS to purgecss config
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

### URLs

- **Local server**: `http://staging.timebank.local/`
- **Tenant URLs**: `http://staging.timebank.local/{tenant-slug}/`
- **Test tenant**: `hour-timebank` (tenant 2)

### Testing URLs

- Compose form: `http://staging.timebank.local/hour-timebank/compose`
- Dashboard: `http://staging.timebank.local/dashboard`
- Admin panel: `http://staging.timebank.local/admin`

### Theme Testing

To test both themes:
1. Change your user's `preferred_layout` in the database, OR
2. Switch via the UI theme toggle

**Important**: Always test changes on both themes before committing.

---

## Deployment

### Server Details

- **Host**: jasper@35.205.239.67
- **Path**: /var/www/vhosts/project-nexus.ie
- **Method**: SCP (not git pull)

### Deploy Commands

```bash
# Preview deployment (dry run)
npm run deploy:preview

# Deploy last commit
npm run deploy

# Deploy only changed files
npm run deploy:changed

# Deploy full folders
npm run deploy:full
```

### Manual SCP

```bash
# Single file
scp -i ~/.ssh/id_ed25519 "local/file.php" jasper@35.205.239.67:/var/www/vhosts/project-nexus.ie/path/file.php

# Check logs
ssh -i ~/.ssh/id_ed25519 jasper@35.205.239.67 "tail -50 /var/www/vhosts/project-nexus.ie/logs/error.log"
```

### Path Mapping

| Local | Remote |
|-------|--------|
| `httpdocs/` | `/var/www/vhosts/project-nexus.ie/httpdocs/` |
| `views/` | `/var/www/vhosts/project-nexus.ie/views/` |
| `src/` | `/var/www/vhosts/project-nexus.ie/src/` |
| `config/` | `/var/www/vhosts/project-nexus.ie/config/` |

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
| `LayoutHelper` | Theme/layout detection |

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

### Common Issues

1. **500 Error**: Check `/var/www/vhosts/project-nexus.ie/logs/error.log`
2. **Database Error**: Verify `.env` credentials, check MySQL is running
3. **CSS Not Loading**: Run `npm run build:css`, clear browser cache
4. **Session Issues**: Check Redis is running, verify session config

### Debug Mode

Set in `.env`:
```
DEBUG=true
DB_PROFILING=true
```

## Contact & Resources

- **Documentation**: `/docs/` directory
- **API Tests**: `/tests/` directory
- **Deployment Guide**: `/.claude/DEPLOYMENT.md`
