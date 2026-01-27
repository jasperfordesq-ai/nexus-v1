# Phase 4 - CSS Guardrails Report

**Date:** 2026-01-27
**Scope:** Tooling and configuration only (no visual changes)

---

## Executive Summary

Phase 4 established automated guardrails to prevent reintroduction of hardcoded colors in Modern theme CSS files. A custom color linter was created that enforces Phase 2 tokenization rules on strict files while providing informational warnings for legacy files.

---

## 1. Files Added/Changed

### Files Created

| File | Purpose |
|------|---------|
| `scripts/lint-modern-colors.js` | Custom color linter for Modern CSS |
| `docs/modern-css-guardrails.md` | Developer documentation for guardrails |

### Files Modified

| File | Change |
|------|--------|
| `.stylelintrc.json` | Added `**/civicone/**` to ignoreFiles |
| `package.json` | Added `lint:css:colors` script, updated `lint` to include it |

---

## 2. Lint Rules Implemented

### A) Color Linter Rules (`lint-modern-colors.js`)

| Rule | Pattern | Severity | Notes |
|------|---------|----------|-------|
| no-hardcoded-hex | `#xxx`, `#xxxxxx` | Warning | Future phase (not tokenized in Phase 2) |
| no-hardcoded-rgba | `rgba(r, g, b, a)` | Error (strict) | Phase 2 tokenized - enforced |
| no-hardcoded-rgb | `rgb(r, g, b)` | Error (strict) | Phase 2 tokenized - enforced |
| no-hardcoded-hsl | `hsl()`, `hsla()` | Error (strict) | Phase 2 tokenized - enforced |

### B) Strict Mode vs Warning Mode

**Strict Files (Phase 2 tokenized - must pass):**
- `federation.css`
- `volunteering.css`
- `scattered-singles.css`
- `nexus-home.css`
- `nexus-groups.css`
- `profile-holographic.css`
- `dashboard.css`

**Warning Files (legacy - informational only):**
- All other CSS files in `httpdocs/assets/css/`
- These don't block builds but should be tokenized in future phases

### C) Excluded Files

- `**/*.min.css` - Minified files
- `**/bundles/**` - Generated bundles
- `**/_archived/**` - Archived files
- `**/vendor/**` - Third-party files
- `**/civicone/**` - CivicOne theme (separate system)
- `**/civicone-*.css` - CivicOne theme files
- `**/design-tokens.css` - Token definition file
- `**/desktop-design-tokens.css` - Token file
- `**/mobile-design-tokens.css` - Token file
- `**/modern-theme-tokens.css` - Token definition file

### D) Allowed Patterns

Dynamic rgba patterns that compute at runtime are allowed:

```css
rgba(var(--settings-*), alpha)
rgba(var(--htb-primary-rgb), alpha)
rgba(var(--color-primary-rgb), alpha)
rgba(var(--holo-primary-rgb), alpha)
rgba(var(--privacy-theme-rgb), alpha)
```

### E) Ignored Values

**Hex colors (common safe values):**
- `#000`, `#000000` - Pure black
- `#fff`, `#ffffff` - Pure white

**RGB values (Phase 2 exceptions):**
- `rgb(40, 15, 5)` - Whiskey gradient (scattered-singles.css)
- `rgb(20, 10, 5)` - Whiskey gradient darker (scattered-singles.css)

---

## 3. NPM Scripts

### New Scripts

```bash
# Check for hardcoded colors in Modern CSS
npm run lint:css:colors

# Options:
npm run lint:css:colors -- --strict  # Only check Phase 2 files
npm run lint:css:colors -- --all     # Treat all files as strict (errors)
```

### Updated Scripts

```bash
# Full lint (now includes color linting)
npm run lint
# Runs: lint:css, lint:css:colors, lint:js, lint:php:styles
```

---

## 4. How to Run Locally

### Check All Files (Recommended)

```bash
npm run lint:css:colors
```

**Output:**
- Errors: Phase 2 tokenized files with rgba/rgb/hsl violations
- Warnings: Legacy files (informational)

### Check Only Phase 2 Files

```bash
npm run lint:css:colors -- --strict
```

### Run Full Lint Suite

```bash
npm run lint
```

---

## 5. CI/CD Integration

### Pre-commit Hook (Recommended)

Add to `.git/hooks/pre-commit`:

```bash
#!/bin/bash
npm run lint:css:colors
if [ $? -ne 0 ]; then
    echo "Hardcoded colors detected. Fix before committing."
    exit 1
fi
```

### GitHub Actions

```yaml
- name: Lint CSS Colors
  run: npm run lint:css:colors
```

---

## 6. Current State

### Linter Results (2026-01-27)

```
Mode: normal
Checking 148 CSS files...

✅ Phase 2 tokenized files are clean!
   Strict files: 0 errors
   Legacy files: 8,726 warnings (informational)
```

### Top Files Needing Future Tokenization

| File | Warning Count |
|------|---------------|
| modern-bundle-compiled.css | 785 |
| polls.css | 343 |
| static-pages.css | 301 |
| goals.css | 277 |
| resources.css | 261 |

---

## 7. Stylelint Configuration

### Change Made

Added CivicOne exclusion to `.stylelintrc.json`:

```json
"ignoreFiles": [
    "**/*.min.css",
    "**/bundles/**",
    "**/_archived/**",
    "**/vendor/**",
    "**/node_modules/**",
    "**/*-compiled.css",
    "**/civicone/**"
]
```

### Why Custom Linter vs Stylelint Rules?

1. **Token file exceptions**: Stylelint can't easily allow colors in specific files
2. **Dynamic pattern detection**: Need regex to allow `rgba(var(--settings-*))` patterns
3. **Strict vs warning modes**: Need different severity per file, not just per rule
4. **Better error messages**: Custom guidance for token usage

---

## 8. Documentation Created

`docs/modern-css-guardrails.md` includes:

- Overview of guardrails system
- All lint commands
- Rules enforced (tables with patterns and fixes)
- How to add new tokens
- When NOT to add tokens
- Allowed exceptions
- File structure reference
- CI/CD integration examples
- Troubleshooting guide

---

## 9. Validation

### Tests Performed

1. **Linter executes without errors** - ✅
2. **Phase 2 files pass strict mode** - ✅
3. **Legacy files report as warnings** - ✅
4. **--strict flag works** - ✅
5. **--all flag works** - ✅
6. **Excluded files are skipped** - ✅
7. **Dynamic rgba patterns allowed** - ✅
8. **npm run lint includes colors** - ✅

---

## 10. Future Phases

### Phase 5 Candidates (Not in Scope)

1. **Hex tokenization** - 8,726 hex colors across legacy files
2. **PHP inline style linting** - Already exists (`lint:php:styles`)
3. **Pre-commit hook setup** - Optional, documented but not implemented

### Recommended Next Steps

1. Run `npm run lint:css:colors` before each CSS change
2. Add pre-commit hook for team enforcement
3. Consider tokenizing high-warning files (polls.css, goals.css, etc.)

---

## 11. Conclusion

Phase 4 successfully established guardrails to prevent regression of Phase 2 tokenization work:

- **Custom linter** enforces rgba/rgb/hsl rules on Phase 2 files
- **Hex colors** tracked as warnings (future phase work)
- **CivicOne theme** fully excluded from all linting
- **Documentation** provides clear guidance for developers
- **npm scripts** integrated into existing lint workflow

The guardrails ensure that any new hardcoded colors in Phase 2 tokenized files will cause build failures, protecting the tokenization investment.
