<!-- Copyright © 2024–2026 Jasper Ford -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
# Project NEXUS — Deployment Guide

## Server Details

| Item | Value |
|------|-------|
| **Host** | Azure VM `20.224.171.253` |
| **SSH** | `ssh -i "C:\ssh-keys\project-nexus.pem" azureuser@20.224.171.253` |
| **Deploy Path** | `/opt/nexus-php/` |
| **Primary Deploy Script** | `scripts/deploy/bluegreen-deploy.sh` |
| **Fallback Deploy Script** | `scripts/safe-deploy.sh` |
| **Docker Compose** | `compose.bluegreen.yml` (zero-downtime web) / `compose.prod.yml` (fallback production) / `compose.yml` (dev) |

## Production URLs

| Service | URL | Container | Port |
|---------|-----|-----------|------|
| React Frontend | https://app.project-nexus.ie | `nexus-{blue|green}-react` | 3000 or 3100 |
| PHP API | https://api.project-nexus.ie | `nexus-{blue|green}-php-app` | 8090 or 8190 |
| Sales Site | https://project-nexus.ie | `nexus-{blue|green}-sales` | 3003 or 3103 |

All traffic routes through Cloudflare and the Apache/Plesk reverse proxy on the VM.

---

## Deployment Modes

### Zero-Downtime Deploy (recommended)

Builds the next release beside the live release, smokes it on inactive local ports, then switches Apache/Plesk routing with a graceful reload. This is the normal path for frequent deploys.

```bash
# On your local machine:
git push origin main

# SSH into the server:
ssh -i "C:\ssh-keys\project-nexus.pem" azureuser@20.224.171.253

# On the server:
cd /opt/nexus-php
export NEXUS_APACHE_ROUTES_FILE=/etc/apache2/conf-enabled/nexus-active-upstreams.conf
sudo bash scripts/deploy/bluegreen-deploy.sh deploy
```

Detached deploy with built-in monitoring:

```bash
sudo bash scripts/deploy/bluegreen-deploy.sh deploy --detach
sudo bash scripts/deploy/bluegreen-deploy.sh monitor
```

Tail the latest deploy log:

```bash
sudo bash scripts/deploy/bluegreen-deploy.sh logs -f
```

**What it does:**
1. Leaves the current site live.
2. Fetches `origin/main` without resetting the live working tree.
3. Creates a separate release worktree under `/opt/nexus-releases/`.
4. Builds immutable PHP, React, and sales images tagged by commit.
5. Starts the inactive color on private local ports.
6. Waits for Docker health checks and private HTTP smoke tests.
7. Updates the Apache route include and runs a graceful Apache reload.
8. Runs public smoke tests after cutover.
9. Starts the matching queue worker and scheduler, then stops the previous worker containers.
10. Refreshes per-tenant pre-rendered HTML when public-facing files changed.
11. Writes a live deployment status file with phase, active color, target color, commit, and log path.
12. Schedules the normal delayed post-deploy health check.

Run backwards-compatible Laravel migrations only when needed:

```bash
sudo bash scripts/deploy/bluegreen-deploy.sh deploy --migrate
```

Rollback does not rebuild:

```bash
sudo bash scripts/deploy/bluegreen-deploy.sh rollback
```

Deployment status:

```bash
sudo bash scripts/deploy/bluegreen-deploy.sh status
```

Monitoring commands:

| Command | Purpose |
|---------|---------|
| `sudo bash scripts/deploy/bluegreen-deploy.sh monitor` | Shows phase, target color, relevant containers, and recent log lines every 5 seconds until success/failure. |
| `sudo bash scripts/deploy/bluegreen-deploy.sh logs` | Prints the latest blue/green deployment log. |
| `sudo bash scripts/deploy/bluegreen-deploy.sh logs -f` | Follows the latest blue/green deployment log live. |
| `sudo bash scripts/deploy/bluegreen-deploy.sh status` | Prints the active color, active ports, latest status file, and Apache route file. |

### Full Deploy (fallback only)

Rebuilds **all containers** from scratch with `--no-cache` and uses global maintenance mode. Use only as an emergency fallback or before the Apache blue/green route switch has been installed.

```bash
# On your local machine:
git push origin main

# SSH into the server:
ssh -i "C:\ssh-keys\project-nexus.pem" azureuser@20.224.171.253

# On the server:
cd /opt/nexus-php
sudo bash scripts/safe-deploy.sh full --detach
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

### Quick Deploy (fallback only)

Rebuilds **frontend + sales site** and restarts PHP. Faster than full but still ensures React changes deploy.

```bash
sudo bash scripts/safe-deploy.sh quick --detach
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
sudo bash scripts/safe-deploy.sh rollback --detach
```

### Status Check

Read-only — shows current commit, last successful deploy, container status.

```bash
sudo bash scripts/safe-deploy.sh status
```

---

## Container Architecture

### Blue/Green Runtime Containers (managed by `compose.bluegreen.yml`)

| Container | Image | Purpose |
|-----------|-------|---------|
| `nexus-blue-php-app` / `nexus-green-php-app` | `nexus-php-app:{commit}` | API backend |
| `nexus-blue-react` / `nexus-green-react` | `nexus-react-prod:{commit}` | React SPA |
| `nexus-blue-sales` / `nexus-green-sales` | `nexus-sales-site:{commit}` | Sales site |
| `nexus-blue-php-queue` / `nexus-green-php-queue` | `nexus-php-app:{commit}` | Laravel queue worker |
| `nexus-blue-php-scheduler` / `nexus-green-php-scheduler` | `nexus-php-app:{commit}` | Laravel scheduler |

### Fallback Containers (managed by `compose.prod.yml`)

| Container | Image | Dockerfile | Purpose |
|-----------|-------|-----------|---------|
| `nexus-php-app` | Built from `Dockerfile.prod` | PHP 8.2 + Apache | API backend |
| `nexus-react-prod` | `nexus-react-prod:latest` | `react-frontend/Dockerfile.prod` | React SPA (Nginx) |
| `nexus-php-db` | `mariadb:10.11` | — | Database |
| `nexus-php-redis` | `redis:7-alpine` | — | Cache + sessions |
| `nexus-sales-site` | Built from `sales-site/Dockerfile` | Nginx | Marketing site |

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
sudo bash scripts/safe-deploy.sh full --detach
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
sudo bash scripts/safe-deploy.sh full --detach
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

---

## Scheduled Jobs

In the zero-downtime deployment path, Laravel's scheduler runs as the active color's `nexus-{blue|green}-php-scheduler` container. The old host crontab entry that runs `php artisan schedule:run` inside `nexus-php-app` must be disabled once blue/green is enabled, otherwise the legacy container can run stale scheduled code.

Fallback `safe-deploy.sh` deployments may still use the old `nexus-php-app` scheduler arrangement until the server has fully moved to blue/green.

### Log Retention (`nexus:prune-logs`)

Runs **daily at 03:00** (container timezone) and chunk-deletes rows older than the retention window from unbounded logging tables. Deletions are performed 1,000 rows at a time to avoid long row-locks. Implemented by `app/Console/Commands/PruneLogs.php`.

| Table | Retention | Timestamp column |
|-------|-----------|------------------|
| `cron_logs` | 90 days | `executed_at` |
| `activity_log` | 180 days | `created_at` |
| `error_404_log` | 30 days | `last_seen_at` |
| `api_logs` | 30 days | `created_at` |
| `federation_api_logs` | 30 days | `created_at` |

To run manually (e.g. after a noisy backfill):

```bash
ssh -i "C:\ssh-keys\project-nexus.pem" -o RequestTTY=force azureuser@20.224.171.253 \
    "sudo docker exec nexus-php-app php artisan nexus:prune-logs"
```

To tune retention windows, edit the `RETENTION` array in `app/Console/Commands/PruneLogs.php` and redeploy.

---

## Post-Deploy Validation

Two automated guardrails run around every production deploy to catch regressions that only surface once real traffic hits the new containers.

### 1. Container Health Check (TD14)

**Script:** `scripts/check-container-health.sh`

**What it does:**
- Runs `docker stats` on all `nexus-*` containers and flags any using >90 % of its memory limit.
- Queries `docker events --since 1h` for `oom` and `die` events — an OOMKill in the last hour is an automatic FAIL.
- Runs `docker inspect` on each container to read the `OOMKilled` flag, `RestartCount`, and `RestartPolicy`.
- Exits non-zero if any container OOM'd or is above the threshold.

**Automatic invocation:** `bluegreen-deploy.sh` schedules this check to run 5 minutes after a successful deploy (in the background, so it does NOT delay the deploy finish). Results are appended to `/opt/nexus-php/logs/health-checks.log`; a detailed log is written to `/opt/nexus-php/logs/post-deploy-health-YYYYMMDD-HHMMSS.log`. A failure is additionally logged to syslog with tag `nexus-deploy`.

**Why 5 minutes?** OOMKills on the PHP container typically happen after Apache workers ramp up and start handling real traffic — a just-booted container consumes ~200 MB and won't trip the limit.

**Manual run — from your local workstation:**

```bash
bash scripts/check-container-health.sh
```

**Manual run — on the production server:**

```bash
ssh -i "C:\ssh-keys\project-nexus.pem" -o RequestTTY=force azureuser@20.224.171.253 \
    "sudo LOCAL_MODE=1 bash /opt/nexus-php/scripts/check-container-health.sh"
```

**Tunables (env vars):**

| Var | Default | Purpose |
|-----|---------|---------|
| `MEM_THRESHOLD_PCT` | `90` | Fail threshold (% of container's memory limit) |
| `OOM_LOOKBACK` | `1h` | docker events window for OOM/die events |
| `CONTAINER_FILTER` | `nexus-` | Prefix to restrict checks to our containers |

**Runbook — OOMKill detected:**

1. **Identify the container** from the health check output. In blue/green deploys this is usually a color-specific container such as `nexus-blue-php-app` or `nexus-green-php-app`.
2. **Inspect recent logs** for memory-hungry requests:
   ```bash
   sudo docker logs --tail 500 <container-from-output> | grep -iE 'memory|fatal|allowed memory size'
   ```
3. **Check current limits** in `compose.bluegreen.yml`:
   ```yaml
   services:
     app:
       mem_limit: ${NEXUS_APP_MEMORY_LIMIT:-1024m}
     queue:
       mem_limit: ${NEXUS_QUEUE_MEMORY_LIMIT:-512m}
   ```
4. **Raise the limit** (e.g. 1024m to 1280m), commit, redeploy:
   ```bash
   sudo bash scripts/deploy/bluegreen-deploy.sh deploy --detach
   ```
5. **Verify** with another health check 5 min into the deploy.

**Metrics baseline (PHP container, as of 2026-04-12):**

| Metric | Value |
|--------|-------|
| API container memory limit | 1024 MB (`NEXUS_APP_MEMORY_LIMIT`, overrideable) |
| Apache MPM workers (peak) | ~10 active, ~50 MB each → ~500 MB RSS peak |
| OPcache | 256 MB |
| `memory_limit` per PHP request | 1 GB |
| Idle container RSS | ~200 MB |

### 2. Artisan Cache Fail-Fast (TD15)

**Why it exists:** `Dockerfile.bluegreen` runs `php artisan optimize` before Apache starts. The active queue and scheduler containers also run `php artisan optimize` before their long-running processes. If optimization fails, the inactive color fails health checks and is never cut over to public traffic.

The TD15 guardrails catch these failures in CI and again inside the inactive color before public cutover.

**Scripts:**

| Script | Purpose |
|--------|---------|
| `scripts/test-artisan-cache.sh` | Runs the three cache commands against the local dev container, cleans up on success/failure |
| `scripts/validate-env.php` | Cross-references `.env.example` ↔ `config/*.php` `env()` calls to ensure all required keys are documented |
| `scripts/pre-push-checks.sh` | Bundles the above — run before `git push` |

**CI integration:** The GitHub Actions `php-checks` job runs both `validate-env.php --file=.env.ci` and the three `artisan *:cache` commands as **BLOCKING** steps. See `.github/workflows/ci.yml`.

**Pre-push integration:** Husky hooks are intentionally disabled on this project (see `MEMORY.md` → `feedback_husky_hooks.md`). `scripts/pre-push-checks.sh` is provided for manual use or CI wiring — **do not re-enable Husky without an explicit user instruction.**

**Manual run:**

```bash
# Validate env var coverage
php scripts/validate-env.php

# Exercise artisan cache (requires running dev container)
bash scripts/test-artisan-cache.sh

# Run both
bash scripts/pre-push-checks.sh
```

**Recovery — artisan cache fails during deploy:**

1. **Read the error** in `docker logs <candidate-container>`; it names the missing key or bad config file.
2. **Fix the root cause:**
   - Missing env var → add to `.env.production` on the server AND add to `.env.example` (empty placeholder) so future validations pass.
   - Bad default in `config/foo.php` → commit the fix, redeploy.
3. **Redeploy:** `sudo bash scripts/deploy/bluegreen-deploy.sh deploy --detach`
4. **Or, as an emergency fallback**, switch to the soft-fail entrypoint (below).

**Adding a new required env var — correct sequence:**

1. Add `NEW_KEY=` (empty placeholder) to `.env.example`.
2. Add `NEW_KEY=real_value` to `.env` locally and to `.env.production` on the server (`sudo vim /opt/nexus-php/.env`).
3. Reference in `config/foo.php` via `env('NEW_KEY')` (no second argument → required) or `env('NEW_KEY', 'sensible_default')` (optional).
4. Run `php scripts/validate-env.php` locally to confirm.
5. Commit + push + deploy.

### 3. Runtime Cache Fallback (fail-soft mode)

**File:** `docker/entrypoint-cache.sh`

This alternate entrypoint runs the three `artisan *:cache` commands at **container start** but **logs** and **continues** instead of failing. It is **NOT wired into `Dockerfile.bluegreen` by default**; it is kept as an emergency fallback if startup-time fail-fast caching proves too fragile in production.

**Tradeoff:**

| Mode | Pros | Cons |
|------|------|------|
| **Fail-fast (current)** | Catches config errors immediately; forces correctness | A single missing env var crash-loops the container → site stuck in maintenance mode |
| **Fail-soft (this script)** | Site stays up even with a broken config | Broken caches can go unnoticed; Laravel falls back to uncached env reads (slower, phantom bugs possible) |

**To switch to fail-soft mode** (only in emergencies, with explicit operator approval):

1. Edit `Dockerfile.bluegreen` and replace the `CMD ["sh", "-c", "..."]` block with:

   ```dockerfile
   COPY docker/entrypoint-cache.sh /usr/local/bin/entrypoint-cache.sh
   RUN chmod +x /usr/local/bin/entrypoint-cache.sh
   CMD ["/usr/local/bin/entrypoint-cache.sh"]
   ```

2. Rebuild + deploy: `sudo bash scripts/deploy/bluegreen-deploy.sh deploy --detach`.
3. Monitor `sudo docker logs -f <active-api-container> | grep -E 'WARN|artisan'` for cache warnings.
4. **Revert** to fail-fast mode once the underlying config issue is fixed.

### Gotchas

- **Windows docker CLI pipe issue:** `check-container-health.sh` runs via SSH (no local docker required) or with `LOCAL_MODE=1` on the server. Do NOT run it directly on Windows; the docker CLI often fails from Git Bash (see `MEMORY.md` for the `powershell.exe` workaround used by `test-artisan-cache.sh`).
- **`docker events` permissions:** Requires `sudo` on the Linux host. The script already prefixes `sudo docker events`. On Docker Desktop for Windows the `events` stream is available without sudo but OOM events are rarely emitted — rely on the production (Linux) host for this signal.
- **`docker stats` with a stopped container:** `--no-stream` returns blank rows for stopped containers; the script only flags containers that appear in `docker ps` output, so stopped containers are invisible to the memory check but will surface via the `OOMKilled=true` inspect check.
- **`validate-env.php` false positives:** Any `env('KEY')` call inside a string or comment will NOT be matched (the regex requires the `env(` token to be a real function call). Dynamic keys (`env($var)`) are skipped. If the validator flags a key that is truly optional, add a default in `config/foo.php`: `env('KEY', null)`.
