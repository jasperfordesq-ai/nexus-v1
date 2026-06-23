# Documentation Architecture

Last reviewed: 2026-06-23

This page defines how Project NEXUS documentation is organised and kept trustworthy. It is a maintainer guide, not a dump of audit notes.

## Standards

Project NEXUS documentation follows these external standards:

- [Diataxis](https://diataxis.fr/): separate tutorials, how-to guides, reference, and explanation so readers know what kind of help they are reading.
- [Google developer documentation style](https://developers.google.com/style): write clear, direct, globally understandable technical English.
- [GitLab documentation style](https://docs.gitlab.com/development/documentation/styleguide/): keep documentation as the single source of truth for supported workflows.
- [Write the Docs docs-as-code](https://www.writethedocs.org/guide/docs-as-code/): keep docs in version control, reviewed and checked with code.
- [OpenAPI](https://spec.openapis.org/oas/v3.2.0.html): API reference starts from the machine-readable API contract.
- [WCAG 2.2](https://www.w3.org/TR/WCAG22/): docs and examples should be readable, navigable, and accessible.
- [Stripe API docs](https://docs.stripe.com/api): API docs should give a quick start path, sandbox guidance, examples, versioning notes, and predictable endpoint reference.

## Information Architecture

| Location | Purpose | Diataxis role |
| --- | --- | --- |
| `README.md` | Public entry point, setup overview, licence and attribution summary. | Tutorial / explanation |
| `docs/` | Maintained public maintainer, developer, operator, architecture, API, testing, security, and governance docs. | How-to / reference / explanation |
| `docs-public/` | Public collateral, announcements, and dated engine-report snapshots. | Explanation / dated reference |
| `openapi.json` | Canonical generated API contract for the large v2 API surface. | Reference |
| `resources/openapi.*` | Smaller resource contract used by tooling or runtime surfaces. | Reference |
| `mobile/docs/` | Mobile release, native UI, and security guidance scoped to the Expo app. | How-to / reference |
| `accessible-frontend/` | HTML-first accessible frontend implementation notes and shared component inventory. | How-to / reference |
| `e2e/` | Playwright runbook and route-test reference notes. | How-to / dated reference |
| `.local-docs-archive/` | Private local scratch, prompts, reports, and handoffs. Gitignored. | Not public docs |

## Public Doc Rules

- Keep public docs concise, current, and safe for a public AGPL repository.
- Do not publish secrets, live credentials, private contact details, production IP addresses, raw prompt logs, generated audit dumps, or machine-local paths.
- Prefer current code paths: `app/`, `routes/api.php`, `database/migrations/`, `react-frontend/`, and `accessible-frontend/`.
- Link every maintained `docs/` page from `docs/README.md`.
- Mark dated snapshots clearly and keep them out of the maintained-reference path.
- Use neutral global examples, not Ireland-only assumptions.
- Treat `CHANGELOG.md`, `VERSION`, `NOTICE`, `CONTRIBUTOR_TERMS.md`, and `CONTRIBUTING.md` as source-of-truth documents.

## Inventory Classes

| Class | Examples | Handling |
| --- | --- | --- |
| Public maintained documentation | `docs/ARCHITECTURE.md`, `docs/API.md`, `docs/DEPLOYMENT.md` | Keep indexed and checked. |
| Private/local-only documentation | `BACKUP.md`, `.local-docs-archive/`, ignored root strategy notes | Do not link from public docs. |
| Generated artifact | raw static-analysis output, Playwright reports, coverage reports | Keep out of maintained docs. |
| Dated snapshot | `docs-public/*_ENGINE_REPORT.md`, route migration snapshots | Label as historical and avoid treating as live reference. |
| Archive candidate | completed implementation plans, stale prompt outputs | Remove from tracked public repo or move to local archive. |
| Delete candidate | temporary paste buffers, generated text dumps | Delete when no tracked reference depends on them. |
| Source-of-truth reference | `openapi.json`, `routes/api.php`, `database/schema/mysql-schema.sql` | Do not paraphrase into competing hand-written reference. |

## Maintenance Workflow

1. Start at `docs/README.md` to find the maintained doc.
2. If the maintained doc is wrong, fix it in the same change as the code.
3. If a raw artifact is useful only for one task, place it under `.local-docs-archive/`.
4. If API behavior changes, update or regenerate `openapi.json` and validate it.
5. If the change is release-relevant, update `CHANGELOG.md` and refresh the app copy.
6. Run `npm run check:docs`, `npm run check:version`, and `npm run check:changelog` before finishing.

## Current Gaps

The public docs now have the right shape. The remaining work is depth: write module guides only when a module is actively changed, starting with wallet/exchanges, notifications, search, federation operations, and mobile packaging.
