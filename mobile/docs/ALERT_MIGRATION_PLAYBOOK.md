<!--
Copyright © 2024–2026 Jasper Ford
SPDX-License-Identifier: AGPL-3.0-or-later
Author: Jasper Ford
See NOTICE file for attribution and acknowledgements.
-->

# Alert.alert → Branded Toast / ConfirmDialog Playbook

Last reviewed: 2026-07-14

`NATIVE_UI_CONTRACT.md` mandates branded HeroUI Native feedback instead of the
OS `Alert.alert`. This is the exact, verified transformation. Reference
implementations: `app/(modals)/wallet.tsx` (toast) and
`app/(modals)/settings-blocked-users.tsx` (confirm).

## 1. Classify each `Alert.alert`

- **Two arguments** `Alert.alert(title, message)` → **toast** (`useAppToast`).
- **Three arguments with a button array** `Alert.alert(title, message, [...])`
  where a button has an `onPress` (and usually a `style: 'cancel'`/`'destructive'`)
  → **confirmation** (`useConfirm`).
- A single `[{ text: 'OK' }]` with no `onPress` is just an acknowledgement →
  **toast**.

## 2. Toast transform

```tsx
import { useAppToast } from '@/components/ui/AppToast';
// inside the component (top level, with the other hooks):
const { show: showToast } = useAppToast();

// replace:
Alert.alert(t('x.title'), t('x.message'));
// with:
showToast({ title: t('x.title'), description: t('x.message'), variant: 'danger' });
```

Pick `variant` from intent:

| Situation | variant |
| --- | --- |
| `catch {}` blocks, request/load/save failures, "could not …" | `'danger'` |
| Form validation ("enter an amount", "select a recipient") | `'warning'` |
| Successful create/update/delete/transfer/send | `'success'` |
| Neutral info ("nothing to export", "copied") | `'default'` |

Keep the existing `t(...)` keys exactly — strings are already translated, **no
new i18n keys are needed**.

## 3. Confirmation transform

```tsx
import { useConfirm } from '@/components/ui/useConfirm';
// inside the component:
const { confirm, confirmDialog } = useConfirm();

// replace:
Alert.alert(title, message, [
  { text: cancelText, style: 'cancel' },
  { text: confirmText, style: 'destructive', onPress: () => doIt() },
]);
// with:
confirm({
  title,
  message,
  confirmLabel: confirmText,
  cancelLabel: cancelText,
  variant: 'danger',     // 'danger' for destructive, 'primary' otherwise
  onConfirm: () => doIt(),
});

// and render the dialog ONCE inside the screen's root container
// (e.g. just before the closing </SafeAreaView>):
{confirmDialog}
```

- A function that existed only to show the confirm Alert can drop its `async`.
- If `onConfirm` runs an async action, return/await it — the dialog shows a
  spinner until it resolves, then closes.

## 4. Imports

- Remove `Alert` from the `react-native` import once no `Alert.alert` remains in
  the file. Leave the rest of the import intact.

## 5. Update the screen's test (`<screen>.test.tsx`)

Add a **stable** toast mock (functions created once in the factory closure — a
fresh `jest.fn()` per render breaks screens that list `show` in a
`useCallback`/`useEffect` dependency array):

```tsx
jest.mock('@/components/ui/AppToast', () => {
  const show = jest.fn();
  const hide = jest.fn();
  return { useAppToast: () => ({ show, hide, isToastVisible: false }) };
});
```

If the screen has a confirmation, replace any `Alert.alert` spy with an
auto-confirming `useConfirm` mock and drop `import { Alert } from 'react-native'`:

```tsx
jest.mock('@/components/ui/useConfirm', () => ({
  useConfirm: () => ({
    confirm: (opts: { onConfirm: () => void | Promise<void> }) => { void opts.onConfirm(); },
    confirmDialog: null,
  }),
}));
```

A pre-existing test that did `jest.spyOn(Alert, 'alert').mockImplementation((t, m, buttons) => buttons.find(...)?.onPress?.())`
is replaced by the auto-confirm mock above (it runs the action immediately).

## 6. Verify

```bash
npx jest "<screen>.test" --runInBand     # the screen's own suite, must pass
```

Confirm no `Alert.alert` remains: it must not appear in the migrated `.tsx`
(the `react-native` `Alert` import should be gone too).
