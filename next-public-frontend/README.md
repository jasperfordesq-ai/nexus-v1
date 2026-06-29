# Project NEXUS Next Public Frontend

This app is the shadow-mode Next.js public frontend for Project NEXUS.
It is intentionally isolated from the live serving path: deploying this
repository must not make Apache/Plesk, the React SPA, or the prerender engine
serve public traffic from Next.js.

## Current Status

- Runtime mode: `shadow`
- Production routing: disabled
- Prerender fallback: retained
- Private/gated UI owner: `react-frontend/`
- Public data source: Laravel public APIs under `/api/v2/...`
- Database access from Next.js: forbidden

The route ownership manifest is documentation and verification input only. It
does not activate route cutover.

## Safe Local Commands

```bash
npm --prefix next-public-frontend run check
npm --prefix next-public-frontend run build
npm --prefix next-public-frontend run dev
```

The default check command runs manifest validation, message validation,
no-JavaScript HTML checks, TypeScript, Vitest, and a production build.

Backend/admin readiness checks:

```bash
vendor/bin/phpunit --no-coverage tests/Laravel/Unit/Services/NextPublicFrontendReadinessServiceTest.php tests/Laravel/Feature/Controllers/AdminNextPublicFrontendControllerTest.php
```

## Shadow Container

The blue/green compose file includes an opt-in profile:

```bash
docker compose -f compose.bluegreen.yml --profile next-public-shadow up next_public_frontend
```

The profile binds the Next app to `NEXUS_NEXT_PUBLIC_PORT` and is disabled by
default. Starting this profile must not be confused with production cutover; it
only starts the shadow service.

## Route Ownership

- `route-ownership.json` lists public routes intended for the Next frontend and
  private routes that must remain in the React/Vite app.
- `content-sources.json` lists public Laravel API endpoints that the shadow app
  may call while rendering API-backed public routes.
- `npm --prefix next-public-frontend run check:manifests` blocks unsafe drift,
  including duplicate route keys, private-route collisions, non-GET methods,
  non-`/v2/` endpoints, Next database access, and route/API parameter mismatch.

## Admin Readiness

The React admin page at `/admin/seo/next-public-frontend` calls the read-only
Laravel readiness API:

```text
GET /api/v2/admin/config/next-public-frontend
```

That API reports manifest status, shadow runtime commands, public/private route
ownership, content-source coverage, cutover blockers, retained prerender
fallback, and verification commands. It does not provide an activation switch.

## Before Any Future Cutover

Do not enable public traffic until all of the following are true:

1. `npm --prefix next-public-frontend run check` passes.
2. `npm --prefix react-frontend run build` passes.
3. `cd react-frontend && npx tsc --noEmit` passes.
4. `vendor/bin/phpunit --no-coverage tests/Laravel/Unit/Services/NextPublicFrontendReadinessServiceTest.php tests/Laravel/Feature/Controllers/AdminNextPublicFrontendControllerTest.php` passes.
5. Public Next routes have parity checks for status codes, canonicals,
   metadata, tenant branding, AGPL attribution, and no-JavaScript HTML.
6. Private Vite routes have regression checks proving gated pages still load.
7. Apache/Plesk canary routing is reviewed separately and explicitly enabled.
8. The existing prerender path remains available as fallback during canary.

Cutover is a separate operational decision and must be explicitly requested.
