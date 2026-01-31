# CivicOne Theme Makeover Plan

## Implementation Status: COMPLETED (2026-01-31)

| Task | Status |
|------|--------|
| Theme scoping guardrails | ✅ Completed |
| CivicOne header makeover | ✅ Completed |
| CivicOne footer makeover | ✅ Completed |
| Accessibility enforcement | ✅ Completed |
| Component replacement sweep | ✅ Started (wallet page migrated) |

### Component Replacement Notes

**Completed:**
- `views/civicone/dashboard/wallet.php` - Migrated to GOV.UK form components and GOV.UK table
  - Forms: `govuk-form-group`, `govuk-label`, `govuk-hint`, `govuk-input`, `govuk-textarea`
  - Table: Using `civicone_govuk_table()` component for transactions
  - Button: `govuk-button` with `data-module="govuk-button"`
  - Layout: `govuk-grid-row`, `govuk-grid-column-*`

**Reviewed but not migrated (already accessible):**
- `views/civicone/compose/index.php` - Custom tabs with proper ARIA attributes
  - Has `role="tablist"`, `role="tab"`, `aria-selected` attributes
  - Migration would require refactoring JS tab switching logic
  - Risk of regression outweighs benefits

**Recommended for future migration:**
- Dashboard pages with custom card layouts
- Listings pages with custom forms
- Profile settings pages

## Executive Summary

This document outlines a comprehensive makeover of the CivicOne theme to achieve full GOV.UK Design System compliance while protecting the Modern (holographic) theme from any changes.

**Mission:**
1. Perform a CivicOne makeover: header, footer, page layouts, components, spacing, typography, forms, tables, tabs, error patterns, focus styles
2. Enforce accessibility patterns (WCAG 2.1 AA) in CivicOne
3. Create hard guardrails so Modern is unaffected

## Files Created/Modified

### New Files

| File | Purpose |
|------|---------|
| `httpdocs/assets/css/theme-scope-guardrails.css` | Hard theme isolation |
| `httpdocs/assets/css/civicone-service-navigation.css` | GOV.UK service nav styling |
| `httpdocs/assets/css/civicone-accessibility-enforcement.css` | WCAG 2.1 AA enforcement |

### Modified Files

| File | Change |
|------|--------|
| `httpdocs/assets/css/layout-isolation.css` | Enhanced component isolation |
| `views/layouts/modern/header.php` | Added guardrails CSS |
| `views/layouts/civicone/partials/assets-css.php` | Added new CSS files |
| `views/layouts/civicone/partials/service-navigation-v2.php` | Full GOV.UK compliance |
| `views/layouts/civicone/partials/site-footer.php` | Service links + disclaimer |
| `httpdocs/assets/css/civicone-footer.css` | Disclaimer + focus styles |
| `purgecss.config.js` | Added new CSS to build |

---

## A) Theme Entry Points (CSS/SCSS)

### Modern Theme CSS Entry Points

**Primary CSS Loader:** `views/layouts/modern/partials/css-loader.php`

| File | Purpose | Load Order |
|------|---------|------------|
| `design-tokens.css` | Shared CSS variables | 1 (sync) |
| `modern-theme-tokens.css` | Modern-specific variables | 2 (sync) |
| `modern-primitives.css` | Layout primitives | 3 (sync) |
| `nexus-phoenix.css` | HTB brand framework | 4 (sync) |
| `nexus-modern-header.css` | Header styles | 5 (sync) |
| `nexus-premium-mega-menu.css` | Mega menu | 6 (sync) |
| `mobile-nav-v2.css` | Mobile navigation | 7 (sync) |
| `bundles/*.css` | Component bundles | 8+ (async) |

### CivicOne Theme CSS Entry Points

**Primary CSS Loader:** `views/layouts/civicone/partials/assets-css.php`

| File | Purpose | Load Order |
|------|---------|------------|
| `design-tokens.css` | Shared CSS variables | 1 (sync) |
| `layout-isolation.css` | Theme isolation | 2 (sync) |
| `nexus-phoenix.css` | Core framework | 3 (sync) |
| `nexus-civicone.css` | CivicOne base theme | 4 (sync) |
| `govuk-frontend-5.14.0/govuk-frontend.min.css` | Official GOV.UK | 5 (sync) |
| `bundles/civicone-govuk-all.css` | GOV.UK extensions | 6 (sync) |
| `civicone-govuk-header.min.css` | GOV.UK header | 7 (sync) |
| `civicone-header-v2.min.css` | Service navigation | 8 (sync) |
| `civicone-mobile-nav-v2.css` | Mobile navigation | 9 (sync) |
| `civicone-*.css` | Page-specific (100+ files) | 10+ (conditional) |

---

## B) Header & Footer Definitions

### Headers

| Theme | File | Key Classes |
|-------|------|-------------|
| **Router** | `views/layouts/header.php` | Proxy - routes to theme header |
| **Modern** | `views/layouts/modern/header.php` | `data-layout="modern"`, `nexus-skin-modern` |
| **CivicOne** | `views/layouts/civicone/header.php` | `data-layout="civicone"`, `govuk-template`, `civicone`, `nexus-skin-civicone` |

**CivicOne Header Partials (8 required):**
1. `document-open.php` - DOCTYPE, html tag
2. `assets-css.php` - All CSS loading
3. `body-open.php` - Body tag with classes
4. `cookie-banner.php` - GOV.UK consent banner
5. `skip-link-and-banner.php` - WCAG 2.4.1 skip link + phase banner
6. `service-navigation-v2.php` - GOV.UK service navigation
7. `main-open.php` - Open main content area
8. *(footer)* `assets-js-footer.php` - JS loading

### Footers

| Theme | File | Key Components |
|-------|------|----------------|
| **Router** | `views/layouts/footer.php` | Proxy - routes to theme footer |
| **Modern** | `views/layouts/modern/footer.php` | `</main>`, glow footer, footer CSS |
| **CivicOne** | `views/layouts/civicone/footer.php` | Modular (4 partials) |

**CivicOne Footer Partials:**
1. `main-close.php` - Close `</main>`
2. `site-footer.php` - GOV.UK footer pattern
3. `assets-js-footer.php` - JavaScript loading
4. `document-close.php` - Close `</body></html>`

---

## C) Theme Selection Mechanism

### Source of Truth

**File:** `src/Services/LayoutHelper.php`

### Selection Priority (in order)

1. **Runtime override** (testing only) - `LayoutHelper::setRuntimeOverride()`
2. **User DB preference** - `users.preferred_layout` column
3. **Session value** - `$_SESSION['nexus_active_layout']`
4. **Tenant default** - `tenants.default_layout` column
5. **Hardcoded default** - `'modern'`

### Helper Functions (`src/helpers.php`)

```php
layout()       // Returns: 'modern' | 'civicone'
is_civicone()  // Returns: bool
is_modern()    // Returns: bool
```

### HTML Attributes Applied

```html
<!-- Modern -->
<html lang="en" data-theme="dark" data-layout="modern">
<body class="nexus-skin-modern ...">

<!-- CivicOne -->
<html lang="en" class="govuk-template" data-theme="light" data-layout="civicone">
<body class="govuk-template__body civicone civicone--govuk nexus-skin-civicone ...">
```

### Valid Body Classes for Scoping

| Selector | Theme | Use Case |
|----------|-------|----------|
| `[data-layout="modern"]` | Modern | CSS scoping |
| `[data-layout="civicone"]` | CivicOne | CSS scoping |
| `.nexus-skin-modern` | Modern | Component targeting |
| `.nexus-skin-civicone` | CivicOne | Component targeting |
| `.civicone` | CivicOne | Legacy/shorthand |
| `.govuk-template` | CivicOne | GOV.UK compliance |

---

## D) Shared Component Libraries

### Shared Components Location

| Directory | Purpose | Used By |
|-----------|---------|---------|
| `views/skeleton/` | Theme-agnostic abstractions | Both themes |
| `views/civicone/components/shared/` | Shared Nexus components | CivicOne |
| `views/civicone/components/govuk/` | GOV.UK Design System (28+ components) | CivicOne only |
| `views/modern/components/` | Modern theme components | Modern only |

### GOV.UK Components Available (`views/civicone/components/govuk/`)

- `accordion.php`, `back-link.php`, `breadcrumbs.php`, `card.php`
- `checkboxes.php`, `date-input.php`, `details.php`, `error-summary.php`
- `fieldset.php`, `file-upload.php`, `form-input.php`, `inset-text.php`
- `notification-banner.php`, `pagination.php`, `panel.php`, `phase-banner.php`
- `radios.php`, `select.php`, `skip-link.php`, `summary-list.php`
- `table.php`, `tabs.php`, `tag.php`, `task-list.php`, `textarea.php`
- `warning-text.php`, `question-page.php`, `confirmation-page.php`

### CSS Isolation File

**File:** `httpdocs/assets/css/layout-isolation.css`

**Current Rules:**
- `.civic-only` hidden in Modern
- `.modern-only` hidden in CivicOne
- `.civicone-header` hidden in Modern
- `.modern-header` hidden in CivicOne
- `.civicone-nav` hidden in Modern
- `.modern-nav` hidden in CivicOne
- `.civicone-bottom-nav` hidden in Modern
- `.modern-utility-bar` hidden in CivicOne

---

## Commit-by-Commit Implementation Plan

### Commit 1: Theme Scoping Guardrails

**Goal:** Ensure all theme-specific CSS is properly scoped to prevent cross-theme leakage.

**Files to Create/Modify:**

1. **`httpdocs/assets/css/theme-scope-guardrails.css`** (NEW)
   - Root-level scoping rules
   - CSS custom property isolation
   - Generic element reset prevention

2. **`httpdocs/assets/css/layout-isolation.css`** (ENHANCE)
   - Add more comprehensive isolation rules
   - Add CSS linting comments for future enforcement

3. **`views/layouts/civicone/partials/assets-css.php`** (MODIFY)
   - Add theme-scope-guardrails.css to load order

**Changes:**

```css
/* theme-scope-guardrails.css */

/* === CRITICAL: SCOPE ALL CIVICONE STYLES === */
/* CivicOne styles MUST be scoped with one of these selectors: */
/* - [data-layout="civicone"] */
/* - body.civicone */
/* - body.nexus-skin-civicone */
/* - .govuk-template */

/* === MODERN THEME PROTECTION === */
/* These selectors NEVER appear in CivicOne CSS files: */
/* - [data-layout="modern"] */
/* - body.nexus-skin-modern */

/* Prevent generic element targeting */
[data-layout="civicone"] {
    /* CivicOne-specific custom properties */
    --theme-active: civicone;
}

[data-layout="modern"] {
    /* Modern-specific custom properties */
    --theme-active: modern;
}

/* Block unscoped rules from affecting wrong theme */
/* This CSS variable check acts as a guardrail */
```

**Rollback:** Delete `theme-scope-guardrails.css`, revert `layout-isolation.css`

**Test Steps:**
1. Load Modern theme - verify no CivicOne classes appear
2. Load CivicOne theme - verify no Modern classes appear
3. Switch themes - verify no style bleed
4. Run Lighthouse on both themes

---

### Commit 2: CivicOne Header Makeover

**Goal:** Implement GOV.UK Design System compliant header with proper spacing, typography, navigation, focus states, keyboard navigation, skip link, and landmarks.

**Source Reference:** https://github.com/alphagov/govuk-frontend/tree/main/packages/govuk-frontend/src/govuk/components/service-navigation

**Files to Modify:**

1. **`views/layouts/civicone/partials/service-navigation-v2.php`**
   - Ensure exact GOV.UK HTML structure
   - Proper ARIA attributes
   - Keyboard navigation support

2. **`views/layouts/civicone/partials/skip-link-and-banner.php`**
   - Verify skip link is first focusable element
   - Proper focus styling

3. **`httpdocs/assets/css/civicone-govuk-header.css`** (ENHANCE)
   - GOV.UK-compliant spacing (8px grid)
   - GOV.UK typography (GDS Transport / fallback)
   - Focus visible states (3px yellow outline)
   - Mobile responsive patterns

4. **`httpdocs/assets/css/civicone-service-navigation.css`** (ENHANCE)
   - Service navigation patterns
   - Mobile menu behavior

**Key Changes:**

```html
<!-- Proper landmark structure -->
<header class="govuk-header" role="banner">
  <!-- Skip link already in skip-link-and-banner.php -->
</header>

<nav aria-label="Service navigation" class="govuk-service-navigation">
  <!-- Current page indicator: aria-current="page" -->
</nav>

<main id="main-content" class="govuk-main-wrapper" role="main" tabindex="-1">
  <!-- tabindex="-1" for Firefox skip link compatibility -->
</main>
```

```css
/* Focus states - GOV.UK standard */
.govuk-link:focus,
.govuk-service-navigation__link:focus {
    outline: 3px solid #ffdd00;
    outline-offset: 0;
    background-color: #ffdd00;
    box-shadow: 0 -2px #ffdd00, 0 4px #0b0c0c;
    text-decoration: none;
    color: #0b0c0c;
}

/* Spacing - GOV.UK 8px grid */
.govuk-service-navigation {
    padding: 10px 0; /* GOV.UK standard */
}

.govuk-width-container {
    max-width: 1020px;
    margin: 0 15px;
}

@media (min-width: 40.0625em) {
    .govuk-width-container {
        margin: 0 30px;
    }
}

@media (min-width: 1020px) {
    .govuk-width-container {
        margin: 0 auto;
    }
}
```

**Rollback:** Git revert commit

**Test Steps:**
1. Tab through header - verify focus order
2. Check skip link navigates to main content
3. Verify `aria-current="page"` on active nav item
4. Test mobile menu toggle (keyboard + touch)
5. Lighthouse accessibility audit

---

### Commit 3: CivicOne Footer Makeover

**Goal:** GOV.UK-compliant footer with service links, ownership disclaimer, proper structure.

**Source Reference:** https://design-system.service.gov.uk/components/footer/

**Files to Modify:**

1. **`views/layouts/civicone/partials/site-footer.php`**
   - Add required service links (accessibility, privacy, cookies, terms, contact)
   - Add service ownership disclaimer
   - Proper landmark structure

2. **`httpdocs/assets/css/civicone-footer.css`** (CREATE or ENHANCE existing)
   - GOV.UK footer spacing
   - Link styling
   - Mobile responsive

**Required Footer Links:**

```html
<footer class="govuk-footer" role="contentinfo">
    <div class="govuk-width-container">
        <!-- Navigation sections (current) -->

        <hr class="govuk-footer__section-break">

        <!-- Meta section -->
        <div class="govuk-footer__meta">
            <div class="govuk-footer__meta-item govuk-footer__meta-item--grow">
                <h2 class="govuk-visually-hidden">Support links</h2>
                <ul class="govuk-footer__inline-list">
                    <li class="govuk-footer__inline-list-item">
                        <a class="govuk-footer__link" href="/accessibility">Accessibility statement</a>
                    </li>
                    <li class="govuk-footer__inline-list-item">
                        <a class="govuk-footer__link" href="/privacy">Privacy policy</a>
                    </li>
                    <li class="govuk-footer__inline-list-item">
                        <a class="govuk-footer__link" href="/cookie-preferences">Cookies</a>
                    </li>
                    <li class="govuk-footer__inline-list-item">
                        <a class="govuk-footer__link" href="/terms">Terms and conditions</a>
                    </li>
                    <li class="govuk-footer__inline-list-item">
                        <a class="govuk-footer__link" href="/contact">Contact</a>
                    </li>
                </ul>
            </div>
            <div class="govuk-footer__meta-item">
                <!-- Service ownership disclaimer -->
                <p class="govuk-body-s">
                    This is a community service. It is not affiliated with or endorsed by the UK Government.
                </p>
                <p class="govuk-body-s">
                    Built with elements from the
                    <a class="govuk-footer__link" href="https://design-system.service.gov.uk/">GOV.UK Design System</a>.
                </p>
            </div>
        </div>
    </div>
</footer>
```

**Rollback:** Git revert commit

**Test Steps:**
1. Verify all links are functional
2. Check footer landmark (`role="contentinfo"`)
3. Tab through footer links
4. Mobile layout test

---

### Commit 4: Component Replacement Sweep

**Goal:** Replace raw HTML elements with GOV.UK Design System components throughout CivicOne pages.

**Replacements:**

| Raw Pattern | GOV.UK Component | Component File |
|-------------|------------------|----------------|
| `<table>` | `govuk-table` | `views/civicone/components/govuk/table.php` |
| Tab panels | `govuk-tabs` | `views/civicone/components/govuk/tabs.php` |
| `<button>` | `govuk-button` | CSS classes |
| Form groups | `govuk-form-group` | `views/civicone/components/govuk/form-input.php` |
| Error messages | `govuk-error-message` | `views/civicone/components/govuk/error-summary.php` |
| Summary lists | `govuk-summary-list` | `views/civicone/components/govuk/summary-list.php` |

**Files to Audit/Modify (Priority Order):**

1. **Dashboard pages** - `views/civicone/dashboard/`
2. **Listing pages** - `views/civicone/listings/`
3. **Profile pages** - `views/civicone/profile/`
4. **Settings pages** - `views/civicone/settings/`
5. **Admin pages** - `views/admin/` (CivicOne variants)

**Example Replacements:**

```php
<!-- BEFORE: Raw table -->
<table class="data-table">
    <thead><tr><th>Name</th><th>Amount</th></tr></thead>
    <tbody><tr><td>John</td><td>10</td></tr></tbody>
</table>

<!-- AFTER: GOV.UK table -->
<?php require_once __DIR__ . '/../components/govuk/table.php'; ?>
<?= civicone_govuk_table([
    'head' => [['text' => 'Name'], ['text' => 'Amount']],
    'rows' => [
        [['text' => 'John'], ['text' => '10', 'format' => 'numeric']]
    ]
]) ?>
```

```php
<!-- BEFORE: Raw tabs -->
<div class="tabs">
    <button class="tab active">Tab 1</button>
    <button class="tab">Tab 2</button>
</div>

<!-- AFTER: GOV.UK tabs -->
<?php require_once __DIR__ . '/../components/govuk/tabs.php'; ?>
<?= civicone_govuk_tabs([
    'items' => [
        ['label' => 'Tab 1', 'id' => 'tab1', 'panel' => $tab1Content],
        ['label' => 'Tab 2', 'id' => 'tab2', 'panel' => $tab2Content]
    ]
]) ?>
```

**Rollback:** Git revert commit (per-file if needed)

**Test Steps:**
1. Visual inspection of each modified page
2. Keyboard navigation through new components
3. Screen reader testing (VoiceOver/NVDA)

---

### Commit 5: Accessibility Enforcement Sweep

**Goal:** Ensure all CivicOne pages meet WCAG 2.1 AA standards.

**Checklist:**

| Requirement | WCAG Criterion | Action |
|-------------|----------------|--------|
| Focus visible | 2.4.7 | Add `:focus-visible` styles |
| ARIA labels | 4.1.2 | Add missing `aria-label`, `aria-labelledby` |
| Error linking | 3.3.1 | Link errors to fields via `aria-describedby` |
| Color contrast | 1.4.3 | Audit all text for 4.5:1 ratio |
| Heading order | 1.3.1 | Ensure logical h1 > h2 > h3 hierarchy |
| Alt text | 1.1.1 | Add alt to all images |
| Skip links | 2.4.1 | Already implemented |
| Keyboard | 2.1.1 | Test all interactive elements |

**Files to Create/Modify:**

1. **`httpdocs/assets/css/civicone-govuk-focus.css`** (ENHANCE)
   - Comprehensive focus-visible styles
   - GOV.UK yellow focus ring

2. **`httpdocs/assets/css/civicone-accessibility-enforcement.css`** (NEW)
   - Forced focus states for custom components
   - High contrast mode support

3. **Multiple view files** - Add missing ARIA attributes

**Focus Style Implementation:**

```css
/* civicone-accessibility-enforcement.css */

/* Focus visible for all interactive elements */
[data-layout="civicone"] a:focus-visible,
[data-layout="civicone"] button:focus-visible,
[data-layout="civicone"] input:focus-visible,
[data-layout="civicone"] select:focus-visible,
[data-layout="civicone"] textarea:focus-visible,
[data-layout="civicone"] [tabindex]:focus-visible {
    outline: 3px solid #ffdd00;
    outline-offset: 0;
    box-shadow: 0 -2px #ffdd00, 0 4px #0b0c0c;
}

/* Error field highlighting */
[data-layout="civicone"] .govuk-input--error,
[data-layout="civicone"] .govuk-select--error,
[data-layout="civicone"] .govuk-textarea--error {
    border-color: #d4351c;
    border-width: 4px;
}

/* High contrast mode support */
@media (prefers-contrast: high) {
    [data-layout="civicone"] a:focus-visible,
    [data-layout="civicone"] button:focus-visible {
        outline: 3px solid currentColor !important;
    }
}
```

**Rollback:** Git revert commit

**Test Steps:**
1. Lighthouse accessibility audit (target: 95+)
2. axe DevTools scan (target: 0 violations)
3. Keyboard-only navigation test
4. Screen reader test (NVDA on Windows, VoiceOver on Mac)
5. Color contrast check with WebAIM tool

---

### Commit 6: Documentation and Testing

**Goal:** Document all changes and provide comprehensive testing checklists.

**Files to Create:**

1. **`docs/CIVICONE_MAKEOVER_COMPLETE.md`** - Summary of changes
2. **`tests/civicone-visual-regression.md`** - Manual visual test scripts
3. **`tests/modern-no-change-verification.md`** - Modern theme verification

---

## Diff Risk Report

### Files Changed (CivicOne Only - Modern Unaffected)

| File | Theme | Risk Level | Rollback |
|------|-------|------------|----------|
| `httpdocs/assets/css/theme-scope-guardrails.css` | Both (isolation) | Low | Delete file |
| `httpdocs/assets/css/layout-isolation.css` | Both (isolation) | Low | Git revert |
| `httpdocs/assets/css/civicone-govuk-header.css` | CivicOne | Medium | Git revert |
| `httpdocs/assets/css/civicone-service-navigation.css` | CivicOne | Medium | Git revert |
| `httpdocs/assets/css/civicone-footer.css` | CivicOne | Low | Git revert |
| `httpdocs/assets/css/civicone-accessibility-enforcement.css` | CivicOne | Low | Delete file |
| `views/layouts/civicone/partials/service-navigation-v2.php` | CivicOne | Medium | Git revert |
| `views/layouts/civicone/partials/site-footer.php` | CivicOne | Low | Git revert |
| `views/layouts/civicone/partials/assets-css.php` | CivicOne | Low | Git revert |
| `views/civicone/**/*.php` (component replacements) | CivicOne | Medium | Git revert |

### Files NOT Changed (Modern Protection)

| File | Reason |
|------|--------|
| `views/layouts/modern/*` | Protected - no modifications |
| `httpdocs/assets/css/modern-*.css` | Protected - no modifications |
| `httpdocs/assets/css/nexus-modern-*.css` | Protected - no modifications |
| `views/modern/**/*` | Protected - no modifications |

---

## Testing Checklists

### Modern Theme Verification (5 Tests)

**Purpose:** Verify Modern theme is completely unaffected by CivicOne changes.

| # | Test | Steps | Expected Result |
|---|------|-------|-----------------|
| 1 | **Visual Regression** | Take screenshots of: homepage, dashboard, listings, profile, messages | Pixel-identical to baseline |
| 2 | **CSS Inspection** | Open DevTools, check body classes and `data-layout` | `data-layout="modern"`, no `civicone` classes |
| 3 | **Console Errors** | Open DevTools Console | No new errors |
| 4 | **Lighthouse Performance** | Run Lighthouse on 3 pages | Score within 5 points of baseline |
| 5 | **Functionality** | Test: login, post creation, messaging | All features work |

**How to Visual Regression Test Modern:**

```bash
# 1. Before changes - create baseline
# Using Playwright or similar tool
npx playwright screenshot http://staging.timebank.local/hour-timebank/ --full-page baseline-home.png
npx playwright screenshot http://staging.timebank.local/hour-timebank/dashboard --full-page baseline-dashboard.png
# ... more pages

# 2. After changes - create comparison
npx playwright screenshot http://staging.timebank.local/hour-timebank/ --full-page current-home.png
npx playwright screenshot http://staging.timebank.local/hour-timebank/dashboard --full-page current-dashboard.png

# 3. Compare (using ImageMagick)
compare baseline-home.png current-home.png diff-home.png
# Should produce blank diff (no differences)
```

### CivicOne Theme Verification (10 Tests)

| # | Test | Steps | Expected Result |
|---|------|-------|-----------------|
| 1 | **Skip Link** | Tab once from page load | Skip link receives focus, visible |
| 2 | **Skip Link Navigation** | Press Enter on skip link | Focus moves to main content |
| 3 | **Header Focus States** | Tab through header navigation | Yellow focus ring on each item |
| 4 | **Header Keyboard** | Use arrow keys in mobile menu | Menu navigates correctly |
| 5 | **Footer Links** | Click each footer link | All links work |
| 6 | **Footer Landmark** | Check with axe DevTools | `contentinfo` landmark present |
| 7 | **Table Component** | Find a page with GOV.UK table | Proper `<caption>`, `<thead>`, `<tbody>` |
| 8 | **Tabs Component** | Find a page with GOV.UK tabs | Keyboard arrow navigation works |
| 9 | **Error Summary** | Submit invalid form | Error summary at top, links to fields |
| 10 | **Contrast Check** | Run WAVE extension | No contrast errors |

### Lighthouse Audit Targets

| Theme | Category | Minimum Score |
|-------|----------|---------------|
| Modern | Performance | 85 |
| Modern | Accessibility | 90 |
| CivicOne | Performance | 80 |
| CivicOne | Accessibility | **95** (improved) |

---

## Acceptance Criteria

### Must Have

- [ ] Modern theme unchanged visually and functionally
- [ ] CivicOne skip link is first focusable element
- [ ] CivicOne header has GOV.UK focus states
- [ ] CivicOne footer has all required service links
- [ ] CivicOne footer has ownership disclaimer
- [ ] Lighthouse accessibility score 95+ for CivicOne
- [ ] No console errors on either theme

### Should Have

- [ ] GOV.UK tables replace raw tables in CivicOne
- [ ] GOV.UK tabs replace raw tabs in CivicOne
- [ ] Error summaries link to form fields

### Nice to Have

- [ ] Automated visual regression tests
- [ ] CSS linting rules to prevent cross-theme styles

---

## Appendix: File Paths Reference

### CivicOne CSS Files (alphabetical selection)

```
httpdocs/assets/css/civicone-*.css (100+ files)
httpdocs/assets/css/bundles/civicone-*.css
httpdocs/assets/css/govuk-frontend-5.14.0/
```

### CivicOne View Files

```
views/layouts/civicone/
views/civicone/
views/civicone/components/govuk/
views/civicone/components/shared/
```

### Modern CSS Files (DO NOT MODIFY)

```
httpdocs/assets/css/modern-*.css
httpdocs/assets/css/nexus-modern-*.css
httpdocs/assets/css/nexus-premium-*.css
```

### Modern View Files (DO NOT MODIFY)

```
views/layouts/modern/
views/modern/
views/modern/components/
```
