# Phase 9: /news Render Timeline Audit

**Date:** 27 January 2026
**Scope:** Modern theme only (CivicOne excluded)
**Status:** AUDIT COMPLETE - No changes made

---

## 1. Final CSS Load Order for /news (Post-Phase 8)

### Sync Stylesheets in `<head>` (in exact order):

| # | File | Source | Applied |
|---|------|--------|---------|
| 1 | `design-tokens.css` | header.php:64 | Sync |
| 2 | `nexus-header-extracted.css` | header.php:66 | Sync |
| 3 | **`blog-index.css`** | header.php:117 (Phase 8) | **Sync - EARLY** |
| 4 | Inline critical CSS (skeleton) | header.php:118-122 | Inline |
| 5 | `nexus-phoenix.css` | css-loader.php:73 | Sync |
| 6 | `modern-theme-tokens.css` | css-loader.php:79 | Sync |
| 7 | `modern-primitives.css` | css-loader.php:85 | Sync |
| 8 | `bundles/core.css` | css-loader.php:87 | Sync |
| 9 | `bundles/components.css` | css-loader.php:89 | Sync |
| 10 | `theme-transitions.css` | css-loader.php:91 | Sync |
| 11 | Header CSS (7 files) | css-loader.php:99-112 | Sync |
| 12 | **`bundles/modern-pages.css`** | css-loader.php:117 | Sync |
| 13 | (blog-index.css SKIPPED) | page-css-loader.php | Skipped |

### Async Stylesheets (via `media="print" onload`):

| File | Source | Risk to Blog |
|------|--------|--------------|
| `components-navigation.css` | css-loader.php:128 | Low (preloaded) |
| `components-buttons.css` | css-loader.php:130 | Low (preloaded) |
| `components-forms.css` | css-loader.php:132 | None |
| `components-cards.css` | css-loader.php:134 | None |
| `utilities-polish.css` | css-loader.php:144 | **POTENTIAL** |
| `utilities-loading.css` | css-loader.php:146 | Low |

---

## 2. CSS Winner Analysis for Skeleton Visibility

### At First Paint

| Selector | Rule | Source | Specificity | Winner |
|----------|------|--------|-------------|--------|
| `.skeleton-container` | `display: none !important` | modern-pages.css:4908 | 0,1,0 + !important | LOSES |
| `#news-holo-wrapper .skeleton-container` | `display: grid !important` | blog-index.css:12-16 | 1,1,0 + !important | **WINS** |
| `#news-holo-wrapper .skeleton-container` | `display: grid !important` | Inline CSS (header.php) | 1,1,0 + !important | **WINS (backup)** |

**CSS Specificity Calculation:**
- `modern-pages.css`: `.skeleton-container` = 0,1,0
- `blog-index.css`: `#news-holo-wrapper .skeleton-container` = 1,1,0 (ID + class)

**Result:** blog-index.css **wins** due to higher specificity (ID selector).

### Grid Container at First Paint

| Selector | Rule | Source | Result |
|----------|------|--------|--------|
| `#news-holo-wrapper #news-grid-container` | `opacity: 0` | blog-index.css:26-28 | Grid hidden |
| `#news-holo-wrapper #news-grid-container` | `opacity: 0` | Inline CSS (header.php) | Grid hidden (backup) |

---

## 3. Tenant-Prefixed URL Verification

**Regex Test Results:**

```
/news                  => MATCH ✓
/blog                  => MATCH ✓
/hour-timebank/news    => MATCH ✓
/hour-timebank/blog    => MATCH ✓
/some-tenant/news      => MATCH ✓
```

**Conclusion:** Phase 8 inline critical CSS **IS** applied for tenant-prefixed `/news` URLs.

---

## 4. JavaScript Analysis

### initSkeletonLoader() in blog/index.php (lines 82-95)

```javascript
(function initSkeletonLoader() {
    const skeleton = document.getElementById('newsSkeleton');
    const grid = document.getElementById('news-grid-container');
    const emptyState = document.querySelector('.news-empty-state');

    if (!skeleton) return;

    // Hide skeleton and show content after short delay
    setTimeout(function() {
        skeleton.classList.add('hidden');
        if (grid) grid.classList.add('content-loaded');
        if (emptyState) emptyState.classList.add('content-loaded');
    }, 300);
})();
```

### Analysis:

| Question | Answer |
|----------|--------|
| Does it always run? | Yes (IIFE at `<script>` in `<body>`) |
| Can it run before CSS applied? | No - script is at end of `<body>`, CSS is in `<head>` |
| Timing mechanism | Fixed 300ms `setTimeout()` |
| Classes added | `.hidden` (skeleton), `.content-loaded` (grid/empty) |

### JS Timing Risk

The 300ms delay is **fixed** regardless of:
- Network speed (CSS may not be fully parsed on slow connections)
- Content readiness (images still loading)
- CSS transitions (400ms for grid opacity transition)

**This is NOT the root cause of FOUC** - the skeleton is visible at first paint because blog-index.css wins the specificity battle.

---

## 5. Late Layout Shift Causes Analysis

### A. Blog Card Images

**Current Implementation (feed-items.php:14-15):**
```html
<img src="..." loading="lazy" alt="...">
```

**Issues:**
- No `width` attribute
- No `height` attribute
- No `aspect-ratio` CSS fallback

**CSS Mitigation (blog-index.css:519-530):**
```css
.news-card-image {
    height: 220px;
    overflow: hidden;
}
.news-card-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
```

**Assessment:** **LOW RISK** - Parent container has fixed height (220px), so image dimensions don't cause layout shift. The `object-fit: cover` ensures images fill the container.

### B. Async CSS Impact on Blog

**utilities-polish.css:**
- Contains `.skeleton` shimmer animations (lines 846-1000+)
- Contains `[class*="skeleton-container"]` transition rule (line 1059)
- **Does NOT target `#news-holo-wrapper`** or `.news-*` classes
- **Impact:** LOW - General skeleton styles, but blog-specific overrides in blog-index.css have higher specificity

**components-navigation.css:**
- Only contains `.newsletter-dropdown-btn` (line 3864) related to "news"
- **No blog/news layout styles**
- **Impact:** NONE

---

## 6. Critical CSS (critical-css.php) Conflict Check

**Skeleton rules in critical-css.php (lines 136-137):**
```css
.skeleton-container{opacity:1;transition:opacity var(--transition-base)}
.skeleton-container.hydrated{opacity:0;pointer-events:none;position:absolute}
```

**Analysis:**
- Uses `.hydrated` class, NOT `.hidden` class
- Blog JS uses `.hidden` class (not `.hydrated`)
- These rules don't conflict with blog-index.css
- **Impact:** NONE - Different class names

---

## 7. Root Cause Decision

After comprehensive analysis, the **primary remaining issue** (if any FOUC persists) is:

### **JS timeout-based reveal causing perceived flash**

**Evidence:**

1. **CSS is now correct** - blog-index.css loads before modern-pages.css and wins specificity
2. **Skeleton is visible at first paint** - Inline critical CSS ensures skeleton shows immediately
3. **The 300ms fixed timeout is arbitrary** - It doesn't wait for:
   - CSS transitions to complete (400ms for grid opacity)
   - Images to begin loading
   - Any actual "ready" state

**Timeline with current implementation:**
```
0ms    - Page starts loading
~10ms  - blog-index.css applied (skeleton visible, grid hidden)
~50ms  - modern-pages.css applied (skeleton still visible due to specificity)
300ms  - JS fires: skeleton gets .hidden, grid gets .content-loaded
300ms  - Skeleton starts fading (opacity: 0, 300ms transition)
300ms  - Grid starts appearing (opacity: 1, 400ms transition)
600ms  - Skeleton fully hidden
700ms  - Grid fully visible
```

**The potential "flash" scenario:**
- If CSS parsing is delayed beyond 300ms on slow connections
- The JS timeout fires before blog-index.css is applied
- Result: Grid made visible while skeleton rules aren't yet in effect

This is **unlikely on modern connections** but possible on slow 3G.

---

## 8. Recommended Minimal Fix

### Option: Replace fixed timeout with CSS-ready detection

**Current (fixed timeout):**
```javascript
setTimeout(function() {
    skeleton.classList.add('hidden');
    if (grid) grid.classList.add('content-loaded');
}, 300);
```

**Recommended (requestAnimationFrame + short delay):**
```javascript
// Wait for next frame (guarantees CSS is applied) then transition
requestAnimationFrame(function() {
    requestAnimationFrame(function() {
        skeleton.classList.add('hidden');
        if (grid) grid.classList.add('content-loaded');
        if (emptyState) emptyState.classList.add('content-loaded');
    });
});
```

**Why this fix:**
1. `requestAnimationFrame` guarantees the browser has completed style calculations
2. Double-rAF ensures the first paint has occurred
3. Removes arbitrary 300ms that may not match actual page readiness
4. **Blog-scoped** - Only affects blog/index.php
5. **Low risk** - No CSS changes needed
6. **Reversible** - Easy to revert if issues arise

**Alternative (if UX prefers delay):**
```javascript
// Minimum 200ms delay + CSS-ready guarantee
var startTime = performance.now();
requestAnimationFrame(function checkReady() {
    if (performance.now() - startTime >= 200) {
        skeleton.classList.add('hidden');
        if (grid) grid.classList.add('content-loaded');
        if (emptyState) emptyState.classList.add('content-loaded');
    } else {
        requestAnimationFrame(checkReady);
    }
});
```

---

## 9. Summary

| Item | Finding |
|------|---------|
| CSS Load Order | Correct - blog-index.css loads before modern-pages.css |
| CSS Specificity | Correct - `#news-holo-wrapper` selector wins over global `.skeleton-container` |
| Tenant-Prefix Support | Confirmed - Regex matches all tenant variations |
| Inline Critical CSS | Present - Extra safety net for skeleton visibility |
| JS Timing | **Fixed 300ms timeout** - potential issue on slow networks |
| Image CLS Risk | Low - CSS sets fixed container height |
| Async CSS Risk | Low - No async CSS affects blog layout |
| **Root Cause** | JS timeout-based reveal (may fire before CSS ready on slow networks) |
| **Recommended Fix** | Replace `setTimeout(300)` with `requestAnimationFrame` |

---

**Report Generated:** 27 January 2026
**Phase 9 Status:** AUDIT COMPLETE - One recommended fix identified
