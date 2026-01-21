# CivicOne Hero Implementation Guide

**Version:** 1.0.0
**Date:** 2026-01-21
**Implements:** Section 9C of CIVICONE_WCAG21AA_SOURCE_OF_TRUTH.md

---

## Overview

The CivicOne hero system provides consistent, accessible page headers across all CivicOne pages. Heroes follow GOV.UK design patterns and WCAG 2.1 AA accessibility standards.

## Files Created

### Core Components

| File | Purpose |
|------|---------|
| `config/heroes.php` | Route-to-hero configuration mapping |
| `app/Helpers/HeroResolver.php` | Resolves hero config from route + overrides |
| `views/layouts/civicone/partials/page-hero.php` | Renders hero HTML |
| `views/layouts/civicone/partials/render-hero.php` | Helper to auto-resolve and render hero |
| `httpdocs/assets/css/civicone-hero.css` | Hero styles (GOV.UK tokens) |
| `httpdocs/assets/css/civicone-hero.min.css` | Minified version (auto-generated) |

### Modified Files

| File | Change |
|------|--------|
| `views/layouts/civicone/header.php` | Updated comments (hero renders AFTER header include) |
| `views/layouts/civicone/partials/assets-css.php` | Added civicone-hero.min.css link |
| `scripts/minify-css.js` | Added civicone-hero.css to minify list |
| `purgecss.config.js` | Added civicone-hero.css to PurgeCSS |

---

## How It Works

### 1. Configuration (`config/heroes.php`)

Maps routes to hero settings:

```php
return [
    '/members' => [
        'variant' => 'page',
        'title' => 'Members Directory',
        'lead' => 'Connect with community members and discover their skills.',
    ],
    '/' => [
        'variant' => 'banner',
        'title' => 'Welcome to Your Community',
        'lead' => 'Connect, collaborate, and make a difference.',
        'cta' => [
            'text' => 'Get started',
            'url' => '/join',
        ],
    ],
];
```

### 2. Auto-Resolution

`HeroResolver::resolve($path, $overrides)` automatically:
- Matches current route to config
- Applies pattern matching for dynamic routes (`/members/123` → `/members/show`)
- Merges controller overrides
- Validates variant, title, CTA structure

### 3. Rendering

`partials/render-hero.php` automatically:
- Auto-resolves hero if not set
- Merges `$heroOverrides` if provided
- Renders hero HTML via `page-hero.php`

---

## Usage Patterns

### Pattern 1: Auto-Resolve (Recommended)

Let the system automatically resolve hero from route config:

```php
<?php
// Include header (opens <main> via main-open.php)
require __DIR__ . '/../../layouts/civicone/header.php';

// Render hero (auto-resolves from config/heroes.php)
require __DIR__ . '/../../layouts/civicone/partials/render-hero.php';
?>

<!-- Your page content here -->
<div class="civicone-width-container">
    <!-- Page-specific content -->
</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
```

### Pattern 2: Controller Override

Override specific hero properties from controller:

```php
<?php
// Controller sets dynamic title
$hero = [
    'title' => $member['name'], // e.g., "Jane Smith"
    'lead' => $member['headline'], // e.g., "Web Designer & Photographer"
];

// Include header
require __DIR__ . '/../../layouts/civicone/header.php';

// Render hero (merges controller override with config)
require __DIR__ . '/../../layouts/civicone/partials/render-hero.php';
?>

<!-- Page content -->
<div class="civicone-width-container">
    <!-- Profile content -->
</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
```

### Pattern 3: Full Manual Override

Completely override hero for special pages:

```php
<?php
// Set custom hero (ignores config)
$hero = [
    'variant' => 'banner',
    'title' => 'Special Event Page',
    'lead' => 'Join us for an exclusive community event.',
    'cta' => [
        'text' => 'Register now',
        'url' => '/events/special-event/register',
    ],
];

// Include header
require __DIR__ . '/../../layouts/civicone/header.php';

// Render hero (uses manual override)
require __DIR__ . '/../../layouts/civicone/partials/render-hero.php';
?>

<!-- Page content -->
<div class="civicone-width-container">
    <!-- Event content -->
</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
```

### Pattern 4: No Hero

Suppress hero for specific pages:

```php
<?php
// Suppress hero
$hero = null;

// Include header
require __DIR__ . '/../../layouts/civicone/header.php';

// render-hero.php won't render anything if $hero is null
require __DIR__ . '/../../layouts/civicone/partials/render-hero.php';
?>

<!-- Page content (no hero) -->
<div class="civicone-width-container">
    <!-- Content without hero -->
</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
```

---

## Hero Variants

### Page Hero (Default)

**Use for:** All standard pages (directories, detail pages, forms, content)

**Components:**
- H1 title (required)
- Lead paragraph (optional)
- NO call-to-action buttons

**Example:**
```php
$hero = [
    'variant' => 'page',
    'title' => 'Members Directory',
    'lead' => 'Connect with community members.',
];
```

### Banner Hero (Landing/Hub Only)

**Use for:** Landing pages, service hubs, onboarding flows

**Components:**
- H1 title (required)
- Lead paragraph (optional)
- Start button CTA (optional, max ONE)

**Example:**
```php
$hero = [
    'variant' => 'banner',
    'title' => 'Welcome to CivicOne',
    'lead' => 'Join your local timebanking community.',
    'cta' => [
        'text' => 'Get started',
        'url' => '/join',
    ],
];
```

---

## Configuration Reference

### Adding New Routes

Edit `config/heroes.php`:

```php
return [
    // Exact route match
    '/my-page' => [
        'variant' => 'page',
        'title' => 'My Page Title',
        'lead' => 'Optional lead paragraph',
    ],

    // Dynamic route (requires pattern in HeroResolver)
    '/my-resource/show' => [
        'variant' => 'page',
        // Title will be set dynamically by controller
    ],
];
```

### Adding Dynamic Route Patterns

Edit `app/Helpers/HeroResolver.php` → `matchRoutePattern()`:

```php
$patterns = [
    // Existing patterns...

    // Add new pattern
    '#^/my-resource/\d+$#' => '/my-resource/show',
];
```

---

## CSS Customization

Hero styles are in `httpdocs/assets/css/civicone-hero.css`.

**DO:**
- Use GOV.UK design tokens (`var(--civicone-space-6)`, etc.)
- Scope all selectors under `.civicone-hero`
- Test responsive behavior (375px, 768px, 1920px)
- Test zoom (200%, 400%)

**DON'T:**
- Add inline styles to page templates
- Use hardcoded pixel values
- Use background images with text
- Override focus states (GOV.UK yellow #ffdd00)

After editing CSS:
```bash
npm run minify:css
```

---

## Accessibility Checklist

Every hero MUST pass:

**Structure:**
- [ ] Hero renders after header include (not in cached header)
- [ ] Hero inside `<main id="main-content">`
- [ ] Exactly ONE `<h1>` per page
- [ ] H1 uses `.civicone-heading-xl` class
- [ ] Lead paragraph (if used) uses `.civicone-body-l` class
- [ ] Lead paragraph appears only once

**Banner Hero (if applicable):**
- [ ] Start button is `<a>` link (not `<button>`)
- [ ] Start button has `role="button"` and `draggable="false"`
- [ ] Start button includes arrow SVG with `aria-hidden="true"`
- [ ] Only ONE primary CTA in hero

**Keyboard & Focus:**
- [ ] H1 not focusable (headings are not interactive)
- [ ] Start button (if present) keyboard accessible (Tab, Enter)
- [ ] Start button has visible focus indicator (yellow #ffdd00)

**Visual:**
- [ ] No background images containing text
- [ ] Text contrast minimum 4.5:1
- [ ] Lead paragraph max-width 70ch
- [ ] Hero spacing uses GOV.UK tokens

---

## Installation Steps

If you're setting up the hero system for the first time:

1. **Regenerate Composer Autoload** (required after adding `App\` namespace):
   ```bash
   composer dump-autoload
   ```

2. **Minify CSS** (if you modified hero styles):
   ```bash
   npm run minify:css
   ```

3. **Clear PHP OpCache** (if using OpCache):
   ```php
   opcache_reset();
   ```
   Or restart your web server.

## Troubleshooting

### Hero doesn't appear

**Check:**
1. Is route configured in `config/heroes.php`?
2. Is `render-hero.php` included after header?
3. Is `$hero` set to `null` (suppresses hero)?
4. Check browser console for PHP errors

### Wrong hero text appears

**Check:**
1. Is header cached? (Clear cache)
2. Is controller override accidentally set?
3. Is route pattern matching correct in `HeroResolver`?

### Hero breaks caching

**Solution:**
- Hero MUST render in page templates, NOT in header.php
- Hero uses `render-hero.php` AFTER `header.php` include
- `header-cached.php` caches header WITHOUT hero

### Multiple H1s on page

**Solution:**
- Hero provides the ONE H1 per page
- Remove manual H1 from page content
- Use H2 for section headings

---

## Migration from Old Hero System

### Old Pattern (WRONG)
```php
<?php require __DIR__ . '/../../layouts/civicone/header.php'; ?>

<div class="civicone-width-container">
    <main class="civicone-main-wrapper" id="main-content">
        <h1>Page Title</h1>
        <p class="lead">Lead paragraph</p>
        <!-- Page content -->
    </main>
</div>

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
```

### New Pattern (CORRECT)
```php
<?php
// Include header (opens <main> via main-open.php)
require __DIR__ . '/../../layouts/civicone/header.php';

// Render hero (auto-resolves or uses controller override)
require __DIR__ . '/../../layouts/civicone/partials/render-hero.php';
?>

<!-- Page content (NO manual H1, NO width-container/main-wrapper opening) -->
<!-- Content goes here -->

<?php require __DIR__ . '/../../layouts/civicone/footer.php'; ?>
```

**Key Changes:**
1. Remove manual `<main>` opening (header.php does it)
2. Remove manual H1 (hero provides it)
3. Add `render-hero.php` include after header
4. Configure hero in `config/heroes.php` or set `$hero` before header

---

## Support

For questions or issues:
1. Read Section 9C of `docs/CIVICONE_WCAG21AA_SOURCE_OF_TRUTH.md`
2. Check examples in this guide
3. Review `config/heroes.php` for route patterns
4. Test in browser with DevTools accessibility tab
