# Phase 11: /news Content-First Load (Remove Initial Skeleton Swap)

**Date:** 27 January 2026
**Scope:** Modern theme only (CivicOne excluded)
**Status:** IMPLEMENTED

---

## 1. Problem Statement

Despite multiple phases of optimization (8, 9, 10, 10J), the /news page still exhibited a visible flash/swap:

1. **Skeleton appears** at first paint
2. **Brief delay** (even with rAF optimization)
3. **Content swaps in** replacing skeleton

This skeleton-to-content swap is unnecessary because:
- Content is **server-rendered** (not fetched via AJAX)
- The swap creates a visual "blink" regardless of timing optimization
- Users perceive the swap as a loading delay

### The Fundamental Issue

The skeleton was designed for client-side rendering where content loads after the initial HTML. For server-rendered pages, the content is already in the HTML - hiding it and showing a skeleton first is counterproductive.

---

## 2. Solution: Content-First Load

Eliminate the skeleton mechanism entirely for initial page load:

1. **Remove** the initial skeleton HTML
2. **Remove** the JavaScript reveal mechanism
3. **Show** server-rendered content immediately
4. **Keep** infinite scroll loading indicator for fetching more posts

---

## 3. Implementation Details

### 3.1 views/modern/blog/index.php

#### Skeleton HTML Removed

**Before:**
```php
<!-- Skeleton Loader -->
<div class="news-skeleton-grid skeleton-container" id="newsSkeleton" aria-label="Loading news">
    <?php for ($i = 0; $i < 6; $i++): ?>
    <div class="news-card-skeleton">
        <div class="skeleton skeleton-image"></div>
        <div class="skeleton-body">
            <div class="skeleton skeleton-date"></div>
            <div class="skeleton skeleton-title"></div>
            <!-- ... more skeleton elements ... -->
        </div>
    </div>
    <?php endfor; ?>
</div>
```

**After:**
```php
<!-- Phase 11 (2026-01-27): Initial skeleton removed for content-first load.
     Server-rendered content displays immediately without swap.
     Infinite scroll has its own loading indicator below. -->
```

#### initSkeletonLoader() IIFE Removed

**Before:**
```javascript
(function initSkeletonLoader() {
    const skeleton = document.getElementById('newsSkeleton');
    const grid = document.getElementById('news-grid-container');
    const emptyState = document.querySelector('.news-empty-state');
    if (!skeleton) return;
    // ... reveal logic ...
})();
```

**After:**
```javascript
// Phase 11 (2026-01-27): initSkeletonLoader() removed for content-first load.
// Server-rendered content is visible immediately without skeleton swap.
```

### 3.2 httpdocs/assets/css/blog-index.css

#### Skeleton CSS Rules Removed

**Before (134 lines of skeleton + reveal CSS):**
```css
/* Skeleton container: visible initially */
#news-holo-wrapper .skeleton-container {
    display: grid !important;
    opacity: 1;
    transition: opacity 300ms ease;
}

/* Grid hidden until JS marks it content-loaded */
#news-holo-wrapper #news-grid-container {
    opacity: 0;
    transition: opacity 400ms ease;
}
/* ... 80+ more lines of skeleton styling ... */
```

**After (simple content-first rules):**
```css
/* Phase 11 Content-First Load (27/01/2026) */

/* Grid visible immediately - no JS reveal needed */
#news-holo-wrapper #news-grid-container {
    opacity: 1;
}

/* Empty state visible immediately */
#news-holo-wrapper .news-empty-state {
    opacity: 1;
}

/* Phase 11: Skeleton CSS removed - HTML skeleton no longer exists */
```

### 3.3 views/layouts/modern/header.php

#### Inline Critical CSS Removed

**Before:**
```php
if ($isBlogIndex):
?>
    <link rel="stylesheet" href=".../blog-index.css">
    <link rel="stylesheet" href=".../utilities-polish.css">
    <style>
    /* Phase 8 Critical: Ensure skeleton visible before blog-index.css fully applies */
    #news-holo-wrapper .skeleton-container { display: grid !important; opacity: 1; }
    #news-holo-wrapper #news-grid-container { opacity: 0; }
    </style>
<?php endif; ?>
```

**After:**
```php
if ($isBlogIndex):
?>
    <!-- PHASE 11: Blog index CSS in <head> for content-first load (no skeleton swap) -->
    <link rel="stylesheet" href=".../blog-index.css">
    <!-- utilities-polish.css sync for consistent styling -->
    <link rel="stylesheet" href=".../utilities-polish.css">
<?php endif; ?>
```

The inline `<style>` block that forced skeleton visible and grid hidden has been removed.

---

## 4. Why This Eliminates Flash

### Before (Phases 8-10J)

```
0ms    - HTML parsing begins
~10ms  - Skeleton visible (CSS forces it visible)
~10ms  - Grid hidden (CSS sets opacity: 0)
~32ms  - JS fires: skeleton.hidden, grid.content-loaded
~32ms  - Skeleton fades out (300ms transition)
~32ms  - Grid fades in (400ms transition)
~432ms - Grid fully visible
```

**User sees:** Skeleton cards → fade → real cards (perceived as "loading")

### After (Phase 11)

```
0ms    - HTML parsing begins
~10ms  - CSS applied
~10ms  - Grid visible (opacity: 1 by default)
~10ms  - Content styled and displayed
```

**User sees:** Real cards immediately (no loading perception)

---

## 5. What Remains for Infinite Scroll

The infinite scroll mechanism is unchanged and has its own loading indicator:

```php
<!-- Infinite Scroll Sentinel -->
<div id="news-scroll-sentinel" style="height: 80px; ...">
    <i class="fa-solid fa-circle-notch fa-spin fa-2x" style="display: none;"></i>
</div>
```

When fetching more posts:
1. Spinner becomes visible (`i.style.display = 'block'`)
2. New cards are fetched and appended
3. Spinner hides (`i.style.display = 'none'`)

This is appropriate for infinite scroll because:
- The page is already loaded
- User scrolled to the bottom
- They expect to wait for more content

---

## 6. CSS Reduction Summary

| Metric | Before | After |
|--------|--------|-------|
| Skeleton HTML lines | 16 | 0 (comment only) |
| Skeleton CSS lines | ~90 | 0 |
| Reveal CSS lines | ~44 | ~8 |
| JS reveal code | 25 lines | 0 (comment only) |
| Inline critical CSS | 2 rules | 0 |

**Total reduction:** ~175 lines of skeleton-related code removed.

---

## 7. Files Modified

| File | Change |
|------|--------|
| `views/modern/blog/index.php` | Removed skeleton HTML and initSkeletonLoader() IIFE |
| `httpdocs/assets/css/blog-index.css` | Removed skeleton CSS, set grid opacity: 1 |
| `views/layouts/modern/header.php` | Removed inline critical CSS for skeleton |

---

## 8. Verification Checklist

### Visual:

- [ ] Hard refresh /news with cache disabled
- [ ] **No skeleton appears** at first paint
- [ ] Blog cards are styled immediately (no swap)
- [ ] No flash or visual "blink"

### Infinite Scroll:

- [ ] Scroll to bottom of /news
- [ ] Spinner appears while fetching
- [ ] New cards append smoothly
- [ ] Spinner disappears after fetch

### Edge Cases:

- [ ] Test /hour-timebank/news (tenant-prefixed)
- [ ] Test /news with empty posts (empty state visible immediately)
- [ ] Test with slow network throttling (content still visible, no skeleton)

### CSS:

- [ ] View Page Source: No inline `<style>` block for skeleton
- [ ] Inspect #news-grid-container: opacity should be 1 by default
- [ ] No .hidden or .content-loaded classes needed on initial load

---

## 9. Rollback Instructions

To restore the skeleton mechanism:

1. **blog/index.php**: Restore skeleton HTML and initSkeletonLoader() IIFE
2. **blog-index.css**: Restore skeleton CSS rules and opacity: 0 for grid
3. **header.php**: Restore inline critical CSS for skeleton visibility

Refer to git history or Phase 8-10J documentation for original code.

---

## 10. Related Documentation

| Document | Purpose |
|----------|---------|
| `docs/modern-css-phase8-head-stylesheets-blog-auth-27-01-2026.md` | Phase 8: CSS in `<head>` |
| `docs/modern-css-phase9-news-render-timeline-27-01-2026.md` | Phase 9: Timeline audit |
| `docs/modern-css-phase10-utilities-polish-sync-27-01-2026.md` | Phase 10: utilities-polish sync |
| `docs/modern-css-phase10J-news-reveal-rAF-27-01-2026.md` | Phase 10J: rAF reveal |

---

## 11. Summary

| Aspect | Before (Phases 8-10J) | After (Phase 11) |
|--------|----------------------|------------------|
| Initial display | Skeleton | Real content |
| JS reveal needed | Yes (rAF) | No |
| CSS transitions | Yes (fade) | No |
| Perceived loading | Yes (swap) | No |
| Infinite scroll | Spinner | Spinner (unchanged) |

**Result:** Server-rendered blog content displays immediately without any skeleton swap, eliminating the perceived "loading" flash entirely.

---

**Report Generated:** 27 January 2026
**Phase 11 Status:** IMPLEMENTED - Ready for verification
