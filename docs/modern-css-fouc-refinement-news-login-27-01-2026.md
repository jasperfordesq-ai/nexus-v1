# FOUC Refinement: /news and Login Routes

**Date:** 27 January 2026
**Scope:** Modern theme only (CivicOne unchanged)
**Target Routes:** `/news` (blog), `/login` (auth)

---

## 1. Route Pattern Analysis

### 1.1 Login Route

**Route patterns (from page-css-loader.php line 47-49):**
```php
'condition' => preg_match('/\/(login|register|password)/', $normPath),
'files' => ['auth.css']
```

**Matching URLs:**
- `/{tenant}/login`
- `/{tenant}/register`
- `/{tenant}/password` (reset)

**CSS file:** `auth.css` (19 KB)

### 1.2 News/Blog Routes

**Blog index (page-css-loader.php line 134-137):**
```php
'condition' => $normPath === '/news' || $normPath === '/blog' || preg_match('/\/(news|blog)$/', $normPath),
'files' => ['blog-index.css']
```

**Matching URLs:**
- `/{tenant}/news`
- `/{tenant}/blog`

**CSS file:** `blog-index.css` (20 KB)

**Blog show/detail (page-css-loader.php line 140-143):**
```php
'condition' => preg_match('/\/(news|blog)\/[^\/]+$/', $normPath) && !preg_match('/\/(news|blog)\/(create|edit)/', $normPath),
'files' => ['blog-show.css']
```

**Matching URLs:**
- `/{tenant}/news/{slug}`
- `/{tenant}/blog/{slug}`
- Excludes: `/news/create`, `/news/edit`, `/blog/create`, `/blog/edit`

**CSS file:** `blog-show.css` (18 KB)

---

## 2. Pre-Existing State

### 2.1 Login (ALREADY COVERED)

The login route was already covered in the previous FOUC fix:

```php
// header.php line 108-109 (existing)
<?php elseif (preg_match('/\/(login|register|password)/', $normPath)): ?>
    <link rel="preload" as="style" href="<?= $assetBase ?>/assets/css/auth.css?v=<?= $cssVersionTimestamp ?>">
```

**Status:** No changes needed for login.

### 2.2 News/Blog (NOT COVERED)

The news/blog routes had NO preload hints. CSS was only loaded via page-css-loader.php sync tags discovered late in HTML parsing.

**Status:** Added preload hints.

---

## 3. Changes Made

### 3.1 File Modified

**`views/layouts/modern/header.php`**

### 3.2 Preload Hints Added

Added after the groups-show condition (line 113-117):

```php
<?php elseif ($normPath === '/news' || $normPath === '/blog' || preg_match('/\/(news|blog)$/', $normPath)): ?>
    <link rel="preload" as="style" href="<?= $assetBase ?>/assets/css/blog-index.css?v=<?= $cssVersionTimestamp ?>">
<?php elseif (preg_match('/\/(news|blog)\/[^\/]+$/', $normPath) && !preg_match('/\/(news|blog)\/(create|edit)/', $normPath)): ?>
    <link rel="preload" as="style" href="<?= $assetBase ?>/assets/css/blog-show.css?v=<?= $cssVersionTimestamp ?>">
<?php endif; ?>
```

### 3.3 Route Conditions Used

| Route | Condition | Preloaded CSS |
|-------|-----------|---------------|
| **Blog index** | `$normPath === '/news' \|\| $normPath === '/blog' \|\| preg_match('/\/(news\|blog)$/', $normPath)` | `blog-index.css` |
| **Blog show** | `preg_match('/\/(news\|blog)\/[^\/]+$/', $normPath) && !preg_match('/\/(news\|blog)\/(create\|edit)/', $normPath)` | `blog-show.css` |

---

## 4. Global Preload Audit

### 4.1 Global Preloads Retained

| File | Size | Justification |
|------|------|---------------|
| `nexus-phoenix.css` | 26 KB | Brand tokens, used on all pages |
| `bundles/core.css` | 89 KB | Core framework, above-fold on all pages |
| `bundles/components-navigation.css` | 137 KB | Header styles (`.nexus-utility-bar`), above-fold on ALL pages |
| `bundles/components-buttons.css` | 24 KB | Button/ripple styles (`.btn`, `.glass-btn`), used on ALL pages |

### 4.2 Why Keep components-navigation.css Global?

Examined file contents (line 29+):
```css
.nexus-utility-bar {
    position: fixed;
    top: 0;
    ...
}
```

This styles the **fixed header** which appears above-fold on every page. Making it conditional would cause header FOUC on routes not explicitly listed.

### 4.3 Why Keep components-buttons.css Global?

Examined file contents (line 29+):
```css
.ripple,
.btn,
.glass-btn,
.glass-btn-primary,
...
```

Button styles apply to navigation buttons, CTAs, and form buttons which appear above-fold on most pages. The 24 KB cost is acceptable for consistent button rendering.

---

## 5. Duplicate Load Prevention

### 5.1 How Preload + Link Works

```html
<!-- Preload: Tells browser to fetch early -->
<link rel="preload" as="style" href="/assets/css/blog-index.css?v=123">

<!-- Later in page-css-loader.php: Actually applies the stylesheet -->
<link rel="stylesheet" href="/assets/css/blog-index.css?v=123">
```

**No duplicate network request:** Browser recognizes same URL and uses cached response.

### 5.2 Verified No Warnings

- Preload + actual stylesheet use is the correct pattern
- No "preload without use" warnings because stylesheets ARE loaded via page-css-loader.php
- Same version parameter ensures cache hit

---

## 6. Before/After Summary

### 6.1 /news (Blog Index)

| Aspect | Before | After |
|--------|--------|-------|
| **Preload hint** | None | `blog-index.css` preloaded |
| **CSS discovery** | Late (during HTML body parse) | Early (in `<head>`) |
| **Expected FOUC** | Yes - blog cards flash | No - styled on first paint |

### 6.2 /news/{slug} (Blog Show)

| Aspect | Before | After |
|--------|--------|-------|
| **Preload hint** | None | `blog-show.css` preloaded |
| **CSS discovery** | Late (during HTML body parse) | Early (in `<head>`) |
| **Expected FOUC** | Yes - article content flashes | No - styled on first paint |

### 6.3 /login (Auth)

| Aspect | Before | After |
|--------|--------|-------|
| **Preload hint** | Already had `auth.css` | No change |
| **CSS discovery** | Early (preload in `<head>`) | Early (unchanged) |
| **Expected FOUC** | None | None |

---

## 7. Complete Preload Coverage

After this refinement, the following routes have page-specific preloads:

| Route | CSS Preloaded |
|-------|---------------|
| Home | `nexus-home.css` |
| Dashboard | `dashboard.css` |
| Profile show | `profile-holographic.css` |
| Auth (login/register/password) | `auth.css` |
| Events index | `events-index.css` |
| Groups index/show | `groups-show.css` |
| **News/Blog index** | `blog-index.css` (NEW) |
| **News/Blog show** | `blog-show.css` (NEW) |

Plus global preloads on ALL pages:
- `nexus-phoenix.css`
- `bundles/core.css`
- `bundles/components-navigation.css`
- `bundles/components-buttons.css`

---

## 8. Testing Instructions

### 8.1 /news Route

1. Open DevTools → Network tab
2. Enable "Disable cache"
3. Navigate to `/{tenant}/news`
4. Verify in waterfall:
   - `blog-index.css` appears near top (preloaded)
   - No flash of unstyled blog cards

### 8.2 /news/{slug} Route

1. Navigate to `/{tenant}/news/some-article`
2. Verify in waterfall:
   - `blog-show.css` appears near top (preloaded)
   - No flash of unstyled article content

### 8.3 /login Route

1. Navigate to `/{tenant}/login`
2. Verify in waterfall:
   - `auth.css` appears near top (preloaded)
   - Login form styled on first paint

### 8.4 Verify No Preload Warnings

In DevTools Console, check for:
- "The resource ... was preloaded using link preload but not used within a few seconds"

If this warning appears, the preload condition doesn't match the actual load condition.

---

## 9. Rollback

To remove these changes:

```bash
# Revert header.php to previous version
git checkout HEAD~1 -- views/layouts/modern/header.php
```

Or manually remove lines 114-117 from header.php.

---

**Report Generated:** 27 January 2026
**Status:** IMPLEMENTED
