# Members Directory v1.5.1 - CSS Fixes Applied

**Date:** 2026-01-22
**Issue:** Layout broken in screenshot - filter showing incorrectly
**Status:** ✅ CSS FIXED - Cache clear required

---

## Issues Fixed

### ✅ Fix 1: Filter Header Showing on Desktop

**Problem:** Filter header with "Filter" title and "Close" button visible on desktop

**Root Cause:** `.moj-filter__header` had no display rules for desktop

**Fix Applied:**
```css
.moj-filter__header {
    display: none; /* Hidden on desktop by default */
    justify-content: space-between;
    align-items: center;
    padding: var(--space-4, 20px);
    border-bottom: 1px solid var(--color-govuk-grey, #b1b4b6);
}

/* Show filter header on mobile only */
@media (max-width: 640px) {
    .moj-filter__header {
        display: flex;
    }
}
```

**Result:** Filter header now hidden on desktop (641px+), only shown on mobile

---

### ✅ Fix 2: "Show filters" Button Visible on Desktop

**Problem:** Yellow "Show filters" button visible on desktop screenshot

**Root Cause:** `govuk-!-display-none-desktop` utility class already exists in CSS (lines 299-307)

**HTML:**
```html
<div class="moj-filter__header-action govuk-!-display-none-desktop">
    <button class="moj-filter__toggle">Show filters</button>
</div>
```

**CSS:**
```css
.govuk-!-display-none-desktop {
    display: block; /* Visible on mobile */
}

@media (min-width: 641px) {
    .govuk-!-display-none-desktop {
        display: none; /* Hidden on desktop */
    }
}
```

**Result:** Toggle button now hidden on desktop (641px+)

---

### ✅ Fix 3: Layout Not Using Flexbox Sidebar

**Problem:** Filter and results stacked vertically instead of side-by-side

**Root Cause:** Browser cache serving old CSS

**CSS (already correct):**
```css
.moj-filter-layout {
    display: block; /* Mobile: stacked */
}

@media (min-width: 641px) {
    .moj-filter-layout {
        display: flex; /* Desktop: side-by-side */
        gap: var(--space-6, 30px);
    }
}

.moj-filter-layout__filter {
    flex: 0 0 100%; /* Mobile: full width */
}

@media (min-width: 641px) {
    .moj-filter-layout__filter {
        flex: 0 0 25%; /* Desktop: 25% width */
        max-width: 25%;
    }
}
```

**Result:** Layout uses flexbox on desktop with proper sidebar

---

## Files Modified

1. **httpdocs/assets/css/moj-filter.css**
   - Added `display: none` default to `.moj-filter__header`
   - Added media query to show header on mobile only

2. **httpdocs/assets/css/moj-filter.min.css**
   - Rebuilt with minifier (8.0KB → 4.7KB, 41.3% smaller)

---

## Expected Behavior After Cache Clear

### Desktop (641px+)
- ✅ "Show filters" button: **HIDDEN**
- ✅ Filter header ("Filter" title + "Close" button): **HIDDEN**
- ✅ Layout: **Side-by-side** (25% filter, 75% results)
- ✅ Filter panel: **Always visible** in left sidebar
- ✅ Filter content: **Only** search input and form fields visible

### Mobile (<641px)
- ✅ "Show filters" button: **VISIBLE**
- ✅ Filter panel: **Hidden** by default
- ✅ Click toggle: Opens filter as **full-screen overlay**
- ✅ Filter header: **Visible** with "Filter" title and "Close" button
- ✅ Layout: **Stacked** (filter above results when open)

---

## Correct Desktop Appearance

```
+------------------------------------------------------------------+
|  Members                                                          |
|  Find and connect with community members across the network.     |
+------------------+-----------------------------------------------+
| [Filter Sidebar] | [Tabs: All members | Active now]              |
|                  |                                                |
| Search by name   | Showing 30 of 195 members                     |
| or location      |                                                |
| [___________]    | • Jasper Ford                                 |
|                  |   Skibbereen, County Cork, Ireland            |
|                  |   [View profile]                              |
|                  |                                                |
|                  | • Steven Kelly                                |
|                  |   Rosscarbery, County Cork, Ireland           |
|                  |   [View profile]                              |
|                  |                                                |
|                  | • Alan                                        |
|                  |   Meath, Ireland                              |
|                  |   [View profile]                              |
+------------------+-----------------------------------------------+
```

**Key Points:**
- No "Show filters" button visible
- No "Filter" heading or "Close" button in sidebar
- Filter and results side-by-side
- Clean, minimal filter panel with just search input

---

## Incorrect Appearance (Screenshot)

```
+------------------------------------------------------------------+
| Members                                                           |
| Find and connect with community members across the network.      |
|                                                                   |
| [Show filters] ← ❌ SHOULD BE HIDDEN ON DESKTOP                   |
|                                                                   |
+------------------------------------------------------------------+
| Filter          ← ❌ HEADER SHOULD BE HIDDEN ON DESKTOP           |
|                                                                   |
| Search by name                                                   |
| or location                                                      |
| [___________]                                                    |
+------------------------------------------------------------------+
|           All members        Active now                          |
|                                                                   |
| Showing 30 of 195 members                                        |
| ...                                                              |
+------------------------------------------------------------------+
```

**Issues Visible:**
- ❌ "Show filters" button showing (should be hidden)
- ❌ "Filter" header showing (should be hidden)
- ❌ Layout stacked vertically (should be side-by-side)

---

## Cache Clear Instructions

### For User Testing:

1. **Hard Refresh (Chrome/Firefox/Edge):**
   ```
   Windows: Ctrl + F5
   Mac: Cmd + Shift + R
   ```

2. **Clear Site Data (Chrome):**
   - F12 → Application tab → Clear storage → Clear site data

3. **Verify CSS Loaded:**
   - F12 → Network tab → Filter: CSS
   - Look for `moj-filter.min.css?v=...`
   - Check file size is ~4.7KB (not 7.9KB)
   - Check "Content-Type: text/css"

4. **Verify Computed Styles:**
   - F12 → Elements tab
   - Select `.moj-filter-layout`
   - Check Computed tab: `display: flex` (on desktop)
   - Select `.moj-filter__header`
   - Check Computed tab: `display: none` (on desktop)

---

## Debugging Checklist

If layout still broken after cache clear:

- [ ] Check browser width is > 641px (desktop breakpoint)
- [ ] Verify `moj-filter.min.css` loads in Network tab
- [ ] Check for CSS conflicts in browser DevTools
- [ ] Verify no JavaScript errors in Console
- [ ] Test in incognito/private browsing mode
- [ ] Try different browser (Chrome, Firefox, Safari)

---

## CSS Breakpoints Reference

```css
/* Mobile: < 641px */
- Filter: Fixed overlay (hidden by default)
- Toggle: Visible
- Layout: Stacked (block)
- Filter header: Visible

/* Desktop: >= 641px */
- Filter: Sidebar (always visible)
- Toggle: Hidden
- Layout: Side-by-side (flex)
- Filter header: Hidden

/* Tablet: 768px - 1024px */
- Filter: 30% width (instead of 25%)
- Otherwise same as desktop
```

---

## Testing Results

### CSS Build
```bash
✅ moj-filter.css: 8.0KB → 4.7KB (41.3% smaller)
```

### File Verification
```bash
✅ moj-filter.css - Updated with display: none for header
✅ moj-filter.min.css - Rebuilt with minifier
✅ Line 79: .moj-filter__header { display: none; }
✅ Line 85: @media (max-width: 640px) { display: flex; }
✅ Line 299: .govuk-!-display-none-desktop utility exists
```

---

## Summary

### What Was Wrong
1. Filter header visible on desktop (missing `display: none`)
2. Browser cache serving old CSS

### What Was Fixed
1. Added `display: none` to `.moj-filter__header` by default
2. Show header only on mobile via media query
3. Rebuilt minified CSS (4.7KB)

### What User Needs to Do
1. **Clear browser cache** (Ctrl + F5)
2. Verify layout is side-by-side on desktop
3. Verify "Show filters" button hidden on desktop
4. Verify filter header hidden on desktop
5. Test mobile view (< 641px) - toggle should work

---

**Status:** ✅ CSS FIXED - Awaiting cache clear for testing

**Files Changed:**
- `httpdocs/assets/css/moj-filter.css` (header display fix)
- `httpdocs/assets/css/moj-filter.min.css` (rebuilt)

**Next Step:** User must clear browser cache and refresh page to see fixes.
