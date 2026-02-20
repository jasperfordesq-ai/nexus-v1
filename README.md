# Project NEXUS

> **Release Preview** — This is an early public release of Project NEXUS. The platform is functional and in active use, but some areas of the codebase are still being cleaned up. The legacy PHP backend is being progressively replaced. Contributions and feedback are welcome.

A modern, multi-tenant community time banking platform built with PHP 8.2+, React 18, and MariaDB.

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
- **Federation** — Multi-community network with cross-community exchanges
- **Smart Matching** — AI-powered matching of members and listings
- **Exchange Workflow** — Broker-approved service exchange lifecycle
- **Multi-Tenant** — Run multiple communities from one platform, each with its own branding and configuration
- **PWA & Mobile** — Progressive Web App with Capacitor Android support
- **Real-Time** — Pusher WebSockets for live updates, FCM for mobile push
- **Light/Dark Theme** — System-aware theme with per-user preference

## Tech Stack

| Layer | Technology |
|-------|-----------|
| **Frontend** | React 18 + TypeScript + HeroUI + Tailwind CSS 4 |
| **Backend API** | PHP 8.2+ (custom MVC framework) |
| **Database** | MariaDB 10.11 |
| **Cache** | Redis 7+ |
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

# Import the database schema
# (after containers are running, import schema.sql into the MariaDB container)

# Start with Docker
docker compose up -d

# Access the application
# React Frontend: http://localhost:5173
# PHP API:        http://localhost:8090
# Sales Site:     http://localhost:3001
```

## Database Setup

A clean database schema is provided at [schema.sql](schema.sql). After starting Docker:

```bash
docker exec -i nexus-php-db mysql -unexus -p'YOUR_DB_PASS' nexus < schema.sql
```

## Project Status

This is a **release preview**. The platform is in active production use, but the codebase is still evolving:

- The **React frontend** (`react-frontend/`) is the primary and active UI
- The **PHP backend** (`src/`) provides the API — some legacy patterns remain and are being modernised
- The **legacy PHP admin views** (`views/`) are being progressively replaced by the React admin panel
- **Tests** are in `tests/` — coverage is growing with each release

We welcome contributors who are comfortable working with a codebase in active development.

## Credits and Origins

### Creator

This software was created by **Jasper Ford**.

### Founders

The originating time bank initiative [hOUR Timebank CLG](https://hour-timebank.ie) was co-founded by:

- **Jasper Ford**
- **Mary Casey**

### Contributors

- **Steven J. Kelly** — Community insight, product thinking

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
| **V1** (this repo) | PHP 8.2+ / React 18 / MariaDB | [nexus-v1](https://github.com/jasperfordesq-ai/nexus-v1) |
| **V2** | ASP.NET Core 8 / React 18 / PostgreSQL | [api.project-nexus.net](https://github.com/jasperfordesq-ai/api.project-nexus.net) |

V1 is the original platform — functional, in production, and the foundation of all Project NEXUS communities. V2 is a new backend being built alongside V1, progressively replacing the PHP API using the [Strangler Fig pattern](https://martinfowler.com/bliki/StranglerFigApplication.html). Both share the same React frontend and design system.

## Source Code

The complete source code for this project is available at:
<https://github.com/jasperfordesq-ai/nexus-v1>
