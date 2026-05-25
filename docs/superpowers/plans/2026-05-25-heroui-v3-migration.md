# HeroUI v3 Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrate the React frontend from HeroUI v2 to HeroUI v3 while keeping the app testable throughout the process.

**Architecture:** Use the official HeroUI v3 incremental migration strategy with aliased v3 packages first, because the app has 827 `@heroui/react` import files and many structural component migrations. Migrate one component family at a time, update the living tracker after each slice, and only remove v2 dependencies after all v2 imports and removed v2 components are gone.

**Tech Stack:** React 19.2.x, TypeScript, Vite, Tailwind CSS 4.x, HeroUI v2, HeroUI v3 via `@heroui-v3/react` alias during coexistence, Vitest, Playwright.

---

## Required Source Of Truth

Before editing any HeroUI component family, use the `heroui-react` MCP server and record the checked docs in `docs/heroui-v3-migration-tracker.md`.

HeroUI MCP docs already checked while creating this plan:

- `/docs/react/migration`
- `/docs/react/migration/incremental-migration`
- `/docs/react/migration/hooks`
- `/docs/react/migration/styling`

Component docs that must be checked immediately before implementation:

- `/docs/react/migration/divider`
- `/docs/react/migration/progress`
- `/docs/react/migration/select`
- `/docs/react/migration/dropdown`
- `/docs/react/migration/accordion`
- `/docs/react/migration/timeinput`
- `/docs/react/migration/code`
- `/docs/react/migration/image`
- `/docs/react/migration/snippet`
- `/docs/react/migration/modal`

## Current Scope Snapshot

Scan command:

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

Counts from 2026-05-25:

| Import | Files |
| --- | ---: |
| `HeroUIProvider` | 66 |
| `useDisclosure` | 97 |
| `Divider` | 126 |
| `Progress` | 51 |
| `CircularProgress` | 0 |
| `DateInput` / `DateInputValue` | 0 |
| `TimeInput` | 3 |
| `SelectItem` | 197 |
| `SelectSection` | 0 |
| `DropdownItem` / `DropdownMenu` / `DropdownTrigger` | 45 |
| `DropdownSection` | 2 |
| `AccordionItem` | 14 |
| `Code` | 5 |
| `Image` | 1 |
| `Snippet` | 3 |
| `Spacer` | 0 |

Package baseline:

- `react`: `^19.2.6`
- `react-dom`: `^19.2.6`
- `tailwindcss`: `^4.0.0`
- `@tailwindcss/vite`: `^4.3.0`
- `@heroui/react`: `^2.6.0`
- `@heroui/theme`: `^2.4.0`
- `framer-motion`: `^11.0.0`
- HeroUI v2 plugin config exists in `react-frontend/src/hero.ts`.

## File Structure

- Modify: `react-frontend/package.json` and lockfile
  - Add temporary v3 aliases.
  - Keep v2 dependencies during coexistence.
- Modify: `react-frontend/src/styles/index.css` or the active global CSS entry
  - Import `@heroui-v3/styles` after `tailwindcss` when coexistence starts.
- Modify: `react-frontend/src/hero.ts`
  - Keep v2 plugin until final cleanup.
  - Remove it only after v2 imports are gone.
- Modify: `react-frontend/src/App.tsx`
  - Remove `HeroUIProvider` only when no v2 components require it.
- Modify: `react-frontend/src/test/test-utils.tsx`
  - Align provider setup with the currently active migration phase.
- Modify: component/page files under `react-frontend/src`
  - Migrate component families in the sequence below.
- Update every task: `docs/heroui-v3-migration-tracker.md`
  - Record docs checked, files touched, validation command, and result.

## Task Order

### Task 1: Establish Coexistence Dependencies

**Files:**
- Modify: `react-frontend/package.json`
- Modify: `react-frontend/package-lock.json`
- Modify: active frontend CSS entry, identified by `rg -n '@import "tailwindcss"|tailwindcss' react-frontend/src -g '*.css'`
- Modify: `docs/heroui-v3-migration-tracker.md`

- [ ] **Step 1: Check MCP docs**

Use HeroUI MCP:

```text
/docs/react/migration/incremental-migration
/docs/react/getting-started/quick-start
/docs/react/migration/styling
```

Record those paths in the tracker row for Task 1.

- [ ] **Step 2: Add v3 aliases**

From `react-frontend`, run:

```powershell
npm install @heroui-v3/react@npm:@heroui/react@latest @heroui-v3/styles@npm:@heroui/styles@latest
```

Expected: `package.json` and `package-lock.json` include `@heroui-v3/react` and `@heroui-v3/styles`, while existing `@heroui/react` and `@heroui/theme` remain.

- [ ] **Step 3: Add v3 styles for coexistence**

Find the global CSS entry:

```powershell
rg -n '@import "tailwindcss"|tailwindcss' react-frontend/src -g '*.css'
```

Add the v3 styles import after Tailwind:

```css
@import "tailwindcss";
@import "@heroui-v3/styles";
```

If the file already has other imports, preserve their relative order and keep Tailwind first.

- [ ] **Step 4: Verify install and CSS build**

Run:

```powershell
cd react-frontend
npx tsc --noEmit
npm run build
```

Expected: no new TypeScript or build failures caused by the aliases or CSS import.

- [ ] **Step 5: Update tracker**

In `docs/heroui-v3-migration-tracker.md`, mark Task 1 as complete with the exact commands and result.

### Task 2: Migrate Low-Risk Separator Usage

**Files:**
- Modify files importing `Divider` from `@heroui/react`
- Update: `docs/heroui-v3-migration-tracker.md`

- [ ] **Step 1: Check MCP docs**

Use HeroUI MCP:

```text
/docs/react/migration/divider
```

Record the doc path in the tracker.

- [ ] **Step 2: List files**

Run:

```powershell
rg -l 'import\s*\{[^}]*\bDivider\b[^}]*\}\s*from\s*[''"]@heroui/react[''"]' react-frontend/src -g '*.ts' -g '*.tsx'
```

Expected baseline: about 126 files.

- [ ] **Step 3: Migrate one vertical toolbar file first**

Start with one compact file such as `react-frontend/src/admin/components/RichTextEditor.tsx` or another file from the list. Change:

```tsx
import { Divider } from '@heroui/react';
```

to:

```tsx
import { Separator } from '@heroui-v3/react';
```

Change JSX:

```tsx
<Divider orientation="vertical" className="h-5 mx-1" />
```

to:

```tsx
<Separator orientation="vertical" className="h-5 mx-1" />
```

- [ ] **Step 4: Verify the first slice**

Run:

```powershell
cd react-frontend
npx tsc --noEmit
```

Expected: no TypeScript errors from the migrated file.

- [ ] **Step 5: Batch the remaining simple `Divider` files**

For each remaining `Divider` import, replace `Divider` with `Separator` from `@heroui-v3/react` only when the file does not also need structural HeroUI v3 work in the same import block.

- [ ] **Step 6: Verify and update tracker**

Run:

```powershell
cd react-frontend
npx tsc --noEmit
npm run build
```

Update the tracker with remaining `Divider` count from:

```powershell
rg -l '\bDivider\b' react-frontend/src -g '*.ts' -g '*.tsx'
```

### Task 3: Remove Or Fence Removed v2 Components

**Files:**
- Modify files importing `Code`, `Image`, or `Snippet` from `@heroui/react`
- Update: `docs/heroui-v3-migration-tracker.md`

- [ ] **Step 1: Check MCP docs**

Use HeroUI MCP:

```text
/docs/react/migration/code
/docs/react/migration/image
/docs/react/migration/snippet
```

- [ ] **Step 2: List removed-component usage**

Run:

```powershell
rg -n 'import\s*\{[^}]*\b(Code|Image|Snippet)\b[^}]*\}\s*from\s*[''"]@heroui/react[''"]' react-frontend/src -g '*.ts' -g '*.tsx'
```

Expected baseline:

- `Code`: 5 files
- `Image`: 1 file
- `Snippet`: 3 files

- [ ] **Step 3: Replace `Code`**

For each HeroUI `Code` import, replace it with semantic inline markup:

```tsx
<code className="rounded bg-default px-1.5 py-0.5 font-mono text-sm">
  {value}
</code>
```

Preserve translation usage and do not introduce hardcoded end-user strings.

- [ ] **Step 4: Replace `Image`**

Replace HeroUI `Image` with native `img` or an existing app image wrapper. Preserve `alt`, `src`, dimensions, lazy loading, fallback behavior, and existing Tailwind classes.

- [ ] **Step 5: Replace `Snippet`**

Replace HeroUI `Snippet` with an app-local copy snippet pattern using a button and `navigator.clipboard.writeText`. Use translated button/label strings if any visible text is added.

- [ ] **Step 6: Verify**

Run:

```powershell
cd react-frontend
npx tsc --noEmit
npm run build
```

Update tracker with remaining removed-component count.

### Task 4: Migrate Progress Components

**Files:**
- Modify files importing `Progress` from `@heroui/react`
- Update tests near the changed files when available
- Update: `docs/heroui-v3-migration-tracker.md`

- [ ] **Step 1: Check MCP docs**

Use HeroUI MCP:

```text
/docs/react/migration/progress
```

- [ ] **Step 2: List files**

Run:

```powershell
rg -l 'import\s*\{[^}]*\bProgress\b[^}]*\}\s*from\s*[''"]@heroui/react[''"]' react-frontend/src -g '*.ts' -g '*.tsx'
```

Expected baseline: about 51 files.

- [ ] **Step 3: Convert one simple progress bar**

Change:

```tsx
<Progress value={value} aria-label={label} className="h-2" />
```

to:

```tsx
<ProgressBar value={value} aria-label={label} className="h-2">
  <ProgressBar.Track>
    <ProgressBar.Fill />
  </ProgressBar.Track>
</ProgressBar>
```

Import `ProgressBar` from `@heroui-v3/react`.

- [ ] **Step 4: Convert labeled progress bars**

Change:

```tsx
<Progress label={label} value={value} showValueLabel />
```

to:

```tsx
<ProgressBar value={value}>
  <Label>{label}</Label>
  <ProgressBar.Output />
  <ProgressBar.Track>
    <ProgressBar.Fill />
  </ProgressBar.Track>
</ProgressBar>
```

Import `Label` from `@heroui-v3/react` when needed.

- [ ] **Step 5: Map color props**

Use the official mapping:

- `color="primary"` becomes `color="accent"`
- `color="secondary"` requires a local design decision; use `color="default"` unless the surrounding UI needs accent emphasis.
- `success`, `warning`, and `danger` stay semantic.

- [ ] **Step 6: Verify**

Run:

```powershell
cd react-frontend
npx tsc --noEmit
npm run build
```

Update tracker with remaining `Progress` count.

### Task 5: Migrate TimeInput Usage

**Files:**
- Modify files importing `TimeInput` from `@heroui/react`
- Update tests near the changed files when available
- Update: `docs/heroui-v3-migration-tracker.md`

- [ ] **Step 1: Check MCP docs**

Use HeroUI MCP:

```text
/docs/react/migration/timeinput
```

- [ ] **Step 2: List files**

Run:

```powershell
rg -l 'import\s*\{[^}]*\bTimeInput\b[^}]*\}\s*from\s*[''"]@heroui/react[''"]' react-frontend/src -g '*.ts' -g '*.tsx'
```

Expected baseline: 3 files.

- [ ] **Step 3: Convert each field**

Change a v2 field:

```tsx
<TimeInput label={label} name={name} value={value} onChange={setValue} />
```

to v3 structure:

```tsx
<TimeField name={name} value={value} onChange={setValue}>
  <Label>{label}</Label>
  <DateInputGroup>
    <DateInputGroup.Input>
      {(segment) => <DateInputGroup.Segment segment={segment} />}
    </DateInputGroup.Input>
  </DateInputGroup>
</TimeField>
```

Import `TimeField`, `DateInputGroup`, and `Label` from `@heroui-v3/react`.

- [ ] **Step 4: Verify**

Run:

```powershell
cd react-frontend
npx tsc --noEmit
npm run build
```

Update tracker with remaining `TimeInput` count.

### Task 6: Migrate Dropdown Family

**Files:**
- Modify files importing `DropdownTrigger`, `DropdownMenu`, `DropdownItem`, or `DropdownSection`
- Update tests near menus when available
- Update: `docs/heroui-v3-migration-tracker.md`

- [ ] **Step 1: Check MCP docs**

Use HeroUI MCP:

```text
/docs/react/migration/dropdown
```

- [ ] **Step 2: List files**

Run:

```powershell
rg -l 'import\s*\{[^}]*\b(DropdownTrigger|DropdownMenu|DropdownItem|DropdownSection)\b[^}]*\}\s*from\s*[''"]@heroui/react[''"]' react-frontend/src -g '*.ts' -g '*.tsx'
```

Expected baseline: about 45 files.

- [ ] **Step 3: Convert one menu**

Change:

```tsx
<Dropdown>
  <DropdownTrigger>
    <Button>Actions</Button>
  </DropdownTrigger>
  <DropdownMenu onAction={handleAction}>
    <DropdownItem key="edit" startContent={<Pencil size={14} />}>
      {t('actions.edit')}
    </DropdownItem>
  </DropdownMenu>
</Dropdown>
```

to:

```tsx
<Dropdown>
  <Dropdown.Trigger>
    <Button>Actions</Button>
  </Dropdown.Trigger>
  <Dropdown.Popover>
    <Dropdown.Menu onAction={handleAction}>
      <Dropdown.Item id="edit" textValue={t('actions.edit')}>
        <Pencil size={14} />
        <Label>{t('actions.edit')}</Label>
      </Dropdown.Item>
    </Dropdown.Menu>
  </Dropdown.Popover>
</Dropdown>
```

Import `Dropdown` and `Label` from `@heroui-v3/react`.

- [ ] **Step 4: Preserve item behavior**

For every item:

- Move `key` identity to `id`.
- Keep `key` only when rendering arrays with `.map`.
- Convert `title` to `<Label>`.
- Convert `description` to `<Description>`.
- Convert `shortcut` to `<Kbd slot="keyboard">`.
- Convert `color="danger"` to `variant="danger"`.
- Replace `showDivider` with `<Separator />`.

- [ ] **Step 5: Verify**

Run:

```powershell
cd react-frontend
npx tsc --noEmit
npm run build
```

Run focused tests for any changed tested menu files, then update tracker.

### Task 7: Migrate Accordion Family

**Files:**
- Modify files importing `AccordionItem`
- Update tests near changed accordions when available
- Update: `docs/heroui-v3-migration-tracker.md`

- [ ] **Step 1: Check MCP docs**

Use HeroUI MCP:

```text
/docs/react/migration/accordion
```

- [ ] **Step 2: List files**

Run:

```powershell
rg -l 'import\s*\{[^}]*\bAccordionItem\b[^}]*\}\s*from\s*[''"]@heroui/react[''"]' react-frontend/src -g '*.ts' -g '*.tsx'
```

Expected baseline: about 14 files.

- [ ] **Step 3: Convert one item**

Change:

```tsx
<AccordionItem key="faq" title={title}>
  {body}
</AccordionItem>
```

to:

```tsx
<Accordion.Item id="faq">
  <Accordion.Heading>
    <Accordion.Trigger>
      {title}
      <Accordion.Indicator />
    </Accordion.Trigger>
  </Accordion.Heading>
  <Accordion.Panel>
    <Accordion.Body>{body}</Accordion.Body>
  </Accordion.Panel>
</Accordion.Item>
```

Import `Accordion` from `@heroui-v3/react`.

- [ ] **Step 4: Convert state props**

Use official mapping:

- `selectedKeys` -> `expandedKeys`
- `defaultSelectedKeys` -> `defaultExpandedKeys`
- `onSelectionChange` -> `onExpandedChange`
- `selectionMode="multiple"` -> `allowsMultipleExpanded`

- [ ] **Step 5: Verify**

Run:

```powershell
cd react-frontend
npx tsc --noEmit
npm run build
```

Update tracker with remaining `AccordionItem` count.

### Task 8: Migrate Select Family

**Files:**
- Modify files importing `SelectItem`
- Update tests near changed selects when available
- Update: `docs/heroui-v3-migration-tracker.md`

- [ ] **Step 1: Check MCP docs**

Use HeroUI MCP:

```text
/docs/react/migration/select
```

- [ ] **Step 2: List files**

Run:

```powershell
rg -l 'import\s*\{[^}]*\bSelectItem\b[^}]*\}\s*from\s*[''"]@heroui/react[''"]' react-frontend/src -g '*.ts' -g '*.tsx'
```

Expected baseline: about 197 files.

- [ ] **Step 3: Migrate one simple static select**

Change:

```tsx
<Select label={label} selectedKeys={selectedKeys} onSelectionChange={setSelectedKeys}>
  <SelectItem key="active">{t('status.active')}</SelectItem>
  <SelectItem key="archived">{t('status.archived')}</SelectItem>
</Select>
```

to:

```tsx
<Select value={value} onChange={setValue} placeholder={placeholder}>
  <Label>{label}</Label>
  <Select.Trigger>
    <Select.Value />
    <Select.Indicator />
  </Select.Trigger>
  <Select.Popover>
    <ListBox>
      <ListBox.Item id="active" textValue={t('status.active')}>
        {t('status.active')}
        <ListBox.ItemIndicator />
      </ListBox.Item>
      <ListBox.Item id="archived" textValue={t('status.archived')}>
        {t('status.archived')}
        <ListBox.ItemIndicator />
      </ListBox.Item>
    </ListBox>
  </Select.Popover>
</Select>
```

Import `Select`, `Label`, and `ListBox` from `@heroui-v3/react`.

- [ ] **Step 4: Convert selection state**

Use official mapping:

- `selectedKeys` -> `value`
- `defaultSelectedKeys` -> `defaultValue`
- `onSelectionChange` -> `onChange`
- Single value type becomes `Key | null`
- Multiple value type becomes `Key[]`

- [ ] **Step 5: Convert mapped items**

For `.map()` items, preserve React reconciliation:

```tsx
{items.map((item) => (
  <ListBox.Item key={item.id} id={item.id} textValue={item.label}>
    {item.label}
    <ListBox.ItemIndicator />
  </ListBox.Item>
))}
```

- [ ] **Step 6: Verify each domain slice**

Work domain by domain, for example:

```powershell
cd react-frontend
npx tsc --noEmit
npm test -- --run src/pages/exchanges/ExchangesPage.test.tsx
npm run build
```

Use the nearest existing test for each changed domain and update tracker after each batch.

### Task 9: Migrate useDisclosure And Modal State

**Files:**
- Modify files importing `useDisclosure`
- Update modal tests near changed files
- Update: `docs/heroui-v3-migration-tracker.md`

- [ ] **Step 1: Check MCP docs**

Use HeroUI MCP:

```text
/docs/react/migration/hooks
/docs/react/migration/modal
```

- [ ] **Step 2: List files**

Run:

```powershell
rg -l 'import\s*\{[^}]*\buseDisclosure\b[^}]*\}\s*from\s*[''"]@heroui/react[''"]' react-frontend/src -g '*.ts' -g '*.tsx'
```

Expected baseline: about 97 files.

- [ ] **Step 3: Convert state API**

Change:

```tsx
const { isOpen, onOpen, onClose, onOpenChange } = useDisclosure();
```

to:

```tsx
const modalState = useOverlayState();
```

Use:

- `isOpen` -> `modalState.isOpen`
- `onOpen` -> `modalState.open`
- `onClose` -> `modalState.close`
- `onOpenChange` as a prop -> `modalState.setOpen`
- toggle behavior -> `modalState.toggle`

- [ ] **Step 4: Prefer local names for multiple modals**

For multiple modals in one file, name each state by purpose:

```tsx
const deleteModalState = useOverlayState();
const editModalState = useOverlayState();
```

- [ ] **Step 5: Verify**

Run:

```powershell
cd react-frontend
npx tsc --noEmit
npm run build
```

Run nearby modal tests for changed files and update tracker.

### Task 10: Remove HeroUIProvider And v2 Tailwind Plugin

**Files:**
- Modify: `react-frontend/src/App.tsx`
- Modify: `react-frontend/src/test/test-utils.tsx`
- Modify: `react-frontend/src/hero.ts`
- Modify: frontend Tailwind/Vite config files that import `hero.ts`
- Modify: `react-frontend/package.json` and lockfile
- Update: `docs/heroui-v3-migration-tracker.md`

- [ ] **Step 1: Confirm v2 imports are gone**

Run:

```powershell
rg -n '@heroui/react|@heroui/theme|from "./hero"|from ''./hero''' react-frontend/src react-frontend/package.json
```

Expected before this task can proceed: no app source imports from v2 `@heroui/react` remain, except package metadata before cleanup.

- [ ] **Step 2: Remove provider wrappers**

Remove `HeroUIProvider` from `react-frontend/src/App.tsx` and `react-frontend/src/test/test-utils.tsx`. Preserve router navigation behavior by using existing React Router APIs directly where needed.

- [ ] **Step 3: Remove v2 theme plugin**

Remove `react-frontend/src/hero.ts` only after no config imports it. Remove any `heroui()` plugin references.

- [ ] **Step 4: Swap aliases to final v3 package names**

Update imports:

```tsx
from '@heroui-v3/react'
```

to:

```tsx
from '@heroui/react'
```

Then remove v2 packages and aliases:

```powershell
cd react-frontend
npm uninstall @heroui/theme framer-motion
npm install @heroui/react@latest @heroui/styles@latest
```

- [ ] **Step 5: Verify final state**

Run:

```powershell
cd react-frontend
npx tsc --noEmit
npm run build
npm test
```

Run smoke E2E when the frontend is available:

```powershell
npx playwright test e2e/tests/smoke.spec.ts --grep '@smoke' --project=chromium-modern
```

Update tracker with final package versions, remaining v2 references, and validation result.

## Self-Review Notes

- The plan uses the HeroUI MCP as required by `AGENTS.md` and `CLAUDE.md`.
- The plan avoids treating structural v3 migrations as find-and-replace work.
- The largest risk is `Select`, because v3 changes structure, item components, and selection state.
- The second largest risk is `useDisclosure` plus modal structure, because many files have multiple modal states.
- The final cleanup task is intentionally gated by a zero-v2-import scan.
