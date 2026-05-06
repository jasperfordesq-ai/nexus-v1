# Project NEXUS

> **Version 1.5 — Release Candidate** — Project NEXUS V1.5 is a release candidate, in active production use while undergoing final pre-release validation. The platform runs on Laravel 12 + PHP 8.2+ with a React 18 frontend. It is currently in use by communities in **Ireland** and being tested by communities in the **United Kingdom**, **Spain**, **Switzerland**, and the **United States**. Contributions and feedback are welcome.

A modern, multi-tenant community time banking platform built with Laravel 12 + PHP 8.2+, React 18, and MariaDB.

## What is Time Banking?

Time banking is a community-based system where members exchange services using time as currency. One hour of service always equals one time credit, regardless of the type of service — everyone's time is valued equally.

## Features

- **Time Credits & Wallet** — Earn and spend time credits for community services
- **Listings Marketplace** — Browse and post service offers and requests
- **Private Messaging** — Connect directly with community members
- **Events** — Organise community gatherings with RSVP tracking
- **Groups** — Interest-based community groups and discussions
- **Social Feed** — Community posts, comments, likes, and polls
- **Gamification** — Badges, achievements, XP, leaderboards, and challenges
- **Volunteering** — Volunteer opportunities and hour logging
- **Blog & Resources** — Community news and shared resource library
- **Federation API** — Multi-community network with cross-community exchanges and federated identity
- **Smart Matching** — AI-powered matching of members and listings
- **Exchange Workflow** — Broker-approved service exchange lifecycle
- **Multi-Tenant** — Run multiple communities from one platform, each with its own branding and configuration
- **PWA & Native Mobile** — Progressive Web App, with native app packaging managed outside the default Docker setup
- **Real-Time** — Pusher WebSockets for live updates, FCM for mobile push
- **Internationalisation** — 11 supported languages: English, Irish (Gaeilge), German, French, Italian, Portuguese, Spanish, Dutch, Polish, Japanese, Arabic (with full RTL support)
- **Light/Dark Theme** — System-aware theme with per-user preference

## Tech Stack

| Layer | Technology |
|-------|-----------|
| **Frontend** | React 18 + TypeScript + HeroUI + Tailwind CSS 4 |
| **Accessible Frontend** | Laravel-rendered HTML + GOV.UK Frontend Sass/JS |
| **Backend API** | Laravel 12 + PHP 8.2+ |
| **Database** | MariaDB 10.11 |
| **Cache** | Redis 7+ |
| **Search** | Meilisearch |
| **CDN** | Cloudflare |
| **Real-Time** | Pusher (WebSockets) + Firebase Cloud Messaging |
| **Dev Environment** | Docker Compose |
| **Icons** | Lucide React |
| **Animations** | Framer Motion |
| **Charts** | Recharts |
| **Rich Text** | Lexical |

## Repository Topology

| Path | Purpose |
|------|---------|
| `app/`, `routes/`, `config/`, `bootstrap/` | Laravel 12 application, API routing, middleware, providers, and runtime configuration |
| `react-frontend/` | Primary React 18 + TypeScript UI for members and current admin workflows |
| `accessible-frontend/` | Accessibility-first, HTML-first frontend served by Laravel at `accessible.project-nexus.ie` and `/{tenantSlug}/alpha/...` |
| `views/` | Laravel Blade/email views plus legacy admin compatibility surfaces only |
| `httpdocs/` | Apache web root, public health endpoints, and compatibility entrypoints |
| `database/`, `migrations/`, `schema.sql` | Laravel migrations, legacy SQL history, and schema reference artifacts |
| `tests/`, `e2e/`, `playwright.config.ts` | PHPUnit, integration, and browser test coverage |
| `sales-site/` | Public marketing site container |
| `.github/` | CI, security, contributor, release, and dependency automation |
| `scripts/` | Build, migration, deployment, maintenance, and audit tooling |

Native mobile project artifacts are not required for the public Docker setup. The React PWA is the canonical user interface; native packaging is release-managed separately from normal local development.

## Quick Start

```bash
# Clone the repository
git clone https://github.com/jasperfordesq-ai/nexus-v1.git
cd nexus-v1

# Copy the example environment file and fill in your values
cp .env.docker.example .env.docker

# Start backend/database/services with Docker
docker compose up -d

# Start the React frontend with native Vite on Windows
npm run dev:frontend

# Run Laravel migrations to set up the database schema
docker exec nexus-php-app php artisan migrate

# Access the application
# React Frontend: http://localhost:5173
# PHP API:        http://localhost:8090
# Sales Site:     http://localhost:3001
# Accessible UI:  http://localhost:8090/hour-timebank/alpha

# Native app packaging is separate from the default Docker workflow
```

## Database Setup

Run Laravel migrations after starting Docker to create the schema:

```bash
docker exec nexus-php-app php artisan migrate
```

A legacy schema dump is also available at [schema.sql](schema.sql) if needed for reference. For zero-downtime deployment notes, see [docs/ZERO_DOWNTIME_DEPLOYMENT_PLAN.md](docs/ZERO_DOWNTIME_DEPLOYMENT_PLAN.md).

## Project Status

This is **version 1.5 — release candidate**, in active production use while undergoing final pre-release validation:

- The **React frontend** (`react-frontend/`) is the primary UI for user-facing pages and current admin workflows
- The **Accessible frontend** (`accessible-frontend/`) is an approved HTML-first UI track for core tenant pages, served by Laravel and planned for `accessible.project-nexus.ie`
- The **Laravel 12 backend** provides the API — all services are native Laravel implementations (zero stubs)
- The **legacy PHP admin views** are compatibility-only surfaces for `/admin-legacy/` and `/super-admin/`
- **Zero-downtime blue/green deployments** — production switches between blue and green container stacks with no maintenance window
- **Native mobile packaging** is managed separately from the default public Docker checkout
- **Tests** are in `tests/`, `react-frontend/src/**/*.test.*`, and `e2e/`; CI also runs static analysis, build, migration, i18n, SPDX, smoke, accessibility, and security gates

We welcome contributors who are comfortable working with a modern Laravel + React codebase.

## Quality, Security, and Releases

- Security reports use the private process in [SECURITY.md](SECURITY.md).
- Contributor behaviour expectations are documented in [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md).
- Dependency updates are managed by Dependabot for Composer, npm, Docker, and GitHub Actions.
- Pull requests run dependency review, CI, security scanning, i18n drift checks, SPDX checks, E2E smoke tests, and accessibility checks.
- GitHub Releases are created from version tags; see [.github/RELEASE_PROCESS.md](.github/RELEASE_PROCESS.md).

## Credits and Origins

### Creator

This software was created by **Jasper Ford**.

### Founders

The originating time bank initiative [hOUR Timebank CLG](https://hour-timebank.ie) was co-founded by:

- **Jasper Ford**
- **Mary Casey**

### Contributors

- **Steven J. Kelly** — Community insight, product thinking
- **Sarah Bird** — CEO, Timebanking UK

### Research Foundation

This software is informed by and builds upon a social impact study commissioned by the **West Cork Development Partnership**.

### Acknowledgements

- **West Cork Development Partnership**
- **Fergal Conlon**, SICAP Manager

## License

This software is licensed under the **GNU Affero General Public License version 3** (AGPL-3.0-or-later).

The AGPL requires that if you run a modified version of this software on a server and let others interact with it, you must make your source code available to those users.

See the [LICENSE](LICENSE) file for the full license text.
See the [NOTICE](NOTICE) file for attribution requirements.

## UI Attribution Requirement

Under AGPL Section 7(b), all public deployments of this software **must** display visible attribution and a link to the source code repository.

### Required Attribution

**Footer (all pages):**
> "Built on Project NEXUS by Jasper Ford"

This text must be a clickable hyperlink to: <https://github.com/jasperfordesq-ai/nexus-v1>

**About page:**
> "Powered by Project NEXUS
> Created by Jasper Ford
> Licensed under AGPL v3"

With a link to: <https://github.com/jasperfordesq-ai/nexus-v1>

### Compliance

- The [NOTICE](NOTICE) file contains the authoritative wording for all attribution requirements
- Removing or obscuring required attribution is a licence violation
- This requirement applies to all deployments, including modified versions and SaaS offerings

## Related Projects

Project NEXUS is being actively developed across two codebases:

| Version | Stack | Repository |
| ------- | ----- | --------- |
| **V1** (this repo) | Laravel 12 + PHP 8.2+ / React 18 / MariaDB | [nexus-v1](https://github.com/jasperfordesq-ai/nexus-v1) |
| **V2** | ASP.NET Core 8 / React 18 / PostgreSQL | [api.project-nexus.net](https://github.com/jasperfordesq-ai/api.project-nexus.net) |

V1 is the original platform — functional, in production, and the foundation of all Project NEXUS communities. It runs on Laravel 12 + PHP 8.2+ with a React 18 frontend. V2 is a new backend being built alongside V1, progressively replacing the PHP API using the [Strangler Fig pattern](https://martinfowler.com/bliki/StranglerFigApplication.html). Both share the same React frontend and design system.

## Source Code

The complete source code for this project is available at:
<https://github.com/jasperfordesq-ai/nexus-v1>
