# Project NEXUS Public Collateral

This directory contains public-facing collateral and dated technical snapshots for Project NEXUS. The maintained documentation starts in [../docs/README.md](../docs/README.md), and the source code remains authoritative for implementation details.

Use these files for publishing, outreach, and high-level technical orientation. Do not treat dated engine reports as live API references.

## Current Public Collateral

| Document | Purpose |
| --- | --- |
| [OPEN_SOURCE_ANNOUNCEMENT_EMAIL.md](OPEN_SOURCE_ANNOUNCEMENT_EMAIL.md) | Plain-text open-source announcement copy. |
| [OPEN_SOURCE_ANNOUNCEMENT_EMAIL.html](OPEN_SOURCE_ANNOUNCEMENT_EMAIL.html) | HTML version of the open-source announcement. |
| [FEDERATION_INTEGRATION_SPECIFICATION.md](FEDERATION_INTEGRATION_SPECIFICATION.md) | Current pointer to maintained federation integration docs. |
| [observability/prerender-runbook.md](observability/prerender-runbook.md) | Public-safe prerender observability runbook. |

## Dated Engine Snapshots

These reports are useful technical snapshots, but they are not the live source of truth:

| Document | Snapshot |
| --- | --- |
| [EVENTS_MODULE_ENGINE_REPORT.md](EVENTS_MODULE_ENGINE_REPORT.md) | Events module implementation snapshot. |
| [GAMIFICATION_ENGINE_REPORT.md](GAMIFICATION_ENGINE_REPORT.md) | Gamification engine implementation snapshot. |
| [GOALS_AND_IMPACT_ENGINE_REPORT.md](GOALS_AND_IMPACT_ENGINE_REPORT.md) | Goals and impact implementation snapshot. |
| [IDEATION_CHALLENGES_ENGINE_REPORT.md](IDEATION_CHALLENGES_ENGINE_REPORT.md) | Ideation and challenges implementation snapshot. |
| [JOBS_MODULE_ENGINE_REPORT.md](JOBS_MODULE_ENGINE_REPORT.md) | Jobs module implementation snapshot. |
| [MEMBERS_DIRECTORY_ENGINE_REPORT.md](MEMBERS_DIRECTORY_ENGINE_REPORT.md) | Members directory implementation snapshot. |
| [TIMEBANKING_ENGINE_REPORT.md](TIMEBANKING_ENGINE_REPORT.md) | Timebanking engine implementation snapshot. |
| [VOLUNTEERING_ENGINE_REPORT.md](VOLUNTEERING_ENGINE_REPORT.md) | Volunteering engine implementation snapshot. |

## Publication Rules

- Do not include secrets, credentials, live webhook URLs, private contacts, production IP addresses, or machine-local paths.
- Keep version labels aligned with `VERSION` and `CHANGELOG.md`.
- Run `npm run check:docs` and `npm run check:version` before publishing or committing collateral changes.
