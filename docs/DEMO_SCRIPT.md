# NEXUS Demo Script — AGORIS / KISS Evaluation
## A guided walkthrough for Roland Greber, Christopher Müller, and Tom Debus

**Tenant URL:** https://app.project-nexus.ie/agoris  
**Duration:** 20–30 minutes  
**Format:** Self-guided or screen-share with Jasper  
**Date prepared:** 2026-04-28  
**Last updated:** 2026-04-30 after AG55 / AG65 / AG72 follow-up buildout

> **Note for evaluators:** This script walks you through the platform's KISS/caring-community flow end-to-end. You are welcome to deviate from the script at any point — the platform is live and everything is real data. Use the callout boxes (▶ **Christopher / Roland / Tom**) to find sections most relevant to your specific evaluation area.

> **Boundary note:** The current AGORIS tenant is a feasibility demonstration built inside open-source Project NEXUS. It is not a finished AGORIS commercial product and it does not settle brand, licensing, data ownership, or commercial independence questions. Treat the walkthrough as a discovery session: what overlaps, what is wrong, what should remain AGORIS-specific, and what should not be implemented in the public AGPL repository?

---

## Before you start

1. Open **https://app.project-nexus.ie/agoris** in a modern browser (Chrome or Firefox recommended)
2. Your admin login credentials have been sent separately
3. The tenant is pre-loaded with **Cham/Zug demo data** — approximately 40 sample members, realistic support relationships, and volunteer logs so dashboards show real numbers rather than zeroes

---

## Act 1 — Member Onboarding (5 min)

*Goal: experience NEXUS from a new member's perspective*

### Step 1.1 — Register as a new member

1. Click **Sign up** on the landing page
2. Enter a name, email, and password
3. Select your preferred language — try **Deutsch** to verify the Swiss-German experience
4. Complete the interests step (Nachbarschaftshilfe, Pflege, Transport, etc.)
5. You will land on the **Dashboard**

> ▶ **Christopher**: This is the moment to assess first-run UX. Is the language natural? Is the flow too many steps for a 75-year-old? Note anything that creates friction for the elderly-resident persona.

### Step 1.2 — Complete your profile

1. Click your avatar → **Mein Profil**
2. Add a short bio, upload a photo, add a skill tag
3. Notice the **Trust Level badge** — you start as *Newcomer*; logging hours and receiving reviews moves you up through Member → Trusted → Verified → Coordinator

> ▶ **Christopher**: This is the "Trustlevel" feature AGORIS named in their marketing. The criteria per tier are configurable per cooperative in the admin panel.

---

## Act 2 — Offering and Requesting Help (5 min)

*Goal: the core exchange loop — the heart of the KISS time-bank model*

### Step 2.1 — Post a listing (offer help)

1. Navigate to **Marktplatz** (`/agoris/caring-community/markt`)
2. Click **Offer Help** → fill in: title ("Fahrdienst zum Arzt"), description, category (Transport), time-credit value (1 hour), location radius
3. Submit. Your listing is now live and discoverable by other members.

### Step 2.2 — Post a help request

1. Navigate to **Hilfe anfordern** (`/agoris/caring-community/request-help`)
2. Fill in: "Begleitung zum Arzttermin am Donnerstag", category, urgency
3. Submit. The system's smart-matching engine will surface relevant volunteers.

> ▶ **Tom**: The matching pipeline is at `app/Services/SmartMatchingEngine.php`. It uses OpenAI embeddings + collaborative filtering on prior engagement signals. After a request is submitted, type `/agoris/matches` to see who the system surfaced and why. The ranking weights are configurable.

### Step 2.3 — Browse the unified directory

Navigate to **Anbieter-Verzeichnis** (`/agoris/caring-community/providers`). This is the unified care-provider directory: Spitex, Tagesstätten, private services, Vereine, and volunteer groups in one filterable view. Filter by type using the tabs at the top.

---

## Act 3 — Support Relationships and Hour Logging (5 min)

*Goal: KISS-specific workflow — verified hour logging, coordinator review*

### Step 3.1 — View support relationships

1. Navigate to **Meine Beziehungen** (`/agoris/caring-community/my-relationships`)
2. You will see the sample support pairs created in the demo seed — supporter ↔ recipient, frequency, next check-in
3. Click a relationship to see the timeline of logged hours

### Step 3.2 — Log hours as a supporter

1. Inside a support relationship, click **Stunden erfassen**
2. Enter date, hours (e.g. 2.5), optional note
3. Submit. The coordinator is notified; the hour goes into the **pending review** queue unless auto-approval is configured for your role.

### Step 3.3 — See the admin review queue (switch to admin)

1. Log in as the **coordinator** account (credentials in the email you received)
2. Navigate to **Admin → Caring Community → Workflow**
3. You see the pending hour log — approve it. The supporter's wallet is credited automatically.
4. Notice the **Reziprozitätssaldo** panel (reciprocal balance): does the cooperative give as much as it receives?

> ▶ **Roland**: The wallet is an immutable double-entry ledger. Every transfer is logged. The cooperative-to-cooperative hour transfer (`K3`) and Future Care Fund (`K1`) are both live. The Fondation KISS cross-cooperative dashboard (`K4`) shows aggregate hours across all federated tenants.

---

## Act 4 — Caregiver Experience (3 min)

*Goal: the Angehörigen (informal carer) persona — distinct from the KISS volunteer role*

### Step 4.1 — Caregiver dashboard

Navigate to **Pflegenden-Dashboard** (`/agoris/caring-community/caregiver`). This is the view for a family member or friend who informally cares for someone:

- See the care schedule for the person you are looking after
- If your own weekly caring hours exceed the threshold, a **burnout warning** appears at the top
- "Request help on their behalf" — you can create a help request for the cared-for person without them needing to log in

> ▶ **Christopher**: This was identified as a key persona gap in the AGORIS blog — *"Angehörige sind tragende Säulen des Systems – und oft überlastet."* The burnout threshold is admin-configurable.

---

## Act 5 — Community Infrastructure (3 min)

*Goal: events, groups, organisations, federation — the civic layer*

### Step 5.1 — Events

1. Navigate to **Veranstaltungen** → browse the Cham/Zug demo events
2. RSVP to one. Notice that events can be restricted to members, open to the public, or cross-tenant via federation.

### Step 5.2 — Organisations

Navigate to **Organisationen**. In the demo, there are sample Vereine and volunteer organisations. Each has:
- Member roster
- Volunteer opportunities
- Wallet (organisation-level time-credit balance)
- Verein bulk-import: an admin can import a CSV of Verein members directly (admin → Caring Community → Verein Import)

### Step 5.3 — Federation

Navigate to **Federation** (`/agoris/federation`). This shows how the Cham cooperative connects to other NEXUS tenants. Cross-tenant hour transfers and member discovery are live.

For the Verein-specific flow, open a member profile and use the cross-invitation action. The backend now discovers shared source Vereine and eligible target Vereine via `/v2/vereine/cross-invite-targets/{userId}`, so the invitation modal has real federation targets instead of relying on a hardcoded list.

---

## Act 6 — Municipal Impact (4 min)

*Goal: the reporting layer — the evidence that Gemeinden and foundations need*

### Step 6.1 — Impact report

Log into admin → **Reports → Municipal Impact Report**. Select a period. The report shows:
- Verified volunteer hours
- Direct economic value (CHF, using configurable hourly rate)
- Social value multiplier (2.8× default, configurable per tenant)
- Member and recipient counts
- Trend charts

> ▶ **Roland**: This is the evidence pack for a Gemeinde contract renewal or parliamentary briefing. The hourly CHF value and social multiplier are set in admin and can reflect KISS's own published methodology.

### Step 6.2 — KPI baseline comparison

Navigate to **Admin → Caring Community → KPI Baselines**.

1. Capture a baseline now (label it "Evaluierungsbeginn April 2026")
2. In a real deployment, you capture a baseline before NEXUS launches, then compare quarterly
3. The comparison view shows absolute deltas and percentage changes per metric — this is where you substantiate claims like "30% weniger Aufwand" to a Gemeinde

### Step 6.3 — Predictive coordinator dashboard

Navigate to **Admin → Caring Community → Workflow → Prognosen**. The forecasting module (Tom Debus's AI pillar) projects hours, member growth, and recipient count 3 months ahead using linear regression on the historical log. The model is deliberately simple and explainable — a care coordinator can understand why it made a prediction.

> ▶ **Tom**: The forecast service is at `app/Services/CaringCommunity/CaringCommunityForecastService.php`. It is currently linear regression only. Swap in a more sophisticated model or connect it to your data infrastructure via the API.

---

## Act 7 — Module Kill Switch (1 min)

*Goal: demonstrate that the platform is governable — you can turn features on and off*

1. Navigate to **Admin → Tenant Features**
2. Find **Caring Community** — toggle it off
3. Navigate back to `/agoris/caring-community` — the entire module disappears from nav and all API endpoints return 403
4. Toggle it back on. All data is preserved.

This applies to every feature: events, marketplace, gamification, federation, AI chat. Each can be independently enabled per cooperative.

> ▶ **Roland**: This is the governance lever. A national foundation can prescribe which features a cooperative must run; the cooperative can enable optional add-ons within that constraint.

---

## Act 8 — Research Governance and Evidence Exports (3 min)

*Goal: show how the "wissenschaftlich begleitet" claim can be governed without exposing raw member data*

1. Navigate to **Admin → Caring Community → Research Partnerships** (`/agoris/admin/caring-community/research`)
2. Review the partner registry: institution, agreement reference, methodology URL, status, and data scope
3. Generate an aggregate dataset export for a reporting period
4. Review the export history: partner, period, row count, hash, status
5. Revoke an export to see the audit-preserving revocation flow

> ▶ **Roland**: This is not a replacement for a DPA, ethics review, or formal legal audit. It is the operational control surface: partner record, member consent state, suppression, export audit, hash, and revocation.

> ▶ **Christopher**: Please assess whether this is understandable to a non-technical product owner. The screen is deliberately plain because the audience is governance/admin, not residents.

---

## Act 9 — Tenant-Branded Mobile Readiness (2 min)

*Goal: show what exists for the "Deine App" / regional app claim, and what remains a later build pipeline*

1. Navigate to **Admin → System → Native App** (`/agoris/admin/native-app`)
2. Review store mode: shared NEXUS app vs tenant-branded app
3. Review iOS/Android identity fields, store metadata, push-routing fields, and PWA settings
4. Click **Export Build Manifest** to download the JSON handoff for a later signed iOS/Android build pipeline

> ▶ **Tom**: This is a handoff contract, not a completed white-label mobile CI/CD system. The later build pipeline can consume `/v2/admin/config/native-app/build-manifest` rather than scraping tenant settings.

---

## Act 10 — Technical Layer (Tom Debus) (3 min)

> This section is for Tom's technical evaluation. Roland and Christopher can skip it.

### Stack at a glance

| Layer | Technology |
|---|---|
| API | Laravel 12, PHP 8.2, RESTful JSON, Sanctum auth |
| Database | MariaDB 10.11 (MySQL-compatible), multi-tenant scoped |
| Frontend | React 18 + TypeScript + HeroUI + Tailwind CSS 4 |
| Real-time | Pusher WebSockets |
| Push notifications | FCM (Firebase Cloud Messaging) via Capacitor |
| AI | OpenAI embeddings + GPT-4o for matching and chat; modular — swap providers |
| Search | Meilisearch (self-hosted) |
| Mobile | Capacitor (iOS + Android from same codebase) |
| Infrastructure | Docker Compose; runs on any Linux VM or self-hosted Kubernetes |
| Source | AGPL-3.0 at https://github.com/jasperfordesq-ai/nexus-v1 |

### Key extension points

- **New tenant features** — add a key to `TenantFeatureConfig::FEATURE_DEFAULTS`; gate PHP with `TenantContext::hasFeature()`, gate React with `useTenant().hasFeature()`
- **New services** — static PHP class in `app/Services/`, always scope by `TenantContext::getId()`
- **New API endpoints** — controller in `app/Http/Controllers/Api/`, route in `routes/api.php`
- **New React pages** — TSX in `react-frontend/src/pages/`, route in `App.tsx`
- **API reference** — `docs/API_REFERENCE.md` (50+ endpoints documented)
- **Technical guide** — `docs/TECHNICAL_EVALUATOR_GUIDE.md`

### Running it yourself

```bash
git clone https://github.com/jasperfordesq-ai/nexus-v1
docker compose up -d
# App at localhost:5173 (React) + localhost:8090 (PHP API)
```

---

## After the walkthrough — what we need from you

| Evaluator | Key questions |
|---|---|
| **Roland** | Which pieces are hard blockers for KISS's current phase versus later commercial AGORIS phases: banking APIs, formal FADP certification, POS partner integrations, isolated-node operations, or commercial licensing? |
| **Christopher** | Which UX flows create friction for elderly Swiss residents? Where does the German feel unnatural? What would you redesign first, and what must remain AGORIS-branded or proprietary? |
| **Tom** | Does the architecture support the data-sovereignty / per-municipality isolated-node model AGORIS described? What AI infrastructure do you want to connect, does the extension API surface support it, and is the native-app build manifest a useful handoff contract? |

Please reply to jasper@hour-timebank.ie with your findings, or use the shared notes doc Martin will send.

---

## Appendix — Key admin credentials

*(Sent separately by Jasper — not included in this document)*

## Appendix — Files referenced in this walkthrough

| Document | Path in repo |
|---|---|
| Platform orientation | `docs/CARING_COMMUNITY_ORIENTATION.md` |
| Technical evaluator guide | `docs/TECHNICAL_EVALUATOR_GUIDE.md` |
| API reference | `docs/API_REFERENCE.md` |
| Full roadmap (gap analysis) | `docs/ROADMAP.md` § 10 |
| Response pack | `docs/AGORIS_MARTIN_RESPONSE_PACK.md` |
| Agoris/KISS architecture | `docs/AGORIS_CARING_COMMUNITY_ARCHITECTURE.md` |
| Diligence question pack | `docs/AGORIS_DILIGENCE_QUESTION_PACK.md` |
| German i18n audit | `docs/GERMAN_AUDIT_REPORT.md` |
