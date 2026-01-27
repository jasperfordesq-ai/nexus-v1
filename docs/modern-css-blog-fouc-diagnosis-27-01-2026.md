# Blog Page (/news) FOUC Forensic Diagnosis

**Date:** 27 January 2026
**Scope:** Modern theme only (CivicOne excluded)
**Status:** DIAGNOSIS ONLY - No fixes implemented

---

## 1. Template Analysis

### 1.1 Blog Index Template

**Path:** `views/modern/blog/index.php`

**Rendering method:** Server-rendered HTML

The blog content is rendered server-side:
- Line 63-65: `<div class="news-grid">` wraps `<?php require __DIR__ . '/partials/feed-items.php'; ?>`
- The posts are already in the HTML when the page loads
- This is NOT client-side rendered

### 1.2 Blog Card Partial

**Path:** `views/modern/blog/partials/feed-items.php`

Contains server-rendered `<article class="news-card">` elements with:
- Image: `<img>` with `loading="lazy"` but NO width/height attributes
- Content: Title, excerpt, meta, action buttons

### 1.3 Blog Show Template

**Path:** `views/modern/blog/show.php`

Single article view - similar structure.

---

## 2. JavaScript Analysis

### 2.1 Inline JS in index.php (lines 76-235)

| Function | Purpose | DOM Modification |
|----------|---------|------------------|
| `initSkeletonLoader()` | Hides skeleton after 300ms delay | Adds `hidden` class to `#newsSkeleton`, adds `content-loaded` class to grid |
| `initOfflineIndicator()` | Shows offline banner | Adds/removes `visible` class |
| Button press states | Touch feedback | Inline style transform |
| `initDynamicThemeColor()` | Updates meta theme-color | Meta tag only |
| Infinite scroll IIFE | Loads more posts on scroll | Appends HTML to `#news-grid-container` via `fetch()` |
| `initParallaxOrbs()` | Parallax effect on orbs | Inline style transform |

### 2.2 Key Finding: Skeleton Transition Logic

```javascript
// Line 82-95
(function initSkeletonLoader() {
    const skeleton = document.getElementById('newsSkeleton');
    const grid = document.getElementById('news-grid-container');
    const emptyState = document.querySelector('.news-empty-state');

    if (!skeleton) return;

    // Hide skeleton and show content after short delay
    setTimeout(function() {
        skeleton.classList.add('hidden');          // ← Adds .hidden class
        if (grid) grid.classList.add('content-loaded');  // ← Adds .content-loaded class
        if (emptyState) emptyState.classList.add('content-loaded');
    }, 300);
})();
```

**The JS expects:**
1. `.skeleton-container.hidden` to hide the skeleton
2. `.content-loaded` to reveal the grid

---

## 3. CSS Dependency Analysis

### 3.1 CSS Load Order for /news

| Order | File | Load Method | Contains Blog Styles? |
|-------|------|-------------|----------------------|
| 1 | `design-tokens.css` | Sync (header.php) | No |
| 2 | `nexus-phoenix.css` | Sync | No |
| 3 | `modern-theme-tokens.css` | Sync | No |
| 4 | `modern-primitives.css` | Sync | No |
| 5 | `bundles/core.css` | Sync | No |
| 6 | `bundles/components.css` | Sync | Some skeleton |
| 7 | `bundles/modern-pages.css` | Sync | **CRITICAL CONFLICT** |
| 8 | `blog-index.css` | Sync (page-specific) | Yes - main styles |
| 9 | `components-navigation.css` | **Async** | No |
| 10 | `utilities-polish.css` | **Async** | Skeleton animations |

### 3.2 Critical CSS Conflict

**File:** `bundles/modern-pages.css` (line 4907-4910)

```css
.skeleton-container {
    display: none !important;
    /* DISABLED: Hide skeleton completely - feed shows instantly */
}
```

**vs. Critical CSS** (`critical-css.php` line 136-137):

```css
.skeleton-container{opacity:1;transition:opacity var(--transition-base)}
.skeleton-container.hydrated{opacity:0;pointer-events:none;position:absolute}
```

**Result:** The `display: none !important` in modern-pages.css KILLS the skeleton immediately, regardless of the JS logic.

### 3.3 Missing CSS Definitions

**blog-index.css is MISSING:**

| Class | Expected By | Defined Where | Actually Loaded? |
|-------|-------------|---------------|------------------|
| `.skeleton-container.hidden` | JS line 91 | `loading-skeletons.css` | **NO** |
| `.content-loaded` | JS line 92-93 | `loading-skeletons.css` | **NO** |
| `.news-skeleton-grid` | HTML line 36 | `bundles/polish.css` | **NO** |
| `.news-card-skeleton` | HTML line 38 | `bundles/polish.css` | **NO** |

**`bundles/polish.css` is NEVER loaded in Modern theme!**

---

## 4. Layout Shift Risks

### 4.1 Image Sizing

**Template** (`feed-items.php` line 15):
```html
<img src="..." loading="lazy" alt="...">
```

**Issues:**
- ❌ No `width` attribute
- ❌ No `height` attribute
- ✅ CSS sets fixed height: `.news-card-image { height: 220px; }`

**Assessment:** Low CLS risk due to CSS fixed height, but no intrinsic aspect-ratio.

### 4.2 Font Loading

- Google Fonts (Roboto) loads async with `display=swap`
- Font Awesome loads async
- Both may cause minor text reflow but not major FOUC

### 4.3 Grid Layout

**CSS** (`blog-index.css` line 320-324):
```css
.news-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: var(--space-8);
}
```

**Assessment:** Grid is stable once CSS loads. Issue is timing of CSS application.

---

## 5. Root Cause Determination

### PRIMARY CAUSE: **Late-loaded CSS + Skeleton System Conflict**

**Evidence:**

1. **modern-pages.css** (sync, line 4908) sets:
   ```css
   .skeleton-container { display: none !important; }
   ```
   This IMMEDIATELY hides the skeleton container before JS can run.

2. **The skeleton is server-rendered** but hidden by CSS before paint, so:
   - User sees unstyled blog grid BEFORE blog-index.css applies
   - The intended skeleton → content transition never occurs

3. **blog-index.css loads AFTER modern-pages.css** but:
   - It doesn't override the skeleton visibility
   - It doesn't define `.hidden` or `.content-loaded` classes

4. **The news-specific skeleton styles** (`.news-skeleton-grid`, `.news-card-skeleton`) are in `bundles/polish.css` which is **NEVER LOADED**.

### SECONDARY CAUSES:

| Cause | Impact | Evidence |
|-------|--------|----------|
| Missing skeleton CSS | High | `news-skeleton-grid` not defined in loaded bundles |
| No preload for blog-index.css | Medium | Added in previous fix but async components still race |
| Images without dimensions | Low | CSS fixes height but no aspect-ratio hint |

---

## 6. Recommended Minimal Fix (PROPOSAL ONLY)

### Option: Add Blog-Specific Skeleton & Transition CSS to blog-index.css

**Scope:** Only modifies `httpdocs/assets/css/blog-index.css`

**Changes:**

1. Add skeleton container overrides for blog page:
```css
/* Override global skeleton hide for blog page */
#news-holo-wrapper .skeleton-container {
    display: grid !important; /* Override modern-pages.css */
    opacity: 1;
    transition: opacity 0.3s ease;
}

#news-holo-wrapper .skeleton-container.hidden {
    display: none !important;
    opacity: 0;
}
```

2. Add content-loaded reveal:
```css
#news-holo-wrapper .news-grid {
    opacity: 0;
    transition: opacity 0.4s ease;
}

#news-holo-wrapper .news-grid.content-loaded {
    opacity: 1;
}
```

3. Add missing skeleton card styles (copy from polish.css):
```css
/* News skeleton card */
.news-card-skeleton {
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: var(--radius-2xl);
    overflow: hidden;
}

.news-skeleton-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: var(--space-8);
}

/* ... responsive variants ... */
```

**Why this fix:**
- Blog-scoped only (uses `#news-holo-wrapper` prefix)
- Doesn't modify global loaders
- Overrides the problematic `display: none !important` specifically for /news
- Provides the missing transition classes the JS expects
- Low risk to other pages

**Risk assessment:** LOW
- Only affects /news route
- Uses higher specificity to override global rule
- Can be reverted by removing the CSS block

---

## 7. Files Examined

| File | Purpose |
|------|---------|
| `views/modern/blog/index.php` | Blog index template |
| `views/modern/blog/partials/feed-items.php` | Blog card partial |
| `views/modern/blog/show.php` | Blog detail template |
| `httpdocs/assets/css/blog-index.css` | Blog page styles |
| `httpdocs/assets/css/bundles/modern-pages.css` | Modern pages bundle (conflict) |
| `httpdocs/assets/css/bundles/polish.css` | Skeleton styles (not loaded) |
| `httpdocs/assets/css/loading-skeletons.css` | Skeleton utilities (not loaded) |
| `views/layouts/modern/critical-css.php` | Inline critical CSS |
| `views/layouts/modern/partials/css-loader.php` | CSS load order |
| `views/layouts/modern/partials/page-css-loader.php` | Page-specific CSS |

---

## 8. Summary

| Item | Finding |
|------|---------|
| **Primary Cause** | `modern-pages.css` sets `.skeleton-container { display: none !important }` which overrides skeleton system |
| **Template Type** | Server-rendered HTML (not JS-injected) |
| **JS Role** | Transitions skeleton → content, but CSS conflict prevents this |
| **Missing CSS** | `.hidden`, `.content-loaded`, `.news-skeleton-grid` not loaded |
| **Recommended Fix** | Add blog-scoped skeleton overrides to `blog-index.css` |
| **Risk Level** | Low (blog-scoped only) |

---

**Report Generated:** 27 January 2026
**Status:** DIAGNOSIS COMPLETE - Awaiting fix approval
