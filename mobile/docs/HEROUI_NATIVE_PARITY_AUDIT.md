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
| Shared UI wrappers | Partial | Button loading now uses HeroUI Native `Spinner`; Input now uses `TextField`, `Label`, `Input`, and `FieldError`; FAB now uses HeroUI Native `Button`; bottom-sheet style job and marketplace action modals now use the HeroUI Native-backed `BottomSheet`; marketplace listing inventory switches use `Toggle`; marketplace listing-card save controls and auth password visibility toggles use HeroUI Native icon buttons; organisation registration terms uses `Checkbox`; `ActionSheet` action rows, jobs tabs/retry/application actions, group-detail tabs, message reaction/action controls, volunteering tabs/hours organisation selector chips, poll answer choices, exchange report reason chips/related listing pills, and login forgot-password action use HeroUI Native-backed buttons; exchange/federation message/member profile transfer/profile-edit/group/blog/messages/global-search/organisation/jobs/detail application/identity verification/marketplace hub/category/advanced search/map coordinate/offer counter/detail/order/tool/listing form/shipping option/collection/seller onboarding fields, wallet action fields, goals create fields, volunteering browse/hours/detail application fields, endorsements skill entry, chat/thread composers, change-password fields, exchange detail request/comment/report fields, group detail discussion fields, and exchange/group/job/event/volunteering/organisation create/edit forms now use the shared Input wrapper. The native connections route uses HeroUI Native Card/Tabs/Chip/Button/Spinner/Surface primitives. | Continue migrating remaining low-level Pressable actions where they are not card/list-row or gesture surfaces. |
| Route-level HeroUI use | Partial | Most functional screens use HeroUI Native directly or through local UI wrappers. Redirect/re-export routes intentionally contain no UI. The entry loading state now uses HeroUI Native `Spinner`. Visible route-screen `TextInput` controls have been migrated to the shared HeroUI Native-backed `Input`; bottom-sheet style route modals have been migrated off React Native `Modal`; only type-only refs remain for keyboard flow. Several complex screens still use raw `Pressable` and manual color styling where a larger refactor is needed. | Incrementally migrate by feature area with tests. |
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
| Exchanges | `/exchanges`, `/exchanges/:id`, request | `(tabs)/exchanges`, `(modals)/new-exchange`, `(modals)/edit-exchange`, `(modals)/exchange-detail` | Complete | Partial | Core exchange browse/detail/create/edit/request workflows exist; browse, create/edit, detail request, comment, and report fields use the shared HeroUI Native-backed Input wrapper, and report reason chips plus related listing pills now use HeroUI Native buttons. | Continue non-input detail/action cleanup. |
| Messages | `/messages`, `/messages/:id` | `(tabs)/messages`, `(modals)/thread`, `(modals)/chat` | Complete | Partial | Threads, unread badges, realtime context, and AI chat route exist; inbox search plus thread and AI chat composers now use the shared HeroUI Native-backed Input wrapper, and message reaction/action controls now use HeroUI Native buttons. | Continue standardizing attachment/action sheets. |
| Wallet | `/wallet`, regional points | `(modals)/wallet` | Partial | Partial | Wallet balance/history/transfer/donation exist, and transfer/donation action fields now use the shared HeroUI Native-backed Input wrapper; regional points are not implemented. | Add regional points only if enabled for non-caring tenants. |
| Members | `/members`, `/profile/:id`, connections | `(tabs)/members`, `(modals)/member-profile`, `(modals)/connections`, federation connections | Complete core | Partial | Member directory, profile, direct member connections, and federation connections now exist. The direct connections route supports accepted, incoming, and sent workflows with profile/message routing; federation transfer fields now use the shared HeroUI Native-backed Input wrapper. | Continue deeper profile add-ons only if enabled for mobile tenants. |
| Profile | `/profile/:id`, collections, appreciation wall | `(tabs)/profile`, `(modals)/edit-profile`, `(modals)/member-profile` | Partial | Partial | Profile/edit basics exist, and edit-profile fields now use the shared HeroUI Native-backed Input wrapper. Collections/appreciation/Verein profile add-ons are absent. | Defer add-ons unless enabled for mobile tenants. |
| Settings | `/settings`, security/privacy/notifications/skills/translation | `(modals)/settings`, `(modals)/change-password`, `(modals)/verify-identity` | Partial | Partial | Core settings, password, and identity verification exist; change-password and identity DOB fields now use the shared HeroUI Native-backed Input wrapper. Data export, blocked users, connected accounts, translation are not full parity. | Add settings sub-routes as discrete modal screens. |
| Notifications | `/notifications` | `(modals)/notifications` | Complete | Partial | Notification list exists. | Confirm mark-read/delete parity during API QA. |
| Search | `/search`, explore | `(tabs)/search`, `(modals)/search` | Partial | Partial | Search exists and now uses the shared HeroUI Native-backed Input wrapper; explore is represented through profile discovery shortcuts rather than a standalone route. | Add richer filters if API supports them on mobile. |
| Events | `/events`, `/events/:id`, create/edit/reminders | `(tabs)/events`, `(modals)/event-detail`, `(modals)/new-event`, `(modals)/edit-event` | Partial | Partial | Core events exist, and create/edit form fields now use the shared HeroUI Native-backed Input wrapper; reminder settings are missing. | Add reminder settings only after mobile notification UX is finalized. |
| Groups | `/groups`, `/groups/:id`, create/edit, rich tabs | `(tabs)/groups`, `(modals)/groups`, `(modals)/group-detail`, `(modals)/new-group`, `(modals)/edit-group` | Partial | Partial | Browse/detail/create exist, create/edit plus detail discussion fields now use the shared HeroUI Native-backed Input wrapper, and group-detail tabs now use HeroUI Native buttons. Web tabs for files, wiki, tasks, QA, analytics, media, etc. are not full parity. | Prioritize announcements, tasks, and files if needed for mobile. |
| Group exchanges | `/group-exchanges`, create/detail | `(modals)/group-exchanges`, `(modals)/group-exchange-detail` | Partial | Partial | List/detail exist; create is represented through exchange creation paths. | Add dedicated create group exchange route if usage requires it. |
| Goals | `/goals`, `/goals/:id` | `(modals)/goals` | Partial | Partial | Goals overview/create exists, and create-goal fields now use the shared HeroUI Native-backed Input wrapper; detail/history/insights/reminders/templates are not full parity. | Add detail route and reminder toggle. |
| Gamification | `/leaderboard`, `/achievements`, `/nexus-score` | `(modals)/gamification` | Partial | Partial | Gamification hub covers badges/leaderboard; separate achievements/nexus score routes absent. | Split into dedicated screens if navigation depth becomes crowded. |
| Polls | `/polls` | `(modals)/polls`, `PollCard` | Partial | Partial | Native polls route now lists feed-backed polls with inline voting via `PollCard`; unvoted answer choices now use HeroUI Native buttons while result rows keep custom animated native views. Advanced web features such as ranked polls, creation, export, and deletion remain deferred. | Add poll creation and ranked-poll support if those workflows become important on mobile. |
| Jobs | `/jobs`, detail, create/edit, analytics, alerts, applications, kanban, employer, talent, bias | `(modals)/jobs`, `job-detail`, `new-job`, `edit-job`, `job-analytics`, `job-pipeline` | Partial | Partial | Core jobs and management routes exist; browse search, detail application, and create/edit form fields now use the shared HeroUI Native-backed Input wrapper, the apply sheet now uses the shared BottomSheet wrapper, and tabs plus retry/interview/offer application actions now use HeroUI Native buttons. Alerts, applications, employer brand, talent search, bias audit are missing. | Add alerts and applications first; defer employer/talent/bias if desktop-heavy. |
| Marketplace | Marketplace browse/detail/create/edit/orders/offers/pickups/coupons/seller/tools/search/map | Broad `(modals)/marketplace-*` coverage | Complete core / partial advanced | Partial | Strong native coverage exists, including tools, coupons, pickups, seller onboarding, orders, offers, map, search, collections. Hub, category, advanced search, map coordinate, offer counter, detail, order, tool, listing form, shipping option, collection creation, and seller onboarding fields now use the shared HeroUI Native-backed Input wrapper; detail/order/tool/collection/coupon action sheets now use the shared BottomSheet wrapper; listing inventory switches now use the shared Toggle wrapper; listing-card save controls now use HeroUI Native icon buttons. | Continue tests for smaller redirect routes and non-input action cleanup. |
| Blog | `/blog`, `/blog/:slug` | `(modals)/blog`, `(modals)/blog-post` | Complete | Partial | Browse/detail exist; search now uses the shared HeroUI Native-backed Input wrapper. | None beyond remaining card/action cleanup. |
| Resources | `/resources`, `/kb`, `/kb/:slug`, `/help` | `(modals)/support` | Partial | Complete for hub | Support hub links to help, resources, about, contact, and legal pages on the web app. Native KB article browsing is not implemented. | Add native resource/KB API screens if offline/in-app reading becomes a priority. |
| Organisations | `/organisations`, detail, register | `(modals)/organisations`, `organisation-detail`, `new-organisation` | Complete | Partial | Core organisation flows exist; directory search and registration form fields now use the shared HeroUI Native-backed Input wrapper. | Continue detail/action cleanup. |
| Volunteering | `/volunteering`, create/detail, org dashboard, applications, donations, expenses, certificates, safeguarding, shifts | `(modals)/volunteering`, `volunteering-detail`, `new-volunteering`, `edit-volunteering` | Partial | Partial | Core opportunity and application flows exist, and browse search, hours logging, detail application, and create/edit opportunity form fields now use the shared HeroUI Native-backed Input wrapper; workflow tabs and hours organisation selector chips now use HeroUI Native buttons. Web org-dashboard and advanced tabs are absent. | Add my organisations/org dashboard only if tenant usage needs native management. |
| Federation | `/federation/*` | `(modals)/federation*`, shared directory screen | Complete core | Partial | Hub, partners, members, listings, groups, events, messages, settings, onboarding, connections exist; message reply and compose fields now use the shared HeroUI Native-backed Input wrapper. | Keep re-export route tests documented and continue non-input action cleanup. |
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

1. Keep visible route-screen text entry on `components/ui/Input`; current non-doc `rg` findings for `TextInput` are the shared `Input` wrapper itself plus type-only refs in `endorsements.tsx` and `new-exchange.tsx` used for keyboard focus.
2. Keep bottom-sheet/dialog style flows on `components/ui/BottomSheet` or a future `Dialog` wrapper; direct React Native `Modal` use is no longer present in non-test route code.
3. Replace low-level `Pressable` controls that act as buttons/chips with `Button`, `Chip`, `ControlField`, `Toggle`, or `Checkbox`; keep navigation cards, list rows, image/media controls, and gesture surfaces as native press targets unless a wrapper improves behavior.
4. Move manual `theme.text`/`theme.surface` styling to semantic Uniwind classes where it does not need tenant-specific runtime color.
5. Keep tenant primary color overrides only for brand-critical accents and document each exception locally.
6. Add route-level tests for marketplace redirect/helper routes that currently rely on broader tool-route tests.

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
npm test -- messages.test.tsx components/ui/Input.test.tsx --runInBand
npm test -- search.test.tsx components/ui/Input.test.tsx --runInBand
npm test -- change-password.test.tsx components/ui/Input.test.tsx --runInBand
npm test -- index.test.tsx --runInBand
npm test -- organisations.test.tsx components/ui/Input.test.tsx --runInBand
npm test -- jobs.test.tsx components/ui/Input.test.tsx --runInBand
npm test -- marketplace.test.tsx components/ui/Input.test.tsx --runInBand
npm test -- marketplace-category.test.tsx marketplace-search.test.tsx marketplace.test.tsx components/ui/Input.test.tsx --runInBand
npm test -- marketplace-map.test.tsx marketplace-category.test.tsx marketplace-search.test.tsx components/ui/Input.test.tsx --runInBand
npm test -- wallet.test.tsx components/ui/Input.test.tsx --runInBand
npm test -- new-group.test.tsx new-job.test.tsx components/ui/Input.test.tsx --runInBand
npm test -- new-event.test.tsx new-volunteering.test.tsx components/ui/Input.test.tsx --runInBand
npm test -- goals.test.tsx components/ui/Input.test.tsx --runInBand
npm test -- new-organisation.test.tsx components/ui/Input.test.tsx --runInBand
npm test -- marketplace-shipping-options.test.tsx components/ui/Input.test.tsx --runInBand
npm test -- marketplace-collections.test.tsx components/ui/Input.test.tsx --runInBand
npm test -- marketplace-merchant-onboarding.test.tsx components/ui/Input.test.tsx --runInBand
npm test -- edit-profile.test.tsx components/ui/Input.test.tsx --runInBand
npm test -- verify-identity.test.tsx components/ui/Input.test.tsx --runInBand
npm test -- marketplace-offers.test.tsx components/ui/Input.test.tsx --runInBand
npm test -- job-detail.test.tsx components/ui/Input.test.tsx --runInBand
npm test -- volunteering.test.tsx components/ui/Input.test.tsx --runInBand
npm test -- volunteering-detail.test.tsx components/ui/Input.test.tsx --runInBand
npm test -- new-marketplace-listing.test.tsx components/ui/Input.test.tsx --runInBand
npm test -- member-profile.test.tsx components/ui/Input.test.tsx --runInBand
npm test -- exchange-detail.test.tsx components/ui/Input.test.tsx --runInBand
npm test -- group-detail.test.tsx components/ui/Input.test.tsx --runInBand
npm test -- marketplace-detail.test.tsx components/ui/Input.test.tsx --runInBand
npm test -- marketplace-orders.test.tsx components/ui/Input.test.tsx --runInBand
npm test -- marketplace-tools.test.tsx components/ui/Input.test.tsx --runInBand
npm test -- endorsements.test.tsx thread.test.tsx chat.test.tsx components/ui/Input.test.tsx --runInBand
npm test -- federation-messages.test.tsx federation-members.test.tsx components/ui/Input.test.tsx --runInBand
npm test -- marketplace-collections.test.tsx marketplace-tools.test.tsx components/ui/Input.test.tsx --runInBand
npm test -- marketplace-detail.test.tsx marketplace-orders.test.tsx marketplace-collections.test.tsx marketplace-tools.test.tsx --runInBand
npm test -- job-detail.test.tsx components/ui/Input.test.tsx --runInBand
npm test -- new-marketplace-listing.test.tsx new-organisation.test.tsx --runInBand
npm test -- jobs.test.tsx job-detail.test.tsx --runInBand
npm test -- components/ui/ActionSheet.test.tsx components/ui/Button.test.tsx --runInBand
npm test -- volunteering.test.tsx volunteering-detail.test.tsx --runInBand
npm run type-check
npm test -- polls.test.tsx --runInBand
npm test -- home.test.tsx polls.test.tsx --runInBand
npm run type-check
npm test -- exchange-detail.test.tsx components/ui/Input.test.tsx --runInBand
npm run type-check
npm test -- exchange-detail.test.tsx --runInBand
npm run type-check
npm test -- marketplace.test.tsx marketplace-category.test.tsx marketplace-search.test.tsx --runInBand
npm run type-check
npm test -- login.test.tsx register.test.tsx select-tenant.test.tsx --runInBand
npm test -- login.test.tsx register.test.tsx --runInBand
npm run type-check
npm test -- group-detail.test.tsx --runInBand
npm run type-check
npm test -- thread.test.tsx messages.test.tsx --runInBand
npm run type-check
npm test -- jobs.test.tsx --runInBand
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
- Focused messages and Input wrapper tests: passed.
- Focused global search and Input wrapper tests: passed.
- Focused change-password and Input wrapper tests: passed.
- Focused entry route Spinner test: passed.
- Focused organisations and Input wrapper tests: passed.
- Focused jobs and Input wrapper tests: passed.
- Focused marketplace hub and Input wrapper tests: passed.
- Focused marketplace category/search/hub and Input wrapper tests: passed.
- Focused marketplace map/category/search and Input wrapper tests: passed.
- Focused wallet and Input wrapper tests: passed.
- Focused new-group/new-job and Input wrapper tests: passed.
- Focused new-event/new-volunteering and Input wrapper tests: passed.
- Focused goals and Input wrapper tests: passed.
- Focused new-organisation and Input wrapper tests: passed.
- Focused marketplace shipping options and Input wrapper tests: passed.
- Focused marketplace collections and Input wrapper tests: passed.
- Focused marketplace merchant onboarding and Input wrapper tests: passed.
- Focused edit-profile and Input wrapper tests: passed.
- Focused verify-identity and Input wrapper tests: passed.
- Focused marketplace offers and Input wrapper tests: passed.
- Focused job detail and Input wrapper tests: passed.
- Focused volunteering and Input wrapper tests: passed.
- Focused volunteering detail and Input wrapper tests: passed.
- Focused new marketplace listing and Input wrapper tests: passed.
- Focused member profile and Input wrapper tests: passed.
- Focused exchange detail and Input wrapper tests: passed.
- Focused group detail and Input wrapper tests: passed.
- Focused marketplace detail and Input wrapper tests: passed.
- Focused marketplace orders and Input wrapper tests: passed.
- Focused marketplace tools and Input wrapper tests: passed.
- Focused endorsements/thread/chat composer and Input wrapper tests: passed.
- Focused federation messages/member and Input wrapper tests: passed.
- Focused marketplace collection/tools BottomSheet and Input wrapper tests: passed.
- Focused marketplace detail/orders/collections/tools BottomSheet tests: passed.
- Focused job detail BottomSheet and Input wrapper tests: passed.
- Focused marketplace listing Toggle and organisation Checkbox tests: passed.
- Focused jobs tabs/retry/application-button and job detail tests: passed.
- Focused ActionSheet and Button wrapper tests: passed.
- Focused volunteering tabs, hours organisation selector chips, and detail tests: passed.
- Focused polls route tests and home/feed route tests covering PollCard usage: passed.
- Focused exchange detail report/input and related-listing button tests: passed.
- Focused marketplace hub/category/search tests covering listing-card save controls: passed.
- Focused login/register auth tests covering password visibility icon buttons: passed. The combined login/register/select-tenant run reported all suites passed but hit the shell timeout during Jest shutdown, so login/register were rerun separately with a clean exit.
- Focused group-detail tests covering the tab strip: passed.
- Focused thread/messages tests covering reaction and message action controls: passed.
- `npm install`: completed and reported 24 audit findings. They were not force-fixed because that would be a separate dependency/security remediation with possible breaking changes.
