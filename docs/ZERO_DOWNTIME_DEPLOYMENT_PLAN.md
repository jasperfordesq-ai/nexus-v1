# Project NEXUS Zero-Downtime Deployment Plan

## Summary

The current production deployment path is safe in the narrow sense, but it creates avoidable public downtime. `scripts/safe-deploy.sh` turns global maintenance mode on before fetching, building, migrating, recreating containers, smoking, purging Cloudflare, and pre-rendering. Since production Docker builds can take many minutes, Google and users can repeatedly see a 503 maintenance page during ordinary deploys.

The professional replacement is a blue/green cutover:

1. Keep the current production stack serving traffic.
2. Pull the next release and build candidate images beside the live stack.
3. Start candidate API/frontend/sales containers on inactive local ports.
4. Run health and smoke checks against those candidate ports.
5. Switch Apache/Plesk reverse-proxy routes to the candidate ports with a graceful reload.
6. Keep the previous stack available for instant rollback, then retire it after a soak period.

Maintenance mode remains available for exceptional work only: destructive migrations, incident response, major infrastructure surgery, or forced offline maintenance. It should not be part of the normal 20-deploys-per-day path.

## What Is Wrong With The Current Flow

Current normal deploy:

```text
maintenance ON
git fetch/reset
docker compose build
run migrations
docker compose up -d --force-recreate
restart PHP for OPcache
smoke test
maintenance OFF
Cloudflare purge
pre-render tenants
```

Problems:

- Build time is public downtime.
- Recreate/restart time is public downtime.
- A failed smoke test leaves the site in maintenance mode.
- `docker compose up -d --force-recreate` destroys the previous good runtime before the new runtime is proven healthy.
- Database migrations run while the entire site is offline, encouraging deploys to treat downtime as normal.
- Cloudflare and search crawlers can observe repeated 503 windows.

## Target Architecture

```text
Cloudflare
  -> Apache/Plesk virtual hosts
      app.project-nexus.ie      -> 127.0.0.1:{active_frontend_port}
      api.project-nexus.ie      -> 127.0.0.1:{active_api_port}
      project-nexus.ie          -> 127.0.0.1:{active_sales_port}

Docker
  nexus-blue-php-app   8090
  nexus-blue-react     3000
  nexus-blue-sales     3003

  nexus-green-php-app  8190
  nexus-green-react    3100
  nexus-green-sales    3103

Shared stateful services:
  MariaDB, Redis, Meilisearch, uploads, storage, logs, prerendered volume
```

Only stateless runtime containers are blue/green. Database, Redis, Meilisearch, uploads, storage, logs, and persistent pre-render volumes stay shared. Queue and scheduler workers are cut over with the active color.

## Release Rules

- Every deploy builds from a separate Git worktree, so the live source directory is not mutated during the build.
- Every deploy builds immutable candidate images tagged by commit, not just `latest`.
- Candidate containers must pass Docker health checks and HTTP smoke checks before traffic moves.
- Apache route switching must be atomic from the user's perspective: write a new include file, run `apachectl configtest`, then graceful reload.
- Rollback is another route switch to the previous port set; it should not rebuild anything.
- Old containers remain running for a soak window, for example 15-60 minutes.
- Migrations must follow expand/contract discipline:
  - Expand: add nullable columns/tables/indexes first.
  - Deploy code compatible with old and new schema.
  - Backfill asynchronously when needed.
  - Contract: remove old columns/code in a later deploy.
- Global maintenance mode is reserved for non-backwards-compatible operations that cannot be made online.

## Implemented Files

- `Dockerfile.bluegreen` builds an immutable PHP/Apache image with Composer dependencies and application code inside the image.
- `compose.bluegreen.yml` starts the inactive API, React frontend, sales site, queue worker, and scheduler with color-specific container names and ports.
- `react-frontend/Dockerfile.bluegreen` and `react-frontend/nginx.bluegreen.conf` build a frontend image that proxies uploads to the matching color API container.
- `scripts/deploy/bluegreen-deploy.sh` prepares a release worktree under `/opt/nexus-releases` by default, starts the inactive color, waits for health checks, runs private smoke tests, switches Apache routes, starts matching worker containers, runs public smoke tests, refreshes pre-rendered HTML when needed, and schedules the delayed health check.
- `scripts/deploy/apache/*.example` documents the Apache/Plesk include shape expected by the route switch.

## Server Setup

The production Apache/Plesk vhosts must proxy through variables controlled by a small include file.

Create the active upstream include:

```apache
# /etc/apache2/conf-enabled/nexus-active-upstreams.conf
Define NEXUS_API_PORT 8090
Define NEXUS_FRONTEND_PORT 3000
Define NEXUS_SALES_PORT 3003
```

Point the vhosts to the variables, following the examples in `scripts/deploy/apache/`.

A switch updates only this include and runs:

```bash
apachectl configtest
systemctl reload apache2
```

If Plesk stores per-domain Apache includes elsewhere, use that path instead. The important rule is that the switch is a configtest plus graceful reload, not container recreation.

Disable the old host cron scheduler after the blue/green scheduler container is installed. Scheduled jobs should run in `nexus-{blue|green}-php-scheduler`, not by `docker exec nexus-php-app ...`.

## Candidate Stack

Run candidate app/frontend/sales containers on the inactive port set:

| Color | API | Frontend | Sales |
| --- | ---: | ---: | ---: |
| blue | 8090 | 3000 | 3003 |
| green | 8190 | 3100 | 3103 |

The candidate shares the existing Docker network and persistent volumes. Only one stateful database/Redis/Meilisearch stack should exist.

## Smoke Before Cutover

Before switching traffic:

- `http://127.0.0.1:{api_port}/health.php`
- `http://127.0.0.1:{api_port}/api/v2/tenant/bootstrap` with `X-Tenant-Slug: hour-timebank`
- `http://127.0.0.1:{frontend_port}/` contains `id="root"`
- `http://127.0.0.1:{sales_port}/`
- Candidate containers are healthy.

After switching:

- Public app/API/sales URLs return 2xx/3xx, not 503.
- CORS is valid between app and API.
- Tenant bootstrap works for `hour-timebank`.
- Maintenance mode is off at both file and database layers.

## Commands After Server Setup

Normal deploy:

```bash
export NEXUS_APACHE_ROUTES_FILE=/etc/apache2/conf-enabled/nexus-active-upstreams.conf
sudo bash scripts/deploy/bluegreen-deploy.sh deploy
```

Detached deploy with built-in monitoring:

```bash
export NEXUS_APACHE_ROUTES_FILE=/etc/apache2/conf-enabled/nexus-active-upstreams.conf
sudo bash scripts/deploy/bluegreen-deploy.sh deploy --detach
sudo bash scripts/deploy/bluegreen-deploy.sh monitor
```

Normal deploy with backwards-compatible Laravel migrations:

```bash
export NEXUS_APACHE_ROUTES_FILE=/etc/apache2/conf-enabled/nexus-active-upstreams.conf
sudo bash scripts/deploy/bluegreen-deploy.sh deploy --migrate
```

Rollback:

```bash
export NEXUS_APACHE_ROUTES_FILE=/etc/apache2/conf-enabled/nexus-active-upstreams.conf
sudo bash scripts/deploy/bluegreen-deploy.sh rollback
```

Status:

```bash
sudo bash scripts/deploy/bluegreen-deploy.sh status
```

Logs:

```bash
sudo bash scripts/deploy/bluegreen-deploy.sh logs -f
```

Fallback, only when explicitly needed:

```bash
sudo bash scripts/safe-deploy.sh full --detach
```

## Open Server Decisions

- Confirm the exact Apache/Plesk include file used by the three production vhosts.
- Confirm whether Plesk service name is `apache2`, `httpd`, or managed through `plesk sbin`.
- Decide whether `NEXUS_APACHE_ROUTES_FILE` should live under `/etc/apache2/conf-enabled/` or Plesk's domain-specific include directory.
