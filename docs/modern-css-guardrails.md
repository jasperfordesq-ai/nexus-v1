# Modern CSS Guardrails

This document describes the automated guardrails that prevent reintroduction of hardcoded colors in the Modern theme CSS.

---

## Overview

After Phase 1-3 CSS cleanup, the Modern theme uses CSS custom properties (tokens) instead of hardcoded colors. These guardrails ensure new code follows the same pattern.

---

## Linting Commands

```bash
# Run all CSS linters
npm run lint:css

# Check for hardcoded colors in Modern CSS (Phase 4 guardrail)
npm run lint:css:colors

# Check with baseline comparison (fails if warnings increased)
npm run lint:css:colors:baseline

# Output JSON report (for CI/tooling)
npm run lint:css:colors:report

# Check for inline styles in PHP templates
npm run lint:php:styles

# Run all linters (CSS, colors, JS, PHP styles)
npm run lint
```

### CLI Options

The color linter supports several command-line options:

```bash
# Show only top N files (default: 20)
npm run lint:css:colors -- --top 10

# Filter to files matching a pattern
npm run lint:css:colors -- --filter admin
npm run lint:css:colors -- --filter polls.css

# Only check Phase 2 strict files (skip legacy warnings)
npm run lint:css:colors -- --strict

# Treat all files as strict (errors instead of warnings)
npm run lint:css:colors -- --all

# Output machine-readable JSON
npm run lint:css:colors -- --json

# Compare against baseline (fail if warnings increased)
npm run lint:css:colors -- --baseline

# Update baseline after cleanup
npm run lint:css:colors -- --update-baseline
```

---

## Rules Enforced

### 1. No Hardcoded Colors in CSS Files

**Scope:** `httpdocs/assets/css/**/*.css` (excluding CivicOne and token files)

| Pattern | Status | Fix |
|---------|--------|-----|
| `#fff`, `#6366f1`, etc. | Disallowed | Use `var(--color-*)` |
| `rgba(255, 255, 255, 0.5)` | Disallowed | Use `var(--effect-white-50)` |
| `rgb(99, 102, 241)` | Disallowed | Use `var(--color-primary-500)` |
| `hsl(...)` / `hsla(...)` | Disallowed | Use CSS variable |

**Exceptions (allowed):**
- Token files: `design-tokens.css`, `modern-theme-tokens.css`
- Dynamic patterns: `rgba(var(--settings-*), alpha)`
- CivicOne theme files

### 2. No Static Inline Styles in Templates

**Scope:** `views/**/*.php`

| Pattern | Status | Fix |
|---------|--------|-----|
| `style="color: red"` | Disallowed | Extract to CSS class |
| `style="--progress: <?= $val ?>"` | Allowed | CSS custom property with dynamic value |
| `style="width: <?= $width ?>%"` | Allowed | Truly dynamic calculation |

---

## How to Add a New Token

### Color Token (design-tokens.css)

1. Open `httpdocs/assets/css/design-tokens.css`
2. Add to the appropriate section:
   ```css
   /* In the color palette section */
   --color-newcolor-500: #abc123;
   ```
3. Run `npm run build:css` to validate

### Effect Token (modern-theme-tokens.css)

1. Open `httpdocs/assets/css/modern-theme-tokens.css`
2. Find the relevant color section
3. Add following the naming convention:
   ```css
   /* Effect token: --effect-{color}-{opacity} */
   --effect-newcolor-20: rgba(171, 193, 35, 0.2);
   ```
4. Run `npm run build:css` to validate

### Naming Convention

```
Color tokens:      --color-{name}-{shade}
                   --color-primary-500, --color-gray-200

Effect tokens:     --effect-{color}-{opacity}
                   --effect-white-50, --effect-primary-20

Shade variants:    --effect-{color}-{shade}-{opacity}
                   --effect-purple-300-15, --effect-slate-900-80
```

---

## When NOT to Add a Token

### Don't create tokens for:

1. **One-time use values** - If a color is only used once, consider if it should be a token or just different
2. **Near-duplicates** - Use existing token with closest value (e.g., use `-20` not create `-21`)
3. **Component-specific colors** - Use semantic tokens like `--color-success` instead of `--color-green-500`

### Use existing tokens instead:

| Need | Use |
|------|-----|
| Error state | `var(--color-danger)` |
| Success state | `var(--color-success)` |
| Warning | `var(--color-warning)` |
| Primary accent | `var(--color-primary-500)` |
| Muted text | `var(--text-muted)` |
| Card background | `var(--bg-elevated)` |
| Border | `var(--border-default)` |

---

## Allowed Exceptions

### Dynamic rgba with settings variables

These patterns are allowed because they compute at runtime:

```css
/* Allowed: Uses CSS variable for RGB values */
background: rgba(var(--settings-primary), 0.1);
border-color: rgba(var(--htb-primary-rgb), 0.2);
```

### Truly dynamic inline styles

```php
<!-- Allowed: Calculated values from PHP -->
<div style="width: <?= $percentage ?>%">
<div style="--progress: <?= $progress ?>">

<!-- NOT allowed: Static styles -->
<div style="color: red">  <!-- Extract to CSS! -->
```

---

## File Structure

### Token Files (Hardcoded colors ALLOWED here)

```
httpdocs/assets/css/
├── design-tokens.css          # Core color palette, spacing, etc.
├── desktop-design-tokens.css  # Desktop-specific tokens
├── mobile-design-tokens.css   # Mobile-specific tokens
└── modern-theme-tokens.css    # Effect tokens (rgba variants)
```

### CSS Files (Hardcoded colors NOT allowed)

```
httpdocs/assets/css/
├── federation.css             # Must use var(--*)
├── volunteering.css           # Must use var(--*)
├── nexus-home.css             # Must use var(--*)
└── [all other Modern CSS]     # Must use var(--*)
```

### Excluded from color linting

```
httpdocs/assets/css/
├── civicone/**                # CivicOne theme (separate system)
├── civicone-*.css             # CivicOne theme files
├── bundles/**                 # Generated files
├── *.min.css                  # Minified files
└── _archived/**               # Archived files
```

---

## Baseline System

The color linter includes a baseline system to prevent warning count regression over time.

### How It Works

1. **Baseline file**: `scripts/lint-modern-colors.baseline.json` stores the current warning counts
2. **Comparison mode**: `--baseline` flag compares current counts against the baseline
3. **Failure condition**: Build fails if total warnings increase above baseline

### Using the Baseline

```bash
# Check against baseline (use in CI)
npm run lint:css:colors:baseline

# After cleanup work, update the baseline
npm run lint:css:colors -- --update-baseline
```

### After a Cleanup Pass

When you reduce warnings by tokenizing files:

1. Run `npm run lint:css:colors` to verify reduction
2. Run `npm run lint:css:colors -- --update-baseline` to lock in the new lower count
3. Commit the updated `lint-modern-colors.baseline.json`

### Baseline File Structure

```json
{
  "timestamp": "2026-01-27T...",
  "totalWarnings": 8726,
  "totalErrors": 0,
  "typeCounts": { "hex": 1252, "rgba": 7460, "rgb": 2, "hsl": 12 },
  "filesWithWarnings": 130,
  "perFileWarnings": { "file.css": 100, ... }
}
```

---

## CI/CD Integration

### Pre-commit hook (recommended)

Add to `.git/hooks/pre-commit`:

```bash
#!/bin/bash
npm run lint:css:colors
if [ $? -ne 0 ]; then
    echo "Hardcoded colors detected. Fix before committing."
    exit 1
fi
```

### GitHub Actions (if applicable)

```yaml
- name: Lint CSS Colors
  run: npm run lint:css:colors
```

---

## Troubleshooting

### "Hardcoded hex color found"

```
Error: #6366f1 found in federation.css:123
Fix: Replace with var(--color-primary-500)
```

1. Find the hex color in design-tokens.css
2. Use the corresponding CSS variable
3. If no match exists, check if it should be a new token or use closest match

### "Hardcoded rgba() found"

```
Error: rgba(99, 102, 241, 0.2) found in federation.css:456
Fix: Replace with var(--effect-primary-20)
```

1. Find the rgba in modern-theme-tokens.css (search by RGB values)
2. Use the corresponding effect token
3. If no match exists, add new token to modern-theme-tokens.css

### "Static inline style detected"

```
Error: style="color: red" in views/modern/page.php:78
Fix: Extract to CSS class
```

1. Create/use a CSS class in appropriate CSS file
2. Add CSS file to purgecss.config.js if new
3. Replace inline style with class
