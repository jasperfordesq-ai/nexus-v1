# CivicOne Header Fix Action Plan

**Date:** 2026-01-20
**Priority:** CRITICAL
**Estimated Time:** 2-3 hours
**Status:** READY TO EXECUTE

---

## Executive Summary

Based on screenshot analysis, the CivicOne header has **4 critical issues** preventing it from meeting Section 9A requirements:

1. ❌ **Service navigation not visible** (most critical)
2. ❌ **Utility bar contains navigation items** (violates Rule HL-003)
3. ❌ **Large hero section replacing primary nav** (anti-pattern)
4. ❌ **Search in wrong layer** (minor)

**Good news:** The code structure is solid (35/35 automated checks pass). The issues are primarily CSS visibility and PHP conditional logic.

---

## Quick Start: 3-Step Emergency Fix (30 minutes)

If you need the header working NOW, run these three commands:

### Step 1: Force Service Navigation Visibility

```bash
bash scripts/fix-header-visibility.sh
```

This adds `!important` rules to CSS to force service navigation to display on desktop. **Temporary fix** until we diagnose why default CSS isn't working.

### Step 2: Regenerate CSS

```bash
node scripts/minify-css.js
```

### Step 3: Hard Refresh Browser

1. Clear browser cache (Ctrl+Shift+Delete)
2. Reload homepage (Ctrl+F5)
3. Verify service navigation appears

**Expected result:** Service navigation (Feed, Members, Groups, Listings) should now be visible below utility bar.

---

## Full Fix: Correct Implementation (2-3 hours)

For production-ready code following Section 9A standards:

### Fix 1: Diagnose Service Navigation Visibility (CRITICAL)

**Time:** 30-60 minutes
**File:** `httpdocs/assets/css/civicone-header.css`

**Problem:** Service navigation exists in HTML but not visible on screen.

**Root causes to check:**

1. **CSS Display Issue:**
   ```bash
   # Check if desktop breakpoint CSS is being applied
   grep -A 5 "@media (min-width: 768px)" httpdocs/assets/css/civicone-header.css | grep -A 3 "civicone-service-navigation__list"
   ```

   Expected:
   ```css
   @media (min-width: 768px) {
       .civicone-service-navigation__list {
           display: flex;
           align-items: center;
       }
   }
   ```

   **If missing or overridden:** Another CSS rule is hiding the navigation.

2. **Z-index Stacking Issue:**
   Hero section may be overlapping service navigation.

   Fix:
   ```css
   .civicone-header {
       position: relative;
       z-index: 100; /* Ensure header is above hero */
   }

   .civicone-hero {
       position: relative;
       z-index: 1; /* Lower than header */
   }
   ```

3. **PHP Hidden Attribute:**
   Check if service navigation has `hidden` attribute in PHP:
   ```bash
   grep "hidden" views/layouts/civicone/partials/service-navigation.php
   ```

   If found, remove it.

4. **Minified CSS Stale:**
   ```bash
   # Check if .min.css is older than source
   ls -lt httpdocs/assets/css/civicone-header.* | head -3
   ```

   If `.css` is newer than `.min.css`, regenerate:
   ```bash
   node scripts/minify-css.js
   ```

**Action items:**

- [ ] Run diagnostic: Check CSS display rules
- [ ] Check z-index stacking with DevTools
- [ ] Verify no `hidden` attribute in PHP
- [ ] Regenerate minified CSS
- [ ] Test at 1920px viewport - navigation should be visible

---

### Fix 2: Clean Up Utility Bar (HIGH PRIORITY)

**Time:** 1 hour
**File:** `views/layouts/civicone/partials/utility-bar.php`

**Problem:** Utility bar contains "+ Create", "Partner Communities", "Admin", "Ranking" - violates Rule HL-003.

**Solution:**

#### 2A: Remove "+ Create" Dropdown (lines 93-114)

```php
// Comment out or remove these lines:
<!--
<div class="civic-dropdown civic-dropdown--right">
    <button class="civic-utility-link civic-utility-btn civic-utility-btn--create" ...>
        + Create <span class="civic-arrow" aria-hidden="true">▾</span>
    </button>
    <div class="civic-dropdown-content" ...>
        ... (all create menu items)
    </div>
</div>
-->
```

**Alternative:** Move "Create" to floating action button (FAB) in page content:

```php
// Create new file: views/layouts/civicone/partials/floating-action-button.php
<button class="civicone-fab" aria-label="Create new content" aria-haspopup="menu">
    <span class="dashicons dashicons-plus" aria-hidden="true"></span>
</button>
<div class="civicone-fab-menu" hidden>
    <a href="/compose?tab=post">📝 New Post</a>
    <a href="/compose?tab=listing">🎁 New Listing</a>
    ... (other create options)
</div>
```

#### 2B: Extract "Partner Communities" to Scope Switcher (lines 117-160)

This belongs in a separate partial following Section 9B.2 (MOJ Organisation Switcher pattern).

**Create new file:** `views/layouts/civicone/partials/federation-scope-switcher.php`

```php
<?php
/**
 * Federation Scope Switcher
 * Pattern: MOJ Organisation Switcher
 * https://design-patterns.service.justice.gov.uk/components/organisation-switcher/
 * See: docs/CIVICONE_WCAG21AA_SOURCE_OF_TRUTH.md Section 9B.2
 */

// Only show if user has federation access and 2+ communities
$hasFederation = false;
if (class_exists('\Nexus\Services\FederationFeatureService')) {
    try {
        $hasFederation = \Nexus\Services\FederationFeatureService::isTenantFederationEnabled();
    } catch (\Exception $e) {
        $hasFederation = false;
    }
}

if (!$hasFederation) {
    return; // Don't render scope switcher
}

// Get partner communities (placeholder - implement actual logic)
$partnerCommunities = []; // Fetch from FederationService
$currentScope = $_GET['scope'] ?? 'all';

// Rule FS-002: Only show if user has access to 2+ communities
if (count($partnerCommunities) < 2) {
    return; // Don't show switcher for single community
}
?>

<!-- Federation Scope Switcher (MOJ Pattern) -->
<div class="civicone-width-container">
    <div class="moj-organisation-switcher" aria-label="Federation scope">
        <p class="moj-organisation-switcher__heading">Partner Communities:</p>
        <nav class="moj-organisation-switcher__nav" aria-label="Switch partner community">
            <ul class="moj-organisation-switcher__list">
                <li class="moj-organisation-switcher__item <?= $currentScope === 'all' ? 'moj-organisation-switcher__item--active' : '' ?>">
                    <a href="/federation?scope=all" <?= $currentScope === 'all' ? 'aria-current="page"' : '' ?>>
                        All shared communities
                    </a>
                </li>
                <?php foreach ($partnerCommunities as $community): ?>
                    <li class="moj-organisation-switcher__item <?= $currentScope == $community['id'] ? 'moj-organisation-switcher__item--active' : '' ?>">
                        <a href="/federation?scope=<?= $community['id'] ?>" <?= $currentScope == $community['id'] ? 'aria-current="page"' : '' ?>>
                            <?= htmlspecialchars($community['name']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <p class="moj-organisation-switcher__change">
            <a href="/settings?section=federation">Change partner preferences</a>
        </p>
    </div>
</div>
```

**Update `site-header.php`** to include scope switcher BELOW service navigation:

```php
<!-- Layer 4: Primary Navigation (Service Navigation Pattern) -->
<header class="civicone-header" role="banner">
    <div class="civicone-width-container">
        <?php require __DIR__ . '/service-navigation.php'; ?>
    </div>
</header>

<!-- NEW: Federation Scope Switcher (if on /federation/* pages) -->
<?php if (strpos($_SERVER['REQUEST_URI'] ?? '', '/federation') === 0): ?>
    <?php require __DIR__ . '/federation-scope-switcher.php'; ?>
<?php endif; ?>

<!-- Layer 5: Search (below service nav, within width container) -->
...
```

#### 2C: Move "Admin" and "Ranking" to User Dropdown (lines 176-182)

Remove from top-level utility bar:

```php
// REMOVE these lines (176-182):
<!--
<a href="/admin" class="civic-utility-link civic-utility-btn civic-utility-btn--admin">Admin</a>
<a href="/admin/group-ranking" class="civic-utility-link civic-utility-btn civic-utility-btn--ranking" ...>
    <i class="fa-solid fa-chart-line"></i> Ranking
</a>
-->
```

Add to user avatar dropdown (lines 229-243):

```php
<div class="civic-dropdown-content" role="menu">
    <a href="/profile/<?= $_SESSION['user_id'] ?>" role="menuitem">
        <i class="fa-solid fa-user civic-menu-icon civic-menu-icon--brand"></i>My Profile
    </a>
    <a href="/dashboard" role="menuitem">
        <i class="fa-solid fa-gauge civic-menu-icon civic-menu-icon--purple"></i>Dashboard
    </a>
    <a href="/wallet" role="menuitem">
        <i class="fa-solid fa-wallet civic-menu-icon civic-menu-icon--green"></i>Wallet
    </a>

    <!-- NEW: Add Admin and Ranking here -->
    <?php if ((!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') || !empty($_SESSION['is_super_admin'])): ?>
        <div class="civic-dropdown-separator" role="separator"></div>
        <a href="/admin" role="menuitem">
            <i class="fa-solid fa-user-shield civic-menu-icon civic-menu-icon--brand"></i>Admin Panel
        </a>
        <a href="/admin/group-ranking" role="menuitem">
            <i class="fa-solid fa-chart-line civic-menu-icon civic-menu-icon--brand"></i>Ranking
        </a>
    <?php endif; ?>

    <div class="civic-dropdown-separator" role="separator"></div>
    <a href="/logout" role="menuitem" class="civic-utility-link--logout">
        <i class="fa-solid fa-right-from-bracket civic-menu-icon"></i>Sign Out
    </a>
</div>
```

**Action items:**

- [ ] Comment out "+ Create" dropdown (lines 93-114)
- [ ] Create `federation-scope-switcher.php` partial
- [ ] Remove "Partner Communities" from utility bar (lines 117-160)
- [ ] Update `site-header.php` to include scope switcher on `/federation/*` pages
- [ ] Move "Admin" and "Ranking" to user dropdown
- [ ] Test utility bar - should only have: Platform, Contrast, Layout, Messages, Notifications, User Avatar

---

### Fix 3: Remove/Minimize Hero Section (HIGH PRIORITY)

**Time:** 30 minutes
**File:** `views/layouts/civicone/partials/hero.php`

**Problem:** Large purple "Featured Content" section is replacing service navigation visually.

**Solutions (choose one):**

#### Option A: Make Hero Conditional (Per-Page)

Update `header.php`:

```php
// Replace:
require __DIR__ . '/partials/hero.php';

// With:
if ($showHero ?? false) {
    require __DIR__ . '/partials/hero.php';
}
```

In individual view files (e.g., `views/civicone/home.php`):

```php
<?php
$showHero = true; // Enable hero for homepage only
require __DIR__ . '/../layouts/civicone/header.php';
?>
```

#### Option B: Remove Hero from Global Header

Comment out in `header.php`:

```php
// Hero banner
// require __DIR__ . '/partials/hero.php'; // REMOVED - use per-page instead
```

Include hero in individual pages as needed.

#### Option C: Reduce Hero Height

Update `hero.php` CSS:

```css
.civicone-hero {
    max-height: 60px; /* Reduce from 400px+ to single-line banner */
    padding: var(--space-3) 0;
}
```

Remove "Partner Communities" content from hero (this belongs in scope switcher).

**Action items:**

- [ ] Choose option A, B, or C above
- [ ] Implement chosen fix
- [ ] Verify hero doesn't overlap service navigation
- [ ] Test page without hero - content should start immediately below search

---

### Fix 4: Verify Search Placement (MEDIUM PRIORITY)

**Time:** 15 minutes
**File:** `views/layouts/civicone/partials/site-header.php`

**Problem:** Search may be duplicated in utility bar.

**Check:**

```bash
grep -n "search" views/layouts/civicone/partials/utility-bar.php
```

If search is found in utility bar, remove it. Search should ONLY be in `site-header.php` (lines 19-66).

**Action items:**

- [ ] Check if search is in utility bar
- [ ] If found, remove from utility bar
- [ ] Verify search is visible below service navigation (Layer 5)

---

## Testing Checklist After Fixes

Run these tests after implementing fixes:

### Visual Verification (Desktop 1920px)

- [ ] Service navigation visible: `[Logo: Project NEXUS]  Feed  Members  Groups  Listings`
- [ ] Active page highlighted (different background/underline)
- [ ] Utility bar clean: `Platform | Contrast | Layout | Messages | Notifications | User`
- [ ] NO "+ Create" dropdown in utility bar
- [ ] NO "Partner Communities" dropdown in utility bar
- [ ] NO "Admin" or "Ranking" links in utility bar (moved to user dropdown)
- [ ] Search bar visible below service navigation
- [ ] Hero section removed OR very small (60px max)
- [ ] Page content constrained to ~1020px width

### Visual Verification (Mobile 375px)

- [ ] Hamburger menu button visible
- [ ] Desktop service navigation hidden
- [ ] Clicking hamburger opens mobile panel with navigation items
- [ ] Mobile panel can be closed with Escape or clicking outside
- [ ] No horizontal scroll

### Automated Tests

```bash
# Run verification script
bash scripts/verify-header-refactor.sh

# Should pass: 35/35 checks
```

### Keyboard Navigation

1. Press Tab → Skip link appears (yellow background)
2. Press Enter → Jumps to main content
3. Tab through header → Order is: utility bar → logo → nav items → search
4. On mobile: Tab to hamburger → Enter → Panel opens → Arrow Down navigates links → Escape closes

### Accessibility Audit

```bash
# Install axe DevTools extension in Chrome
# Or run CLI:
npx @axe-core/cli http://localhost/ --include="header"
```

Target: 0 violations

---

## Files to Modify - Quick Reference

| File | Changes | Priority |
|------|---------|----------|
| `httpdocs/assets/css/civicone-header.css` | Add !important rules to force visibility (temporary) | P1 |
| `views/layouts/civicone/partials/utility-bar.php` | Remove "+ Create" (lines 93-114) | P2 |
| `views/layouts/civicone/partials/utility-bar.php` | Remove "Partner Communities" (lines 117-160) | P2 |
| `views/layouts/civicone/partials/utility-bar.php` | Remove "Admin"/"Ranking" (lines 176-182) | P2 |
| `views/layouts/civicone/partials/utility-bar.php` | Add "Admin"/"Ranking" to user dropdown (lines 229-243) | P2 |
| **NEW:** `views/layouts/civicone/partials/federation-scope-switcher.php` | Create new partial (Section 9B.2) | P2 |
| `views/layouts/civicone/partials/site-header.php` | Include federation scope switcher (after line 17) | P2 |
| `views/layouts/civicone/header.php` | Make hero conditional or remove | P3 |
| `views/layouts/civicone/partials/hero.php` | Reduce height or remove content | P3 |

---

## Expected Final Result

After all fixes, header should look like this:

```
┌────────────────────────────────────────────────────────────────┐
│ Phase Banner (green):                                          │
│ "ACCESSIBLE Experimental in Development | WCAG 2.1 AA..."     │
└────────────────────────────────────────────────────────────────┘
┌────────────────────────────────────────────────────────────────┐
│ Utility Bar (light grey, spans full width):                    │
│ Platform ▾ | Contrast | Layout ▾ | [✉️ 2] | [🔔 5] | [Avatar ▾]│
└────────────────────────────────────────────────────────────────┘
┌────────────────────────────────────────────────────────────────┐
│ Service Navigation (white, inside 1020px container):           │
│ [Logo: Project NEXUS]  Feed  Members  Groups  Listings        │
│                        Volunteering  Events                    │
│                       (Active page has blue underline)         │
└────────────────────────────────────────────────────────────────┘
┌────────────────────────────────────────────────────────────────┐
│ Search (inside 1020px container):                              │
│ [Search input...............................] [🔍 Submit]       │
└────────────────────────────────────────────────────────────────┘
┌────────────────────────────────────────────────────────────────┐
│ Main Content (starts here, inside 1020px container)           │
│ ...                                                            │
```

**Key success indicators:**
1. ✅ Service navigation VISIBLE with logo + 6-7 nav items
2. ✅ Utility bar has ≤7 items (no Create, no Partner Communities, no Admin/Ranking)
3. ✅ NO large purple section
4. ✅ Page content starts immediately below search (no hero gap)
5. ✅ Everything inside 1020px container (except utility bar background)

---

## Rollback Plan

If fixes break something:

```bash
# Restore CSS backup
cp httpdocs/assets/css/civicone-header.css.backup httpdocs/assets/css/civicone-header.css

# Regenerate minified CSS
node scripts/minify-css.js

# Revert utility-bar.php changes
git checkout views/layouts/civicone/partials/utility-bar.php

# Hard refresh browser
# Ctrl+F5
```

---

## Next Steps After Fixes

1. ✅ Implement fixes per this action plan
2. ✅ Run `bash scripts/verify-header-refactor.sh` (should pass 35/35)
3. ✅ Take screenshots (desktop + mobile)
4. ✅ Run `docs/HEADER_VISUAL_TESTING_GUIDE.md` tests
5. ✅ Update `docs/HEADER_REFACTOR_DIAGNOSTIC_2026-01-20.md` with results
6. ✅ Create GitHub issue for any remaining minor issues
7. ✅ Deploy to staging for final testing

---

## Support Documents

- **Diagnostic Report:** `docs/HEADER_REFACTOR_DIAGNOSTIC_2026-01-20.md`
- **Issues Found:** `docs/HEADER_ISSUES_FOUND_2026-01-20.md`
- **Visual Testing:** `docs/HEADER_VISUAL_TESTING_GUIDE.md`
- **Verification Script:** `scripts/verify-header-refactor.sh`
- **Visibility Fix Script:** `scripts/fix-header-visibility.sh`
- **Source of Truth:** `docs/CIVICONE_WCAG21AA_SOURCE_OF_TRUTH.md` (Section 9A, 9B)
