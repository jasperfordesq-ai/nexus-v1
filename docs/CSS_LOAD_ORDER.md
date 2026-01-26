# CSS Load Order - Critical Documentation

> **WARNING**: Changing CSS load order can break the entire theme. Read this document before making ANY changes to CSS loading.

## Overview

The Modern theme CSS is loaded in a specific order that MUST be maintained. The system loads ~60+ CSS files with complex dependencies.

## Load Order Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│  1. DESIGN TOKENS (header.php - line 64)                        │
│     └── design-tokens.css                                       │
│         • MUST load FIRST                                       │
│         • Defines ALL CSS variables (321+ colors, spacing, etc) │
│         • ALL other CSS files depend on this                    │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│  2. HEADER EXTRACTED (header.php - line 66)                     │
│     └── nexus-header-extracted.css                              │
│         • Critical scroll/layout styles                         │
│         • Prevents FOUC (Flash of Unstyled Content)             │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│  3. CRITICAL CSS (css-loader.php - Section 1)                   │
│     ├── nexus-phoenix.css                                       │
│     ├── bundles/core.css                                        │
│     ├── bundles/components.css                                  │
│     ├── theme-transitions.css                                   │
│     └── modern-experimental-banner.css                          │
│         • Render-blocking (sync load)                           │
│         • Framework and core styles                             │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│  4. HEADER & NAVIGATION (css-loader.php - Section 2)            │
│     ├── nexus-modern-header.css                                 │
│     ├── nexus-premium-mega-menu.css                             │
│     ├── mega-menu-icons.css                                     │
│     ├── nexus-native-nav-v2.css                                 │
│     ├── mobile-nav-v2.css                                       │
│     ├── modern-header-utilities.css                             │
│     └── modern-header-emergency-fixes.css                       │
│         • Sync load - above fold                                │
│         • Navigation must render immediately                    │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│  5. MODERN PAGES BASE (css-loader.php - Section 2b)             │
│     └── bundles/modern-pages.css                                │
│         • MUST load BEFORE page-specific CSS                    │
│         • Contains base styles that pages override              │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│  6. PAGE-SPECIFIC CSS (page-css-loader.php)                     │
│     └── Conditionally loaded based on current route             │
│         • Loads AFTER modern-pages.css                          │
│         • Can override base styles                              │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│  7. COMPONENT BUNDLES (css-loader.php - Section 4)              │
│     ├── bundles/components-navigation.css                       │
│     ├── bundles/components-buttons.css                          │
│     ├── bundles/components-forms.css                            │
│     ├── bundles/components-cards.css                            │
│     ├── bundles/components-modals.css                           │
│     └── bundles/components-notifications.css                    │
│         • Async loaded (non-blocking)                           │
│         • UI component styles                                   │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│  8-11. UTILITIES, MOBILE, DESKTOP, SHARED (css-loader.php)      │
│     • Various utility and responsive styles                     │
│     • Mostly async loaded                                       │
│     • See css-loader.php for full list                          │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│  12. EMERGENCY OVERRIDES (css-loader.php - Section 12)          │
│     └── scroll-fix-emergency.css                                │
│         • MUST be loaded LAST                                   │
│         • Uses maximum specificity + !important                 │
│         • Fixes critical scroll issues                          │
│         • Moving this file WILL break scrolling                 │
└─────────────────────────────────────────────────────────────────┘
```

## Critical Rules

### Rule 1: design-tokens.css MUST Load First

**Location**: `views/layouts/modern/header.php` line 64

```html
<link rel="stylesheet" href="/assets/css/design-tokens.css?v=...">
```

**Why**: Every other CSS file uses variables like `var(--color-primary-500)`, `var(--space-4)`, etc. If these aren't defined first, styles will silently fail.

**What breaks if violated**: All colors become transparent/black, spacing breaks, entire layout collapses.

### Rule 2: scroll-fix-emergency.css MUST Load Last

**Location**: `views/layouts/modern/partials/css-loader.php` line 210

```php
<?= syncCss('/assets/css/scroll-fix-emergency.css', $cssVersion, $assetBase) ?>
```

**Why**: Uses `!important` with maximum specificity to override all other scroll settings. Fixes a critical bug where body `overflow: visible` breaks mouse wheel scrolling.

**What breaks if violated**: Page becomes unscrollable, mouse wheel stops working.

### Rule 3: modern-pages.css Before Page-Specific CSS

**Location**: `views/layouts/modern/partials/css-loader.php` section 2b (line 91)

**Why**: Page-specific CSS needs to override base styles. If loaded in wrong order, pages look broken.

### Rule 4: Never Use Minified design-tokens

**Current Status**: Using non-minified `design-tokens.css` (see header.php line 63-64)

**Why**: PurgeCSS incorrectly removes CSS variables thinking they're unused classes. The minified version gets corrupted.

**Validation**: Run `npm run validate:design-tokens` to check integrity.

## File Dependencies

### Files That Depend on design-tokens.css (Partial List)

Every CSS file in the project depends on design-tokens.css. Here are critical ones:

- `nexus-phoenix.css` - Core framework
- `nexus-modern-header.css` - Header styles
- `bundles/*.css` - All bundles
- `modern-*.css` - All modern theme files

### Files with Load Order Dependencies

| File | Must Load After | Must Load Before |
|------|-----------------|------------------|
| design-tokens.css | (nothing) | ALL other CSS |
| nexus-header-extracted.css | design-tokens.css | nexus-phoenix.css |
| modern-pages.css | header CSS | page-specific CSS |
| scroll-fix-emergency.css | ALL other CSS | (nothing - must be last) |

## Common Breakage Scenarios

### Scenario 1: Styles Missing After Deploy

**Symptom**: Colors look wrong, spacing broken
**Likely Cause**: design-tokens.css or design-tokens.min.css corrupted
**Fix**: Run `npm run validate:design-tokens` then `npm run minify:css`

### Scenario 2: Page Won't Scroll

**Symptom**: Mouse wheel doesn't work, can't scroll page
**Likely Cause**: scroll-fix-emergency.css not loading last, or another file added !important overflow rules after it
**Fix**: Check css-loader.php section 12, ensure no CSS loads after emergency file

### Scenario 3: Header/Navigation Broken

**Symptom**: Menu doesn't open, dropdowns misaligned
**Likely Cause**: Header CSS loaded in wrong order
**Fix**: Check sections 2 and 2b of css-loader.php

### Scenario 4: Page-Specific Styles Not Applied

**Symptom**: A specific page looks wrong, others fine
**Likely Cause**: Page CSS loading before modern-pages.css base
**Fix**: Check page-css-loader.php and css-loader.php section ordering

## Safe Modification Guidelines

### Adding a New CSS File

1. Determine the appropriate section in css-loader.php
2. Add to `purgecss.config.js` so it's included in builds
3. Run `npm run css:auto-config` to auto-detect new files
4. Test on BOTH themes (modern and civicone)

### Modifying Existing CSS

1. NEVER remove CSS variable definitions without checking all usages
2. NEVER add `!important` to `overflow` properties (except in emergency file)
3. NEVER redefine z-index scale (use design-tokens.css as source of truth)
4. Test locally before deploying

### Emergency Recovery

If the theme breaks completely:

1. Check if design-tokens.css is loading (browser Network tab)
2. Run `npm run validate:design-tokens`
3. Check css-loader.php for syntax errors
4. Verify scroll-fix-emergency.css is last
5. Clear browser cache and test in incognito

## Validation Commands

```bash
# Check if design tokens are intact
npm run validate:design-tokens

# Find CSS files not tracked in purgecss config
npm run css:discover

# Add missing CSS files to config
npm run css:auto-config

# Full CSS rebuild with validation
npm run build:css
```

## Files Reference

| File | Purpose | Location |
|------|---------|----------|
| css-loader.php | Main CSS orchestrator | views/layouts/modern/partials/ |
| page-css-loader.php | Page-specific CSS | views/layouts/modern/partials/ |
| header.php | Loads design-tokens first | views/layouts/modern/ |
| design-tokens.css | Variable definitions | httpdocs/assets/css/ |
| scroll-fix-emergency.css | Critical scroll fixes | httpdocs/assets/css/ |

---

**Last Updated**: 2026-01-26
**Maintainer**: Development Team
