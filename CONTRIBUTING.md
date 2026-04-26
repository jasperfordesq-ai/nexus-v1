# Contributing to Project NEXUS

Thank you for your interest in contributing to Project NEXUS — an open-source, multi-tenant community timebanking platform. This guide covers everything you need to get started, including environment setup, coding standards, and the pull request process.

Project NEXUS is released under **AGPL-3.0-or-later**. By contributing, you agree that your contributions will be licensed under the same terms.

By submitting a contribution, you also agree to the [Project NEXUS Contributor Terms](CONTRIBUTOR_TERMS.md), including the licence, patent, ownership, third-party-code, and AI-disclosure terms described there.

**Repository:** https://github.com/jasperfordesq-ai/nexus-v1

---

## Table of Contents

1. [Code of Conduct](#code-of-conduct)
2. [Contributor Terms](#contributor-terms)
3. [Getting Started](#getting-started)
4. [Development Environment Setup](#development-environment-setup)
5. [Project Structure Overview](#project-structure-overview)
6. [How to Contribute](#how-to-contribute)
7. [Frontend Contribution Workflow](#frontend-contribution-workflow)
8. [Backend Contribution Workflow](#backend-contribution-workflow)
9. [Mobile Contribution Workflow](#mobile-contribution-workflow)
10. [SPDX Header Requirement (Mandatory)](#spdx-header-requirement-mandatory)
11. [Coding Standards](#coding-standards)
12. [Git Commit Convention](#git-commit-convention)
13. [Running Tests](#running-tests)
14. [AGPL-3.0 Compliance](#agpl-30-compliance)
15. [Attribution Requirements](#attribution-requirements)

---

## Code of Conduct

All contributors are expected to behave respectfully and professionally. We do not tolerate harassment, discrimination, or hostile behaviour in any project space — issues, pull requests, discussions, or elsewhere. Please report any concerns to the project maintainer.

---

## Contributor Terms

All contributions are subject to the [Project NEXUS Contributor Terms](CONTRIBUTOR_TERMS.md). These terms give Jasper Ford, and any entity he designates to steward Project NEXUS, the right to use contributions under AGPL-3.0-or-later and under commercial or proprietary licence terms.

Do not submit a contribution unless you have the right to make that grant. Contributions must not include secrets, confidential material, incompatible third-party code, or undisclosed AI-generated material.

Pull requests are checked automatically. A PR cannot pass the Project NEXUS PR quality checks unless the contributor-terms acknowledgement is checked and the AI and third-party-material disclosure fields are completed.

---

## Getting Started

1. **Fork** the repository on GitHub: https://github.com/jasperfordesq-ai/nexus-v1
2. **Clone** your fork:
   ```bash
   git clone https://github.com/YOUR_USERNAME/nexus-v1.git
   cd nexus-v1
   ```
3. **Set up the development environment** (see below).
4. **Create a branch** for your work:
   ```bash
   git checkout -b feat/your-feature-name
   ```
5. Make your changes, write tests, and submit a pull request.

---

## Development Environment Setup

**Docker is the only supported development environment.** No local PHP or Node.js installation is required.

### Prerequisites

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (Windows/macOS) or Docker Engine + Compose (Linux)
- Git

### Start the platform

```bash
docker compose up -d
```

This starts all services. The first run will pull and build images, which may take several minutes.

### Service URLs

| Service | URL |
|---------|-----|
| React Frontend | http://localhost:5173 |
| PHP API | http://localhost:8090 |
| Sales Site | http://localhost:3001 |
| React Admin | http://localhost:5173/admin |
| PHP Admin (Legacy) | http://localhost:8090/admin-legacy/ |
| phpMyAdmin | http://localhost:8091 (start with `--profile tools`) |

### Environment variables

Copy `.env.example` to `.env` and fill in the required values. **Never commit `.env`** — the repository is public.

### Full setup guide

See [docs/LOCAL_DEV_SETUP.md](docs/LOCAL_DEV_SETUP.md) for detailed instructions, including database seeding, first-time configuration, and troubleshooting.

---

## Project Structure Overview

```text
nexus-v1/
├── react-frontend/               # React 18 + HeroUI + Tailwind CSS 4 SPA (PRIMARY UI)
│   ├── src/
│   │   ├── components/           # Shared UI components
│   │   ├── contexts/             # React context providers
│   │   ├── hooks/                # Custom React hooks
│   │   ├── pages/                # Page-level components (one per route)
│   │   ├── lib/                  # Utilities, API client, helpers
│   │   ├── styles/               # tokens.css (theme-aware CSS variables)
│   │   └── types/                # TypeScript type definitions
│   └── CLAUDE.md                 # React-specific conventions
├── app/                          # Laravel 12 application (PHP 8.2+)
│   ├── Http/Controllers/         # Laravel controllers
│   ├── Models/                   # Eloquent models
│   ├── Services/                 # Business logic services
│   └── Listeners/                # Event listeners
├── src/                          # Legacy PHP (PSR-4: Nexus\) — admin views only
│   ├── Controllers/Api/          # V2 API controllers
│   ├── Models/                   # Legacy data models
│   └── Services/                 # Legacy services
├── mobile/                       # Capacitor + Expo mobile app
├── sales-site/                   # Static Next.js marketing site
│   └── CLAUDE.md                 # Sales site conventions
├── database/
│   └── migrations/               # Laravel migrations (use these for new schema changes)
├── migrations/                   # Legacy SQL migrations (historical)
├── tests/                        # PHPUnit test suite
├── docs/                         # Developer documentation
├── scripts/                      # Build, deploy, and maintenance scripts
├── compose.yml                   # Docker Compose (development)
└── compose.prod.yml              # Docker Compose (production)
```

**The React frontend (`react-frontend/`) is the primary UI.** All user-facing pages belong there. PHP admin views at `views/admin/` and `views/modern/admin/` are maintained only for legacy admin panels at `/admin-legacy/` and `/super-admin/`.

---

## How to Contribute

### Reporting bugs

Open an issue on GitHub with:
- A clear, descriptive title
- Steps to reproduce
- Expected vs. actual behaviour
- Browser/OS/version details if relevant
- Any relevant console errors or screenshots

### Requesting features

Open a GitHub Discussion or issue tagged `enhancement`. Describe the use case, not just the solution. Large features should be discussed before implementation begins.

### Submitting a pull request

1. Make sure your branch is based on the latest `main`.
2. Write or update tests for your changes.
3. Ensure all tests pass and linting is clean (the pre-push hook will verify this automatically).
4. Open a PR against `main` with a clear description of:
   - What the change does
   - Why it is needed
   - How to test it
5. Link any related issues.

Fix PRs must include a **Root Cause** and **Prevention** explanation in the description.

### Discussions

For questions, architectural ideas, or anything not suited to an issue, use GitHub Discussions.

---

## Frontend Contribution Workflow

The React frontend lives in `react-frontend/`. See [react-frontend/CLAUDE.md](react-frontend/CLAUDE.md) for full conventions.

### Stack

| Tool | Version |
|------|---------|
| React | 18 |
| TypeScript | 5 (strict mode) |
| HeroUI | `@heroui/react` (latest) |
| Tailwind CSS | 4 |
| Framer Motion | latest |
| Icons | Lucide React (`lucide-react`) only |
| Testing | Vitest |

### Component rules

- **HeroUI first.** Use HeroUI components (`Button`, `Card`, `Modal`, `Input`, `Chip`, etc.) as primary building blocks. Only fall back to raw HTML elements when HeroUI has no equivalent.
- **Tailwind utilities only.** Apply all layout, spacing, and colour via Tailwind utility classes. Do not create separate CSS component files.
- **CSS tokens.** Use theme-aware colour variables from `src/styles/tokens.css` (e.g., `var(--color-primary)`) for colours that must adapt to light/dark themes.
- **Icons.** Import icons exclusively from `lucide-react`. Do not use other icon libraries.
- **No hardcoded colours.** Never use raw hex values or Tailwind colour utilities that bypass the token system for brand/semantic colours.

### Adding a new page

1. Create a page component in `react-frontend/src/pages/`.
2. Add the route in `App.tsx` — wrap with `FeatureGate` if the feature is tenant-gated.
3. Call `usePageTitle()` at the top of the component.
4. Use `tenantPath()` for all internal navigation links.
5. Write a Vitest test covering the key render paths.

### Running the frontend dev server

```bash
# Inside Docker (recommended)
docker compose up -d
# Then visit http://localhost:5173

# Or outside Docker (requires Node 20+)
cd react-frontend
npm install
npm run dev
```

### Frontend tests

```bash
cd react-frontend
npm test          # Run Vitest in watch mode
npm run lint      # TypeScript check (tsc --noEmit)
npm run build     # Production build check
```

---

## Backend Contribution Workflow

The backend is Laravel 12 + PHP 8.2+. See [docs/PHP_CONVENTIONS.md](docs/PHP_CONVENTIONS.md) for full patterns.

### Namespace conventions

| Code | Namespace | Location |
|------|-----------|----------|
| New Laravel code | `App\` | `app/` |
| Legacy code (admin only) | `Nexus\` | `src/` |

All new PHP code goes in `app/`. Do not add new files under `src/` unless they are strictly required by the legacy admin panel.

### Multi-tenant scoping (CRITICAL)

**Every database query against a tenant-scoped table must include a `tenant_id` filter.** This is a security requirement, not a preference.

```php
// Correct
$listings = Listing::where('tenant_id', TenantContext::getId())
    ->where('status', 'active')
    ->get();

// WRONG — never do this
$listings = Listing::where('status', 'active')->get();
```

The `tenant_id` column must appear in every `WHERE`, `UPDATE`, and `DELETE` on tenant-scoped tables. A missing scope is a cross-tenant data leak (IDOR vulnerability).

### Services

- Services use static methods.
- Always resolve the tenant ID via `TenantContext::getId()` at the start of each method.
- Business logic lives in services; controllers are thin.

### Database migrations

New schema changes use Laravel migrations in `database/migrations/`:

```bash
php artisan make:migration create_my_table
```

- Always use `if exists` / `if not exists` guards for idempotency.
- Never modify existing migration files that have already run in production — create a new migration instead.

### Running the backend

```bash
docker compose up -d
# PHP API is available at http://localhost:8090
```

### Backend tests

```bash
# From your local machine via Docker
docker exec nexus-php-app vendor/bin/phpunit
docker exec nexus-php-app vendor/bin/phpunit --testsuite Unit
docker exec nexus-php-app vendor/bin/phpunit --testsuite Services
```

Test environment uses `APP_ENV=testing`, database `nexus_test`, and `CACHE_DRIVER=array`.

---

## Mobile Contribution Workflow

The mobile app is a Capacitor + Expo application in `mobile/`. It shares the same React component patterns as the main frontend.

### Stack

- Expo (React Native)
- Capacitor (native bridge)
- TypeScript strict mode
- Same HeroUI and Tailwind conventions as `react-frontend/` where applicable

### Running mobile tests

```bash
cd mobile
npx expo test
```

### Key notes

- Mobile-specific API calls live in `mobile/lib/api/`.
- Security-sensitive logic (certificate pinning, etc.) lives in `mobile/lib/security/`.
- Follow the same SPDX header and multi-tenant rules that apply everywhere else.

---

## SPDX Header Requirement (Mandatory)

**Every new source file** (`.php`, `.ts`, `.tsx`) must include the following SPDX copyright header.

**TypeScript / TSX** — first lines of the file:

```typescript
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.
```

**PHP** — immediately after `<?php`:

```php
<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.
```

### Tooling

```bash
# Add headers to all files missing them
node scripts/add-spdx-headers.mjs

# Verify all files have correct headers
node scripts/check-spdx.mjs
```

Pull requests that introduce new source files without SPDX headers will not be merged.

---

## Coding Standards

### Global platform — no locale-specific validation

Project NEXUS serves timebanks worldwide. **Never add Ireland-specific or any other locale-specific validation.**

- Use `Validator::isPhone()` for international E.164 phone validation — never `isIrishPhone()` or any pattern matching `+353`, `08x`, or `00353`.
- Use neutral international examples in form placeholders (e.g., `+1 555 123 4567`), not Irish numbers.
- Never default maps or location fields to Ireland/Dublin. Use a neutral global centre.
- `validateIrishLocation()` is legacy code — do not call it.

### Dead legacy themes — never touch

The following PHP theme directories are dead legacy code and must never be modified, fixed, or referenced:

- `views/civicone/`
- `views/modern/` (non-admin subdirectories)
- `views/starter/`

The only maintained PHP views are `views/admin/` and `views/modern/admin/`.

### General rules

- **Prepared statements always.** Never concatenate user input into SQL queries.
- **CSRF tokens** on all state-changing forms.
- **`htmlspecialchars()`** on all PHP output that is not already escaped.
- **Rate-limit auth endpoints.**
- **Validate and sanitize all input** before use.
- **Never expose internal error details** to the client.
- **No raw arrays as query parameters** — use `implode(',', array_fill(0, count($ids), '?'))` for `IN` clauses.
- **TypeScript strict mode** — no `any` unless absolutely unavoidable, and document why.
- **Accessibility (WCAG 2.1 AA)** — minimum 4.5:1 contrast ratio, focus indicators, semantic HTML, ARIA labels, keyboard navigation. HeroUI provides built-in accessibility props.

---

## Git Commit Convention

Use the following prefixes for commit messages:

| Prefix | When to use |
|--------|-------------|
| `feat:` | New feature |
| `fix:` | Bug fix |
| `docs:` | Documentation only |
| `style:` | Formatting, whitespace (no logic change) |
| `refactor:` | Code restructure (no feature or bug change) |
| `test:` | Adding or fixing tests |
| `chore:` | Maintenance, dependency updates, tooling |

**Format:**

```
<prefix>(<scope>): <short imperative description>

Optional longer explanation if needed.

Co-Authored-By: Claude <noreply@anthropic.com>
```

**Examples:**

```
feat(wallet): Add time credit transfer confirmation modal
fix(listings): Correct tenant_id scope in search query
test(events): Add PHPUnit coverage for EventService::create
refactor(auth): Extract token validation to AuthHelper
```

- Keep the subject line under 72 characters.
- Use the imperative mood ("Add" not "Added", "Fix" not "Fixed").
- Reference GitHub issues in the body when relevant (`Closes #123`).

### Pre-commit and pre-push hooks

Husky hooks run automatically:

- **Pre-commit** (`lint-staged`): ESLint + `tsc --noEmit` on staged `.ts`/`.tsx` files.
- **Pre-push**: Full `tsc --noEmit` + production build check.

Do not skip hooks with `--no-verify` unless you have a specific, documented reason.

---

## Running Tests

### React frontend (Vitest)

```bash
cd react-frontend
npm test              # Watch mode
npm run test -- --run # Single run (CI)
npm run lint          # TypeScript type check
npm run build         # Production build check
```

### PHP backend (PHPUnit)

```bash
# Via Docker (recommended)
docker exec nexus-php-app vendor/bin/phpunit
docker exec nexus-php-app vendor/bin/phpunit --testsuite Unit
docker exec nexus-php-app vendor/bin/phpunit --testsuite Services
docker exec nexus-php-app php tests/run-api-tests.php
```

### Mobile (Expo)

```bash
cd mobile
npx expo test
```

### Static analysis (PHP)

```bash
docker exec nexus-php-app vendor/bin/phpstan analyse --no-progress --memory-limit=512M
```

PHPStan runs at level 3 targeting `src/`. It is currently warning-only (non-blocking). Fix pre-existing errors before promoting to blocking.

---

## AGPL-3.0 Compliance

Project NEXUS is licensed under the **GNU Affero General Public License v3.0 or later (AGPL-3.0-or-later)**.

Key obligations for contributors:

1. **Any modified version** of this software, when made available over a network (e.g., as a hosted service), must provide the complete corresponding source code to users of that service.
2. **All new files** you contribute must carry the SPDX header shown in the [SPDX Header Requirement](#spdx-header-requirement-mandatory) section.
3. **Attribution must not be removed.** The footer, mobile drawer, and auth pages must display the AGPL Section 7(b) attribution. Do not remove or obscure this attribution.
4. **Third-party dependencies** you add must be compatible with AGPL-3.0. Licenses that are incompatible with AGPL (e.g., proprietary, SSPL, non-commercial-only) may not be introduced.
5. **The NOTICE file** contains authoritative legal terms (Section 7 a–f). Do not modify it without fully understanding the implications.

The full license text is in the `LICENSE` file at the repository root.

---

## Attribution Requirements

Project NEXUS applies AGPL Section 7(b) attribution requirements. This means:

- **Every user-facing page** (footer, mobile drawer, authentication pages) must display the project attribution. Do not remove these.
- **Contributors** are listed in `react-frontend/src/data/contributors.json` and rendered programmatically on the About page. They are never hardcoded in templates.
- **AI-generated code** (e.g., produced with Claude Code) should include `Co-Authored-By: Claude <noreply@anthropic.com>` in the commit message.
- The `NOTICE` file at the repository root is the canonical record of authorship and third-party acknowledgements.

---

## Questions?

If anything in this guide is unclear, open a GitHub Discussion or file an issue. We're happy to help new contributors get set up.
