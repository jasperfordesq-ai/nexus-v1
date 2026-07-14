# Mobile Wrapper Policy

Last reviewed: 2026-07-14

Consolidated guide for native wrapper components, the UI contract, and locale/i18n
for the Expo mobile app (`mobile/`).

See [NATIVE_UI_CONTRACT.md](NATIVE_UI_CONTRACT.md) for the full contract and
[HEROUI_NATIVE_PARITY_AUDIT.md](HEROUI_NATIVE_PARITY_AUDIT.md) for the current parity matrix.

---

## Provider Setup

The root layout (`app/_layout.tsx`) follows this exact order:

1. `global.css` is imported first (Tailwind CSS 4, Uniwind, `heroui-native/styles`).
2. `GestureHandlerRootView` wraps the entire app.
3. `HeroUINativeProvider` wraps the app content.
4. `SafeAreaProvider`, tenant/auth/realtime providers, and error boundaries mount
   inside the HeroUI Native shell.

Do not change this order.

---

## When to Use a Wrapper vs a Primitive

### Use the `components/ui` wrappers for screen-level UI

Route screens must prefer the local wrappers in `components/ui`. They enforce
consistent theming, accessibility labels, and the native UI contract without
requiring per-screen knowledge of HeroUI Native internals.

| Wrapper | File | Use for |
|---------|------|---------|
| `Button` | `components/ui/Button.tsx` | Ordinary actions, navigation taps, icon-only buttons |
| `Input` | `components/ui/Input.tsx` | All visible text entry (wraps HeroUI Native `TextField`/`Label`/`Input`/`FieldError`) |
| `TextArea` | `components/ui/TextArea.tsx` | Long text entry, especially inside bottom sheets |
| `SearchInput` | `components/ui/SearchInput.tsx` | Search boxes — provides clear action and search affordance natively |
| `Card` | `components/ui/Card.tsx` | Generic framed content; pressable mode uses a HeroUI Native button wrapper |
| `Badge` / HeroUI `Chip` (via `Badge.tsx`) | `components/ui/Badge.tsx` | Status labels and counts |
| `Toggle` | `components/ui/Toggle.tsx` | Boolean settings (e.g. inventory switches) |
| `Checkbox` | `components/ui/Checkbox.tsx` | Multi-select or terms-acceptance controls |
| `BottomSheet` | `components/ui/BottomSheet.tsx` | Mobile drawers, sheet workflows, form/action sheets |
| `ActionSheet` | `components/ui/ActionSheet.tsx` | Option menus and action rows presented as sheets |
| `NativePressable` | `components/ui/NativePressable.tsx` | Card/list row taps where a button would create nested-button semantics |
| `LoadingSpinner` | `components/ui/LoadingSpinner.tsx` | Async loading states |
| `EmptyState` | `components/ui/EmptyState.tsx` | Empty list / error fallback states |

For transient feedback and confirmations use the hook-based wrappers:

| Hook | File | Use for |
|------|------|---------|
| `useAppToast()` | `components/ui/AppToast.tsx` | Transient success / error / warning feedback — replaces `Alert.alert` |
| `useConfirm()` | `components/ui/useConfirm.tsx` | Yes/no confirmations; render `{confirmDialog}` once in the screen |

`Alert.alert` is fully retired from product code. New code must not reintroduce it.

### Use raw HeroUI Native primitives for compound composition

Reach directly for `heroui-native` components when building dense compound layouts
where the local wrapper would fight the native component anatomy (e.g. a
multi-part compound card with custom inner controls). This is the exception, not
the rule — prefer the wrapper first and only bypass it when there is a concrete
structural reason.

### Keep raw React Native primitives for native capabilities

Use `View`, `Text`, `ScrollView`, `FlatList`, `Image`, `KeyboardAvoidingView`,
and other React Native primitives for:

- Layout, spacing, and composition containers
- Lists and virtualized data
- Media (images, video)
- Map surfaces
- Gesture-heavy layers (e.g. feed double-tap, drag-and-drop)
- Platform APIs and animation containers

Do not wrap these in HeroUI Native components. The goal is native-first behavior,
not component uniformity.

---

## Practical Exceptions

These use cases intentionally remain as manual or native styling rather than
being migrated to HeroUI Native wrappers:

- **Tenant brand colors**: loaded at runtime from the API; applied directly to
  surfaces that need the tenant primary accent.
- **Media, map, chart, and image sizing**: controlled by native layout constraints,
  not theming tokens.
- **Navigation and tab bar styles**: owned by Expo Router and React Navigation;
  do not override with HeroUI Native styles.
- **Complex screen-specific form helpers**: migrate incrementally with tests, not
  as a bulk replacement.
- **Native touch/gesture surfaces**: feed double-tap and media composition layers
  remain raw `Pressable` targets because wrapping them would break gesture behavior.
- **Admin and broker panels**: excluded from mobile by owner instruction; do not
  implement.

---

## Migration Discipline

When moving a screen or component to a new native primitive:

1. Add a failing test first.
2. Migrate by interaction pattern, not by whole page.
3. Preserve the existing layout unless the test or visual audit proves it is the
   problem.
4. Do not perform bulk replacements (`Pressable`, `TextInput`, `Alert.alert`) —
   each replacement needs context.
5. Prefer one shared primitive plus one low-risk consumer per pass.
6. Run `npm run type-check` and the focused test suite before committing.

---

## Locale / i18n

**Library:** `i18next` with `react-i18next` and `expo-localization`.  
**Setup file:** `lib/i18n.ts`  
**Locale files:** `locales/<lang>/<namespace>.json` (bundled in the Expo build).

### Supported languages

| Code | Language |
|------|----------|
| `en` | English (default fallback) |
| `ga` | Irish |
| `de` | German |
| `fr` | French |
| `it` | Italian |
| `pt` | Portuguese |
| `es` | Spanish |

### Namespaces

Strings are split into per-module namespaces. The default namespace is `common`.
Other namespaces (`auth`, `exchanges`, `wallet`, `groups`, `marketplace`, etc.)
must be declared in the `useTranslation` hook call:

```ts
const { t } = useTranslation('exchanges');
```

The authoritative namespace list is the `NAMESPACES` constant in `lib/i18n.ts`.

### Language detection and persistence

1. On cold start, `lib/i18n.ts` detects the device locale via
   `expo-localization`. If the detected language is supported, it is used;
   otherwise the app falls back to `en`.
2. Only the active language and English are parsed at cold start (lazy loading).
   Other languages are loaded on demand when the user switches in Settings.
3. The user's chosen language is persisted to `expo-secure-store` via
   `lib/storage.ts` (`STORAGE_KEYS.LANGUAGE`). `restoreSavedLanguage()` re-applies
   it at boot so the choice survives app restarts.

### Switching language at runtime

```ts
import { changeLanguage } from '@/lib/i18n';
await changeLanguage('fr'); // loads bundle if not yet loaded, then switches
```

### Adding strings

1. Add the key to `locales/en/<namespace>.json`.
2. Add the same key to every other locale file in `locales/<lang>/<namespace>.json`.
3. Verify all locale JSON files parse correctly before committing:

```bash
node -e "
  const fs = require('fs');
  for (const l of fs.readdirSync('locales')) {
    for (const f of fs.readdirSync('locales/' + l).filter(f => f.endsWith('.json'))) {
      JSON.parse(fs.readFileSync('locales/' + l + '/' + f, 'utf8'));
    }
  }
  console.log('all locale JSON ok');
"
```

Never hardcode user-visible strings in screen files. If a translation is not yet
available for a locale, add a placeholder in that locale's file — missing keys
fall back to English automatically.

---

## Verification

After any wrapper or locale change:

```bash
cd mobile
npm run type-check
npm test -- --runInBand --silent
```

For focused passes, run the affected screen test plus the wrapper test together:

```bash
npm test -- <screen>.test.tsx components/ui/Input.test.tsx --runInBand
```

Review any HeroUI Native, Uniwind, colorKit, or React `act` warning in context;
a non-zero exit, timeout, or open-handle failure is not a passing verification.
