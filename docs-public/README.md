# Project NEXUS Public Collateral

Last reviewed: 2026-07-14

This directory contains public-facing collateral and public-safe operational artifacts for Project NEXUS. The maintained documentation starts in [../docs/README.md](../docs/README.md), and the source code remains authoritative for implementation details.

## Current Public Collateral

| Document | Purpose |
| --- | --- |
| [observability/prerender-runbook.md](observability/prerender-runbook.md) | Public-safe prerender observability runbook. |
| [observability/prerender-alerts.yml](observability/prerender-alerts.yml) | Public-safe alert rule example for prerender monitoring. |
| [observability/prerender-grafana-dashboard.json](observability/prerender-grafana-dashboard.json) | Public-safe Grafana dashboard JSON for prerender monitoring. |

## Federation

The maintained federation integration guide is [../docs/FEDERATION_API_MANUAL.md](../docs/FEDERATION_API_MANUAL.md) (plain-English overview plus the technical protocol/endpoint reference). The earlier `FEDERATION_INTEGRATION_SPECIFICATION.md` pointer has been removed in favour of that single source.

## Archived snapshots

The earlier per-module "engine report" snapshots (events, gamification, goals/impact, ideation, jobs, members directory, timebanking, volunteering — all dated 2026-03-29) and the open-source announcement email have been removed from the public repository. They were one-off, dated artifacts that had drifted from current behaviour. Curated, maintained module documentation now lives under [../docs/MODULES.md](../docs/MODULES.md) and `../docs/modules/`.

## Publication Rules

- Do not include secrets, credentials, live webhook URLs, private contacts, production IP addresses, or machine-local paths.
- Keep version labels aligned with `VERSION` and `CHANGELOG.md`.
- Run `npm run check:docs` and `npm run check:version` before publishing or committing collateral changes.
