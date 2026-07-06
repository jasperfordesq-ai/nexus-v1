# API Reference

Last reviewed: 2026-06-23

Project NEXUS exposes a large Laravel JSON API for the React frontend, mobile clients, integrations, federation, and admin surfaces. The hand-written docs do not duplicate every endpoint. **The OpenAPI contract is the source of truth.**

This page is a getting-started guide: base URLs, the auth model, and one worked request. For the full endpoint catalogue, see [Browse the full API](#browse-the-full-api).

## Source Of Truth

| Contract | Status | Notes |
| --- | --- | --- |
| `openapi.json` (repo root) | Main API contract | OpenAPI 3.0.3, `Project NEXUS v2 API`, version `2.0.0`. ~360 KB — render it (see below) rather than reading the raw file. |
| `resources/openapi.json` | Smaller resource contract | OpenAPI 3.1.0, a focused subset. |
| `resources/openapi.yaml` | YAML companion | Keep aligned with `resources/openapi.json` when that smaller contract changes. |
| `routes/api.php` | Runtime route source | Laravel API route registration for v2, admin, partner, federation, support, and regional analytics routes. |

When the docs and the running code disagree, `routes/api.php` and the controllers under `app/Http/Controllers/Api/` are authoritative for behaviour; `openapi.json` is authoritative for the published contract.

## Getting Started

### Base URLs

All routes are registered under an `/api` prefix (see `app/Providers/RouteServiceProvider.php`), and the current API surface lives under the `/api/v2/...` namespace.

| Environment | Base URL |
| --- | --- |
| Production | `https://api.project-nexus.ie` |
| Local Docker PHP | `http://127.0.0.1:8090` |

So a v2 endpoint is reached at, for example, `https://api.project-nexus.ie/api/v2/health`.

### Tenant context (required)

Project NEXUS is multi-tenant. Every request resolves a tenant before routing. The tenant is resolved from, in order of preference:

- an `X-Tenant-ID` request header, or
- an `X-Tenant-Slug` request header, or
- the request host (e.g. a tenant subdomain).

Requests that cannot resolve a tenant receive a `400` with an error code of `tenant_resolution_failed` or `tenant_required` (see `app/Http/Middleware/ResolveTenant.php`). When calling the API directly (not from a tenant subdomain), send an explicit tenant header.

### Authentication

The API uses **bearer-token authentication** (`Authorization: Bearer <token>`). The `Authenticate` middleware is hybrid — it accepts a current Laravel Sanctum token and falls back to legacy JWT tokens issued before the migration (see `app/Http/Middleware/Authenticate.php`). The OpenAPI `securitySchemes` declares a single `bearerAuth` scheme (`type: http`, `scheme: bearer`).

**Obtain a token** by calling the login endpoint with a tenant header:

```bash
curl -X POST "https://api.project-nexus.ie/api/v2/auth/login" \
  -H "Content-Type: application/json" \
  -H "X-Tenant-ID: 1" \
  -d '{"email": "you@example.com", "password": "your-password"}'
```

A successful response includes a `token` field (plus `access_token`, `refresh_token`, and `token_type: "Bearer"`) and the authenticated `user` object. The login route is rate limited to 30 requests/minute per IP and is additionally protected by a database-backed brute-force limiter on failed attempts.

**Send the token** on subsequent requests:

```bash
curl "https://api.project-nexus.ie/api/v2/<some-endpoint>" \
  -H "Authorization: Bearer <token>" \
  -H "X-Tenant-ID: 1"
```

> Do not embed real credentials in scripts, issues, or documentation. Fresh local installs seed the master tenant as `tenant_id=1`; use a throwaway account for examples.

### CSRF for cookie/session calls

The bearer-token flow above does not require a CSRF token. If you authenticate via the browser session cookie instead of an `Authorization` header, fetch a CSRF token first and send it back with state-changing (`POST`/`PUT`/`DELETE`) requests:

```bash
curl "https://api.project-nexus.ie/api/v2/csrf-token" -H "X-Tenant-ID: 1"
# → { "success": true, "data": { "csrf_token": "<token>" } }
```

Prefer the `Authorization: Bearer` header for programmatic/server-to-server integrations; it is simpler and is the model the published contract documents.

## A Worked Example

`GET /api/v2/health` is a public, unauthenticated readiness probe — ideal for confirming connectivity and your tenant header without any credentials.

**Request:**

```bash
curl "https://api.project-nexus.ie/api/v2/health" \
  -H "X-Tenant-ID: 1"
```

**Response (`200 OK`):**

```json
{ "status": "ok" }
```

There is also a framework-level probe at `GET /api/laravel/health` that deliberately returns only `{ "status": "ok" }` and never exposes the framework version or tenant id.

For a public read of real data, `GET /api/v2/listings` and `GET /api/v2/events` are registered without auth (they run with optional auth in the controller) and return tenant-scoped collections. Confirm the exact query parameters and response shapes in `openapi.json` rather than assuming them here.

## Rate Limiting

Routes apply Laravel throttle middleware, with per-route limits chosen for the endpoint's sensitivity. Common patterns seen in `routes/api.php`:

| Route class | Typical limit (requests/minute per IP) |
| --- | --- |
| Auth (login, register) | 30 |
| Config / federation reads | 60 |
| File uploads | 20 |
| Public feeds (jobs RSS/JSON) | 30 |
| Sensitive flows (e.g. sales order submit) | 5 |

Treat the table as indicative — the authoritative limit for any given route is the `throttle:` middleware on that route in `routes/api.php`.

## Browse the Full API

`openapi.json` at the repo root is the canonical, machine-readable contract. Do not hand-maintain a competing endpoint table here — generate reference, SDKs, and validation from the contract instead.

Render it locally with either tool:

```bash
# Redoc (interactive, single-file docs)
npx @redocly/cli preview-docs openapi.json

# or Swagger UI via any OpenAPI viewer
npx swagger-ui-watcher openapi.json
```

You can also import `openapi.json` into Postman, Insomnia, or an SDK generator (e.g. `openapi-generator`).

> Publishing a hosted Redoc page (e.g. on GitHub Pages) is a maintainer toggle, not a default. The committed `openapi.json` is always the source of truth; a hosted page is a convenience rendering of it.

## How To Use The API Docs

- Use `openapi.json` for generated reference, SDK generation, validation, and partner review.
- Use `routes/api.php` and `app/Http/Controllers/Api/` when checking runtime behaviour.
- Use [docs/FEDERATION_API_MANUAL.md](FEDERATION_API_MANUAL.md) for federation-specific semantics and operational notes.
- Use [docs/MODULES.md](MODULES.md) to find the service, model, and frontend code for a module before editing endpoint behaviour.

## Documentation Standard

API documentation should follow the Stripe-style pattern:

- a short "just getting started" path for the common integration;
- authentication and tenant-context guidance before endpoint details;
- sandbox or test-tenant guidance that does not expose live credentials;
- versioning notes and deprecation policy;
- clear examples that avoid real personal data;
- generated endpoint reference from OpenAPI rather than hand-maintained endpoint tables.

## Validation

Before publishing API documentation changes:

```bash
npm run check:docs
```

Before changing version labels or public collateral:

```bash
npm run check:version
```

If a future change adds OpenAPI validation tooling, wire it into `npm run check:docs` instead of maintaining a separate manual checklist.
