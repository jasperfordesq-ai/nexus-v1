# Phase 15B: Sync Navigation + Button Bundles for Blog Routes

**Date:** 27 January 2026
**Scope:** Modern theme, blog routes only (/news, /blog, /news/{slug}, /blog/{slug})
**Status:** IMPLEMENTED

---

## Summary

Made `components-navigation.css` and `components-buttons.css` load synchronously (render-blocking) for blog routes only to eliminate the remaining header/button styling flash on /news. All other routes continue to use async loading.

---

## Problem

After Phases 12-14 addressed the three primary FOUC causes:
1. nexus-instant-load.js body hiding (disabled)
2. Font Awesome async (sync on blog/auth)
3. Roboto font async (sync on blog/auth)

The Phase 15A audit identified remaining flash sources on /news:
- Header navigation styling from `components-navigation.css`
- Button effects from `components-buttons.css`

Both were still loaded via `media="print" onload` (async), causing a brief styling shift when they applied.

---

## Changes Made

### File 1: `views/layouts/modern/header.php`

**Blog Index Section (lines 113-122):**
```php
if ($isBlogIndex):
    $GLOBALS['css_already_in_head'][] = 'blog-index.css';
    $GLOBALS['css_already_in_head'][] = 'utilities-polish.css';
    $GLOBALS['css_already_in_head'][] = 'components-navigation.css';  // PHASE 15B
    $GLOBALS['css_already_in_head'][] = 'components-buttons.css';     // PHASE 15B
?>
    <!-- PHASE 11: Blog index CSS in <head> for content-first load (no skeleton swap) -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/blog-index.css?v=<?= $cssVersionTimestamp ?>">
    <!-- utilities-polish.css sync for consistent styling -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/bundles/utilities-polish.css?v=<?= $cssVersionTimestamp ?>">
    <!-- PHASE 15B: Sync nav+button bundles for blog to eliminate header/button snap -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/bundles/components-navigation.css?v=<?= $cssVersionTimestamp ?>">
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/bundles/components-buttons.css?v=<?= $cssVersionTimestamp ?>">
```

**Blog Show Section (lines 123-132):**
```php
<?php elseif ($isBlogShow):
    $GLOBALS['css_already_in_head'][] = 'blog-show.css';
    $GLOBALS['css_already_in_head'][] = 'components-navigation.css';  // PHASE 15B
    $GLOBALS['css_already_in_head'][] = 'components-buttons.css';     // PHASE 15B
?>
    <!-- PHASE 8: Blog show CSS in <head> -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/blog-show.css?v=<?= $cssVersionTimestamp ?>">
    <!-- PHASE 15B: Sync nav+button bundles for blog to eliminate header/button snap -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/bundles/components-navigation.css?v=<?= $cssVersionTimestamp ?>">
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/bundles/components-buttons.css?v=<?= $cssVersionTimestamp ?>">
```

### File 2: `views/layouts/modern/partials/css-loader.php`

**Section 4 - Components (lines 125-139):**
```php
<!-- ==========================================
     4. COMPONENTS (Async - lazy loaded)
     ========================================== -->
<?php
// PHASE 15B (2026-01-27): Skip nav+button bundles if already loaded sync in <head> for blog routes
$cssAlreadyInHead = $GLOBALS['css_already_in_head'] ?? [];
if (!in_array('components-navigation.css', $cssAlreadyInHead)):
?>
<?= asyncCss('/assets/css/bundles/components-navigation.css', $cssVersion, $assetBase) ?>
<?php endif; ?>

<?php if (!in_array('components-buttons.css', $cssAlreadyInHead)): ?>
<?= asyncCss('/assets/css/bundles/components-buttons.css', $cssVersion, $assetBase) ?>
<?php endif; ?>
```

---

## Load Order Verification

### /news Route (Blog Index)

```
1. design-tokens.css (sync) - header.php
2. nexus-header-extracted.css (sync) - header.php
3. blog-index.css (sync) - header.php [PHASE 11]
4. utilities-polish.css (sync) - header.php [PHASE 10]
5. components-navigation.css (sync) - header.php [PHASE 15B] ← NEW
6. components-buttons.css (sync) - header.php [PHASE 15B] ← NEW
7. nexus-phoenix.css (sync) - css-loader.php
8. modern-theme-tokens.css (sync) - css-loader.php
9. modern-primitives.css (sync) - css-loader.php
10. core.css (sync) - css-loader.php
11. components.css (sync) - css-loader.php
12. ... other sync CSS ...
13. components-forms.css (async) - css-loader.php
14. components-cards.css (async) - css-loader.php
15. ... other async CSS ...
16. Font Awesome (sync) - header.php [PHASE 13]
17. Roboto (sync) - header.php [PHASE 14]
```

**Note:** `components-navigation.css` and `components-buttons.css` are NOT output in css-loader.php for /news (deduplication check skips them).

### /dashboard Route (Non-Blog)

```
1. design-tokens.css (sync) - header.php
2. nexus-header-extracted.css (sync) - header.php
3. dashboard.css (preload only) - header.php
4. nexus-phoenix.css (sync) - css-loader.php
5. modern-theme-tokens.css (sync) - css-loader.php
6. ... other sync CSS ...
7. components-navigation.css (async) - css-loader.php ← Still async
8. components-buttons.css (async) - css-loader.php ← Still async
9. components-forms.css (async) - css-loader.php
10. ... other async CSS ...
11. Font Awesome (async) - header.php
12. Roboto (async) - header.php
```

---

## Deduplication Verification

### /news - No Duplicate Stylesheets

| Bundle | header.php | css-loader.php | Total |
|--------|------------|----------------|-------|
| components-navigation.css | 1 (sync) | 0 (skipped) | 1 |
| components-buttons.css | 1 (sync) | 0 (skipped) | 1 |
| utilities-polish.css | 1 (sync) | 0 (skipped) | 1 |

### /dashboard - Normal Async Loading

| Bundle | header.php | css-loader.php | Total |
|--------|------------|----------------|-------|
| components-navigation.css | 0 | 1 (async) | 1 |
| components-buttons.css | 0 | 1 (async) | 1 |
| utilities-polish.css | 0 | 1 (async) | 1 |

---

## Expected Impact

### /news and /blog Routes

- **Header navigation** styled immediately at first paint
- **Nav links** (`.nav-link`) styled immediately
- **Buttons** (`.news-btn`, "Read Article") styled immediately
- **No more header/button styling flash**

### Other Routes

- **No change** - still use async loading for these bundles
- **Performance preserved** - non-blog routes remain optimized

---

## Trade-offs

| Aspect | Blog Routes | Other Routes |
|--------|-------------|--------------|
| components-navigation.css | SYNC (~15-20KB) | ASYNC |
| components-buttons.css | SYNC (~8-12KB) | ASYNC |
| Total added render-blocking | ~25-30KB | 0 |
| Header flash | ELIMINATED | Unchanged |

---

## Cumulative FOUC Fixes (Blog Routes)

After Phase 15B, blog routes now have ALL identified FOUC sources addressed:

| Resource | Status | Phase |
|----------|--------|-------|
| nexus-instant-load.js | DISABLED | Phase 12 |
| Font Awesome | SYNC | Phase 13 |
| Roboto font | SYNC | Phase 14 |
| components-navigation.css | SYNC | Phase 15B |
| components-buttons.css | SYNC | Phase 15B |
| blog-index.css | SYNC | Phase 8/11 |
| utilities-polish.css | SYNC | Phase 10 |

---

## Files Modified

| File | Change |
|------|--------|
| `views/layouts/modern/header.php` | Added sync bundles for blog routes, updated $GLOBALS tracker |
| `views/layouts/modern/partials/css-loader.php` | Added deduplication checks for nav+button bundles |

---

## Files NOT Modified

- `views/layouts/civicone/` - CivicOne unchanged
- All CSS bundle files - No CSS content changes
- Auth routes - No changes (/login still uses inline styles)

---

## Verification Checklist

- [ ] Hard refresh /news: header and buttons styled immediately
- [ ] Hard refresh /news/{slug}: header and buttons styled immediately
- [ ] View page source on /news: only ONE components-navigation.css tag
- [ ] View page source on /news: only ONE components-buttons.css tag
- [ ] Hard refresh /dashboard: bundles still load async (check Network tab)
- [ ] No console errors
- [ ] No duplicate stylesheet warnings

---

**Report Generated:** 27 January 2026
**Phase 15B Status:** COMPLETE - Sync nav+button bundles for blog routes
