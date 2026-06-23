# Module Guide Map

Last reviewed: 2026-06-23

This page maps high-value product modules to their primary code and documentation locations. It is a guide to where deeper module docs should live, not a replacement for source code.

> Maintained module guides live under `docs/modules/`. Until a module has a curated guide, the **source code** (`app/Services/`, `routes/api.php`) and the [architecture map](ARCHITECTURE.md) are the source of truth — the "Current docs" column reflects that. The earlier per-module engine-report snapshots (dated 2026-03-29) were moved out of the public repo into the local archive because they had drifted from current behaviour; do not treat them as live reference.

| Module | Backend surface | Frontend surface | Current docs | Deeper guide priority |
| --- | --- | --- | --- | --- |
| Wallet and exchanges | `app/Services/WalletService.php`, `app/Services/Exchange*`, `routes/api.php` `/v2/exchanges`, wallet routes | `react-frontend/src/pages`, accessible wallet/exchange routes | [modules/wallet-exchanges.md](modules/wallet-exchanges.md) | — guide exists. |
| Listings | `ListingsController`, `ListingService`, marketplace services | listings pages, accessible listing templates | [API.md](API.md); `routes/api.php` | High: offer/request flow, moderation, search indexing. |
| Messaging | `MessagesController`, message services, Pusher auth | conversations pages, accessible messages routes | code under `app/Services/`, `routes/api.php` | Medium: attachments, voice, broker visibility, retention. |
| Events | `EventsController`, event services, recurring-event logic | event pages and accessible events routes | code under `app/Services/`, `routes/api.php` | Medium: recurrence, RSVP, waitlists, organiser actions. |
| Groups and members | `GroupsController`, member/profile services | group/member pages, admin/member directories | [modules/members-and-gdpr.md](modules/members-and-gdpr.md) (members/GDPR); groups: code under `app/Services/` | Medium: permissions, private groups, group feed. |
| Federation | federation controllers and services, partner API routes | federation pages, accessible federation routes | [FEDERATION_API_MANUAL.md](FEDERATION_API_MANUAL.md) | High: operations, partner onboarding, failure recovery. |
| Notifications and email | notification services, listeners, locale context | notification inbox and settings | [modules/notifications.md](modules/notifications.md) | — guide exists. |
| Search | `SearchService`, Meilisearch integration, SQL fallback | search and explore pages | [modules/search.md](modules/search.md) | — guide exists. |
| Gamification | gamification services and controllers | achievements, leaderboard, score surfaces | code under `app/Services/` | Medium: scoring rules and anti-abuse. |
| Volunteering | volunteering services, organisation controllers, hours logging | volunteering pages and accessible routes | [modules/volunteering.md](modules/volunteering.md) | — guide exists. |
| Jobs | job controllers and services, bias audit | jobs pages and admin hiring surfaces | code under `app/Services/`, `routes/api.php` | Medium: hiring workflow, fairness reports, exports. |
| Goals and impact | goal, impact, regional analytics services | goals and impact pages | code under `app/Services/` | Medium: metrics definitions and reporting windows. |
| Admin | `app/Http/Controllers/Api/Admin*`, admin services | `react-frontend/src/admin` | [modules/admin.md](modules/admin.md), [CONTRIBUTOR_TERMS_ENFORCEMENT.md](CONTRIBUTOR_TERMS_ENFORCEMENT.md), [SECURITY-SCANNING.md](SECURITY-SCANNING.md) | — guide exists. |
| Mobile | API-backed Expo app | `mobile/` | `mobile/README.md`, `mobile/docs/` | Medium: release packaging and security model. |
| Accessible frontend | `app/Http/Controllers/GovukAlpha`, `routes/govuk-alpha.php`, parity route files | `accessible-frontend/` Blade/Sass/TS | [govuk-alpha/RESEARCH.md](govuk-alpha/RESEARCH.md), `accessible-frontend/README.md` | High: parity, accessibility, route migration from alpha names. |

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
