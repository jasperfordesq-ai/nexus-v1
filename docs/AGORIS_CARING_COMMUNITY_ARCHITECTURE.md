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
