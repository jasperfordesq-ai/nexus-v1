# Phase 16: Content-First Load - Remove Staged Reveal Animations

**Date:** 27 January 2026
**Scope:** Modern theme, /news route only
**Status:** IMPLEMENTED

---

## Summary

Removed the CSS animations that caused a staged "fade-in" reveal effect on the /news page. Cards and hero section now render immediately at full opacity without staggered animation delays.

---

## Problem

After Phases 8-15B addressed CSS loading order, Font Awesome, Roboto, and component bundles, /news still appeared to "snap" into view. The root cause was **CSS animations** that:

1. Started hero section at `opacity: 0` with `fadeInUp` animation
2. Started each news card at `opacity: 0` with `fadeInScale` animation
3. Applied staggered `animation-delay` (0.1s, 0.15s, 0.2s, etc.) to cards

Even though content was server-rendered and CSS was sync-loaded, the animations created a visible staged reveal effect that felt like "something loading then appearing."

---

## Root Cause Analysis

### Before (blog-index.css lines 50-105):

```css
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(var(--space-8)); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes fadeInScale {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}

#news-holo-wrapper .news-hero-section {
    animation: fadeInUp 0.6s ease-out;
}

#news-holo-wrapper .news-card {
    animation: fadeInScale 0.5s ease-out both;
}

#news-holo-wrapper .news-card:nth-child(1) { animation-delay: 0.1s; }
#news-holo-wrapper .news-card:nth-child(2) { animation-delay: 0.15s; }
/* ... etc ... */
```

**What this caused:**
- Hero renders invisible, then fades in over 0.6s
- Card 1 renders invisible for 0.1s, then fades in over 0.5s
- Card 2 renders invisible for 0.15s, then fades in over 0.5s
- ...and so on

The total perceived "staged load" was ~0.85s (0.35s delay + 0.5s animation for last card).

---

## Change Made

### File: `httpdocs/assets/css/blog-index.css`

**Before (lines 50-105):** Active keyframe animations with staggered delays

**After:** All animation code commented out with explanation:

```css
/* PHASE 16 (2026-01-27): Content reveal animations REMOVED for content-first load.
   Cards and hero now render immediately without staggered fade-in.
   This eliminates the perceived "snap" where content appears to load in stages.

   The original animations started elements at opacity:0 with animation-delay,
   causing a visible staged reveal even though content was server-rendered.

   To restore animations, uncomment the keyframes and animation rules below.
*/

/*
@keyframes fadeInUp { ... }
@keyframes fadeInScale { ... }
#news-holo-wrapper .news-hero-section { animation: fadeInUp 0.6s ease-out; }
#news-holo-wrapper .news-card { animation: fadeInScale 0.5s ease-out both; }
... animation-delay rules ...
*/
```

---

## What's Preserved

| Feature | Status |
|---------|--------|
| Grid visible at opacity: 1 | KEPT (Phase 11) |
| Empty state visible at opacity: 1 | KEPT (Phase 11) |
| Infinite scroll functionality | KEPT (unchanged) |
| Card hover animations | KEPT (transform on :hover) |
| Button press states | KEPT (transform on :active) |
| Holographic orb parallax | KEPT (scroll-based) |
| Offline banner | KEPT (show/hide on online/offline events) |

---

## What's Removed

| Animation | Effect | Reason for Removal |
|-----------|--------|-------------------|
| `fadeInUp` on `.news-hero-section` | Hero fades in from below | Creates delayed visibility |
| `fadeInScale` on `.news-card` | Cards fade in with scale | Creates delayed visibility |
| `animation-delay` on cards | Staggered card reveal | Creates staged "loading" perception |

---

## Expected Result

**Before Phase 16:**
```
t=0ms: Page renders with hero and cards at opacity: 0
t=100ms: Card 1 starts fading in
t=150ms: Card 2 starts fading in
...
t=850ms: All cards fully visible
```

**After Phase 16:**
```
t=0ms: Page renders with hero and cards at opacity: 1 (fully visible)
```

---

## Files Modified

| File | Change |
|------|--------|
| `httpdocs/assets/css/blog-index.css` | Commented out fadeInUp, fadeInScale keyframes and animation rules (lines 50-105) |

---

## Files NOT Modified

| File | Reason |
|------|--------|
| `views/modern/blog/index.php` | Phase 11 already removed JS skeleton reveal |
| `views/layouts/modern/header.php` | No changes needed |
| `views/layouts/modern/partials/css-loader.php` | No changes needed |
| Other routes | Only /news affected |

---

## Rollback Instructions

To restore the staggered animations (Phase 16 only):

1. Open `httpdocs/assets/css/blog-index.css`
2. Find the "PHASE 16" comment block (around line 50)
3. Uncomment the keyframes and animation rules inside the `/* ... */` block
4. Save the file

**Specific lines to uncomment:**
- `@keyframes fadeInUp { ... }`
- `@keyframes fadeInScale { ... }`
- `#news-holo-wrapper .news-hero-section { animation: ... }`
- `#news-holo-wrapper .news-card { animation: ... }`
- All `#news-holo-wrapper .news-card:nth-child(n) { animation-delay: ... }` rules

---

## Verification Checklist

- [ ] Hard refresh /news (DevTools cache disabled): page renders immediately with no staged fade-in
- [ ] Hero section visible immediately at full opacity
- [ ] All news cards visible immediately at full opacity
- [ ] Card hover effects still work (transform on hover)
- [ ] Button press states still work (scale on active)
- [ ] Infinite scroll still loads more posts
- [ ] No console errors
- [ ] /news/{slug} unchanged (article pages)
- [ ] Other routes unchanged

---

## Cumulative FOUC Fixes (Blog Routes)

After Phase 16, blog routes have ALL visual flash sources addressed:

| Source | Fix | Phase |
|--------|-----|-------|
| nexus-instant-load.js body hiding | DISABLED | Phase 12 |
| Font Awesome async | SYNC | Phase 13 |
| Roboto font async | SYNC | Phase 14 |
| components-navigation.css async | SYNC | Phase 15B |
| components-buttons.css async | SYNC | Phase 15B |
| blog-index.css async | SYNC | Phase 8/11 |
| utilities-polish.css async | SYNC | Phase 10 |
| CSS staggered reveal animations | REMOVED | Phase 16 |

---

**Report Generated:** 27 January 2026
**Phase 16 Status:** COMPLETE - Content renders immediately without animations
