# Caring Community Architecture (Agoris / KISS reference)

> Last updated: 2026-05-01 — refreshed after pilot-evaluation batch, website-completeness demo layer, pilot-operations dashboards, and May 1 hardening pass shipped (AG78/AG80-AG97).

This note preserves the implementation and product architecture for the Agoris / KISS evaluation work. It should be read with `docs/ROADMAP.md`, especially the strategic partnership section.

## Positioning

NEXUS should treat the Agoris opportunity as a reusable Caring Community module cluster, not as an Agoris-specific fork. The module cluster combines timebank exchange, verified volunteering, trusted organisations, groups, events, resources, member onboarding, reporting, translation, and federation under one tenant-controlled feature profile.

The current feature key is `caring_community`. When it is disabled, public routes, dashboard cards, admin navigation entries, report affordances, workflow endpoints, and export types must disappear or return `FEATURE_DISABLED`.

## Tenant And Node Model

The Agoris research briefs describe regional nodes of roughly 15,000-30,000 citizens, with strong expectations around data sovereignty and local trust. In NEXUS terms:

| Agoris concept | NEXUS mapping | Current status |
|---|---|---|
| Regional node | Tenant, tenant domain, tenant feature/module config | Available |
| Canton / municipality / cooperative | Tenant hierarchy or federation relationship | Strong foundation; formal operating model still needed |
| Isolated local node | Separate tenant with tenant-local tables and optional custom domain | Available at application layer |
| Shared regional network | Federation with opt-in discovery, Verein-to-Verein sharing, cross-invite target discovery, and controlled cross-tenant exchange | Available; needs Agoris policy mapping |
| Local data sovereignty | Tenant-scoped storage, exports, audit logs, and per-tenant configuration | Strong foundation |
| KISS national/foundation oversight | Role preset plus aggregate reporting pattern | Strong foundation; cross-node reporting policy documented below |

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
| Research partnerships admin | Available only when `caring_community` is enabled |
| Research consent / aggregate export APIs | Return `FEATURE_DISABLED` when disabled |
| Tenant-branded native app config | Tenant-scoped system setting, with build-manifest export for later mobile build pipeline |
| AG80 FADP/nDSG pilot disclosure pack admin | Available only when `caring_community` is enabled; editable JSON envelope under `caring.disclosure_pack`; Markdown export endpoint |
| AG81 KISS operating-policy tenant settings | 11 discrete settings under `caring.operating_policy.*`; admin form with schema-driven validation |
| AG82 commercial boundary map | 31-capability matrix under `CommercialBoundaryService`; per-tenant overrides under `caring.commercial_boundary`; classifies AGPL public / tenant config / private deployment / commercial |
| AG83 pilot success scoreboard | 10-metric 90-day rolling window using only existing tenant data; baselines persisted to `caring_kpi_baselines` with `pilot_scoreboard` envelope; pre-pilot label + quarterly review cadence |
| AG84 pilot data-quality dashboard | 10 read-only checks; 6 with row-level drill-down; surfaces seed-marker users, duplicate emails/phones, missing language, organisation verification gaps |
| AG85 isolated-node decision gate | 11 ownership decisions under `caring.isolated_node.*`; gate `closed=true` only when every item has status `decided` |
| AG87 external integration backlog | Single JSON envelope under `caring.external_integrations`; tracks partner-dependent integrations (banking, payment, AHV, Spitex, municipal master-data, postal); seed defaults available |

## Current Operational Model

The module now supports the KISS-style operating loop:

1. A member, family, organisation, or coordinator records a need or offer.
2. A coordinator matches people, organisations, or trusted volunteers.
3. Support time is logged.
4. Tenant workflow policy decides whether the log is approved immediately or sent to trusted review.
5. Pending reviews can be assigned to coordinators and escalated.
6. Accepted hours feed member statements and municipal/KISS evidence packs.
7. Optional research partners can receive suppressed aggregate datasets only where the tenant has configured a research partner and members have an explicit consent state.

## Reporting Model

Municipal reporting is designed to answer procurement and public-value questions that the Agoris research highlighted as under-proven publicly:

| Evidence question | Current report signal |
|---|---|
| Is there measurable municipal value? | Verified hours, direct value, social value, total value |
| Is there real participation? | Active members, new members, participating members |
| Is there a partner network? | Trusted organisations and active opportunities |
| Is local exchange happening? | Support requests, support offers, categories, monthly trend |
| Is the evidence pack repeatable? | Saved report templates by audience and period |
| Can external research be governed? | Research partner registry, member consent state, anonymised aggregate dataset export, export audit and revocation |

Saved report templates support municipality, canton, cooperative, and foundation audiences. Exports carry the template assumptions into the municipal impact pack.

## Data Sovereignty Rules

For the Agoris/KISS path, the default rule is tenant-local first:

- Tenant-scoped tables remain the source of truth for members, offers, requests, hours, organisations, workflow policy, templates, and report exports.
- Cross-node discovery must be opt-in and should never expose sensitive support details by default.
- Municipal exports should be generated from the tenant or authorised aggregate node only.
- Any national/foundation aggregate reporting should use explicit sharing policy and aggregate metrics before personal data.
- Future isolated-node deployments should reuse the same module keys, migrations, and build-manifest contracts to avoid a forked product line.

## Current Follow-Up Priorities

The original build priorities in this note have mostly moved from implementation gaps to validation and governance questions. With the 2026-04-30 pilot-evaluation batch (AG78/AG80-AG88), the website-completeness layer (AG89-AG94), the pilot-operations dashboards (AG95-AG97), and the May 1 hardening pass, the platform now has admin surfaces for every governance, demonstration, and pilot-readiness question that was previously hand-waved. The near-term priorities are now:

1. Run the AG78 guided walkthrough (`docs/CARING_COMMUNITY_PILOT_EVALUATION.md`) with prospective pilot stakeholders to produce the AG88 decision memo.
2. Commission the AG79 Swiss German/French/Italian terminology review — only remaining AG78–AG88 item not yet shipped, requires a native speaker rather than a code change.
3. Capture pre-pilot baselines (AG83) on every prospective tenant before resident onboarding begins.
4. Run AG84 data-quality checks before any tenant transitions from demo seed to real residents.
5. Resolve AG87 external integration ownership before building any feature that depends on a partner integration that is not yet in `live` status.
6. Close the AG85 isolated-node gate before any canton with strict data-sovereignty rules goes live.

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

## Research Partnership Governance

The AG65 research layer is now implemented as a tenant-scoped governance surface rather than an informal export process.

| Concern | Current implementation |
|---|---|
| Research partner record | `caring_research_partners`, including institution, contact email, agreement reference, methodology URL, status, and data scope |
| Member consent | Member endpoint stores `opted_in`, `opted_out`, or `revoked` state for research participation |
| Dataset generation | Admin endpoint generates anonymised aggregate data for an active partner and reporting period |
| Suppression | Aggregate export applies suppression thresholds before release |
| Auditability | Every export is recorded in `caring_research_dataset_exports` with hash, row count, status, period, metadata, and partner |
| Revocation | Admins can revoke an export; revocation preserves the row and stamps `revoked_by` / `revoked_at` metadata |
| Admin UI | `/admin/caring-community/research` supports partner creation, export generation, export history, and revocation |

This is sufficient for a pilot-level "wissenschaftlich begleitet" claim, provided the actual research agreement, methodology, and legal review are supplied by the partner institution. It is not a substitute for a signed DPA, ethics review, or canton-specific legal assessment.

## Tenant-Branded Native App Handoff

AG72 is a readiness and handoff layer, not a completed white-label mobile release pipeline.

The admin UI at `/admin/native-app` stores the real `native_app_*` tenant settings used by the backend:

- Store mode: shared NEXUS app or tenant-branded app.
- iOS identity: bundle ID and App Store ID.
- Android identity: package name and Play Store ID.
- Store metadata: marketing, privacy, and support URLs.
- Push routing: sender ID and tenant channel prefix.
- PWA configuration: service worker, install prompt, display mode, orientation, theme/background colour.

The build handoff endpoint `/v2/admin/config/native-app/build-manifest` returns a JSON manifest containing tenant, app, store, push, PWA, and readiness data. A later signed iOS/Android build pipeline can consume that manifest without needing to scrape tenant settings directly.

What is not yet built:

- Separate signed App Store / Play Store binaries per tenant.
- CI/CD automation for tenant-branded mobile builds.
- Apple/Google developer-account ownership workflow.
- Store submission, screenshots, review responses, and release management.

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
- **Support model**: if a canton or AGORIS wants commercial support, billing, private deployment operations, SLA backing, or a separately licensed copy, that needs a separate commercial agreement; AGPL alone does not provide it.
- **Certification**: the platform has an FADP/nDSG disclosure-pack foundation, but if a Swiss FADP audit (or canton-specific equivalent) is required, the certification path still needs to be agreed - the platform itself does not currently carry formal FADP certification.
- **Commercial independence**: AGORIS-specific brand, UX, advertising, monetisation, or proprietary product layers should be explicitly classified before implementation so the parties know whether they belong in public AGPL NEXUS, a private deployment, or a separate commercial layer.

## Deployment Modes Summary

| Mode | Hosting | DB | Updates | Federation |
|------|---------|----|---------|-----------|
| Hosted tenant on `app.project-nexus.ie` | Central NEXUS infra | Shared MariaDB | Auto-deploy | Opt-in shared aggregates |
| Hosted with custom domain | Central NEXUS infra | Shared MariaDB | Auto-deploy | Opt-in shared aggregates |
| Isolated node (canton-controlled) | Canton infra | Canton-managed | Manual via release tags | Opt-in via signed federation API |

## Website Completeness Extension Layer

The 2026-04-30 live scrape of `agoris.ch` did not reveal hidden public app, login, customer, pricing, or API pages. It did, however, surface a few demo promises that have now been implemented as an extension layer on top of the completed Caring Community foundation rather than as changes to completed modules.

Reference document: `docs/AGORIS_WEBSITE_COMPLETENESS_AUDIT.md`.

| Roadmap ID | Architectural implication |
|---|---|
| AG89 municipal AI communication/moderation copilot | Shipped as a municipality-specific proposal workflow that can draft, target, review, translate-check, and queue official communications before publication while reusing the existing KI-Agenten proposal/decision model. |
| AG90 personalised civic information filter/digest | Shipped as a resident digest over existing feed, project announcements, safety alerts, events, Vereine, care providers, marketplace/time-credit offers, and sub-region metadata. It remains a read/composition layer over completed modules, not a duplicate content store. |
| AG91 success-story proof cards | Shipped as exportable narrative cards over KPI/ROI/pilot-scoreboard signals, with caveats and evidence provenance. |
| AG92 two-way municipality feedback inbox | Shipped as a lightweight inbound civic feedback workflow distinct from formal surveys, tenant-scoped and optionally sub-region-scoped. |
| AG93 open-standards and integration showcase | Shipped as an evaluator-facing view of federation/API/webhook/OpenAPI/partner API capabilities without creating a second integration system. |
| AG94 newsletter and pilot-region lead nurture | Shipped as segmented follow-up for municipalities, investors, local businesses, residents, and partners, with explicit consent and locale handling. This remains demo/evaluation support, not a core KISS time-bank dependency. |
| AG95-AG97 pilot operations dashboards | Shipped as launch-readiness, help-request SLA, and civic-digest cadence surfaces so pilot operators can evaluate readiness from the admin console. |

Together these items form the "best demo ever" layer because they show the full Agoris promise: AI-assisted municipal work, filtered resident relevance, two-way dialogue, measurable proof, open integration, follow-up pipeline, and operational readiness.
