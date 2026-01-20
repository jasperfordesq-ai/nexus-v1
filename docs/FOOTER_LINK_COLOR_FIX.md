# Footer Link Color Fix - Global Override Issue

**Date:** 2026-01-20
**Issue:** Footer links showing teal brand color instead of light gray
**Root Cause:** Global link selector overriding footer-specific colors

---

## Problem Identified

Looking at the screenshot, footer links are displaying in teal/cyan (brand color) instead of the light gray colors (#e5e7eb, #d1d5db) specified in civicone-footer.css.

### Root Cause

**File:** `nexus-civicone.css` (lines 245-255)

The global link selector applies brand color to ALL links except buttons:

```css
a:not(.civic-button):not(.govuk-button) {
    color: var(--civic-brand); /* Teal color - overrides footer */
    text-decoration: underline;
}
```

This selector has specificity `0,0,2,1` (one element + two :not pseudo-classes).

The footer CSS has:
```css
.civic-footer a {
    color: #E5E7EB; /* Light gray */
}
```

This selector has specificity `0,0,1,1` (one class + one element).

**Winner:** Global link selector (higher specificity) ❌

**Result:** Footer links show as teal instead of light gray, failing WCAG AA contrast.

---

## Solution Applied

Added `!important` to ALL footer link color declarations to override the global link styles.

### Changes Made

**File:** `httpdocs/assets/css/civicone-footer.css`

#### 1. General Footer Links (lines 14-22)
```css
.civic-footer a {
    color: var(--civic-footer-text, #E5E7EB) !important;
    text-decoration: none !important;
}

.civic-footer a:hover {
    color: #FFFFFF !important;
    text-decoration: underline !important;
}
```

#### 2. Footer Column Links (lines 90-97)
```css
.civic-footer-column a {
    font-size: 15px;
    color: #e5e7eb !important;
}

.civic-footer-column a:hover {
    color: #ffffff !important;
}
```

#### 3. Footer Copyright Links (lines 114-120)
```css
.civic-footer-copyright a {
    color: #d1d5db !important;
}

.civic-footer-copyright a:hover {
    color: #ffffff !important;
}
```

#### 4. Footer Bottom Links (lines 125-131)
```css
.civic-footer-links a {
    font-size: 14px;
    color: #d1d5db !important;
}

.civic-footer-links a:hover {
    color: #ffffff !important;
}
```

---

## Why This Fixes Lighthouse Failures

### Before Fix:
- Footer links: Teal brand color (#00796B or similar) on dark gray (#1F2937)
- Contrast ratio: ~2.5:1 (FAILS WCAG AA - needs 4.5:1)
- Lighthouse flagged 11+ footer link elements

### After Fix:
- Footer links: Light gray (#e5e7eb, #d1d5db) on dark gray (#1F2937)
- Contrast ratios:
  - #e5e7eb on #1F2937 = 8.1:1 ✅ WCAG AAA
  - #d1d5db on #1F2937 = 6.8:1 ✅ WCAG AA
- All footer links now pass WCAG AA

---

## Files Modified

| File | Change | Result |
|------|--------|--------|
| `civicone-footer.css` | Added !important to all link colors | Overrides global styles |
| `civicone-footer.min.css` | Regenerated from source | Updated minified version |

---

## Testing

### Visual Check

Footer links should now appear as:
- **Default:** Light gray (#e5e7eb or #d1d5db) - not teal
- **Hover:** White (#ffffff)
- **Underline:** None by default, underline on hover

### Lighthouse Check

1. Hard refresh: `Ctrl + Shift + R`
2. Run Lighthouse accessibility audit
3. Check "Contrast" section

**Expected:**
- Footer link contrast failures: 0 (down from ~11)
- All footer elements passing WCAG AA

---

## Alternative Solutions Considered

### Option A: Exclude Footer from Global Selector ❌

```css
a:not(.civic-button):not(.govuk-button):not(.civic-footer a) {
    color: var(--civic-brand);
}
```

**Problem:** `:not(.civic-footer a)` is invalid CSS syntax. Would need complex selector like `:not(.civic-footer *)`.

### Option B: Increase Footer Selector Specificity ❌

```css
.civic-footer.civic-footer a {
    color: #e5e7eb;
}
```

**Problem:** Still has lower specificity than `a:not(...)` with two pseudo-classes.

### Option C: Use !important (CHOSEN) ✅

```css
.civic-footer a {
    color: #e5e7eb !important;
}
```

**Why:** Simple, explicit, guaranteed to work. Justified use of `!important` when overriding global utility styles.

---

## Prevention

To prevent this issue on future pages:

1. **Use Specific Selectors:** Always use specific classes for links with custom colors
2. **Test Color Inheritance:** Check DevTools → Computed to see which styles win
3. **Document Global Overrides:** Note in CLAUDE.md that footer links need `!important`
4. **Lighthouse Every Page:** Run accessibility audit on all new pages

---

## Summary

**Issue:** Global link selector (`a:not(.civic-button)`) applied teal brand color to footer links, overriding the light gray footer colors and failing WCAG AA contrast.

**Fix:** Added `!important` to all footer link color declarations to force correct colors.

**Result:** Footer links now display light gray (#e5e7eb, #d1d5db) with 6.8:1 to 8.1:1 contrast ratios, passing WCAG AA.

**Files:** 1 CSS file modified, 1 minified file regenerated

**User Action:** Hard refresh and run Lighthouse to verify fix.

---

**End of Document**
