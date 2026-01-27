# Phase 8: Eliminate FOUC by Loading Critical Page CSS in `<head>`

**Date:** 27 January 2026
**Scope:** Modern theme only (CivicOne excluded)
**Status:** IMPLEMENTED

---

## 1. Problem Statement

Despite preloading blog-index.css (added in Phase 7), the `/news` page still experienced FOUC because:

1. **Preload is not enough** - `<link rel="preload">` starts the fetch early but does NOT apply the stylesheet
2. **modern-pages.css wins the race** - It contains `.skeleton-container { display: none !important }` which hides the skeleton before blog-index.css can override it
3. **Late stylesheet application** - blog-index.css was only output by `page-css-loader.php` which runs AFTER `css-loader.php` (after modern-pages.css)

### The Timeline Problem

```
1. Browser parses <head>
2. Preload for blog-index.css starts fetching (but NOT applied)
3. css-loader.php outputs modern-pages.css (applied immediately)
4. modern-pages.css: .skeleton-container { display: none !important } → SKELETON HIDDEN
5. page-css-loader.php outputs blog-index.css (finally applied)
6. blog-index.css overrides → but too late, user already saw flash
```

---

## 2. Solution: Real Stylesheet in `<head>`

Move the actual `<link rel="stylesheet">` for critical page CSS into `<head>` BEFORE any conflicting bundles load.

### Files Modified

| File | Change |
|------|--------|
| `views/layouts/modern/header.php` | Add real stylesheet links for blog/auth routes + inline critical CSS |
| `views/layouts/modern/partials/page-css-loader.php` | Skip files already loaded in `<head>` |

---

## 3. Implementation Details

### 3.1 header.php Changes (lines 99-140)

**Before:** Only preloads for blog-index.css, blog-show.css, auth.css

**After:** Real stylesheet links + inline critical CSS for these routes

```php
<?php
// Track which CSS files are loaded in <head> to prevent duplicates
$GLOBALS['css_already_in_head'] = [];

// Blog index: /news, /blog (with tenant prefix variants)
$isBlogIndex = $normPath === '/news' || $normPath === '/blog' || preg_match('/\/(news|blog)$/', $normPath);
// Blog show: /news/{slug}, /blog/{slug}
$isBlogShow = preg_match('/\/(news|blog)\/[^\/]+$/', $normPath) && !preg_match('/\/(news|blog)\/(create|edit)/', $normPath);
// Auth pages: /login, /register, /password
$isAuthPage = preg_match('/\/(login|register|password)/', $normPath);

if ($isBlogIndex):
    $GLOBALS['css_already_in_head'][] = 'blog-index.css';
?>
    <!-- PHASE 8: Blog index CSS in <head> for instant skeleton render -->
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/blog-index.css?v=<?= $cssVersionTimestamp ?>">
    <style>
    /* Phase 8 Critical: Ensure skeleton visible before blog-index.css fully applies */
    #news-holo-wrapper .skeleton-container { display: grid !important; opacity: 1; }
    #news-holo-wrapper #news-grid-container { opacity: 0; }
    </style>
<?php elseif ($isBlogShow):
    $GLOBALS['css_already_in_head'][] = 'blog-show.css';
?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/blog-show.css?v=<?= $cssVersionTimestamp ?>">
<?php elseif ($isAuthPage):
    $GLOBALS['css_already_in_head'][] = 'auth.css';
?>
    <link rel="stylesheet" href="<?= $assetBase ?>/assets/css/auth.css?v=<?= $cssVersionTimestamp ?>">
<?php endif; ?>
```

### 3.2 page-css-loader.php Changes (lines 340-360)

**Before:** Outputs all matching CSS files unconditionally

**After:** Checks `$GLOBALS['css_already_in_head']` and skips duplicates

```php
// Get list of CSS files already loaded in <head> (set by header.php Phase 8)
$cssAlreadyInHead = $GLOBALS['css_already_in_head'] ?? [];

<?php foreach ($config['files'] as $cssFile): ?>
<?php if (!in_array($cssFile, $cssAlreadyInHead)): ?>
    <link rel="stylesheet" href="...">
<?php endif; ?>
<?php endforeach; ?>
```

### 3.3 Inline Critical CSS (Blog Only)

A tiny 2-line inline style block ensures the skeleton is visible even if blog-index.css hasn't fully loaded:

```css
#news-holo-wrapper .skeleton-container { display: grid !important; opacity: 1; }
#news-holo-wrapper #news-grid-container { opacity: 0; }
```

This is intentionally minimal - just enough to counter the global `display: none !important` from modern-pages.css.

---

## 4. Duplicate Prevention Mechanism

```
┌─────────────────────────────────────────────────────────────────┐
│ header.php (early in <head>)                                    │
│                                                                 │
│ 1. Detect route: /news, /blog, /login, etc.                    │
│ 2. Output <link rel="stylesheet" ...>                          │
│ 3. Add filename to $GLOBALS['css_already_in_head']             │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ css-loader.php → page-css-loader.php (later in <head>)         │
│                                                                 │
│ 1. Check $GLOBALS['css_already_in_head']                       │
│ 2. Skip any file already in that array                         │
│ 3. Output remaining page-specific CSS                          │
└─────────────────────────────────────────────────────────────────┘
```

---

## 5. New Load Order for /news

```
1. design-tokens.css (line 64)
2. nexus-header-extracted.css (line 66)
3. blog-index.css (NEW - line ~105) ← BEFORE modern-pages.css!
4. Inline critical CSS (skeleton visible)
5. css-loader.php bundles (including modern-pages.css)
6. page-css-loader.php (skips blog-index.css - already loaded)
```

### Expected Behavior

| Time | What Happens |
|------|--------------|
| 0ms | `<head>` parsing begins |
| ~10ms | blog-index.css + inline critical CSS applied |
| ~10ms | Skeleton container visible (`display: grid !important`) |
| ~10ms | Grid container hidden (`opacity: 0`) |
| ~50ms | modern-pages.css loads - but blog-index.css already won specificity |
| 300ms | JS adds `.hidden` to skeleton, `.content-loaded` to grid |
| 300-700ms | Smooth crossfade transition |

---

## 6. Routes Covered

| Route Pattern | CSS in `<head>` | Inline Critical |
|---------------|-----------------|-----------------|
| `/news`, `/blog`, `/{tenant}/news` | blog-index.css | Yes (skeleton) |
| `/news/{slug}`, `/blog/{slug}` | blog-show.css | No |
| `/login`, `/register`, `/password` | auth.css | No |

---

## 7. Verification Checklist

### In Browser DevTools:

- [ ] View Page Source: Confirm `blog-index.css` appears as `<link rel="stylesheet">` in `<head>` (not just preload)
- [ ] View Page Source: Confirm NO duplicate `blog-index.css` tags
- [ ] View Page Source: Confirm inline `<style>` with skeleton rules appears for /news
- [ ] Network tab: blog-index.css should start loading very early (top of waterfall)
- [ ] Network tab: blog-index.css should show as "Stylesheet" not "Other" (preload)

### Visual:

- [ ] Hard refresh /news with cache disabled
- [ ] Skeleton should be visible immediately (purple shimmer cards)
- [ ] No flash of unstyled/misaligned content
- [ ] Smooth transition to real news cards after ~300ms

### Edge Cases:

- [ ] Test /hour-timebank/news (tenant-prefixed)
- [ ] Test /login, /register, /password
- [ ] Test /news/some-article (blog show)

---

## 8. Rollback Instructions

To revert Phase 8:

1. **header.php**: Replace the Phase 8 block (lines 99-140) with the original preload-only version
2. **page-css-loader.php**: Remove the `$cssAlreadyInHead` check (lines 345-350)

Or via git:
```bash
git checkout HEAD~1 -- views/layouts/modern/header.php views/layouts/modern/partials/page-css-loader.php
```

---

## 9. Related Documentation

| Document | Purpose |
|----------|---------|
| `docs/modern-css-fouc-fix-27-01-2026.md` | Original FOUC fix (preloads) |
| `docs/modern-css-blog-fouc-diagnosis-27-01-2026.md` | Blog FOUC root cause analysis |
| `docs/modern-css-blog-fouc-fix-27-01-2026.md` | Blog-specific CSS overrides |

---

**Report Generated:** 27 January 2026
**Phase 8 Status:** IMPLEMENTED - Ready for verification
