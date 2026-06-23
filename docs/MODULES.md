# Module Guide Map

Last reviewed: 2026-06-23

This page maps high-value product modules to their primary code and documentation locations. It is a guide to where deeper module docs should live, not a replacement for source code.

| Module | Backend surface | Frontend surface | Current docs | Deeper guide priority |
| --- | --- | --- | --- | --- |
| Wallet and exchanges | `app/Services/WalletService.php`, `app/Services/Exchange*`, `routes/api.php` `/v2/exchanges`, wallet routes | `react-frontend/src/pages`, accessible wallet/exchange routes | `docs/ARCHITECTURE.md` | High: lifecycle, ledger invariants, failure modes. |
| Listings | `ListingsController`, `ListingService`, marketplace services | listings pages, accessible listing templates | `docs/API.md` | High: offer/request flow, moderation, search indexing. |
| Messaging | `MessagesController`, message services, Pusher auth | conversations pages, accessible messages routes | `docs/TESTING.md` | Medium: attachments, voice, broker visibility, retention. |
| Events | `EventsController`, event services, recurring-event logic | event pages and accessible events routes | `docs-public/EVENTS_MODULE_ENGINE_REPORT.md` | Medium: recurrence, RSVP, waitlists, organiser actions. |
| Groups and members | `GroupsController`, member/profile services | group/member pages, admin/member directories | `docs-public/MEMBERS_DIRECTORY_ENGINE_REPORT.md` | Medium: permissions, private groups, group feed. |
| Federation | federation controllers and services, partner API routes | federation pages, accessible federation routes | `docs/FEDERATION_API_MANUAL.md`, `docs/FEDERATION_COVERAGE.md` | High: operations, partner onboarding, failure recovery. |
| Notifications and email | notification services, listeners, locale context | notification inbox and settings | `docs/ARCHITECTURE.md` | High: recipient locale, channels, retry behavior. |
| Search | `SearchService`, Meilisearch integration, SQL fallback | search and explore pages | `docs/API.md` | High: indexing, fallback, relevance, privacy. |
| Gamification | gamification services and controllers | achievements, leaderboard, score surfaces | `docs-public/GAMIFICATION_ENGINE_REPORT.md` | Medium: scoring rules and anti-abuse. |
| Volunteering | volunteering services, organisation controllers, hours logging | volunteering pages and accessible routes | `docs-public/VOLUNTEERING_ENGINE_REPORT.md` | High: hour approval, certificates, safeguarding. |
| Jobs | job controllers and services, bias audit | jobs pages and admin hiring surfaces | `docs-public/JOBS_MODULE_ENGINE_REPORT.md` | Medium: hiring workflow, fairness reports, exports. |
| Goals and impact | goal, impact, regional analytics services | goals and impact pages | `docs-public/GOALS_AND_IMPACT_ENGINE_REPORT.md` | Medium: metrics definitions and reporting windows. |
| Admin | `app/Http/Controllers/Api/Admin*`, admin services | `react-frontend/src/admin` | `docs/CONTRIBUTOR_TERMS_ENFORCEMENT.md`, `docs/SECURITY-SCANNING.md` | High: permissions and audit surfaces. |
| Mobile | API-backed Expo app | `mobile/` | `mobile/README.md`, `mobile/docs/` | Medium: release packaging and security model. |
| Accessible frontend | `app/Http/Controllers/GovukAlpha`, `routes/govuk-alpha.php`, parity route files | `accessible-frontend/` Blade/Sass/TS | `docs/govuk-alpha/RESEARCH.md`, `accessible-frontend/README.md` | High: parity, accessibility, route migration from alpha names. |

## Writing A Module Guide

Create a module guide only when it will help future maintenance. A good guide includes:

- audience and supported workflows;
- tenant and feature-gate rules;
- key routes, controllers, services, models, tables, and frontend entry points;
- security and privacy invariants;
- test commands and important regression tests;
- operational failure modes and recovery steps;
- OpenAPI links instead of copied endpoint tables.

Keep module guides small. Split only when one page becomes difficult to scan.
