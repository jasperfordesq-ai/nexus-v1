# Blog Page (/news) FOUC Fix Implementation

**Date:** 27 January 2026
**Scope:** Modern theme only (CivicOne excluded)
**Status:** IMPLEMENTED

---

## 1. Summary

This fix addresses the Flash of Unstyled Content (FOUC) on the blog index page (`/news`) by adding blog-scoped CSS overrides to `blog-index.css`. The fix restores the intended skeleton → content reveal transition that was being blocked by a global CSS conflict.

---

## 2. Root Cause (from Diagnosis)

**Primary Cause:** `bundles/modern-pages.css` (line 4907-4910) sets:
```css
.skeleton-container {
    display: none !important;
}
```

This immediately hides the skeleton container before the JS skeleton loader can run its 300ms transition logic. The intended flow was:
1. Show skeleton immediately
2. After 300ms, JS adds `.hidden` class to skeleton
3. JS adds `.content-loaded` class to grid
4. Skeleton fades out, grid fades in

**Secondary Cause:** The CSS classes that JS expects (`.hidden`, `.content-loaded`) and the skeleton card styles (`.news-skeleton-grid`, `.news-card-skeleton`) were defined in `bundles/polish.css` but that file is **never loaded** in the Modern theme.

---

## 3. Fix Applied

### 3.1 File Modified

**`httpdocs/assets/css/blog-index.css`**

Added 134 lines of CSS at the top of the file under the header:
```css
/* Blog skeleton + reveal fixes (27/01/2026) */
```

### 3.2 Changes Made

| Category | CSS Rules Added | Purpose |
|----------|-----------------|---------|
| **Skeleton Override** | `#news-holo-wrapper .skeleton-container { display: grid !important; }` | Override global `display: none` from modern-pages.css |
| **Hidden Transition** | `#news-holo-wrapper .skeleton-container.hidden { opacity: 0; visibility: hidden; }` | Provide the `.hidden` class JS expects |
| **Grid Reveal** | `#news-holo-wrapper #news-grid-container { opacity: 0; }` + `.content-loaded { opacity: 1; }` | Hide grid until loaded |
| **Empty State** | Same pattern for `.news-empty-state` | Support empty state reveal |
| **Skeleton Grid** | `.news-skeleton-grid` with responsive breakpoints | 3-col → 2-col → 1-col grid layout |
| **Skeleton Cards** | `.news-card-skeleton` + children | Visual appearance of placeholder cards |
| **Reduced Motion** | `@media (prefers-reduced-motion: reduce)` | Accessibility: instant transitions |

### 3.3 Design Tokens Used

All styles use existing design tokens:
- Spacing: `var(--space-2)` through `var(--space-20)`
- Radius: `var(--radius-md)`, `var(--radius-2xl)`
- Effects: `var(--effect-white-10)`, `var(--effect-purple-10)`, etc.
- Typography: `var(--font-size-sm)`, `var(--space-6)`

### 3.4 Scope Limitation

All rules are prefixed with `#news-holo-wrapper` to ensure:
- Changes only affect the blog page
- No risk of breaking other pages
- Higher specificity to override the global `!important`

---

## 4. Files NOT Modified

| File | Reason |
|------|--------|
| `bundles/modern-pages.css` | Per requirements - no bundle changes |
| `css-loader.php` | Per requirements - no loader changes |
| `page-css-loader.php` | Per requirements - no loader changes |
| `blog-show.css` | Blog detail page has no skeleton loader |
| `views/modern/blog/index.php` | Template already had correct HTML structure |

---

## 5. HTML Structure Verification

The CSS selectors were verified against `views/modern/blog/index.php`:

| Selector | Line | Matches |
|----------|------|---------|
| `#news-holo-wrapper` | 16 | `<div id="news-holo-wrapper">` |
| `.skeleton-container` | 36 | `<div class="news-skeleton-grid skeleton-container" ...>` |
| `#newsSkeleton` | 36 | `id="newsSkeleton"` |
| `#news-grid-container` | 63 | `<div class="news-grid" id="news-grid-container">` |
| `.news-empty-state` | 54 | `<div class="news-empty-state">` |
| `.news-skeleton-grid` | 36 | Class on skeleton container |
| `.news-card-skeleton` | 38 | `<div class="news-card-skeleton">` |

---

## 6. Expected Behavior After Fix

### Page Load Sequence:
1. **0ms:** Skeleton grid visible (6 placeholder cards)
2. **0-300ms:** Skeleton animates with shimmer effect
3. **300ms:** JS adds `.hidden` to skeleton, `.content-loaded` to grid
4. **300-700ms:** Skeleton fades out (opacity 0), grid fades in (opacity 1)
5. **700ms+:** Full content visible, skeleton hidden

### Visual States:
- **During skeleton:** Purple/cyan gradient shimmer cards
- **Transition:** Smooth opacity crossfade
- **Content loaded:** News cards with staggered animation (existing fadeInScale)

---

## 7. Testing Checklist

### Manual Testing:
- [ ] Hard refresh with DevTools cache disabled
- [ ] Skeleton visible immediately on page load
- [ ] Skeleton fades out after ~300ms
- [ ] Grid fades in after skeleton fades
- [ ] No flash of unstyled content
- [ ] Works on both light and dark themes
- [ ] Responsive: Test at 320px, 768px, 1200px+ widths
- [ ] Reduced motion: Transitions are instant

### DevTools Verification:
1. Network tab: Confirm `blog-index.css` loads
2. Elements tab: Watch for `.hidden` class added to `#newsSkeleton`
3. Elements tab: Watch for `.content-loaded` class added to `#news-grid-container`

---

## 8. Rollback Instructions

To revert this fix, remove the CSS block from `blog-index.css`:

1. Open `httpdocs/assets/css/blog-index.css`
2. Delete lines 1-134 (everything before `/* GOLD STANDARD - Native App Features */`)
3. Save and deploy

Or use git:
```bash
git checkout HEAD~1 -- httpdocs/assets/css/blog-index.css
```

---

## 9. Related Files

| File | Purpose |
|------|---------|
| `docs/modern-css-blog-fouc-diagnosis-27-01-2026.md` | Diagnosis report |
| `docs/modern-css-fouc-fix-27-01-2026.md` | Initial FOUC fix (preloads) |
| `views/modern/blog/index.php` | Blog template with skeleton HTML |
| `bundles/modern-pages.css` | Contains the conflicting global rule |
| `bundles/polish.css` | Contains original skeleton styles (not loaded) |

---

## 10. CSS Diff Summary

```diff
+ /* Blog skeleton + reveal fixes (27/01/2026) */
+ #news-holo-wrapper .skeleton-container { display: grid !important; opacity: 1; transition: opacity 300ms ease; }
+ #news-holo-wrapper .skeleton-container.hidden { opacity: 0; pointer-events: none; visibility: hidden; }
+ #news-holo-wrapper #news-grid-container { opacity: 0; transition: opacity 400ms ease; }
+ #news-holo-wrapper #news-grid-container.content-loaded { opacity: 1; }
+ #news-holo-wrapper .news-empty-state { opacity: 0; transition: opacity 400ms ease; }
+ #news-holo-wrapper .news-empty-state.content-loaded { opacity: 1; }
+ #news-holo-wrapper .news-skeleton-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--space-8); }
+ /* ... responsive variants ... */
+ #news-holo-wrapper .news-card-skeleton { ... skeleton card styles ... }
+ @media (prefers-reduced-motion: reduce) { ... instant transitions ... }
```

---

**Report Generated:** 27 January 2026
**Fix Status:** IMPLEMENTED - Ready for testing
