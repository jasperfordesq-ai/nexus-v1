# Modern UI Primitives

Token-driven layout and utility classes for consistent UI development in the Modern theme.

**File:** `httpdocs/assets/css/modern-primitives.css`
**Created:** 2026-01-27 (Phase 7)

---

## Philosophy

**Prefer primitives + tokens over writing new one-off CSS.**

These primitives provide:
- Consistent spacing using `--space-*` tokens
- Predictable layouts without custom CSS
- Low specificity for easy overriding
- Accessibility built-in

---

## Layout Primitives

### `.container`

Centered content container with responsive max-width.

```html
<div class="container">
    <!-- Content centered with max-width -->
</div>

<div class="container container--narrow">
    <!-- Narrower container (800px) -->
</div>

<div class="container container--wide">
    <!-- Wider container (1400px) -->
</div>
```

**Customization:**
```css
.my-section {
    --container-max-width: 1000px;
}
```

---

### `.stack`

Vertical layout with consistent spacing between children.

```html
<div class="stack">
    <div>First item</div>
    <div>Second item</div>
    <div>Third item</div>
</div>

<!-- Size variants -->
<div class="stack stack--sm"><!-- Small gap --></div>
<div class="stack stack--md"><!-- Medium gap (default) --></div>
<div class="stack stack--lg"><!-- Large gap --></div>
<div class="stack stack--xl"><!-- Extra large gap --></div>
```

**When to use:** Vertical lists, form fields, card content, section layouts.

---

### `.cluster`

Horizontal layout that wraps, with consistent spacing.

```html
<div class="cluster">
    <span class="tag">Tag 1</span>
    <span class="tag">Tag 2</span>
    <span class="tag">Tag 3</span>
</div>

<!-- Alignment variants -->
<div class="cluster cluster--start"><!-- Align left --></div>
<div class="cluster cluster--center"><!-- Align center --></div>
<div class="cluster cluster--end"><!-- Align right --></div>
<div class="cluster cluster--between"><!-- Space between --></div>
```

**When to use:** Tags, buttons, inline items, action bars.

---

### `.grid`

Simple responsive grid with auto-fit columns.

```html
<!-- Auto-fit grid (default min: 250px) -->
<div class="grid">
    <div>Card 1</div>
    <div>Card 2</div>
    <div>Card 3</div>
    <div>Card 4</div>
</div>

<!-- Fixed column grids (responsive on mobile) -->
<div class="grid grid--2"><!-- 2 columns --></div>
<div class="grid grid--3"><!-- 3 columns --></div>
<div class="grid grid--4"><!-- 4 columns --></div>
```

**Customization:**
```css
.my-grid {
    --grid-min: 200px;  /* Minimum column width */
    --grid-gap: var(--space-6);  /* Custom gap */
}
```

---

### `.sidebar`

Two-column layout with fixed sidebar and fluid content.

```html
<div class="sidebar">
    <aside>
        <!-- Sidebar content (280px default) -->
    </aside>
    <main>
        <!-- Main content (fills remaining space) -->
    </main>
</div>

<!-- Sidebar on right -->
<div class="sidebar sidebar--right">
    <main>Main content</main>
    <aside>Sidebar</aside>
</div>
```

**Customization:**
```css
.my-layout {
    --sidebar-width: 320px;
    --sidebar-gap: var(--space-8);
}
```

---

## Spacing Utilities

### Gap (for flex/grid containers)

| Class | Value |
|-------|-------|
| `.gap-1` | `var(--space-1)` |
| `.gap-2` | `var(--space-2)` |
| `.gap-3` | `var(--space-3)` |
| `.gap-4` | `var(--space-4)` |
| `.gap-5` | `var(--space-5)` |
| `.gap-6` | `var(--space-6)` |
| `.gap-8` | `var(--space-8)` |

### Padding

| Class | Description |
|-------|-------------|
| `.p-2` to `.p-8` | All sides |
| `.px-2` to `.px-6` | Horizontal (inline) |
| `.py-2` to `.py-6` | Vertical (block) |

### Margin (top/bottom only)

| Class | Description |
|-------|-------------|
| `.mt-0` to `.mt-8` | Margin top |
| `.mb-0` to `.mb-8` | Margin bottom |

---

## Typography Utilities

### Text Colors

| Class | Token |
|-------|-------|
| `.text-primary` | `--color-text` |
| `.text-secondary` | `--color-text-secondary` |
| `.text-muted` | `--color-text-muted` |
| `.text-accent` | `--color-primary-500` |
| `.text-success` | `--color-success` |
| `.text-warning` | `--color-warning` |
| `.text-danger` | `--color-danger` |

### Text Sizes

| Class | Token |
|-------|-------|
| `.text-xs` | `--font-size-xs` |
| `.text-sm` | `--font-size-sm` |
| `.text-base` | `--font-size-base` |
| `.text-lg` | `--font-size-lg` |
| `.text-xl` | `--font-size-xl` |
| `.text-2xl` | `--font-size-2xl` |

### Font Weights

| Class | Value |
|-------|-------|
| `.font-normal` | 400 |
| `.font-medium` | 500 |
| `.font-semibold` | 600 |
| `.font-bold` | 700 |

---

## Accessibility Utilities

### `.sr-only`

Visually hidden but accessible to screen readers.

```html
<button>
    <span class="sr-only">Close dialog</span>
    <i class="fa fa-times"></i>
</button>
```

### `.sr-only-focusable`

Hidden until focused (for skip links).

```html
<a href="#main-content" class="sr-only-focusable">
    Skip to main content
</a>
```

### `.focus-ring`

Consistent focus indicator using design tokens.

```html
<button class="custom-button focus-ring">
    Custom Button
</button>
```

---

## Display & Flexbox Utilities

### Display

`.hidden`, `.block`, `.inline`, `.inline-block`, `.flex`, `.inline-flex`, `.grid-display`

### Flexbox

```html
<div class="flex items-center justify-between gap-4">
    <span>Left</span>
    <span>Right</span>
</div>

<div class="flex flex-col gap-2">
    <div>Stacked item 1</div>
    <div>Stacked item 2</div>
</div>
```

| Class | Description |
|-------|-------------|
| `.flex-row` | Row direction |
| `.flex-col` | Column direction |
| `.flex-wrap` | Allow wrapping |
| `.items-center` | Align items center |
| `.justify-between` | Space between |
| `.flex-1` | Grow to fill |
| `.flex-shrink-0` | Don't shrink |

---

## Border & Radius Utilities

| Class | Token |
|-------|-------|
| `.rounded-none` | 0 |
| `.rounded-sm` | `--radius-sm` |
| `.rounded` | `--radius-base` |
| `.rounded-md` | `--radius-md` |
| `.rounded-lg` | `--radius-lg` |
| `.rounded-xl` | `--radius-xl` |
| `.rounded-full` | 9999px |

---

## Example: Combining Primitives

```html
<!-- Card grid layout -->
<div class="container">
    <div class="stack stack--lg">
        <h1 class="text-2xl font-bold">Dashboard</h1>

        <div class="grid grid--3">
            <div class="p-4 rounded-lg">Card 1</div>
            <div class="p-4 rounded-lg">Card 2</div>
            <div class="p-4 rounded-lg">Card 3</div>
        </div>

        <div class="cluster cluster--end">
            <button class="focus-ring">Cancel</button>
            <button class="focus-ring">Save</button>
        </div>
    </div>
</div>
```

```html
<!-- Sidebar layout -->
<div class="container">
    <div class="sidebar">
        <aside class="stack stack--sm p-4">
            <h2 class="font-semibold">Filters</h2>
            <!-- Filter options -->
        </aside>
        <main class="stack">
            <h1 class="text-xl font-bold">Results</h1>
            <!-- Content -->
        </main>
    </div>
</div>
```

---

## Rules & Guidelines

### DO

- **Use primitives first** before writing custom CSS
- **Combine primitives** for complex layouts
- **Use tokens** for all spacing, colors, and typography
- **Keep low specificity** so primitives can be overridden

### DON'T

- **Don't create new one-off utilities** - add to primitives if needed
- **Don't use inline styles** for spacing/layout
- **Don't override primitives with `!important`**
- **Don't use hardcoded pixel values** - use tokens

### When to Create a New Component

Create a new component CSS file when:
1. The pattern is used on **3+ pages**
2. It has **unique interactive states** (hover, focus, active)
3. It requires **complex internal styling** beyond layout
4. It has **semantic meaning** (e.g., `.card`, `.badge`, `.modal`)

Keep using primitives for:
- Simple layout arrangements
- Spacing between elements
- Basic typography styling
- Accessibility helpers

---

## File Location

```
httpdocs/assets/css/modern-primitives.css
```

Loaded in: `views/layouts/modern/partials/css-loader.php` (sync, after tokens)

---

**Documentation updated:** 2026-01-27
