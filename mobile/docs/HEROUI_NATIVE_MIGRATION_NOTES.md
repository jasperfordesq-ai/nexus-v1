<!--
Copyright © 2024–2026 Jasper Ford
SPDX-License-Identifier: AGPL-3.0-or-later
Author: Jasper Ford
See NOTICE file for attribution and acknowledgements.
-->

# HeroUI Native Migration Notes

Date: 2026-05-29

## Current Foundation

The mobile app is on Expo SDK 54 with React 19, Expo Router 5, Tailwind CSS 4, Uniwind, and HeroUI Native. `heroui-native` is updated to `^1.0.4`, which was the latest npm release checked during this pass.

The root layout already follows the required native provider pattern:

- `global.css` is imported first.
- `GestureHandlerRootView` wraps the app.
- `HeroUINativeProvider` wraps the app content.
- `SafeAreaProvider`, tenant/auth/realtime providers, and error boundaries are mounted inside that HeroUI Native shell.

## Wrapper Policy

Route screens should prefer the local wrappers in `components/ui` unless a HeroUI Native component is needed directly for compound composition.

Use:

- `Button` for ordinary actions.
- `heroui-native` `Button` directly for dense compound/icon cases.
- `Input` for ordinary form fields.
- `Card` for generic framed content.
- `Badge`/`Chip` for status labels.
- `Toggle` and `Checkbox` for boolean settings.
- `BottomSheet`/`ActionSheet` for sheet interactions.
- `LoadingSpinner` and `EmptyState` for async states.

Keep raw React Native primitives for layout and native capabilities: `View`, `Text`, `ScrollView`, `FlatList`, `Image`, `KeyboardAvoidingView`, media, maps, platform APIs, and animation containers.

## What Changed In This Pass

- Upgraded `heroui-native` from `^1.0.3` to `^1.0.4`.
- Replaced the local Button loading `ActivityIndicator` with HeroUI Native `Spinner`.
- Replaced the app entry route `ActivityIndicator` with HeroUI Native `Spinner`.
- Reworked the local Input wrapper to use HeroUI Native `TextField`, `Label`, `Input`, and `FieldError`.
- Reworked the local FAB wrapper to use HeroUI Native `Button` instead of a custom animated `Pressable`.
- Added a mobile Support & Legal hub for help, resources, about, contact, terms, privacy, cookies, and accessibility web parity links.
- Added a native Polls route backed by the feed polls query and inline `PollCard` voting.
- Added a native direct Connections workflow route for accepted, incoming, and sent member connection requests.
- Migrated exchange browse/create/edit/detail request-comment-report, federation message/reply/compose fields, member/profile transfer, profile edit, group browse/create/edit/detail discussion, endorsements skill entry, blog, messages inbox/thread composer, AI chat composer, global search, organisation search, jobs search/detail application, identity verification, marketplace hub, marketplace category, advanced marketplace search, marketplace map coordinate fields, marketplace offer counter fields, marketplace detail/order/tool form helpers, marketplace listing form, marketplace shipping option, collection creation, and seller onboarding fields, wallet action fields, goals create fields, volunteering browse/hours/detail application fields, and new/edit group/job/event/volunteering/organisation form fields from raw `TextInput` controls to the shared HeroUI Native-backed `Input` wrapper.
- Migrated new/edit exchange form fields to the shared `Input` wrapper and added explicit ref forwarding to keep keyboard flow intact.
- Migrated change-password form fields to the shared `Input` wrapper while preserving secure text entry and validation errors.
- Migrated bottom-sheet style React Native `Modal` flows for job applications and marketplace collection, coupon QR, listing detail, order action, and seller-coupon redemption workflows to the shared HeroUI Native-backed `BottomSheet` wrapper.
- Migrated the marketplace listing inventory switches to the shared `Toggle` wrapper, marketplace listing-card save controls, auth password visibility toggles, the voice-message playback control, and the volunteering search clear control to HeroUI Native icon buttons, the organisation registration terms checkbox to the shared `Checkbox` wrapper, and `ActionSheet` rows plus jobs tabs/retry/application interview-offer actions, group-detail tabs, message reaction/action controls, the messages swipe archive action, feed read-more action, volunteering tabs/hours organisation selector chips, poll answer choices, exchange report reason chips/related listing pills, and the login forgot-password action to HeroUI Native-backed buttons.
- Moved the shared image carousel fallback accessibility label to `common:aria.carouselImage` and added the key across the mobile locale set.
- Added a native feed item detail modal for post/poll/discussion/resource-style feed items, backed by the same post and polymorphic feed item API endpoints used by the web frontend.
- Added native feed hashtag discovery and hashtag detail modals, plus a home feed entry point, backed by the same trending/search/tagged-post endpoints used by the web frontend.
- Replaced the login screen's external forgot-password web link with a native HeroUI Native forgot-password flow backed by the same `/api/auth/forgot-password` endpoint as the web frontend.
- Added a native HeroUI Native reset-password flow backed by `/api/auth/reset-password`, completing the mobile password recovery path after a reset-link deep link.
- Added a native HeroUI Native verify-email flow backed by `/api/auth/verify-email`, so email verification links can resolve inside the mobile app.
- Added a native HeroUI Native resources and knowledge-base flow backed by `/v2/resources`, `/v2/resources/categories`, `/v2/kb`, and `/v2/kb/{id}`, plus support-hub routing into the native resources screen.
- Added a native HeroUI Native ideation challenge flow backed by `/v2/ideation-challenges`, `/v2/ideation-categories`, `/v2/ideation-challenges/{id}/ideas`, and `/v2/ideation-ideas/{id}/vote`, covering browse/detail, idea submission, idea voting, and the profile discovery shortcut.
- Migrated profile hub menu rows from raw React Native `Pressable` controls to HeroUI Native `Button` rows while preserving the existing navigation layout and accessibility labels.
- Migrated `MemberCard` navigation from a raw React Native `Pressable` wrapper to a HeroUI Native `Button` wrapper with scale feedback; exchange/listing cards with nested action buttons remain native touch surfaces to avoid nested button semantics.
- Migrated select-tenant option rows from raw React Native `Pressable` controls to HeroUI Native `Button` wrappers while preserving selected state and tenant logo rendering.
- Migrated blog list cards from raw React Native `Pressable` controls to HeroUI Native `Button` wrappers with scale feedback.
- Migrated settings action rows from raw React Native `Pressable` controls to HeroUI Native `Button` wrappers while preserving navigation to password, identity, blocked-user, data-export, and translation settings.
- Migrated organisation directory cards from raw React Native `Pressable` controls to HeroUI Native `Button` wrappers while preserving detail navigation and website/detail card actions.
- Migrated group-exchange list cards from raw React Native `Pressable` wrappers to HeroUI Native `Button` wrappers while preserving detail navigation and existing metadata chips.
- Migrated global search result cards from raw React Native `Pressable` wrappers to HeroUI Native `Button` wrappers while preserving haptics and typed result routing.
- Migrated event and group list cards from raw React Native `Pressable` wrappers to HeroUI Native `Button` wrappers while preserving haptics, media rendering, metadata chips, and detail navigation.
- Migrated marketplace collection rows from raw React Native `Pressable` wrappers to HeroUI Native `Button` wrappers while preserving the collection item-sheet workflow.
- Removed the raw outer `Pressable` from marketplace listing cards, kept save as its own HeroUI Native icon button, and moved detail navigation to an explicit translated HeroUI Native button.
- Migrated jobs browse cards from raw React Native `Pressable` wrappers to HeroUI Native `Button` wrappers while preserving haptics and detail routing.
- Migrated notification body taps from raw React Native `Pressable` wrappers to HeroUI Native `Button` wrappers while keeping mark-read and delete as separate actions.
- Migrated wallet recipient search result rows and transaction rows from raw React Native `Pressable` wrappers to HeroUI Native `Button` wrappers while preserving selection state and haptic feedback.
- Migrated group-detail event cards from raw React Native `Pressable` wrappers to HeroUI Native `Button` wrappers while preserving event detail routing.
- Added native group announcement management for group admins, backed by `/v2/groups/{id}/announcements`, with HeroUI Native composer controls, pin/unpin actions, and delete actions.
- Migrated thread context cards from raw React Native `Pressable` wrappers to HeroUI Native `Button` wrappers while preserving contextual deep links; removed an unused chat `Pressable` import.
- Migrated member-profile listing rows from raw React Native `Pressable` wrappers to HeroUI Native `Button` wrappers while preserving listing detail navigation.
- Migrated exchange-detail author cards from raw React Native `Pressable` wrappers to HeroUI Native `Button` wrappers while leaving image thumbnail controls native.
- Migrated federation hub quick-link and partner cards plus federation directory partner, group, event, and message cards from raw React Native `Pressable` wrappers to HeroUI Native `Button` wrappers while preserving haptics, route navigation, and in-screen detail/thread transitions.
- Migrated story circle targets from raw React Native `Pressable` wrappers to HeroUI Native `Button` wrappers while preserving member story callbacks and avatar rendering.
- Migrated exchange list cards from raw React Native `Pressable` wrappers to HeroUI Native `Button` wrappers while replacing nested duplicate detail buttons with visual affordances.
- Added HeroUI Native dashboard summary cards to the home feed hub for time balance, upcoming events, open requests, and unread notifications using existing mobile API modules.
- Added native settings blocked-user management and data export request/history screens, backed by existing settings/privacy API endpoints.
- Added native translation/content preference settings for chronological feed ordering, UGC auto-translation, target locale, and local language switching.
- Added a native goal detail route with progress history, insights, milestones, reminder controls, and owner progress updates backed by existing V2 goals endpoints.
- Added native job-alert management inside the jobs workflow, backed by the existing V2 job-alert APIs and using the shared Input and Toggle wrappers plus HeroUI Native buttons/chips for alert criteria, status, and actions.
- Added applicant-side job application cover-message expansion, status history loading, and withdraw actions inside the native jobs workflow.
- Added per-event reminder controls to the native event detail workflow, backed by `/v2/events/{id}/reminders` and implemented with HeroUI Native buttons/chips rather than a web-only preferences placeholder.
- Added attendee previews plus organizer attendance/check-in management to the native event detail workflow, backed by `/v2/events/{id}/attendees` and `/v2/events/{id}/attendees/{attendeeId}/check-in` and rendered with HeroUI Native cards, chips, buttons, spinner states, and translated accessibility labels.
- Added native event waitlist join/leave controls for full events and corrected the shared Laravel POST waitlist route to call the existing `joinWaitlist` handler.
- Added event-linked poll loading and voting to the native event detail workflow, backed by `/v2/polls?event_id=...` and `/v2/polls/{id}/vote`.
- Removed the raw `Pressable` wrapper around native volunteering opportunity cards and kept the concrete view/apply actions on HeroUI Native buttons with translated accessibility labels.
- Added a native volunteering My Shifts tab backed by `/v2/volunteering/shifts` and `/v2/volunteering/shifts/{id}/signup`, with HeroUI Native confirmed-shift cards, opportunity links, and cancel actions.
- Added a native volunteering Certificates tab backed by `/v2/volunteering/certificates`, with HeroUI Native certificate cards, generation, and printable certificate open actions.
- Added a native volunteering Expenses tab backed by `/v2/volunteering/expenses`, with HeroUI Native expense cards, organisation/type selectors, and submission fields.
- Added a native volunteering Donations tab backed by `/v2/volunteering/giving-days` and `/v2/volunteering/donations`, with HeroUI Native giving-day cards, donation history, anonymous toggle, and pledge/offline donation submission fields.
- Migrated exchange-detail gallery thumbnail selectors from raw `Pressable` controls to HeroUI Native icon buttons while preserving selected accessibility state.
- Migrated messages inbox rows from raw `Pressable` controls to HeroUI Native buttons while preserving swipe-to-archive behavior.
- Migrated the shared image carousel image press target from raw `Pressable` to a HeroUI Native button while preserving translated image-button accessibility labels.
- Removed the raw outer `Pressable` from federation listing cards and kept detail navigation on a HeroUI Native button with the existing translated label.
- Migrated the compatibility `components/ui/Card` pressable mode from raw `Pressable` to a HeroUI Native button wrapper.
- Added explicit native notification mark-read and delete card actions backed by the V2 notification endpoints.
- Migrated the app-level and modal error-boundary recovery actions from hand-styled React Native `Pressable` controls to the shared HeroUI Native-backed `Button` wrapper.
- Added a first-class native skills route that reuses the existing HeroUI Native skills and endorsements management screen, and pointed the profile shortcut at that route.
- Added a native activity dashboard route backed by `/v2/users/me/activity/dashboard`, with HeroUI Native summary cards for hours, connections, posts, skills, and recent timeline entries.
- Added a native matches route backed by `/v2/matches/all`, with HeroUI Native tabs/cards/chips, profile navigation, source routing for listings/jobs/volunteering/groups, and listing dismiss actions.
- Added a native reviews route backed by the V2 reviews endpoints for received, given, and pending review workflows, including the mobile write-review form and own-review deletion.
- Added native HeroUI Native AI tool-result cards for structured chat `tool_invocations`, preserving listing/member/event/job/marketplace/knowledge-base/wallet result payloads from the AI chat API.
- Added the parity audit and migration queue in `mobile/docs/HEROUI_NATIVE_PARITY_AUDIT.md`.

## Practical Exceptions

Some manual styling remains intentional for now:

- Tenant branding colors loaded at runtime.
- Media, map, chart, and image sizing.
- Navigation/tab bar styles owned by Expo Router and React Navigation.
- Existing complex screen-specific form helpers that need staged migration with tests.
- Native touch surfaces remain where they are semantically useful: feed double-tap/media composition and other native media/gesture layers.

Admin and broker panels are intentionally excluded from mobile parity by owner instruction. Workflow-oriented product screens remain eligible when they make sense on native mobile.
