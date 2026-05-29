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
- Reworked the local Input wrapper to use HeroUI Native `TextField`, `Label`, `Input`, and `FieldError`.
- Reworked the local FAB wrapper to use HeroUI Native `Button` instead of a custom animated `Pressable`.
- Added a mobile Support & Legal hub for help, resources, about, contact, terms, privacy, cookies, and accessibility web parity links.
- Added a native Polls route backed by the feed polls query and inline `PollCard` voting.
- Added a native direct Connections workflow route for accepted, incoming, and sent member connection requests.
- Migrated exchange, member, group, blog, messages, and global search fields from raw `TextInput` controls to the shared HeroUI Native-backed `Input` wrapper.
- Migrated new/edit exchange form fields to the shared `Input` wrapper and added explicit ref forwarding to keep keyboard flow intact.
- Migrated change-password form fields to the shared `Input` wrapper while preserving secure text entry and validation errors.
- Added the parity audit and migration queue in `mobile/docs/HEROUI_NATIVE_PARITY_AUDIT.md`.

## Practical Exceptions

Some manual styling remains intentional for now:

- Tenant branding colors loaded at runtime.
- Media, map, chart, and image sizing.
- Navigation/tab bar styles owned by Expo Router and React Navigation.
- Existing complex screen-specific form helpers that need staged migration with tests.

Admin and caring-community workflows are intentionally excluded from mobile parity by owner instruction.
