# CivicOne Landing Page - GOV.UK Refactor Plan

**Created:** 2026-01-22
**Status:** Implementation Ready
**Source:** GOV.UK Frontend v5.14.0 (WCAG 2.2 AA Compliant)

---

## Overview

This document outlines the complete refactoring of the CivicOne landing page to use pure GOV.UK Frontend components for maximum accessibility and compliance.

---

## Current State Analysis

### Files Involved
1. `views/civicone/home.php` - Entry point with hero overrides
2. `views/civicone/feed/index.php` - Main feed content (900 lines)
3. `views/layouts/civicone/partials/assets-css.php` - CSS loading

### Current Components
- ✅ Hero section (custom enhanced version)
- ✅ Post composer
- ✅ Feed stream (posts, events, listings, polls, etc.)
- ❌ Custom toast notifications (JavaScript)
- ❌ No structured success/error feedback
- ❌ No pagination (infinite scroll only)
- ❌ No breadcrumbs on sub-pages

---

## Refactoring Strategy

### Phase 1: ✅ COMPLETED - Load GOV.UK Components

**File:** `views/layouts/civicone/partials/assets-css.php`

**Changes:**
```php
<!-- GOV.UK Feedback Components (NEW 2026-01-22) -->
<link rel="stylesheet" href="/assets/css/civicone-govuk-feedback.min.css?v=<?= $cssVersion ?>">

<!-- GOV.UK Navigation Components (NEW 2026-01-22) -->
<link rel="stylesheet" href="/assets/css/civicone-govuk-navigation.min.css?v=<?= $cssVersion ?>">

<!-- GOV.UK Content Components (NEW 2026-01-22) -->
<link rel="stylesheet" href="/assets/css/civicone-govuk-content.min.css?v=<?= $cssVersion ?>">
```

**Status:** ✅ Complete

---

### Phase 2: Replace Toast Notifications with Notification Banner

**Current:** JavaScript `showToast()` function
**Replace with:** GOV.UK Notification Banner component

#### Implementation

**1. Update Post Submission Success (feed/index.php line 292)**

**Before:**
```javascript
showToast('Your post has been published!');
```

**After:**
```php
$_SESSION['success_message'] = 'Your post has been published';
header("Location: " . $_SERVER['REQUEST_URI']);
exit;
```

**Display in home.php:**
```php
<?php if (!empty($_SESSION['success_message'])): ?>
<div class="civicone-notification-banner civicone-notification-banner--success" role="alert">
    <div class="civicone-notification-banner__header">
        <h2 class="civicone-notification-banner__title">Success</h2>
    </div>
    <div class="civicone-notification-banner__content">
        <p class="civicone-notification-banner__heading"><?= htmlspecialchars($_SESSION['success_message']) ?></p>
    </div>
</div>
<?php unset($_SESSION['success_message']); endif; ?>
```

#### Other Toast Replacements Needed

**File:** `views/civicone/feed/index.php`

Search for all `showToast()` calls and replace:

1. **Line ~571:** `showToast('Location feature coming soon!')`
   - Replace with info notification banner

2. **JavaScript AJAX Success/Error:**
   - Like/unlike success: Show success banner
   - Comment submitted: Show success banner
   - Errors: Show error notification banner

---

### Phase 3: Add Warning Text for Important Notices

**Use Cases:**
1. Account verification needed
2. Profile incomplete
3. Important policy updates
4. Content moderation notices

#### Implementation

**Location:** After hero, before feed
**Condition:** Check for warnings in session

```php
<?php if (!empty($_SESSION['warning_message'])): ?>
<div class="civicone-warning-text">
    <span class="civicone-warning-text__icon" aria-hidden="true">!</span>
    <strong class="civicone-warning-text__text">
        <span class="civicone-warning-text__assistive">Warning</span>
        <?= htmlspecialchars($_SESSION['warning_message']) ?>
    </strong>
</div>
<?php unset($_SESSION['warning_message']); endif; ?>
```

**Example Warnings:**
- "Your account email is not verified. Please check your inbox."
- "Your profile is incomplete. Complete it to unlock all features."
- "Community guidelines have been updated. Please review them."

---

### Phase 4: Add Inset Text for Highlighted Content

**Use Cases:**
1. First-time user tips
2. Community guidelines reminder
3. Feature announcements
4. Helpful information blocks

#### Implementation

**Example: Welcome Tips for New Users**

```php
<?php if (!empty($_SESSION['user_id']) && empty($_SESSION['seen_welcome_tips'])): ?>
<div class="civicone-inset-text">
    <p><strong>Welcome to CivicOne!</strong> Start by completing your profile, joining groups, and connecting with neighbors. Need help? Visit our <a href="/help" class="civicone-link">Help Centre</a>.</p>
</div>
<?php endif; ?>
```

**Example: Community Guidelines**

```php
<div class="civicone-inset-text">
    <p><strong>Community Guidelines:</strong> Be respectful, supportive, and inclusive. Your contributions help build a stronger community for everyone.</p>
</div>
```

---

### Phase 5: Add Pagination (Optional)

**Current:** Infinite scroll
**Option:** Add pagination as alternative

#### Implementation

**Location:** Bottom of feed stream
**Condition:** Only if total items > 20

```php
<?php if ($totalPages > 1): ?>
<nav class="civicone-pagination" aria-label="Feed pagination">
    <?php if ($currentPage > 1): ?>
    <div class="civicone-pagination__prev">
        <a class="civicone-pagination__link" href="/?page=<?= $currentPage - 1 ?>" rel="prev">
            <svg class="civicone-pagination__icon civicone-pagination__icon--prev" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">
                <path d="m6.5938-0.0078125-6.7266 6.7266 6.7441 6.4062 1.377-1.449-4.1856-3.9768h12.896v-2h-12.984l4.2931-4.293-1.414-1.414z"></path>
            </svg>
            <span class="civicone-pagination__link-title">Previous</span>
        </a>
    </div>
    <?php endif; ?>

    <ul class="civicone-pagination__list">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <li class="civicone-pagination__item<?= $i === $currentPage ? ' civicone-pagination__item--current' : '' ?>">
            <a class="civicone-pagination__link" href="/?page=<?= $i ?>"<?= $i === $currentPage ? ' aria-current="page"' : '' ?>><?= $i ?></a>
        </li>
        <?php endfor; ?>
    </ul>

    <?php if ($currentPage < $totalPages): ?>
    <div class="civicone-pagination__next">
        <a class="civicone-pagination__link" href="/?page=<?= $currentPage + 1 ?>" rel="next">
            <span class="civicone-pagination__link-title">Next</span>
            <svg class="civicone-pagination__icon civicone-pagination__icon--next" xmlns="http://www.w3.org/2000/svg" height="13" width="15" aria-hidden="true" focusable="false" viewBox="0 0 15 13">
                <path d="m8.107-0.0078125-1.4136 1.414 4.2926 4.293h-12.986v2h12.896l-4.1855 3.9766 1.377 1.4492 6.7441-6.4062-6.7246-6.7266z"></path>
            </svg>
        </a>
    </div>
    <?php endif; ?>
</nav>
<?php endif; ?>
```

---

### Phase 6: Add Summary Lists for Feed Item Metadata

**Use Case:** Display event/listing details in feed items

#### Implementation

**Example: Event Feed Item Metadata**

```php
<!-- Inside event feed item -->
<dl class="civicone-summary-list civicone-summary-list--no-border">
    <div class="civicone-summary-list__row">
        <dt class="civicone-summary-list__key">Date</dt>
        <dd class="civicone-summary-list__value"><?= date('l, j F Y', strtotime($item['event_date'])) ?></dd>
    </div>
    <div class="civicone-summary-list__row">
        <dt class="civicone-summary-list__key">Time</dt>
        <dd class="civicone-summary-list__value"><?= date('g:i A', strtotime($item['event_time'])) ?></dd>
    </div>
    <?php if (!empty($item['location'])): ?>
    <div class="civicone-summary-list__row">
        <dt class="civicone-summary-list__key">Location</dt>
        <dd class="civicone-summary-list__value"><?= htmlspecialchars($item['location']) ?></dd>
    </div>
    <?php endif; ?>
</dl>
```

**Example: Listing Feed Item Metadata**

```php
<dl class="civicone-summary-list civicone-summary-list--no-border">
    <div class="civicone-summary-list__row">
        <dt class="civicone-summary-list__key">Type</dt>
        <dd class="civicone-summary-list__value"><?= strtoupper($item['listing_type']) ?></dd>
    </div>
    <?php if (!empty($item['price'])): ?>
    <div class="civicone-summary-list__row">
        <dt class="civicone-summary-list__key">Price</dt>
        <dd class="civicone-summary-list__value">£<?= number_format($item['price'], 2) ?></dd>
    </div>
    <?php endif; ?>
    <?php if (!empty($item['condition'])): ?>
    <div class="civicone-summary-list__row">
        <dt class="civicone-summary-list__key">Condition</dt>
        <dd class="civicone-summary-list__value"><?= htmlspecialchars($item['condition']) ?></dd>
    </div>
    <?php endif; ?>
</dl>
```

---

### Phase 7: Add Details Component for Expandable Content

**Use Case:** Expandable help text, more information sections

#### Implementation

**Example: Community Guidelines in Composer**

```php
<details class="civicone-details">
    <summary class="civicone-details__summary">
        <span class="civicone-details__summary-text">Community guidelines</span>
    </summary>
    <div class="civicone-details__text">
        <p>Before posting, please remember:</p>
        <ul>
            <li>Be respectful and considerate</li>
            <li>No spam or self-promotion</li>
            <li>Keep content relevant to the community</li>
            <li>Report inappropriate content</li>
        </ul>
    </div>
</details>
```

**Example: Help with Post Types**

```php
<details class="civicone-details">
    <summary class="civicone-details__summary">
        <span class="civicone-details__summary-text">What can I post?</span>
    </summary>
    <div class="civicone-details__text">
        <p>You can share:</p>
        <ul>
            <li><strong>Updates:</strong> News, achievements, thoughts</li>
            <li><strong>Events:</strong> Community gatherings and activities</li>
            <li><strong>Listings:</strong> Items for sale, wanted, or free</li>
            <li><strong>Polls:</strong> Get opinions from the community</li>
        </ul>
    </div>
</details>
```

---

## File-by-File Changes

### 1. views/civicone/home.php

**Current:**
```php
// Override hero for homepage
$heroOverrides = [
    'variant' => 'banner',
    'title' => 'Welcome to Your Community',
    'lead' => 'Connect, collaborate, and make a difference in your local area.',
    'cta' => [
        'text' => 'Get started',
        'url' => '/join',
    ],
];

require __DIR__ . '/feed/index.php';
```

**New (Option 1: Minimal Changes):**
```php
// Add notification banners before hero
<?php if (!empty($_SESSION['success_message'])): ?>
    <!-- GOV.UK Success Banner -->
<?php endif; ?>

<?php if (!empty($_SESSION['warning_message'])): ?>
    <!-- GOV.UK Warning Text -->
<?php endif; ?>

// Keep existing hero and feed
```

**New (Option 2: Full GOV.UK):**
Use `views/civicone/home-govuk-enhanced.php` (already created)

---

### 2. views/civicone/feed/index.php

**Changes Needed:**

1. **Line 292:** Post submission success
   ```php
   // OLD: JavaScript toast
   // NEW: Session message + redirect
   $_SESSION['success_message'] = 'Your post has been published';
   header("Location: " . $_SERVER['REQUEST_URI']);
   exit;
   ```

2. **Line 571:** Location feature
   ```php
   // OLD: onclick="showToast('Location feature coming soon!')"
   // NEW: onclick="showFeatureNotice()"
   // Then add session message for next page load
   ```

3. **AJAX Responses:** Update JSON responses
   ```php
   // OLD: Return status only
   // NEW: Set session message for display on reload
   echo json_encode(['status' => 'success', 'reload' => true]);
   ```

---

### 3. httpdocs/assets/js/feed-interactions.js (Create New)

**Replace toast calls with proper feedback:**

```javascript
// Handle AJAX success
function handleSuccess(message) {
    // Create temporary notification banner
    const banner = document.createElement('div');
    banner.className = 'civicone-notification-banner civicone-notification-banner--success';
    banner.setAttribute('role', 'alert');
    banner.innerHTML = `
        <div class="civicone-notification-banner__header">
            <h2 class="civicone-notification-banner__title">Success</h2>
        </div>
        <div class="civicone-notification-banner__content">
            <p class="civicone-notification-banner__heading">${message}</p>
        </div>
    `;

    document.body.insertBefore(banner, document.body.firstChild);

    // Auto-remove after 5 seconds
    setTimeout(() => banner.remove(), 5000);
}
```

---

## Testing Checklist

### Functional Testing
- [ ] Success banner appears after posting
- [ ] Warning text displays for important notices
- [ ] Inset text shows for first-time users
- [ ] Details component expands/collapses
- [ ] Summary lists display metadata correctly
- [ ] Pagination works (if implemented)

### Accessibility Testing
- [ ] Keyboard navigation works for all components
- [ ] Focus states visible (yellow GOV.UK ring)
- [ ] Screen reader announces banners correctly
- [ ] ARIA attributes present (`role="alert"`, `aria-labelledby`)
- [ ] No color-only information
- [ ] 4.5:1 contrast ratio minimum

### Browser Testing
- [ ] Chrome/Edge (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Mobile browsers (iOS Safari, Chrome Android)

### Responsive Testing
- [ ] Mobile (320px-640px)
- [ ] Tablet (641px-1024px)
- [ ] Desktop (1024px+)
- [ ] Zoom to 200%

---

## Rollout Plan

### Step 1: Preparation
1. ✅ Create new CSS files
2. ✅ Add to purgecss.config.js
3. ✅ Load in assets-css.php
4. ⬜ Run `npm run purgecss`

### Step 2: Staged Rollout
1. **Test Environment:**
   - Switch `home.php` to load `home-govuk-enhanced.php`
   - Test all notification types
   - Verify accessibility

2. **Production Rollout:**
   - Deploy CSS files
   - Update `home.php`
   - Monitor for issues

### Step 3: Full Refactor
1. Update all `showToast()` calls
2. Add pagination option
3. Add Details components
4. Add Summary Lists to feed items

---

## Benefits

✅ **WCAG 2.2 AA Compliant** - All feedback patterns accessible
✅ **Consistent UX** - Matches GOV.UK Design System
✅ **Better Visibility** - Banners more prominent than toasts
✅ **Persistent Messages** - Survive page reloads
✅ **Screen Reader Friendly** - Proper ARIA attributes
✅ **Keyboard Accessible** - All interactive elements focusable
✅ **Print Friendly** - Notifications print correctly

---

## Next Steps

1. **Immediate:**
   - Run `npm run purgecss` to generate minified CSS
   - Test `home-govuk-enhanced.php` on staging
   - Verify all notifications display correctly

2. **Short Term:**
   - Replace all toast notifications
   - Add warning text for account issues
   - Add inset text for tips

3. **Long Term:**
   - Add pagination as option
   - Use Summary Lists in feed items
   - Add Details for help content

---

**Status:** Ready for implementation
**Files Modified:** 3 (assets-css.php + created new files)
**Files Created:** 4 (3 CSS + 1 PHP)
**Backward Compatible:** Yes (toast still works until replaced)
