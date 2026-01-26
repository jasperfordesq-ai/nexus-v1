# Visual Flash (FOUC) Fix Plan

## Executive Summary

**Problem**: Visual flashes during page loads on Modern theme
**Root Causes Identified**: 3 critical architectural issues
**Risk Level**: LOW (fixes are targeted, not wholesale refactors)
**Impact**: Eliminates FOUC on Modern and CivicOne themes

---

## Issues Identified (Deep Site Audit)

### Critical Issues

| # | Issue | Theme | Impact | Fix Complexity |
|---|-------|-------|--------|----------------|
| 1 | **CSS loaded AFTER `<body>` tag** | CivicOne | HIGH - Main FOUC cause | LOW |
| 2 | **Mixed minified/non-minified CSS** | Modern | MEDIUM - Inconsistent | LOW |
| 3 | **design-tokens.css not first** | Modern | MEDIUM - Variable flashing | LOW |

### Secondary Issues

| # | Issue | Theme | Impact | Fix Complexity |
|---|-------|-------|--------|----------------|
| 4 | Async CSS loading causing flash | Both | LOW | LOW |
| 5 | 50 JS files with direct style manipulation | Both | LOW | HIGH (skip) |

---

## Fix Plan (Prioritized)

### Phase 1: CivicOne FOUC Fix (CRITICAL)

**File**: `views/layouts/civicone/partials/body-open.php`

**Problem**: 17 CSS files loaded after `<body>` tag (lines 71-105):
- civicone-footer.css
- civicone-events.css
- civicone-profile.css
- civicone-groups.css
- civicone-groups-utilities.css
- civicone-utilities.css
- civicone-volunteering.css
- civicone-mini-modules.css
- civicone-messages.css
- civicone-wallet.css
- civicone-blog.css
- civicone-help.css
- civicone-matches.css
- civicone-federation.css
- civicone-federation-shell.css
- civicone-members-directory.css
- civicone-listings-directory.css
- civicone-feed.css

**Solution**: Move ALL CSS `<link>` tags from body-open.php to assets-css.php (in `<head>`)

**Why this is safe**:
- CSS loading order is preserved
- No changes to selectors or rules
- Simply relocating where the CSS is loaded from

**Expected Result**: Eliminates CivicOne visual flashes immediately

---

### Phase 2: Modern Theme CSS Order Fix

**File**: `views/layouts/modern/partials/css-loader.php`

**Problem**:
- Line 52: Comment says "Using non-minified CSS for stability debugging"
- design-tokens.css loads at position 2 (after nexus-header-extracted.css)

**Solution**:
1. Move design-tokens.css to position 1 in css-loader.php
2. Switch to minified CSS now that builds are stable (305/305 pass)

**Current Order**:
```
1. nexus-header-extracted.css (in header.php)
2. design-tokens.css
3. nexus-phoenix.css
```

**Correct Order**:
```
1. design-tokens.css (variables must load first)
2. nexus-header-extracted.css
3. nexus-phoenix.css
```

**Why this is safe**:
- Only changes load order, not CSS content
- design-tokens.css has no dependencies
- Minified CSS is already tested (build passed)

---

### Phase 3: Enable Minified CSS (Modern Theme)

**Files to update**:
- `views/layouts/modern/header.php` (line 88-89)
- `views/layouts/modern/partials/css-loader.php` (line 52)

**Current** (non-minified for debugging):
```php
<link rel="stylesheet" href="/assets/css/design-tokens.css?v=...">
```

**Change to** (minified for production):
```php
<link rel="stylesheet" href="/assets/css/design-tokens.min.css?v=...">
```

**CSS files to switch to minified**:
- design-tokens.css → design-tokens.min.css
- nexus-phoenix.css → nexus-phoenix.min.css
- All bundles (already using non-.min versions)

**Why this is safe**:
- Minified files are auto-generated from source
- 305/305 CSS files pass PurgeCSS validation
- Build tested: 70.6% size reduction achieved

---

## Implementation Steps

### Step 1: Backup Current State
```bash
cp views/layouts/civicone/partials/body-open.php views/layouts/civicone/partials/body-open.php.backup
cp views/layouts/civicone/partials/assets-css.php views/layouts/civicone/partials/assets-css.php.backup
cp views/layouts/modern/partials/css-loader.php views/layouts/modern/partials/css-loader.php.backup
```

### Step 2: Fix CivicOne FOUC (Phase 1)
1. Cut CSS links from body-open.php (lines 70-106)
2. Paste them at end of assets-css.php (before `</head>`)
3. Test CivicOne theme - no visual flash

### Step 3: Fix Modern CSS Order (Phase 2)
1. In css-loader.php, move design-tokens.css to line 1
2. In header.php, remove preload for design-tokens (now sync loads first)
3. Test Modern theme - variables render immediately

### Step 4: Enable Minified CSS (Phase 3)
1. Update css-loader.php to use .min.css files
2. Update header.php preloads to use .min.css
3. Test both themes - faster load, no flash

---

## What NOT to Do

Based on previous issues (design token rollout), we are **NOT**:

1. ❌ Refactoring CSS architecture (23,319 risk score)
2. ❌ Changing selectors or specificity
3. ❌ Consolidating or bundling CSS differently
4. ❌ Fixing the 50 JS files with style manipulation
5. ❌ Changing !important usage
6. ❌ Modifying cascade inheritance

These fixes are **relocation only** - moving existing working code to correct positions.

---

## Testing Checklist

After each phase:

- [ ] CivicOne home page loads without flash
- [ ] CivicOne profile page loads without flash
- [ ] CivicOne groups page loads without flash
- [ ] Modern home page loads without flash
- [ ] Modern profile page loads without flash
- [ ] No console errors
- [ ] CSS variables work (colors render correctly)
- [ ] Mobile navigation works
- [ ] Forms display correctly

---

## Rollback Plan

If issues occur:
```bash
cp views/layouts/civicone/partials/body-open.php.backup views/layouts/civicone/partials/body-open.php
cp views/layouts/civicone/partials/assets-css.php.backup views/layouts/civicone/partials/assets-css.php
cp views/layouts/modern/partials/css-loader.php.backup views/layouts/modern/partials/css-loader.php
```

---

## Timeline

| Phase | Task | Status |
|-------|------|--------|
| 1 | Move CivicOne CSS to head | **COMPLETED** (2026-01-25) |
| 2 | Fix Modern CSS load order | **COMPLETED** (2026-01-25) |
| 3 | Enable minified CSS | **COMPLETED** (2026-01-25) |
| - | Testing & Verification | Ready for testing |

---

## Appendix: File Locations

### CivicOne Theme
- Head CSS: `views/layouts/civicone/partials/assets-css.php`
- Body open: `views/layouts/civicone/partials/body-open.php`
- Header: `views/layouts/civicone/header.php`

### Modern Theme
- Header: `views/layouts/modern/header.php`
- CSS Loader: `views/layouts/modern/partials/css-loader.php`
- Page CSS: `views/layouts/modern/partials/page-css-loader.php`

### CSS Files
- Design tokens: `/httpdocs/assets/css/design-tokens.css`
- Core bundle: `/httpdocs/assets/css/bundles/core.css`
- Phoenix: `/httpdocs/assets/css/nexus-phoenix.css`
