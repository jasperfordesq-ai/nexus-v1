# AGORIS / KISS Diligence Question Pack

> Prepared: 2026-05-01  
> Audience: Martin Villiger, Roland Greber, Dr. Christopher Mueller, Tom Debus  
> Purpose: questions to clarify the overlap between Project NEXUS, Fondation KISS needs, and AGORIS commercial strategy before any pilot, integration, private deployment, or licensing decision.

## 1. Strategic Fit

1. Is AGORIS intended to be an independent commercial product, a KISS-supported civic infrastructure layer, a regional super-app, or a combination of these?
2. Which parts of the AGORIS concept are considered proprietary or commercially sensitive?
3. Which parts could safely be implemented in an open-source AGPL project without harming AGORIS's strategic position?
4. Should NEXUS be evaluated as a technical foundation, a module provider, a reference implementation, an integration partner, or simply useful prior work?
5. What would count as a successful first pilot by the end of 2026?

## 2. KISS / Non-Profit Boundary

1. Which requirements come directly from Fondation KISS or local KISS cooperatives?
2. Which requirements come from AGORIS's commercial product vision rather than KISS's time-bank model?
3. Would KISS cooperatives expect different licensing, pricing, governance, or data-protection terms from commercial AGORIS regional operators?
4. Who owns the operating policy for hour approval, trusted reviewers, member statements, and legacy-hour handling?
5. Is the KISS workflow expected to remain institutionally neutral, or should it be AGORIS-branded?

## 3. Product Scope

1. Which resident journeys matter most for the first pilot: request help, offer help, time banking, commercial marketplace, municipal announcements, events, research evidence, or caregiver support?
2. Which workflows must be excellent for older/nontechnical residents before launch?
3. What should be hidden from residents until a later phase?
4. Which modules should be tenant-enabled for the AGORIS demo, and which should remain off?
5. Should AGORIS use NEXUS's existing Caring Community hub, or does Christopher's UX direction require a distinct AGORIS shell?

## 4. Commercial Model

1. Which monetisation routes are in scope for the first commercial version: local advertising, paid push campaigns, premium features, merchant discounts, regional analytics, partner APIs, or municipality contracts?
2. Are advertising and paid promotion compatible with the trust expectations of KISS cooperatives?
3. Who would contract with municipalities: AGORIS, KISS, a regional conference, or another entity?
4. Is a private AGORIS deployment, separately licensed copy, or commercial support/SLA arrangement required?
5. What must remain independent in AGORIS's brand, customer relationships, data model, and pricing?

## 5. Open Source And Licensing

1. Is AGORIS comfortable building on AGPL-3.0-or-later software where network-use modifications must be shared under the same licence?
2. Which proposed AGORIS-specific features should not be committed to the public NEXUS repository?
3. Would AGORIS want to explore a separate commercial licence or private copy, subject to legal review?
4. Who will review compatibility between NEXUS's AGPL licence, AGORIS's commercial ambitions, and Swiss municipal procurement expectations?
5. What attribution, source-availability, and modification-sharing obligations would AGORIS be prepared to accept?

## 6. Data Protection And Governance

1. What data classes does AGORIS expect to process: member profiles, addresses, hour logs, care relationships, caregiver notes, municipal data, research exports, advertising audiences, payment data?
2. Which data must remain in Switzerland, and is canton-controlled isolated hosting required for any pilot?
3. Who is the controller and who is the processor for each operating model?
4. Is formal FADP/nDSG certification or external legal audit required before pilot launch?
5. What research-consent, anonymisation, suppression, and revocation standards must apply to academic or evaluation partners?

## 7. Architecture And Integration

1. Does AGORIS need central hosted tenants, custom-domain hosted tenants, isolated canton nodes, or a mixture?
2. Should regional nodes federate by aggregate reporting only, member discovery, event sharing, Verein sharing, or full cross-node exchange?
3. Which external systems are expected: banking/payment APIs, municipal systems, identity providers, POS partners, Spitex/care systems, analytics platforms, AI infrastructure?
4. Is the tenant-branded native app build-manifest approach useful for AGORIS's mobile strategy?
5. What security review, penetration test, code audit, or architecture review would be required before a pilot?

## 8. Pilot Definition

1. Which pilot region, municipalities, and organisations are involved in Nördlich Lägern?
2. How many residents, coordinators, Vereine, businesses, and municipalities should be included in the first pilot?
3. What is the minimum tangible platform experience required by the end of 2026?
4. What metrics define success: onboarded members, active helpers, logged hours, response time, coordinator workload, resident satisfaction, commercial engagement, municipal reporting value?
5. Who has decision authority after the walkthrough: Martin, Roland, Christopher, Tom, the regional conference, KISS, municipalities, or another board?

## 9. Walkthrough Output

After the first walkthrough, produce a one-page overlap map:

| Area | Covered by NEXUS today | Needs configuration | Needs build | Should remain AGORIS-specific | Owner |
|---|---|---|---|---|---|
| KISS time-bank workflow | | | | | |
| Caring Community resident UX | | | | | |
| Municipal reporting | | | | | |
| Research evidence layer | | | | | |
| Local commerce / advertising | | | | | |
| Mobile app | | | | | |
| Licensing / commercial model | | | | | |
| Data protection / hosting | | | | | |

## 10. Immediate Ask

Before any deeper buildout, ask AGORIS/KISS to confirm:

1. The intended role of NEXUS in the evaluation.
2. The parts of AGORIS that must remain private or commercially independent.
3. Whether AGPL is acceptable for the shared foundation.
4. Whether a separately licensed/private route should be explored.
5. The exact scope of the first Nördlich Lägern platform experience.

## 11. Website Completeness Questions

The 2026-04-30 live scrape of `agoris.ch` added AG89-AG94 to the roadmap, and the May 1 pass shipped those demo layers plus AG95-AG97 pilot operations dashboards. Use these questions to validate whether the now-demonstrable surfaces are truly in scope for the first pilot, only later marketing/product strategy, or commercial/private AGORIS territory.

1. For the claimed AI support in municipal communication and moderation, should AGORIS expect an admin copilot that drafts, reviews, targets, translates, and queues official posts before publication?
2. For "Informationsfilter" and direct communication, should residents receive a personalised regional digest combining municipality posts, project updates, Vereine, care providers, safety alerts, marketplace offers, and help needs?
3. For "Erfolgsgeschichten", may the demo use clearly labelled fictional proof cards generated from seeded KPI/ROI data until real pilot data exists?
4. For feedback/dialogue, is a lightweight municipality inbox enough, or must feedback be routed into formal survey/project/CRM workflows?
5. For open standards and integrations, what does Tom/Roland need to see: OpenAPI, webhook signatures, federation aggregate payload, OAuth/client credentials, a sample partner app, or all of these?
6. For newsletter/contact capture, should NEXUS demonstrate lead nurture for municipalities, investors, businesses, residents, and partners, or leave this in HubSpot/AGORIS CRM?

## 12. Readiness Sign-Off Questions

1. Who owns the launch-readiness gate for each workstream: Martin/KISS for operations, Christopher for resident UX, Roland/AGORIS for commercial boundary, Tom for architecture, and legal counsel for FADP/nDSG?
2. Are the AG95 launch-readiness sections enough for a first go/no-go review, or does AGORIS need additional procurement, finance, or board-approval gates?
3. What help-request SLA should be configured for a pilot, and who is accountable when the AG96 dashboard shows breached or at-risk requests?
4. Should the AG90 civic digest start disabled, weekly, or daily for pilot residents?
5. Which hardening evidence matters most before access is provisioned: accessibility regression results, database retention/cascade changes, recipient-locale notification tests, federation aggregate audit trail, or all of them?
