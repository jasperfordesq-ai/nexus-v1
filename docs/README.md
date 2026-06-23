# Project NEXUS Documentation

This directory contains the public, maintained documentation for Project NEXUS.

Historical prompts, one-off audits, dated handoff notes, generated reports, PDF exports, and stale planning documents were moved out of `docs/` during the 2026-06-23 cleanup. They remain available locally under `.local-docs-archive/2026-06-23-docs-cleanup/`, which is intentionally ignored by git.

## Operations

| Document | Purpose |
| --- | --- |
| [DEPLOYMENT.md](DEPLOYMENT.md) | Production deployment workflow and blue/green commands. |
| [RUNBOOK-INCIDENTS.md](RUNBOOK-INCIDENTS.md) | First-response runbook for production incidents. |
| [MONITORING.md](MONITORING.md) | External uptime checks, alert channels, and response notes. |
| [SLO.md](SLO.md) | Initial service-level objectives and wired alerting commands. |
| [SENTRY.md](SENTRY.md) | Backend and frontend Sentry configuration. |

## Platform Features

| Document | Purpose |
| --- | --- |
| [CUSTOM-DOMAINS.md](CUSTOM-DOMAINS.md) | Tenant custom-domain setup for the React and accessible frontends. |
| [FEDERATION_API_MANUAL.md](FEDERATION_API_MANUAL.md) | Plain-English and technical federation API guide. |
| [FEDERATION_COVERAGE.md](FEDERATION_COVERAGE.md) | Dated federation test coverage snapshot. |
| [govuk-alpha/RESEARCH.md](govuk-alpha/RESEARCH.md) | Accessible frontend architecture and GOV.UK Frontend constraints. |
| [govuk-alpha/ATTRIBUTION.md](govuk-alpha/ATTRIBUTION.md) | GOV.UK-related attribution notes. |

## Governance

| Document | Purpose |
| --- | --- |
| [CONTRIBUTOR_TERMS_ENFORCEMENT.md](CONTRIBUTOR_TERMS_ENFORCEMENT.md) | How PR gates enforce contributor terms and ownership acknowledgements. |

## Publication Standards

Before adding a document here:

- keep it useful to a future maintainer, not just useful to one finished task;
- remove secrets, live credentials, private contact details, machine-local paths, and personal notes;
- avoid publishing raw prompts, handoffs, scratch plans, generated audit dumps, and exported PDFs;
- mark dated verification records clearly with the date and command used;
- prefer current code paths such as `app/`, `database/migrations/`, `routes/api.php`, and `react-frontend/`;
- move local-only material to `.local-docs-archive/` instead of committing it.
