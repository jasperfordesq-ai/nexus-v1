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

## Rules

- Use `components/ui/BottomSheet.tsx` for mobile drawers and form/action sheets.
- Use `components/ui/AppToast.tsx` for transient success/error feedback instead of `Alert.alert`.
- Use `components/ui/ConfirmDialog.tsx` for destructive or blocking confirmations.
- Use `components/ui/NativePressable.tsx` for card/list row taps instead of raw `Pressable` or button-shaped cards.
- Use `components/ui/SearchInput.tsx` for search boxes, so clear actions and search affordances are native.
- Use `components/ui/TextArea.tsx` for long mobile text entry, especially inside drawers.

## Migration Discipline

- Migrate by interaction pattern, not by whole page.
- Add a failing test before moving a screen to a new native primitive.
- Preserve the existing polished layout unless the test or visual audit proves it is the problem.
- Do not globally replace `Alert.alert`, `Pressable`, or `TextInput`; each replacement needs context.
- Prefer one shared primitive plus one low-risk consumer per pass.
