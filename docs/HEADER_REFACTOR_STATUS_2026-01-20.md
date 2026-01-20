# CivicOne Header Refactor Status Report

**Date:** 2026-01-20
**Pattern Applied:** GOV.UK Service Navigation + Global Header & Navigation Contract
**Refactor Status:** **PHASE 1 COMPLETE** (Markup consolidation + JavaScript updated)
**Next Step:** CSS refactoring required

---

## Summary

The CivicOne header has been refactored to follow the **Global Header & Navigation Contract** (Section 9A of `CIVICONE_WCAG21AA_SOURCE_OF_TRUTH.md`). This refactor consolidates multiple competing navigation systems into a single GOV.UK Service Navigation pattern.

---

## What Was Completed (Phase 1)

### 1. Created Service Navigation Partial ✅

**File:** `views/layouts/civicone/partials/service-navigation.php`

**What it does:**
- Implements GOV.UK Service Navigation component pattern
- ONE primary navigation system (no mega menu duplication)
- Top-level sections only: Feed, Members, Groups, Listings, Volunteering, Events
- Supports database-driven pages from Page Builder
- Active state detection with `aria-current="page"`
- Mobile responsive with toggle button
- Keyboard operable (Tab, Enter, Escape, Arrow keys)

**Key features:**
- Clean separation of desktop vs mobile navigation (same items, different presentation)
- Proper ARIA attributes (`aria-label`, `aria-controls`, `aria-expanded`)
- Semantic HTML (`<nav>`, `<ul>`, `<li>`)
- Backward compatibility hook for mobile drawer

### 2. Refactored Site Header Structure ✅

**File:** `views/layouts/civicone/partials/site-header.php`

**What changed:**
- **REMOVED:** Old navigation structure with desktop nav + mega menu + separate mobile toggles
- **ADDED:** Clean 4-layer structure per contract:
  1. Skip link (handled by skip-link-and-banner.php)
  2. Phase banner (not yet implemented - optional)
  3. Utility bar (handled by utility-bar.php)
  4. **PRIMARY NAVIGATION** (new service-navigation.php)
  5. Search (integrated below service nav)

**Improvements:**
- Width container enforcement (`civicone-width-container` with max-width: 1020px)
- Proper landmark structure (`<header role="banner">`)
- Search properly positioned (not competing header block)
- Mobile search toggle integrated
- Backward compatibility for mobile drawer (`#civic-menu-toggle` preserved)

### 3. Updated Header JavaScript ✅

**File:** `views/layouts/civicone/partials/header-scripts.php`

**What changed:**
- **REMOVED:** Mega menu toggle logic (83 lines)
- **ADDED:** Service navigation mobile panel toggle logic
- **UPDATED:** Mobile search toggle to use new IDs (`civicone-mobile-search-toggle`, `civicone-mobile-search-bar`)
- **ADDED:** Backward compatibility mapping for old mega menu button

**Key behaviors preserved:**
- Escape key closes nav panel and returns focus to toggle
- Click outside closes panel
- Arrow key navigation within panel
- Focus management (moves to first link when opening)
- Tab + Shift+Tab edge case handling

**Accessibility improvements:**
- Proper `hidden` attribute usage (not just display:none)
- CSS transition timing considered (150ms delay before adding `hidden`)
- Screen reader announcements preserved

---

## What Still Needs to Be Done (Phase 2)

### 1. **CSS Refactoring Required** 🔴 CRITICAL

**File to edit:** `httpdocs/assets/css/civicone-header.css`

**Current state:**
- CSS still references old classes (`.civic-header`, `.civic-desktop-nav`, `.civic-mega-menu`)
- New classes not yet styled (`.civicone-header`, `.civicone-service-navigation`, etc.)

**Required changes:**

#### A. Add Service Navigation Styles

```css
/* ===========================================
   SERVICE NAVIGATION (GOV.UK Pattern)
   =========================================== */

.civicone-header {
    background-color: var(--civic-bg-primary);
    border-bottom: 4px solid var(--civic-brand);
    position: relative;
    z-index: 100;
}

.civicone-width-container {
    max-width: 1020px; /* GOV.UK standard */
    margin: 0 auto;
    padding: 0 var(--space-6);
}

.civicone-service-navigation__container {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-4);
    min-height: 80px;
}

/* Logo / Service Name */
.civicone-service-navigation__branding {
    flex-shrink: 0;
}

.civicone-service-navigation__logo {
    text-decoration: none;
    color: var(--civic-text-primary);
    font-weight: 700;
    font-size: 1.5rem;
    display: inline-block;
}

.civicone-service-navigation__logo:focus-visible {
    outline: 3px solid var(--govuk-focus-colour, #ffdd00);
    outline-offset: 3px;
    background-color: var(--govuk-focus-colour, #ffdd00);
    color: var(--govuk-focus-text-colour, #0b0c0c);
}

.civicone-service-navigation__service-name {
    display: inline-block;
}

/* Desktop Navigation List */
.civicone-service-navigation__list {
    display: none; /* Hidden on mobile */
    list-style: none;
    margin: 0;
    padding: 0;
    gap: var(--space-3);
}

@media (min-width: 768px) {
    .civicone-service-navigation__list {
        display: flex;
        align-items: center;
    }
}

.civicone-service-navigation__item {
    margin: 0;
    padding: 0;
}

.civicone-service-navigation__link {
    display: inline-block;
    padding: var(--space-3) var(--space-4);
    color: var(--civic-text-primary);
    text-decoration: none;
    font-weight: 600;
    font-size: 1rem;
    border-bottom: 4px solid transparent;
    transition: border-color var(--duration-fast) var(--ease-default);
}

.civicone-service-navigation__link:hover {
    text-decoration: underline;
    border-bottom-color: var(--civic-brand-light);
}

.civicone-service-navigation__link:focus-visible {
    outline: 3px solid var(--govuk-focus-colour, #ffdd00);
    outline-offset: 0;
    background-color: var(--govuk-focus-colour, #ffdd00);
    color: var(--govuk-focus-text-colour, #0b0c0c);
    box-shadow: 0 -2px var(--govuk-focus-colour, #ffdd00), 0 4px var(--govuk-focus-text-colour, #0b0c0c);
    text-decoration: none;
}

/* Active state */
.civicone-service-navigation__item--active .civicone-service-navigation__link {
    border-bottom-color: var(--civic-brand);
    font-weight: 700;
}

/* Mobile Toggle Button */
.civicone-service-navigation__toggle {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    padding: var(--space-3) var(--space-4);
    background: none;
    border: 2px solid var(--civic-text-primary);
    border-radius: var(--radius-sm);
    font-size: 1rem;
    font-weight: 600;
    color: var(--civic-text-primary);
    cursor: pointer;
    min-height: 44px;
    min-width: 44px;
}

@media (min-width: 768px) {
    .civicone-service-navigation__toggle {
        display: none; /* Hide on desktop */
    }
}

.civicone-service-navigation__toggle:hover {
    background-color: var(--civic-bg-tertiary);
}

.civicone-service-navigation__toggle:focus-visible {
    outline: 3px solid var(--govuk-focus-colour, #ffdd00);
    outline-offset: 0;
    background-color: var(--govuk-focus-colour, #ffdd00);
    color: var(--govuk-focus-text-colour, #0b0c0c);
    border-color: var(--govuk-focus-text-colour, #0b0c0c);
}

.civicone-service-navigation__toggle-icon {
    display: flex;
    flex-direction: column;
    gap: 4px;
    width: 20px;
    height: 16px;
}

.civicone-service-navigation__toggle-icon span {
    display: block;
    width: 100%;
    height: 3px;
    background-color: currentColor;
    border-radius: 2px;
    transition: transform var(--duration-fast) var(--ease-default);
}

.civicone-service-navigation__toggle.active .civicone-service-navigation__toggle-icon span:nth-child(1) {
    transform: translateY(7px) rotate(45deg);
}

.civicone-service-navigation__toggle.active .civicone-service-navigation__toggle-icon span:nth-child(2) {
    opacity: 0;
}

.civicone-service-navigation__toggle.active .civicone-service-navigation__toggle-icon span:nth-child(3) {
    transform: translateY(-7px) rotate(-45deg);
}

/* Mobile Navigation Panel */
.civicone-service-navigation__mobile-panel {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: var(--civic-bg-primary);
    border-bottom: 4px solid var(--civic-brand);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    max-height: 0;
    overflow: hidden;
    opacity: 0;
    transition: max-height var(--duration-fast) var(--ease-default), opacity var(--duration-fast) var(--ease-default);
}

.civicone-service-navigation__mobile-panel.active {
    max-height: 500px;
    opacity: 1;
}

.civicone-service-navigation__mobile-list {
    list-style: none;
    margin: 0;
    padding: var(--space-4) 0;
}

.civicone-service-navigation__mobile-item {
    margin: 0;
    padding: 0;
    border-top: 1px solid var(--civic-bg-tertiary);
}

.civicone-service-navigation__mobile-link {
    display: block;
    padding: var(--space-4) var(--space-6);
    color: var(--civic-text-primary);
    text-decoration: none;
    font-weight: 600;
    font-size: 1.125rem;
}

.civicone-service-navigation__mobile-link:hover {
    background-color: var(--civic-bg-tertiary);
    text-decoration: underline;
}

.civicone-service-navigation__mobile-link:focus-visible {
    outline: 3px solid var(--govuk-focus-colour, #ffdd00);
    outline-offset: -3px;
    background-color: var(--govuk-focus-colour, #ffdd00);
    color: var(--govuk-focus-text-colour, #0b0c0c);
}

.civicone-service-navigation__mobile-item--active .civicone-service-navigation__mobile-link {
    font-weight: 700;
    border-left: 4px solid var(--civic-brand);
    padding-left: calc(var(--space-6) - 4px);
}
```

#### B. Add Search Wrapper Styles

```css
/* ===========================================
   SEARCH AREA
   =========================================== */

.civicone-search-wrapper {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: var(--space-4);
    padding: var(--space-4) 0;
    border-bottom: 1px solid var(--civic-bg-tertiary);
}

.civicone-desktop-search {
    display: none;
}

@media (min-width: 768px) {
    .civicone-desktop-search {
        display: block;
    }
}

.civicone-search-form {
    display: flex;
    align-items: center;
    gap: var(--space-2);
}

.civicone-search-input {
    padding: var(--space-2) var(--space-3);
    border: 2px solid var(--civic-text-primary);
    border-radius: var(--radius-sm);
    font-size: 1rem;
    min-height: 44px;
    min-width: 200px;
}

.civicone-search-input:focus-visible {
    outline: 3px solid var(--govuk-focus-colour, #ffdd00);
    outline-offset: 0;
    box-shadow: inset 0 0 0 2px;
}

.civicone-search-button {
    padding: var(--space-2) var(--space-4);
    background: var(--civic-brand);
    color: white;
    border: 2px solid var(--civic-brand);
    border-radius: var(--radius-sm);
    font-weight: 600;
    cursor: pointer;
    min-height: 44px;
    min-width: 44px;
}

.civicone-search-button:hover {
    background: var(--civic-brand-dark);
    border-color: var(--civic-brand-dark);
}

.civicone-search-button:focus-visible {
    outline: 3px solid var(--govuk-focus-colour, #ffdd00);
    outline-offset: 0;
    background-color: var(--govuk-focus-colour, #ffdd00);
    color: var(--govuk-focus-text-colour, #0b0c0c);
    border-color: var(--govuk-focus-text-colour, #0b0c0c);
}

.civicone-mobile-search-toggle {
    display: flex;
    align-items: center;
    justify-content: center;
    background: none;
    border: 2px solid var(--civic-text-primary);
    border-radius: var(--radius-sm);
    padding: var(--space-2);
    cursor: pointer;
    min-height: 44px;
    min-width: 44px;
}

@media (min-width: 768px) {
    .civicone-mobile-search-toggle {
        display: none;
    }
}

.civicone-mobile-search-toggle:hover {
    background-color: var(--civic-bg-tertiary);
}

.civicone-mobile-search-toggle:focus-visible {
    outline: 3px solid var(--govuk-focus-colour, #ffdd00);
    outline-offset: 0;
    background-color: var(--govuk-focus-colour, #ffdd00);
    color: var(--govuk-focus-text-colour, #0b0c0c);
}

.civicone-mobile-search-bar {
    max-height: 0;
    overflow: hidden;
    opacity: 0;
    transition: max-height var(--duration-fast) var(--ease-default), opacity var(--duration-fast) var(--ease-default);
}

.civicone-mobile-search-bar.active {
    max-height: 100px;
    opacity: 1;
    padding: var(--space-4);
}

.civicone-mobile-search-form {
    display: flex;
    gap: var(--space-2);
}

.civicone-mobile-search-input {
    flex: 1;
    padding: var(--space-2) var(--space-3);
    border: 2px solid var(--civic-text-primary);
    border-radius: var(--radius-sm);
    font-size: 1rem;
    min-height: 44px;
}

.civicone-mobile-search-button {
    padding: var(--space-2) var(--space-4);
    background: var(--civic-brand);
    color: white;
    border: 2px solid var(--civic-brand);
    border-radius: var(--radius-sm);
    cursor: pointer;
    min-height: 44px;
    min-width: 44px;
}

.civicone-visually-hidden {
    position: absolute !important;
    width: 1px !important;
    height: 1px !important;
    margin: -1px !important;
    padding: 0 !important;
    overflow: hidden !important;
    clip: rect(0, 0, 0, 0) !important;
    white-space: nowrap !important;
    border: 0 !important;
}
```

#### C. Remove/Comment Out Old Styles

**DO NOT DELETE** - Comment out for rollback safety:

```css
/* DEPRECATED - OLD HEADER STRUCTURE (Commented out 2026-01-20)
.civic-header { ... }
.civic-header-wrapper { ... }
.civic-desktop-nav { ... }
.civic-mega-menu { ... }
.civic-mega-grid { ... }
.civic-menu-btn { ... }
*/
```

### 2. **Regenerate CSS Build Outputs** 🔴 CRITICAL

After CSS refactoring, run these commands:

```bash
# 1. Minify civicone-header.css
npx csso httpdocs/assets/css/civicone-header.css -o httpdocs/assets/css/civicone-header.min.css

# 2. Regenerate purged CSS
npx purgecss --config purgecss.config.js

# 3. Verify file sizes
ls -lh httpdocs/assets/css/civicone-header.*
ls -lh httpdocs/assets/css/purged/civicone-header.*
```

### 3. **Testing Checklist** 🔴 CRITICAL

**MUST pass ALL checks before considering refactor complete:**

#### Structure Tests
- [ ] Width container enforced (max-width: 1020px) on all viewports
- [ ] No content outside width container (except full-width backgrounds)
- [ ] ONE primary navigation system only (no mega menu visible)
- [ ] Service navigation visible on desktop
- [ ] Service navigation toggle visible on mobile (< 768px)

#### Keyboard Navigation
- [ ] Tab order is logical: skip link → utility bar → service nav → search → main
- [ ] All nav items keyboard accessible (Tab, Enter)
- [ ] Mobile toggle opens/closes panel with Enter/Space
- [ ] Escape closes open panel and returns focus to toggle
- [ ] Arrow keys navigate within panel
- [ ] No focus traps

#### Focus Visibility
- [ ] GOV.UK yellow (#ffdd00) focus visible on all interactive elements
- [ ] Focus ring has 3:1+ contrast against adjacent colors
- [ ] No elements with `outline: none` without alternative focus styles

#### Mobile Responsive (375px viewport)
- [ ] Header stacks cleanly (no horizontal scroll)
- [ ] Mobile toggle button visible and operable
- [ ] Mobile panel opens/closes correctly
- [ ] Touch targets minimum 44x44px

#### Zoom Tests
- [ ] Usable at 200% zoom (no horizontal scroll)
- [ ] Reflows to single column at 400% zoom
- [ ] Text doesn't overlap or clip

#### Visual Regression
- [ ] Screenshot homepage at 1920px, 768px, 375px
- [ ] Compare before/after screenshots
- [ ] Document intentional visual changes

#### Accessibility Audits
- [ ] Axe DevTools passes (no new errors)
- [ ] Lighthouse accessibility score ≥95
- [ ] Screen reader test (NVDA/VoiceOver)

#### Functional Tests
- [ ] Navigation links work
- [ ] Active page marked correctly (`aria-current="page"`)
- [ ] Search form functional
- [ ] Mobile search toggle functional
- [ ] Utility bar dropdowns work
- [ ] Layout switcher works (switch to Modern and back)
- [ ] Pusher notifications appear
- [ ] AI chat widget appears
- [ ] No JavaScript console errors

---

## Files Modified

### Created:
1. `views/layouts/civicone/partials/service-navigation.php` (NEW - 122 lines)
2. `docs/HEADER_REFACTOR_STATUS_2026-01-20.md` (NEW - this file)

### Modified:
1. `views/layouts/civicone/partials/site-header.php` (148 lines → 78 lines, -70 lines)
2. `views/layouts/civicone/partials/header-scripts.php` (Updated mega menu logic → service nav logic)

### Requires Update (Next Phase):
1. `httpdocs/assets/css/civicone-header.css` (Add service nav styles, comment out old styles)
2. `httpdocs/assets/css/civicone-header.min.css` (Regenerate after CSS changes)
3. `httpdocs/assets/css/purged/civicone-header.min.css` (Regenerate after CSS changes)

### Preserved (Unchanged):
1. `views/layouts/civicone/header.php` (Orchestrates partials - works with new structure)
2. `views/layouts/civicone/header-cached.php` (Uses header.php - inherits new structure)
3. `views/layouts/civicone/partials/utility-bar.php` (No changes - works with new structure)
4. `views/layouts/civicone/partials/skip-link-and-banner.php` (No changes - layer 1 preserved)
5. `views/layouts/civicone/partials/mobile-nav-v2.php` (No changes - backward compatibility preserved)

---

## Backward Compatibility

### Preserved Hooks:
- `#civic-menu-toggle` - Hidden button in site-header.php maps to mobile drawer
- `openMobileMenu()` / `closeMobileMenu()` - Mobile drawer functions still work
- `NEXUS_BASE` - JavaScript global still available
- `window.NEXUS` - Global namespace preserved
- Notification drawer IDs (#notif-drawer, #notif-drawer-overlay)
- AI chat widget integration

### Deprecated (But Safe to Remove After CSS Testing):
- `#civic-mega-menu-btn` - Old mega menu toggle (mapped to service nav toggle in JS)
- `#civic-mega-menu` - Old mega menu panel (removed from markup)
- `.civic-nav-link[data-nav-match]` - Active state detection (new logic in service-navigation.php)

---

## Rollback Plan

If issues found after deployment:

### Immediate Rollback (Markup only):
```bash
git checkout HEAD~1 views/layouts/civicone/partials/site-header.php
git checkout HEAD~1 views/layouts/civicone/partials/header-scripts.php
rm views/layouts/civicone/partials/service-navigation.php
```

### Full Rollback (Including CSS):
```bash
git revert <commit-hash>
```

---

## Next Steps (Priority Order)

1. **Add service navigation CSS** to `civicone-header.css` (see Section 2.1.A above)
2. **Comment out old styles** in `civicone-header.css` (see Section 2.1.C above)
3. **Regenerate minified CSS** (see Section 2.2 commands above)
4. **Test locally** at http://localhost/ (see Section 2.3 checklist)
5. **Visual regression screenshots** (1920px, 768px, 375px)
6. **Axe/Lighthouse audits**
7. **Screen reader walkthrough** (NVDA/VoiceOver)
8. **Deploy to staging** for real-data testing
9. **A/B test** with 5% of users
10. **Full rollout** if metrics good

---

## Definition of Done

Header refactor is COMPLETE when ALL of the following are true:

- [ ] CSS for service navigation pattern added and tested
- [ ] Minified CSS regenerated and verified
- [ ] No JavaScript console errors
- [ ] All keyboard navigation tests pass (see Section 2.3)
- [ ] All focus visibility tests pass
- [ ] All mobile responsive tests pass (375px viewport)
- [ ] All zoom tests pass (200% and 400%)
- [ ] Axe DevTools passes with 0 errors
- [ ] Lighthouse accessibility score ≥95
- [ ] Screen reader walkthrough complete (no blockers)
- [ ] Visual regression screenshots captured and reviewed
- [ ] No horizontal scroll at any viewport width
- [ ] Header stacks cleanly on mobile
- [ ] ONE navigation system only (no mega menu)
- [ ] Active state detection working (aria-current="page")
- [ ] Search functional (desktop and mobile)
- [ ] Utility bar dropdowns work
- [ ] Layout switcher works (CivicOne ↔ Modern)
- [ ] Pusher notifications work
- [ ] AI chat widget appears
- [ ] Documentation updated (this file)

---

**Refactored by:** Claude Sonnet 4.5
**Date:** 2026-01-20
**Status:** Phase 1 Complete (Markup + JavaScript) / Phase 2 Pending (CSS + Testing)
**Compliance:** Follows GOV.UK Service Navigation pattern + Global Header & Navigation Contract (Section 9A)
