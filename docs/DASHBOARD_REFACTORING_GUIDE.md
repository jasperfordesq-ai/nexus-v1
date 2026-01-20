# Dashboard Page GOV.UK Component Refactoring Guide

**Date:** 2026-01-20
**Phase:** 3 - Page Template Refactoring
**Target File:** `views/civicone/dashboard.php`
**Status:** 🔄 READY TO START

---

## Executive Summary

This document provides a complete guide for refactoring the CivicOne dashboard page (`views/civicone/dashboard.php`) to use the new GOV.UK button and form component classes created in Phase 2.

**Current State:**
- Dashboard uses **25+ button instances** with legacy `.civic-btn-*` classes
- Forms use inline styles and non-GOV.UK patterns
- Buttons have inconsistent styling and sizing

**Target State:**
- All buttons use GOV.UK component classes (`.civic-button`, `.civic-button--primary`, etc.)
- Forms follow GOV.UK patterns with proper labels, hints, and error states
- Consistent 44px touch targets (WCAG 2.1 AA)
- Yellow focus states (#ffdd00) on all interactive elements

---

## Available GOV.UK Component Classes

### Button Components (from civicone-govuk-buttons.css)

#### Base Classes

| Class | Purpose | Background | Text | Shadow |
|-------|---------|------------|------|--------|
| `.civic-button` | Base button (primary green) | #00703c | White | #002d18 |
| `.civic-button--primary` | Explicit primary button | #00703c | White | #002d18 |
| `.civic-button--secondary` | Secondary action (grey) | #f3f2f1 | Black | #929191 |
| `.civic-button--warning` | Destructive action (red) | #d4351c | White | #55150b |
| `.civic-button--inverse` | For dark backgrounds | #ffffff | Black | #595959 |

#### Modifiers

| Modifier | Purpose | Example |
|----------|---------|---------|
| `.civic-button--start` | Start action with arrow icon | "Start now →" |
| `.civic-button--disabled` | Disabled state | Non-interactive button |
| `.civic-button--full-width` | 100% width on mobile | Mobile-first CTAs |

#### Size Variants

| Class | Use Case |
|-------|----------|
| Default | Standard buttons (44px min height) |
| `.civic-button + .civic-button` | Automatic spacing between buttons |

### Form Components (from civicone-govuk-forms.css)

| Class | Purpose | Usage |
|-------|---------|-------|
| `.civic-form-group` | Wraps label + hint + input + error | Container for form field |
| `.civic-label` | Form label | Always required for inputs |
| `.civic-hint` | Help text | Associated via `aria-describedby` |
| `.civic-input` | Text input | All text/email/password inputs |
| `.civic-input--error` | Error state input | 5px red left border |
| `.civic-error-message` | Error message text | Prefixed with "Error:" for SR |
| `.civic-textarea` | Multi-line text input | For comments, descriptions |
| `.civic-select` | Dropdown select | Standard select styling |

---

## Current Dashboard Button Audit

### Button Patterns Found (25+ instances)

| Current Class | Count | Current Usage | GOV.UK Replacement |
|---------------|-------|---------------|-------------------|
| `.civic-balance-btn` | 1 | "Manage Wallet" link | `.civic-button--secondary` |
| `.civic-dash-view-all` | 3 | "View All" links | `.civic-button--secondary` or link styling |
| `.civic-empty-link` | 4 | Empty state CTAs | `.civic-button--start` or `.civic-button--primary` |
| `.civic-action-btn` | 3 | Quick action buttons | `.civic-button--primary` |
| `.civic-btn-secondary` | 3 | Modal actions | `.civic-button--secondary` |
| `.civic-btn-primary` | 12 | Primary CTAs | `.civic-button` or `.civic-button--primary` |
| `.civic-btn-sm` | 10 | Small buttons in lists | `.civic-button` (no size modifier needed) |
| `.civic-btn-danger` | 3 | Delete buttons | `.civic-button--warning` |
| `.civic-btn-full` | 1 | Full-width submit | `.civic-button civic-button--full-width` |
| `.civic-modal-close` | 1 | Close button (X) | Keep as-is (icon button) |
| `.civic-selected-clear` | 1 | Clear selection | `.civic-button--secondary` |
| `.civic-fab-main` | 1 | Floating action button | Keep as-is (special mobile component) |
| `.civic-fab-item` | 3 | FAB menu items | Keep as-is |

**Total:** 46 button/link instances to review

---

## Refactoring Strategy

### Phase 1: Low-Risk Button Replacements (DO FIRST)

Replace standalone CTA buttons that don't affect complex interactions:

1. **"Manage Wallet" button** (line 85)
   ```php
   <!-- BEFORE -->
   <a href="<?= $basePath ?>/wallet" class="civic-balance-btn">Manage Wallet</a>

   <!-- AFTER -->
   <a href="<?= $basePath ?>/wallet" class="civic-button civic-button--secondary" role="button">
       Manage Wallet
   </a>
   ```

2. **Empty state CTAs** (lines 177, 225, 281, 772)
   ```php
   <!-- BEFORE -->
   <a href="<?= $basePath ?>/events" class="civic-empty-link">Explore Events</a>

   <!-- AFTER -->
   <a href="<?= $basePath ?>/events" class="civic-button civic-button--start" role="button">
       Explore Events
       <svg class="civic-button__start-icon" aria-hidden="true" focusable="false" ...>
           <!-- Right arrow icon -->
       </svg>
   </a>
   ```

3. **Quick action buttons** (lines 303, 307, 311)
   ```php
   <!-- BEFORE -->
   <a href="<?= $basePath ?>/achievements" class="civic-action-btn civic-action-btn-score">
       View Achievements
   </a>

   <!-- AFTER -->
   <a href="<?= $basePath ?>/achievements" class="civic-button civic-button--secondary" role="button">
       <i class="fa-solid fa-trophy" aria-hidden="true"></i>
       View Achievements
   </a>
   ```

### Phase 2: List Item Buttons (MEDIUM RISK)

Replace buttons within notification, event, and listing cards:

4. **Notification "View" and "Mark Read" buttons** (lines 445, 448)
   ```php
   <!-- BEFORE -->
   <a href="<?= htmlspecialchars($n['link']) ?>" class="civic-btn-primary civic-btn-sm">View</a>
   <button type="button" class="civic-btn-secondary civic-btn-sm">Mark Read</button>

   <!-- AFTER -->
   <a href="<?= htmlspecialchars($n['link']) ?>" class="civic-button" role="button">View</a>
   <button type="button" class="civic-button civic-button--secondary">Mark Read</button>
   ```

5. **Delete buttons** (lines 452, 564)
   ```php
   <!-- BEFORE -->
   <button type="button" onclick="deleteNotificationDashboard(<?= $n['id'] ?>)" class="civic-btn-danger civic-btn-sm">
       <i class="fa-solid fa-trash"></i> Delete
   </button>

   <!-- AFTER -->
   <button type="button" onclick="deleteNotificationDashboard(<?= $n['id'] ?>)" class="civic-button civic-button--warning">
       <i class="fa-solid fa-trash" aria-hidden="true"></i>
       <span class="civic-visually-hidden">Delete notification</span>
       Delete
   </button>
   ```

### Phase 3: Modal and Form Buttons (HIGH RISK)

Replace buttons in modals and forms:

6. **Modal action buttons** (lines 329, 333, 337, 375)
   ```php
   <!-- BEFORE -->
   <button type="button" onclick="openEventsModal()" class="civic-btn-secondary">
       <i class="fa-solid fa-filter"></i>
       <span class="btn-label">Events</span>
   </button>

   <!-- AFTER -->
   <button type="button" onclick="openEventsModal()" class="civic-button civic-button--secondary">
       <i class="fa-solid fa-filter" aria-hidden="true"></i>
       Events
   </button>
   ```

7. **Form submit button** (line 643)
   ```php
   <!-- BEFORE -->
   <button type="submit" id="transfer-btn" class="civic-btn-primary civic-btn-full">
       Transfer Credits
   </button>

   <!-- AFTER -->
   <button type="submit" id="transfer-btn" class="civic-button civic-button--primary">
       Transfer Credits
   </button>
   ```
   Note: GOV.UK buttons are full-width on mobile by default

### Phase 4: Form Field Refactoring (HIGHEST RISK)

Update the transfer form to use GOV.UK patterns:

8. **Transfer form** (lines 590-648)
   ```php
   <!-- BEFORE -->
   <form method="POST" action="<?= $basePath ?>/wallet/transfer" id="transfer-form">
       <label for="recipient">Recipient Username:</label>
       <input type="text" id="recipient" name="recipient" required>

       <label for="amount">Amount (hours):</label>
       <input type="number" id="amount" name="amount" min="0.5" step="0.5" required>

       <label for="note">Note (optional):</label>
       <textarea id="note" name="note"></textarea>

       <button type="submit" class="civic-btn-primary civic-btn-full">Transfer Credits</button>
   </form>

   <!-- AFTER (GOV.UK Pattern) -->
   <form method="POST" action="<?= $basePath ?>/wallet/transfer" id="transfer-form">
       <div class="civic-form-group">
           <label class="civic-label" for="recipient">
               Recipient Username
           </label>
           <div id="recipient-hint" class="civic-hint">
               Enter the username of the person you want to transfer credits to
           </div>
           <input class="civic-input"
                  id="recipient"
                  name="recipient"
                  type="text"
                  aria-describedby="recipient-hint"
                  required>
       </div>

       <div class="civic-form-group">
           <label class="civic-label" for="amount">
               Amount (hours)
           </label>
           <div id="amount-hint" class="civic-hint">
               Minimum transfer is 0.5 hours
           </div>
           <input class="civic-input civic-input--width-5"
                  id="amount"
                  name="amount"
                  type="number"
                  min="0.5"
                  step="0.5"
                  aria-describedby="amount-hint"
                  required>
       </div>

       <div class="civic-form-group">
           <label class="civic-label" for="note">
               Note (optional)
           </label>
           <textarea class="civic-textarea"
                     id="note"
                     name="note"
                     rows="5"></textarea>
       </div>

       <button type="submit" id="transfer-btn" class="civic-button">
           Transfer Credits
       </button>
   </form>
   ```

---

## Implementation Checklist

### Pre-Refactoring

- [ ] **Backup current dashboard.php** (copy to dashboard.php.backup)
- [ ] **Test current functionality** (all tabs, buttons, forms work)
- [ ] **Screenshot current state** (all tabs for visual comparison)
- [ ] **Verify GOV.UK CSS files are loaded** (check page source)

### During Refactoring

#### Phase 1: Low-Risk Buttons (Estimate: 30 mins)
- [ ] Replace "Manage Wallet" button (line 85)
- [ ] Replace 4 empty state CTA links (lines 177, 225, 281, 772)
- [ ] Replace 3 quick action buttons (lines 303, 307, 311)
- [ ] Test: Verify all links navigate correctly
- [ ] Test: Verify buttons have yellow focus (#ffdd00)

#### Phase 2: List Item Buttons (Estimate: 45 mins)
- [ ] Replace notification action buttons (lines 445, 448)
- [ ] Replace delete buttons (lines 452, 564)
- [ ] Replace "Enter Hub" buttons (line 493)
- [ ] Replace "Create Listing" button (line 528)
- [ ] Replace listing action buttons (lines 561, 564)
- [ ] Test: Verify list interactions work (view, delete, mark read)
- [ ] Test: Verify button spacing in lists

#### Phase 3: Modal Buttons (Estimate: 30 mins)
- [ ] Replace modal control buttons (lines 329, 333, 337)
- [ ] Replace modal close button (line 375)
- [ ] Replace "Got it" confirmation button
- [ ] Test: Verify modals open/close correctly
- [ ] Test: Focus returns to trigger after modal close

#### Phase 4: Form Refactoring (Estimate: 1 hour)
- [ ] Refactor transfer form with GOV.UK patterns
- [ ] Add `.civic-form-group` wrappers
- [ ] Add `.civic-label` classes
- [ ] Add `.civic-hint` elements with `aria-describedby`
- [ ] Add `.civic-input` classes
- [ ] Replace submit button
- [ ] Test: Form submission works
- [ ] Test: Form validation works
- [ ] Test: Error states display correctly

### Post-Refactoring

- [ ] **Keyboard navigation test** (Tab through all buttons)
- [ ] **Focus visibility test** (All buttons show yellow focus)
- [ ] **Screen reader test** (NVDA/VoiceOver announce correctly)
- [ ] **Mobile test** (Touch targets are 44px minimum)
- [ ] **Visual regression check** (Compare screenshots)
- [ ] **Functional test** (All features work as before)
- [ ] **Document changes** (Before/after examples in this guide)

---

## Known Considerations

### Keep As-Is (Do Not Refactor)

These elements should **NOT** be changed to GOV.UK button components:

| Element | Reason |
|---------|--------|
| Tab navigation links (`.civic-dash-tab`) | Custom tab component with specific styling |
| "View All" links (`.civic-dash-view-all`) | Text links, not buttons |
| Floating Action Button (`.civic-fab-main`) | Mobile-specific component with animation |
| FAB menu items (`.civic-fab-item`) | Part of FAB component system |
| Modal close button (X icon) | Icon-only button, keep minimal styling |

### Accessibility Improvements to Add

While refactoring, add these WCAG improvements:

1. **Add `role="button"` to link-styled buttons**
   - Links that trigger actions (not navigation) should have `role="button"`

2. **Add `aria-hidden="true"` to decorative icons**
   - All Font Awesome icons should be hidden from screen readers

3. **Add visually-hidden text for icon-only buttons**
   ```html
   <button class="civic-button civic-button--warning">
       <i class="fa-solid fa-trash" aria-hidden="true"></i>
       <span class="civic-visually-hidden">Delete notification</span>
   </button>
   ```

4. **Associate hints with inputs using `aria-describedby`**
   ```html
   <div id="amount-hint" class="civic-hint">Minimum transfer is 0.5 hours</div>
   <input id="amount" aria-describedby="amount-hint" ...>
   ```

---

## Testing Matrix

### Functional Tests

| Test | Pass/Fail | Notes |
|------|-----------|-------|
| Overview tab loads | [ ] | |
| Notifications tab loads | [ ] | |
| My Hubs tab loads | [ ] | |
| My Listings tab loads | [ ] | |
| Wallet tab loads | [ ] | |
| Events tab loads | [ ] | |
| All buttons clickable | [ ] | |
| All links navigate | [ ] | |
| Transfer form submits | [ ] | |
| Delete actions work | [ ] | |
| Mark read works | [ ] | |
| Modal opens/closes | [ ] | |

### Accessibility Tests

| Test | Pass/Fail | Notes |
|------|-----------|-------|
| Tab key navigates all buttons | [ ] | |
| Enter/Space activates buttons | [ ] | |
| Focus visible on all elements | [ ] | |
| Focus is yellow (#ffdd00) | [ ] | |
| Screen reader announces buttons | [ ] | |
| Screen reader announces form labels | [ ] | |
| Form hints announced | [ ] | |
| Error messages announced | [ ] | |
| Touch targets ≥ 44px | [ ] | |
| No keyboard traps | [ ] | |

### Visual Tests

| Test | Pass/Fail | Notes |
|------|-----------|-------|
| Buttons have GOV.UK styling | [ ] | |
| Primary buttons are green | [ ] | |
| Secondary buttons are grey | [ ] | |
| Warning buttons are red | [ ] | |
| Buttons have 3D shadow | [ ] | |
| Spacing consistent | [ ] | |
| Mobile responsive | [ ] | |
| Dark mode compatible | [ ] | |

---

## Rollback Plan

If issues are discovered after refactoring:

1. **Immediate rollback:**
   ```bash
   cp views/civicone/dashboard.php.backup views/civicone/dashboard.php
   ```

2. **Verify rollback:**
   - Clear browser cache
   - Test dashboard loads correctly
   - Test all tabs and buttons work

3. **Investigate issue:**
   - Review browser console for errors
   - Check if GOV.UK CSS files are loading
   - Verify class names match available CSS

---

## Before/After Examples

### Example 1: Primary CTA Button

**Before:**
```php
<a href="<?= $basePath ?>/listings/create" class="civic-btn-primary">
    Create a Listing
</a>
```

**After:**
```php
<a href="<?= $basePath ?>/listings/create" class="civic-button" role="button">
    Create a Listing
</a>
```

**Visual Change:**
- Green GOV.UK primary button (#00703c)
- 3D shadow effect (0 2px 0 #002d18)
- 44px minimum height (WCAG touch target)
- Yellow focus background (#ffdd00)

### Example 2: Delete Button

**Before:**
```php
<button type="button" onclick="deleteListing(<?= $listing['id'] ?>)" class="civic-btn-danger civic-btn-sm">
    <i class="fa-solid fa-trash"></i> Delete
</button>
```

**After:**
```php
<button type="button" onclick="deleteListing(<?= $listing['id'] ?>)" class="civic-button civic-button--warning">
    <i class="fa-solid fa-trash" aria-hidden="true"></i>
    Delete
</button>
```

**Visual Change:**
- Red GOV.UK warning button (#d4351c)
- White text for contrast
- Icon marked as decorative (`aria-hidden="true"`)
- No size variant needed (standard size)

### Example 3: Form Input

**Before:**
```php
<label for="amount">Amount (hours):</label>
<input type="number" id="amount" name="amount" min="0.5" step="0.5" required>
```

**After:**
```php
<div class="civic-form-group">
    <label class="civic-label" for="amount">
        Amount (hours)
    </label>
    <div id="amount-hint" class="civic-hint">
        Minimum transfer is 0.5 hours
    </div>
    <input class="civic-input civic-input--width-5"
           id="amount"
           name="amount"
           type="number"
           min="0.5"
           step="0.5"
           aria-describedby="amount-hint"
           required>
</div>
```

**Visual Change:**
- Thicker 2px black border (GOV.UK pattern)
- Yellow focus with inset shadow
- Hint text associated via `aria-describedby`
- Constrained width for number input

---

## Success Criteria

Refactoring is considered successful when:

✅ All 46 button instances reviewed and updated where appropriate
✅ Transfer form uses GOV.UK form patterns
✅ All functional tests pass
✅ All accessibility tests pass
✅ Visual comparison shows GOV.UK styling
✅ No regressions in existing functionality
✅ Keyboard navigation works perfectly
✅ Screen reader announces all elements correctly
✅ Mobile touch targets meet 44px minimum

---

## Next Steps After Dashboard

Once dashboard refactoring is complete and tested:

1. **Document lessons learned** (update this guide with findings)
2. **Create reusable patterns** (extract common button/form patterns)
3. **Move to Feed/Home page** (`views/civicone/feed/index.php`)
4. **Update Profile/Settings pages**
5. **Update Events pages**
6. **Update Groups pages**

---

## Resources

- **Source of Truth:** `docs/CIVICONE_WCAG21AA_SOURCE_OF_TRUTH.md`
- **Phase 2 Summary:** `docs/PHASE2_COMPLETION_SUMMARY.md`
- **GOV.UK Buttons CSS:** `httpdocs/assets/css/civicone-govuk-buttons.css`
- **GOV.UK Forms CSS:** `httpdocs/assets/css/civicone-govuk-forms.css`
- **GOV.UK Design System:** https://design-system.service.gov.uk/

---

**Document Version:** 1.0.0
**Last Updated:** 2026-01-20
**Status:** Ready for Implementation
