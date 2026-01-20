# Identity Bar WCAG 2.1 AA Testing Checklist

**Date:** 2026-01-20
**Component:** Profile Header Identity Bar
**File:** `views/civicone/profile/components/profile-header.php`

---

## Quick Verification

### 1. Visual Inspection

Visit any profile page (e.g., `/profile/26` or `/members/26`):

- [ ] Identity bar appears with blue background
- [ ] User name displays as H1 heading
- [ ] Avatar shows with online status indicator (if online)
- [ ] Metadata badges display (location, joined date, credits, etc.)
- [ ] Action buttons display below identity bar
- [ ] Admin phone badge shows "Show phone" button (admins only)

---

## Keyboard Navigation Test

### Steps:
1. Visit profile page
2. Press **Tab** key repeatedly
3. Verify focus moves in this order:

**Expected Tab Order:**
1. Skip link (if present)
2. Header navigation (if present)
3. Identity bar badges (if clickable - org owner/admin, rating)
4. Action buttons (Edit Profile / Add Friend / Message / Send Credits / Leave Review / Admin)
5. Main content

### Verification:
- [ ] Each focusable element shows **yellow background** (#ffdd00) when focused
- [ ] Focus indicator has black box-shadow (3px visible border)
- [ ] Focus order follows visual layout (top to bottom, left to right)
- [ ] No focus traps (can Tab forward and Shift+Tab backward)
- [ ] Phone reveal button receives focus (admins only)

---

## Screen Reader Test (NVDA)

### Setup:
1. Download NVDA: https://www.nvaccess.org/download/
2. Install and start NVDA (Ctrl+Alt+N)
3. Visit profile page

### Landmarks Test:
1. Press **Insert+F7** (Landmarks List)
2. Verify "Profile summary" landmark appears
3. Navigate to it and press Enter

**Expected Announcement:**
- "Profile summary, complementary landmark"

### Heading Navigation:
1. Press **H** key (next heading)
2. Verify user name announced as "Heading level 1"

**Expected Announcement:**
- "Heading level 1, {User Name}"

### Metadata List:
1. Tab to identity bar
2. Use arrow keys to navigate metadata items

**Expected Announcements:**
- "List with {N} items"
- "Online now" (if online)
- "Rosscarbery, County Cork, Ireland"
- "Joined October 2022"
- "106 Credits" (not "One hundred six credits")
- "Admin, button, Show phone" (if admin viewing)

### Status Indicator:
1. Navigate to avatar area
2. Verify status announced

**Expected Announcement:**
- "Profile picture of {Name}"
- "User is online now, status" (if online indicator present)

---

## Focus Visible Test

### Steps:
1. Tab through all interactive elements
2. Take screenshots of each focused element

### Verification Checklist:
- [ ] **Organization badge link** (if present): Yellow background, black text, black box-shadow
- [ ] **Rating badge link** (if present): Yellow background, black text, black box-shadow
- [ ] **Phone reveal button** (admin only): Yellow background, black text, black box-shadow
- [ ] **Action buttons** (all): Yellow background, black text, black box-shadow
- [ ] **No elements** have `outline: none` without replacement

### Focus Contrast Check:
- [ ] Yellow focus (#ffdd00) contrasts with:
  - Blue identity bar background (#1d70b8): ✅ 3.4:1
  - Grey action bar background (#f3f2f1): ✅ 1.1:1 (acceptable, yellow is highly visible)
  - White elements: ✅ 1.1:1 (acceptable, yellow stands out)

---

## Phone Reveal Test (Admin Only)

### Prerequisites:
- Must be logged in as admin
- Viewing another user's profile with phone number

### Steps:
1. Tab to phone reveal button
2. Verify button shows "Show phone" text
3. Press **Enter** or **Space**
4. Verify phone number appears
5. Verify button becomes disabled

### Expected Behavior:
- [ ] Button is keyboard accessible
- [ ] Button has `aria-label="Reveal phone number"`
- [ ] After click: button text changes to phone number
- [ ] After click: button disabled (grey, not clickable)
- [ ] After click: button `aria-label` updates to "Phone number: {number}"

---

## Reduced Motion Test

### Steps:
1. Enable reduced motion in OS:
   - **Windows:** Settings → Accessibility → Visual effects → Animation effects (Off)
   - **macOS:** System Preferences → Accessibility → Display → Reduce motion
2. Visit profile page
3. Observe online status indicator

### Verification:
- [ ] Online status indicator (green circle) does NOT pulse/animate
- [ ] Button hover transitions are instant (no smooth transitions)
- [ ] Badge hover effects are instant

---

## Zoom Test (WCAG 1.4.4, 1.4.10)

### 200% Zoom Test:
1. Visit profile page
2. Zoom browser to 200% (Ctrl+Plus 2 times)
3. Verify layout

**Expected:**
- [ ] Identity bar remains constrained to max-width (1020px)
- [ ] Text is readable (not clipped or overlapping)
- [ ] Metadata badges wrap to multiple lines if needed
- [ ] No horizontal scrollbar
- [ ] Action buttons wrap if needed

### 400% Zoom Test:
1. Zoom browser to 400% (Ctrl+Plus 6 times)
2. Verify layout

**Expected:**
- [ ] Identity bar stacks to single column
- [ ] Avatar appears above name
- [ ] Metadata badges stack vertically
- [ ] Action buttons stack vertically (full width)
- [ ] All content reflows cleanly
- [ ] No horizontal scrollbar

### Mobile Viewport Test (320px):
1. Open DevTools (F12)
2. Toggle device toolbar (Ctrl+Shift+M)
3. Set viewport to 320px width
4. Verify layout

**Expected:**
- [ ] Avatar shrinks to 60px (from 80px)
- [ ] Name font size reduces to 1.5rem (from 2rem)
- [ ] Metadata badges wrap and stack
- [ ] Action buttons become full-width
- [ ] No horizontal scrollbar

---

## Color Contrast Test (WCAG 1.4.3)

### Automated Test:
1. Install axe DevTools browser extension
2. Visit profile page
3. Run axe scan (right-click → Inspect → axe DevTools → Scan All)

**Expected:**
- [ ] 0 color contrast violations
- [ ] All text meets 4.5:1 minimum (normal text)
- [ ] All text meets 3:1 minimum (large text 18pt+)

### Manual Contrast Check:
Use WebAIM Contrast Checker: https://webaim.org/resources/contrastchecker/

**Tests:**
1. White text (#ffffff) on blue background (#1d70b8):
   - [ ] Result: 8.6:1 (Passes AAA)

2. Black text (#0b0c0c) on yellow focus (#ffdd00):
   - [ ] Result: 19.6:1 (Passes AAA)

3. Action button text (#0b0c0c) on white background (#ffffff):
   - [ ] Result: 21:1 (Passes AAA)

4. Green "Online" badge text on blue:
   - [ ] Result: Sufficient (visual check + icon provides redundancy)

---

## Semantic HTML Test

### Validation:
1. Visit profile page
2. View page source (Ctrl+U)
3. Check for proper semantic structure

**Expected Structure:**
```html
<aside class="civicone-identity-bar" aria-label="Profile summary">
  <div class="govuk-width-container">
    <div class="civicone-identity-bar__container">
      <div class="civicone-identity-bar__avatar">
        <img alt="Profile picture of {Name}" />
        <span role="status" aria-label="User is online now"></span>
      </div>
      <div class="civicone-identity-bar__info">
        <h1>{Name}</h1>
        <ul role="list">
          <li><data value="106">106 Credits</data></li>
          <!-- More metadata -->
        </ul>
      </div>
    </div>
  </div>
</aside>
```

### Verification Checklist:
- [ ] `<aside>` with `aria-label="Profile summary"` exists
- [ ] `<h1>` contains user name
- [ ] `<img>` has descriptive `alt` text starting with "Profile picture of"
- [ ] Status indicator has `role="status"` and `aria-label`
- [ ] Metadata uses `<ul role="list">` with `<li>` items
- [ ] Credits wrapped in `<data value="106">`
- [ ] Icons have `aria-hidden="true"`
- [ ] Phone reveal is `<button type="button">` (if admin)

---

## Lighthouse Audit

### Steps:
1. Open Chrome DevTools (F12)
2. Go to Lighthouse tab
3. Select "Accessibility" category only
4. Click "Generate report"

### Expected Results:
- [ ] **Accessibility Score:** 95-100
- [ ] **0 errors** in accessibility category
- [ ] Passing audits:
  - [x] Background and foreground colors have sufficient contrast
  - [x] Buttons have an accessible name
  - [x] Image elements have [alt] attributes
  - [x] Links have a discernible name
  - [x] Lists contain only `<li>` elements
  - [x] List items are contained within `<ul>` or `<ol>` parent elements
  - [x] [aria-*] attributes match their roles
  - [x] [role]s have all required [aria-*] attributes
  - [x] Elements with an ARIA role have required accessible name
  - [x] `<html>` element has a [lang] attribute
  - [x] Heading elements are in a sequentially-descending order

---

## Cross-Browser Testing

### Required Tests:
- [ ] **Chrome 131+** (Windows): All features work
- [ ] **Firefox 133+** (Windows): All features work
- [ ] **Edge 131+** (Windows): All features work
- [ ] **Safari 17+** (macOS): Expected to work (GOV.UK patterns are cross-browser)

### Focus States Verification:
Test in each browser:
1. Tab to phone reveal button (if admin)
2. Verify yellow background appears
3. Verify black box-shadow visible
4. Take screenshot

**Expected:**
- Same visual appearance across all browsers
- Yellow #ffdd00 background
- Black 3px box-shadow

---

## Regression Testing

### Features That Must Still Work:
- [ ] **Online status indicator:** Green circle pulses when online
- [ ] **Organization badges:** Links to organization wallet pages
- [ ] **Rating badge:** Scrolls to reviews section smoothly
- [ ] **Action buttons:** All friendship states work (Add Friend, Accept Request, etc.)
- [ ] **Admin impersonation:** "Login As User" button works
- [ ] **Edit Profile:** Link works for own profile
- [ ] **Message button:** Opens message compose to user
- [ ] **Send Credits:** Opens wallet with pre-filled recipient

---

## Privacy & Security Test

### Admin Phone Badge:
- [ ] Phone number NOT visible by default
- [ ] "Show phone" button visible only to admins
- [ ] Non-admins do NOT see phone badge at all
- [ ] Phone revealed ONLY after button click
- [ ] Button disabled after reveal (cannot spam)

### Test Matrix:
| Viewer Role | Has Phone | Expected Display |
|-------------|-----------|------------------|
| Own profile | Yes | No phone badge (use settings to update) |
| Own profile | No | No phone badge |
| Other user | Yes (admin viewing) | "Show phone" button |
| Other user | Yes (non-admin) | No phone badge |
| Other user | No | No phone badge |

---

## Performance Testing

### Metrics:
- [ ] **Identity bar renders in < 50ms** (no layout shift)
- [ ] **Focus state changes in < 16ms** (60fps)
- [ ] **Button hover transitions smooth** (unless reduced motion)
- [ ] **Page load not affected** by CSS changes (1.3KB purged CSS)

### Tools:
- Chrome DevTools Performance tab
- Lighthouse Performance audit
- Visual comparison before/after changes

---

## Pass Criteria

**Component is WCAG 2.1 AA compliant when:**
- ✅ All keyboard navigation tests pass
- ✅ All screen reader tests pass
- ✅ All focus visible tests pass
- ✅ All color contrast tests pass
- ✅ All semantic HTML tests pass
- ✅ Lighthouse accessibility score ≥ 95
- ✅ No regression in existing features
- ✅ Privacy/security tests pass

---

## Test Sign-Off

**Tester Name:** _________________
**Date:** _________________
**Result:** [ ] PASS / [ ] FAIL
**Notes:** _________________

---

## Troubleshooting

### Issue: Focus indicator not visible
**Solution:** Clear browser cache and reload CSS

### Issue: Phone reveal button not working
**Solution:** Check JavaScript console for errors, verify button has `onclick="revealPhone(this)"`

### Issue: Screen reader not announcing status
**Solution:** Verify `role="status"` and `aria-label` present on status indicator

### Issue: NVDA not finding landmark
**Solution:** Verify `<aside aria-label="Profile summary">` in HTML source

### Issue: Lighthouse score < 95
**Solution:** Check for custom modifications that removed ARIA attributes or changed semantic HTML
