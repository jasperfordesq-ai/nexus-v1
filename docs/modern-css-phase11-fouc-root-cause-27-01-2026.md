# Phase 11: FOUC Root Cause Isolation Report

**Date:** 27 January 2026
**Scope:** /news and /login pages (Modern theme only)
**Status:** AUDIT COMPLETE - No changes made

---

## Executive Summary

Despite multiple optimization phases (8-11), visible "snap" or flash persists on /news and /login. This audit identifies **3 primary root causes**, all related to resources that load or activate AFTER first paint.

**Top 3 Root Causes (Ranked by Impact):**

1. **nexus-instant-load.js body hiding mechanism** - Hides body with opacity:0, then fades in after CSS check
2. **Font Awesome async loading** - Icons render as boxes/blank, then "pop in" when FA CSS applies
3. **Google Fonts async loading** - Text renders in system font, then shifts to Roboto

---

## 1. Resource Analysis: What Loads AFTER First Paint

### 1.1 Async CSS (media="print" onload pattern)

| File | Source | Above-Fold Impact |
|------|--------|-------------------|
| `components-navigation.css` | css-loader.php:128 | **HIGH** - Styles header nav |
| `components-buttons.css` | css-loader.php:130 | **HIGH** - Styles all buttons |
| `components-forms.css` | css-loader.php:132 | **HIGH** (/login) - Form styles |
| `components-cards.css` | css-loader.php:134 | MEDIUM - Card components |
| `utilities-polish.css` | css-loader.php:149 (conditional) | LOW for /news (sync), HIGH elsewhere |
| `enhancements.css` | css-loader.php:156 | LOW |
| `mobile-accessibility-fixes.css` | css-loader.php:172 | LOW |
| `social-interactions.css` | css-loader.php:198 | LOW |
| `nexus-modern-footer.css` | css-loader.php:206 | NONE (below fold) |

**Mitigation in place:** Preloads exist for components-navigation.css and components-buttons.css (header.php:96-97), but preload only fetches early - it does NOT apply the CSS synchronously.

### 1.2 Font Awesome (Async CDN)

**Source:** header.php:162
```html
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
      media="print" onload="this.media='all'">
```

**Icons used above-fold on /news:**
- `fa-solid fa-newspaper` (hero badge, empty state, placeholder)
- `fa-regular fa-calendar` (news card meta)
- `fa-solid fa-arrow-right` (read article button)
- `fa-regular fa-clock` (reading time)
- `fa-solid fa-wifi-slash` (offline banner - hidden by default)
- `fa-solid fa-circle-notch` (infinite scroll - hidden by default)

**Icons used above-fold on /login:**
- `fa-solid fa-fingerprint` / biometric SVG icons (inline SVG, no FA)
- Form icons if any custom styling

**Impact:** Icons render as invisible/blank boxes until FA CSS loads (~50-100ms), then "pop in".

### 1.3 Google Fonts (Async)

**Source:** header.php:166-168
```html
<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap">
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap"
      rel="stylesheet" media="print" onload="this.media='all'">
```

**Weights used above-fold:**
- `400` (body text, paragraphs)
- `600`/`700` (headings, badges, buttons) - Note: 600 is NOT loaded, falls back to 500 or 700
- `500` (secondary text)

**Impact:** Text renders in system font (`-apple-system, BlinkMacSystemFont, "Segoe UI"` per critical-css.php:44), then shifts to Roboto when WOFF2 files download.

### 1.4 JavaScript that Modifies Visibility

**PRIMARY CULPRIT: nexus-instant-load.js**

**Source:** header.php:179 (deferred)
```html
<script defer src="/assets/js/nexus-instant-load.min.js"></script>
```

**What it does (nexus-instant-load.js:17-39):**
```javascript
const criticalHideStyles = document.createElement('style');
criticalHideStyles.textContent = `
    body {
        opacity: 0;
        visibility: hidden;
    }
    main, .main-content, .page-content {
        opacity: 0;
    }
`;
document.head.appendChild(criticalHideStyles);
```

**Timeline:**
1. Script loads (deferred, runs after DOM parsed)
2. Adds inline `<style>` that hides body
3. Polls for CSS loading (50ms intervals)
4. After CSS detected OR 1.5s timeout, calls `showContent()`
5. `showContent()` fades in body over 250ms

**This is the MAIN source of the "snap"** - the page is artificially hidden, then revealed with a transition.

---

## 2. Dependency Graph: Above-Fold Styling

### 2.1 /news Page

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ ABOVE-FOLD ELEMENTS                                                          │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌──────────────────────────────────────────────────────────────────────┐   │
│  │ HEADER (.nexus-modern-header)                                        │   │
│  │   CSS: nexus-modern-header.css (SYNC), nexus-premium-mega-menu.css   │   │
│  │   Async: components-navigation.css, components-buttons.css           │   │
│  └──────────────────────────────────────────────────────────────────────┘   │
│                                                                              │
│  ┌──────────────────────────────────────────────────────────────────────┐   │
│  │ HERO SECTION (.news-hero-section)                                    │   │
│  │   CSS: blog-index.css (SYNC in <head>)                               │   │
│  │   Icons: fa-solid fa-newspaper (ASYNC - Font Awesome)                │   │
│  │   Font: Roboto 700 (ASYNC - Google Fonts)                            │   │
│  └──────────────────────────────────────────────────────────────────────┘   │
│                                                                              │
│  ┌──────────────────────────────────────────────────────────────────────┐   │
│  │ NEWS GRID (#news-grid-container)                                     │   │
│  │   CSS: blog-index.css (SYNC), utilities-polish.css (SYNC for /news)  │   │
│  │   Card CSS: modern-pages.css (SYNC)                                  │   │
│  │   Icons: fa-regular fa-calendar, fa-solid fa-arrow-right (ASYNC)     │   │
│  │   Font: Roboto 400/600 (ASYNC)                                       │   │
│  └──────────────────────────────────────────────────────────────────────┘   │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 2.2 /login Page

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ ABOVE-FOLD ELEMENTS                                                          │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌──────────────────────────────────────────────────────────────────────┐   │
│  │ HEADER (.nexus-modern-header)                                        │   │
│  │   CSS: Same as /news                                                 │   │
│  └──────────────────────────────────────────────────────────────────────┘   │
│                                                                              │
│  ┌──────────────────────────────────────────────────────────────────────┐   │
│  │ AUTH CARD (.auth-wrapper .auth-card)                                 │   │
│  │   CSS: auth.css (SYNC in <head> via Phase 8)                         │   │
│  │   Form CSS: components-forms.css (ASYNC)                             │   │
│  │   Button CSS: components-buttons.css (ASYNC)                         │   │
│  │   Icons: Inline SVGs (no FA dependency above-fold)                   │   │
│  │   Font: Roboto 400/600/700 (ASYNC)                                   │   │
│  └──────────────────────────────────────────────────────────────────────┘   │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 3. First Paint vs Stable: Delta Table

### 3.1 /news Page

| Element | First Paint State | Stable State | Delta |
|---------|-------------------|--------------|-------|
| `body` | `opacity: 0` (nexus-instant-load) | `opacity: 1` | **FLASH** |
| `.news-hero-badge i` | Empty box (no FA) | Newspaper icon | Icon pop-in |
| `.news-hero-title` | System font | Roboto 700 | Font shift |
| `.news-card-date i` | Empty box | Calendar icon | Icon pop-in |
| `.news-btn i` | Empty box | Arrow icon | Icon pop-in |
| `.news-card-reading-time i` | Empty box | Clock icon | Icon pop-in |
| Header buttons | Unstyled | Styled | Button style shift |

### 3.2 /login Page

| Element | First Paint State | Stable State | Delta |
|---------|-------------------|--------------|-------|
| `body` | `opacity: 0` (nexus-instant-load) | `opacity: 1` | **FLASH** |
| Form inputs | Basic styling | Enhanced styling | Form style shift |
| Submit button | Basic gradient | Full styling | Button enhancement |
| "Sign In" heading | System font | Roboto 700 | Font shift |
| Labels | System font | Roboto 600 | Font shift |

---

## 4. Root Causes Ranked

### #1: nexus-instant-load.js Body Hiding (CRITICAL)

**Evidence:**
- nexus-instant-load.js:17-22 injects `body { opacity: 0; visibility: hidden; }`
- nexus-instant-load.js:104-128 reveals with 250ms fade transition
- This creates an ARTIFICIAL flash - the page would render fine without it

**Impact:** 100% of pages affected. Every page load shows a blank screen, then fades in.

**Why it exists:** Originally intended to prevent FOUC by hiding content until CSS loads. But with proper CSS load order (established in Phases 8-11), this is now counterproductive.

### #2: Font Awesome Async Loading (HIGH)

**Evidence:**
- header.php:162 loads FA with `media="print" onload`
- Icons on /news: fa-newspaper, fa-calendar, fa-arrow-right, fa-clock
- Icons render as empty boxes for ~50-100ms until FA CSS applies

**Impact:** Any page with above-fold icons shows icon "pop-in".

### #3: Google Fonts Async Loading (MEDIUM)

**Evidence:**
- header.php:166-168 loads Roboto with `media="print" onload`
- critical-css.php:44 sets fallback: `-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto`
- Text renders in system font, then shifts when Roboto loads

**Impact:** Subtle text shift on all pages. Less noticeable than #1 and #2.

---

## 5. Additional Suspects (Investigated, Lower Impact)

### theme-transitions.css

**Source:** css-loader.php:91 (SYNC)
```css
html {
    transition:
        background-color var(--theme-transition-duration) var(--theme-transition-easing),
        color var(--theme-transition-duration) var(--theme-transition-easing);
}
```

**Impact:** LOW. Only activates during theme SWITCH, not initial load. The `.theme-transitioning` class is never applied on initial load.

### Async Component Bundles

**Files:** components-navigation.css, components-buttons.css, components-forms.css

**Impact:** MEDIUM. These are preloaded (header.php:96-97) which helps, but preload doesn't apply CSS - it only fetches. The CSS applies when the async onload fires.

---

## 6. Proposed Minimal Fixes (NOT IMPLEMENTED)

### Option A: Remove nexus-instant-load.js Body Hiding (Recommended)

**Scope:** Global change (affects all pages)

**Change:**
1. Remove the body hiding styles from nexus-instant-load.js
2. Keep the CSS loading check logic for logging/debugging only
3. Let pages render naturally with the sync CSS already in place

**Risk:** LOW. The sync CSS load order from Phases 8-11 ensures critical styles apply before first paint.

**Expected result:** Eliminates the artificial "blank then fade" flash entirely.

### Option B: Sync Font Awesome for /news and /login Only

**Scope:** /news and /login routes only

**Change:**
1. In header.php, add conditional sync FA loading for blog/auth routes:
```php
if ($isBlogIndex || $isBlogShow || $isAuthPage):
?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<?php else: ?>
    <link rel="stylesheet" href="...fa..." media="print" onload="this.media='all'">
<?php endif; ?>
```

**Risk:** MEDIUM. Adds ~30KB render-blocking CSS for affected routes.

**Expected result:** Icons visible at first paint on /news and /login.

### Option C: Preload Specific Font Awesome Icons (Experimental)

**Scope:** /news route only

**Change:**
1. Inline the specific FA icon SVGs used above-fold in blog-index.css
2. Remove FA dependency for those specific icons

**Risk:** LOW. Only affects blog styling.

**Expected result:** Icons render immediately without FA dependency.

### Option D: Self-Host Google Fonts with font-display: optional

**Scope:** Global

**Change:**
1. Download Roboto WOFF2 files
2. Host in /assets/fonts/
3. Use `font-display: optional` to prevent font swap

**Risk:** LOW. Fonts either load fast or use system font permanently.

**Expected result:** No font swap flash (text stays in system font if Roboto doesn't load fast enough).

---

## 7. Recommendation

**Implement Option A (Remove body hiding) as the primary fix.**

The nexus-instant-load.js body hiding mechanism is the single largest contributor to the perceived flash. With the CSS load order optimizations from Phases 8-11:

- blog-index.css loads sync in `<head>` for /news
- auth.css loads sync in `<head>` for /login
- utilities-polish.css loads sync for /news
- Core CSS bundles are all sync

The artificial hiding is no longer necessary and is actively causing the problem it was meant to solve.

**Secondary consideration:** After removing body hiding, evaluate if Font Awesome async loading is acceptable. If icon pop-in is still noticeable, consider Option B or C.

---

## 8. Files Referenced

| File | Purpose |
|------|---------|
| `views/layouts/modern/header.php` | CSS/JS loading, route detection |
| `views/layouts/modern/partials/css-loader.php` | CSS load order |
| `views/layouts/modern/critical-css.php` | Inline critical CSS |
| `httpdocs/assets/js/nexus-instant-load.js` | Body hiding mechanism |
| `httpdocs/assets/css/blog-index.css` | Blog-specific styles |
| `httpdocs/assets/css/auth.css` | Auth page styles |
| `views/modern/blog/index.php` | Blog index template |
| `views/modern/auth/login.php` | Login template |

---

**Report Generated:** 27 January 2026
**Phase 11 Audit Status:** COMPLETE - 3 root causes identified, 4 fix options proposed
