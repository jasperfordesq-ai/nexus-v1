<!--
Copyright © 2024–2026 Jasper Ford
SPDX-License-Identifier: AGPL-3.0-or-later
Author: Jasper Ford
See NOTICE file for attribution and acknowledgements.
-->

# Accessible Frontend

This is the Project NEXUS accessibility-first frontend. It is built as an HTML-first, progressively enhanced Laravel frontend using the official `govuk-frontend` package, without GOV.UK branding, crown, logotype, header identity, footer crest, or GDS Transport.

Recommended public subdomain: `accessible.project-nexus.ie`.

Deployment target: the Laravel/PHP blue-green app container, not the React frontend container.

## Structure

- `src/`: Sass and TypeScript entrypoints for the accessible frontend build.
- `views/`: Blade page templates loaded through Laravel's `accessible-frontend::` view namespace.
- Built assets are emitted to `httpdocs/build/accessible-frontend/`.

The public-facing accessible frontend is now Beta and served under `/{tenantSlug}/accessible/...`. Legacy `/{tenantSlug}/alpha/...` URLs permanently redirect (301 for GET, 308 otherwise). The internal names (`GovukAlpha` controllers, `govuk_alpha` translations, `govuk-alpha.*` route names) remain until a deliberate namespace migration is done.

The generic root path `/` renders a tenant chooser for shared hosts such as local development and `accessible.project-nexus.ie`. Tenant-scoped pages continue under `/{tenantSlug}/accessible/...`; tenants with a dedicated accessible custom domain serve the same routes slug-less at the bare root.

## Deployment Checks

Run these before deploying accessible frontend changes:

```bash
npm run build:accessible-frontend
npm run test:accessible-frontend:php
npm run test:accessible-frontend:a11y
```

Commit the generated files under `httpdocs/build/accessible-frontend/` with the source changes unless the deploy pipeline has been updated to build them inside the PHP image.

## Official Stack

- Component library: installed `govuk-frontend@6.1.0`; latest stable verified from npm on 2026-06-23 was `6.3.0`.
- Source repository: https://github.com/alphagov/govuk-frontend
- Design System source: https://github.com/alphagov/govuk-design-system
- Technical frontend docs: https://github.com/alphagov/govuk-frontend-docs

See `COMPONENTS.md` for the local inventory of the installed shared components we can use.
