# Phase 10: Make utilities-polish.css Render-Blocking for Blog Routes

**Date:** 27 January 2026
**Scope:** Modern theme only (CivicOne excluded)
**Status:** IMPLEMENTED

---

## 1. Problem Statement

After Phase 8 and 9, the blog skeleton displays correctly at first paint. However, a visible "snap" occurs ~69ms after load when `utilities-polish.css` finishes loading asynchronously.

### Evidence from DevTools

```
utilities-polish.css
- Start: ~0ms (preloaded)
- Duration: 69.51ms
- Impact: Skeleton shimmer animation snaps in after initial paint
```

### Root Cause

`utilities-polish.css` contains skeleton shimmer animations (lines 846-1000+) that are loaded via the `media="print" onload` async pattern. When this CSS finally applies, the skeleton appearance changes visibly.

---

## 2. Solution: Sync Loading for Blog Routes Only

For blog index routes (`/news`, `/blog`, and tenant-prefixed variants), load `utilities-polish.css` as a render-blocking stylesheet in `<head>` instead of async.

### Why Blog Routes Only?

- Blog pages have prominent skeleton loaders that benefit from shimmer animations at first paint
- Other pages don't have visible skeletons or the async delay is acceptable
- Minimizes render-blocking CSS impact across the site

---

## 3. Implementation Details

### 3.1 header.php Changes

**Location:** `views/layouts/modern/header.php` (within the `$isBlogIndex` block)

**Added:**

```php
if ($isBlogIndex):
    $GLOBALS['css_already_in_head'][] = 'blog-index.css';
    $GLOBALS['css_already_in_head'][] = 'utilities-polish.css';  // NEW
?>
    <!-- PHASE 8: Blog index CSS in <head> for instant skeleton render -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/blog-index.css?v=<?= $cssVersionTimestamp ?>">
    <!-- PHASE 10: utilities-polish.css sync for blog routes (prevents 69ms skeleton snap) -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/bundles/utilities-polish.css?v=<?= $cssVersionTimestamp ?>">
```

### 3.2 css-loader.php Changes

**Location:** `views/layouts/modern/partials/css-loader.php` (line 144)

**Before:**

```php
<?= asyncCss('/assets/css/bundles/utilities-polish.css', $cssVersion, $assetBase) ?>
```

**After:**

```php
<?php
// PHASE 10 (2026-01-27): Skip utilities-polish.css if already loaded sync in <head> for blog routes
$cssAlreadyInHead = $GLOBALS['css_already_in_head'] ?? [];
if (!in_array('utilities-polish.css', $cssAlreadyInHead)):
?>
<?= asyncCss('/assets/css/bundles/utilities-polish.css', $cssVersion, $assetBase) ?>
<?php endif; ?>
```

---

## 4. Load Order for /news (Post-Phase 10)

```
1. design-tokens.css (header.php:64)
2. nexus-header-extracted.css (header.php:66)
3. blog-index.css (header.php:117) - Phase 8
4. utilities-polish.css (header.php:118) - Phase 10 NEW
5. Inline critical CSS (header.php:119-123)
6. css-loader.php bundles (utilities-polish.css SKIPPED)
```

### Expected Behavior

| Time | What Happens |
|------|--------------|
| 0ms | `<head>` parsing begins |
| ~10ms | blog-index.css applied (skeleton visible) |
| ~15ms | utilities-polish.css applied (shimmer animation active) |
| ~50ms | remaining CSS bundles load |
| 300ms | JS reveals content, skeleton fades |

**Result:** Skeleton shimmer animation is visible from first paint, no delayed snap.

---

## 5. Duplicate Prevention

The same `$GLOBALS['css_already_in_head']` mechanism from Phase 8 is reused:

1. `header.php` adds `'utilities-polish.css'` to the tracking array for blog routes
2. `css-loader.php` checks the array and skips the async version if already loaded

This ensures utilities-polish.css is never loaded twice.

---

## 6. Routes Affected

| Route Pattern | utilities-polish.css Loading |
|---------------|------------------------------|
| `/news`, `/blog` | Sync (render-blocking) |
| `/{tenant}/news`, `/{tenant}/blog` | Sync (render-blocking) |
| All other routes | Async (non-blocking) |

---

## 7. Performance Impact

### Blog Routes (/news, /blog)

- **Before:** utilities-polish.css loads async, causes 69ms snap
- **After:** utilities-polish.css loads sync, no snap, slight increase in Time to First Paint

**Trade-off:** Marginally slower initial render (~15-20ms) but smoother visual experience.

### All Other Routes

- **No change:** utilities-polish.css continues to load async

---

## 8. Verification Checklist

### In Browser DevTools:

- [ ] View Page Source on /news: Confirm TWO stylesheet links (blog-index.css AND utilities-polish.css)
- [ ] View Page Source on /news: Confirm NO duplicate utilities-polish.css tags
- [ ] Network tab: utilities-polish.css should appear early in waterfall for /news
- [ ] Network tab: utilities-polish.css should appear late (async) for other pages like /dashboard

### Visual:

- [ ] Hard refresh /news with cache disabled
- [ ] Skeleton shimmer animation should be visible immediately
- [ ] No "snap" or visual shift ~69ms after load
- [ ] Smooth transition to real content

### Edge Cases:

- [ ] Test /hour-timebank/news (tenant-prefixed blog)
- [ ] Test /dashboard (should NOT have sync utilities-polish.css)
- [ ] Test /login (should NOT have sync utilities-polish.css)

---

## 9. Rollback Instructions

To revert Phase 10:

1. **header.php**: Remove the utilities-polish.css line and tracking entry:
   ```php
   // Remove: $GLOBALS['css_already_in_head'][] = 'utilities-polish.css';
   // Remove: <link rel="stylesheet" href=".../utilities-polish.css...">
   ```

2. **css-loader.php**: Remove the conditional check, restore direct asyncCss call:
   ```php
   <?= asyncCss('/assets/css/bundles/utilities-polish.css', $cssVersion, $assetBase) ?>
   ```

---

## 10. Related Documentation

| Document | Purpose |
|----------|---------|
| `docs/modern-css-phase8-head-stylesheets-blog-auth-27-01-2026.md` | Phase 8: blog-index.css in `<head>` |
| `docs/modern-css-phase9-news-render-timeline-27-01-2026.md` | Phase 9: Render timeline audit |
| `docs/modern-css-blog-fouc-fix-27-01-2026.md` | Original blog FOUC CSS fix |

---

**Report Generated:** 27 January 2026
**Phase 10 Status:** IMPLEMENTED - Ready for verification
