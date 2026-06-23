# Project NEXUS Federation Integration

This public note replaces the older January 2026 partner specification that referenced retired PHP namespaces, legacy view templates, and non-public recipient language.

Project NEXUS federation is documented in the maintained public docs:

- [../docs/FEDERATION_API_MANUAL.md](../docs/FEDERATION_API_MANUAL.md) - plain-English and technical integration guide.
- [../docs/FEDERATION_COVERAGE.md](../docs/FEDERATION_COVERAGE.md) - dated test coverage snapshot by protocol and entity.

## Current Integration Surface

Federation endpoints are registered in `routes/api.php` and implemented in Laravel under `app/`.

Supported integration paths include:

- native Project NEXUS federation endpoints under `/api/v1/federation`;
- external partner webhook intake under `/api/v2/federation/external/webhooks/receive`;
- Komunitin-compatible endpoints under `/api/v2/federation/komunitin/...`;
- Credit Commons-compatible endpoints under `/api/v2/federation/cc/...`;
- outbound partner calls through `App\Services\FederationExternalApiClient`;
- tenant/system gates through `App\Services\FederationFeatureService`.

## Authentication

The federation layer supports API-key, HMAC-signed, JWT/OAuth-style, and protocol-specific partner authentication paths. Do not publish partner secrets, signing keys, or live webhook secrets in repository documentation.

## Operational Posture

Federation is opt-in and gated at multiple layers:

- global platform controls;
- tenant whitelisting;
- tenant feature toggles;
- per-partner configuration;
- member-level opt-in where user data leaves the local tenant context.

Treat any dated implementation report, handoff, or generated partner pack as historical only unless it is linked from [../docs/README.md](../docs/README.md) or this file.
