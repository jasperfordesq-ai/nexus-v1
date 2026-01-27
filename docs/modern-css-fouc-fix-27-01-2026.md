# Modern Theme FOUC Fix Report

**Date:** 27 January 2026
**Scope:** Modern theme only (CivicOne unchanged)
**Goal:** Eliminate or reduce Flash of Unstyled Content (FOUC)

---

## 1. Diagnosis Summary

### 1.1 Root Causes Identified

| Issue | Cause | Impact |
|-------|-------|--------|
| **Page-specific CSS late discovery** | Page CSS files load sync but browser discovers them late in HTML parsing | High - causes content flash on first paint |
| **Async component bundles** | components-navigation.css (137KB) and components-buttons.css (24KB) load async | Medium - header/nav buttons flash |
| **No preload hints** | Critical page CSS had no preload hints | High - browser can't start fetching early |

### 1.2 Files Arriving Late

| File | Size | Routes Affected | Above-Fold Content |
|------|------|-----------------|-------------------|
| `nexus-home.css` | 147 KB | Home page | Feed cards, sidebar, filters |
| `dashboard.css` | 28 KB | Dashboard | Stats cards, activity feed |
| `profile-holographic.css` | 62 KB | Profile show | Avatar, header, tabs |
| `auth.css` | 19 KB | Login/Register | Form, social buttons |
| `events-index.css` | 31 KB | Events listing | Event cards, filters |
| `groups-show.css` | 85 KB | Groups | Group cards, header |
| `components-navigation.css` | 137 KB | All pages | Header, mega-menu, nav links |
| `components-buttons.css` | 24 KB | All pages | All buttons |

### 1.3 What Was NOT Causing FOUC

- **Token loading order**: modern-theme-tokens.css loads correctly as third sync file
- **Google Fonts**: Already using `display=swap` parameter
- **Font Awesome**: Already loading async with proper fallback
- **Critical CSS**: Already inlined in critical-css.php (good baseline)

---

## 2. Fix Implementation

### 2.1 Strategy Applied

**Preload hints** for both:
1. Route-specific page CSS (conditional)
2. Async component bundles that style above-fold content (all pages)

This allows browsers to start fetching CSS files as soon as `<head>` is parsed, rather than waiting to discover `<link>` tags deeper in the document.

### 2.2 File Modified

**`views/layouts/modern/header.php`**

Added preload hints after line 94:

```php
<!-- FOUC FIX (2026-01-27): Preload async bundles that style above-fold navigation/buttons -->
<link rel="preload" as="style" href="<?= $assetBase ?>/assets/css/bundles/components-navigation.css?v=<?= $cssVersionTimestamp ?>">
<link rel="preload" as="style" href="<?= $assetBase ?>/assets/css/bundles/components-buttons.css?v=<?= $cssVersionTimestamp ?>">

<!-- FOUC FIX (2026-01-27): Preload page-specific CSS for high-traffic routes
     This allows browsers to start fetching page CSS before parsing HTML body.
     Only preloads the PRIMARY page file; secondary files load via page-css-loader. -->
<?php if ($isHome): ?>
    <link rel="preload" as="style" href="<?= $assetBase ?>/assets/css/nexus-home.css?v=<?= $cssVersionTimestamp ?>">
<?php elseif (strpos($normPath, '/dashboard') !== false): ?>
    <link rel="preload" as="style" href="<?= $assetBase ?>/assets/css/dashboard.css?v=<?= $cssVersionTimestamp ?>">
<?php elseif (preg_match('/\/profile\/[^\/]+$/', $normPath) && strpos($normPath, '/edit') === false): ?>
    <link rel="preload" as="style" href="<?= $assetBase ?>/assets/css/profile-holographic.css?v=<?= $cssVersionTimestamp ?>">
<?php elseif (preg_match('/\/(login|register|password)/', $normPath)): ?>
    <link rel="preload" as="style" href="<?= $assetBase ?>/assets/css/auth.css?v=<?= $cssVersionTimestamp ?>">
<?php elseif ($normPath === '/events' || preg_match('/\/events$/', $normPath)): ?>
    <link rel="preload" as="style" href="<?= $assetBase ?>/assets/css/events-index.css?v=<?= $cssVersionTimestamp ?>">
<?php elseif ($normPath === '/groups' || preg_match('/\/groups$/', $normPath) || preg_match('/\/groups\/\d+$/', $normPath)): ?>
    <link rel="preload" as="style" href="<?= $assetBase ?>/assets/css/groups-show.css?v=<?= $cssVersionTimestamp ?>">
<?php endif; ?>
```

### 2.3 What Changed

| Route | CSS Preloaded |
|-------|---------------|
| **All pages** | `components-navigation.css`, `components-buttons.css` |
| **Home** | `nexus-home.css` |
| **Dashboard** | `dashboard.css` |
| **Profile show** | `profile-holographic.css` |
| **Auth (login/register)** | `auth.css` |
| **Events index** | `events-index.css` |
| **Groups index/show** | `groups-show.css` |

### 2.4 Why Preload Instead of Sync?

| Approach | Pros | Cons |
|----------|------|------|
| **Sync loading** | Blocks render until loaded | Increases Time to First Byte, LCP |
| **Preload + Async** | Early fetch, non-blocking | Slight complexity, relies on browser support |
| **Inline critical** | Fastest possible | Increases HTML size, hard to maintain |

**Chosen: Preload + Async** - Best balance of performance and FOUC reduction.

---

## 3. Font Loading Analysis

### 3.1 Current State

| Font | Loading Method | font-display |
|------|---------------|--------------|
| **Roboto (Google)** | Async via print hack | `swap` |
| **Roboto (critical inline)** | Inline @font-face | `optional` |
| **Font Awesome** | Async via print hack | N/A (icon font) |

### 3.2 Assessment

- **Google Fonts**: `display=swap` is correct - shows fallback immediately, swaps when loaded
- **Critical inline**: `font-display: optional` was intentionally set for performance
  - This can cause permanent fallback if font doesn't load in ~100ms
  - Trade-off: Better performance vs. potential font inconsistency
  - **Recommendation**: Leave as-is unless font flash is reported

### 3.3 No Font Changes Made

The current font loading is optimized for performance. No changes required.

---

## 4. How to Test

### 4.1 Browser DevTools Method

1. Open Chrome DevTools → Network tab
2. Enable "Disable cache" checkbox
3. Set throttling to "Slow 3G"
4. Navigate to each route and observe:
   - Are preload requests visible at top of waterfall?
   - Does page paint with styled content?

### 4.2 Visual Testing Checklist

| Route | URL | What to Check |
|-------|-----|---------------|
| **Home** | `/{tenant}/` | Feed cards have proper styling on first paint |
| **Dashboard** | `/{tenant}/dashboard` | Stats cards don't flash unstyled |
| **Profile** | `/{tenant}/profile/{id}` | Avatar and header styled immediately |
| **Login** | `/{tenant}/login` | Form inputs styled on first paint |
| **Events** | `/{tenant}/events` | Event cards don't flash |
| **Groups** | `/{tenant}/groups` | Group cards styled immediately |

### 4.3 Lighthouse Check

1. Run Lighthouse Performance audit
2. Check "Eliminate render-blocking resources" section
3. Verify preloaded CSS appears in "Preload key requests" section

### 4.4 Network Waterfall Verification

In Chrome DevTools Network tab, you should see:
- `components-navigation.css` and `components-buttons.css` start loading in first ~5 requests
- Page-specific CSS (e.g., `nexus-home.css`) starts loading early, not after DOM parse

---

## 5. Rollback Instructions

### 5.1 Quick Rollback

Remove the preload hints from `views/layouts/modern/header.php`:

1. Delete lines containing `<!-- FOUC FIX (2026-01-27)`
2. Delete the PHP conditional blocks for page preloads
3. Delete the two `components-navigation.css` and `components-buttons.css` preload lines

### 5.2 Full Rollback

```bash
git checkout HEAD~1 -- views/layouts/modern/header.php
```

---

## 6. Future Improvements (Not Implemented)

These were considered but deferred for minimal change:

| Improvement | Reason Deferred |
|-------------|-----------------|
| **Inline critical page CSS** | Would require extracting above-fold rules from each page CSS file |
| **Split components-navigation.css** | Large refactor, risk of breaking |
| **Change font-display to swap** | Current `optional` was intentional for performance |
| **HTTP/2 Server Push** | Requires server configuration changes |

---

## 7. Summary

| Metric | Before | After |
|--------|--------|-------|
| **Files with preload hints** | 2 | 8-10 (varies by route) |
| **Routes with page CSS preload** | 0 | 6 |
| **Files modified** | 0 | 1 |
| **Lines added** | 0 | ~20 |

**Result:** Minimal, reversible fix that improves CSS discovery timing for high-traffic routes.

---

**Report Generated:** 27 January 2026
**Fix Status:** IMPLEMENTED
