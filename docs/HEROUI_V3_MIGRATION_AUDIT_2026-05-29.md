# HeroUI v3 Migration Audit - 2026-05-29

## Docs Checked

- HeroUI MCP: `/docs/react/getting-started/quick-start`
- HeroUI MCP: `/docs/react/migration/agent-guide-full`
- HeroUI MCP: `/docs/react/migration/styling`
- HeroUI MCP component docs: `DateField`, `DateRangePicker`, `TagGroup`, `Separator`, `CloseButton`, `TextField`, `Form`, `Fieldset`, `Disclosure`, `DisclosureGroup`, `Meter`, `Toolbar`

## Completed In This Pass

- Fixed the remaining HeroUI-related TypeScript blockers:
  - `DateField` wrapper now uses the v3 exported component type surface instead of the generic root props directly.
  - `DateRangePicker` wrapper now uses the v3 exported component type surface instead of the generic root props directly.
  - `TagGroup` no longer imports the removed/non-exported `TagProps` type from `@heroui/react`.
- Added first-class v3 UI barrel wrappers for:
  - `Separator`
  - `CloseButton`
  - `TextField`
  - `TextArea` alias via the existing `Textarea` wrapper
  - `Form`
  - `Fieldset`, `FieldsetLegend`, `FieldGroup`, `FieldsetActions`
  - `Label`
  - `Description`
  - `Disclosure` compound parts
  - `Meter` compound parts
  - `Toolbar`
- Migrated remaining safe direct `@heroui/react` value imports outside `src/components/ui` to `@/components/ui` for `Chip`, `Card`, `Separator`, `CloseButton`, `ToggleButton`, and `ToggleButtonGroup`.
- Preserved v3 compound usage through local wrappers by exposing `Chip.Label` and `Card.Header` / `Card.Content` / `Card.Body` / `Card.Footer`.
- Replaced the remaining straightforward v2 utility classes in source:
  - `text-foreground-400` -> `text-theme-subtle`
  - `text-foreground-500` / `text-foreground-600` -> `text-theme-muted`
  - `bg-content2` -> `bg-surface-secondary`
  - `bg-primary-50 text-primary-600` -> `bg-accent/10 text-accent`
- Fixed adjacent TypeScript verification blockers discovered during the HeroUI pass:
  - Sentry integration typing now imports the current SDK `Integration` type from `@sentry/core`.
  - Volunteering tabs now guard the string cast against the known tab list.
  - Help center imports the missing `Search` icon.

## Current Status

- `@heroui/react` value imports outside `react-frontend/src/components/ui` are effectively gone.
- Remaining non-wrapper source references are type-only `Key` imports and comments.
- The frontend now has a fuller local v3 primitive surface, so future component work can start from project wrappers instead of raw HeroUI imports.
- TypeScript verification passes with `npx tsc --noEmit --pretty false`.

## Intentional Compatibility Shims Left

- Existing v2-shaped wrappers remain for broad compatibility: `Button`, `Card`, `Chip`, `Input`, `Textarea`, `Modal`, `Drawer`, `Select`, `Dropdown`, `Tabs`, `Table`, and related families.
- The chart bridge variables in `tokens.css` and chart call sites using `--heroui-*` remain intentional until chart color helpers are centralized.
- `Code` and `Snippet` local wrappers remain as app-local replacements for removed HeroUI v2 components.

## Remaining Work

- Gradually retire the highest-risk compatibility props where call sites can be changed safely:
  - `Button` legacy `color` / `variant` aliases.
  - `Card` legacy `shadow`, `radius`, and `classNames` bridges.
  - `Modal` / `Drawer` slot `classNames` bridges.
  - `Select` / `Dropdown` legacy slot props and item aliases.
  - `Tabs` old variant aliases.
  - `Table` slot compatibility.
- Replace chart `hsl(var(--heroui-*))` reads with a typed chart token helper that reads project tokens once per theme.
- Continue opportunistic conversion of large form sections to native v3 `Form`, `Fieldset`, `TextField`, `Label`, `Description`, and `FieldError` composition.
- Consider introducing `DisclosureGroup` once a concrete admin/public accordion-like section needs grouped disclosure semantics.
- Browser spot-check authenticated admin/job/broker routes after login fixtures are available; static type migration is clean, but dense operational surfaces should still be visually sampled.

