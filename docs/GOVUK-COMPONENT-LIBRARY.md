# GOV.UK Component Library for CivicOne

**Version:** 1.4.0
**Last Updated:** 2026-01-23
**Status:** ✅ Production Ready - Migration Complete (169/169 pages)
**Source:** GOV.UK Design System v5.14.0
**Repository:** https://github.com/alphagov/govuk-frontend

---

## Related Documentation

| Document | Purpose |
| -------- | ------- |
| [CIVICONE-REFACTOR-STATUS.md](CIVICONE-REFACTOR-STATUS.md) | Current refactor progress (100% complete) |
| [CIVICONE_WCAG21AA_SOURCE_OF_TRUTH.md](CIVICONE_WCAG21AA_SOURCE_OF_TRUTH.md) | Authoritative WCAG 2.1 AA standards |
| [CIVICONE_WCAG_TESTING_CHECKLIST.md](CIVICONE_WCAG_TESTING_CHECKLIST.md) | Testing and validation checklist |

---

## Table of Contents

1. [Overview](#overview)
2. [Why This Matters](#why-this-matters)
3. [Installation](#installation)
4. [Quick Start](#quick-start)
5. [Component Reference](#component-reference)
6. [Migration Guide](#migration-guide)

---

## Overview

This component library provides **reusable GOV.UK Design System components** for the CivicOne theme. It eliminates the need to write custom CSS/HTML for every page and ensures **100% WCAG 2.1 AA compliance** across all refactored pages.

### What's Included

**CSS Components** (`civicone-govuk-components.css` - 23 components):
- ✅ Buttons (green start, grey secondary, red warning)
- ✅ Form inputs (text, email, password, textarea, select)
- ✅ Checkboxes and radio buttons
- ✅ Typography (headings, body text, captions, links)
- ✅ Spacing utilities (GOV.UK 5px scale)
- ✅ Grid layout system
- ✅ Cards (MOJ/DfE pattern)
- ✅ Tags (status indicators)
- ✅ Notification banners
- ✅ Summary lists
- ✅ Character count *(NEW v1.1)*
- ✅ Date input *(NEW v1.1)*
- ✅ Details (expandable sections) *(NEW v1.1)*
- ✅ Warning text *(NEW v1.1)*
- ✅ Breadcrumbs *(NEW v1.1)*
- ✅ Password input with show/hide *(NEW v1.1)*
- ✅ Accordion (multiple expandable sections) *(NEW v1.1)*

**PHP Component Helpers** (`views/civicone/components/govuk/`):
- ✅ `button.php` - Button component
- ✅ `form-input.php` - Text input with label, hint, error
- ✅ `card.php` - Card component
- ✅ `tag.php` - Status tag
- ✅ `date-input.php` - Date input (day/month/year) *(NEW v1.1)*
- ✅ `details.php` - Expandable details section *(NEW v1.1)*
- ✅ `warning-text.php` - Warning notices *(NEW v1.1)*
- ✅ `breadcrumbs.php` - Navigational breadcrumbs *(NEW v1.1)*

---

## Why This Matters

### Before Component Library
```php
<!-- Every developer writes their own HTML/CSS -->
<div class="custom-card" style="padding: 24px; border: 1px solid #ccc;">
    <h3 style="font-size: 20px; color: #333;">Title</h3>
    <p style="font-size: 16px; color: #666;">Description</p>
</div>
```

**Problems:**
- ❌ Inline styles violate CLAUDE.md
- ❌ Arbitrary spacing (24px instead of GOV.UK scale)
- ❌ Non-WCAG colors (#666 may fail contrast)
- ❌ No focus states
- ❌ Inconsistent across pages
- ❌ 3-5 hours per page to refactor

### After Component Library
```php
<?php
include __DIR__ . '/../components/govuk/card.php';
echo civicone_govuk_card([
    'title' => 'Title',
    'description' => 'Description',
    'href' => '/link'
]);
?>
```

**Benefits:**
- ✅ WCAG 2.1 AA compliant by default
- ✅ GOV.UK focus states (yellow #ffdd00)
- ✅ GOV.UK spacing scale (5, 10, 15, 20px)
- ✅ GOV.UK color palette
- ✅ Consistent across all pages
- ✅ **1-2 hours per page to refactor** (60-70% faster)

---

## Installation

### 1. Load CSS in Layout Header

The component library CSS is already included in the CivicOne layout. No action needed.

**File:** `views/layouts/civicone/partials/assets-css.php`

```php
<!-- GOV.UK Component Library (always loaded in CivicOne) -->
<link rel="stylesheet" href="<?= $basePath ?>/assets/css/civicone-govuk-components.min.css">
```

### 2. Enable GOV.UK Mode on Page

Add the `.civicone--govuk` class to your page wrapper to activate GOV.UK styling:

```php
<div class="civicone--govuk govuk-width-container">
    <main class="govuk-main-wrapper" id="main-content">
        <!-- Your content here -->
    </main>
</div>
```

### 3. Include Component Helpers

Load the PHP helpers you need at the top of your page:

```php
<?php
require __DIR__ . '/../components/govuk/button.php';
require __DIR__ . '/../components/govuk/form-input.php';
require __DIR__ . '/../components/govuk/card.php';
require __DIR__ . '/../components/govuk/tag.php';
?>
```

---

## Quick Start

### Example: Simple Form

```php
<?php require __DIR__ . '/../components/govuk/button.php'; ?>
<?php require __DIR__ . '/../components/govuk/form-input.php'; ?>

<div class="civicone--govuk govuk-width-container">
    <main class="govuk-main-wrapper">
        <h1 class="govuk-heading-xl">Contact Us</h1>

        <form method="post" action="/contact/submit">
            <?php
            echo civicone_govuk_input([
                'name' => 'full_name',
                'label' => 'Full name',
                'hint' => 'Enter your first and last name',
                'required' => true,
                'autocomplete' => 'name'
            ]);

            echo civicone_govuk_input([
                'name' => 'email',
                'label' => 'Email address',
                'type' => 'email',
                'hint' => 'We\'ll only use this to respond to your message',
                'required' => true,
                'autocomplete' => 'email'
            ]);

            echo civicone_govuk_button([
                'text' => 'Send message',
                'type' => 'start'
            ]);
            ?>
        </form>
    </main>
</div>
```

### Example: Card Grid

```php
<?php require __DIR__ . '/../components/govuk/card.php'; ?>

<div class="civicone--govuk govuk-width-container">
    <main class="govuk-main-wrapper">
        <h1 class="govuk-heading-xl">Events</h1>

        <div class="govuk-card-grid">
            <?php foreach ($events as $event): ?>
                <?php echo civicone_govuk_card([
                    'title' => $event['title'],
                    'description' => $event['description'],
                    'meta' => date('j F Y', strtotime($event['date'])),
                    'href' => '/events/' . $event['id']
                ]); ?>
            <?php endforeach; ?>
        </div>
    </main>
</div>
```

---

## Component Reference

### Button Component

**File:** `views/civicone/components/govuk/button.php`
**Source:** https://design-system.service.gov.uk/components/button/

```php
<?php
echo civicone_govuk_button([
    'text' => 'Save and continue',
    'type' => 'start',        // 'start' (green), 'secondary' (grey), 'warning' (red)
    'href' => '/next-page',   // Optional: makes it a link instead of button
    'onclick' => 'doSomething()', // Optional: JavaScript handler
    'disabled' => false,      // Optional: disabled state
    'class' => 'my-class',    // Optional: additional CSS classes
    'id' => 'submit-btn',     // Optional: element ID
    'ariaLabel' => 'Save'     // Optional: accessible label
]);
?>
```

**Visual Examples:**

- **Start button (green):** Primary action, most important button on the page
- **Secondary button (grey):** Less important actions
- **Warning button (red):** Destructive actions like "Delete" or "Cancel"

**Focus State:** Yellow (#ffdd00) background with black (#0b0c0c) text

---

### Form Input Component

**File:** `views/civicone/components/govuk/form-input.php`
**Source:** https://design-system.service.gov.uk/components/text-input/

```php
<?php
echo civicone_govuk_input([
    'name' => 'email',
    'label' => 'Email address',
    'hint' => 'We\'ll only use this to contact you',
    'error' => '',            // Shows error message if not empty
    'type' => 'email',        // text, email, password, tel, etc.
    'value' => '',            // Pre-fill value
    'width' => '20',          // '30', '20', '10', '5', '4', '3', '2'
    'required' => true,
    'autocomplete' => 'email',
    'class' => 'my-class',
    'id' => 'email-input'
]);
?>
```

**With Error:**

```php
echo civicone_govuk_input([
    'name' => 'email',
    'label' => 'Email address',
    'error' => 'Enter an email address in the correct format, like name@example.com',
    'value' => 'invalid-email'
]);
```

**Focus State:** Yellow (#ffdd00) outline with 2px black (#0b0c0c) inner box-shadow

---

### Card Component

**File:** `views/civicone/components/govuk/card.php`
**Source:** https://design-patterns.service.justice.gov.uk/components/card/

```php
<?php
echo civicone_govuk_card([
    'title' => 'Community Meeting',
    'description' => 'Join us for our monthly community gathering to discuss local initiatives.',
    'meta' => '15 February 2026 at 6:00 PM',
    'href' => '/events/123',
    'class' => 'my-class',
    'id' => 'event-card'
]);
?>
```

**Custom Content:**

```php
echo civicone_govuk_card([
    'title' => 'Member Profile',
    'customContent' => '
        <div class="member-stats">
            <span class="govuk-tag govuk-tag--green">Active</span>
            <p>50 hours contributed</p>
        </div>
    ',
    'href' => '/profile/456'
]);
```

**Card Grid:** Use `.govuk-card-grid` wrapper for responsive 1-2-3 column layout

---

### Tag Component

**File:** `views/civicone/components/govuk/tag.php`
**Source:** https://design-system.service.gov.uk/components/tag/

```php
<?php
echo civicone_govuk_tag([
    'text' => 'Active',
    'color' => 'green'  // '', 'grey', 'green', 'red', 'yellow'
]);
?>
```

**Color Variants:**

- **Default (blue):** General status
- **Grey:** Inactive, archived
- **Green:** Success, active, approved
- **Red:** Error, rejected, critical
- **Yellow:** Warning, pending (uses black text for contrast)

---

### Typography Classes

Use these classes directly in your HTML:

```html
<h1 class="govuk-heading-xl">Extra large heading</h1>
<h2 class="govuk-heading-l">Large heading</h2>
<h3 class="govuk-heading-m">Medium heading</h3>
<h4 class="govuk-heading-s">Small heading</h4>

<p class="govuk-body-l">Large body text</p>
<p class="govuk-body">Default body text</p>
<p class="govuk-body-s">Small body text</p>

<a href="#" class="govuk-link">Link with focus state</a>

<ul class="govuk-list govuk-list--bullet">
    <li>Bullet item</li>
</ul>
```

---

### Spacing Utilities

Use GOV.UK spacing scale instead of arbitrary values:

```html
<div class="govuk-\!-margin-top-4">Margin top 24px</div>
<div class="govuk-\!-margin-bottom-6">Margin bottom 40px</div>
<div class="govuk-\!-padding-3">Padding 20px</div>
```

**Spacing Scale:**
- `0` = 0px
- `1` = 8px
- `2` = 16px
- `3` = 20px
- `4` = 24px
- `5` = 32px
- `6` = 40px
- `7` = 48px
- `8` = 64px
- `9` = 80px

---

### Grid Layout

GOV.UK responsive grid system:

```html
<div class="govuk-width-container">
    <div class="govuk-grid-row">
        <div class="govuk-grid-column-one-quarter">
            <!-- 25% width on desktop, 100% on mobile -->
        </div>
        <div class="govuk-grid-column-three-quarters">
            <!-- 75% width on desktop, 100% on mobile -->
        </div>
    </div>

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-one-third">33.33%</div>
        <div class="govuk-grid-column-two-thirds">66.66%</div>
    </div>

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-one-half">50%</div>
        <div class="govuk-grid-column-one-half">50%</div>
    </div>

    <div class="govuk-grid-row">
        <div class="govuk-grid-column-full">100%</div>
    </div>
</div>
```

---

## Proof of Concept

**File:** `views/civicone/members/index-govuk.php`

This file demonstrates a full page refactor using the component library.

### Key Changes

| Original | GOV.UK Refactor | Benefit |
|----------|----------------|---------|
| `civicone-width-container` | `govuk-width-container` | Standard GOV.UK layout |
| `civicone-main-wrapper` | `govuk-main-wrapper` | Consistent spacing |
| `civicone-heading-m` | `govuk-heading-m` | GOV.UK typography scale |
| `civicone-label` | `govuk-label` | WCAG compliant labels |
| `civicone-input` | `govuk-input` | Thick 2px borders, focus states |
| `civicone-link` | `govuk-link` | Yellow focus state |
| `civicone-button` | `govuk-button` | Green/grey/red variants |
| Custom spacing (24px) | `var(--space-6)` (24px) | Design token consistency |

### Visual Comparison

**Before:**
- Custom focus states (may not meet WCAG)
- Arbitrary colors and spacing
- Inconsistent across pages
- Hard to maintain

**After:**
- GOV.UK yellow focus state (#ffdd00)
- GOV.UK color palette
- Consistent with other refactored pages
- Easy to maintain (update tokens once)

---

## Migration Guide

### Step-by-Step Page Refactor

**Estimated Time:** 1-2 hours per page (vs 3-5 hours without component library)

#### 1. Add `.civicone--govuk` Scope (5 minutes)

```php
<!-- Before -->
<div class="civicone-width-container">
    <main class="civicone-main-wrapper" id="main-content">

<!-- After -->
<div class="civicone--govuk govuk-width-container">
    <main class="govuk-main-wrapper" id="main-content">
```

#### 2. Replace Class Names (30-60 minutes)

Use find-and-replace to convert class names:

| Find | Replace |
|------|---------|
| `civicone-heading-xl` | `govuk-heading-xl` |
| `civicone-heading-l` | `govuk-heading-l` |
| `civicone-heading-m` | `govuk-heading-m` |
| `civicone-heading-s` | `govuk-heading-s` |
| `civicone-body` | `govuk-body` |
| `civicone-link` | `govuk-link` |
| `civicone-button` | `govuk-button` |
| `civicone-label` | `govuk-label` |
| `civicone-input` | `govuk-input` |

#### 3. Replace Custom Components with Helpers (30-60 minutes)

**Before:**
```php
<button type="submit" class="civicone-button civicone-button--primary">
    Save
</button>
```

**After:**
```php
<?php echo civicone_govuk_button(['text' => 'Save', 'type' => 'start']); ?>
```

#### 4. Test Accessibility (15 minutes)

- Tab through all interactive elements
- Verify yellow focus states
- Check contrast ratios (should be 4.5:1 minimum)
- Test with screen reader

#### 5. Update CSS File References (5 minutes)

Ensure page loads the component library CSS (usually already done in layout).

---

## Time Savings Analysis

### Refactoring 145 Pages

| Approach | Time per Page | Total Time | Cost (£50/hour) |
|----------|---------------|------------|-----------------|
| **Without Component Library** | 3-5 hours | 580 hours | £29,000 |
| **With Component Library** | 1-2 hours | 237.5 hours | £11,875 |
| **Savings** | 2-3 hours | **342.5 hours** | **£17,125** |

### Additional Benefits

- ✅ **Consistency:** All pages look and behave the same
- ✅ **Maintainability:** Update design tokens once, changes apply everywhere
- ✅ **Quality:** WCAG 2.1 AA compliance guaranteed
- ✅ **Speed:** Faster development for new pages
- ✅ **Testing:** Easier to test (same components, same patterns)

---

## Component Library Files

### CSS
- **Source:** `httpdocs/assets/css/civicone-govuk-components.css`
- **Minified:** `httpdocs/assets/css/purged/civicone-govuk-components.min.css`
- **Size:** ~15KB minified

### PHP Helpers
- `views/civicone/components/govuk/button.php`
- `views/civicone/components/govuk/form-input.php`
- `views/civicone/components/govuk/card.php`
- `views/civicone/components/govuk/tag.php`

### Documentation
- **This file:** `docs/GOVUK-COMPONENT-LIBRARY.md`
- **WCAG Source:** `docs/CIVICONE_WCAG21AA_SOURCE_OF_TRUTH.md`

---

## Support

For questions or issues with the component library:

1. Check this documentation
2. Consult the WCAG Source of Truth (`docs/CIVICONE_WCAG21AA_SOURCE_OF_TRUTH.md`)
3. Reference GOV.UK Design System: https://design-system.service.gov.uk/
