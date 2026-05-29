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
- Migrated the marketplace listing inventory switches to the shared `Toggle` wrapper, marketplace listing-card save controls, auth password visibility toggles, the voice-message playback control, and the volunteering search clear control to HeroUI Native icon buttons, the organisation registration terms checkbox to the shared `Checkbox` wrapper, and `ActionSheet` rows plus jobs tabs/retry/application interview-offer actions, group-detail tabs, message reaction/action controls, the messages swipe archive action, volunteering tabs/hours organisation selector chips, poll answer choices, exchange report reason chips/related listing pills, and the login forgot-password action to HeroUI Native-backed buttons.
- Added the parity audit and migration queue in `mobile/docs/HEROUI_NATIVE_PARITY_AUDIT.md`.

## Practical Exceptions

Some manual styling remains intentional for now:

- Tenant branding colors loaded at runtime.
- Media, map, chart, and image sizing.
- Navigation/tab bar styles owned by Expo Router and React Navigation.
- Existing complex screen-specific form helpers that need staged migration with tests.
- Pressable card/list-row/gesture/image surfaces where native touch composition is still more appropriate than a button abstraction.

Admin and caring-community workflows are intentionally excluded from mobile parity by owner instruction.
