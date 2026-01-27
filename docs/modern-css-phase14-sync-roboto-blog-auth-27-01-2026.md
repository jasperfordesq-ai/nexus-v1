# Phase 14: Sync Roboto Font for Blog/Auth Routes (Modern Theme Only)

**Date:** 27 January 2026
**Scope:** Modern theme only (CivicOne unchanged)
**Status:** IMPLEMENTED

---

## Summary

Made Google Fonts (Roboto) load synchronously (render-blocking) for blog and auth routes to eliminate FOUT (Flash of Unstyled Text) where text briefly renders in system font before Roboto applies. Other routes continue to use async loading for better performance.

---

## Problem

The Phase 11 root cause audit identified Google Fonts async loading as the #3 contributor to visual flash:

**Behavior before fix:**
- Text renders in system font (`-apple-system, BlinkMacSystemFont, "Segoe UI"`) at first paint
- When Roboto WOFF2 files download, text shifts to Roboto
- Creates visible text "swap" on above-fold content

**Font weights used above-fold:**
- `400` - Body text, paragraphs
- `500` - Secondary text, labels
- `700` - Headings, badges, buttons

---

## Change Made

### File: `views/layouts/modern/header.php`

**Before (lines 166-169):**
```html
<!-- Google Fonts (async) -->
<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap">
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
<noscript><link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet"></noscript>
```

**After:**
```php
<!-- Google Fonts (Roboto) -->
<?php if ($isBlogIndex || $isBlogShow || $isAuthPage): ?>
    <!-- PHASE 14 (2026-01-27): Sync Roboto for blog/auth routes to eliminate font swap -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap">
<?php else: ?>
    <!-- Async Roboto for other routes (non-blocking) -->
    <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet"></noscript>
<?php endif; ?>
```

---

## Routes Covered

### Sync Roboto (render-blocking):

| Route Variable | Pattern | Examples |
|----------------|---------|----------|
| `$isBlogIndex` | `/news`, `/blog`, `/{tenant}/news`, `/{tenant}/blog` | /news, /hour-timebank/news |
| `$isBlogShow` | `/news/{slug}`, `/blog/{slug}` | /news/my-article, /blog/post-title |
| `$isAuthPage` | `/login`, `/register`, `/password` | /login, /hour-timebank/login |

### Async Roboto (non-blocking):

All other routes including:
- Home (`/`, `/home`)
- Dashboard (`/dashboard`)
- Profile pages (`/profile/{username}`)
- Events (`/events`, `/events/{id}`)
- Groups (`/groups`, `/groups/{id}`)
- Members (`/members`)
- Feed, Listings, Messages, etc.

---

## Route Detection Variables (Reused from Phase 8)

```php
// Blog index: /news, /blog (with tenant prefix variants)
$isBlogIndex = $normPath === '/news' || $normPath === '/blog' || preg_match('/\/(news|blog)$/', $normPath);

// Blog show: /news/{slug}, /blog/{slug}
$isBlogShow = preg_match('/\/(news|blog)\/[^\/]+$/', $normPath) && !preg_match('/\/(news|blog)\/(create|edit)/', $normPath);

// Auth pages: /login, /register, /password
$isAuthPage = preg_match('/\/(login|register|password)/', $normPath);
```

---

## Font Weights Preserved

No changes to font weights - kept existing `400;500;700`:

| Weight | Usage |
|--------|-------|
| 400 | Body text, paragraphs, card excerpts |
| 500 | Secondary text, meta labels |
| 700 | Headings, badges, buttons, titles |

---

## Trade-offs

| Aspect | Sync (blog/auth) | Async (other routes) |
|--------|------------------|----------------------|
| Text in Roboto at first paint | YES | NO (system font first) |
| Render-blocking | YES (~5-15KB CSS + WOFF2 fetch) | NO |
| Best for | Text-heavy above-fold | Performance-critical pages |

---

## Expected Result

**On /news and /login:**
- Text renders in Roboto immediately at first paint
- No font swap/shift effect
- Slightly longer time to first paint (Google Fonts CSS + WOFF2 download)

**On other routes:**
- Roboto loads asynchronously (non-blocking)
- Text may briefly show system font then swap to Roboto
- Faster time to first paint

---

## Verification Checklist

- [ ] Hard refresh /news: headings and body text in Roboto immediately
- [ ] Hard refresh /news/{slug}: article text in Roboto immediately
- [ ] Hard refresh /login: form labels and headings in Roboto immediately
- [ ] Hard refresh /register: text in Roboto immediately
- [ ] Hard refresh /dashboard: Roboto still loads async (check Network tab for `media="print"`)
- [ ] Hard refresh /home: Roboto still loads async
- [ ] No duplicate Roboto stylesheet tags in page source
- [ ] No console warnings about unused preload

---

## Cumulative FOUC Fixes (Blog/Auth Routes)

After Phases 12-14, blog and auth routes now have:

| Resource | Loading | Phase |
|----------|---------|-------|
| nexus-instant-load.js | DISABLED | Phase 12 |
| Font Awesome | SYNC | Phase 13 |
| Roboto | SYNC | Phase 14 |
| blog-index.css | SYNC | Phase 8/11 |
| auth.css | SYNC | Phase 8 |
| utilities-polish.css | SYNC (blog) | Phase 10 |

**Result:** All three root causes identified in Phase 11 audit are now addressed for /news and /login.

---

## Files Modified

| File | Change |
|------|--------|
| `views/layouts/modern/header.php` | Wrapped Roboto loading in route conditional |

---

## Files NOT Modified

- `views/layouts/civicone/header.php` - CivicOne unchanged
- All CSS files - No CSS changes
- All JS files - No JS changes

---

## Related Phases

| Phase | Relationship |
|-------|--------------|
| Phase 8 | Defined route detection variables reused here |
| Phase 11 Audit | Identified Google Fonts async as #3 root cause |
| Phase 12 | Disabled nexus-instant-load.js (#1 root cause) |
| Phase 13 | Sync Font Awesome (#2 root cause) |

---

**Report Generated:** 27 January 2026
**Phase 14 Status:** COMPLETE - Sync Roboto for blog/auth routes
