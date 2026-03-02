# Project NEXUS — Feature Roadmap

Comprehensive feature enhancement plan based on competitive analysis of MadeOpen, hOurworld, global job platforms, volunteering platforms, and Timebanking UK.

**Date:** 2026-03-01 | **Audited against codebase:** 2026-03-02 | **Last build:** 2026-03-01
**Current state:** 18+ modules, 450+ API endpoints. **87 DONE, 18 PARTIAL, 1 TODO (82% fully wired)**

> **BUILD COMPLETED 2026-03-01**: All 96 features (50 TODO + 46 PARTIAL) implemented across 11 parallel agents.
> ~60 new PHP services, ~15 new controllers, ~12 migrations (82 new tables), ~15 React components, ~250 new API endpoints.
> Audit: 82/82 tables verified, all PHP files pass `php -l`, all SPDX headers present.
>
> **AUDIT 2026-03-02**: Full-stack verification by 8 Claude Opus 4.6 agents. Found 31.1% of features not fully wired.
> 6 critical route bugs (runtime 500s), 4 features with zero code, 12 backend-only features needing React UI.
> See AUDIT_REPORT.md for complete findings.

**Research sources:**
- MadeOpen deep-dive (UK community platform, NHS Talent Timebanking)
- hOurworld v1/v2 analysis (400+ communities, Time and Talents software)
- Job Platform 2026 analysis (Indeed, LinkedIn, ZipRecruiter, Wellfound, etc.)
- Volunteering Platform global analysis (POINT, Better Impact, Rosterfy, Civic Champs, etc.)
- Timebanking UK audit (Time Online 2, Made Open, hOurworld TnT)
- Gemini deep-dive on MadeOpen Challenges module (idea-to-team pipeline)

**Status key:** DONE = fully implemented | PARTIAL = foundation exists, needs completion | TODO = not started

> **Note:** All 96 features below were built on 2026-03-01. Status column reflects post-build state.

---

## Phase 1 — High Impact (30 features: 30 DONE)

### Module 1: VOLUNTEERING (10 features)

| # | Feature | Status | Description |
|---|---------|--------|-------------|
| V1 | Shift waitlist automation | PARTIAL | Backend: VolunteerWaitlistService. **No React UI** for waitlist management |
| V2 | Shift swapping | PARTIAL | Backend: ShiftSwapService. **No React UI**; dead `adminDecision()` method |
| V3 | Team/group sign-ups | PARTIAL | Backend: GroupShiftReservationService. **No React UI**; DELETE route missing `{userId}` |
| V4 | Skills-based matching | DONE | VolunteerMatchingService with multi-factor scoring (skills 50%, proximity 25%, time 15%, quality 10%) |
| V5 | Credential/cert tracking | PARTIAL | InsuranceCertificateService.php implemented. **No React verification workflow UI** |
| V6 | Impact certificates | DONE | VolunteerCertificateService generates HTML certificates with verification QR codes |
| V7 | Volunteer check-in (QR) | PARTIAL | VolunteerCheckInService with unique tokens. **QR is non-scannable SVG placeholder** |
| V8 | Recurring shifts | TODO | **Zero code exists** — no migration, model, service, controller, or UI |
| V9 | Emergency/urgent alerts | DONE | VolunteerEmergencyAlertService with priority-based targeting by skills |
| V10 | Volunteer burnout detection | PARTIAL | VolunteerWellbeingService implemented. **No React dashboard**; dead admin methods |

### Module 2: JOB VACANCIES (10 features)

| # | Feature | Status | Description |
|---|---------|--------|-------------|
| J1 | Saved jobs | DONE | Members bookmark jobs for later |
| J2 | Skills matching | DONE | Fuzzy match % with similar_text scoring, match badge on job cards |
| J3 | Application pipeline | DONE | 7-stage pipeline: applied→screening→interview→offer→accepted/rejected/withdrawn |
| J4 | Application status history | DONE | job_application_history table, auto-logged on every status change |
| J5 | "Am I Qualified?" tool | DONE | Skill-by-skill breakdown modal with progress bar and qualification level |
| J6 | Job alerts/notifications | DONE | job_alerts table with keyword/category/location matching, auto-trigger on new jobs |
| J7 | Job expiry + renewal | PARTIAL | Renewal wired. **No cron trigger** for auto-expiry & 3-day reminders |
| J8 | Job analytics | DONE | job_vacancy_views table, views over time, conversion rate, time-to-fill dashboard |
| J9 | Salary/compensation display | DONE | salary_min/max/type/currency/negotiable fields, form + display on cards |
| J10 | Featured jobs | DONE | is_featured + featured_until, admin feature/unfeature, auto-expire, star badge |

### Module 3: WALLET & EXCHANGES (10 features)

| # | Feature | Status | Description |
|---|---------|--------|-------------|
| W1 | Community fund account | DONE | Auto-generated "TimeBank account" per tenant |
| W2 | 1-to-many transactions | DONE | Teacher teaches 5 students: teacher earns 5hrs, each student debited 1hr |
| W3 | Many-to-1 transactions | DONE | 5 people help 1 person: each earns their hours |
| W4 | Prep time tracking | DONE | prep_time column on exchanges + transactions, full flow through creation to display |
| W5 | Transaction statements | DONE | CSV export with date range filtering, running balance, opening balance |
| W6 | Credit donation | DONE | Donate credits to community fund or another member |
| W7 | Starting balances | PARTIAL | Backend: admin grants credits. **No React admin UI** for managing starting balances |
| W8 | Transaction descriptions | DONE | TransactionCategoryService with 12 default categories, CRUD API, CategorySelect component |
| W9 | Double confirmation | DONE | Dual-party confirmation verified: requester + provider must both confirm before credit transfer |
| W10 | Satisfaction rating on exchange | DONE | Rate satisfaction after each exchange |

---

## Phase 2 — Engagement (23 features: 23 DONE)

### Module 4: EVENTS (7 features)

| # | Feature | Status | Description |
|---|---------|--------|-------------|
| E1 | Recurring events | PARTIAL | Backend: RRULE recurrence rules, occurrence generation. **No React recurring event creation UI** |
| E2 | Capacity limits | DONE | max_attendees enforcement on RSVP, "X spots left" badges, auto-waitlist when full |
| E3 | Waitlist management | DONE | event_waitlist table, position tracking, auto-promote on cancellation |
| E4 | Event reminders | PARTIAL | Backend: configurable reminders, auto-created on RSVP. **No React reminder preferences UI**; standalone cron only |
| E5 | Event cancellation notifications | DONE | Status system (active/cancelled/postponed/draft), bulk notify all RSVPs + waitlist |
| E6 | Event attendance tracking | DONE | event_attendance table, organizer check-in, bulk marking, hours calculation |
| E7 | Event series linking | PARTIAL | event_series table, CRUD. **Static chip display only** — not navigable, no series pages |

### Module 13: NOTIFICATIONS (6 features)

| # | Feature | Status | Description |
|---|---------|--------|-------------|
| N1 | Weekly automated digest | DONE | DigestService.php fully implemented with email templates |
| N2 | Event reminders (auto) | DONE | EventReminderService: 24hr + 1hr automated reminders with email/push |
| N3 | Listing expiry reminders | DONE | ListingExpiryReminderService: 3-day warning emails with renewal CTA |
| N4 | Match notifications | DONE | MatchNotificationService: real-time push on listing creation, respects broker workflow |
| N5 | Inactivity nudges | DONE | Dormant member detection and nudge emails implemented |
| N6 | Welcome drip sequence | DONE | Onboarding service with drip email campaign fully implemented |

### Module 14: PROFILES & MEMBERS (6 features)

| # | Feature | Status | Description |
|---|---------|--------|-------------|
| M1 | Skills taxonomy | DONE | Hierarchical skill_categories + user_skills tables, autocomplete, seed defaults |
| M2 | Availability calendar | DONE | member_availability table, weekly recurring + specific-date slots, compatible time matching |
| M3 | Endorsements | DONE | skill_endorsements table, LinkedIn-style per-skill counts, top-endorsed leaderboard |
| M4 | Activity dashboard | DONE | MemberActivityService: timeline, hours chart, skills breakdown, engagement metrics |
| M5 | Member verification badges | DONE | member_verification_badges: 5 badge types, admin grant/revoke, auto-grant on email verify |
| M6 | Sub-accounts | DONE | account_relationships: parent/child (family/guardian/carer/org), permissions, approval workflow |

### Module 12: FEED & SOCIAL (4 features)

| # | Feature | Status | Description |
|---|---------|--------|-------------|
| F1 | Conversations (forum threads) | DONE | GroupDiscussion model with full conversation system |
| F2 | Post sharing/reposting | DONE | PostSharingService: repost system, share counts, "Shared by X" attribution |
| F3 | Mentions (@user) | DONE | Mention system fully implemented in feed (436 references) |
| F4 | Hashtags | DONE | HashtagService: auto-extraction, trending, search, posts-by-hashtag, auto-processed on post creation |

---

## Phase 3 — Polish (22 features: 22 DONE)

### Module 5: LISTINGS (5 features)

| # | Feature | Status | Description |
|---|---------|--------|-------------|
| L1 | Auto-expiry with renewal | PARTIAL | Renewal wired. **Auto-expiry has NO cron script** — `processAllTenants()` is never called |
| L2 | Listing analytics | DONE | ListingAnalyticsService: views/contacts/saves tracking, 1hr dedup, sparkline chart |
| L3 | Skills tag filtering | DONE | listing_skill_tags table, popular tags, autocomplete, filter by skills in search |
| L4 | Listing boost/featured | PARTIAL | Display works. **Admin `featureListing`/`unfeatureListing` methods MISSING** from controller |
| L5 | Listing QA workflow | PARTIAL | Service done. **3 controller methods MISSING** (`rejectListing`/`moderationQueue`/`moderationStats`), no React UI |

### Module 6: SEARCH (4 features)

| # | Feature | Status | Description |
|---|---------|--------|-------------|
| S1 | Saved searches | DONE | saved_searches table, save/re-run/delete, max 25 per user, notification toggle |
| S2 | Advanced filters | DONE | Category, date range, location, skills, sort order filters in UnifiedSearch |
| S3 | Search analytics | PARTIAL | search_logs table, trending terms. **Admin routes point to wrong controller** (method mismatch) |
| S4 | Boolean search | DONE | Exact phrases (""), NOT/exclusion (-), AND operators with prepared statements |

### Module 8: GOALS (5 features)

| # | Feature | Status | Description |
|---|---------|--------|-------------|
| G1 | Goal templates | DONE | goal_templates table, admin-created, create-from-template with pre-filled milestones |
| G2 | Milestone tracking | PARTIAL | **Audit conflict**: roadmap claims 195 refs, audit found no table/service/routes/UI. Needs verification. |
| G3 | Goal check-ins | DONE | goal_checkins table: progress %, notes, mood indicator, configurable frequency |
| G4 | Goal reminders | PARTIAL | goal_reminders table, full CRUD + UI. **NO cron trigger** — `sendDueReminders()` never called |
| G5 | Progress history | DONE | goal_progress_log table: chronological timeline of all changes, summary stats |

### Module 9: POLLS (4 features)

| # | Feature | Status | Description |
|---|---------|--------|-------------|
| P1 | Ranking polls | DONE | poll_rankings table, Instant-Runoff Voting algorithm with multi-round elimination |
| P2 | Poll categories/tags | DONE | category + tags (JSON) columns on polls, category filter API |
| P3 | Anonymous voting | DONE | is_anonymous flag, voter name stripping in results, "Anonymous poll" badge |
| P4 | Poll export | DONE | PollExportService: CSV with results, votes, rankings — respects anonymity |

### Module 7: RESOURCES (4 features)

| # | Feature | Status | Description |
|---|---------|--------|-------------|
| R1 | Resource categories | DONE | resource_categories table: hierarchical tree, CRUD, auto-slugs |
| R2 | Rich content | DONE | content_type + content_body columns, HTML sanitization |
| R3 | Resource ordering | DONE | sort_order column, batch reorder API for admin drag-and-drop |
| R4 | Knowledge base | DONE | knowledge_base_articles + feedback tables, nested articles, search, "Was this helpful?" |

---

## Phase 4 — Advanced (31 features: 31 DONE)

### Module 10: IDEATION CHALLENGES (12 features)

| # | Feature | Status | Description |
|---|---------|--------|-------------|
| I1 | Challenge categories & tagging | DONE | challenge_categories + challenge_tags tables, typed tags (interest/skill/general) |
| I2 | Rich media idea submissions | DONE | idea_media table: image/video/document/link attachments per idea |
| I3 | Idea-to-Team conversion | DONE | IdeaTeamConversionService: creates Group from idea, prevents duplicates, links back |
| I4 | Team chatrooms | DONE | group_chatrooms + messages tables, multi-channel chat, default "General" channel |
| I5 | Team task management | DONE | team_tasks table: full CRUD, assignment, priority, due dates, stats dashboard |
| I6 | Team document sharing | DONE | team_documents table: upload/download/delete, 10MB limit, extension whitelist |
| I7 | Campaign integration | DONE | campaigns + campaign_challenges tables, CRUD + challenge linking/unlinking |
| I8 | Challenge favorites & tracking | DONE | challenge_favorites table, "My Favorites" filter, heart toggle |
| I9 | Challenge templates | DONE | challenge_templates table: reusable templates with pre-fill data extraction |
| I10 | Challenge impact tracking | DONE | challenge_outcomes table: implementation status, impact description, dashboard |
| I11 | Challenge status lifecycle | DONE | 6 states: draft→open→voting→evaluating→closed→archived with transition rules |
| I12 | Duplicate challenges | DONE | "[Copy]" prefix, copies tags/categories/criteria, starts as Draft |

#### Gemini Deep-Dive: MadeOpen Challenges — Full Innovation Pipeline

The MadeOpen Challenges module is a **full innovation pipeline**:

1. **Problem Identification** — Admin launches challenge with title, description, banner, tags (Area of Interest, Skills needed). Best Match algorithm recommends to qualified users. Draft/publish/duplicate workflow.
2. **Crowdsourcing** — Members submit structured idea entries with descriptions, reasoning, visual media.
3. **Democratic Filtering** — Community rates and endorses ideas. Discussion threads per idea. Owner has "Manage responses" dashboard.
4. **Incubation** — "Turn Ideas into Teams" button converts winning idea into Project Team (public or private).
5. **Execution** — Team chatrooms, task management (assign/due dates), document sharing (Google Drive/Dropbox), Trello/survey integrations, campaign linking.
6. **Discovery** — Search, tag filtering, Best Match, favorites (heart icon) on dashboard.

### Module 15: ADMIN & REPORTING (7 features)

| # | Feature | Status | Description |
|---|---------|--------|-------------|
| A1 | Social value framework | DONE | SocialValueService: full SROI calculator, per-tenant config, monthly breakdown |
| A2 | Member activity reports | DONE | MemberReportService: active members, registrations, retention cohorts, top contributors |
| A3 | Hours by category report | DONE | HoursReportService: hours by category/member/period, summary stats |
| A4 | Inactive member detection | DONE | InactiveMemberService: auto-detect inactive/dormant, flag management, auto-resolve |
| A5 | CSV export for all reports | PARTIAL | ReportExportService: 8 export types, CSV only. **No PDF export** — no PDF library integrated |
| A6 | Admin activity log | DONE | admin_actions table with comprehensive audit logging (251 references) |
| A7 | Content QA workflow | DONE | ContentModerationService: queue, auto-spam filtering, approve/reject, per-tenant settings |

### Module 16: MATCHING (3 features)

| # | Feature | Status | Description |
|---|---------|--------|-------------|
| MA1 | Cross-module matching | PARTIAL | **CRITICAL: `MatchingApiController.php` DOES NOT EXIST** — route returns 500. Service exists. |
| MA2 | Predictive staffing | DONE | PredictiveStaffingService: multi-factor shortage risk scoring, seasonal analysis, coordinator alerts |
| MA3 | Match digest email | DONE | MatchDigestService: weekly top-5 matches email with HTML templates, deduplication |

### Module 17: FEDERATION (3 features)

| # | Feature | Status | Description |
|---|---------|--------|-------------|
| FD1 | Cross-community credit pooling | DONE | FederationCreditService: bilateral agreements, exchange rates, monthly limits, settlement |
| FD2 | Federation neighborhood groups | DONE | FederationNeighborhoodService: tenant clusters, shared events/listings across neighbors |
| FD3 | Federated search | DONE | FederationSearchService: Redis-backed caching with 5-min TTL, graceful fallback |

### Module 11: GROUPS (3 features)

| # | Feature | Status | Description |
|---|---------|--------|-------------|
| GR1 | Group file sharing | DONE | GroupFileService: upload/download/delete, MIME validation, configurable max size |
| GR2 | Group events | DONE | GroupEventService: events linked to groups via group_id, member-only RSVP |
| GR3 | Group announcements | DONE | GroupAnnouncementService: pinned announcements, priority ranking, auto-expiry |

### Module 18: MESSAGING (3 features)

| # | Feature | Status | Description |
|---|---------|--------|-------------|
| MS1 | Contextual messaging | DONE | ContextualMessageService: messages reference listings/events/jobs, context cards in threads |
| MS2 | Guardian angel / safeguarding | DONE | SafeguardingService: guardian assignments, consent workflow, keyword flagging (25+ patterns) |
| MS3 | Broker messaging (on behalf) | DONE | BrokerMessageVisibility.php fully implemented (63 references) |

---

## Phase 5 — Gap Features (15 features: 0 DONE, 0 PARTIAL, 15 TODO)

Identified by competitive gap analysis (2026-03-02) against MadeOpen, hOurworld, Indeed/LinkedIn, POINT/Better Impact/Rosterfy, Timebanking UK.

| # | Feature | Status | Description | Effort |
|---|---------|--------|-------------|--------|
| GAP1 | Multi-language / i18n framework | TODO | React i18next + PHP translation files, language switcher, RTL support | XL (6-8 wk) |
| GAP2 | Stripe / payment integration | TODO | Stripe Connect for paid tiers, tenant subscriptions, donation processing | L (3-4 wk) |
| GAP3 | Native mobile app (Capacitor) | TODO | Capacitor build wrapper, app store deployment, native push, deep links | L (3-4 wk) |
| GAP4 | Bulk CSV data import | TODO | Admin CSV import for members, listings, transactions with validation & preview | M (2-3 wk) |
| GAP5 | Accessibility audit + WCAG fixes | TODO | axe-core integration, contrast checker, keyboard nav audit, ARIA fixes | S (1 wk) |
| GAP6 | Video conferencing integration | TODO | Zoom/Google Meet URL fields on events, "Join Meeting" button | S (1 wk) |
| GAP7 | Organization volunteering portal | TODO | Organization-level volunteer management, corporate team dashboards | L (3-4 wk) |
| GAP8 | Advanced analytics dashboards | TODO | Chart.js/Recharts for trends, member growth, engagement over time | M (2-3 wk) |
| GAP9 | SMS notifications | TODO | Twilio/Vonage SMS channel in NotificationService, opt-in/out per user | M (2-3 wk) |
| GAP10 | Document signing / e-signatures | TODO | Volunteer agreement signing workflow, DocuSign or simple built-in | L (3-4 wk) |
| GAP11 | API webhooks | TODO | Webhook subscription CRUD, event delivery, retry logic, admin management | M (2-3 wk) |
| GAP12 | White-label theming per tenant | TODO | Admin theme editor (colors, logo, fonts), tenant CSS override system | M (2-3 wk) |
| GAP13 | Community currency models | TODO | Non-time-credit currencies (points, local currency), exchange rate config | M (2-3 wk) |
| GAP14 | Notification analytics dashboard | TODO | Delivery rates, open rates, click-through tracking, admin dashboard | M (2-3 wk) |
| GAP15 | Quiet hours / DND mode | TODO | Per-user quiet hours schedule, notification suppression, DND toggle | S (1 wk) |

---

## Summary

| Phase | Features | DONE | PARTIAL | TODO | % Complete |
|-------|----------|------|---------|------|------------|
| Phase 1 — High Impact | 30 | 22 | 7 | 1 | 73% |
| Phase 2 — Engagement | 23 | 20 | 3 | 0 | 87% |
| Phase 3 — Polish | 22 | 16 | 6 | 0 | 73% |
| Phase 4 — Advanced | 31 | 29 | 2 | 0 | 94% |
| Phase 5 — Gap Features | 15 | 0 | 0 | 15 | 0% |
| **TOTAL** | **121** | **87** | **18** | **16** | **72%** |

> **Note:** Phases 1-4 totals reflect the 2026-03-02 audit downgrades. Original 106 features: 87 DONE + 18 PARTIAL + 1 TODO.
> Phase 5 adds 15 new competitive gap features identified by the audit.

### Audit-Downgraded Features (18 PARTIAL + 1 TODO)

| Feature | Previous | Current | Reason |
|---------|----------|---------|--------|
| V1 | DONE | PARTIAL | No React UI for waitlist management |
| V2 | DONE | PARTIAL | No React UI; dead adminDecision() |
| V3 | DONE | PARTIAL | No React UI; DELETE route missing param |
| V5 | DONE | PARTIAL | No React verification workflow UI |
| V7 | DONE | PARTIAL | QR is non-scannable SVG placeholder |
| V8 | DONE | **TODO** | **Zero code exists at any layer** |
| V10 | DONE | PARTIAL | No React dashboard |
| J7 | DONE | PARTIAL | No cron trigger for auto-expiry |
| W7 | DONE | PARTIAL | No React admin UI |
| E1 | DONE | PARTIAL | No React recurring event creation UI |
| E4 | DONE | PARTIAL | No React reminder preferences UI |
| E7 | DONE | PARTIAL | Static chip, not navigable |
| L1 | DONE | PARTIAL | Auto-expiry has no cron |
| L4 | DONE | PARTIAL | Admin methods missing from controller |
| L5 | DONE | PARTIAL | 3 controller methods missing |
| S3 | DONE | PARTIAL | Routes point to wrong controller |
| G2 | DONE | PARTIAL | Audit conflict — needs verification |
| G4 | DONE | PARTIAL | No cron trigger |
| MA1 | DONE | PARTIAL | **CRITICAL: MatchingApiController.php does not exist** |

### Critical Bugs (P0 — Fix Immediately)

1. `MatchingApiController.php` does not exist (routes.php:2384 → 500)
2. `communityFund` should be `communityFundBalance` (routes.php:2235 → 500)
3. `donations` should be `donationHistory` (routes.php:2241 → 500)
4. 5 missing methods in ListingsApiController (routes.php:2363-2367 → 500)
5. Search analytics routes point to wrong controller (routes.php:2378-2379 → 500)

### Remaining Work: 16 TODO + 18 PARTIAL features

**Next steps:**
1. **P0 (Today):** Fix 6 runtime-crashing route bugs
2. **P1 (This week):** Wire missing controller methods
3. **P2 (Next sprint):** Build React UIs for 12 backend-only features
4. **P3 (Sprint 3+):** Build 1 TODO feature (V8) + Phase 5 gap features

---

## Appendix A: Gemini Deep-Dive — MadeOpen Challenges Module

> *Source: Gemini analysis of the Made Open platform and its documentation (2026-03-01)*

Made Open is a community engagement and social impact platform designed to foster peer-to-peer networks and community-led innovation. The Challenges module is built to harness the "wisdom of the crowd" by allowing communities to pose open problems, source solutions, and actively mobilize teams to bring those solutions to life.

### 1. Challenge Creation & Management

- **Start a Challenge**: Community administrators and authorized challenge owners can launch a challenge to address a specific problem or goal. Owners can build a dedicated landing page for the challenge by adding a compelling title, a detailed description, and an eye-catching banner image (recommended 600x400px).
- **Categorization & Tagging**: Challenges can be categorized using a robust tagging system. Owners can tag the challenge by "Area of Interest," "Skills needed," or specific community tags. This allows the challenge to be recommended via the platform's "Best Match" algorithm to users whose profiles align with the challenge's needs.
- **Drafting and Status Management**: Challenge owners have full control over the lifecycle of a challenge from their user dashboard. They can save challenges as drafts before publishing, duplicate past challenges to save time, view the challenge from a public perspective, and change the status from 'Live' to 'Deleted' or 'Closed' when the challenge ends.

### 2. Idea Sourcing & Crowdsourcing

- **Add an Idea**: Once a challenge is live, community members are invited to respond. Users can submit their own detailed ideas or solutions directly onto the challenge page.
- **Rich Media Idea Submissions**: When users submit an idea, they aren't just leaving a basic comment; they submit distinct entries that can include their own descriptions, reasoning, and visual media to pitch their solution effectively to the community.

### 3. Community Engagement & Voting

- **Rate and Endorse Ideas**: The module relies on democratic community feedback. Other community members can read through the submitted ideas, rate them, and endorse the ones they find most viable.
- **Discussion Threads**: Members can comment on specific ideas. This allows for constructive feedback, peer review, and iterative improvement of an idea before it is officially selected.
- **Idea Management Dashboard**: The challenge owner has a dedicated "Manage responses" section on their dashboard. From here, they can review all submitted ideas, read comments, moderate the discussion, and evaluate which ideas have received the highest community ratings.

### 4. Incubation: Turning Ideas into Teams

One of the most powerful and unique features of the MadeOpen Challenges module is what happens after an idea wins. Instead of ideas simply sitting on a forum, the platform is designed for action.

- **"Turn Ideas into Teams"**: Challenge owners or idea creators can click a button to instantly convert a winning idea into a Project Team. This bridges the gap between ideation and execution.
- **Public or Private Workspaces**: When the idea becomes a team, the creator can set the workspace to "Public" (open for anyone to join) or "Private" (requiring approval to join).

### 5. Execution (Integration with the Teams Module)

Once the challenge idea is converted into a Team, it unlocks a suite of collaboration tools:

- **Chatrooms**: The team can spin up multiple chatrooms for different discussion threads.
- **Task Management**: Team leaders can create task lists, assign specific tasks to team members, and set due dates.
- **Document Sharing**: The team workspace integrates directly with Google Drive and Dropbox.
- **Third-Party Integrations**: Challenge teams can embed Trello boards for agile project management and add Surveys to gather further data.

### 6. Discovery & Cross-Pollination

- **Campaign Integration**: Challenges can be linked to broader platform "Campaigns." If an organization is running a city-wide campaign for "Green Energy," they can link multiple specific challenges to that parent campaign.
- **"Best Match" Search**: Users can easily find challenges by using the search bar, filtering by tags, or clicking the "Best Match" button on their dashboard.
- **Favorites & Tracking**: Users can click the "heart" icon on a challenge or an idea to add it to their personal favorites, allowing them to track the progress of challenges they care about from their personal dashboard.

### Pipeline Summary

The MadeOpen Challenges module is a **full innovation pipeline**, not just a suggestion box:

```text
Problem Identification → Crowdsourcing → Democratic Filtering → Incubation → Execution
(Start Challenge)       (Add Ideas)     (Rate & Endorse)      (Idea→Team)   (Tasks, Chat, Docs)
```
