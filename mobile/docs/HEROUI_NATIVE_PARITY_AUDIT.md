<!--
Copyright © 2024–2026 Jasper Ford
SPDX-License-Identifier: AGPL-3.0-or-later
Author: Jasper Ford
See NOTICE file for attribution and acknowledgements.
-->

# HeroUI Native Parity Audit

Date: 2026-05-29

Scope: `mobile/` Expo app compared with the maintained web React frontend in `react-frontend/`.

Out of scope by owner instruction: React admin, broker/admin panels, caring-community workflows, legacy PHP views, production deployment tooling, and web-only marketing/admin operations.

## Executive Status

| Area | Status | Notes | Next action |
| --- | --- | --- | --- |
| HeroUI Native package | Complete | `heroui-native` updated from `^1.0.3` to `^1.0.4`, the latest npm version checked during the audit. | Keep current during future Expo upgrades. |
| Provider setup | Complete | `app/_layout.tsx` imports `global.css`, wraps with `GestureHandlerRootView`, and mounts `HeroUINativeProvider`. | None. |
| Styling setup | Complete | `global.css` imports Tailwind CSS, Uniwind, HeroUI Native styles, and sources HeroUI Native library classes. Current official HeroUI Native theme sources use OKLCH variables, so the existing OKLCH brand overrides match upstream. | Continue moving screen code from manual theme colors to semantic class names. |
| Shared UI wrappers | Partial | Button loading now uses HeroUI Native `Spinner`; Input now uses `TextField`, `Label`, `Input`, and `FieldError`; FAB now uses HeroUI Native `Button`; exchange/member/group/blog search fields and exchange create/edit forms now use the shared Input wrapper. The native connections route uses HeroUI Native Card/Tabs/Chip/Button/Spinner/Surface primitives. | Continue migrating complex form fields to shared wrappers. |
| Route-level HeroUI use | Partial | Most functional screens use HeroUI Native directly or through local UI wrappers. Redirect/re-export routes intentionally contain no UI. Several complex screens still use raw `TextInput`, `Pressable`, and manual color styling where a larger refactor is needed. | Incrementally migrate by feature area with tests. |
| Web parity | Partial | Core timebanking/social/mobile commerce workflows exist. Web-only/admin/caring areas are excluded. Several web features remain missing or intentionally deferred for native. | Use the matrix below as the implementation queue. |
| Verification | Complete for this pass | `npm run type-check` and full `npm test -- --runInBand` passed after dependency and wrapper changes. | Keep warning cleanup as a separate Jest/Uniwind task. |

## HeroUI Native Documentation Checked

Official docs checked on 2026-05-29:

- HeroUI Native introduction: https://heroui.com/en/docs/native/getting-started
- HeroUI Native quick start: https://heroui.com/docs/native/getting-started/quick-start
- HeroUI Native provider: https://heroui.com/docs/native/getting-started/provider
- HeroUI Native theming: https://heroui.com/docs/native/getting-started/theming
- Components index: https://heroui.com/docs/native/components
- Button: https://heroui.com/docs/native/components/button
- Card: https://heroui.com/docs/native/components/card
- TextField/Input: https://heroui.com/docs/native/components/text-field and installed `input.md`
- Dialog: https://heroui.com/docs/native/components/dialog
- Chip: https://heroui.com/docs/native/components/chip
- Checkbox: https://heroui.com/docs/native/components/checkbox
- Switch: https://heroui.com/docs/native/components/switch
- Spinner: https://heroui.com/docs/native/components/spinner

## Parity Matrix

| Web area | Web route/page | Mobile route/screen | Status | HeroUI Native status | Notes | Next action |
| --- | --- | --- | --- | --- | --- | --- |
| Auth | `/login`, `/register` | `(auth)/login`, `(auth)/register`, `(auth)/select-tenant` | Complete | Partial | Uses HeroUI Card/Input/Button wrappers. Forgot/reset/verify email are not native screens yet. | Add forgot/reset email flows if product wants password recovery in-app. |
| Dashboard | `/dashboard` | `(tabs)/home` | Partial | Partial | Home acts as the mobile dashboard/feed hub. Web dashboard-specific panels are not all represented. | Add dashboard summary cards for wallet, upcoming events, open requests, and community health. |
| Feed | `/feed`, `/feed/posts/:id`, hashtags | `(tabs)/home`, `FeedItem` | Partial | Partial | Feed list is present; dedicated post detail and hashtag discovery routes are missing. | Add post detail and hashtag modal routes. |
| Listings | `/listings`, `/listings/:id`, create/edit | `(tabs)/exchanges`, `(modals)/exchange-detail`, create/edit exchange | Partial | Partial | Mobile models listings through timebank exchanges. Direct listing terminology/routes are absent. | Keep exchange-first mobile language unless separate marketplace/listings UX is required. |
| Exchanges | `/exchanges`, `/exchanges/:id`, request | `(tabs)/exchanges`, `(modals)/new-exchange`, `(modals)/edit-exchange`, `(modals)/exchange-detail` | Complete | Partial | Core exchange browse/detail/create/edit/request workflows exist; browse and create/edit text fields use the shared HeroUI Native-backed Input wrapper. | Continue detail/request form cleanup. |
| Messages | `/messages`, `/messages/:id` | `(tabs)/messages`, `(modals)/thread`, `(modals)/chat` | Complete | Partial | Threads, unread badges, realtime context, and AI chat route exist. | Continue standardizing composer controls and attachment/action sheets. |
| Wallet | `/wallet`, regional points | `(modals)/wallet` | Partial | Partial | Wallet balance/history/transfer/donation exist; regional points are not implemented. | Add regional points only if enabled for non-caring tenants. |
| Members | `/members`, `/profile/:id`, connections | `(tabs)/members`, `(modals)/member-profile`, `(modals)/connections`, federation connections | Complete core | Partial | Member directory, profile, direct member connections, and federation connections now exist. The direct connections route supports accepted, incoming, and sent workflows with profile/message routing. | Continue deeper profile add-ons only if enabled for mobile tenants. |
| Profile | `/profile/:id`, collections, appreciation wall | `(tabs)/profile`, `(modals)/edit-profile`, `(modals)/member-profile` | Partial | Partial | Profile/edit basics exist; collections/appreciation/Verein profile add-ons are absent. | Defer add-ons unless enabled for mobile tenants. |
| Settings | `/settings`, security/privacy/notifications/skills/translation | `(modals)/settings`, `(modals)/change-password`, `(modals)/verify-identity` | Partial | Partial | Core settings, password, and identity verification exist; data export, blocked users, connected accounts, translation are not full parity. | Add settings sub-routes as discrete modal screens. |
| Notifications | `/notifications` | `(modals)/notifications` | Complete | Partial | Notification list exists. | Confirm mark-read/delete parity during API QA. |
| Search | `/search`, explore | `(tabs)/search`, `(modals)/search` | Partial | Partial | Search exists; explore is represented through profile discovery shortcuts rather than a standalone route. | Add richer filters if API supports them on mobile. |
| Events | `/events`, `/events/:id`, create/edit/reminders | `(tabs)/events`, `(modals)/event-detail`, `(modals)/new-event`, `(modals)/edit-event` | Partial | Partial | Core events exist; reminder settings are missing. | Add reminder settings only after mobile notification UX is finalized. |
| Groups | `/groups`, `/groups/:id`, create/edit, rich tabs | `(tabs)/groups`, `(modals)/groups`, `(modals)/group-detail`, `(modals)/new-group`, `(modals)/edit-group` | Partial | Partial | Browse/detail/create exist. Web tabs for files, wiki, tasks, QA, analytics, media, etc. are not full parity. | Prioritize announcements, tasks, and files if needed for mobile. |
| Group exchanges | `/group-exchanges`, create/detail | `(modals)/group-exchanges`, `(modals)/group-exchange-detail` | Partial | Partial | List/detail exist; create is represented through exchange creation paths. | Add dedicated create group exchange route if usage requires it. |
| Goals | `/goals`, `/goals/:id` | `(modals)/goals` | Partial | Partial | Goals overview/create exists; detail/history/insights/reminders/templates are not full parity. | Add detail route and reminder toggle. |
| Gamification | `/leaderboard`, `/achievements`, `/nexus-score` | `(modals)/gamification` | Partial | Partial | Gamification hub covers badges/leaderboard; separate achievements/nexus score routes absent. | Split into dedicated screens if navigation depth becomes crowded. |
| Polls | `/polls` | `(modals)/polls`, `PollCard` | Partial | Partial | Native polls route now lists feed-backed polls with inline voting via `PollCard`. Advanced web features such as ranked polls, creation, export, and deletion remain deferred. | Add poll creation and ranked-poll support if those workflows become important on mobile. |
| Jobs | `/jobs`, detail, create/edit, analytics, alerts, applications, kanban, employer, talent, bias | `(modals)/jobs`, `job-detail`, `new-job`, `edit-job`, `job-analytics`, `job-pipeline` | Partial | Partial | Core jobs and management routes exist. Alerts, applications, employer brand, talent search, bias audit are missing. | Add alerts and applications first; defer employer/talent/bias if desktop-heavy. |
| Marketplace | Marketplace browse/detail/create/edit/orders/offers/pickups/coupons/seller/tools/search/map | Broad `(modals)/marketplace-*` coverage | Complete core / partial advanced | Partial | Strong native coverage exists, including tools, coupons, pickups, seller onboarding, orders, offers, map, search, collections. | Continue tests for smaller redirect routes and migrate form helpers. |
| Blog | `/blog`, `/blog/:slug` | `(modals)/blog`, `(modals)/blog-post` | Complete | Partial | Browse/detail exist; search now uses the shared HeroUI Native-backed Input wrapper. | None beyond remaining card/action cleanup. |
| Resources | `/resources`, `/kb`, `/kb/:slug`, `/help` | `(modals)/support` | Partial | Complete for hub | Support hub links to help, resources, about, contact, and legal pages on the web app. Native KB article browsing is not implemented. | Add native resource/KB API screens if offline/in-app reading becomes a priority. |
| Organisations | `/organisations`, detail, register | `(modals)/organisations`, `organisation-detail`, `new-organisation` | Complete | Partial | Core organisation flows exist. | Continue form wrapper migration. |
| Volunteering | `/volunteering`, create/detail, org dashboard, applications, donations, expenses, certificates, safeguarding, shifts | `(modals)/volunteering`, `volunteering-detail`, `new-volunteering`, `edit-volunteering` | Partial | Partial | Core opportunity and application flows exist. Web org-dashboard and advanced tabs are absent. | Add my organisations/org dashboard only if tenant usage needs native management. |
| Federation | `/federation/*` | `(modals)/federation*`, shared directory screen | Complete core | Partial | Hub, partners, members, listings, groups, events, messages, settings, onboarding, connections exist. | Keep re-export route tests documented; migrate internal raw inputs over time. |
| AI chat | `/chat` | `(modals)/chat` | Complete | Partial | Native chat route exists. | Confirm mobile tool-result cards parity later. |
| Ideation | `/ideation/*` | None | Missing | Not applicable yet | No mobile implementation. | Defer unless tenants need native challenge participation. |
| Skills | `/skills` | None | Missing | Not applicable yet | No mobile route. | Add only if profile/settings skill management becomes mobile priority. |
| Activity | `/activity` | Home/feed signals only | Missing | Not applicable yet | No dedicated activity dashboard. | Add as dashboard sub-screen if needed. |
| Matches/reviews | `/matches`, `/reviews` | Exchange/member detail surfaces | Partial | Partial | Dedicated route absent; related actions appear in context. | Add if reviews/matches need first-class mobile navigation. |
| Public/legal | `/about`, `/contact`, `/terms`, `/privacy`, `/cookies`, accessibility, trust/safety, legal hub | `(modals)/support` | Partial | Complete for hub | Support hub links to the canonical web support and legal pages. Full native legal document rendering is deferred. | Add native legal document rendering if offline or app-store review requirements demand it. |
| Premium/advertising/developer | `/premium`, `/advertise`, `/developers` | None | Not applicable | Not applicable | Desktop/web commercial and developer flows are intentionally not in mobile for now. | Keep out of scope unless mobile business requirement is added. |
| Admin/broker | `/admin/*`, `/broker/*` | None | Not applicable | Not applicable | Excluded by owner instruction. | Do not implement in mobile. |
| Caring community | `/caring-community/*`, `/caring/*` | None | Not applicable | Not applicable | Excluded by owner instruction. | Do not implement in mobile. |

## Route Files Without Dedicated UI

These files are redirects or re-exports and intentionally do not need HeroUI Native UI:

- `(tabs)/create.tsx`
- `(modals)/edit-event.tsx`
- `(modals)/edit-group.tsx`
- `(modals)/edit-job.tsx`
- `(modals)/edit-marketplace-listing.tsx`
- `(modals)/edit-volunteering.tsx`
- `(modals)/federation-events.tsx`
- `(modals)/federation-groups.tsx`
- `(modals)/federation-listings.tsx`
- `(modals)/federation-member.tsx`
- `(modals)/federation-members.tsx`
- `(modals)/federation-messages.tsx`
- `(modals)/federation-partners.tsx`
- `(modals)/federation-settings.tsx`
- `(modals)/groups.tsx`
- `(modals)/members.tsx`
- `(modals)/search.tsx`

## Remaining HeroUI Native Cleanup Queue

1. Continue replacing per-screen raw `TextInput` form helpers with `components/ui/Input`; exchange/member/group/blog search fields and exchange create/edit forms are complete.
2. Replace low-level `Pressable` controls that act as buttons/chips with `Button`, `Chip`, `ControlField`, `Switch`, or `Checkbox`.
3. Move manual `theme.text`/`theme.surface` styling to semantic Uniwind classes where it does not need tenant-specific runtime color.
4. Keep tenant primary color overrides only for brand-critical accents and document each exception locally.
5. Add route-level tests for marketplace redirect/helper routes that currently rely on broader tool-route tests.

## Verification Log

Commands run during this pass:

```bash
npm install heroui-native@latest
npm test -- components/ui/Button.test.tsx --runInBand
npm test -- components/ui/Input.test.tsx --runInBand
npm test -- support.test.tsx --runInBand
npm test -- polls.test.tsx --runInBand
npm test -- polls.test.tsx exchanges.test.tsx members.test.tsx groups.test.tsx --runInBand
npm test -- new-exchange.test.tsx edit-exchange.test.tsx components/ui/Input.test.tsx --runInBand
npm test -- connections.test.ts connections.test.tsx --runInBand
npm test -- blog.test.tsx components/ui/Input.test.tsx --runInBand
npm test -- --runInBand
npm run type-check
```

Observed status:

- `npm run type-check`: passed after the HeroUI Native update, shared wrapper edits, and docs updates.
- `npm test -- --runInBand`: passed. Jest still logs known HeroUI Native/Uniwind test-environment warnings about unresolved CSS variables and SVG gradient colors.
- `components/ui/Button.test.tsx`: passed.
- `components/ui/Input.test.tsx`: passed.
- `support.test.tsx`: passed.
- `polls.test.tsx`: passed.
- Focused polls/exchanges/members/groups route tests: passed.
- Focused new/edit exchange and Input wrapper tests: passed.
- Focused direct connections API/screen tests: passed.
- Focused blog and Input wrapper tests: passed.
- `npm install`: completed and reported 24 audit findings. They were not force-fixed because that would be a separate dependency/security remediation with possible breaking changes.
