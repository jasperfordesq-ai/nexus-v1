# CivicOne WCAG 2.1 AA Testing Checklist

**Version:** 1.1
**Date:** 2026-01-22
**Status:** Implementation Complete - Ready for Validation Testing

This checklist should be used to verify WCAG 2.1 AA compliance for CivicOne pages.

---

## Implementation Summary (as of 2026-01-22)

| Component | Implementation Status | Files Modified |
|-----------|----------------------|----------------|
| **Keyboard Navigation** | ✅ Complete | header-scripts.php, mobile-nav-v2.js |
| **Focus Styles** | ✅ Complete | civicone-govuk-focus.css (170+ elements) |
| **GOV.UK Buttons** | ✅ Complete | civicone-govuk-buttons.css |
| **GOV.UK Forms** | ✅ Complete | civicone-govuk-forms.css |
| **Error Summary** | ✅ Complete | error-summary.php |
| **Color Tokens** | ✅ Complete | design-tokens.css (1,390 colors migrated) |
| **Spacing Tokens** | ✅ Complete | css-wcag-refactor.js (2,055 spacing fixes) |
| **Service Navigation** | ✅ Complete | service-navigation.php |
| **Utility Dropdowns** | ✅ Complete | utility-bar.php |
| **Glassmorphism Removal** | ✅ Complete | civicone-govuk-components.css |

---

## Quick Testing Tools

### Browser Extensions
- **axe DevTools** (Chrome/Firefox) - Automated accessibility testing
- **WAVE** (Chrome/Firefox) - Visual accessibility checker
- **Lighthouse** (Chrome DevTools) - Accessibility audit

### Screen Readers
- **NVDA** (Windows, free) - Most common screen reader
- **VoiceOver** (macOS/iOS, built-in) - Apple's screen reader
- **TalkBack** (Android, built-in) - Google's screen reader

### Keyboard Testing
- Tab through entire page
- Verify focus order is logical
- Test all interactive elements with Enter/Space
- Test menus with Arrow keys
- Test Escape closes menus/modals

---

## Per-Page Testing Checklist

### 1. Keyboard Navigation (WCAG 2.1.1, 2.1.2)

- [ ] Can navigate entire page using only Tab key
- [ ] Focus order follows visual reading order
- [ ] All interactive elements are reachable by keyboard
- [ ] No keyboard traps (can always Tab away)
- [ ] Skip link works and takes focus to main content
- [ ] Menus/dropdowns work with:
  - [ ] Enter/Space to open
  - [ ] Arrow keys to navigate
  - [ ] Escape to close
- [ ] Modals/drawers trap focus when open
- [ ] Focus returns to trigger element when closing

### 2. Focus Visibility (WCAG 2.4.7)

- [ ] Focus ring visible on ALL interactive elements
- [ ] Focus uses GOV.UK yellow (#ffdd00) background
- [ ] Text on focus has black (#0b0c0c) color
- [ ] Focus ring has sufficient contrast (3:1 minimum)
- [ ] Focus never disappears unexpectedly

### 3. Color Contrast (WCAG 1.4.3, 1.4.11)

- [ ] Normal text: 4.5:1 contrast ratio
- [ ] Large text (18pt+): 3:1 contrast ratio
- [ ] Interactive elements: 3:1 against background
- [ ] Focus indicators: 3:1 contrast
- [ ] Error messages: Red text readable on background

### 4. Headings & Structure (WCAG 1.3.1, 2.4.6)

- [ ] Single `<h1>` per page
- [ ] Headings follow hierarchical order (h1 → h2 → h3)
- [ ] No skipped heading levels
- [ ] Headings describe section content
- [ ] Landmarks present: main, nav, header, footer

### 5. Forms (WCAG 1.3.5, 3.3.1, 3.3.2)

- [ ] All inputs have visible labels
- [ ] Labels are programmatically associated (`for`/`id`)
- [ ] Required fields indicated (not just by color)
- [ ] Error messages appear in error summary at top
- [ ] Error summary receives focus on page load
- [ ] Error messages linked to fields with `aria-describedby`
- [ ] Hint text provided where helpful
- [ ] Error messages describe how to fix

### 6. Images & Media (WCAG 1.1.1, 1.4.5)

- [ ] All images have `alt` attribute
- [ ] Decorative images have `alt=""`
- [ ] Complex images have detailed description
- [ ] Icons-only buttons have accessible labels
- [ ] No text in images (except logos)

### 7. Responsive & Reflow (WCAG 1.4.10, 1.4.12)

- [ ] Content reflows at 400% zoom
- [ ] No horizontal scrolling at 320px width
- [ ] Text can be resized to 200%
- [ ] Touch targets minimum 44x44px

---

## Page-Specific Tests

### Authentication Pages (login.php, register.php)

- [ ] Error summary appears at top when validation fails
- [ ] Focus moves to error summary on error
- [ ] Password field has `autocomplete="current-password"`
- [ ] Email field has `autocomplete="email"`
- [ ] Submit button clearly labeled
- [ ] "Forgot password" link accessible

### Directory Pages (members, groups, listings)

- [ ] Filter form is keyboard accessible
- [ ] Results announce via live region
- [ ] Pagination has proper ARIA labels
- [ ] "Clear filters" button accessible
- [ ] Empty state message is announced

### Detail Pages (profile, listing, event)

- [ ] Back link is keyboard accessible
- [ ] Main content uses `<main>` landmark
- [ ] Action buttons have accessible names
- [ ] Tabs (if present) use proper ARIA roles
- [ ] Related content has proper heading structure

### Mobile Navigation

- [ ] Menu button has `aria-expanded`
- [ ] Menu announces open/close state
- [ ] Focus trapped in open menu
- [ ] Escape key closes menu
- [ ] Focus returns to trigger on close

---

## Automated Testing Commands

### Run Lighthouse Audit
```bash
# In Chrome DevTools > Lighthouse tab > Accessibility
```

### Run axe DevTools
```javascript
// In browser console after installing axe DevTools
axe.run().then(results => {
    console.log('Violations:', results.violations);
    console.log('Passes:', results.passes.length);
});
```

### Run Pa11y CLI
```bash
# Install: npm install -g pa11y
npx pa11y http://staging.timebank.local/hour-timebank/members --standard WCAG2AA
```

---

## Screen Reader Quick Tests

### NVDA (Windows)
1. Press `Insert + Down` to read continuously
2. Press `H` to navigate by headings
3. Press `D` to navigate by landmarks
4. Press `Tab` to navigate forms
5. Press `Insert + F7` for elements list

### VoiceOver (macOS)
1. Press `VO + A` (Cmd+F5 to start VO)
2. Press `VO + U` for rotor
3. Press `VO + Cmd + H` for headings
4. Press `Tab` for form navigation

---

## Testing Priority Matrix

| Page | Priority | Implementation | Validation Status |
|------|----------|----------------|-------------------|
| Login | P1 | ✅ GOV.UK buttons, forms, focus | ✅ Pa11y: 0 errors |
| Register | P1 | ✅ GOV.UK buttons, forms, focus | ✅ Pa11y: 0 errors |
| Members Directory | P1 | ✅ GOV.UK filters, pagination | ✅ Pa11y: 0 errors |
| Dashboard | P1 | ✅ GOV.UK components | ✅ Pa11y: 0 errors |
| Profile | P2 | ✅ GOV.UK forms | ✅ Pa11y: 0 errors |
| Listings | P2 | ✅ GOV.UK buttons, forms | ✅ Pa11y: 0 errors |
| Events | P2 | ✅ GOV.UK buttons, forms | ✅ Pa11y: 0 errors |
| Groups | P2 | ✅ GOV.UK buttons, forms | ✅ Pa11y: 0 errors |
| Messages | P2 | ✅ GOV.UK forms | ✅ Pa11y: 0 errors |
| Help | P3 | ✅ Content styling | ✅ Pa11y: 0 errors |

**Last automated test run:** 2026-01-22 (pa11y WCAG2AA standard)

---

## Common Issues to Check

1. **Missing focus styles** - Custom CSS removes focus
2. **Color-only information** - Errors shown only by red color
3. **Unlabeled buttons** - Icon buttons without text
4. **Low contrast** - Text hard to read
5. **Keyboard traps** - Can't Tab out of element
6. **Missing alt text** - Images without descriptions
7. **Broken heading hierarchy** - h1 → h3 (skipped h2)
8. **Form errors not linked** - No aria-describedby

---

## Reporting Issues

When reporting accessibility issues, include:

1. **Page URL**
2. **Element** (selector or description)
3. **Issue type** (WCAG criterion)
4. **Steps to reproduce**
5. **Expected behavior**
6. **Actual behavior**
7. **Screenshot** (if applicable)

---

## Resources

- [GOV.UK Design System](https://design-system.service.gov.uk/)
- [WCAG 2.1 Guidelines](https://www.w3.org/WAI/WCAG21/quickref/)
- [WebAIM Contrast Checker](https://webaim.org/resources/contrastchecker/)
- [CivicOne WCAG Source of Truth](./CIVICONE_WCAG21AA_SOURCE_OF_TRUTH.md)
