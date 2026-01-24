# GOV.UK Design System Polish Log

## Project: Civicone Theme Refactoring
**Target**: GOV.UK Design System compliance (WCAG 2.1 AA)
**Source**: alphagov/govuk-frontend v5.14.0

---

## 2026-01-24: Session 1 - Foundation & Priority Pages

### Completed Tasks

#### 1. GOV.UK Frontend v5.14.0 Integration
- [x] Downloaded `govuk-frontend.min.css` (132KB) to `/httpdocs/assets/govuk-frontend-5.14.0/`
- [x] Downloaded `govuk-frontend.min.js` (48KB) to `/httpdocs/assets/govuk-frontend-5.14.0/`
- [x] Added CSS loading to `views/layouts/civicone/partials/assets-css.php`
- [x] Added JS loading + `GOVUKFrontend.initAll()` to `views/layouts/civicone/partials/assets-js-footer.php`

#### 2. Layout Wrapper Refactoring
- [x] Updated `views/layouts/civicone/partials/main-open.php`:
  - Added `govuk-width-container` and `govuk-template` wrapper
  - Changed `<main>` to use `govuk-main-wrapper` class with `role="main"`
- [x] Updated `views/layouts/civicone/partials/main-close.php`:
  - Properly closes both `</main>` and `</div>` for width-container

#### 3. Page Refactoring (Phase 1)
All pages refactored to use official GOV.UK Frontend v5.14.0 classes:

| Page | Status | Changes |
|------|--------|---------|
| `members/index.php` | ✅ Complete | GOV.UK breadcrumbs, tabs, grid, pagination |
| `groups/index.php` | ✅ Complete | GOV.UK checkboxes, grid layout, filter panel |
| `volunteering/index.php` | ✅ Complete | GOV.UK form groups, tags, inset text |
| `listings/index.php` | ✅ Complete | GOV.UK checkboxes, tags, pagination |

### Class Migrations Applied
- `civicone-width-container` → `govuk-width-container`
- `civicone-main-wrapper` → `govuk-main-wrapper`
- `civicone-breadcrumbs*` → `govuk-breadcrumbs*`
- `civicone-heading-*` → `govuk-heading-*`
- `civicone-body*` → `govuk-body*`
- `civicone-link` → `govuk-link`
- `civicone-button*` → `govuk-button*`
- `civicone-grid-row` → `govuk-grid-row`
- `civicone-grid-column-*` → `govuk-grid-column-*`
- `civicone-checkbox*` → `govuk-checkboxes*`
- `civicone-pagination*` → `govuk-pagination*`
- `civicone-filter-*` → GOV.UK form-group pattern
- `civicone-tag*` → `govuk-tag*`
- `civicone-empty-state` → `govuk-inset-text`

### Pending Tasks
- [ ] `dashboard.php`
- [ ] `profile/show.php`, `profile/edit.php`
- [ ] `settings/index.php`
- [ ] `listings/show.php`, `listings/create.php`, `listings/edit.php`
- [ ] Federation pages (~25 files in views/civicone/federation/)
- [ ] Base federation views (~25 files in views/federation/)

---

## Style Migration Guide

### Page Template Structure (GOV.UK Standard)
```html
<!-- Layout provides: -->
<div class="govuk-width-container govuk-template">
    <main class="govuk-main-wrapper" id="main-content" role="main">

<!-- Page content: -->
<nav class="govuk-breadcrumbs govuk-!-margin-bottom-6" aria-label="Breadcrumb">
    <ol class="govuk-breadcrumbs__list">
        <li class="govuk-breadcrumbs__list-item">
            <a class="govuk-breadcrumbs__link" href="/">Home</a>
        </li>
        <li class="govuk-breadcrumbs__list-item" aria-current="page">Page Name</li>
    </ol>
</nav>

<div class="govuk-grid-row">
    <div class="govuk-grid-column-two-thirds">
        <h1 class="govuk-heading-xl">Page Title</h1>
        <p class="govuk-body-l">Lead paragraph.</p>
    </div>
</div>

<!-- Layout closes: -->
    </main>
</div>
```

### Directory Page Pattern (1/3 Filters + 2/3 Results)
```html
<div class="govuk-grid-row">
    <!-- Filters (1/3) -->
    <div class="govuk-grid-column-one-third">
        <div class="govuk-!-padding-4" style="background: #f3f2f1;">
            <h2 class="govuk-heading-m">Filter [items]</h2>
            <form>
                <div class="govuk-form-group">
                    <label class="govuk-label" for="search">Search</label>
                    <input type="text" class="govuk-input" id="search">
                </div>
                <div class="govuk-form-group">
                    <fieldset class="govuk-fieldset">
                        <legend class="govuk-fieldset__legend">Type</legend>
                        <div class="govuk-checkboxes govuk-checkboxes--small">
                            <div class="govuk-checkboxes__item">
                                <input type="checkbox" class="govuk-checkboxes__input">
                                <label class="govuk-label govuk-checkboxes__label">Option</label>
                            </div>
                        </div>
                    </fieldset>
                </div>
                <button type="submit" class="govuk-button govuk-button--secondary">Apply</button>
            </form>
        </div>
    </div>

    <!-- Results (2/3) -->
    <div class="govuk-grid-column-two-thirds">
        <p class="govuk-body">Showing <strong>N</strong> results</p>
        <ul class="govuk-list" role="list">
            <li class="govuk-!-margin-bottom-4 govuk-!-padding-bottom-4" style="border-bottom: 1px solid #b1b4b6;">
                <!-- Item content -->
            </li>
        </ul>
    </div>
</div>
```

---

## Notes
- All changes are Civicone-only (Modern theme unaffected)
- GOV.UK Frontend CSS/JS only loads on Civicone pages
- Layout provides outer wrappers; pages provide content with govuk-grid-row
- Use `govuk-!-margin-*` and `govuk-!-padding-*` for spacing
- Use `govuk-tag` variants for badges: `--green`, `--blue`, `--grey`
- Empty states use `govuk-inset-text` pattern
- Minimal inline styles - only for truly dynamic values or non-GOV.UK patterns
