# Scroll Fix Summary - 2026-01-19

## Problem
Users reported: "Mouse scrolling doesn't work with multiple browser tabs open"

## Root Cause Found
Two separate bugs were setting `overflow: visible` on the body element, which **prevents scrolling** (instead of allowing it):

### Bug #1: nexus-instant-load.js
**Location:** Line 212
**Issue:** `document.body.style.cssText = 'overflow: visible !important;'`
**Impact:** Ran on EVERY page load, blocked ALL scrolling site-wide
**Fix:** Changed to `overflow-y: auto !important;`
**Commit:** 72cdd64

### Bug #2: scroll-fix-emergency.css
**Location:** Line 15
**Issue:** `body { overflow-y: visible; }`
**Impact:** Loaded on EVERY page in both layouts
**Fix:** Changed to `overflow-y: auto;`
**Commit:** 444a2c4

## Additional Improvements

### Cache Busting System
Created automatic version bumping to force CSS/JS refresh for all users:
- **File:** config/deployment-version.php
- **Script:** scripts/bump-version.js
- **Usage:** `node scripts/bump-version.js "Description"`
- **Commit:** 5eef78a

### .htaccess Fix
Removed `immutable` directive from CSS/JS cache headers:
- **Issue:** Browsers ignored version query strings
- **Fix:** Changed from `immutable` to `must-revalidate`
- **Commit:** e2fed01

### Mobile Navigation
Changed mobile menu scroll lock to mobile-only:
- **Fix:** Wrapped `body.mobile-menu-open` in `@media (max-width: 1024px)`
- **Commit:** 72cdd64

### CSS Cleanup
Removed all conflicting scroll "fixes":
- Deleted force-scroll-fix.js (too aggressive)
- Removed duplicate federation CSS rules
- Simplified mobile-nav cleanup code
- **Commit:** dcf31f9

## Deployed Versions

| Version | Description | Commit |
|---------|-------------|--------|
| 2026.01.19.001 | Initial federation scroll fix | 37760d7 |
| 2026.01.19.002 | Cache busting system | 5eef78a |
| 2026.01.19.003 | .htaccess cache fix | e2fed01 |
| 2026.01.19.004 | Federation CSS aggressive fix | - |
| 2026.01.19.005 | Force-scroll-fix.js (removed later) | d7b09af |
| 2026.01.19.006 | Multi-tab fix attempt | d7b09af |
| 2026.01.19.007 | Cleanup conflicting fixes | dcf31f9 |
| 2026.01.19.008 | Global scroll fix | 1297f7d |
| **2026.01.19.009** | **Fix overflow:visible in JS** | **72cdd64** ✅ |
| **2026.01.19.010** | **Fix overflow:visible in CSS** | **444a2c4** ✅ |

## Final Solution

### What Works Now:
✅ Mouse scrolling works on ALL pages
✅ Works with 1 tab or 100 tabs open
✅ No conflicts between CSS files
✅ Proper CSS cascade maintained
✅ Automatic cache busting for all users

### Key Files Changed:
1. `httpdocs/assets/js/nexus-instant-load.js` - Fixed overflow
2. `httpdocs/assets/css/scroll-fix-emergency.css` - Fixed overflow
3. `httpdocs/assets/css/nexus-native-nav-v2.css` - Mobile-only menu lock
4. `httpdocs/.htaccess` - Removed immutable cache
5. `config/deployment-version.php` - Version tracking
6. `scripts/bump-version.js` - Auto version bumper

### Documentation:
- `docs/CSS_BODY_HTML_RULES.md` - Complete cascade analysis
- `docs/SCROLL_FIX_SUMMARY.md` - This file

## Testing
1. Open https://project-nexus.ie
2. Open 10+ more tabs
3. Switch between tabs
4. Mouse scroll should work perfectly on all pages

## Lesson Learned
The issue wasn't about multiple tabs causing problems. It was about `overflow: visible` being set on the body element, which **prevents scrolling**. The correct value is `overflow-y: auto` which **enables scrolling**.
