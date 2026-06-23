# Incident Response Runbook

This runbook covers first response for Project NEXUS production incidents. It assumes Apache plus Docker blue/green deployment on the production host.

## First Five Minutes

1. Confirm scope: one tenant, one feature, or the whole platform.
2. Check external monitoring and Sentry before guessing.
3. If the issue began after a deploy, roll back first and investigate after traffic is safe.
4. If writes must stop, enable global maintenance mode with `scripts/maintenance.sh`.
5. Record what changed, what users saw, and what evidence was checked.

## Health Checks

| Check | Command | Healthy result |
| --- | --- | --- |
| Pre-framework health | `curl -sS https://api.project-nexus.ie/health.php` | HTTP 200 and healthy JSON |
| Laravel health | `curl -sS https://api.project-nexus.ie/v2/health` | HTTP 200 |
| Live build | `curl -sI https://api.project-nexus.ie/ \| grep -i x-build` | Current deployed commit |
| Active color | `sudo bash scripts/deploy/bluegreen-deploy.sh status` | Active color and last deploy state |

When running from a development machine, load private SSH details from `.secrets.local/deploy.env` and run the same server-side commands over SSH.

## Rollback

Rollback is the preferred response when a deploy caused the incident:

```bash
sudo bash scripts/deploy/bluegreen-deploy.sh rollback --detach
sudo bash scripts/deploy/bluegreen-deploy.sh logs -f
```

Rollback switches Apache routing back to the previous color after health checks. It should not require a maintenance window.

## Maintenance Mode

Use maintenance mode only when the platform must stop accepting non-local traffic or writes:

```bash
sudo bash scripts/maintenance.sh on
sudo bash scripts/maintenance.sh status
sudo bash scripts/maintenance.sh off
```

The script toggles both the pre-framework file gate and the database maintenance flag. Do not toggle those layers separately.

## Common Failure Modes

### Backend health fails but the React shell loads

Check the active PHP app logs and recent deploy logs. A backend-only failure is often a config, migration, queue, or dependency issue.

### Both frontend and API fail

Check Apache routing, the active color, container health, and whether the route file points at the expected upstream ports.

### Email delivery appears down

Check the application email log and provider activity before changing mailer code. A delivered provider response with poor inbox placement is a different incident from a send failure.

### Queues are stuck

Check the active color queue and scheduler containers, then Horizon status. Restart the queue container only after confirming the active color.

### Database or Redis is degraded

`health.php` should identify which dependency failed. Restarting PHP can briefly make the first requests slow while OPcache warms.

### Backup or restore incident

Pause writes first if data integrity is at risk. Take a fresh dump of the damaged state for forensics, restore into a throwaway target before touching live data, and run migrations after restore if the backup predates the current schema.

## After The Incident

Write a short note with:

- what broke;
- how it was detected;
- how it was resolved;
- what test, monitor, or runbook change would catch it earlier next time.

If detection was slow, update [SLO.md](SLO.md) or [MONITORING.md](MONITORING.md).

---

## Post-Mortem Template

Use this template for any incident that lasted more than a few minutes, required maintenance mode, triggered a rollback, or resulted in data risk. Post-mortems are blameless — the goal is systemic improvement, not fault attribution.

Copy the block below into a new file under `.local-docs-archive/postmortems/YYYY-MM-DD-short-title.md` (gitignored). If the incident is public-safe and relevant to contributors, a summary may be linked from `CHANGELOG.md`.

```markdown
# Post-Mortem: <short title>

**Date:** YYYY-MM-DD  
**Author(s):** <names or handles>  
**Severity:** P1 / P2 / P3  
**Status:** Draft | Under review | Closed

---

## Summary

One or two sentences: what broke, and why it matters.

## Impact

| Dimension | Detail |
|-----------|--------|
| Who was affected | All tenants / tenant `<slug>` / admin users only |
| Approximate user count | e.g. ~200 active sessions |
| Duration | HH:MM (detection → resolution) |
| Features degraded | e.g. API 500s on /v2/listings, queue backlog |
| Data at risk | Yes / No — describe if yes |

## Timeline (all times UTC)

| Time | Event |
|------|-------|
| HH:MM | First signal (monitor / user report / Sentry alert) |
| HH:MM | Incident declared, responder on-call |
| HH:MM | Root cause identified |
| HH:MM | Mitigation applied (rollback / maintenance mode / hotfix deploy) |
| HH:MM | Service restored, users unblocked |
| HH:MM | Post-mortem opened |

## Root Cause

Describe the technical root cause concisely. Reference the relevant file, service, or config by repo-relative path (e.g. `compose.bluegreen.yml`, `app/Services/WalletService.php`).

## Resolution

What was done to stop the bleeding and restore service:

1. Step one (e.g. rolled back with `bluegreen-deploy.sh rollback --detach`)
2. Step two (e.g. applied hotfix commit `abc1234`, deployed)
3. Step three (e.g. cleared OPcache with `docker exec nexus-<color>-php-app php artisan optimize:clear`)

## What Went Well

- Detection time was acceptable.
- Rollback restored service without a maintenance window.
- _(add or remove lines)_

## What to Improve / Action Items

| Action | Owner | Due |
|--------|-------|-----|
| Add alarm for X | @handle | YYYY-MM-DD |
| Add regression test covering Y | @handle | YYYY-MM-DD |
| Update runbook section Z | @handle | YYYY-MM-DD |

## Links

- CHANGELOG.md entry: [Unreleased] or `[vX.Y.Z]` — paste the relevant bullet here
- Sentry issue(s): (link or issue ID)
- New or updated SLO/alarm: link to `docs/SLO.md` section or `docs/MONITORING.md` entry
```

### Worked Example (Bootstrap Cache Cross-Color Volume Incident)

The following illustrates how to fill the template. Details are kept generic and public-safe.

**Incident class:** Shared `bootstrap/cache` Docker volume between blue and green containers.

During a blue-green deploy the incoming color's queue container ran `artisan optimize`, which regenerated and then deleted `bootstrap/cache/events.php`. The live color's scheduler was still booting child artisan processes that expected that file. Result: ~2,000 `Failed to open stream: bootstrap/cache/events.php` errors in Sentry. No user-visible data loss; the issue was CLI-process only (Sentry `users=0`).

| Template field | What to write |
|----------------|---------------|
| **Summary** | Queue container `nexus-<inactive>-php-queue` deleted shared bootstrap cache files during `artisan optimize`, crashing the live scheduler's booting artisan children. |
| **Impact** | No authenticated users affected; background job processing degraded for ~N minutes. |
| **Root cause** | `compose.bluegreen.yml` declared a single shared `nexus-php-bootstrap-cache` volume mounted into both colors' queue and scheduler services. One color's `optimize` invalidated the other color's already-running bootstrap. |
| **Resolution** | De-shared the volume so each color owns its own bootstrap cache. Regression test added. Deployed hotfix. |
| **Action item** | Update `compose.bluegreen.yml` so bootstrap/cache volumes are color-scoped; add a CI test that boots both colors simultaneously and asserts no cross-color file deletion. |
| **CHANGELOG link** | Reference the relevant `[Unreleased]` bullet or fix commit in `CHANGELOG.md`. |
