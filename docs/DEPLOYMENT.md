<!-- Copyright © 2024–2026 Jasper Ford -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
# Project NEXUS — Deployment Guide

## Server Details

| Item | Value |
|------|-------|
| **Host** | Azure VM `20.224.171.253` |
| **SSH** | `ssh -i "C:\ssh-keys\project-nexus.pem" azureuser@20.224.171.253` |
| **Deploy Path** | `/opt/nexus-php/` |
| **Deploy Script** | `scripts/safe-deploy.sh` |
| **Docker Compose** | `compose.prod.yml` (production) / `compose.yml` (dev) |

## Production URLs

| Service | URL | Container | Port |
|---------|-----|-----------|------|
| React Frontend | https://app.project-nexus.ie | `nexus-react-prod` | 3000 |
| PHP API | https://api.project-nexus.ie | `nexus-php-app` | 8090 |
| Sales Site | https://project-nexus.ie | `nexus-sales-site` | 3003 |

All traffic routed through Cloudflare → Nginx reverse proxy on the VM.

---

## Deployment Modes

### Full Deploy (recommended)

Rebuilds **all containers** from scratch with `--no-cache`. Use for any code change.

```bash
# On your local machine:
git push origin main

# SSH into the server:
ssh -i "C:\ssh-keys\project-nexus.pem" azureuser@20.224.171.253

# On the server:
cd /opt/nexus-php
sudo bash scripts/safe-deploy.sh full
```

**What it does:**
1. Saves current commit (for rollback)
2. `git fetch origin main && git reset --hard origin/main`
3. Copies `compose.prod.yml` → `compose.yml`
4. `docker compose build --no-cache` (all containers)
5. `docker compose up -d --force-recreate`
6. Verifies production images (nginx, not node; OPCache off; display_errors off)
7. Runs smoke tests (API health, frontend, DB, Redis)
8. Writes `.build-version` with commit hash + timestamp
9. Purges Cloudflare cache (all domains)

### Quick Deploy

Rebuilds **frontend + sales site** and restarts PHP. Faster than full but still ensures React changes deploy.

```bash
sudo bash scripts/safe-deploy.sh quick
```

**What it does:**
1. Git pull
2. Copies `compose.prod.yml` → `compose.yml`
3. `docker compose build --no-cache frontend sales`
4. `docker compose up -d --force-recreate frontend sales`
5. `docker restart nexus-php-app` (clears OPCache)
6. Verifies production images
7. Runs smoke tests + Cloudflare purge

### Rollback

Reverts to the last successful deployment commit and does a full rebuild.

```bash
sudo bash scripts/safe-deploy.sh rollback
```

### Status Check

Read-only — shows current commit, last successful deploy, container status.

```bash
sudo bash scripts/safe-deploy.sh status
```

---

## Container Architecture

### Our Containers (managed by `compose.prod.yml`)

| Container | Image | Dockerfile | Purpose |
|-----------|-------|-----------|---------|
| `nexus-php-app` | Built from `Dockerfile.prod` | PHP 8.2 + Apache | API backend |
| `nexus-react-prod` | `nexus-react-prod:latest` | `react-frontend/Dockerfile.prod` | React SPA (Nginx) |
| `nexus-php-db` | `mariadb:10.11` | — | Database |
| `nexus-php-redis` | `redis:7-alpine` | — | Cache + sessions |
| `nexus-sales-site` | Built from `sales-site/Dockerfile` | Nginx | Marketing site |

### Other Project Containers (NEVER TOUCH)

`nexus-backend-*`, `nexus-frontend-*`, `nexus-uk-*`, `nexus-civic-*` — belong to other projects on the same VM.

---

## Dev vs Production Dockerfiles

### PHP (`Dockerfile` vs `Dockerfile.prod`)

| Setting | Dev | Prod |
|---------|-----|------|
| PHP config | `php.ini-development` | `php.ini-production` |
| `display_errors` | On | **Off** |
| `opcache.validate_timestamps` | 1 (auto-reload) | **0** (requires restart) |
| Apache `ServerTokens` | Full | **Prod** |
| `expose_php` | On | **Off** |
| Healthcheck | No | **Yes** |

**⚠️ Because `opcache.validate_timestamps=0` in production, you MUST restart the PHP container after code changes. The deploy script handles this automatically.**

### React Frontend (`react-frontend/Dockerfile` vs `react-frontend/Dockerfile.prod`)

| Aspect | Dev | Prod |
|--------|-----|------|
| Base | `node:20-alpine` | Multi-stage: `node:20-alpine` → `nginx:alpine` |
| Server | Vite dev server (`:5173`) | **Nginx** (`:80`) |
| Build | Live rebuild (hot reload) | `npm run build` → static `/app/dist` |
| Build args | None | `VITE_API_BASE`, `VITE_SENTRY_DSN`, `BUILD_COMMIT` |

**Key difference:** Dev runs Node.js, production runs Nginx. The image verification step in the deploy script checks for `nginx` binary to confirm the correct image is running.

---

## Post-Deploy Verification

The deploy script automatically verifies:

1. **Image type** — React container has `nginx` (not `node`)
2. **Image name** — `nexus-react-prod:latest` (not `nexus-react-dev` or `staging_frontend`)
3. **OPCache** — `validate_timestamps=0` (production)
4. **display_errors** — Off (production)
5. **API health** — `http://127.0.0.1:8090/health.php` responds 200
6. **Frontend health** — `http://127.0.0.1:3000/` responds 200
7. **Database** — MariaDB ping succeeds
8. **Container health** — No unhealthy containers

If any check fails, the deploy logs a clear error with remediation steps.

---

## Troubleshooting

### React changes not appearing after deploy

**Cause:** Old Docker image still running (not rebuilt).

**Fix:** Always use `full` deploy:
```bash
sudo bash scripts/safe-deploy.sh full
```

### PHP changes not appearing after deploy

**Cause:** OPCache serving stale bytecode.

**Fix:** Restart the PHP container:
```bash
docker restart nexus-php-app
```
The deploy script does this automatically.

### "Dev image on production" error

**Cause:** Dev `compose.yml` was used instead of `compose.prod.yml`.

**Fix:**
```bash
cd /opt/nexus-php
cp compose.prod.yml compose.yml
sudo bash scripts/safe-deploy.sh full
```

### Cloudflare serving stale content

**Fix:** Purge cache manually:
```bash
bash scripts/purge-cloudflare-cache.sh
```

### Container won't start / unhealthy

**Check logs:**
```bash
docker compose logs app --tail=50        # PHP
docker compose logs frontend --tail=50   # React
docker compose logs db --tail=50         # Database
```

---

## Critical Rules

1. **Always use `--no-cache`** on production Docker builds — stale layers cause phantom bugs
2. **Always restart `nexus-php-app`** after PHP changes — OPCache never re-reads files
3. **Never build React locally and upload `dist/`** — always rebuild on the server
4. **Cloudflare cache purge after every deploy** — automated in deploy script
5. **Never run dev compose on production** — always use `compose.prod.yml`
6. **Never touch other project containers** — only manage `nexus-php-*`, `nexus-react-prod`, `nexus-sales-site`

---

## Running Database Migrations on Production

> **Automated migrations (recommended):** As of March 2026, `safe-deploy.sh` automatically detects and runs pending migrations during both `quick` and `full` deploys. You no longer need to run migrations manually. The `status` command also shows pending migration count.

### Manual Method (if needed)

`php scripts/safe_migrate.php` **does NOT work** via `docker exec` because:
- `bootstrap.php`, `scripts/`, and `migrations/` are **NOT volume-mounted** into `nexus-php-app`
- Only `httpdocs/`, `src/`, `views/`, `config/` are mounted (read-only)
- `sudo` requires a PTY (`use_pty` in sudoers) which breaks non-interactive SSH

### ✅ Correct Method: Run SQL directly via the DB container

```bash
# Step 1: SCP migration files to the server (from local machine)
scp -i "C:\ssh-keys\project-nexus.pem" migrations/your_migration.sql \
    azureuser@20.224.171.253:/opt/nexus-php/migrations/

# Step 2: SSH in with RequestTTY=force for sudo support
ssh -i "C:\ssh-keys\project-nexus.pem" -o RequestTTY=force azureuser@20.224.171.253

# Step 3: Run SQL against the DB container (from the server)
sudo docker exec -i nexus-php-db \
    mysql -u nexus -pYOUR_DB_PASS nexus \
    < /opt/nexus-php/migrations/your_migration.sql
```

Or as a one-liner from local machine (no interactive SSH needed):
```bash
# Must pipe the file in via stdin — docker exec -i reads from stdin
ssh -i "C:\ssh-keys\project-nexus.pem" -o RequestTTY=force azureuser@20.224.171.253 \
    "sudo docker exec -i nexus-php-db mysql -u nexus -pYOUR_DB_PASS nexus \
    < /opt/nexus-php/migrations/your_migration.sql; echo EXIT:\$?"
```

### Why `-o RequestTTY=force` is required

`/etc/sudoers` has `use_pty` set — sudo refuses to run without a terminal.
- `ssh ... "sudo ..."` → exit 255, no output
- `ssh -t ...` → "Pseudo-terminal will not be allocated because stdin is not a terminal"
- `ssh -o RequestTTY=force ...` → works ✅

### DB Credentials (production)

Production credentials are stored in `/opt/nexus-php/.env` on the server. **Never commit credentials to the repository.**

### Verify migrations ran

```bash
ssh -i "C:\ssh-keys\project-nexus.pem" -o RequestTTY=force azureuser@20.224.171.253 \
    "sudo docker exec nexus-php-db mysql -u nexus -pYOUR_DB_PASS nexus \
    -e \"SHOW TABLES LIKE 'your_table%';\""
```
