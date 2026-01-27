# Phase 13: Sync Font Awesome for Blog/Auth Routes (Modern Theme Only)

**Date:** 27 January 2026
**Scope:** Modern theme only (CivicOne unchanged)
**Status:** IMPLEMENTED

---

## Summary

Made Font Awesome load synchronously (render-blocking) for blog and auth routes to eliminate icon "pop-in" where icons briefly appear as empty boxes before Font Awesome CSS applies. Other routes continue to use async loading for better performance.

---

## Problem

The Phase 11 root cause audit identified Font Awesome async loading as the #2 contributor to visual flash:

**Icons affected on /news:**
- `fa-solid fa-newspaper` (hero badge, empty state, placeholder)
- `fa-regular fa-calendar` (news card meta)
- `fa-solid fa-arrow-right` (read article button)
- `fa-regular fa-clock` (reading time)

**Behavior before fix:**
- Icons render as invisible/empty boxes for ~50-100ms
- When FA CSS loads, icons "pop in" suddenly
- Creates visible flash on above-fold content

---

## Change Made

### File: `views/layouts/modern/header.php`

**Before (lines 156-158):**
```html
<!-- Font Awesome (async) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous"></noscript>
```

**After:**
```php
<!-- Font Awesome -->
<?php if ($isBlogIndex || $isBlogShow || $isAuthPage): ?>
    <!-- PHASE 13 (2026-01-27): Sync FA for blog/auth routes to eliminate icon pop-in -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">
<?php else: ?>
    <!-- Async FA for other routes (non-blocking) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous"></noscript>
<?php endif; ?>
```

---

## Routes Covered

### Sync Font Awesome (render-blocking):

| Route Variable | Pattern | Examples |
|----------------|---------|----------|
| `$isBlogIndex` | `/news`, `/blog`, `/{tenant}/news`, `/{tenant}/blog` | /news, /hour-timebank/news |
| `$isBlogShow` | `/news/{slug}`, `/blog/{slug}` | /news/my-article, /blog/post-title |
| `$isAuthPage` | `/login`, `/register`, `/password` | /login, /hour-timebank/login |

### Async Font Awesome (non-blocking):

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

These variables are defined at lines 107-111 and reused for CSS and Font Awesome loading decisions.

---

## Trade-offs

| Aspect | Sync (blog/auth) | Async (other routes) |
|--------|------------------|----------------------|
| Icons visible at first paint | YES | NO (brief delay) |
| Render-blocking | YES (~30KB) | NO |
| Best for | Icon-heavy above-fold | Performance-critical pages |

---

## Expected Result

**On /news and /login:**
- Icons visible immediately at first paint
- No empty boxes or pop-in effect
- Slightly longer time to first paint (~30-50ms for FA download)

**On other routes:**
- FA loads asynchronously (non-blocking)
- Icons may pop-in but these pages have fewer above-fold icons
- Faster time to first paint

---

## Verification Checklist

- [ ] Hard refresh /news: calendar, newspaper, arrow, clock icons visible immediately
- [ ] Hard refresh /news/{slug}: icons visible immediately
- [ ] Hard refresh /login: any FA icons visible immediately
- [ ] Hard refresh /register: any FA icons visible immediately
- [ ] Hard refresh /dashboard: FA still loads async (check Network tab)
- [ ] Hard refresh /home: FA still loads async
- [ ] No duplicate FA stylesheet tags in page source
- [ ] No console warnings about unused preload

---

## Files Modified

| File | Change |
|------|--------|
| `views/layouts/modern/header.php` | Wrapped FA loading in route conditional |

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
| Phase 11 Audit | Identified FA async as #2 root cause |
| Phase 12 | Disabled nexus-instant-load.js (#1 root cause) |

---

**Report Generated:** 27 January 2026
**Phase 13 Status:** COMPLETE - Sync Font Awesome for blog/auth routes
