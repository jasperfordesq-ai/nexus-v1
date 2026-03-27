# Project NEXUS

> **Production-Ready V1** — Project NEXUS V1 is in active production use. The platform runs on Laravel 12 + PHP 8.2+ with a React 18 frontend. Contributions and feedback are welcome.

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
- **PWA & Native Mobile** — Progressive Web App plus Capacitor native apps (iOS & Android)
- **Real-Time** — Pusher WebSockets for live updates, FCM for mobile push
- **Internationalisation** — 7 supported languages: English, Irish (Gaeilge), German, French, Italian, Portuguese, Spanish
- **Light/Dark Theme** — System-aware theme with per-user preference

## Tech Stack

| Layer | Technology |
|-------|-----------|
| **Frontend** | React 18 + TypeScript + HeroUI + Tailwind CSS 4 |
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

## Quick Start

```bash
# Clone the repository
git clone https://github.com/jasperfordesq-ai/nexus-v1.git
cd nexus-v1

# Copy the example environment file and fill in your values
cp .env.docker.example .env.docker

# Start with Docker
docker compose up -d

# Run Laravel migrations to set up the database schema
docker exec nexus-php-app php artisan migrate

# Access the application
# React Frontend: http://localhost:5173
# PHP API:        http://localhost:8090
# Sales Site:     http://localhost:3001

# Mobile app (iOS/Android) — see mobile/ directory for Capacitor setup
```

## Database Setup

Run Laravel migrations after starting Docker to create the schema:

```bash
docker exec nexus-php-app php artisan migrate
```

A legacy schema dump is also available at [schema.sql](schema.sql) if needed for reference. For production migrations, see the [deployment guide](docs/DEPLOYMENT.md).

## Project Status

This is **production-ready V1**. The platform is in active production use:

- The **React frontend** (`react-frontend/`) is the primary and active UI
- The **Laravel 12 backend** provides the API — all services are native Laravel implementations
- The **legacy PHP admin views** (`views/`) remain for `/admin-legacy/` and `/super-admin/`; the React admin panel handles all other admin UI
- The **mobile app** (`mobile/`) is a Capacitor project targeting iOS and Android
- **Tests** are in `tests/` — coverage is growing with each release

We welcome contributors who are comfortable working with a modern Laravel + React codebase.

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
