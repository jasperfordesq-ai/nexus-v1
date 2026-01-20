# CivicOne Header Visual Testing Guide

**Date:** 2026-01-20
**Status:** READY FOR TESTING
**Automated Checks:** ✅ 35/35 PASS (100%)

---

## Quick Start

The CivicOne header has been refactored to follow GOV.UK Design System patterns and WCAG 2.1 AA standards. All automated code checks pass. This guide helps you verify the header works correctly in the browser.

**Estimated time:** 30-45 minutes

---

## Prerequisites

1. **Local development server running:**
   ```bash
   # Start Apache + PHP
   # Browse to: http://localhost/staging/ (or your tenant base URL)
   ```

2. **Browser DevTools installed:**
   - Chrome DevTools (built-in)
   - Firefox Developer Tools (built-in)
   - axe DevTools extension (install from Chrome Web Store)

3. **Screen reader (optional but recommended):**
   - Windows: NVDA (free download)
   - macOS: VoiceOver (built-in, press Cmd+F5)

---

## Test 1: Visual Structure (5 minutes)

### Desktop View (1920px)

1. **Load homepage** in Chrome/Firefox
2. **Open DevTools** (F12)
3. **Set viewport** to 1920px × 1080px

**Expected layout:**

```
┌────────────────────────────────────────────────────────┐
│ Utility Bar (light grey background)                    │
│ Platform: CivicOne  |  [User Menu]  |  [Notifications] │
└────────────────────────────────────────────────────────┘
┌────────────────────────────────────────────────────────┐
│ Header (white background, inside 1020px container)     │
│ [Logo: Project NEXUS]  Feed  Members  Groups  Listings │
│                        Volunteering  Events             │
└────────────────────────────────────────────────────────┘
┌────────────────────────────────────────────────────────┐
│ Search Bar (below header, inside 1020px container)     │
│ [Search input field]                         [🔍]      │
└────────────────────────────────────────────────────────┘
┌────────────────────────────────────────────────────────┐
│ Main Content (starts here)                             │
│ ...                                                     │
```

**Verification checklist:**

- [ ] Skip link NOT visible (appears only on focus)
- [ ] Utility bar spans full viewport width (light grey background)
- [ ] Logo visible on left
- [ ] Navigation items visible in a row: Feed, Members, Groups, Listings, Volunteering, Events
- [ ] Active page highlighted (different background color)
- [ ] Search bar below navigation
- [ ] Hamburger menu NOT visible (desktop view)
- [ ] Page content constrained to ~1020px width (not full viewport)
- [ ] No horizontal scrollbar

### Tablet View (768px)

1. **Resize viewport** to 768px × 1024px (DevTools responsive mode)

**Expected changes:**

- Navigation items may stack or compress
- Logo and nav still on same row
- Search may move to mobile toggle
- Hamburger menu may appear

**Verification checklist:**

- [ ] Header doesn't break (no overlap)
- [ ] Navigation items readable
- [ ] No horizontal scroll

### Mobile View (375px)

1. **Resize viewport** to 375px × 667px

**Expected layout:**

```
┌──────────────────────┐
│ Utility Bar          │
│ [☰ Menu]      [User] │
└──────────────────────┘
┌──────────────────────┐
│ [Logo]    [☰ Menu]   │
└──────────────────────┘
┌──────────────────────┐
│ [🔍 Search]          │
└──────────────────────┘
┌──────────────────────┐
│ Main Content         │
│ ...                  │
```

**Verification checklist:**

- [ ] Desktop navigation hidden
- [ ] Hamburger menu button visible (3 horizontal lines icon)
- [ ] Logo visible
- [ ] Search toggle button visible (magnifying glass icon)
- [ ] No horizontal scroll
- [ ] Touch targets ≥ 44px × 44px (use DevTools ruler)

**📸 SCREENSHOT:** Save screenshot as `header-mobile-375px.png`

---

## Test 2: Keyboard Navigation (10 minutes)

### Skip Link Test

1. **Load homepage**
2. **Press Tab** (once)

**Expected:**
- Yellow box appears at top of page with text "Skip to main content"
- Text is black on yellow background (GOV.UK pattern)
- Box has clear border

**Verification:**
- [ ] Skip link appears on first Tab press
- [ ] Has yellow background (#ffdd00)
- [ ] Has black text (#0b0c0c)
- [ ] Has visible border/outline

3. **Press Enter** (while skip link focused)

**Expected:**
- Page scrolls to main content area
- Focus moves to main content (or first focusable element in main)

**Verification:**
- [ ] Page scrolls smoothly
- [ ] Main content area receives focus

### Navigation Tab Order

1. **Refresh page**
2. **Press Tab** repeatedly and record order:

**Expected tab order:**

1. Skip link (yellow box)
2. Utility bar items (platform switcher, user menu, notifications)
3. Logo link
4. Feed nav link
5. Members nav link
6. Groups nav link
7. Listings nav link
8. Volunteering nav link (if enabled)
9. Events nav link (if enabled)
10. Search input field
11. Search submit button
12. Main content area (or first link in main)

**Verification:**
- [ ] Tab order matches expected sequence
- [ ] No focus traps (can Tab through entire page)
- [ ] Can Shift+Tab backwards through same sequence
- [ ] Every interactive element is reachable
- [ ] Focus indicator visible on ALL elements (see Test 3)

### Mobile Navigation Keyboard Test (375px viewport)

1. **Resize to 375px width**
2. **Tab to hamburger menu button**
3. **Press Enter**

**Expected:**
- Mobile navigation panel slides in from right/bottom
- First nav link receives focus (Feed)
- Panel has white/light background
- Panel overlays main content

**Verification:**
- [ ] Panel opens smoothly (CSS transition)
- [ ] Focus moves to first link in panel
- [ ] Panel is visually distinct from page background

4. **Press Arrow Down** (while focus in panel)

**Expected:**
- Focus moves to next nav link (Members)

**Verification:**
- [ ] Arrow Down moves to next link
- [ ] Arrow Up moves to previous link
- [ ] Focus cycles through all nav links

5. **Press Escape**

**Expected:**
- Panel closes
- Focus returns to hamburger menu button

**Verification:**
- [ ] Panel closes smoothly
- [ ] Focus returns to toggle button (not lost)
- [ ] Panel is hidden (not just visually hidden)

6. **Reopen panel** (Enter on hamburger)
7. **Tab outside panel** (Tab 10+ times)

**Expected:**
- Panel should close when focus leaves
- OR focus should be trapped in panel (both are acceptable)

**Verification:**
- [ ] Panel behavior is predictable (closes OR traps focus)
- [ ] No focus on elements behind panel

---

## Test 3: Focus Indicators (5 minutes)

### GOV.UK Focus Pattern Test

1. **Load homepage**
2. **Press Tab** to move focus through header elements
3. **For each focusable element, verify:**

**Expected focus style (GOV.UK pattern):**

- **Yellow background:** `#ffdd00` (bright yellow)
- **Black text:** `#0b0c0c` (nearly black)
- **Thick outline:** 3px solid
- **Box shadow:** Optional but enhances visibility

**Elements to test:**

| Element | Expected Focus Style | Pass/Fail |
|---------|---------------------|-----------|
| Skip link | Yellow bg, black text, 3px outline | [ ] |
| Logo link | Yellow bg, black text, 3px outline | [ ] |
| Feed nav link | Yellow bg, black text, 3px outline | [ ] |
| Members nav link | Yellow bg, black text, 3px outline | [ ] |
| Groups nav link | Yellow bg, black text, 3px outline | [ ] |
| Listings nav link | Yellow bg, black text, 3px outline | [ ] |
| Search input | Yellow bg, black text, 3px outline | [ ] |
| Search button | Yellow bg, black text, 3px outline | [ ] |
| Hamburger menu (mobile) | Yellow bg, black text, 3px outline | [ ] |

**Verification:**
- [ ] ALL elements have visible focus indicator
- [ ] Focus indicator has 3:1+ contrast with background (use DevTools Color Picker)
- [ ] Focus indicator is consistent across all elements
- [ ] No elements with `outline: none` without alternative focus style

**📸 SCREENSHOT:** Take screenshot of focused nav link showing yellow background

---

## Test 4: Responsive Behavior (10 minutes)

### Breakpoint Tests

Test header at these key viewport widths:

| Viewport | Expected Behavior | Pass/Fail |
|----------|------------------|-----------|
| 1920px | Desktop nav visible, hamburger hidden, search visible | [ ] |
| 1024px | Desktop nav visible, may compress slightly | [ ] |
| 768px | Tablet view, nav may stack or compress | [ ] |
| 641px | Mobile nav appears, desktop nav hidden | [ ] |
| 375px | Mobile nav, hamburger visible, logo + menu only | [ ] |
| 320px | Smallest mobile (iPhone SE), everything stacks | [ ] |

**Verification for each viewport:**
- [ ] No horizontal scroll
- [ ] No overlapping text
- [ ] All interactive elements have 44px+ touch targets (mobile only)
- [ ] Logo remains visible and readable
- [ ] Active page highlight remains visible

### Zoom Tests (WCAG 1.4.4)

1. **Load homepage at 1920px viewport**
2. **Zoom to 200%** (Ctrl + or Cmd +)

**Expected:**
- Page reflows (no horizontal scroll)
- Navigation items may stack vertically
- Search may move below navigation
- All text remains readable

**Verification:**
- [ ] No horizontal scroll at 200% zoom
- [ ] All content accessible without scrolling sideways
- [ ] Focus indicators remain visible
- [ ] Text doesn't overlap or clip

3. **Zoom to 400%** (keep zooming)

**Expected:**
- Header stacks into single column
- Hamburger menu may appear (mobile breakpoint triggered)
- All content readable but may require vertical scrolling

**Verification:**
- [ ] No horizontal scroll at 400% zoom
- [ ] Content reflows cleanly
- [ ] No broken layout

**Reset zoom:** Ctrl+0 or Cmd+0

---

## Test 5: Search Functionality (5 minutes)

### Desktop Search

1. **Load homepage at 1920px**
2. **Tab to search input** or **click search input**

**Verification:**
- [ ] Search input receives focus (yellow outline)
- [ ] Input is keyboard accessible
- [ ] Label exists (may be visually hidden but announced by screen readers)

3. **Type:** "test query"
4. **Press Enter**

**Expected:**
- Form submits to `/search?q=test+query` (or similar)
- Search results page loads

**Verification:**
- [ ] Form submits correctly
- [ ] Query parameter passed in URL

### Mobile Search

1. **Resize to 375px**
2. **Tab to mobile search toggle button** (magnifying glass icon)
3. **Press Enter**

**Expected:**
- Mobile search bar slides in/appears
- Search input receives focus

**Verification:**
- [ ] Mobile search bar appears
- [ ] Search input focused automatically
- [ ] Input is functional (can type)

4. **Type:** "test query"
5. **Press Enter**

**Expected:**
- Form submits (same as desktop)

**Verification:**
- [ ] Mobile search works identically to desktop

6. **Press Escape** or **blur input**

**Expected:**
- Mobile search bar collapses/hides

**Verification:**
- [ ] Mobile search bar can be closed
- [ ] Focus returns to toggle button

---

## Test 6: Active Page Highlighting (5 minutes)

### Navigation State Test

1. **Load homepage** (`/` or `/feed`)

**Expected:**
- "Feed" nav link has active state styling (different background, bold text, or underline)
- "Feed" nav link has `aria-current="page"` attribute (check in DevTools)

**Verification:**
- [ ] Feed link visually highlighted
- [ ] Feed link has `aria-current="page"` in HTML

2. **Navigate to Members page** (`/members`)

**Expected:**
- "Members" nav link now has active state
- "Feed" nav link returns to normal state

**Verification:**
- [ ] Members link highlighted
- [ ] Members link has `aria-current="page"`
- [ ] Only ONE nav link has active state

3. **Test other pages:**

| Page | URL | Expected Active Nav Item | Pass/Fail |
|------|-----|-------------------------|-----------|
| Groups | `/groups` | Groups | [ ] |
| Listings | `/listings` | Listings | [ ] |
| Volunteering | `/volunteering` | Volunteering | [ ] |
| Events | `/events` | Events | [ ] |
| Group detail | `/groups/123` | Groups (parent section) | [ ] |
| Member profile | `/members/456` | Members (parent section) | [ ] |

**Verification:**
- [ ] Active state works on all pages
- [ ] Detail pages highlight parent section (e.g., `/groups/123` highlights "Groups")
- [ ] Only ONE nav item is active at a time

---

## Test 7: Accessibility Audit (10 minutes)

### axe DevTools Scan

1. **Install axe DevTools extension** (if not already installed)
   - Chrome: https://chrome.google.com/webstore/detail/axe-devtools/lhdoppojpmngadmnindnejefpokejbdd
   - Firefox: https://addons.mozilla.org/en-US/firefox/addon/axe-devtools/

2. **Load homepage**
3. **Open DevTools** (F12)
4. **Go to "axe DevTools" tab**
5. **Click "Scan ALL of my page"**

**Expected results:**
- **0 violations** (or document any violations found)
- Minor issues/warnings are acceptable if documented

**Verification:**
- [ ] 0 critical violations
- [ ] 0 serious violations
- [ ] Document any moderate/minor issues

**If violations found:**
- Screenshot the violation details
- Note the element and issue type
- Add to "Issues Found" section below

### Lighthouse Accessibility Audit

1. **Open DevTools**
2. **Go to "Lighthouse" tab**
3. **Select "Accessibility" only**
4. **Click "Analyze page load"**

**Expected score:**
- **≥95** (target: 100)

**Verification:**
- [ ] Lighthouse accessibility score ≥95
- [ ] Document score: _______
- [ ] Note any failed audits

**Common Lighthouse checks:**
- Background and foreground colors have sufficient contrast
- `[aria-*]` attributes match their roles
- `[role]` values are valid
- Elements with an ARIA role must have required attributes
- Form elements have associated labels
- Heading elements appear in sequentially-descending order
- Links have a discernible name
- `<html>` element has a `[lang]` attribute

---

## Test 8: Screen Reader Test (OPTIONAL - 10 minutes)

**Note:** This is optional but highly recommended for full WCAG compliance.

### Windows (NVDA)

1. **Download NVDA** (if not installed): https://www.nvaccess.org/download/
2. **Start NVDA** (Ctrl+Alt+N)
3. **Load homepage**
4. **Press H** (navigate by headings)

**Expected:**
- NVDA announces page heading hierarchy
- Can navigate through headings logically

5. **Press D** (navigate by landmarks)

**Expected:**
- NVDA announces: "Banner landmark" (header)
- "Navigation landmark" (service navigation)
- "Main landmark" (main content)

6. **Tab through navigation**

**Expected for each nav link:**
- "Feed, link" (or "Feed, link, current page" if on homepage)
- "Members, link"
- "Groups, link"
- etc.

**Verification:**
- [ ] Landmarks announced correctly
- [ ] Active page announced with "current page"
- [ ] All links have descriptive names (not "link" or "click here")

### macOS (VoiceOver)

1. **Start VoiceOver** (Cmd+F5)
2. **Load homepage**
3. **Navigate by landmarks** (VO+U, then left/right arrows to "Landmarks")

**Expected:**
- Banner (header)
- Navigation (service nav)
- Main (main content)

4. **Navigate through header** (VO+Right Arrow)

**Expected:**
- VoiceOver announces each element
- Link names are descriptive
- Active page indicated

**Verification:**
- [ ] VoiceOver announces structure correctly
- [ ] Can navigate through header logically

**Stop VoiceOver:** Cmd+F5

---

## Test 9: Cross-Browser Compatibility (OPTIONAL - 15 minutes)

Test header in multiple browsers:

| Browser | Version | Desktop Pass | Mobile Pass | Notes |
|---------|---------|--------------|-------------|-------|
| Chrome | Latest | [ ] | [ ] | |
| Firefox | Latest | [ ] | [ ] | |
| Safari | Latest | [ ] | [ ] | macOS/iOS only |
| Edge | Latest | [ ] | [ ] | |

**For each browser:**
1. Load homepage
2. Test keyboard navigation (Tab, Enter, Escape)
3. Test mobile menu (resize to 375px)
4. Verify focus indicators visible
5. Check for console errors (F12 → Console tab)

**Verification:**
- [ ] Header renders correctly in all browsers
- [ ] No JavaScript errors in console
- [ ] Focus indicators visible in all browsers
- [ ] Mobile menu works in all browsers

---

## Test 10: Performance Check (OPTIONAL - 5 minutes)

### Lighthouse Performance Audit

1. **Open DevTools**
2. **Go to "Lighthouse" tab**
3. **Select "Performance" only**
4. **Click "Analyze page load"**

**Expected:**
- Performance score: ≥80 (header should not significantly impact performance)

**Metrics to check:**
- **Largest Contentful Paint (LCP):** <2.5s
- **First Input Delay (FID):** <100ms
- **Cumulative Layout Shift (CLS):** <0.1

**Verification:**
- [ ] No layout shift when header loads
- [ ] Header CSS loads quickly
- [ ] No render-blocking CSS for header

---

## Issues Found

**Record any issues discovered during testing:**

### Issue 1:
- **Test:** _______________________
- **Browser:** _______________________
- **Viewport:** _______________________
- **Description:** _______________________
- **Screenshot:** _______________________
- **Severity:** Critical / Major / Minor
- **Status:** Open / Fixed

### Issue 2:
(Add more as needed)

---

## Test Results Summary

**Date completed:** _______________________
**Tester:** _______________________
**Environment:** _______________________

### Overall Status

- [ ] **PASS** - All tests passed, header ready for production
- [ ] **PASS WITH NOTES** - Minor issues documented, header acceptable
- [ ] **FAIL** - Critical issues found, header needs fixes

### Test Scores

| Test Category | Pass Rate | Notes |
|---------------|-----------|-------|
| Visual Structure | ___/10 | |
| Keyboard Navigation | ___/15 | |
| Focus Indicators | ___/9 | |
| Responsive Behavior | ___/12 | |
| Search Functionality | ___/8 | |
| Active Page Highlighting | ___/8 | |
| Accessibility Audit | ___/5 | |
| Screen Reader (optional) | ___/6 | |
| Cross-Browser (optional) | ___/4 | |
| Performance (optional) | ___/3 | |

**Total Score:** ___/80 (or ___/93 with optional tests)

### Recommendations

1. _______________________
2. _______________________
3. _______________________

### Sign-Off

**Approved by:** _______________________
**Date:** _______________________
**Signature:** _______________________

---

## Next Steps After Testing

**If all tests pass:**

1. ✅ Mark header refactor as COMPLETE in project tracker
2. ✅ Update `docs/HEADER_REFACTOR_DIAGNOSTIC_2026-01-20.md` with test results
3. ✅ Create GitHub issue to track any minor issues for future improvement
4. ✅ Deploy to staging environment
5. ✅ Notify team of new header implementation

**If critical issues found:**

1. ❌ Document issues in this file
2. ❌ Create GitHub issues for each critical issue
3. ❌ Fix issues following Section 9A.7 refactor workflow
4. ❌ Re-run tests after fixes
5. ❌ Repeat until all tests pass

---

## Reference Documents

- **WCAG 2.1 AA Source of Truth:** `docs/CIVICONE_WCAG21AA_SOURCE_OF_TRUTH.md` (Section 9A)
- **Header Diagnostic:** `docs/HEADER_REFACTOR_DIAGNOSTIC_2026-01-20.md`
- **Verification Script:** `scripts/verify-header-refactor.sh`
- **GOV.UK Service Navigation:** https://design-system.service.gov.uk/components/service-navigation/
- **GOV.UK Skip Link:** https://design-system.service.gov.uk/components/skip-link/
- **WCAG 2.1 Quick Reference:** https://www.w3.org/WAI/WCAG21/quickref/
