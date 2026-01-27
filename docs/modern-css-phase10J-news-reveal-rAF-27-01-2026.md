# Phase 10J: Remove Arbitrary 300ms Reveal Timer (Blog Only)

**Date:** 27 January 2026
**Scope:** Modern theme only (CivicOne excluded)
**Status:** IMPLEMENTED

---

## 1. Problem Statement

The skeleton-to-content reveal on `/news` used a fixed 300ms `setTimeout()` delay. This is problematic because:

1. **Arbitrary timing** - The 300ms doesn't correspond to any actual "ready" state
2. **Too slow on fast connections** - Users wait unnecessarily
3. **Too fast on slow connections** - May fire before CSS is fully applied
4. **Not paint-aware** - Doesn't guarantee the browser has completed style calculations

### Phase 9 Recommendation

From `docs/modern-css-phase9-news-render-timeline-27-01-2026.md`:

> **Root Cause:** JS timeout-based reveal (may fire before CSS ready on slow networks)
> **Recommended Fix:** Replace `setTimeout(300)` with `requestAnimationFrame`

---

## 2. Solution: Double requestAnimationFrame

Replace the fixed 300ms delay with a "paint-ready" reveal using double `requestAnimationFrame`.

### Why Double rAF?

```
First rAF:  Queued to run before next paint
Second rAF: Queued to run before the paint AFTER the first one
```

This guarantees:
1. The browser has completed all style calculations
2. At least one paint has occurred (skeleton is visible)
3. The reveal happens at the optimal moment - immediately after first paint

---

## 3. Implementation Details

### File Modified

`views/modern/blog/index.php` - initSkeletonLoader() IIFE (lines 82-104)

### Before (Fixed 300ms delay)

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

### After (Paint-ready reveal)

```javascript
// Phase 10J (2026-01-27): Replace arbitrary 300ms delay with paint-ready reveal
(function initSkeletonLoader() {
    const skeleton = document.getElementById('newsSkeleton');
    const grid = document.getElementById('news-grid-container');
    const emptyState = document.querySelector('.news-empty-state');

    if (!skeleton) return;

    // Reveal content once CSS is applied and first paint has occurred
    function revealContent() {
        skeleton.classList.add('hidden');
        if (grid) grid.classList.add('content-loaded');
        if (emptyState) emptyState.classList.add('content-loaded');
    }

    // Double rAF ensures first paint has occurred before reveal
    if (window.requestAnimationFrame) {
        requestAnimationFrame(function() {
            requestAnimationFrame(revealContent);
        });
    } else {
        // Fallback for browsers without rAF (IE9 and older)
        setTimeout(revealContent, 0);
    }
})();
```

---

## 4. Timing Comparison

### Before (Fixed 300ms)

```
0ms    - Page starts loading
~10ms  - CSS applied, skeleton visible
300ms  - setTimeout fires (regardless of actual readiness)
300ms  - Skeleton starts fading, grid appears
600ms  - Skeleton fully hidden
700ms  - Grid fully visible
```

**Problem:** 290ms of unnecessary waiting after CSS is ready.

### After (Double rAF)

```
0ms    - Page starts loading
~10ms  - CSS applied, skeleton visible
~16ms  - First rAF callback (next frame)
~32ms  - Second rAF callback (frame after first paint)
~32ms  - Skeleton starts fading, grid appears
~332ms - Skeleton fully hidden (300ms CSS transition)
~432ms - Grid fully visible (400ms CSS transition)
```

**Result:** Reveal happens ~270ms earlier on fast connections.

---

## 5. Fallback for Legacy Browsers

```javascript
if (window.requestAnimationFrame) {
    // Modern browsers: paint-ready reveal
    requestAnimationFrame(function() {
        requestAnimationFrame(revealContent);
    });
} else {
    // IE9 and older: immediate reveal (next tick)
    setTimeout(revealContent, 0);
}
```

`requestAnimationFrame` is supported in all modern browsers (IE10+, all evergreen browsers). The fallback is only for extremely old browsers where immediate reveal is acceptable.

---

## 6. Classes Toggled

| Element | Class Added | Effect |
|---------|-------------|--------|
| `#newsSkeleton` | `.hidden` | Triggers fade-out (opacity: 0, 300ms transition) |
| `#news-grid-container` | `.content-loaded` | Triggers fade-in (opacity: 1, 400ms transition) |
| `.news-empty-state` | `.content-loaded` | Triggers fade-in (if no posts) |

These classes are unchanged - only the timing of when they're applied has changed.

---

## 7. Verification Checklist

### Functional:

- [ ] Hard refresh /news with cache disabled
- [ ] Skeleton appears immediately on page load
- [ ] Content reveals smoothly without obvious "wait" period
- [ ] No flash of unstyled content
- [ ] Transitions still smooth (fade out skeleton, fade in grid)

### Timing:

- [ ] Open DevTools Performance tab
- [ ] Record page load
- [ ] Verify reveal happens within ~32ms of first paint (not 300ms)

### Edge Cases:

- [ ] Test /hour-timebank/news (tenant-prefixed)
- [ ] Test with empty posts (empty state should reveal)
- [ ] Test with slow network throttling (3G)
- [ ] Test in IE11 (if needed - should use fallback)

### Classes:

- [ ] Inspect `#newsSkeleton` after reveal - should have `.hidden` class
- [ ] Inspect `#news-grid-container` after reveal - should have `.content-loaded` class

---

## 8. Rollback Instructions

To revert Phase 10J, restore the original setTimeout:

```javascript
(function initSkeletonLoader() {
    const skeleton = document.getElementById('newsSkeleton');
    const grid = document.getElementById('news-grid-container');
    const emptyState = document.querySelector('.news-empty-state');

    if (!skeleton) return;

    setTimeout(function() {
        skeleton.classList.add('hidden');
        if (grid) grid.classList.add('content-loaded');
        if (emptyState) emptyState.classList.add('content-loaded');
    }, 300);
})();
```

---

## 9. Related Documentation

| Document | Purpose |
|----------|---------|
| `docs/modern-css-phase9-news-render-timeline-27-01-2026.md` | Identified setTimeout as root cause |
| `docs/modern-css-phase10-utilities-polish-sync-27-01-2026.md` | Phase 10: utilities-polish.css sync loading |
| `docs/modern-css-phase8-head-stylesheets-blog-auth-27-01-2026.md` | Phase 8: blog-index.css in `<head>` |

---

## 10. Summary

| Aspect | Before | After |
|--------|--------|-------|
| Trigger | Fixed 300ms setTimeout | Double requestAnimationFrame |
| Timing | Always 300ms after script runs | ~32ms after first paint |
| Paint-aware | No | Yes |
| Fallback | N/A | setTimeout(fn, 0) for IE9 |
| Classes toggled | Same | Same |
| CSS transitions | Same | Same |

**Result:** Skeleton reveal now happens at the optimal moment - immediately after the browser confirms first paint has occurred, eliminating the arbitrary 300ms wait.

---

**Report Generated:** 27 January 2026
**Phase 10J Status:** IMPLEMENTED - Ready for verification
