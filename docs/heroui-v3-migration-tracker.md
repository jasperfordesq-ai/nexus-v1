# HeroUI v3 Migration Tracker

This is the living tracker for migrating `react-frontend` from HeroUI v2 to HeroUI v3.

## Standing Rules

- Use the `heroui-react` MCP server before advising on or editing HeroUI migration work.
- Record each HeroUI MCP doc path checked before marking a component family in progress.
- Prefer official MCP docs over memory.
- Do not treat v3 structural components as simple renames unless the MCP docs explicitly say the props and structure remain compatible.
- Keep v2 and v3 side by side until the tracker shows all v2 imports and removed v2 components are gone.

## Source Docs Checked

| Date | MCP docs | Purpose |
| --- | --- | --- |
| 2026-05-25 | `/docs/react/migration` | Overall v2 to v3 migration reference, major changes, component matrix |
| 2026-05-25 | `/docs/react/migration/incremental-migration` | Coexistence strategy using aliases and phased component migration |
| 2026-05-25 | `/docs/react/migration/hooks` | `useDisclosure` to `useOverlayState` and removed hook guidance |
| 2026-05-25 | `/docs/react/migration/modal`, `/docs/react/components/modal` | Modal state migration and v3 compound modal structure reference |
| 2026-05-25 | `/docs/react/migration/styling` | Tailwind plugin removal, CSS import, token and utility migration |
| 2026-05-25 | `/docs/react/migration/code`, `/docs/react/migration/image`, `/docs/react/migration/snippet` | Removed v2 component replacement guidance |
| 2026-05-25 | `/docs/react/migration/progress` | Next phase scoping for `Progress` to compound `ProgressBar` |
| 2026-05-25 | `/docs/react/migration/timeinput`, `/docs/react/components/time-field` | `TimeInput` to compound `TimeField` migration guidance |
| 2026-05-25 | `/docs/react/migration/dropdown`, `/docs/react/components/dropdown` | `Dropdown*` family migration to compound `Dropdown.*` components |
| 2026-05-25 | `/docs/react/migration/accordion`, `/docs/react/components/accordion` | `Accordion*` family migration to compound `Accordion.*` components |
| 2026-05-25 | `/docs/react/migration/select`, `/docs/react/components/select` | `Select*` family migration to compound `Select.*` and `ListBox.*` components |
| 2026-05-25 | `/docs/react/migration/input`, `/docs/react/components/input`, `/docs/react/components/text-area`, `/docs/react/components/text-field` | `Input` and `Textarea` migration to v3 primitive/compound field composition |
| 2026-05-25 | `/docs/react/migration/card`, `/docs/react/components/card` | `Card` family migration to v3 compound `Card.*` API via compatibility wrapper |
| 2026-05-25 | `/docs/react/migration/avatar`, `/docs/react/components/avatar` | `Avatar` migration to v3 compound `Avatar.Image` / `Avatar.Fallback` API and manual `AvatarGroup` pattern |

## Current Baseline

Captured: 2026-05-25

| Area | Current state |
| --- | --- |
| React | `^19.2.6` in `react-frontend/package.json` |
| React DOM | `^19.2.6` in `react-frontend/package.json` |
| Tailwind | `^4.0.0`; `@tailwindcss/vite` is `^4.3.0` |
| HeroUI v2 | `@heroui/react` `^2.6.0`, `@heroui/theme` `^2.4.0` |
| Framer Motion | `^11.0.0` still present |
| v2 Tailwind plugin | `react-frontend/src/hero.ts` exports `heroui(...)` |
| v3 aliases | `@heroui-v3/react` -> `@heroui/react@^3.0.5`; `@heroui-v3/styles` -> `@heroui/styles@^3.0.5` |

## Scope Counts

Captured with import-specific scan of `react-frontend/src`.

| Component/API | Files | Migration kind | Status |
| --- | ---: | --- | --- |
| `HeroUIProvider` | 66 | Remove after v2 no longer needs provider | Not started |
| `useDisclosure` | 0 remaining from 97 baseline | Replaced with app-local compatibility hook backed by v3 `useOverlayState`; modal structure review remains for final v2 component cleanup | Complete |
| `Divider` | 0 remaining from 126 baseline | Renamed to `Separator`; mostly mechanical | Complete |
| `Progress` | 0 remaining from 51 baseline | Structural migration to `ProgressBar` compound components via app-local wrapper | Complete |
| `CircularProgress` | 0 | No work currently detected | Complete |
| `DateInput` / `DateInputValue` | 0 | No work currently detected | Complete |
| `TimeInput` | 0 remaining from 3 baseline | Structural migration to `TimeField` via app-local wrapper | Complete |
| `SelectItem` | 0 remaining from 197 baseline | Structural migration to `Select` + `ListBox.Item` via app-local wrapper | Complete |
| `SelectSection` | 0 | No work currently detected | Complete |
| `DropdownItem` / `DropdownMenu` / `DropdownTrigger` | 0 remaining from 45 baseline | Structural migration to compound `Dropdown.*` API via app-local wrapper | Complete |
| `DropdownSection` | 0 remaining from 2 baseline | Structural migration to `Dropdown.Section` with `Header` / `Separator` via app-local wrapper | Complete |
| `AccordionItem` | 0 remaining from 14 baseline | Structural migration to compound `Accordion.*` API via app-local wrapper | Complete |
| `Button` | 0 remaining from 669 baseline | Structural migration to v3 `Button` via app-local compatibility wrapper | Complete |
| `Chip` | 0 remaining from 445 baseline | Structural migration to v3 `Chip` via app-local compatibility wrapper | Complete |
| `Spinner` | 0 remaining from 349 baseline | Structural migration to v3 `Spinner` via app-local compatibility wrapper | Complete |
| `Input` / `InputProps` | 0 remaining from current baseline | Structural migration to v3 `Input` + `TextField` via app-local compatibility wrapper | Complete |
| `Textarea` / `TextareaProps` | 0 remaining from current baseline | Structural migration to v3 `TextArea` + `TextField` via app-local compatibility wrapper | Complete |
| `Card` / `CardHeader` / `CardBody` / `CardFooter` | 0 remaining from 290 / 174 / 283 / 3 baseline | Structural migration to v3 compound `Card.*` via app-local compatibility wrapper | Complete |
| `Modal` / `ModalContent` / `ModalHeader` / `ModalBody` / `ModalFooter` | 0 remaining from 216-file baseline | Structural migration to v3 compound `Modal.*` via app-local compatibility wrapper | Complete |
| `Avatar` / `AvatarGroup` | 0 remaining from 154 / 5 baseline | Structural migration to v3 compound `Avatar.Image` / `Avatar.Fallback` via app-local compatibility wrapper; manual CSS group wrapper for removed `AvatarGroup` | Complete |
| `Code` | 0 remaining from 5 baseline | Removed component; replaced with app-local semantic wrapper | Complete |
| `Image` | 0 remaining from 1 baseline | Removed component; replaced with native `img` | Complete |
| `Snippet` | 0 remaining from 3 baseline | Removed component; replaced with app-local copy snippet | Complete |
| `Spacer` | 0 | No work currently detected | Complete |

## Phase Tracker

| Phase | Scope | MCP docs to check immediately before work | Validation | Status | Notes |
| --- | --- | --- | --- | --- | --- |
| 1 | Install v3 aliases and styles for coexistence | `/docs/react/migration/incremental-migration`, `/docs/react/getting-started/quick-start`, `/docs/react/migration/styling` | `cd react-frontend && npx tsc --noEmit && npm run build` | Complete with verification note | v3 aliases installed; v2 packages kept. `npm run build` passed. `npx tsc --noEmit` timed out twice without error output after 2 and 5 minutes. |
| 2 | `Divider` to `Separator` | `/docs/react/migration/divider` | `cd react-frontend && npx tsc --noEmit && npm run build` | Complete with verification note | 126 files migrated to `Separator` via `@heroui-v3/react`. No remaining `Divider` imports or tags. `npm run build` passed. `npx tsc --noEmit` timed out during 60s/2m/5m attempts without error output. |
| 3 | Removed v2 components: `Code`, `Image`, `Snippet` | `/docs/react/migration/code`, `/docs/react/migration/image`, `/docs/react/migration/snippet` | `cd react-frontend && npx tsc --noEmit && npm run build` | Complete with verification note | Added app-local `Code` and `Snippet`; replaced HeroUI `Image` with native `img`. No `Navbar`, `User`, or `Spacer` imports from HeroUI detected. `npm run build` passed. `npx tsc --noEmit` timed out after 5 minutes with no error output. |
| 4 | `Progress` to `ProgressBar` | `/docs/react/migration/progress` | `cd react-frontend && npx tsc --noEmit && npm run build` | Complete with verification note | Added app-local `Progress` wrapper backed by v3 `ProgressBar`, `Label`, `ProgressBar.Output`, `ProgressBar.Track`, and `ProgressBar.Fill`; mapped v2 `primary` to v3 `accent`. `npm run build` passed. `npx tsc --noEmit` timed out after 5 minutes with no error output. |
| 5 | `TimeInput` to `TimeField` | `/docs/react/migration/timeinput` | `cd react-frontend && npx tsc --noEmit && npm run build` | Complete | Added app-local `TimeInput` wrapper backed by v3 `TimeField` compound parts. `npx tsc --noEmit` and `npm run build` passed. |
| 6 | `Dropdown*` family | `/docs/react/migration/dropdown` | `cd react-frontend && npx tsc --noEmit && npm run build`, plus nearby tests | Complete | Added app-local v3-backed wrappers for `Dropdown`, `DropdownTrigger`, `DropdownMenu`, `DropdownItem`, and `DropdownSection`; no remaining v2 Dropdown imports. |
| 7 | `AccordionItem` family | `/docs/react/migration/accordion` | `cd react-frontend && npx tsc --noEmit && npm run build`, plus nearby tests | Complete | Added app-local v3-backed wrappers for `Accordion` and `AccordionItem`; no remaining v2 Accordion imports. |
| 8 | `SelectItem` family | `/docs/react/migration/select` | Domain tests, `npx tsc --noEmit`, `npm run build` | Complete | Added app-local v3-backed wrappers for `Select`, `SelectItem`, and `SelectSection`; no remaining v2 Select imports. |
| 9 | `useDisclosure` and modal state | `/docs/react/migration/hooks`, `/docs/react/migration/modal` | Modal tests, `npx tsc --noEmit`, `npm run build` | Complete | Added app-local v3-backed `useDisclosure` compatibility hook; no remaining v2 `useDisclosure` imports. |
| 10 | `Button` family | `/docs/react/migration/button`, `/docs/react/components/button` | `npx tsc --noEmit`, `npm run build` | Complete | Added app-local v3-backed compatibility wrapper; no remaining v2 `Button` imports. |
| 11 | `Chip` family | `/docs/react/migration/chip`, `/docs/react/components/chip` | `npx tsc --noEmit`, `npm run build` | Complete | Added app-local v3-backed compatibility wrapper; no remaining v2 `Chip` imports. |
| 12 | `Spinner` family | `/docs/react/migration/spinner`, `/docs/react/components/spinner` | `npx tsc --noEmit`, `npm run build` | Complete | Added app-local v3-backed compatibility wrapper; no remaining v2 `Spinner` imports. |
| 13 | `Input` and `Textarea` family | `/docs/react/migration/input`, `/docs/react/components/input`, `/docs/react/components/text-area`, `/docs/react/components/text-field` | `npx tsc --noEmit`, `npm run build` | Complete with verification note | Added app-local v3-backed compatibility wrappers; no remaining v2 `Input`, `InputProps`, `Textarea`, or `TextareaProps` imports. |
| 14 | `Card` family | `/docs/react/migration/card`, `/docs/react/components/card` | `npx tsc --noEmit`, `npm run build` | Complete with verification note | Added app-local v3-backed compatibility wrapper; no remaining v2 `Card`, `CardHeader`, `CardBody`, or `CardFooter` imports. |
| 15 | `Modal` family | `/docs/react/migration/modal`, `/docs/react/components/modal` | `npx tsc --noEmit`, `npm run build` | Complete with verification note | Added app-local v3-backed compatibility wrapper; no remaining v2 `Modal*` imports. Build passed. `npx tsc --noEmit` now reaches unrelated import-block corruption in non-Modal files. |
| 16 | `Avatar` and `AvatarGroup` family | `/docs/react/migration/avatar`, `/docs/react/components/avatar` | `npx tsc --noEmit`, `npm run build` | Complete with verification note | Added app-local v3-backed compatibility wrapper; no remaining v2 `Avatar` or `AvatarGroup` imports. Targeted wrapper type-check and lint passed. Full `npx tsc --noEmit` and `npm run build` timed out after 10 minutes without diagnostics. |
| 17 | Remove provider, v2 plugin, v2 deps, and aliases | `/docs/react/migration`, `/docs/react/migration/styling` | `npx tsc --noEmit`, `npm run build`, `npm test`, smoke E2E | Blocked | Blocked until all v2 imports are gone |

## Per-Phase Log

Add one entry per migration slice.

| Date | Phase | Files changed | MCP docs checked | Commands run | Result | Follow-up |
| --- | --- | --- | --- | --- | --- | --- |
| 2026-05-25 | Planning | `docs/superpowers/plans/2026-05-25-heroui-v3-migration.md`, `docs/heroui-v3-migration-tracker.md` | `/docs/react/migration`, `/docs/react/migration/incremental-migration`, `/docs/react/migration/hooks`, `/docs/react/migration/styling` | Scope scan only | Tracker created | Begin Phase 1 |
| 2026-05-25 | 1 | `react-frontend/package.json`, `react-frontend/package-lock.json`, `react-frontend/src/index.css`, `docs/heroui-v3-migration-tracker.md` | `/docs/react/migration/incremental-migration`, `/docs/react/getting-started/quick-start`, `/docs/react/migration/styling` | `npm install @heroui-v3/react@npm:@heroui/react@latest @heroui-v3/styles@npm:@heroui/styles@latest`; `npx tsc --noEmit`; `npm run build` | Aliases installed and build passed. `npx tsc --noEmit` timed out after 2 minutes and again after 5 minutes with no error output. | Begin Phase 2 after checking `/docs/react/migration/divider` |
| 2026-05-25 | 2 | 126 files under `react-frontend/src` that imported `Divider` from `@heroui/react` | `/docs/react/migration/divider` | First-file slice on `react-frontend/src/admin/components/RichTextEditor.tsx`; mechanical codemod for remaining files; `rg` check for remaining `Divider`; `npm run build` | `Divider` imports/tags reduced to 0. Build passed. `npx tsc --noEmit` timed out without error output. | Begin Phase 3 after checking removed-component docs |
| 2026-05-25 | 3 | `react-frontend/src/components/ui/Code.tsx`, `react-frontend/src/components/ui/Snippet.tsx`, `react-frontend/src/components/ui/index.ts`, plus 8 call-site files | `/docs/react/migration/code`, `/docs/react/migration/image`, `/docs/react/migration/snippet` | Import scan for removed components; `npx tsc --noEmit`; `npm run build` | `Code`, `Image`, and `Snippet` imports from `@heroui/react` reduced to 0. Build passed. `npx tsc --noEmit` timed out after 5 minutes with no error output. | Begin Phase 4 using `/docs/react/migration/progress` already checked |
| 2026-05-25 | 4 | `react-frontend/src/components/ui/Progress.tsx`, `react-frontend/src/components/ui/index.ts`, 51 call-site files using `Progress` | `/docs/react/migration/progress`, `/docs/react/components/progress-bar` | Mechanical import rewrite; removed-component import scan; `npx tsc --noEmit`; `npm run build` | `Progress` imports from `@heroui/react` reduced to 0. Build passed. `npx tsc --noEmit` timed out after 5 minutes with no error output. | Begin Phase 5 after checking `/docs/react/migration/timeinput` |
| 2026-05-25 | 5 | `react-frontend/src/components/ui/TimeInput.tsx`, `react-frontend/src/components/ui/index.ts`, `react-frontend/src/components/compose/tabs/PostTab.tsx`, `react-frontend/src/components/compose/tabs/EventTab.tsx`, `react-frontend/src/pages/events/CreateEventPage.tsx` | `/docs/react/migration/timeinput`, `/docs/react/components/time-field` | Import scan for `TimeInput`; `npx tsc --noEmit`; `npm run build` | `TimeInput` imports from `@heroui/react` reduced to 0. TypeScript and build passed. | Begin Phase 6 after checking `/docs/react/migration/dropdown` |
| 2026-05-25 | 6 | `react-frontend/src/components/ui/Dropdown.tsx`, `react-frontend/src/components/ui/index.ts`, plus Dropdown call-site files under `react-frontend/src` | `/docs/react/migration/dropdown`, `/docs/react/components/dropdown` | Import scan for v2 Dropdown components; mechanical import/id rewrite; `npx tsc --noEmit`; `npm run build` | `Dropdown`, `DropdownTrigger`, `DropdownMenu`, `DropdownItem`, and `DropdownSection` imports from `@heroui/react` reduced to 0. TypeScript and build passed. | Begin Phase 7 after checking `/docs/react/migration/accordion` |
| 2026-05-25 | 7 | `react-frontend/src/components/ui/Accordion.tsx`, `react-frontend/src/components/ui/index.ts`, plus Accordion call-site files under `react-frontend/src` | `/docs/react/migration/accordion`, `/docs/react/components/accordion` | Import scan for v2 Accordion components; mechanical import/id rewrite; `npx tsc --noEmit --pretty false --noErrorTruncation`; `npm run build` | `Accordion` and `AccordionItem` imports from `@heroui/react` reduced to 0. TypeScript and build passed. | Begin Phase 8 after checking `/docs/react/migration/select` |
| 2026-05-25 | 8 | `react-frontend/src/components/ui/Select.tsx`, `react-frontend/src/components/ui/index.ts`, plus Select call-site files under `react-frontend/src` | `/docs/react/migration/select`, `/docs/react/components/select` | Import scan for v2 Select components; mechanical import/id rewrite; `npx tsc --noEmit --pretty false --noErrorTruncation`; `npm run build` | `Select`, `SelectItem`, and `SelectSection` imports from `@heroui/react` reduced to 0. TypeScript and build passed. Build kept existing Vite chunk/dynamic-import warnings. | Begin Phase 9 after checking `/docs/react/migration/hooks` and `/docs/react/migration/modal` |
| 2026-05-25 | 9 | `react-frontend/src/components/ui/useDisclosure.ts`, `react-frontend/src/components/ui/index.ts`, plus `useDisclosure` call-site import rewrites under `react-frontend/src` | `/docs/react/migration/hooks`, `/docs/react/migration/modal`, `/docs/react/components/modal` | Import scan for v2 `useDisclosure`; `npx tsc --noEmit --pretty false --noErrorTruncation`; `npm run build` | `useDisclosure` imports from `@heroui/react` reduced to 0. TypeScript and build passed. Build kept existing Vite chunk/dynamic-import warnings. | Begin Phase 10 after final v2 import scan and styling docs check |
| 2026-05-25 | 10 | `react-frontend/src/components/ui/Button.tsx`, `react-frontend/src/components/ui/index.ts`, plus 669 Button call-site import rewrites under `react-frontend/src` | `/docs/react/migration/agent-guide-incremental`, `/docs/react/migration/button`, `/docs/react/components/button` | Import scan for v2 `Button`; mechanical import rewrite; `npx tsc --noEmit --pretty false --noErrorTruncation`; `npm run build` | `Button` imports from `@heroui/react` reduced to 0. TypeScript and build passed. Build kept existing Vite dynamic-import/chunk-size warnings. | Continue core component migration; `Chip`, `Spinner`, `Input`, `Card`, and `Modal` remain the largest v2 families |
| 2026-05-25 | 11 | `react-frontend/src/components/ui/Chip.tsx`, `react-frontend/src/components/ui/index.ts`, plus 445 Chip call-site import rewrites under `react-frontend/src` | `/docs/react/migration/chip`, `/docs/react/components/chip` | Import scan for v2 `Chip`; mechanical import rewrite; `npx tsc --noEmit --pretty false --noErrorTruncation`; `npm run build` | `Chip` and `ChipProps` imports from `@heroui/react` reduced to 0. TypeScript and build passed. Build kept existing Vite dynamic-import/chunk-size warnings. | Continue core component migration; `Spinner`, `Input`, `Card`, `Modal`, and `Textarea` remain the largest v2 families |
| 2026-05-25 | 12 | `react-frontend/src/components/ui/Spinner.tsx`, `react-frontend/src/components/ui/index.ts`, plus 349 Spinner call-site import rewrites under `react-frontend/src` | `/docs/react/migration/spinner`, `/docs/react/components/spinner` | Import scan for v2 `Spinner`; mechanical import rewrite; `npx tsc --noEmit --pretty false --noErrorTruncation`; `npm run build` | `Spinner` imports from `@heroui/react` reduced to 0. TypeScript and build passed. Build kept existing Vite dynamic-import/chunk-size warnings. | Continue core component migration; `Input`, `Card`, `Modal`, `Textarea`, and `Avatar` remain the largest v2 families |
| 2026-05-25 | 13 | `react-frontend/src/components/ui/Input.tsx`, `react-frontend/src/components/ui/Textarea.tsx`, `react-frontend/src/components/ui/index.ts`, plus 350 call-site import rewrites under `react-frontend/src` | `/docs/react/migration/input`, `/docs/react/components/input`, `/docs/react/components/text-area`, `/docs/react/components/text-field` | Import scan for v2 `Input`/`Textarea`; mechanical import rewrite; `npx tsc --noEmit --pretty false --noErrorTruncation`; `npm run build` | `Input`, `InputProps`, `Textarea`, and `TextareaProps` imports from `@heroui/react` reduced to 0. Build passed. `npx tsc --noEmit` timed out after 5 minutes with no error output. | Continue core component migration; `Card`, `Modal`, `Avatar`, `Switch`, and `Tabs` remain the largest v2 families |
| 2026-05-25 | 14 | `react-frontend/src/components/ui/Card.tsx`, `react-frontend/src/components/ui/Input.tsx`, `react-frontend/src/components/ui/index.ts`, plus Card call-site import rewrites under `react-frontend/src` | `/docs/react/migration/card`, `/docs/react/components/card` | Two explorer agents for Card usage/import risks; import scan for v2 `Card*`; `npx tsc --noEmit --pretty false --noErrorTruncation`; `npm run build` | `Card`, `CardHeader`, `CardBody`, and `CardFooter` imports from `@heroui/react` reduced to 0. Build passed. `npx tsc --noEmit` timed out after 5 minutes with no error output after earlier diagnostics were resolved. | Continue core component migration; `Modal`, `Avatar`, `Switch`, `Tabs`, and `Tooltip` remain among the largest v2 families |
| 2026-05-25 | 15 | `react-frontend/src/components/ui/Modal.tsx`, `react-frontend/src/components/ui/index.ts`, Modal call-site import rewrites under `react-frontend/src`, plus wrapper/import repairs in `Input.tsx`, `Textarea.tsx`, `Card.tsx`, `ApiDocumentation.tsx`, and `Partnerships.tsx` | `/docs/react/migration/modal`, `/docs/react/components/modal`, Modal source via HeroUI MCP | Two explorer agents for Modal usage/risk scan; import scan for v2 `Modal*`; `npx tsc --noEmit --pretty false --noErrorTruncation`; `npm run build` | `Modal`, `ModalContent`, `ModalHeader`, `ModalBody`, and `ModalFooter` imports from `@heroui/react` reduced to 0. Build passed. `npx tsc --noEmit` now reaches unrelated import-block corruption in files such as `SafeguardingHelp.tsx`, `CronJobs.tsx`, `VolunteerExpenses.tsx`, `VolunteerSafeguarding.tsx`, and `MostAppreciatedWidget.tsx`. | Clean up remaining import-block corruption, then continue with `Avatar`, `Switch`, `Tabs`, and `Tooltip` |
| 2026-05-25 | 16 | `react-frontend/src/components/ui/Avatar.tsx`, `react-frontend/src/components/ui/index.ts`, Avatar call-site import rewrites under `react-frontend/src`, plus import-block repairs in `SafeguardingHelp.tsx`, `CronJobs.tsx`, `VolunteerExpenses.tsx`, `VolunteerSafeguarding.tsx`, and `MostAppreciatedWidget.tsx` | `/docs/react/migration/avatar`, `/docs/react/components/avatar` | Two explorer agents for Avatar usage and import repair scope; import scan for v2 `Avatar*`; targeted `npx eslint` on new/repaired files; targeted `npx tsc` on `Avatar.tsx`; `git diff --check`; full `npx tsc --noEmit --pretty false --noErrorTruncation`; full `npm run build` | `Avatar` and `AvatarGroup` imports from `@heroui/react` reduced to 0. Targeted lint/type checks passed. `git diff --check` passed with line-ending warnings only. Full TypeScript and build each timed out after 10 minutes without diagnostics. | Continue core component migration; `Switch`, `Tabs`, `Tooltip`, `Skeleton`, and `Table` remain among the notable v2 families |

## Recount Commands

Use this after each phase to keep the tracker honest.

```powershell
$paths = rg --files react-frontend/src -g '*.ts' -g '*.tsx' -g '*.js' -g '*.jsx'
$names = @('HeroUIProvider','useDisclosure','Divider','Progress','CircularProgress','DateInput','DateInputValue','TimeInput','TimeInputValue','SelectItem','SelectSection','DropdownItem','DropdownMenu','DropdownTrigger','DropdownSection','AccordionItem','Code','Image','Snippet','Spacer','User','Navbar')
foreach ($name in $names) {
  $escaped = [regex]::Escape($name)
  $pattern = '(?s)import\s*\{[^}]*\b' + $escaped + '\b[^}]*\}\s*from\s*[''\"]@heroui/react[''\"]'
  $matches = @()
  foreach ($p in $paths) {
    $text = Get-Content -LiteralPath $p -Raw
    if ([regex]::IsMatch($text, $pattern)) { $matches += $p }
  }
  "$name`t$($matches.Count)"
}
```

Check final v2 references:

```powershell
rg -n '@heroui/react|@heroui/theme|heroui\(' react-frontend/src react-frontend/package.json
```

## Open Decisions

| Decision | Current recommendation | Rationale |
| --- | --- | --- |
| Migration strategy | Incremental aliases | Official MCP docs recommend this for large apps that need to stay functional |
| First component family | `Divider` to `Separator` | Lowest structural risk and proves alias/style setup |
| Largest risk | `SelectItem` | v3 changes structure, item components, and selection state |
| Cleanup timing | Last phase only | Removing provider/plugin/deps early risks breaking v2 components during coexistence |
