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
npm run check:next-public:dry-run
npm run check:next-public:inert
npm --prefix next-public-frontend run check
npm --prefix next-public-frontend run build
npm --prefix next-public-frontend run dev
```

The root dry-run command is the safest local pre-cutover bundle. It runs the
production-inertness guard, the isolated Next check, the backend/admin readiness
contract tests, the React typecheck, and the React production build. It stops on
the first failed check and reports no production routing effect.

The root inertness check verifies the shadow module is still safe to deploy
without changing production serving. It fails if the cutover env flag is enabled,
the inert Apache canary template is referenced by deploy/compose, the Next
service is no longer behind the `next-public-shadow` compose profile, or the
current prerender fallback is missing. The default Next check command runs
manifest validation, message validation, no-JavaScript HTML checks, TypeScript,
Vitest, and a production build.

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

## Apache Canary Template

The inert Apache/Plesk example route file lives at:

```text
scripts/deploy/apache/next-public-foundation-canary.conf.example
```

This file is documentation and future canary preparation only. It is not
included by `scripts/deploy/bluegreen-deploy.sh`, is not referenced by
`compose.bluegreen.yml`, and must not be copied into a live Plesk include
without a separate explicit cutover instruction.

The template currently contains exact-match foundation routes only. It avoids
dynamic detail routes, logged-in routes, create/edit flows, dashboards, admin
areas, and member workbench paths. Future Apache/Plesk work should keep this
config-only until route parity checks pass and an operator deliberately enables
a reviewed include.

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

It also audits the inert Apache canary template by expanding supported
`RewriteRule` patterns into concrete paths and comparing them with the shadow
route manifest. The audit reports the exact public paths in the template,
whether every template path is Next-owned public route coverage, whether any
private Vite route collides with the template, and whether unsupported rewrite
rules need manual review. This is read-only reporting and does not include the
template in Apache.

The readiness API and admin page also expose pre-cutover dry-run checks. These
checks list the exact local commands and route groups that still need proof
before a future canary: shadow manifest/no-JavaScript HTML verification,
platform legal content review, auth-only public contract review, private Vite
regression, and the inertness guard. They are instructions and evidence only;
they include no activation controls and have no production routing effect.

## Remaining Blockers

The shadow manifests currently classify 76 intended public routes. Of those,
69 are backed by public Laravel API content sources. The remaining routes are
blocked deliberately and must not be promoted by adding ad hoc public APIs:

- `platformTerms`, `platformPrivacy`, `platformDisclaimer`: require an
  authoritative platform legal content source and manual no-JavaScript shadow
  review before they can be considered API-backed.
- `marketplaceCollections`, `coupons`, `couponDetail`, `ideationIdeaDetail`:
  would expand currently auth-only content surfaces and require an explicit
  public visibility decision plus privacy review before any public API is
  added.

These blockers keep cutover eligibility false and activation unavailable. They
do not affect production routing or the current prerender serving path.

## Before Any Future Cutover

Do not enable public traffic until all of the following are true:

1. `npm run check:next-public:dry-run` passes.
2. `npm --prefix next-public-frontend run check` passes.
3. `npm run check:next-public:inert` passes.
4. `npm --prefix react-frontend run build` passes.
5. `cd react-frontend && npx tsc --noEmit` passes.
6. `vendor/bin/phpunit --no-coverage tests/Laravel/Unit/Services/NextPublicFrontendReadinessServiceTest.php tests/Laravel/Feature/Controllers/AdminNextPublicFrontendControllerTest.php` passes.
7. Public Next routes have parity checks for status codes, canonicals,
   metadata, tenant branding, AGPL attribution, and no-JavaScript HTML.
8. Private Vite routes have regression checks proving gated pages still load.
9. The Apache/Plesk canary template audit is clean: no private-route
   collisions, no unmatched template paths, and no unsupported rewrite rules.
10. Apache/Plesk canary routing is reviewed separately and explicitly enabled.
11. The existing prerender path remains available as fallback during canary.

Cutover is a separate operational decision and must be explicitly requested.
