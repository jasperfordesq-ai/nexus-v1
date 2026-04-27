# Agoris / KISS Caring Community Architecture

> Last updated: 2026-04-27

This note preserves the implementation and product architecture for the Agoris / KISS evaluation work. It should be read with `docs/ROADMAP.md`, especially the strategic partnership section.

## Positioning

NEXUS should treat the Agoris opportunity as a reusable Caring Community module cluster, not as an Agoris-specific fork. The module cluster combines timebank exchange, verified volunteering, trusted organisations, groups, events, resources, member onboarding, reporting, translation, and federation under one tenant-controlled feature profile.

The current feature key is `caring_community`. When it is disabled, public routes, dashboard cards, admin navigation entries, report affordances, workflow endpoints, and export types must disappear or return `FEATURE_DISABLED`.

## Tenant And Node Model

The Agoris research briefs describe regional nodes of roughly 15,000-30,000 citizens, with strong expectations around data sovereignty and local trust. In NEXUS terms:

| Agoris concept | NEXUS mapping | Current status |
|---|---|---|
| Regional node | Tenant, tenant domain, tenant feature/module config | Available |
| Canton / municipality / cooperative | Tenant hierarchy or federation relationship | Strong foundation; needs formal operating model |
| Isolated local node | Separate tenant with tenant-local tables and optional custom domain | Available at application layer |
| Shared regional network | Federation with opt-in discovery and controlled cross-tenant exchange | Available; needs Agoris policy mapping |
| Local data sovereignty | Tenant-scoped storage, exports, audit logs, and per-tenant configuration | Strong foundation |
| KISS national/foundation oversight | Role preset plus aggregate reporting pattern | Partial; needs cross-node reporting policy |

## Module Boundaries

The Caring Community cluster currently includes these switch-aware surfaces:

| Surface | Behavior |
|---|---|
| Public `/caring-community` hub | Feature-gated by `caring_community` |
| Community navigation | Hidden when the feature is disabled |
| Dashboard cards and quick actions | Hidden when the feature is disabled |
| Admin Caring Community config | Available only when enabled |
| KISS workflow console | Backend returns `FEATURE_DISABLED` when disabled |
| Municipal impact reports | Backend returns `FEATURE_DISABLED` when disabled |
| Municipal impact export type | Hidden from export-type list when disabled |
| Municipal impact PDF/CSV export | Returns `FEATURE_DISABLED` when disabled |

## Current Operational Model

The module now supports the KISS-style operating loop:

1. A member, family, organisation, or coordinator records a need or offer.
2. A coordinator matches people, organisations, or trusted volunteers.
3. Support time is logged.
4. Tenant workflow policy decides whether the log is approved immediately or sent to trusted review.
5. Pending reviews can be assigned to coordinators and escalated.
6. Accepted hours feed member statements and municipal/KISS evidence packs.

## Reporting Model

Municipal reporting is designed to answer procurement and public-value questions that the Agoris research highlighted as under-proven publicly:

| Evidence question | Current report signal |
|---|---|
| Is there measurable municipal value? | Verified hours, direct value, social value, total value |
| Is there real participation? | Active members, new members, participating members |
| Is there a partner network? | Trusted organisations and active opportunities |
| Is local exchange happening? | Support requests, support offers, categories, monthly trend |
| Is the evidence pack repeatable? | Saved report templates by audience and period |

Saved report templates support municipality, canton, cooperative, and foundation audiences. Exports carry the template assumptions into the municipal impact pack.

## Data Sovereignty Rules

For the Agoris/KISS path, the default rule is tenant-local first:

- Tenant-scoped tables remain the source of truth for members, offers, requests, hours, organisations, workflow policy, templates, and report exports.
- Cross-node discovery must be opt-in and should never expose sensitive support details by default.
- Municipal exports should be generated from the tenant or authorised aggregate node only.
- Any national/foundation aggregate reporting should use explicit sharing policy and aggregate metrics before personal data.
- Future isolated-node deployments should reuse the same module keys and migrations to avoid a forked product line.

## Next Build Priorities

1. Add admin toggle end-to-end coverage and API affordance checks for every Caring Community route.
2. Define a cross-node reporting policy for canton/foundation aggregate views.
3. Add assisted onboarding for older/nontechnical users, including coordinator-created profiles and printable invite flows.
4. Add native German glossary review for KISS, municipality, canton, cooperative, and care terms.
5. Decide whether partner-led hour logs may be valid without an organisation and adjust schema/policy accordingly.

## Cross-Node Aggregate Reporting Policy

Federation between NEXUS tenants (e.g. several KISS cooperatives in one canton, or a canton-level rollup of municipal nodes) needs an explicit policy so that the platform can produce useful canton-level numbers without leaking sensitive personal data. The policy below is the reference implementation for any future federation aggregate endpoint.

### What can be shared

The default whitelist of fields safe to expose across nodes:

- Aggregate counts: total approved hours bucketed by reporting period, total member count brackets (e.g. `<50`, `50-200`, `200-1000`, `>1000` rather than exact counts when the tenant is small enough that the exact count would be PII-equivalent).
- Anonymised category histograms: top N hour categories with their counts.
- Anonymised geographic histograms when location is collected at category or area level only.
- Partner-org count (number, not the names) by default; named partner orgs only when both nodes opt in.
- Opt-in member directories: tenants may explicitly publish opted-in members for federation discovery, with the member's individual consent recorded.

### What must NEVER be shared without explicit per-tenant federation consent

- Raw member identities (name, email, phone, address).
- Individual hour logs or timebank transactions.
- Support relationships (who supported whom, what they needed, when).
- Coordinator notes, review queues, or pending-review entries.
- Wallet balances per member.
- Any PII at all without explicit per-tenant federation consent recorded in the federation control tables.

### Federation aggregation model

Each tenant exposes a read-only `/federation/aggregates` endpoint that emits a signed JSON payload:

```json
{
  "tenant_id": 42,
  "period": { "from": "2026-01-01", "to": "2026-03-31" },
  "schema_version": 1,
  "aggregates": {
    "total_approved_hours": 1234.5,
    "member_count_bracket": "200-1000",
    "top_categories": [
      { "name": "Companionship", "count": 87 },
      { "name": "Transport", "count": 64 }
    ],
    "partner_org_count": 12,
    "supported_locales": ["de", "fr", "it"]
  },
  "signature": "ed25519:..."
}
```

Other federated tenants pull this on demand. The signature is verified against a public key exchanged out-of-band when the federation relationship is approved by both sides. Payloads are cached at the requester for at most 24 hours.

### Reporting at canton level

When a Kanton-level report is requested:

1. The system queries `/federation/aggregates` from each tenant in the canton's whitelist whose federation status is `approved` and whose `caring_community` feature is enabled.
2. It sums the aggregates and produces a canton-rolled-up version of the canton-variant Municipal Impact Report (the same `canton_variant` block, with `aggregate_municipalities_count` reflecting the real number of contributing nodes).
3. Per-tenant identities (the names of the contributing municipalities) are preserved in the rolled-up report only when ALL involved tenants have explicitly consented to identification in shared reports. Otherwise tenants are listed as anonymous contributors with a stable hash so the canton can verify counts but cannot single out a small node.

### Audit trail

Every cross-node aggregate query is logged with:

- Requester tenant ID and admin user ID
- Target tenant ID
- Timestamp
- The exact list of fields returned in the response
- Whether the response was cached or freshly fetched

Audit records are stored for at least 12 months and are queryable from the super-admin federation console.

### Opt-out

Any tenant may disable federation aggregate sharing entirely from tenant settings (`Caring Community > Federation > Share aggregates: off`). When disabled, `/federation/aggregates` returns `403 FEDERATION_DISABLED` and the tenant simply will not appear in canton-level rollups. The opt-out is immediate and does not require a deploy.

## Isolated-Node Deployment Option

Some Swiss cantons and KISS cooperatives have strict data-sovereignty requirements that prevent them using a hosted multi-tenant deployment, even one configured for tenant-local storage. The platform supports an "isolated node" deployment as a first-class option.

### Use case

A canton-government department or a single KISS cooperative with binding canton-level data-sovereignty rules wants its NEXUS deployment to run on infrastructure they directly control - typically Swiss-only hosting in a known data centre - with no shared compute or storage with the central NEXUS deployment. They still want to be able to federate (opt-in) with other NEXUS deployments to roll up canton-level numbers, but the underlying personal data must never leave their infrastructure.

### Architecture

The platform is already containerised: Docker Compose, MariaDB, Redis, the React frontend, the PHP/Laravel API, and the Meilisearch index all run in containers. Isolated nodes run the same containers on canton-managed hardware with their own:

- Database (MariaDB) - canton-controlled, canton-backed-up
- Redis cache
- React frontend container served from the canton domain
- File storage - either S3-compatible storage that the canton operates, or local disk on canton-managed VMs

Federation interop with the central NEXUS deployment (or with other isolated nodes) is opt-in via outbound HTTPS calls to the federation aggregate endpoints described above. The isolated node can both publish its own `/federation/aggregates` and consume aggregates from other nodes.

### What changes vs hosted tenants

| Concern | Hosted tenant | Isolated node |
|---|---|---|
| DNS | `app.project-nexus.ie` (or hosted custom domain) | Canton-controlled domain (e.g. `caring.zg.ch`) |
| Email | Gmail API via central NEXUS Google account | Canton SMTP server or canton's own Gmail/Microsoft 365 |
| Storage | Central NEXUS Azure-managed disk + S3 | Canton-controlled S3-compatible storage or local disk |
| Backups | Central NEXUS backup repo | Canton-managed backups, canton's own retention policy |
| Updates | Auto-deployed via central CI/CD | Pulled from a public release tag, applied on canton's schedule |
| Telemetry | Central NEXUS error reporting (Sentry, etc.) | Optional outbound-only error reporting; defaults OFF |
| Cloudflare proxy | Central NEXUS Cloudflare account | Canton's CDN / proxy of choice |

### What stays the same

- All source code is the same AGPL-3.0 open-source codebase from <https://github.com/jasperfordesq-ai/nexus-v1>.
- All features and admin tooling are identical.
- All migrations are identical.
- The `caring_community` feature flag and module gates work the same way.
- The federation aggregate endpoint speaks the same protocol regardless of where it is hosted.

### Federation between isolated and hosted nodes

Federation between an isolated canton node and the central hosted deployment (or between two isolated nodes) works the same way as hosted-to-hosted federation:

- Both sides expose `/federation/aggregates`.
- Both sides authenticate the federation channel using shared signing keys exchanged out-of-band when the federation relationship is approved.
- Both sides honour the per-tenant opt-out.

This means a canton can run an isolated node in canton-controlled infrastructure, and still consume canton-rollup data from federated cooperatives elsewhere (and vice versa) without any change to the federation protocol.

### Open questions

- **Licence audit**: any canton-specific modifications to the source must remain AGPL-3.0 compliant (Section 13 / network-use clause). A licence audit before deployment is recommended.
- **Support model**: if a canton wants commercial support, billing, and SLA backing for an isolated deployment, that needs a separate commercial agreement; AGPL alone does not provide it.
- **Certification**: if a Swiss FADP audit (or canton-specific equivalent) is required, the certification path needs to be agreed - the platform itself does not currently carry FADP certification.

## Deployment Modes Summary

| Mode | Hosting | DB | Updates | Federation |
|------|---------|----|---------|-----------|
| Hosted tenant on `app.project-nexus.ie` | Central NEXUS infra | Shared MariaDB | Auto-deploy | Opt-in shared aggregates |
| Hosted with custom domain | Central NEXUS infra | Shared MariaDB | Auto-deploy | Opt-in shared aggregates |
| Isolated node (canton-controlled) | Canton infra | Canton-managed | Manual via release tags | Opt-in via signed federation API |
