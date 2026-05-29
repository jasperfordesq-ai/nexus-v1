<!--
Copyright © 2024–2026 Jasper Ford
SPDX-License-Identifier: AGPL-3.0-or-later
Author: Jasper Ford
See NOTICE file for attribution and acknowledgements.
-->

# HeroUI Native Parity Audit

Date: 2026-05-29

Scope: `mobile/` Expo app compared with the maintained web React frontend in `react-frontend/`.

Out of scope by owner instruction: React admin, broker/admin panels, legacy PHP views, production deployment tooling, and web-only marketing/admin operations. Product workflows remain in scope when they are useful on native mobile.

## Executive Status

| Area | Status | Notes | Next action |
| --- | --- | --- | --- |
| HeroUI Native package | Complete | `heroui-native` updated from `^1.0.3` to `^1.0.4`, the latest npm version checked during the audit. | Keep current during future Expo upgrades. |
| Provider setup | Complete | `app/_layout.tsx` imports `global.css`, wraps with `GestureHandlerRootView`, and mounts `HeroUINativeProvider`. | None. |
| Styling setup | Complete | `global.css` imports Tailwind CSS, Uniwind, HeroUI Native styles, and sources HeroUI Native library classes. Current official HeroUI Native theme sources use OKLCH variables, so the existing OKLCH brand overrides match upstream. | Continue moving screen code from manual theme colors to semantic class names. |
| Shared UI wrappers | Partial | Button loading now uses HeroUI Native `Spinner`; Input now uses `TextField`, `Label`, `Input`, and `FieldError`; FAB and pressable Card compatibility now use HeroUI Native `Button`; app and modal error-boundary recovery actions now use the shared HeroUI Native-backed `Button`; bottom-sheet style job and marketplace action modals now use the HeroUI Native-backed `BottomSheet`; marketplace listing inventory switches use `Toggle`; marketplace listing-card save/detail controls, auth password visibility toggles, the voice-message playback control, the shared image carousel image button, the exchange gallery thumbnail selector, and the volunteering search clear control use HeroUI Native icon buttons/buttons; organisation registration terms uses `Checkbox`; `ActionSheet` action rows, jobs tabs/retry/application actions, job browse cards, exchange list cards, notification body taps, wallet recipient search rows/transaction rows, federation hub quick-link/partner cards, federation directory partner/group/event/message/listing detail actions, story circles, thread context cards, member-profile listing rows, exchange author cards, group-detail tabs/event cards, group-exchange list cards, global search result cards, events list cards, groups list cards, marketplace collection/listing cards, message inbox rows/reaction/action controls, the messages swipe archive action, feed read-more action, volunteering opportunity actions/tabs/hours organisation selector chips, poll answer choices, exchange report reason chips/related listing pills, profile hub menu rows, member-card navigation wrappers, select-tenant option rows, blog-post list cards, settings action rows, organisation directory cards, and login forgot-password action use HeroUI Native-backed buttons; exchange/federation message/member profile transfer/profile-edit/group/blog/messages/global-search/organisation/jobs/detail application/identity verification/marketplace hub/category/advanced search/map coordinate/offer counter/detail/order/tool/listing form/shipping option/collection/seller onboarding fields, wallet action fields, goals create fields, volunteering browse/hours/detail application fields, endorsements skill entry, chat/thread composers, change-password fields, exchange detail request/comment/report fields, group detail discussion fields, and exchange/group/job/event/volunteering/organisation create/edit forms now use the shared Input wrapper. The native connections route uses HeroUI Native Card/Tabs/Chip/Button/Spinner/Surface primitives. | Continue migrating remaining low-level Pressable actions where they are not gesture/media surfaces; avoid wrapping cards that already contain nested action buttons. |
| Route-level HeroUI use | Partial | Most functional screens use HeroUI Native directly or through local UI wrappers. Redirect/re-export routes intentionally contain no UI. The entry loading state now uses HeroUI Native `Spinner`. Visible route-screen `TextInput` controls have been migrated to the shared HeroUI Native-backed `Input`; bottom-sheet style route modals have been migrated off React Native `Modal`; only type-only refs remain for keyboard flow. Several complex screens still use raw `Pressable` and manual color styling where a larger refactor is needed. | Incrementally migrate by feature area with tests. |
| Web parity | Partial | Core timebanking/social/mobile commerce workflows exist. Web-only/admin/broker panels are excluded. Several web features remain missing or intentionally deferred for native. | Use the matrix below as the implementation queue. |
| Verification | Complete for this pass | Focused route suites and `npm run type-check` pass after the wrapper changes. The latest full Jest run reported all 139 suites and 874 tests passed, then hit the command timeout during Jest shutdown, matching the known open-handle behavior seen in focused route tests. | Keep warning/open-handle cleanup as a separate Jest/Uniwind task. |

## HeroUI Native Documentation Checked

Official docs checked on 2026-05-29:

- HeroUI Native introduction: https://heroui.com/en/docs/native/getting-started
- HeroUI Native quick start: https://heroui.com/docs/native/getting-started/quick-start
- HeroUI Native provider: https://heroui.com/docs/native/getting-started/provider
- HeroUI Native theming: https://heroui.com/docs/native/getting-started/theming
- Components index: https://heroui.com/docs/native/components
- Button: https://heroui.com/docs/native/components/button
- Card: https://heroui.com/docs/native/components/card
- Tabs: installed HeroUI Native component docs
- TextField/Input: https://heroui.com/docs/native/components/text-field and installed `input.md`
- Dialog: https://heroui.com/docs/native/components/dialog
- Chip: https://heroui.com/docs/native/components/chip
- Checkbox: https://heroui.com/docs/native/components/checkbox
- Switch: https://heroui.com/docs/native/components/switch
- Spinner: https://heroui.com/docs/native/components/spinner

## Parity Matrix

| Web area | Web route/page | Mobile route/screen | Status | HeroUI Native status | Notes | Next action |
| --- | --- | --- | --- | --- | --- | --- |
| Auth | `/login`, `/register`, `/password/forgot`, `/password/reset`, `/verify-email` | `(auth)/login`, `(auth)/register`, `(auth)/select-tenant`, `(auth)/forgot-password`, `(auth)/reset-password`, `(auth)/verify-email` | Complete core | Partial | Uses HeroUI Card/Input/Button wrappers. Forgot-password, reset-password, and verify-email now use native HeroUI Native flows backed by `/api/auth/forgot-password`, `/api/auth/reset-password`, and `/api/auth/verify-email`. | Add OAuth callback/account-link handling only after mobile OAuth deep links are configured. |
| Dashboard | `/dashboard` | `(tabs)/home` | Complete core | Partial | Home acts as the mobile dashboard/feed hub and now includes HeroUI Native summary cards for wallet balance, upcoming events, open requests, and unread notifications. Web dashboard-specific analytics panels are not all represented. | Add deeper community health/analytics panels only if they become mobile-first workflows. |
| Feed | `/feed`, `/feed/posts/:id`, `/feed/hashtags`, `/feed/hashtag/:tag` | `(tabs)/home`, `(modals)/feed-item-detail`, `(modals)/feed-hashtags`, `(modals)/feed-hashtag`, `FeedItem` | Complete core | Partial | Feed list, native post/polymorphic item detail, hashtag discovery, and tagged-post hashtag detail routes are present; feed read-more/action controls use HeroUI Native buttons, and image carousel fallback accessibility labels are translated. | Continue advanced web-only feed moderation/report parity only where useful on mobile. |
| Listings | `/listings`, `/listings/:id`, create/edit | `(tabs)/exchanges`, `(modals)/exchange-detail`, create/edit exchange | Partial | Partial | Mobile models listings through timebank exchanges. Exchange detail author navigation now uses HeroUI Native-backed buttons. Direct listing terminology/routes are absent. | Keep exchange-first mobile language unless separate marketplace/listings UX is required. |
| Exchanges | `/exchanges`, `/exchanges/:id`, request | `(tabs)/exchanges`, `(modals)/new-exchange`, `(modals)/edit-exchange`, `(modals)/exchange-detail` | Complete | Partial | Core exchange browse/detail/create/edit/request workflows exist; browse, create/edit, detail request, comment, and report fields use the shared HeroUI Native-backed Input wrapper; exchange list cards, report reason chips, related listing pills, and author cards now use HeroUI Native buttons. | Continue media thumbnail cleanup only where it improves native behavior. |
| Messages | `/messages`, `/messages/:id` | `(tabs)/messages`, `(modals)/thread`, `(modals)/chat` | Complete | Partial | Threads, unread badges, realtime context, and AI chat route exist; inbox search plus thread and AI chat composers now use the shared HeroUI Native-backed Input wrapper, and thread context cards, message reaction/action controls, swipe archive, and voice-message playback now use HeroUI Native buttons. | Continue standardizing attachment/action sheets. |
| Wallet | `/wallet`, regional points | `(modals)/wallet` | Partial | Partial | Wallet balance/history/transfer/donation exist, transfer/donation action fields now use the shared HeroUI Native-backed Input wrapper, and recipient search result rows plus transaction rows use HeroUI Native-backed buttons; regional points are not implemented. | Add regional points only if enabled for non-caring tenants. |
| Members | `/members`, `/profile/:id`, connections | `(tabs)/members`, `(modals)/member-profile`, `(modals)/connections`, federation connections | Complete core | Partial | Member directory, profile, direct member connections, and federation connections now exist. The direct connections route supports accepted, incoming, and sent workflows with profile/message routing; member-profile listing navigation and federation transfer fields now use HeroUI Native-backed wrappers. | Continue deeper profile add-ons only if enabled for mobile tenants. |
| Profile | `/profile/:id`, collections, appreciation wall | `(tabs)/profile`, `(modals)/edit-profile`, `(modals)/member-profile` | Partial | Partial | Profile/edit basics exist, and edit-profile fields now use the shared HeroUI Native-backed Input wrapper. Collections/appreciation/Verein profile add-ons are absent. | Defer add-ons unless enabled for mobile tenants. |
| Settings | `/settings`, security/privacy/notifications/skills/translation | `(modals)/settings`, `(modals)/change-password`, `(modals)/verify-identity`, `(modals)/settings-blocked-users`, `(modals)/settings-data-export`, `(modals)/settings-translation`, `(modals)/skills` | Complete core / partial advanced | Partial | Core settings, password, privacy, notification, blocked-user management, data export request/history, translation/content preferences, identity verification, and profile skill management exist; change-password and identity DOB fields now use the shared HeroUI Native-backed Input wrapper, and skills route into the HeroUI Native skills/endorsements surface. Connected accounts are still deferred because mobile OAuth/deep-link account linking is not configured. | Add connected accounts only after mobile OAuth/deep-link support is required. |
| Notifications | `/notifications` | `(modals)/notifications` | Complete | Partial | Notification list exists with native tap-through, mark-all-read, explicit mark-read, and delete actions backed by the V2 notification APIs. Notification body taps and card actions use HeroUI Native buttons. | Consider grouped-notification parity only if grouped inbox behavior becomes important on mobile. |
| Search | `/search`, explore | `(tabs)/search`, `(modals)/search` | Partial | Partial | Search exists and now uses the shared HeroUI Native-backed Input wrapper plus HeroUI Native-backed result-card navigation; explore is represented through profile discovery shortcuts rather than a standalone route. | Add richer filters if API supports them on mobile. |
| Events | `/events`, `/events/:id`, create/edit/reminders/check-in/waitlist/polls | `(tabs)/events`, `(modals)/event-detail`, `(modals)/new-event`, `(modals)/edit-event` | Complete core | Partial | Core events exist, list-card navigation uses HeroUI Native-backed buttons, create/edit form fields use the shared HeroUI Native-backed Input wrapper, and event detail now supports attendee previews, event-linked poll voting, per-event reminder settings, waitlist join/leave, and organizer attendance/check-in backed by `/v2/events/{id}/attendees`, `/v2/polls?event_id=...`, `/v2/events/{id}/reminders`, `/v2/events/{id}/waitlist`, and `/v2/events/{id}/attendees/{attendeeId}/check-in`. The shared Laravel POST waitlist route was corrected to call `joinWaitlist`. Global reminder preferences remain a web placeholder and are not implemented natively. | Add richer mobile event operations such as attendee messaging only if native organizers need it. |
| Groups | `/groups`, `/groups/:id`, create/edit, rich tabs | `(tabs)/groups`, `(modals)/groups`, `(modals)/group-detail`, `(modals)/new-group`, `(modals)/edit-group` | Partial | Partial | Browse/detail/create exist, browse list-card navigation uses HeroUI Native-backed buttons, create/edit plus detail discussion/announcement/Q&A/wiki/task fields now use the shared HeroUI Native-backed Input wrapper, and group-detail tabs/event cards/announcement admin actions/file download/delete/media/Q&A/wiki/task/analytics actions now use HeroUI Native buttons. Native group announcements now support list, create, pin/unpin, and delete via `/v2/groups/{id}/announcements`; native group files now support member-only listing, download links, and admin delete via `/v2/groups/{id}/files`; native media now supports member gallery listing, image/video filtering, photo/video upload, URL open, and admin delete via `/v2/groups/{id}/media`; native Q&A now supports member-only question listing, ask, detail expansion, answers, question/answer voting, and group-admin/question-asker answer acceptance via `/v2/groups/{id}/questions`, `/v2/groups/{id}/qa/vote`, and `/v2/groups/{id}/answers/{answerId}/accept`; native wiki now supports member page listing, read, create, edit, revision history, and admin delete via `/v2/groups/{id}/wiki`; native tasks now support stats, status filtering, create, inline status cycling, priority updates, and assignment updates via `/v2/groups/{id}/tasks` and `/v2/team-tasks/{id}`; native analytics now supports an admin-only dashboard for overview, activity, contributors, content performance, retention cohorts, and group comparison via `/v2/groups/{id}/analytics`, `/retention`, and `/comparative`. Web tabs for document file upload and CSV analytics exports are not full parity. | Prioritize mobile document-picker file upload and authenticated export/download UX only if native collaboration needs more depth. |
| Group exchanges | `/group-exchanges`, create/detail | `(modals)/group-exchanges`, `(modals)/group-exchange-detail` | Partial | Partial | List/detail exist, with list-card navigation migrated to HeroUI Native-backed buttons; create is represented through exchange creation paths. | Add dedicated create group exchange route if usage requires it. |
| Goals | `/goals`, `/goals/:id` | `(modals)/goals`, `(modals)/goal-detail` | Complete core / partial discovery | Partial | Goals overview/create plus native detail now exist. The detail route loads goal detail, progress history, insights, milestones, and reminders from the V2 goals APIs; owner progress updates use the shared HeroUI Native-backed Input wrapper. The goals route now includes a HeroUI Native template picker backed by `/v2/goals/templates`, `/v2/goals/templates/categories`, and `/v2/goals/from-template/{templateId}`. Web discovery/mentoring depth is not full parity. | Add goal discovery/mentoring only if mobile goal coaching becomes a priority. |
| Gamification | `/leaderboard`, `/achievements`, `/nexus-score` | `(modals)/gamification`, `(modals)/achievements`, `(modals)/leaderboard`, `(modals)/nexus-score` | Complete practical parity | Partial | Gamification hub covers profile XP, badges, badge showcase management, leaderboard, daily reward claim, challenges/reward claims, badge collection journeys, XP shop browse/purchase, and a native Nexus Score breakdown backed by `/v2/gamification/profile`, `/v2/gamification/badges`, `/v2/gamification/showcase`, `/v2/gamification/leaderboard`, `/v2/gamification/daily-reward`, `/v2/gamification/challenges`, `/v2/gamification/collections`, `/v2/gamification/shop`, and `/v2/gamification/nexus-score`. Web-equivalent route aliases now open the same HeroUI Native hub on the relevant tab. | Continue polish only: split tabs into separate screens if the hub becomes crowded. |
| Polls | `/polls` | `(modals)/polls`, `PollCard` | Partial | Partial | Native polls route now lists feed-backed polls with inline voting via `PollCard`; unvoted answer choices now use HeroUI Native buttons while result rows keep custom animated native views. Advanced web features such as ranked polls, creation, export, and deletion remain deferred. | Add poll creation and ranked-poll support if those workflows become important on mobile. |
| Jobs | `/jobs`, detail, create/edit, analytics, alerts, applications, kanban, employer, talent, bias | `(modals)/jobs`, `job-detail`, `new-job`, `edit-job`, `job-analytics`, `job-pipeline` | Complete core / partial advanced | Partial | Core jobs and management routes exist. Browse, my applications, my postings, applicant history/withdraw, owner pipeline/analytics, and saved job-alert workflows are native; browse search, detail application, job alert creation fields, and create/edit form fields now use the shared HeroUI Native-backed Input wrapper. The apply sheet uses the shared BottomSheet wrapper, and browse cards plus tabs/retry/interview/offer/application/alert actions use HeroUI Native buttons. Employer brand, talent search, and bias audit remain desktop-heavy gaps. | Defer employer/talent/bias unless mobile-first; add richer applicant messaging only when native message deep-link context is finalized. |
| Marketplace | Marketplace browse/detail/create/edit/orders/offers/pickups/coupons/seller/tools/search/map | Broad `(modals)/marketplace-*` coverage | Complete core / partial advanced | Partial | Strong native coverage exists, including tools, coupons, pickups, seller onboarding, orders, offers, map, search, collections. Hub, category, advanced search, map coordinate, offer counter, detail, order, tool, listing form, shipping option, collection creation, and seller onboarding fields now use the shared HeroUI Native-backed Input wrapper; detail/order/tool/collection/coupon action sheets now use the shared BottomSheet wrapper; listing inventory switches now use the shared Toggle wrapper; listing-card save/detail controls and collection-card navigation now use HeroUI Native-backed buttons. | Continue tests for smaller redirect routes and non-input action cleanup. |
| Blog | `/blog`, `/blog/:slug` | `(modals)/blog`, `(modals)/blog-post` | Complete | Partial | Browse/detail exist; search now uses the shared HeroUI Native-backed Input wrapper. | None beyond remaining card/action cleanup. |
| Resources | `/resources`, `/kb`, `/kb/:slug`, `/help` | `(modals)/support`, `(modals)/resources`, `(modals)/kb-article` | Complete core | Partial | Support hub links to help/about/contact/legal web pages and routes resources into a native HeroUI Native resource/knowledge screen. Native resources browse/search/category filtering and KB article detail are backed by `/v2/resources`, `/v2/resources/categories`, `/v2/kb`, and `/v2/kb/{id}`. | Keep upload/admin/resource-management actions on web; add offline article/resource caching only if mobile usage needs it. |
| Organisations | `/organisations`, detail, register | `(modals)/organisations`, `organisation-detail`, `new-organisation` | Complete | Partial | Core organisation flows exist; directory search and registration form fields now use the shared HeroUI Native-backed Input wrapper. | Continue detail/action cleanup. |
| Volunteering | `/volunteering`, create/detail, org dashboard, applications, donations, expenses, certificates, safeguarding, shifts | `(modals)/volunteering`, `volunteering-detail`, `new-volunteering`, `edit-volunteering` | Partial | Partial | Core opportunity, application, shift schedule, certificates, expenses, donations/giving-days, and hours flows exist. Browse search, hours logging, expense/donation submission, detail application, and create/edit opportunity form fields now use the shared HeroUI Native-backed Input wrapper; opportunity card actions, workflow tabs, confirmed shift cards/cancel actions, certificate cards/generate/open actions, expense cards/type selectors/submit actions, donation campaign selectors/history cards/submit actions, search clear, and hours organisation selector chips now use HeroUI Native buttons. Web org-dashboard and safeguarding tabs are absent; Stripe/payment-sheet donation checkout remains deferred in favor of the existing pledge/offline donation API path. | Add my organisations/org dashboard only if tenant usage needs native management; add mobile payment-sheet depth only if app-store donation/payment requirements need it. |
| Federation | `/federation/*` | `(modals)/federation*`, shared directory screen | Complete core | Partial | Hub, partners, members, listings, groups, events, messages, settings, onboarding, connections exist; message reply and compose fields use the shared HeroUI Native-backed Input wrapper, and hub quick-link/partner cards plus directory partner/group/event/message cards now use HeroUI Native-backed buttons. | Keep re-export route tests documented; leave listing cards with nested actions/media as native touch surfaces until a larger detail-card refactor is worthwhile. |
| AI chat | `/chat` | `(modals)/chat` | Complete | Partial | Native chat route exists with starters, message limits, source chips, and HeroUI Native tool-result cards for structured AI tool invocations. | Add feedback voting only if the mobile API/client flow needs trace-based response rating. |
| Ideation | `/ideation/*` | `(modals)/ideation`, `(modals)/ideation-detail` | Complete core / partial management | Partial | Native challenge browse/detail now exists behind the `ideation_challenges` feature gate, with status/category/search filtering, idea list sorting, idea submission, and idea voting backed by `/v2/ideation-challenges`, `/v2/ideation-categories`, `/v2/ideation-challenges/{id}/ideas`, and `/v2/ideation-ideas/{id}/vote`. Admin challenge creation/editing, campaigns, outcomes, templates, and rich media management remain web workflows. | Keep admin/campaign/outcome management on web unless challenge facilitation becomes a mobile workflow. |
| Skills | `/skills` | `(modals)/skills`, `(modals)/endorsements` | Complete core / partial advanced | Partial | Mobile now has a first-class skills route that reuses the native HeroUI Native skills and endorsements management surface backed by `/v2/users/me/skills`; the profile shortcut routes to it. The skills surface now includes a HeroUI Native Discover tab backed by `/v2/skills/categories`, `/v2/skills/categories/{id}`, and `/v2/skills/members` for category browsing, category skill drill-down, and members-by-skill profile routing. Advanced web discovery and profile skill-management depth remains compacted for mobile. | Add richer filters/recommendations only if skills discovery becomes a primary mobile workflow. |
| Activity | `/activity` | `(modals)/activity`, home/feed signals | Partial | Partial | Native activity dashboard now exists, backed by `/v2/users/me/activity/dashboard`, with HeroUI Native summary cards for hours, connections, posts, skills, and a recent timeline. The home feed still provides the ambient activity stream. Web monthly chart depth is intentionally compacted for mobile. | Add richer charting only if mobile users need analytics-depth review. |
| Matches/reviews | `/matches`, `/reviews` | `(modals)/matches`, `(modals)/reviews`, exchange/member detail surfaces | Complete core | Partial | Native matches route is backed by `/v2/matches/all`, with type tabs, summary cards, source routing, and listing dismiss actions. Native reviews route is backed by `/v2/reviews/user/{id}`, `/v2/reviews/pending`, `/v2/reviews`, and `/v2/reviews/{id}` for received/given/pending workflows, write-review form, and own-review deletion. | Keep deeper social interaction panels contextual unless likes/comments on reviews become a mobile-first workflow. |
| Public/legal | `/about`, `/contact`, `/terms`, `/privacy`, `/cookies`, accessibility, trust/safety, legal hub | `(modals)/support` | Partial | Complete for hub | Support hub links to the canonical web support and legal pages. Full native legal document rendering is deferred. | Add native legal document rendering if offline or app-store review requirements demand it. |
| Premium/advertising/developer | `/premium`, `/advertise`, `/developers` | None | Not applicable | Not applicable | Desktop/web commercial and developer flows are intentionally not in mobile for now. | Keep out of scope unless mobile business requirement is added. |
| Admin/broker | `/admin/*`, `/broker/*` | None | Not applicable | Not applicable | Excluded by owner instruction. | Do not implement in mobile. |
| Caring/workflows | `/caring-community/*`, `/caring/*`, workflow-oriented surfaces | None | Deferred pending product fit | Not applicable yet | Owner clarified to focus on workflows rather than the caring-community label. No native route has been added in this pass. | Reassess concrete workflow screens against tenant needs before implementation. |

## Route Files Without Dedicated UI

These files are redirects or re-exports and intentionally do not need HeroUI Native UI:

- `(tabs)/create.tsx`
- `(modals)/edit-event.tsx`
- `(modals)/edit-group.tsx`
- `(modals)/edit-job.tsx`
- `(modals)/edit-marketplace-listing.tsx`
- `(modals)/edit-volunteering.tsx`
- `(modals)/achievements.tsx`
- `(modals)/federation-events.tsx`
- `(modals)/federation-groups.tsx`
- `(modals)/federation-listings.tsx`
- `(modals)/federation-member.tsx`
- `(modals)/federation-members.tsx`
- `(modals)/federation-messages.tsx`
- `(modals)/federation-partners.tsx`
- `(modals)/federation-settings.tsx`
- `(modals)/groups.tsx`
- `(modals)/leaderboard.tsx`
- `(modals)/members.tsx`
- `(modals)/nexus-score.tsx`
- `(modals)/search.tsx`

## Remaining HeroUI Native Cleanup Queue

1. Keep visible route-screen text entry on `components/ui/Input`; current non-doc `rg` findings for `TextInput` are the shared `Input` wrapper itself plus type-only refs in `endorsements.tsx` and `new-exchange.tsx` used for keyboard focus.
2. Keep bottom-sheet/dialog style flows on `components/ui/BottomSheet` or a future `Dialog` wrapper; direct React Native `Modal` use is no longer present in non-test route code.
3. Replace low-level `Pressable` controls that act as buttons/chips with `Button`, `Chip`, `ControlField`, `Toggle`, or `Checkbox`; keep image/media controls and gesture surfaces as native press targets unless a wrapper improves behavior. Current production `Pressable` usage is limited to feed double-tap/media composition; remaining test-only `Pressable` mocks are not product UI.
4. Move manual `theme.text`/`theme.surface` styling to semantic Uniwind classes where it does not need tenant-specific runtime color.
5. Keep tenant primary color overrides only for brand-critical accents and document each exception locally.
6. Keep marketplace redirect/helper route tests current when adding new seller-tool aliases; saved searches, promotions, pickup slots/scan, seller onboarding aliases, sales orders, and coupon edit/redemption helpers now have focused route coverage.

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
npm test -- volunteering.test.tsx volunteering-detail.test.tsx --runInBand
npm run type-check
npm test -- messages.test.tsx --runInBand
npm run type-check
npm test -- home.test.tsx polls.test.tsx --runInBand
npm run type-check
node -e "const fs=require('fs'); for (const f of fs.readdirSync('locales').map(l=>'locales/'+l+'/common.json')) JSON.parse(fs.readFileSync(f,'utf8')); console.log('common locale JSON ok')"
npm test -- home.test.tsx polls.test.tsx --runInBand
npm run type-check
npm test -- components/ui/ImageCarousel.test.tsx --runInBand
npm run type-check
npm test -- --runInBand
npm test -- feed-item-detail.test.tsx --runInBand
npm run type-check
npm test -- home.test.tsx polls.test.tsx --runInBand
node -e "const fs=require('fs'); for (const f of ['locales/en/home.json','locales/en/common.json']) JSON.parse(fs.readFileSync(f,'utf8')); console.log('changed locale JSON ok')"
npm run type-check
node -e "const fs=require('fs'); for (const l of fs.readdirSync('locales')) { JSON.parse(fs.readFileSync('locales/'+l+'/home.json','utf8')); } console.log('home locale JSON ok')"
npm test -- feed-hashtags.test.tsx feed-hashtag.test.tsx feed-item-detail.test.tsx --runInBand
npm run type-check
node -e "const fs=require('fs'); for (const l of fs.readdirSync('locales')) { JSON.parse(fs.readFileSync('locales/'+l+'/auth.json','utf8')); } console.log('auth locale JSON ok')"
npm test -- login.test.tsx forgot-password.test.tsx --runInBand
npm run type-check
npm test -- --runInBand
npm run type-check
node -e "const fs=require('fs'); for (const l of fs.readdirSync('locales')) { JSON.parse(fs.readFileSync('locales/'+l+'/home.json','utf8')); } console.log('home locale JSON ok')"
npm test -- home.test.tsx --runInBand
npm test -- jobs.test.tsx --runInBand
npm test -- --runInBand
npm run type-check
npm test -- settings.test.tsx settings-blocked-users.test.tsx settings-data-export.test.tsx --runInBand
npm test -- --runInBand
npm test -- settings.test.tsx settings-translation.test.tsx --runInBand
npm test -- --runInBand
npm test -- goals.test.tsx goal-detail.test.tsx --runInBand
npm test -- --runInBand --silent
npm run type-check
node -e "const fs=require('fs'); for (const l of fs.readdirSync('locales')) JSON.parse(fs.readFileSync('locales/'+l+'/jobs.json','utf8')); console.log('jobs locale JSON ok')"
npm test -- jobs.test.tsx --runInBand --silent
npm test -- --runInBand --silent
npm run type-check
node -e "const fs=require('fs'); for (const l of fs.readdirSync('locales')) JSON.parse(fs.readFileSync('locales/'+l+'/jobs.json','utf8')); console.log('jobs locale JSON ok')"
npm test -- jobs.test.tsx --runInBand --silent
npm test -- --runInBand --silent
npm run type-check
node -e "const fs=require('fs'); for (const l of fs.readdirSync('locales')) JSON.parse(fs.readFileSync('locales/'+l+'/events.json','utf8')); console.log('events locale JSON ok')"
npm test -- event-detail.test.tsx events.test.ts --runInBand --silent
npm run type-check
node -e "const fs=require('fs'); for (const l of fs.readdirSync('locales')) { for (const f of fs.readdirSync('locales/'+l).filter(f=>f.endsWith('.json'))) JSON.parse(fs.readFileSync('locales/'+l+'/'+f,'utf8')); } console.log('all locale JSON ok')"
git diff --check -- mobile
npm test -- --runInBand --silent
npm run type-check
node -e "const fs=require('fs'); for (const l of fs.readdirSync('locales')) JSON.parse(fs.readFileSync('locales/'+l+'/notifications.json','utf8')); console.log('notifications locale JSON ok')"
npm test -- notifications.test.tsx notifications.test.ts --runInBand --silent
npm run type-check
node -e "const fs=require('fs'); for (const l of fs.readdirSync('locales')) { for (const f of fs.readdirSync('locales/'+l).filter(f=>f.endsWith('.json'))) JSON.parse(fs.readFileSync('locales/'+l+'/'+f,'utf8')); } console.log('all locale JSON ok')"
git diff --check -- mobile
npm test -- --runInBand --silent
npm run type-check
npm test -- skills.test.tsx profile.test.tsx --runInBand --silent
npm run type-check
node -e "const fs=require('fs'); for (const l of fs.readdirSync('locales')) { JSON.parse(fs.readFileSync('locales/'+l+'/home.json','utf8')); JSON.parse(fs.readFileSync('locales/'+l+'/profile.json','utf8')); } console.log('home/profile locale JSON ok')"
npm test -- activity.test.ts activity.test.tsx profile.test.tsx --runInBand --silent
npm run type-check
node -e "const fs=require('fs'); for (const l of fs.readdirSync('locales')) { for (const f of fs.readdirSync('locales/'+l).filter(f=>f.endsWith('.json'))) JSON.parse(fs.readFileSync('locales/'+l+'/'+f,'utf8')); } console.log('all locale JSON ok')"
git diff --check -- mobile
npm run type-check
npm test -- --runInBand --silent
npm test -- matches.test.ts matches.test.tsx profile.test.tsx --runInBand --silent
npm run type-check
node -e "const fs=require('fs'); for (const l of fs.readdirSync('locales')) { for (const f of fs.readdirSync('locales/'+l).filter(f=>f.endsWith('.json'))) JSON.parse(fs.readFileSync('locales/'+l+'/'+f,'utf8')); } console.log('locale JSON ok')"
git diff --check -- mobile
npm test -- --runInBand --silent
npm test -- reviews.test.ts reviews.test.tsx profile.test.tsx --runInBand --silent
npm run type-check
node -e "const fs=require('fs'); for (const l of fs.readdirSync('locales')) JSON.parse(fs.readFileSync('locales/'+l+'/profile.json','utf8')); console.log('profile locale JSON ok')"
node -e "const fs=require('fs'); for (const l of fs.readdirSync('locales')) { for (const f of fs.readdirSync('locales/'+l).filter(f=>f.endsWith('.json'))) JSON.parse(fs.readFileSync('locales/'+l+'/'+f,'utf8')); } console.log('locale JSON ok')"
git diff --check -- mobile
npm test -- --runInBand --silent
npm test -- auth.test.ts reset-password.test.tsx forgot-password.test.tsx --runInBand --silent
npm run type-check
node -e "const fs=require('fs'); for (const l of fs.readdirSync('locales')) JSON.parse(fs.readFileSync('locales/'+l+'/auth.json','utf8')); console.log('auth locale JSON ok')"
node -e "const fs=require('fs'); for (const l of fs.readdirSync('locales')) { for (const f of fs.readdirSync('locales/'+l).filter(f=>f.endsWith('.json'))) JSON.parse(fs.readFileSync('locales/'+l+'/'+f,'utf8')); } console.log('locale JSON ok')"
git diff --check -- mobile
npm test -- --runInBand --silent
npm test -- auth.test.ts verify-email.test.tsx reset-password.test.tsx --runInBand --silent
npm run type-check
node -e "const fs=require('fs'); for (const l of fs.readdirSync('locales')) JSON.parse(fs.readFileSync('locales/'+l+'/auth.json','utf8')); console.log('auth locale JSON ok')"
node -e "const fs=require('fs'); for (const l of fs.readdirSync('locales')) { for (const f of fs.readdirSync('locales/'+l).filter(f=>f.endsWith('.json'))) JSON.parse(fs.readFileSync('locales/'+l+'/'+f,'utf8')); } console.log('locale JSON ok')"
git diff --check -- mobile
npm test -- --runInBand --silent
npm test -- resources.test.ts resources.test.tsx kb-article.test.tsx support.test.tsx --runInBand --silent
npm test -- ideation.test.ts ideation.test.tsx ideation-detail.test.tsx profile.test.tsx --runInBand --silent
npm run type-check
node -e "const fs=require('fs'); for (const l of fs.readdirSync('locales')) { for (const f of fs.readdirSync('locales/'+l).filter(f=>f.endsWith('.json'))) JSON.parse(fs.readFileSync('locales/'+l+'/'+f,'utf8')); } console.log('locale JSON ok')"
npm test -- profile.test.tsx --runInBand --silent
npm run type-check
npm test -- members.test.tsx member-profile.test.tsx profile.test.tsx --runInBand --silent
npm run type-check
npm test -- select-tenant.test.tsx login.test.tsx register.test.tsx --runInBand --silent
npm run type-check
npm test -- blog.test.tsx --runInBand --silent
npm run type-check
npm test -- settings.test.tsx settings-blocked-users.test.tsx settings-data-export.test.tsx settings-translation.test.tsx --runInBand --silent
npm run type-check
npm test -- organisations.test.tsx organisation-detail.test.tsx new-organisation.test.tsx --runInBand --silent
npm run type-check
npm test -- group-exchanges.test.tsx group-exchange-detail.test.tsx --runInBand --silent
npm run type-check
npm test -- search.test.tsx --runInBand --silent
npm run type-check
npm test -- events.test.tsx groups.test.tsx --runInBand --silent
npm run type-check
npm test -- marketplace-collections.test.tsx --runInBand --silent
npm run type-check
npm test -- jobs.test.tsx --runInBand --silent
npm run type-check
npm test -- notifications.test.tsx --runInBand --silent
npm run type-check
npm test -- --runInBand --silent
node -e "const fs=require('fs'); for (const l of fs.readdirSync('locales')) { for (const f of fs.readdirSync('locales/'+l).filter(f=>f.endsWith('.json'))) JSON.parse(fs.readFileSync('locales/'+l+'/'+f,'utf8')); } console.log('locale JSON ok')"
git diff --check -- mobile
npm test -- wallet.test.tsx --runInBand --silent
npm run type-check
npm test -- group-detail.test.tsx --runInBand --silent
npm run type-check
npm test -- thread.test.tsx chat.test.tsx --runInBand --silent
npm run type-check
npm test -- member-profile.test.tsx --runInBand --silent
npm run type-check
npm test -- exchange-detail.test.tsx --runInBand --silent
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
- Focused thread/messages tests covering reaction, message action, voice playback, and inbox archive controls: passed.
- Focused volunteering tests covering tabs, hours organisation selector chips, and search clear controls: passed.
- Focused home/feed and polls tests covering the feed read-more action and poll cards: passed.
- Common locale JSON parse check after adding `aria.carouselImage`: passed.
- Focused ImageCarousel tests covering translated fallback labels and explicit alt text: passed.
- Full `npm test -- --runInBand` after the latest feed/carousel/i18n changes: passed with the known HeroUI Native/Uniwind/colorKit/act warning noise.
- Focused feed-item detail tests covering native post detail rendering and feed module gating: passed.
- Focused home/feed and polls tests after post/poll detail routing: passed.
- Home locale JSON parse across all mobile locales after hashtag additions: passed.
- Focused feed hashtag discovery/detail tests covering trending hashtags, route navigation, tagged feed posts, and feed module gating: passed.
- Auth locale JSON parse across all mobile locales after forgot-password additions: passed.
- Focused login/forgot-password tests covering native forgot-password routing, reset-link request, validation, success, and login return navigation: passed.
- Full `npm test -- --runInBand` after native forgot-password and feed hashtag additions: passed with the known HeroUI Native/Uniwind/colorKit/act warning noise.
- Focused home tests covering the new dashboard summary cards: passed with the known HeroUI Native/Uniwind/colorKit/act warning noise.
- Focused settings tests covering blocked-user management, data export request/history, and settings navigation: passed with the known HeroUI Native/Uniwind warning noise.
- Full `npm test -- --runInBand` after settings blocked-user and data-export additions: passed with the known HeroUI Native/Uniwind/colorKit/act warning noise.
- Focused settings translation tests covering feed ordering, auto-translation target locale, save payload, and local language switch: passed with the known HeroUI Native/Uniwind warning noise.
- Full `npm test -- --runInBand` after settings translation additions: passed with the known HeroUI Native/Uniwind/colorKit/act warning noise.
- Focused goals tests covering native goal detail, progress history, insights, milestones, reminder enable/disable, progress updates, and list-to-detail navigation: passed with the known HeroUI Native/Uniwind warning noise.
- Full `npm test -- --runInBand --silent` after native goal detail additions: passed, 124 suites and 821 tests.
- Focused jobs tests covering the native job-alert workflow, alert creation payloads, pause/delete actions, and the existing jobs tabs: passed.
- Jobs locale JSON parse across all mobile locales after alert additions: passed.
- Full `npm test -- --runInBand --silent` after native job-alert additions: passed, 124 suites and 824 tests.
- Focused jobs tests covering applicant cover-message expansion, application history loading, withdraw actions, job alerts, and existing jobs tabs: passed.
- Full `npm test -- --runInBand --silent` after applicant-side jobs parity additions: passed, 115 suites and 805 tests.
- Focused event detail/API tests covering native per-event reminder loading and update payloads: passed.
- Events locale JSON parse across all mobile locales after reminder additions: passed.
- Full `npm test -- --runInBand --silent` after native event reminder additions: passed, 124 suites and 828 tests.
- Focused notification screen/API tests covering explicit mark-read, delete, tap-through read, and V2 delete helper: passed.
- Notifications locale JSON parse across all mobile locales after action additions: passed.
- Full `npm test -- --runInBand --silent` after native notification action additions: passed, 124 suites and 832 tests.
- `npm run type-check` after app/modal error-boundary recovery actions moved to the shared HeroUI Native-backed Button wrapper: passed.
- Focused skills/profile tests covering the native first-class skills route alias and profile shortcut: passed, 4 suites and 37 tests.
- `npm run type-check` after adding the first-class skills route: passed.
- Home/profile locale JSON parse checks after adding activity labels: passed.
- Focused activity API/screen/profile tests covering the native activity dashboard route and profile shortcut: passed, 5 suites and 38 tests.
- `npm run type-check` after adding the native activity dashboard: passed.
- All locale JSON parse checks after activity additions: passed.
- `git diff --check -- mobile` after activity additions: passed with LF-to-CRLF warnings only.
- Full `npm test -- --runInBand --silent` after skills/activity additions: passed, 127 suites and 835 tests.
- Focused matches API/screen/profile tests covering the native matches route, type tabs, source navigation, dismiss payloads, empty/error states, and profile shortcut: passed, 5 suites and 41 tests.
- `npm run type-check` after adding the native matches route: passed.
- All locale JSON parse checks after matches additions: passed.
- `git diff --check -- mobile` after matches additions: passed with LF-to-CRLF warnings only.
- Full `npm test -- --runInBand --silent` after matches additions: passed, 129 suites and 840 tests.
- Focused reviews API/screen/profile tests covering the native reviews route, received/given/pending tabs, write-review payloads, own-review delete action, and profile shortcut: passed, 5 suites and 42 tests.
- `npm run type-check` after adding the native reviews route: passed.
- Profile locale JSON parse checks after reviews additions: passed.
- All locale JSON parse checks after reviews additions: passed.
- `git diff --check -- mobile` after reviews additions: passed with LF-to-CRLF warnings only.
- Full `npm test -- --runInBand --silent` after reviews additions: passed, 131 suites and 846 tests.
- Focused auth API/forgot-password/reset-password tests covering reset token payloads, form validation, success state, and invalid-link state: passed, 3 suites and 15 tests, with the existing Jest open-handle shutdown warning.
- `npm run type-check` after adding the native reset-password route: passed.
- Auth locale JSON parse checks after reset-password additions: passed.
- All locale JSON parse checks after reset-password additions: passed.
- `git diff --check -- mobile` after reset-password additions: passed with LF-to-CRLF warnings only.
- Full `npm test -- --runInBand --silent` after reset-password additions: passed, 132 suites and 850 tests.
- Focused auth API/verify-email/reset-password tests covering email verification token payloads, success, invalid-link, and failed verification states: passed, 3 suites and 16 tests, with the existing Jest open-handle shutdown warning.
- `npm run type-check` after adding the native verify-email route: passed.
- Auth locale JSON parse checks after verify-email additions: passed.
- All locale JSON parse checks after verify-email additions: passed.
- `git diff --check -- mobile` after verify-email additions: passed with LF-to-CRLF warnings only.
- Full `npm test -- --runInBand --silent` after verify-email additions: passed, 133 suites and 854 tests.
- Focused resources/KB API, native resources screen, native KB article detail, and support-hub routing tests: passed, 4 suites and 9 tests, with the existing Jest open-handle shutdown warning.
- `npm run type-check` after native resources/KB additions: passed.
- All locale JSON parse checks after adding the `resources` namespace: passed.
- `git diff --check -- mobile` after native resources/KB additions: passed with LF-to-CRLF warnings only.
- Full `npm test -- --runInBand --silent` after native resources/KB additions: passed, 136 suites and 861 tests.
- Focused ideation API, challenge browse, challenge detail, idea submission/voting, and profile shortcut tests: passed, 6 suites and 40 tests.
- `npm run type-check` after native ideation additions: passed on rerun; a first invocation exited without diagnostics, and both `npx tsc --noEmit --pretty false` plus a second `npm run type-check` passed.
- All locale JSON parse checks after adding the `ideation` namespace and profile shortcut keys: passed.
- `git diff --check -- mobile` after native ideation additions: passed with LF-to-CRLF warnings only.
- Full `npm test -- --runInBand --silent` after native ideation additions: passed, 139 suites and 865 tests.
- Focused profile hub tests after migrating menu rows from raw `Pressable` to HeroUI Native `Button`: passed, 3 suites and 36 tests.
- `npm run type-check` after profile menu row migration: passed.
- Focused members/member-profile/profile tests after migrating `MemberCard` navigation from raw `Pressable` to HeroUI Native `Button`: passed, 6 suites and 50 tests, with the existing Jest open-handle shutdown warning.
- `npm run type-check` after `MemberCard` wrapper migration: passed.
- Focused select-tenant/login/register tests after migrating tenant option rows from raw `Pressable` to HeroUI Native `Button`: passed, 3 suites and 23 tests.
- `npm run type-check` after select-tenant row migration: passed.
- Focused blog route tests after migrating blog list cards from raw `Pressable` to HeroUI Native `Button`: passed, 1 suite and 7 tests.
- `npm run type-check` after blog card migration: passed.
- Focused settings route tests after migrating settings action rows from raw `Pressable` to HeroUI Native `Button`: passed, 5 suites and 18 tests.
- `npm run type-check` after settings action row migration: passed.
- Focused organisations/detail/register tests after migrating organisation directory cards from raw `Pressable` to HeroUI Native `Button`: passed, 3 suites and 20 tests.
- `npm run type-check` after organisation directory card migration: passed.
- Focused group-exchanges/detail tests after migrating group-exchange list cards from raw `Pressable` to HeroUI Native `Button`: passed, 2 suites and 6 tests.
- `npm run type-check` after group-exchange list-card migration: passed.
- Focused search route tests after migrating global search result cards from raw `Pressable` to HeroUI Native `Button`: passed, 2 suites and 9 tests because the Jest pattern also matched marketplace search.
- `npm run type-check` after global search result-card migration: passed.
- Focused events/groups route tests after migrating event and group list cards from raw `Pressable` to HeroUI Native `Button`: passed, 3 suites and 20 tests because the Jest pattern also matched federation groups/events.
- `npm run type-check` after event/group list-card migration: passed.
- Focused marketplace collections tests after migrating collection rows from raw `Pressable` to HeroUI Native `Button`: passed, 1 suite and 5 tests.
- `npm run type-check` after marketplace collection-row migration: passed.
- Focused jobs route tests after migrating browse job cards from raw `Pressable` to HeroUI Native `Button`: passed, 1 suite and 16 tests.
- `npm run type-check` after job-card migration: passed.
- Focused notifications route tests after migrating notification body taps from raw `Pressable` to HeroUI Native `Button`: passed, 1 suite and 11 tests.
- `npm run type-check` after notification body-tap migration: passed.
- Full `npm test -- --runInBand --silent` after the latest HeroUI Native button-wrapper pass: reported 139 suites and 874 tests passed, then hit the command timeout during Jest shutdown; this matches the known open-handle shutdown behavior seen in focused route tests.
- Latest all-locale JSON parse after the wrapper pass: passed.
- Latest `git diff --check -- mobile` after the wrapper pass: passed with LF-to-CRLF warnings only.
- Focused wallet route tests after migrating recipient search rows and transaction rows from raw `Pressable` to HeroUI Native `Button`: passed, 1 suite and 12 tests.
- `npm run type-check` after wallet recipient/transaction-row migration: passed.
- Focused group-detail tests after migrating group event cards from raw `Pressable` to HeroUI Native `Button`: passed, 1 suite and 9 tests, with the existing Jest open-handle shutdown warning.
- `npm run type-check` after group event-card migration: passed.
- Focused thread/chat tests after migrating thread context cards from raw `Pressable` to HeroUI Native `Button` and removing an unused chat `Pressable` import: passed, 2 suites and 20 tests.
- `npm run type-check` after thread context-card migration: passed.
- Focused member-profile tests after migrating member listing rows from raw `Pressable` to HeroUI Native `Button`: passed, 1 suite and 14 tests.
- `npm run type-check` after member-profile listing-row migration: passed.
- Focused exchange-detail tests after migrating author cards from raw `Pressable` to HeroUI Native `Button`: passed, 2 suites and 10 tests because the Jest pattern also matched group-exchange detail.
- `npm run type-check` after exchange author-card migration: passed.
- Focused federation hub/groups-events/messages tests after migrating hub quick-link/partner cards and directory group/event/message cards from raw `Pressable` to HeroUI Native `Button`: passed, 3 suites and 18 tests.
- Focused federation members/listings tests after migrating directory partner cards and preserving nested listing-card touch behavior: passed, 2 suites and 7 tests.
- `npm run type-check` after the federation and wallet row migrations: passed.
- Latest all-locale JSON parse after the federation/wallet/story row migrations: passed.
- Latest `git diff --check -- mobile` after the federation/wallet/story row migrations: passed with LF-to-CRLF warnings only.
- Focused StoryCircles component test after migrating story targets from raw `Pressable` to HeroUI Native `Button`: passed, 1 suite and 1 test.
- `npm run type-check` after the StoryCircles migration: passed.
- Focused ExchangeCard component test after migrating exchange list cards from raw `Pressable` to HeroUI Native `Button`: passed, 1 suite and 1 test.
- `npm run type-check` after the ExchangeCard migration: passed.
- Focused marketplace helper-route, ExchangeCard, and StoryCircles tests after adding coupon edit/redemption redirect coverage: passed, 3 suites and 11 tests.
- `npm run type-check` after the marketplace helper-route coverage update: passed.
- `mobile/README.md` was refreshed to document Expo SDK 54, HeroUI Native `^1.0.4`, Uniwind/Tailwind 4 setup, local native API URLs on port 8088, wrapper policy, and verification commands.
- `git diff --check -- mobile` after the README refresh and latest code/docs updates: passed with LF-to-CRLF warnings only.
- `npm run type-check` after the README refresh: passed.
- Focused marketplace helper-route, ExchangeCard, and StoryCircles tests after the README refresh: passed, 3 suites and 11 tests.
- Focused chat screen/API tests after adding native AI tool-result cards: passed, 2 suites and 13 tests.
- Chat locale JSON parse after adding tool-result labels: passed.
- `npm run type-check` after adding native AI tool-result cards: passed.
- Focused event API/detail tests after adding attendee previews and organizer attendance/check-in management: passed, 4 suites and 41 tests because the Jest pattern also matched events/federation event route tests.
- Events locale JSON parse across all mobile locales after adding attendee preview/check-in labels: passed.
- `npm run type-check` after adding attendee previews and organizer attendance/check-in management: passed.
- Focused event API/detail tests after adding waitlist join/leave controls and the shared waitlist route correction: passed, 4 suites and 45 tests because the Jest pattern also matched events/federation event route tests.
- Events locale JSON parse across all mobile locales after adding waitlist labels: passed.
- `npm run type-check` after adding event waitlist controls: passed.
- Focused event API/detail tests after adding event-linked poll loading and voting: passed, 4 suites and 48 tests because the Jest pattern also matched events/federation event route tests.
- Events locale JSON parse across all mobile locales after adding event poll labels: passed.
- `npm run type-check` after adding event-linked polls: passed.
- Focused volunteering tests after removing the raw `Pressable` opportunity-card wrapper and keeping opportunity actions on HeroUI Native buttons: passed, 2 suites and 14 tests because the Jest pattern also matched new-volunteering.
- Volunteering locale JSON parse across all mobile locales after adding opportunity action accessibility labels: passed.
- `npm run type-check` after the volunteering opportunity-card action migration: passed.
- Focused exchange detail tests after migrating listing image gallery thumbnails from raw `Pressable` to HeroUI Native icon buttons: passed, 2 suites and 10 tests because the Jest pattern also matched group-exchange detail.
- `npm run type-check` after the exchange gallery thumbnail migration: passed.
- Focused messages tests after migrating inbox rows from raw `Pressable` to HeroUI Native buttons while preserving swipe archive actions: passed, 2 suites and 25 tests because the Jest pattern also matched federation messages.
- `npm run type-check` after the messages inbox-row migration: passed.
- Focused shared image carousel and home feed tests after migrating carousel image buttons from raw `Pressable` to HeroUI Native buttons: passed, 2 suites and 12 tests, with the known Jest open-handle shutdown warning.
- `npm run type-check` after the shared image carousel migration: passed.
- Focused federation listings tests after removing the raw outer `Pressable` wrapper from listing cards and keeping detail navigation on a HeroUI Native button: passed, 1 suite and 5 tests.
- `npm run type-check` after the federation listing-card action migration: passed.
- Focused marketplace browse/category/search/map/my-listings tests after removing the raw outer `Pressable` from marketplace listing cards and adding an explicit HeroUI Native detail button: passed, 5 suites and 9 tests.
- Marketplace locale JSON parse after adding the listing detail accessibility label: passed.
- Focused shared UI wrapper tests after migrating the compatibility Card pressable mode to a HeroUI Native button: passed, 3 suites and 9 tests.
- `npm run type-check` after marketplace listing-card and Card wrapper migrations: passed.
- Focused volunteering screen/API tests after adding the native My Shifts tab and shift cancel helper: passed, 3 suites and 25 tests because the Jest pattern also matched new-volunteering.
- Volunteering locale JSON parse across all mobile locales after adding shift labels: passed.
- `npm run type-check` after the native volunteering shifts workflow: passed.
- Focused volunteering screen/API tests after adding the native Certificates tab and certificate list/generate helpers: passed, 3 suites and 28 tests because the Jest pattern also matched new-volunteering.
- Volunteering locale JSON parse across all mobile locales after adding certificate labels: passed.
- `npm run type-check` after the native volunteering certificates workflow: passed.
- Focused volunteering screen/API tests after adding the native Expenses tab and expense list/submit helpers: passed, 3 suites and 31 tests because the Jest pattern also matched new-volunteering.
- Volunteering locale JSON parse across all mobile locales after adding expense labels: passed.
- `npm run type-check` after the native volunteering expenses workflow: passed.
- Focused volunteering screen/API tests after adding the native Donations tab, giving-day cards, donation history, and donation submit helpers: passed, 3 suites and 35 tests because the Jest pattern also matched new-volunteering.
- All mobile locale JSON parse checks after adding donation labels across locale files: passed.
- `npm run type-check` after the native volunteering donations workflow: passed.
- Focused group detail/API tests after adding native group announcement create, pin/unpin, and delete helpers: passed, 3 suites and 33 tests because the Jest pattern also matched the groups tab route; Jest printed the known open-handle shutdown warning after completion.
- All mobile locale JSON parse checks after adding group announcement management labels: passed.
- `npm run type-check` after the native group announcement workflow: passed.
- Focused group detail/API tests after adding the native group Files tab and group file API helper: passed, 3 suites and 35 tests.
- All mobile locale JSON parse checks after adding group files labels: passed.
- `npm run type-check` after the native group files workflow: passed.
- Focused group detail/API tests after adding the native group Q&A tab, ask flow, question detail expansion, answer submission, and Q&A API helpers: passed, 3 suites and 41 tests.
- All mobile locale JSON parse checks after adding group Q&A labels: passed.
- `npm run type-check` after the native group Q&A workflow: passed.
- Focused group detail/API tests after adding the native group Wiki tab, page listing/detail, create/edit flows, and wiki API helpers: passed, 3 suites and 47 tests, with the known Jest open-handle shutdown warning after completion.
- All mobile locale JSON parse checks after adding group wiki labels: passed.
- `npm run type-check` after the native group wiki workflow: passed.
- `git diff --check -- mobile routes/api.php` after the group wiki workflow: passed with LF-to-CRLF warnings only.
- Focused group detail/API tests after adding the native group Tasks tab, stats, status filters, create flow, inline status cycling, and task API helpers: passed, 3 suites and 55 tests.
- All mobile locale JSON parse checks after adding group task labels: passed.
- `npm run type-check` after the native group tasks workflow: passed.
- `git diff --check -- mobile routes/api.php` after the group tasks workflow: passed with LF-to-CRLF warnings only.
- Focused group detail/API tests after adding admin file delete actions and the group file delete API helper: passed, 3 suites and 57 tests.
- All mobile locale JSON parse checks after adding group file delete labels: passed.
- `npm run type-check` after the native group file delete workflow: passed.
- `git diff --check -- mobile routes/api.php` after the group file delete workflow: passed with LF-to-CRLF warnings only.
- Focused group detail/API tests after adding the native group Media tab, image/video filtering, URL open actions, admin media delete, and group media API helpers: passed, 3 suites and 62 tests.
- All mobile locale JSON parse checks after adding group media labels: passed.
- `npm run type-check` after the native group media workflow: passed.
- `git diff --check -- mobile routes/api.php` after the group media workflow: passed with LF-to-CRLF warnings only.
- Focused group detail/API tests after adding the native group Analytics tab, admin-only dashboard, day-window selector, and group analytics API helper: passed, 3 suites and 64 tests.
- All mobile locale JSON parse checks after adding group analytics labels: passed.
- `npm run type-check` after the native group analytics workflow: passed.
- `git diff --check -- mobile routes/api.php` after the native group analytics workflow: passed with LF-to-CRLF warnings only.
- Focused group detail/API tests after adding native group Q&A question/answer voting and group-admin answer acceptance helpers: passed, 3 suites and 67 tests.
- All mobile locale JSON parse checks after adding Q&A vote/accept labels: passed.
- `npm run type-check` after the native Q&A vote/accept workflow: passed.
- Focused group detail/API tests after adding native group wiki revision history and admin delete helpers: passed, 3 suites and 70 tests.
- All mobile locale JSON parse checks after adding wiki revision/delete labels: passed.
- `npm run type-check` after the native wiki revision/delete workflow: passed.
- Focused group detail/API tests after adding native group photo/video media upload through Expo ImagePicker and multipart helper: passed, 3 suites and 72 tests.
- All mobile locale JSON parse checks after adding group media upload labels: passed.
- `npm run type-check` after the native group media upload workflow: passed.
- Focused group detail/API tests after adding inline native task priority and assignment update controls: passed, 3 suites and 74 tests.
- `npm run type-check` after the native task priority/assignment workflow: passed.
- Focused group detail/API tests after adding question-asker Q&A answer acceptance: passed, 3 suites and 75 tests.
- `npm run type-check` after question-asker Q&A answer acceptance: passed.
- Focused group detail/API tests after adding native group analytics retention and comparative sections: passed, 3 suites and 77 tests.
- `npm run type-check` after native group analytics retention/comparison: passed.
- Full `npm test -- --runInBand --silent` after the group parity additions: reported 141 suites and 955 tests passed, then hit the command timeout during Jest shutdown; this is consistent with the known open-handle behavior already documented for full-suite runs.
- Focused goals route/API tests after adding the native goal template picker: passed, 2 suites and 22 tests.
- All mobile goal locale JSON parse checks after adding goal template labels: passed.
- `npm run type-check` after the native goal template picker: passed.
- Focused endorsements/skills route/API tests after adding native skill category discovery: passed, 3 suites and 23 tests.
- All mobile endorsements locale JSON parse checks after adding skill discovery labels: passed.
- `npm run type-check` after native skill category discovery: passed.
- Focused endorsements/skills route/API tests after adding native category skill drill-down and members-by-skill discovery: passed, 3 suites and 26 tests.
- All mobile endorsements locale JSON parse checks after adding members-by-skill labels: passed.
- `npm run type-check` after native members-by-skill discovery: passed.
- `git diff --check -- mobile routes/api.php` after native members-by-skill discovery: passed with LF-to-CRLF warnings only.
- Focused gamification route/API tests after adding native Nexus Score and route aliases: passed, 2 suites and 17 tests.
- Focused gamification route/API tests after adding native daily reward status and claim flow: passed, 2 suites and 20 tests.
- Focused gamification route/API tests after adding native challenges and challenge reward claims: passed, 2 suites and 23 tests.
- Focused gamification route/API tests after adding native badge collection journeys: passed, 2 suites and 25 tests.
- Focused gamification route/API tests after adding native XP shop browse/purchase: passed, 2 suites and 29 tests.
- Focused gamification route/API tests after adding native badge showcase management: passed, 2 suites and 31 tests.
- All mobile gamification locale JSON parse checks after adding Nexus Score, daily reward, challenge, journey, shop, and showcase labels: passed.
- `npm run type-check` after native Nexus Score route parity: passed.
- `npm run type-check` after native daily reward parity: passed.
- `npm run type-check` after native challenge parity: passed.
- `npm run type-check` after native badge collection journeys: passed.
- `npm run type-check` after native XP shop parity: passed.
- `npm run type-check` after native badge showcase management: passed.
- Checkpoint commit is currently blocked by local `.git` ACLs: `git add -- mobile` fails with `fatal: Unable to create 'C:/platforms/htdocs/staging/.git/index.lock': Permission denied`.
- `npm install`: completed and reported 24 audit findings. They were not force-fixed because that would be a separate dependency/security remediation with possible breaking changes.
