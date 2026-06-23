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
| Live build | `curl -sI https://api.project-nexus.ie/ | grep -i x-build` | Current deployed commit |
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
