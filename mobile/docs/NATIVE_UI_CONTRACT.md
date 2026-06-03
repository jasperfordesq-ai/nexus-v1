# Mobile Native UI Contract

This app should feel like a native mobile application first, and a parity layer second.
Feature parity must not override the visual and interaction system.

## HeroUI Native Baseline

Checked against the HeroUI Native docs on 2026-05-31:

- BottomSheet: https://www.heroui.com/en/docs/native/components/bottom-sheet
- Toast: https://www.heroui.com/en/docs/native/components/toast
- Dialog: https://www.heroui.com/en/docs/native/components/dialog
- PressableFeedback: https://www.heroui.com/en/docs/native/components/pressable-feedback
- SearchField: https://www.heroui.com/en/docs/native/components/search-field
- TextArea: https://www.heroui.com/en/docs/native/components/text-area
- ListGroup: https://www.heroui.com/en/docs/native/components/list-group
- Popover: https://www.heroui.com/en/docs/native/components/popover

## Status (2026-06-03)

- **`Alert.alert` is fully retired from product code.** All ~359 calls across 62
  screens/components were migrated to branded HeroUI Native feedback. New code
  MUST NOT introduce `Alert.alert` — use the wrappers below.
- Transient feedback → `useAppToast()` from `components/ui/AppToast.tsx`
  (`show({ title, description, variant })`; variant `danger`/`warning`/`success`/`default`).
- Yes/no confirmations → `useConfirm()` from `components/ui/useConfirm.tsx`
  (`confirm({ title, message, confirmLabel, cancelLabel, variant, onConfirm })`
  plus render `{confirmDialog}` once in the screen). See
  `docs/ALERT_MIGRATION_PLAYBOOK.md`.
- Light/dark theming is live: `useTheme()` is reactive (backed by
  `lib/theme/themeStore.ts`); the user picks System/Light/Dark in Settings.

## Rules

- Use `components/ui/BottomSheet.tsx` for mobile drawers and form/action sheets.
- Use `components/ui/AppToast.tsx` for transient success/error feedback instead of `Alert.alert`.
- Use `components/ui/useConfirm.tsx` (built on `ConfirmDialog.tsx`) for destructive or blocking confirmations.
- Use `components/ui/NativePressable.tsx` for card/list row taps instead of raw `Pressable` or button-shaped cards.
- Use `components/ui/SearchInput.tsx` for search boxes, so clear actions and search affordances are native.
- Use `components/ui/TextArea.tsx` for long mobile text entry, especially inside drawers.

## Migration Discipline

- Migrate by interaction pattern, not by whole page.
- Add a failing test before moving a screen to a new native primitive.
- Preserve the existing polished layout unless the test or visual audit proves it is the problem.
- Do not globally replace `Alert.alert`, `Pressable`, or `TextInput`; each replacement needs context.
- Prefer one shared primitive plus one low-risk consumer per pass.
