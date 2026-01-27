# Hotfix Report: Token Syntax Errors

**Date:** 27 January 2026
**Type:** CSS Syntax Error Fix
**Scope:** Modern theme only (CivicOne excluded per rules)

---

## 1. Issue

Phase 6A hex-to-token replacements caused double-replacement syntax errors where `#fff` was partially replaced, resulting in invalid CSS like:

```css
/* BROKEN */
color: var(--color-white)fff;
background: var(--color-white, var(--color-white)fff);
```

---

## 2. Files Fixed

| File | Lines Fixed | Pattern |
|------|-------------|---------|
| `cookie-banner.css` | 1 | `var(--color-white)fff` → `var(--color-white)` |
| `dev-notice-modal.css` | 2 | `var(--color-white)fff` → `var(--color-white)` |
| `members-directory-v1.6.css` | 7 | `var(--color-white, var(--color-white)fff)` → `var(--color-white)` |
| `moj-filter.css` | 2 | `var(--color-white, var(--color-white)fff)` → `var(--color-white)` |

**Total:** 12 syntax errors fixed across 4 files.

---

## 3. Search Performed

Searched for similar double-replacement patterns:
```
Pattern: \)fff|\)000|\)111
Scope: httpdocs/assets/css/**/*.css
```

**Results:**
- Found 12 instances in Modern theme files (all fixed)
- Found 1 instance in `nexus-civicone.css` (excluded per rules)

---

## 4. Verification

### Lint Results

All fixed files pass lint (0 errors, only informational warnings for remaining legacy colors):

| File | Status | Warnings |
|------|--------|----------|
| `cookie-banner.css` | PASS | 15 (legacy) |
| `dev-notice-modal.css` | PASS | 12 (legacy) |
| `members-directory-v1.6.css` | PASS | 39 (legacy) |
| `moj-filter.css` | PASS | 22 (legacy) |

### Remaining (Not Fixed)

- `nexus-civicone.css:17` - CivicOne file, excluded per rules

---

## 5. Root Cause

The Phase 6A replacement scripts used case-insensitive regex matching for `#fff` which partially matched hex values that were already part of fallback patterns like:

```css
/* Original */
background: var(--color-white, #fff);

/* After replacement - BROKEN */
background: var(--color-white, var(--color-white)fff);
```

The `#fff` inside the fallback was replaced with `var(--color-white)`, but the trailing `fff` from the original hex remained.

---

## 6. Prevention

For future bulk replacements, the regex should use word boundaries:
```javascript
// Better pattern
const regex = new RegExp(`\\b${hex}\\b`, 'gi');
```

Or explicitly match the full value context:
```javascript
// Even better - match only standalone hex values
const regex = new RegExp(`(?<![a-fA-F0-9])${hex}(?![a-fA-F0-9])`, 'gi');
```

---

**Report Generated:** 27 January 2026
**Fixed By:** Claude Code (Hotfix)
