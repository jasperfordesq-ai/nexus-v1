# CivicOne GOV.UK Design System Audit & Makeover Plan

**Created:** 2026-01-30
**Last Updated:** 2026-01-30
**Status:** Active
**Theme Scope:** CivicOne only (Modern theme is READ-ONLY)

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [Theme Boundary Enforcement](#theme-boundary-enforcement)
3. [Component Coverage Audit](#component-coverage-audit)
4. [Missing Components](#missing-components)
5. [Raw HTML Replacements Needed](#raw-html-replacements-needed)
6. [Makeover Plan - PR Sequence](#makeover-plan---pr-sequence)
7. [Testing Requirements](#testing-requirements)
8. [Accessibility Compliance Checklist](#accessibility-compliance-checklist)

---

## Executive Summary

The CivicOne theme has **strong foundational alignment** with GOV.UK Design System patterns but has several gaps that need addressing:

| Category | Status |
|----------|--------|
| **Components Fully Implemented** | 24 of 35 |
| **Components Partially Implemented** | 9 of 35 |
| **Components Missing** | 2 of 35 |
| **Critical CSS Bug** | 1 (media query variables) |
| **Accessibility Issues** | 6 (documented below) |

### Key Strengths
- Comprehensive PHP partial library at `views/civicone/components/govuk/`
- Proper GOV.UK class naming (`govuk-*`)
- Good design token integration
- WCAG 2.1 AA awareness in component documentation

### Critical Issues

1. ~~**CSS Variables in Media Queries** - Responsive spacing completely broken~~ ✅ FIXED 2026-01-30
2. **Error Message Accessibility** - Using `::before` instead of `<span class="govuk-visually-hidden">`
3. ~~**Custom Navigation Dropdown** - Non-GOV.UK pattern, keyboard trap risk~~ ✅ REVIEWED: Properly documented as CivicOne extension with keyboard nav
4. ~~**Missing Footer Heading** - Screen readers can't navigate support links~~ ✅ VERIFIED: Already has `<h2 class="govuk-visually-hidden">Support links</h2>`

---

## Theme Boundary Enforcement

### ABSOLUTE RULES

1. **DO NOT** modify any file in:
   - `views/modern/`
   - `views/layouts/modern/`
   - `httpdocs/assets/css/modern-*.css`

2. **ONLY** modify files in:
   - `views/civicone/`
   - `views/layouts/civicone/`
   - `httpdocs/assets/css/civicone-*.css`
   - `httpdocs/assets/js/civicone-*.js`

3. **DO NOT** modify shared files:
   - `design-tokens.css` (used by both themes)
   - `nexus-phoenix.css` (core framework)
   - `views/partials/` (shared partials)

### CSS Scoping Requirements

All CivicOne CSS must use one of these selectors:

```css
/* Option 1: Data attribute */
[data-layout="civicone"] .my-class { }

/* Option 2: Body class */
body.nexus-skin-civicone .my-class { }

/* Option 3: Wrapper class */
.civicone .my-class { }

/* Option 4: Prefixed class (preferred for new classes) */
.civicone-my-class { }
```

### Pre-Merge Verification Script

```bash
#!/bin/bash
# Run before merging any CivicOne PR

# Check for Modern file modifications
MODERN_FILES=$(git diff --name-only main | grep -E "(views/modern|views/layouts/modern|modern-.*\.css)")
if [ -n "$MODERN_FILES" ]; then
    echo "ERROR: Modern theme files modified:"
    echo "$MODERN_FILES"
    exit 1
fi

# Check for shared file modifications
SHARED_FILES=$(git diff --name-only main | grep -E "(design-tokens|nexus-phoenix|views/partials/)")
if [ -n "$SHARED_FILES" ]; then
    echo "WARNING: Shared files modified - review carefully:"
    echo "$SHARED_FILES"
fi

echo "Theme boundary check passed"
```

---

## Component Coverage Audit

### Component Library Locations

| Type | Location |
|------|----------|
| **GOV.UK Reference** | `govuk-frontend-ref/packages/govuk-frontend/src/govuk/components/` |
| **CivicOne PHP Partials** | `views/civicone/components/govuk/` (30 files) |
| **CivicOne Layout Partials** | `views/layouts/civicone/partials/` (29 files) |
| **CivicOne View Partials** | `views/civicone/partials/` (11 files) |
| **CivicOne CSS** | `httpdocs/assets/css/civicone-govuk-*.css` |

### Full Coverage Table

| GOV.UK Component | In Library? | Used? | Implementation | Gaps/Deviations | Priority |
|------------------|-------------|-------|----------------|-----------------|----------|
| Accordion | Yes | Yes (3) | PHP partial | Missing JS init in some usages | P1 |
| Back link | Yes | Yes (56) | PHP partial | Some raw HTML usage | P1 |
| Breadcrumbs | Yes | Yes (103) | PHP partial | Some raw HTML, needs standardization | P0 |
| Button | Yes | Yes | PHP partial | Missing `data-module` attribute | P0 |
| Character count | Partial | No | Referenced only | No standalone component | P1 |
| Checkboxes | Yes | Yes | PHP partial | Compliant | P0 |
| Cookie banner | Yes | Yes | Inline HTML | Inline `<script>`, custom notification | P0 |
| Date input | Yes | Yes | PHP partial | Compliant | P0 |
| Details | Yes | Yes (5) | PHP partial | Could be used more widely | P1 |
| Error message | Partial | Yes | CSS only | No PHP partial, wrong a11y pattern | P0 |
| Error summary | Yes | Yes (24) | PHP partial | Some pages use raw HTML | P0 |
| Exit this page | No | No | Not implemented | Safety component missing | P2 |
| Fieldset | Yes | Yes | PHP partial | Compliant | P0 |
| File upload | Yes | Yes | PHP partial | Compliant | P0 |
| Footer | Partial | Yes | Inline HTML | Missing visually hidden heading | P0 |
| Header | Partial | Yes | Inline HTML | Custom dropdown, no partial | P0 |
| Hint | Partial | Yes | CSS only | No standalone partial | P0 |
| Input | Yes | Yes | PHP partial | Named `form-input.php` | P0 |
| Inset text | Yes | Yes (87) | PHP partial | Compliant, heavily used | P1 |
| Label | Partial | Yes | CSS only | No standalone partial | P0 |
| Notification banner | Yes | Yes (48) | PHP partial | Compliant with auto-focus | P1 |
| Pagination | Yes | Yes (14) | PHP partial | Compliant | P1 |
| Panel | Yes | Yes (10) | PHP partial | Compliant | P1 |
| Password input | No | No | Not implemented | No show/hide toggle | P1 |
| Phase banner | Yes | Yes | PHP partial + inline | Two implementations | P0 |
| Radios | Yes | Yes | PHP partial | Compliant | P0 |
| Select | Yes | Yes | PHP partial | Compliant | P0 |
| Service navigation | Partial | Yes | Inline HTML | Custom dropdown, no partial | P0 |
| Skip link | Yes | Yes | PHP partial + inline | Compliant | P0 |
| Summary list | Yes | Yes (27) | PHP partial | Compliant | P1 |
| Table | Yes | Yes (22) | PHP partial | Compliant | P1 |
| Tabs | Yes | Yes (6) | PHP partial | Some raw HTML usage | P1 |
| Tag | Yes | Yes (86) | PHP partial | Compliant, heavily used | P1 |
| Task list | Yes | Minimal (1) | PHP partial | Underutilized | P2 |
| Textarea | Yes | Yes | PHP partial | References char count, no impl | P0 |
| Warning text | Yes | Yes (14) | PHP partial | Compliant | P1 |

### Coverage Statistics

```
Full Implementation:    24 components (69%)
Partial Implementation:  9 components (26%)
Not Implemented:         2 components (5%)
─────────────────────────────────────────
Total GOV.UK Components: 35
```

---

## Missing Components

### 1. Exit This Page (`exit-this-page`)

**Status:** Not implemented
**Priority:** P2
**GOV.UK Spec:** Large red button for quick exit from sensitive content

**Pages That May Need It:**
- Domestic abuse support pages
- Mental health resources
- Any sensitive content where user may need quick exit

**Implementation Path:**
```
views/civicone/components/govuk/exit-this-page.php (new)
httpdocs/assets/css/civicone-exit-this-page.css (new)
httpdocs/assets/js/civicone-exit-this-page.js (new)
```

---

### 2. Password Input (`password-input`)

**Status:** Not implemented
**Priority:** P1
**GOV.UK Spec:** Password field with show/hide toggle

**Pages That Need It:**
- `views/civicone/auth/login.php`
- `views/civicone/auth/register.php`
- `views/civicone/auth/reset_password.php`
- `views/civicone/settings/index.php`

**Implementation Path:**
```
views/civicone/components/govuk/password-input.php (new)
httpdocs/assets/js/civicone-password-input.js (new)
```

---

### 3. Character Count (`character-count`)

**Status:** Referenced but not implemented
**Priority:** P1
**GOV.UK Spec:** Live counter for text length

**Pages That Need It:**
- Any textarea with max length
- Bio/description fields
- Message composition

**Implementation Path:**
```
views/civicone/components/govuk/character-count.php (new)
httpdocs/assets/js/civicone-character-count.js (new)
```

---

### 4. Error Message (Standalone)

**Status:** CSS only, no partial
**Priority:** P0

**Current Problem:**
```css
/* WRONG - using pseudo-element */
.govuk-error-message::before {
    content: "Error: ";
    /* visually hidden styles */
}
```

**Correct Pattern:**
```html
<p class="govuk-error-message">
    <span class="govuk-visually-hidden">Error:</span>
    Enter your email address
</p>
```

**Implementation Path:**
```
views/civicone/components/govuk/error-message.php (new)
```

---

### 5. Hint (Standalone)

**Status:** CSS only, no partial
**Priority:** P0

**Implementation Path:**
```
views/civicone/components/govuk/hint.php (new)
```

---

### 6. Label (Standalone)

**Status:** CSS only, no partial
**Priority:** P0

**Implementation Path:**
```
views/civicone/components/govuk/label.php (new)
```

---

## Raw HTML Replacements Needed

These locations use raw HTML instead of existing PHP partials:

| File | Component | Current | Should Use |
|------|-----------|---------|------------|
| `views/civicone/auth/login.php:32-38` | Error Summary | Raw HTML | `civicone_govuk_error_summary()` |
| `views/layouts/civicone/partials/skip-link-and-banner.php:6-15` | Phase Banner | Raw HTML | `civicone_govuk_phase_banner()` |
| `views/layouts/civicone/partials/site-footer.php` | Footer | Raw HTML | Create `civicone_govuk_footer()` |
| `views/layouts/civicone/partials/service-navigation-v2.php` | Service Nav | Raw HTML | Create `civicone_govuk_service_navigation()` |
| Multiple pages (~103) | Breadcrumbs | Mixed | Standardize on `civicone_govuk_breadcrumbs()` |
| Multiple pages (~56) | Back Link | Mixed | Standardize on `civicone_govuk_back_link()` |

---

## Makeover Plan - PR Sequence

### Phase 1: Critical Fixes (P0)

#### PR 1: Fix CSS Variable Media Queries ✅ COMPLETED 2026-01-30

**Files:** `httpdocs/assets/css/civicone-govuk-spacing.css`
**Risk:** HIGH (critical bug fix)
**Status:** ✅ IMPLEMENTED

**Changes Made:**

- Replaced 11 instances of `@media (min-width: var(--govuk-breakpoint-tablet))` with `@media (min-width: 40.0625em)`
- Replaced 2 instances of `@media (min-width: var(--govuk-breakpoint-desktop))` with `@media (min-width: 61.875em)`
- Added explanatory comment documenting why CSS variables cannot be used in media query conditions

---

#### PR 2: Create Error/Hint/Label Partials ✅ COMPLETED 2026-01-30

**Files:**
- `views/civicone/components/govuk/error-message.php` (new)
- `views/civicone/components/govuk/hint.php` (new)
- `views/civicone/components/govuk/label.php` (new)

**Risk:** LOW
**Status:** ✅ IMPLEMENTED

**Changes Made:**
- Created error-message.php with proper `<span class="govuk-visually-hidden">Error:</span>` pattern
- Created hint.php for form field hints
- Created label.php with isPageHeading support for wrapping in `<h1>`

---

#### PR 3: Fix Skip Link Structure ✅ COMPLETED 2026-01-30

**Files:**
- `views/layouts/civicone/partials/skip-link-and-banner.php`
- `views/layouts/civicone/partials/main-open.php`

**Risk:** LOW
**Status:** ✅ IMPLEMENTED

**Changes Made:**
- Updated skip-link-and-banner.php to use `civicone_govuk_skip_link()` component
- Added `tabindex="-1"` to main element for Firefox compatibility
- Skip link already had `data-module="govuk-skip-link"` attribute

---

#### PR 4: Fix Footer Accessibility ✅ VERIFIED COMPLIANT 2026-01-30

**Files:**

- `views/layouts/civicone/partials/site-footer.php`
- `httpdocs/assets/css/civicone-footer.css`

**Risk:** LOW
**Status:** ✅ NO CHANGES NEEDED - Already compliant

**Verification:**
The footer at line 144 already contains: `<h2 class="govuk-visually-hidden">Support links</h2>`
Footer section widths correctly use `govuk-grid-column-one-quarter` per GOV.UK pattern.

---

#### PR 5: Add data-module to Buttons ✅ COMPLETED 2026-01-30

**Files:** `views/civicone/components/govuk/button.php`
**Risk:** LOW
**Status:** ✅ IMPLEMENTED

**Changes Made:**
- Added `data-module="govuk-button"` to all buttons
- Added `draggable="false"` to link-styled buttons
- Added `isStartButton` option with SVG arrow icon
- Added `preventDoubleClick` option
- Changed default button type from `button` to `submit` (GOV.UK default)
- Added support for `name` and `value` attributes

---

#### PR 6: Fix Auth Page Error Handling ✅ COMPLETED 2026-01-30

**Files:**

- `views/civicone/auth/login.php`
- `views/civicone/auth/forgot_password.php`
- `views/civicone/auth/reset_password.php`

**Risk:** LOW
**Status:** ✅ IMPLEMENTED

**Changes Made:**

- Replaced raw error summary HTML with `civicone_govuk_error_summary()` calls
- Added proper `require_once` for error-summary component
- Error links now point to relevant form fields for keyboard navigation
- Note: register.php doesn't have inline error handling (uses server-side validation)

---

### Phase 2: Typography & Forms (P0-P1)

#### PR 7: Fix Caption Font Size ✅ COMPLETED 2026-01-30

**Files:** `httpdocs/assets/css/civicone-govuk-typography.css`
**Risk:** LOW
**Status:** ✅ IMPLEMENTED

**Changes Made:**
- Increased caption minimum size from 12px/14px to 16px/19px for WCAG 2.1 AA compliance
- Updated header comment to document elevation from Point 14 to Point 19 scale

---

#### PR 8: Add aria-describedby to Inputs ✅ COMPLETED 2026-01-30

**Files:**
- `views/civicone/auth/login.php`
- `views/civicone/auth/register.php`

**Risk:** LOW
**Status:** ✅ IMPLEMENTED

**Changes Made:**
- Added hint IDs and aria-describedby to login email and password fields
- Added aria-describedby to register form fields (last_name, location, phone, password)
- Added spellcheck="false" to name fields

---

#### PR 9: Create Password Input Component ✅ COMPLETED 2026-01-30

**Files:**
- `views/civicone/components/govuk/password-input.php` (new)
- `httpdocs/assets/css/civicone-password-input.css` (new)
- `httpdocs/assets/js/civicone-password-input.js` (new)

**Risk:** MEDIUM
**Status:** ✅ IMPLEMENTED

**Changes Made:**
- Created PHP component with show/hide toggle button
- Added i18n support for button text and screen reader announcements
- Implemented progressive enhancement (button hidden without JS)
- Added CSS for inline button layout on desktop
- JavaScript handles toggle state and screen reader announcements

---

#### PR 10: Create Character Count Component ✅ COMPLETED 2026-01-30

**Files:**
- `views/civicone/components/govuk/character-count.php` (new)
- `httpdocs/assets/css/civicone-character-count.css` (new)
- `httpdocs/assets/js/civicone-character-count.js` (new)

**Risk:** MEDIUM
**Status:** ✅ IMPLEMENTED

**Changes Made:**
- Created PHP component supporting both character and word limits
- Added threshold option (show count after X% of limit reached)
- JavaScript updates count in real-time with debounced screen reader announcements
- Automatic error state when limit exceeded

---

#### PR 11: Refactor Service Navigation ✅ REVIEWED 2026-01-30

**Files:**

- `views/layouts/civicone/partials/service-navigation-v2.php`
- `httpdocs/assets/css/civicone-header-v2.css`
- `httpdocs/assets/js/civicone-header-v2.js`

**Risk:** MEDIUM
**Status:** ✅ DOCUMENTED AS CIVICONE EXTENSION - No immediate changes needed

**Review Findings:**
The "More" dropdown is necessary for CivicOne's extensive navigation (GOV.UK service navigation only handles flat lists).
The implementation is already well-documented and compliant:

- CSS clearly documents it as "CivicOne Extension" with GOV.UK-sourced patterns
- JavaScript includes full keyboard navigation (Arrow keys, Home, End, Escape, Tab)
- Focus management is properly implemented
- ARIA attributes are correct (`aria-expanded`, `aria-controls`, `aria-haspopup`)
- Header documentation updated to v2.2 with comprehensive GOV.UK references

---

### Phase 3: Standardization (P1)

#### PR 12: Standardize Breadcrumb Usage ⏳ IN PROGRESS 2026-01-30

**Files:** ~100 PHP files in `views/civicone/`
**Risk:** MEDIUM
**Status:** ⏳ IN PROGRESS (11/103 files converted)

**Files Converted:**

- `views/civicone/auth/forgot_password.php`
- `views/civicone/auth/reset_password.php`
- `views/civicone/dashboard.php`
- `views/civicone/events/index.php`
- `views/civicone/groups/index.php`
- `views/civicone/help/index.php`
- `views/civicone/home.php`
- `views/civicone/listings/index.php`
- `views/civicone/members/index.php`
- `views/civicone/profile/show.php`
- `views/civicone/wallet/index.php`

**Pattern Established:**

```php
require_once __DIR__ . '/../components/govuk/breadcrumbs.php';
// ...
<?= civicone_govuk_breadcrumbs([
    'items' => [
        ['text' => 'Home', 'href' => $basePath],
        ['text' => 'Parent Page', 'href' => $basePath . '/parent'],
        ['text' => 'Current Page']  // No href = current page
    ],
    'class' => 'govuk-!-margin-bottom-6'  // Optional margin class
]) ?>
```

**Remaining:** ~92 files to convert (can be done incrementally)

---

#### PR 13: Exit This Page Component ✅ COMPLETED 2026-01-30

**Files:**
- `views/civicone/components/govuk/exit-this-page.php` (new)
- `httpdocs/assets/css/civicone-exit-this-page.css` (new)
- `httpdocs/assets/js/civicone-exit-this-page.js` (new)

**Risk:** LOW
**Status:** ✅ IMPLEMENTED

**Changes Made:**
- Created PHP component with configurable redirect URL and i18n strings
- Added `civicone_govuk_exit_this_page_with_skiplink()` variant for full implementation
- CSS provides fixed positioning, warning button styling, and loading animation
- JavaScript implements Shift×3 keyboard shortcut with visual/audio feedback
- Replaces browser history entry for privacy protection

---

### PR Priority Matrix

| Order | PR | Focus | Risk | Est. Files | Status |
|-------|------|------------------|--------|------------|--------------|
| 1 | PR 1 | CSS variable fix | HIGH | 1 | ✅ DONE |
| 2 | PR 2 | Create partials | LOW | 3 | ✅ DONE |
| 3 | PR 3 | Skip link | LOW | 2 | ✅ DONE |
| 4 | PR 4 | Footer a11y | LOW | 2 | ✅ VERIFIED |
| 5 | PR 5 | Button module | LOW | 1 | ✅ DONE |
| 6 | PR 6 | Auth errors | LOW | 3 | ✅ DONE |
| 7 | PR 7 | Caption size | LOW | 1 | ✅ DONE |
| 8 | PR 8 | aria-describedby | LOW | 2 | ✅ DONE |
| 9 | PR 9 | Password input | MEDIUM | 3 | ✅ DONE |
| 10 | PR 10 | Char count | MEDIUM | 3 | ✅ DONE |
| 11 | PR 11 | Service nav | MEDIUM | 3 | ✅ REVIEWED |
| 12 | PR 12 | Breadcrumbs | MEDIUM | ~100 | ⏳ 11% |
| 13 | PR 13 | Exit this page | LOW | 3 | ✅ DONE |

---

## Testing Requirements

### Manual Test Checklist (Per PR)

- [ ] **Visual regression**: Compare before/after screenshots
- [ ] **Keyboard navigation**: Tab through all interactive elements
- [ ] **Screen reader**: Test with NVDA or VoiceOver
- [ ] **Mobile responsive**: Test at 320px, 768px, 1024px widths
- [ ] **Modern theme check**: Switch to Modern, verify NO changes
- [ ] **Cross-browser**: Chrome, Firefox, Safari, Edge

### Automated Checks

```bash
# CSS linting
npm run lint:css

# Check for Modern contamination
grep -r "civicone" views/modern/ && echo "FAIL" || echo "PASS"

# Accessibility audit (if pa11y configured)
npx pa11y http://staging.timebank.local/hour-timebank/login
```

### Recommended pa11y Configuration

Create `.pa11yci.json`:
```json
{
  "defaults": {
    "standard": "WCAG2AA",
    "runners": ["axe"],
    "chromeLaunchConfig": {
      "args": ["--no-sandbox"]
    }
  },
  "urls": [
    "http://staging.timebank.local/hour-timebank/",
    "http://staging.timebank.local/hour-timebank/login",
    "http://staging.timebank.local/hour-timebank/register",
    "http://staging.timebank.local/hour-timebank/listings",
    "http://staging.timebank.local/hour-timebank/events",
    "http://staging.timebank.local/hour-timebank/groups",
    "http://staging.timebank.local/hour-timebank/members"
  ]
}
```

---

## Accessibility Compliance Checklist

### WCAG 2.1 AA Issues to Fix

| Issue | WCAG Criterion | Current State | Fix In PR |
|-------|----------------|---------------|-----------|
| Skip link not first element | 2.4.1 Bypass Blocks | May be after cookie banner | PR 3 |
| Error prefix not accessible | 1.3.1 Info & Relationships | Using `::before` | PR 2 |
| Footer missing heading | 1.3.1 Info & Relationships | No visually hidden h2 | PR 4 |
| Caption text too small | 1.4.4 Resize Text | 12px minimum | PR 7 |
| Form hints not linked | 3.3.2 Labels or Instructions | Missing aria-describedby | PR 8 |
| Custom dropdown keyboard trap | 2.1.2 No Keyboard Trap | More menu may trap focus | PR 9 |

### Post-Implementation Verification

After all PRs are merged, run full accessibility audit:

```bash
# Full site audit
npx pa11y-ci --config .pa11yci.json

# Generate report
npx pa11y http://staging.timebank.local/hour-timebank/ --reporter html > a11y-report.html
```

---

## References

- [GOV.UK Design System](https://design-system.service.gov.uk/)
- [GOV.UK Frontend GitHub](https://github.com/alphagov/govuk-frontend)
- [WCAG 2.1 Guidelines](https://www.w3.org/WAI/WCAG21/quickref/)
- [Project NEXUS CLAUDE.md](../CLAUDE.md)
- [CivicOne WCAG Source of Truth](./CIVICONE_WCAG21AA_SOURCE_OF_TRUTH.md)

---

## Document History

| Date | Author | Changes |
|------|--------|---------|
| 2026-01-30 | Claude | Initial audit and makeover plan created |
| 2026-01-30 | Claude | Implemented PR 1: Fixed CSS variable media query bug (11 instances) |
| 2026-01-30 | Claude | Verified PR 4: Footer already has visually hidden heading |
| 2026-01-30 | Claude | Reviewed PR 9: Service navigation documented as compliant extension |
| 2026-01-30 | Claude | Updated header.php documentation to v2.2 with GOV.UK references |
| 2026-01-30 | Claude | Completed PR 7: Fixed caption font size (16px min for WCAG) |
| 2026-01-30 | Claude | Completed PR 8: Added aria-describedby to auth form inputs |
| 2026-01-30 | Claude | Completed PR 9: Created password-input component with show/hide |
| 2026-01-30 | Claude | Completed PR 10: Created character-count component |
| 2026-01-30 | Claude | Completed PR 13: Created exit-this-page safety component |
| 2026-01-30 | Claude | Started PR 12: Converted 11 key files to use breadcrumbs component |
