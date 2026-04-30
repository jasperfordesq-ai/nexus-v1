---
title: Project NEXUS — Response Pack for Martin Villiger, Roland Greber, and Christopher Mueller
audience: Agoris AG / Fondation KISS leadership
prepared_by: Jasper Ford, Project NEXUS
date: 2026-04-30 (revised after AG55 / AG65 / AG72 follow-up buildout)
status: Draft for review before sending
---

# Response Pack — Agoris / KISS / Caring Community Engagement

This document is the structured product response to Martin Villiger's email of 2026-04-25 asking whether NEXUS can be adapted and extended for KISS/Agoris, and requesting administration access for Roland Greber and Christopher Mueller.

It is intended as a single-page-able executive summary plus deeper supporting sections, suitable for forwarding to Roland and Christopher ahead of an evaluation session.

---

## 1. Executive summary (one paragraph)

**Yes, NEXUS can be adapted and extended for KISS and Agoris.** A switchable Caring Community module cluster is already built and live on the production tenant `https://app.project-nexus.ie/agoris`. It covers the full KISS time-bank workflow (verified hour logging, coordinator review, escalation, member statements, recurring support relationships, organisation auto-payment), the Caring Community member experience (request help, offer favour, my support relationships, invite codes), the municipal/canton/cooperative impact reporting layer with CHF social-value estimates, and the supporting infrastructure (multilingual de/fr/it/en, federation between regional nodes, kill-switch tested at API and route level). The module is built once and reusable for KISS, Swiss cantons, Irish/UK timebanks, and international federation partners — not an Agoris-specific fork. NEXUS now covers approximately 88 percent of the broader Agoris platform vision, and approximately 99 percent of a KISS-only time-bank deployment. The remaining gaps are no longer basic workflow gaps; they are mostly strategic/commercial decisions, external-partner integrations, legal/commercial structuring, Swiss-native content review, and later isolated-node/mobile build operations. The next concrete step is a careful guided walkthrough with you, Roland, and Christopher, followed by a focused product/legal/commercial workshop on KISS workflows, municipal reports, data protection, open-source/commercial boundaries, and Swiss deployment expectations.

---

## 2. Direct answers to your questions

### "Can NEXUS be adapted and extended with additional functionality?"

Yes. The platform is multi-tenant and feature-gated: every Caring Community capability is governed by per-tenant module switches. Adding new functionality follows the same pattern — services are added behind a feature flag, gated at the API and route level, surfaced in admin only when the feature is on, and removed cleanly when toggled off. This is verified by integration tests covering all 12 Caring Community admin endpoints plus the tenant bootstrap endpoint, and by route-level guards that redirect to a 404 when the module is disabled.

### "Can Roland Greber and Christopher Mueller have administration access?"

Yes. Roland and Christopher can be provisioned with least-privilege coordinator/admin access on the production `agoris` tenant. The coordinator role is one of the six KISS role presets shipped in the platform (national admin, canton admin, municipality admin, cooperative coordinator, organisation coordinator, trusted reviewer). They can evaluate the full KISS workflow without needing super-admin privileges. If preferred, we can also stand up a private staging tenant for evaluation.

### "Can the module disappear cleanly when turned off?"

Yes. Demonstrated by `test_caring_community_feature_disabled_reflects_in_tenant_config` and 11 other automated tests. With the feature off: admin sidebar entries hide, member nav entries hide, dashboard cards hide, mobile quick-create entries hide, public hub route returns the standard "Coming Soon" page, all 12 admin API endpoints return 403 with `FEATURE_DISABLED`, municipal impact export options hide, and direct admin URL navigation redirects to the admin 404. There is no orphan UI.

---

## 3. What is already built and live

The full inventory at the production tenant `https://app.project-nexus.ie/agoris`:

### Member-facing (Caring Community hub)

| Feature | Path | Status |
|--------|------|--------|
| Caring Community hub | `/caring-community` | Live |
| Request Help (low-friction form) | `/caring-community/request-help` | Live |
| Offer a Favour (credit-free pay-it-forward) | `/caring-community/offer-favour` | Live |
| My Support Relationships | `/caring-community/my-relationships` | Live |
| Marktplatz (unified time-credit + commercial) | `/caring-community/markt` | Live |
| Time-credit ↔ merchant loyalty redemption (closed-loop economy) | Inline at marketplace checkout | Live |
| My Loyalty Redemptions history | `/caring-community/loyalty/history` | Live |
| Future Care Fund (Zeitvorsorge view) | `/caring-community/future-care-fund` | Live |
| Cooperative-to-cooperative hour transfer | `/caring-community/hour-transfer` | Live |
| Hour gifting (give banked hours to family/friends) | `/caring-community/hour-gift` | Live |
| Safeguarding report submission | `/caring-community/safeguarding/report` | Live |
| My safeguarding reports | `/caring-community/safeguarding/my-reports` | Live |
| GDPR/FADP personal data export | `/settings/data-export` | Live |
| Invite Redemption | `/join/:code` | Live |
| Clubs & Associations directory | `/clubs` | Live |
| Time-credit listings | `/listings` | Live |
| Volunteering opportunities | `/volunteering` | Live |
| Organisations directory | `/organisations` | Live |
| Events / Groups / Goals / Resources / Polls | Various | Live |
| Federated cross-community discovery | `/federation` | Live |
| Multilingual de / fr / it / en | Tenant default `de` | Live |
| Near-me proximity filtering on listings, events, opportunities | All filterable | Live |

### Admin-facing (KISS Workflow Console + reporting)

| Feature | Path | Status |
|--------|------|--------|
| KISS Workflow Console | `/admin/caring-community/workflow` | Live |
| Trusted-review queue with SLA chips | Inside Workflow Console | Live |
| Coordinator assignment + manual escalation | Inside Workflow Console | Live |
| Approve / decline review decisions | Inside Workflow Console | Live |
| Recurring support relationship CRUD | Inside Workflow Console | Live |
| Relationship-linked hour logging | Inside Workflow Console | Live |
| Member statement preview + CSV export | Inside Workflow Console | Live |
| Workflow policy controls (SLAs, CHF hour value, statement day) | Inside Workflow Console | Live |
| KISS role-pack installer (six presets) | Inside Workflow Console | Live |
| Tandem suggestion engine (location/language/skills/availability/intergenerational) | Inside Workflow Console | Live |
| Loyalty redemption ledger + per-merchant settings | `/admin/caring-community/loyalty` | Live |
| Predictive Insights dashboard (3-month forecast + 7-signal alert engine) | Inside Workflow Console | Live |
| Safeguarding Reports queue with severity-based SLA | `/admin/caring-community/safeguarding` | Live |
| Cooperative-to-cooperative inbound/outbound transfer queue | `/admin/caring-community/hour-transfers` | Live |
| Federation Aggregates (signed JSON, HMAC-SHA256, 12-month audit log) | `/admin/federation/aggregates` | Live |
| National Fondation KISS Dashboard (cross-cooperative comparative) | `/admin/national/kiss` | Live |
| Coordinator-assisted member onboarding (temp password) | Inside Workflow Console | Live |
| Printable invite codes (6-char + print card) | Inside Workflow Console | Live |
| Informal favours log | Inside Workflow Console | Live |
| Municipal Impact Report with audience variants (canton / municipality / cooperative) | `/admin/reports/municipal-impact` | Live |
| Saved report templates with date filters and CSV/PDF export | `/admin/reports/municipal-impact` | Live |
| Municipality Announcer role + official feed badges | Admin user edit | Live |
| Research partnership governance | `/admin/caring-community/research` | Live |
| Research partner registry + aggregate dataset export/revocation | `/admin/caring-community/research` | Live |
| Tenant-branded native app readiness + build manifest export | `/admin/native-app` | Live foundation |

### Backend services and storage

| Capability | Implementation |
|-----------|----------------|
| Multi-tenant scoping | Every query scopes by `tenant_id`; integration tests cover IDOR boundaries |
| Feature flags | `tenant_features` JSON + Laravel middleware enforcement |
| RBAC | `roles` + `permissions` + `user_roles` tables, KISS preset installer, audit log |
| Federation | Signed `/federation/aggregates` endpoint, opt-in cross-node discovery, audit trail |
| Verein federation | Cross-invite target discovery, event sharing, member-sharing consent, municipality calendar |
| Authentication | Email/password + WebAuthn passkeys + Google/Apple OIDC |
| Identity verification | Stripe Identity + DOB matching, badge surfaced on profile |
| Email i18n | LocaleContext::withLocale renders every notification in the recipient's `preferred_language` |
| Research data governance | Partner registry, member consent state, anonymised aggregate export, export revocation audit |
| Native app handoff | Tenant-branded readiness booleans and `/v2/admin/config/native-app/build-manifest` JSON export |
| Wallets | Member + organisation balances with audit trail, atomic claiming, lockForUpdate |
| Backups | Encrypted full-stack snapshots to private repo |

---

## 4. Honest gap analysis vs the full Agoris vision

I have read the public Agoris materials (LinkedIn, RocketReach, the live agoris.ch homepage when it is reachable, and the strategic research briefs you shared) and benchmarked NEXUS against the five layers Agoris describes:

| Agoris platform layer | NEXUS coverage | Notes |
|----------------------|---------------|-------|
| KISS time-banking (Zeitvorsorge) | **99%** | Production-ready. Tandem matching, member statements, CHF social value, legacy-hour handling, KISS Treffen support, and policy-driven approval flows are all in place. |
| Voluntary help without time tracking | **85%** | Offer a Favour flow is live without wallet/credits. Further polish would come from real resident testing and category-led browsing. |
| Unified Marktplatz (commercial + voluntary) + closed-loop loyalty | **92%** | `/caring-community/markt` aggregator live, time-credit ↔ merchant loyalty bridge live, regional points layer live, advertising/push/premium/commercial analytics foundations live. Remaining work depends on commercial model decisions. |
| Regional infrastructure (Vereine, municipality, proximity) | **90%** | Verein directory, municipality announcer role, proximity filtering, municipal verification, Verein dues, Verein-to-Verein federation, cross-invite target discovery, and joint municipality calendar are live. Remaining work is mostly operating policy and Swiss field validation. |
| Modern UX hiding complexity for elderly users | **82%** | Warmth pass and native German pass complete; assisted onboarding and paper-form intake foundations exist. Remaining polish should be driven by Christopher/real-user UX testing. |
| Research / evidence / data governance | **85%** | Research partner registry, member consent, aggregate dataset export, suppression, export audit log, and export revocation are live. Richer agreement templates and formal external research workflows remain future polish. |
| Tenant-branded mobile / regional app layer | **Foundation** | Tenant-branded app configuration, store metadata readiness, push-routing readiness, and build-manifest export are live. Separate signed App Store / Play Store builds and CI/CD remain a later operational track. |

### Where the platform is genuinely best-in-class

The KISS workflow layer is more detailed than anything Agoris has publicly described. Coordinators get an end-to-end console with assignment, escalation, decision-making, recurring relationships, member statements, role presets, and tandem suggestions. The municipal impact report goes beyond a simple hour count — it produces audience-specific narratives (canton, municipality, cooperative) with CHF social-value estimates, year-over-year trends, member retention rates, reciprocity rates, and partner organisation breakdowns. This directly supports the political narrative KISS is building with the Swiss national parliament and canton governments.

### Where the platform has defined Phase 2 work

The original Phase 2 product gaps are now largely shipped: credit-free favour flow, proximity filtering, unified Marktplatz, municipal announcement channel, Verein directory, UX warmth pass, canton/municipality/cooperative report variants, native German review, regional points, research governance, and Verein federation follow-up are all in place. What remains is less about "can the platform do this?" and more about fit: which parts should be open-source NEXUS, which parts should remain an AGORIS-branded commercial layer, which deployment model is acceptable, and which claims need Swiss legal/UX validation before a public pilot.

### Where Phase 3 and beyond live

Physical POS bridge work should not be built speculatively. Earlier research conflated an unrelated Miderva "Agoris Smart POS & Inventory" app with AGORIS AG; the roadmap now treats POS as a hypothetical partner integration requiring a real POS partner agreement. Banking/payment/admin integrations are also partner-dependent and should sit after KISS fit, licensing, and commercial boundaries are agreed.

---

## 4a. What we know about Agoris's actual product surface today

I have done structured diligence using the public agoris.ch site, two independent research reports (one ChatGPT-led, one Gemini-led), the LinkedIn company page, RocketReach, the Fondation KISS website, the Swiss caring-communities network materials, and external software directories (G2, Capterra). The picture is consistent across sources, so I want to share it openly so we are calibrated together.

**What is publicly verifiable about Agoris today:**

- **Legal entity**: AGORIS AG, registered in Cham, Zug, Switzerland, at Obermühlestrasse 8, 6330 Cham.
- **Co-located with KISS Genossenschaft Cham at the same address** — operationally inseparable from the KISS pilot and the strongest point of credibility.
- **Four-person leadership** with relevant credentials: you (Martin) on time-banking and governance; Roland Greber on banking, payments, and innovation (CEO Swiss Bankers Prepaid Services); Dr. Christopher Mueller on UX (Die Ergonomen Usability AG, ETH Zurich); Tom Debus on technology and AI (Ferris).
- **Stated commercial model**: free for residents, monetized via local advertising, push campaigns, premium features, and later regional insights / data services.
- **Architecture intent**: federated regional nodes of approximately 15,000–30,000 citizens each, with options for centralised hosting or canton-controlled isolated nodes; "data sovereignty" and "Swiss Made Privacy" framing.
- **A founder-associated 99designs brief** describes transactions in **money, hours, OR regional points** — a third currency type beyond cash and time credits. This is on the Agoris roadmap publicly but does not yet appear to be live.
- **Software directory listings** (G2, Capterra) reference an "Agoris Smart POS & Inventory" product line with tiered SaaS pricing (Discovery free, Essential ~CHF 14.99, Growth ~CHF 29.99) — Miderva-developed merchant tooling — but this is a separate product surface from the civic platform on agoris.ch.

**What I could not find in public sources:**

- A published list of currently live municipalities or KISS cooperatives running Agoris.
- A public price sheet for municipalities or organisations.
- Quantified case studies, retention metrics, or implementation outcomes attributable to the current Cham-based AGORIS AG.
- A current Swiss Handelsregister extract surfaced on the live website.
- Mainstream press coverage or analyst writeups of the current company (as opposed to the historical Chablais AGORIS regional-integration project from 2008–2015, which appears to be brand/domain heritage rather than confirmed corporate lineage).
- A stable agoris.ch — the site was returning database errors during my research.

This is the normal profile of an early-stage or early-commercialisation Swiss platform with a strong founding narrative and limited public proof. Nothing about it is disqualifying. But it does mean that, in any partnership or evaluation conversation, **NEXUS's working production tenant and active Caring Community buildout are genuinely concrete, demonstrable assets in the room**. That asymmetry should shape how we approach the engagement.

---

## 4b. Why NEXUS is complementary to Crossiety and Localcities, not competitive with them

A neutral diligence read of the Swiss municipal-tech landscape places three incumbents close to the broader Agoris vision:

- **Crossiety** — 160+ digital village squares, 15,000+ groups, 730,000+ residents across Switzerland, Germany, and Liechtenstein. The dominant Swiss municipal/community resident app.
- **Localcities** — represented in 2,000+ Swiss municipalities. Strong incumbent in the municipal information layer.
- **Hoplr / nebenan.de** — European neighbourhood network scale (1.2M and 4M+ users respectively).

**NEXUS does not try to compete with these on municipal communication breadth.** That bar is real and high, and we would lose on scale.

**NEXUS competes — and wins — on the layer none of them have:**

| Layer | Crossiety | Localcities | Hoplr / nebenan.de | NEXUS Caring Community |
|-------|:---------:|:-----------:|:------------------:|:----------------------:|
| Resident communication / village square | ✅ | ✅ | ✅ | Basic (feed, groups) |
| Municipal information (waste, events, business directory) | ✅ | ✅ | Partial | Partial |
| **KISS-compatible time-bank workflow** | ✗ | ✗ | ✗ | **✅ Production-ready** |
| **Verified hour logging with coordinator review and SLAs** | ✗ | ✗ | ✗ | **✅** |
| **Tandem matching engine** | ✗ | ✗ | ✗ | **✅** |
| **Recurring support relationships with check-in tracking** | ✗ | ✗ | ✗ | **✅** |
| **Member statements with CHF social value estimate** | ✗ | ✗ | ✗ | **✅** |
| **Municipal impact reports (canton / municipality / cooperative variants)** | ✗ | ✗ | ✗ | **✅** |
| **Closed-loop time-credit-to-merchant loyalty bridge** | ✗ | ✗ | ✗ | **✅ New** |
| **Open source AGPL** | ✗ | ✗ | ✗ | **✅** |

**The strategic implication is straightforward**: NEXUS Caring Community is the engine that plugs into the regional/municipal communication layer that Crossiety, Localcities, or even a future Agoris super-app provides. A KISS cooperative running NEXUS today does not compete with Crossiety; it can run alongside Crossiety and offer the Zeitvorsorge layer that Crossiety lacks.

This positioning matters for two reasons:

1. **For KISS cooperatives evaluating us**, the question is not "should we replace Crossiety with NEXUS?" but "should we add NEXUS to deliver the time-bank workflow Crossiety cannot?". That is a much easier conversation.
2. **For Agoris specifically**, NEXUS's open-source nature means the Caring Community module cluster could be embedded into an Agoris-led regional super-app rather than competing with it. The AGPL licence requires that any modifications stay open, which aligns with Agoris's own data-sovereignty narrative.

---

## 4c. The closed-loop loyalty bridge — a uniquely strong differentiator

The Gemini research identifies a feature Agoris's public materials describe but the company does not appear to have shipped: **time credits earned in the Caring Community become discounts at participating local merchants**. This is the closed-loop regional economy that ties the social engine to the commercial engine.

**As of 2026-04-27, NEXUS has shipped this feature.** Concrete implementation:

- A KISS member earns hours by helping a neighbour (verified, coordinator-reviewed).
- The hours land in the member's wallet (existing).
- A local marketplace merchant who has opted in sets an exchange rate (e.g. CHF 25 per hour) and a maximum discount per order (e.g. 50%).
- At checkout, the member sees a "Use my time credits" card with a live discount preview, applies hours, and pays the reduced cash price.
- The redemption is logged to an immutable `caring_loyalty_redemptions` ledger; the merchant absorbs the discount as a community-engagement cost; the member's wallet debits.
- Admin sees a full redemption ledger and per-merchant participation report.

This is not a research project. It is a working endpoint set: `GET /v2/caring-community/loyalty/quote`, `POST /v2/caring-community/loyalty/redeem`, `GET /v2/caring-community/loyalty/my-history`, plus the admin equivalents. With Stripe Connect already integrated into the Marketplace module, the cash side of the transaction is production-grade.

This bridges Layer 1 (KISS time-bank) and Layer 3 (commercial Marktplatz) of the Agoris vision in a way that no visible competitor — Swiss or international — currently offers. **It is the single feature that, in a 30-minute walkthrough, separates NEXUS from every adjacent platform.**

---

## 5. Architecture summary for Roland and Christopher

For technical evaluation, the relevant facts:

- **Stack**: Laravel 12 (PHP 8.2+) backend, React 18 + TypeScript + HeroUI + Tailwind CSS 4 frontend, MariaDB 10.11, Redis 7+, Apache via Plesk on Azure VM.
- **Multi-tenant model**: row-level `tenant_id` scoping, with feature flags and module switches per tenant. Each KISS cooperative or Agoris regional node can be its own tenant with its own domain.
- **Three deployment modes** (full architecture in `docs/AGORIS_CARING_COMMUNITY_ARCHITECTURE.md`):
  1. Hosted tenant on `app.project-nexus.ie` — fastest to onboard
  2. Hosted tenant with custom domain (e.g. `caring.zg.ch`) — same infra, branded URL
  3. **Isolated-node deployment** — canton-controlled hosting, canton-managed DB, canton-managed backups, opt-in federation via signed API. This is the route a Swiss canton or KISS cooperative would take if data sovereignty is a hard requirement.
- **Federation between nodes**: each tenant exposes a read-only signed aggregate endpoint. Cross-node reporting at canton level rolls up federated aggregates without exposing raw member identities.
- **Open source**: AGPL-3.0-or-later, public repository at `https://github.com/jasperfordesq-ai/nexus-v1`. Source code is auditable; canton-specific modifications must remain AGPL-compliant.
- **Data protection**: tenant-scoped storage, opt-in federation, audit trail of cross-node queries, GDPR-style data export and deletion endpoints, FADP/nDSG disclosure-pack foundation, research-consent ledger, and anonymised aggregate export/revocation controls. Formal certification or canton-specific audit sign-off remains a separate procurement/legal workstream.
- **Research governance**: research partners can be registered with agreement/methodology references; members can opt in/out/revoke consent; admins can generate suppressed aggregate datasets and revoke exports while preserving audit history.
- **Native app handoff**: tenant-branded app readiness is represented as store metadata, iOS/Android identity, push-routing fields, and a downloadable build-manifest JSON for a later signed App Store / Play Store pipeline.
- **Email locale**: every notification renders in the recipient's `preferred_language` via LocaleContext::withLocale, validated by integration tests. A KISS member who chose Italian will receive Italian notifications even when the email was triggered by a German-speaking coordinator.

---

## 6. Suggested next steps

### Website completeness addendum

A fresh live scrape of `agoris.ch` on 2026-04-30 found no hidden public product, login, pricing, customer, demo, or API pages beyond the sitemap-listed marketing pages and three blog posts. The main platform vision is still covered by the existing NEXUS Caring Community buildout. The remaining website-derived demo opportunities are now tracked as AG89-AG94 in `docs/ROADMAP.md`, with source mapping in `docs/AGORIS_WEBSITE_COMPLETENESS_AUDIT.md`.

The most important additions for a "best demo ever" are:

| New roadmap item | Why it matters for the walkthrough |
|---|---|
| AG89 municipal AI communication/moderation copilot | Demonstrates Agoris's claim that AI supports municipal communication and moderation, not only matching or summaries. |
| AG90 personalised civic information filter/digest | Demonstrates the resident-side promise that relevant regional information becomes simple and filtered by locality/interests. |
| AG91 success-story proof cards | Turns KPI/ROI data into the "Erfolgsgeschichten" proof layer Agoris points to publicly. |
| AG92 two-way municipality feedback inbox | Covers feedback/dialogue beyond formal surveys and one-way announcements. |
| AG93 open-standards and integration showcase | Makes the modular/open/payment/admin-interface claims easy for Tom/Roland to inspect. |
| AG94 newsletter and pilot-region lead nurture | Covers the website's newsletter/contact funnel and gives follow-up structure after the demo. |

These items should be built as incremental layers on completed NEXUS modules, not as rewrites. The roadmap now includes an appendix showing which completed modules they extend.

I propose this sequence:

1. **Roland and Christopher review the `/agoris` tenant.** They can look around asynchronously before a walkthrough and note what feels aligned, wrong, missing, or strategically sensitive.
2. **30-minute guided walkthrough.** I demonstrate the KISS workflow end-to-end: a member requests help, a coordinator pairs them via tandem suggestion, the supporter logs hours, a trusted reviewer approves, the supporter's wallet credits, the recipient receives a member statement, and the canton-level municipal impact report aggregates the activity. Followed by a kill-switch demo (toggle the feature off, watch all UI disappear).
3. **Boundary and diligence discussion.** We explicitly separate KISS non-profit workflow needs, AGORIS commercial product strategy, open-source NEXUS reuse, private/commercial licensing options, data protection, and any confidential/proprietary AGORIS material that should not be implemented in the public AGPL repository. The companion question pack is `docs/AGORIS_DILIGENCE_QUESTION_PACK.md`.
4. **Focused product workshop, half day.** Working session with you, Roland, Christopher, and Tom Debus to align on KISS workflow specifics, municipal report format, FADP requirements, Swiss deployment expectations, UX testing, and commercial model assumptions. Output: a shortlist of gaps that are truly not yet in NEXUS.
5. **Pilot agreement or collaboration structure.** Choose whether the next step is a KISS cooperative pilot, an AGORIS/NEXUS integration, a private deployment, a separately licensed commercial copy, or a looser reference-implementation relationship. Define success metrics and a 90-day evaluation window only after that structure is clear.

I am ready to start step 2 immediately when you are.

---

## 7. Risks and open questions I want to flag now

So we go in clear-eyed:

- **Agoris public proof is thin.** The agoris.ch website was returning database errors during my 2026-04-27 research session. I would value seeing live deployments, current customers, and KPIs before scoping any custom build that would only make sense for Agoris specifically. The Caring Community module cluster as built today does not depend on Agoris for its value — it serves any KISS cooperative, Swiss canton, or international time-bank federation partner.
- **KISS is non-profit; Agoris is commercial.** The relationship between Fondation KISS and Agoris AG should be clearly documented for procurement and compliance. I would expect different licensing terms for non-profit KISS cooperatives versus commercial Agoris node operators.
- **Swiss FADP certification.** The FADP/nDSG compliance foundation is now implemented, but certification or formal legal audit is not free. If a canton requires audited FADP compliance, that is a separate workstream with its own timeline and budget.
- **Native-speaker content review.** The German UI is now KISS-canonical (Zeitvorsorge, Sorgende Gemeinschaft, Vertrauensperson, Unterstützungsbeziehung) but a Swiss German native review is still needed before public launch. Christopher Mueller could lead this if appropriate.
- **Open-source versus commercial boundary.** Anything committed to the public NEXUS repository is open-source under AGPL-3.0-or-later. Commercial backing, support SLAs, hosted tenant pricing, canton-specific extension contracts, a separately licensed copy, or private AGORIS layers are all open questions that need explicit legal/commercial structuring before either side relies on them.

---

## 8. What you can do today, even before the walkthrough

If you want to evaluate the platform yourself before scheduling the walkthrough:

- Visit `https://app.project-nexus.ie/agoris` — the production Agoris tenant.
- Browse the public Caring Community hub at `/caring-community`.
- Browse the public Clubs directory at `/clubs` — populated with realistic Cham Vereine (Männerturnverein, Frauenchor Cham-Hagendorn, Velo-Club Cham, Quartierverein Lorzenhof, and others).
- Browse the public Marktplatz at `/caring-community/markt`.
- In admin, review `/admin/caring-community/research` for research partner governance and `/admin/native-app` for tenant-branded app readiness.
- Check the listings, events, organisations, federation, and member directories — populated with realistic Cham/Zug content: KISS Genossenschaft Cham as primary partner, Spitex Zug, Pro Senectute Zug, Tafel Zug, Bibliothek Cham, plus a 15-member fictional Caring Community with 50+ logged hours over 30 days, 5 active recurring tandems, and an upcoming KISS Cham Mitgliederversammlung.
- Review the open-source code at `https://github.com/jasperfordesq-ai/nexus-v1`.
- Read the architecture note at `docs/AGORIS_CARING_COMMUNITY_ARCHITECTURE.md` in the repo.

The German UI is the default for that tenant, with French / Italian / English available via the language switcher. KISS-specific terminology has been hand-translated to native Swiss German throughout: Zeitvorsorge, Sorgende Gemeinschaft, Vertrauensperson, Unterstützungsbeziehung, Gefälligkeit, with du-form addressing for community warmth.

---

## 9. Contact and follow-up

For everything described in this document:

**Jasper Ford** — Project NEXUS founder, jasper@hour-timebank.ie
GitHub: `https://github.com/jasperfordesq-ai/nexus-v1`
Production: `https://app.project-nexus.ie`

I will hold the walkthrough slot open and respond same-day to any technical or commercial follow-ups you send.

---

*This document was originally prepared on 2026-04-27 and revised on 2026-04-30 after the AG55, AG65, and AG72 follow-up buildout. The state of the platform, the gap analysis, and the roadmap items referenced are accurate to 2026-04-30. The supporting source code, integration tests, and architecture notes referenced live in the public repository above.*
