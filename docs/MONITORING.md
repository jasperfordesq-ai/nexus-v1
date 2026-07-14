# Production Monitoring

Last reviewed: 2026-07-14

Project NEXUS uses external uptime checks plus application error tracking. Monitoring configuration should avoid committing private contact details, tokens, or webhook URLs.

## External Checks

Configure HTTPS monitors for:

| URL | Type | Expected result | Suggested interval |
| --- | --- | --- | --- |
| `https://api.project-nexus.ie/api/v2/health` | HTTP keyword | Health JSON reports OK | 5 min |
| `https://api.project-nexus.ie/health.php` | HTTP status | 200 when dependencies are healthy | 5 min |
| `https://app.project-nexus.ie/` | HTTP keyword | Project NEXUS shell loads | 5 min |
| `https://accessible.project-nexus.ie/` | HTTP keyword | Accessible frontend loads | 5 min |
| `https://project-nexus.ie/` | HTTP keyword | Sales site loads | 5 min |
| `https://app.project-nexus.ie/manifest.json` | HTTP status | 200 | 15 min |

Use at least two alert destinations for primary checks, such as an owner-controlled email address plus an incident channel. Keep private contact values in the monitoring provider, not in this repository.

## Cloudflare Health Checks

Cloudflare health checks are useful as a second vantage point:

1. Check `api.project-nexus.ie` at `/api/v2/health`, expecting HTTP 200.
2. Check `app.project-nexus.ie` at `/`, expecting HTTP 200 plus a stable body keyword.
3. Notify an owner-controlled alert destination on status changes.

## When An Alert Fires

1. Confirm the alert from a second source.
2. Check the API health endpoint and the pre-framework `health.php` endpoint.
3. Check the active blue/green color:

   ```bash
   sudo bash scripts/deploy/bluegreen-deploy.sh status
   ```

4. If the incident started immediately after a deploy, prefer rollback before deep debugging:

   ```bash
   sudo bash scripts/deploy/bluegreen-deploy.sh rollback --detach
   ```

5. If the platform is unusable and writes must pause, use `scripts/maintenance.sh` to toggle both maintenance layers.

## Related Docs

- [DEPLOYMENT.md](DEPLOYMENT.md)
- [RUNBOOK-INCIDENTS.md](RUNBOOK-INCIDENTS.md)
- [SENTRY.md](SENTRY.md)
- [SLO.md](SLO.md)
