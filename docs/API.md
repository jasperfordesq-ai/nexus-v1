# API Reference

Last reviewed: 2026-06-23

Project NEXUS exposes a large Laravel JSON API for the React frontend, mobile clients, integrations, federation, and admin surfaces. The hand-written docs do not duplicate every endpoint. The API contract is the source of truth.

## Source Of Truth

| Contract | Status | Notes |
| --- | --- | --- |
| `openapi.json` | Main API contract | OpenAPI 3.0.3, `Project NEXUS v2 API`, version `2.0.0`, 679 paths, 891 operations. |
| `resources/openapi.json` | Smaller resource contract | OpenAPI 3.1.0, 8 paths, 9 operations. |
| `resources/openapi.yaml` | YAML companion | Keep aligned with `resources/openapi.json` when that smaller contract changes. |
| `routes/api.php` | Runtime route source | Laravel API route registration for v2, admin, partner, federation, support, and regional analytics routes. |

## How To Use The API Docs

- Use `openapi.json` for generated reference, SDK generation, validation, and partner review.
- Use `routes/api.php` and `app/Http/Controllers/Api/` when checking runtime behavior.
- Use `docs/FEDERATION_API_MANUAL.md` for federation-specific semantics and operational notes.
- Use `docs/MODULES.md` to find the service, model, and frontend code for a module before editing endpoint behavior.

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
