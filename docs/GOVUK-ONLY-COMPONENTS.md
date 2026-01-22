# GOV.UK Frontend Components - CivicOne Implementation

**Status:** Active
**Source of Truth:** GOV.UK Frontend ONLY
**Created:** 2026-01-22
**Last Updated:** 2026-01-22

---

## ⚠️ CRITICAL RULE

**ONLY use components from GOV.UK Frontend (v5.14.0) for CivicOne.**

**DO NOT use:**
- ❌ ONS (Office for National Statistics) - Partially compliant only
- ❌ NHS.UK Frontend - Reference only
- ❌ MOJ/DfE/Other systems - Reference only

**Source Repository:** `govuk-frontend-ref/` (GOV.UK Frontend v5.14.0)

---

## Extracted Components (Updated 2026-01-22)

✅ **Complete:** All essential components extracted from GOV.UK Frontend v5.14.0

### 1. Hero / Page Header Components

**Files Created:**
- `httpdocs/assets/css/civicone-hero-govuk.css` - Clean implementation
- `docs/hero-govuk-examples.html` - Working examples
- `docs/govuk-components-extracted/` - Raw GOV.UK source files

**Components Included:**

#### A. Default Page Hero
- **Pattern:** GOV.UK Page Template heading structure
- **Use:** Standard pages (Members, Groups, Events)
- **Source:** GOV.UK Page Template
- **Classes:** `.civicone-hero`, `.civicone-heading-xl`, `.civicone-hero__lead`

```html
<div class="civicone-hero">
    <h1 class="civicone-heading-xl">Page Title</h1>
    <p class="civicone-hero__lead">Page description</p>
</div>
```

#### B. Banner Hero
- **Pattern:** GOV.UK Page Template + Start Button
- **Use:** Homepage, service hubs, onboarding
- **Source:** GOV.UK Start Button component
- **Classes:** `.civicone-hero--banner`, `.civicone-button--start`

```html
<div class="civicone-hero civicone-hero--banner">
    <h1 class="civicone-heading-xl">Welcome</h1>
    <p class="civicone-hero__lead">Description</p>
    <a href="/join" role="button" class="civicone-button civicone-button--start">
        Get started
        <svg class="civicone-button__start-icon">...</svg>
    </a>
</div>
```

#### C. Confirmation Panel
- **Pattern:** GOV.UK Panel Component (EXACT)
- **Use:** Success pages, form completions
- **Source:** `govuk-frontend-ref/packages/govuk-frontend/src/govuk/components/panel/`
- **Classes:** `.civicone-panel`, `.civicone-panel--confirmation`

```html
<div class="civicone-panel civicone-panel--confirmation">
    <h1 class="civicone-panel__title">Application complete</h1>
    <div class="civicone-panel__body">
        Your reference number<br>
        <strong>HDJ2123F</strong>
    </div>
</div>
```

---

### 2. Feedback Components

**Files Created:**
- `httpdocs/assets/css/civicone-govuk-feedback.css` - Notification banner, warning text, inset text

**Components Included:**

#### A. Notification Banner
- **Pattern:** GOV.UK Notification Banner Component
- **Use:** Success messages, important announcements
- **Source:** `govuk-frontend/components/notification-banner/`
- **Classes:** `.civicone-notification-banner`, `.civicone-notification-banner--success`

```html
<!-- Info/Important Banner -->
<div class="civicone-notification-banner" role="region" aria-labelledby="banner-title">
    <div class="civicone-notification-banner__header">
        <h2 class="civicone-notification-banner__title" id="banner-title">Important</h2>
    </div>
    <div class="civicone-notification-banner__content">
        <p class="civicone-notification-banner__heading">You have 7 days left to send your application.</p>
    </div>
</div>

<!-- Success Banner -->
<div class="civicone-notification-banner civicone-notification-banner--success" role="alert">
    <div class="civicone-notification-banner__header">
        <h2 class="civicone-notification-banner__title">Success</h2>
    </div>
    <div class="civicone-notification-banner__content">
        <h3 class="civicone-notification-banner__heading">Your post has been published</h3>
        <p>We've sent you a confirmation email.</p>
    </div>
</div>
```

#### B. Warning Text
- **Pattern:** GOV.UK Warning Text Component
- **Use:** Critical warnings with exclamation icon
- **Source:** `govuk-frontend/components/warning-text/`
- **Classes:** `.civicone-warning-text`, `.civicone-warning-text__icon`, `.civicone-warning-text__text`

```html
<div class="civicone-warning-text">
    <span class="civicone-warning-text__icon" aria-hidden="true">!</span>
    <strong class="civicone-warning-text__text">
        <span class="civicone-warning-text__assistive">Warning</span>
        You can be fined up to £5,000 if you do not register.
    </strong>
</div>
```

#### C. Inset Text
- **Pattern:** GOV.UK Inset Text Component
- **Use:** Highlighted content, important information blocks
- **Source:** `govuk-frontend/components/inset-text/`
- **Classes:** `.civicone-inset-text`

```html
<div class="civicone-inset-text">
    <p>It can take up to 8 weeks to register a lasting power of attorney if there are no mistakes in the application.</p>
</div>
```

---

### 3. Navigation Components

**Files Created:**
- `httpdocs/assets/css/civicone-govuk-navigation.css` - Pagination, breadcrumbs, back link

**Components Included:**

#### A. Pagination
- **Pattern:** GOV.UK Pagination Component
- **Use:** Page navigation for lists/search results
- **Source:** `govuk-frontend/components/pagination/`
- **Classes:** `.civicone-pagination`, `.civicone-pagination__item`, `.civicone-pagination--block`

```html
<nav class="civicone-pagination" aria-label="Pagination">
    <div class="civicone-pagination__prev">
        <a class="civicone-pagination__link" href="/page/1" rel="prev">
            <svg class="civicone-pagination__icon civicone-pagination__icon--prev">...</svg>
            <span class="civicone-pagination__link-title">Previous</span>
        </a>
    </div>
    <ul class="civicone-pagination__list">
        <li class="civicone-pagination__item">
            <a class="civicone-pagination__link" href="/page/1">1</a>
        </li>
        <li class="civicone-pagination__item civicone-pagination__item--current">
            <a class="civicone-pagination__link" href="/page/2" aria-current="page">2</a>
        </li>
        <li class="civicone-pagination__item">
            <a class="civicone-pagination__link" href="/page/3">3</a>
        </li>
    </ul>
    <div class="civicone-pagination__next">
        <a class="civicone-pagination__link" href="/page/3" rel="next">
            <span class="civicone-pagination__link-title">Next</span>
            <svg class="civicone-pagination__icon civicone-pagination__icon--next">...</svg>
        </a>
    </div>
</nav>
```

#### B. Breadcrumbs
- **Pattern:** GOV.UK Breadcrumbs Component
- **Use:** Hierarchical navigation path
- **Source:** `govuk-frontend/components/breadcrumbs/`
- **Classes:** `.civicone-breadcrumbs`, `.civicone-breadcrumbs--collapse-on-mobile`

```html
<nav class="civicone-breadcrumbs" aria-label="Breadcrumb">
    <ol class="civicone-breadcrumbs__list">
        <li class="civicone-breadcrumbs__list-item">
            <a class="civicone-breadcrumbs__link" href="/">Home</a>
        </li>
        <li class="civicone-breadcrumbs__list-item">
            <a class="civicone-breadcrumbs__link" href="/groups">Groups</a>
        </li>
        <li class="civicone-breadcrumbs__list-item">
            <a class="civicone-breadcrumbs__link" href="/groups/community">Community Groups</a>
        </li>
    </ol>
</nav>
```

#### C. Back Link
- **Pattern:** GOV.UK Back Link Component
- **Use:** Return to previous page
- **Source:** `govuk-frontend/components/back-link/`
- **Classes:** `.civicone-back-link`

```html
<a href="/previous-page" class="civicone-back-link">Back</a>
```

---

### 4. Content Components

**Files Created:**
- `httpdocs/assets/css/civicone-govuk-content.css` - Details (accordion), summary list

**Components Included:**

#### A. Details (Accordion)
- **Pattern:** GOV.UK Details Component
- **Use:** Expandable/collapsible content sections
- **Source:** `govuk-frontend/components/details/`
- **Classes:** `.civicone-details`, `.civicone-details__summary`, `.civicone-details__text`

```html
<details class="civicone-details">
    <summary class="civicone-details__summary">
        <span class="civicone-details__summary-text">Help with nationality</span>
    </summary>
    <div class="civicone-details__text">
        <p>We need to know your nationality so we can work out which elections you're entitled to vote in.</p>
        <p>If you cannot provide your nationality, you'll have to send copies of identity documents through the post.</p>
    </div>
</details>
```

#### B. Summary List
- **Pattern:** GOV.UK Summary List Component
- **Use:** Key-value pairs, metadata display
- **Source:** `govuk-frontend/components/summary-list/`
- **Classes:** `.civicone-summary-list`, `.civicone-summary-list__row`, `.civicone-summary-list__key`, `.civicone-summary-list__value`, `.civicone-summary-list__actions`

```html
<dl class="civicone-summary-list">
    <div class="civicone-summary-list__row">
        <dt class="civicone-summary-list__key">Name</dt>
        <dd class="civicone-summary-list__value">Sarah Philips</dd>
        <dd class="civicone-summary-list__actions">
            <a class="civicone-link" href="/change-name">Change<span class="civicone-visually-hidden"> name</span></a>
        </dd>
    </div>
    <div class="civicone-summary-list__row">
        <dt class="civicone-summary-list__key">Date of birth</dt>
        <dd class="civicone-summary-list__value">5 January 1978</dd>
        <dd class="civicone-summary-list__actions">
            <a class="civicone-link" href="/change-dob">Change<span class="civicone-visually-hidden"> date of birth</span></a>
        </dd>
    </div>
</dl>
```

#### C. Summary Card
- **Pattern:** GOV.UK Summary Card (wrapper for summary list)
- **Use:** Grouped summary information with title and actions
- **Classes:** `.civicone-summary-card`, `.civicone-summary-card__title`, `.civicone-summary-card__actions`

```html
<div class="civicone-summary-card">
    <div class="civicone-summary-card__title-wrapper">
        <h2 class="civicone-summary-card__title">University of Bristol</h2>
        <ul class="civicone-summary-card__actions">
            <li class="civicone-summary-card__action">
                <a class="civicone-link" href="/delete">Delete choice</a>
            </li>
        </ul>
    </div>
    <div class="civicone-summary-card__content">
        <dl class="civicone-summary-list">
            <div class="civicone-summary-list__row">
                <dt class="civicone-summary-list__key">Course</dt>
                <dd class="civicone-summary-list__value">English (3DMD)</dd>
            </div>
        </dl>
    </div>
</div>
```

---

### 5. Table & Tabs Components (v2.1 - 2026-01-22)

**Files Created:**
- `httpdocs/assets/css/civicone-govuk-content.css` (updated - added Table)
- `httpdocs/assets/css/civicone-govuk-tabs.css` (new - Tabs component)

**Components Included:**

#### A. Table
- **Pattern:** GOV.UK Table Component
- **Use:** Data tables for member/listing directories
- **Source:** `govuk-frontend/components/table/`
- **Classes:** `.civicone-table`, `.civicone-table__header`, `.civicone-table__cell`, `.civicone-table__caption`

```html
<table class="civicone-table">
    <caption class="civicone-table__caption--m">Members directory</caption>
    <thead>
        <tr>
            <th scope="col" class="civicone-table__header">Name</th>
            <th scope="col" class="civicone-table__header">Location</th>
            <th scope="col" class="civicone-table__header--numeric">Credits</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="civicone-table__cell">John Smith</td>
            <td class="civicone-table__cell">London</td>
            <td class="civicone-table__cell--numeric">120</td>
        </tr>
    </tbody>
</table>
```

#### B. Tabs
- **Pattern:** GOV.UK Tabs Component
- **Use:** Tabbed interface for organizing content
- **Source:** `govuk-frontend/components/tabs/`
- **Classes:** `.civicone-tabs`, `.civicone-tabs__list`, `.civicone-tabs__tab`, `.civicone-tabs__panel`

```html
<div class="civicone-tabs js-enabled">
    <h2 class="civicone-tabs__title">Contents</h2>
    <ul class="civicone-tabs__list">
        <li class="civicone-tabs__list-item civicone-tabs__list-item--selected">
            <a class="civicone-tabs__tab" href="#active">
                Active Members
            </a>
        </li>
        <li class="civicone-tabs__list-item">
            <a class="civicone-tabs__tab" href="#all">
                All Members
            </a>
        </li>
    </ul>
    <div class="civicone-tabs__panel" id="active">
        <h2>Active Members</h2>
        <!-- Active members list -->
    </div>
    <div class="civicone-tabs__panel civicone-tabs__panel--hidden" id="all">
        <h2>All Members</h2>
        <!-- All members list -->
    </div>
</div>
```

**Features:**
- ✅ Progressive enhancement (works without JS)
- ✅ Mobile responsive (vertical list on small screens)
- ✅ Keyboard accessible
- ✅ Print friendly

---

## GOV.UK Source Files

### Extracted from govuk-frontend-ref/

**Panel Component:**
- Template: `packages/govuk-frontend/src/govuk/components/panel/template.njk`
- Styles: `packages/govuk-frontend/src/govuk/components/panel/_index.scss`

**Button Component:**
- Template: `packages/govuk-frontend/src/govuk/components/button/template.njk`
- Styles: `packages/govuk-frontend/src/govuk/components/button/_index.scss`
- JavaScript: `packages/govuk-frontend/src/govuk/components/button/button.mjs`

**Typography:**
- Core: `packages/govuk-frontend/src/govuk/core/_typography.scss`

---

## Compliance Verification

### GOV.UK Frontend v5.14.0

✅ **WCAG 2.2 AA Compliant** (Fully)
- Continuously tested
- Production-ready
- Used by millions of UK gov users
- Gold standard for accessibility

**References:**
- [GOV.UK Design System](https://design-system.service.gov.uk/)
- [GOV.UK Panel Component](https://design-system.service.gov.uk/components/panel/)
- [GOV.UK Page Template](https://design-system.service.gov.uk/styles/page-template/)
- [WCAG 2.2 Updates](https://accessibility.blog.gov.uk/2024/01/11/get-to-wcag-2-2-faster-with-the-gov-uk-design-system/)

### Why NOT ONS/NHS/Others

**ONS Design System:**
- ❌ Only "partially compliant" with WCAG 2.1 AA
- ❌ Known heading level issues (fails 1.3.1)
- ❌ Hidden focusable elements (fails 4.1.2)
- ❌ Last tested: June 2022 (outdated)

**Source:** [ONS Accessibility Statement](https://service-manual.ons.gov.uk/accessibility-statement)

---

## Future Components to Extract

Additional components available in GOV.UK Frontend:

1. **Forms** (Already partially extracted)
   - Checkboxes component
   - Radios component
   - Select component
   - File upload component (already extracted)
   - Character count component

2. **Tables & Data**
   - Table component
   - Tag component (status badges)

3. **Advanced Patterns**
   - Tabs component
   - Accordion component (multi-section details)
   - Task list component

---

## Usage in CivicOne Pages

### Load Components in Header

Add these lines to `views/layouts/civicone/partials/assets-css.php`:

```php
<!-- GOV.UK Feedback Components (NEW 2026-01-22) -->
<link rel="stylesheet" href="/assets/css/civicone-govuk-feedback.min.css?v=<?= $cssVersion ?>">

<!-- GOV.UK Navigation Components (NEW 2026-01-22) -->
<link rel="stylesheet" href="/assets/css/civicone-govuk-navigation.min.css?v=<?= $cssVersion ?>">

<!-- GOV.UK Content Components (NEW 2026-01-22) -->
<link rel="stylesheet" href="/assets/css/civicone-govuk-content.min.css?v=<?= $cssVersion ?>">
```

### Replace Toast Notifications

**Before (Custom Toast):**
```javascript
showToast('Your post has been published!');
```

**After (GOV.UK Notification Banner):**
```html
<div class="civicone-notification-banner civicone-notification-banner--success" role="alert">
    <div class="civicone-notification-banner__header">
        <h2 class="civicone-notification-banner__title">Success</h2>
    </div>
    <div class="civicone-notification-banner__content">
        <p class="civicone-notification-banner__heading">Your post has been published</p>
    </div>
</div>
```

---

## Testing

**View examples:**
```bash
# Open in browser
start docs/hero-govuk-examples.html
```

**Verify compliance:**
- ✅ Keyboard navigation (Tab through all focusable elements)
- ✅ Focus visible (yellow #ffdd00 GOV.UK focus state)
- ✅ Screen reader (NVDA/JAWS announces correctly)
- ✅ Zoom to 200% (no horizontal scroll)
- ✅ Print styles (confirmation panel has border, not background)

---

## Document History

| Version | Date | Changes |
|---------|------|---------|
| 2.1.0 | 2026-01-22 | **DIRECTORY FEATURES** - Added Table (data tables) and Tabs (tabbed interface) components for members/listings directories. Total: 37 components. |
| 2.0.0 | 2026-01-22 | **COMPLETE EXTRACTION** - Added all 8 essential components: Notification Banner, Warning Text, Inset Text, Pagination, Breadcrumbs, Back Link, Details, Summary List. Total: 35 components. |
| 1.0.0 | 2026-01-22 | Initial extraction - Hero components from GOV.UK Frontend only. Removed ONS/NHS references. |
