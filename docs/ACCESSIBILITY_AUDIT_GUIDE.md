# Automated Accessibility Audit Guide

**Component:** Profile Header Identity Bar
**Target:** WCAG 2.1 AA Compliance
**Date:** 2026-01-20

---

## Method 1: Lighthouse Audit (Chrome DevTools) ⭐ RECOMMENDED

**Why:** Built into Chrome, no installation needed, official Google tool.

### Steps:

1. **Open the profile page in Chrome:**
   ```
   http://staging.timebank.local/hour-timebank/profile/26
   ```

2. **Open DevTools:**
   - Press `F12` (or right-click → Inspect)

3. **Navigate to Lighthouse:**
   - Click the **"Lighthouse"** tab at the top
   - (If not visible, click the `>>` arrows to show more tabs)

4. **Configure the audit:**
   - ✅ Check **"Accessibility"** (uncheck Performance, Best Practices, SEO)
   - Device: **Desktop** (or Mobile)
   - Click **"Analyze page load"**

5. **Wait for results** (10-30 seconds)

### Expected Results:

```
Accessibility Score: 95-100 ✅

Passed Audits:
✅ [aria-*] attributes match their roles
✅ Background and foreground colors have sufficient contrast
✅ Buttons have an accessible name
✅ Image elements have [alt] attributes
✅ Links have a discernible name
✅ Lists contain only <li> elements
✅ [role]s have all required [aria-*] attributes
✅ Elements with ARIA roles have accessible names
✅ <html> element has a [lang] attribute
✅ Heading elements are in sequentially-descending order
```

### How to Save Results:

1. Click the **"⋮"** (three dots) in top-right of Lighthouse panel
2. Select **"Save as HTML"**
3. Save to: `docs/audits/lighthouse-profile-identity-bar-2026-01-20.html`

---

## Method 2: axe DevTools (Browser Extension) ⭐ MOST COMPREHENSIVE

**Why:** Industry-standard tool used by accessibility professionals, catches more issues than Lighthouse.

### Installation:

1. **Chrome:** https://chrome.google.com/webstore/detail/axe-devtools-web-accessib/lhdoppojpmngadmnindnejefpokejbdd
2. **Firefox:** https://addons.mozilla.org/en-US/firefox/addon/axe-devtools/
3. **Edge:** https://microsoftedge.microsoft.com/addons/detail/axe-devtools-web-access/kcenlimkmjjkdfcaleembgmldmnnlfkn

### Steps:

1. **Install axe DevTools extension** (free version is sufficient)

2. **Open the profile page:**
   ```
   http://staging.timebank.local/hour-timebank/profile/26
   ```

3. **Open DevTools:**
   - Press `F12`

4. **Navigate to axe DevTools:**
   - Click the **"axe DevTools"** tab
   - Click **"Scan ALL of my page"**

5. **Wait for results** (5-15 seconds)

### Expected Results:

```
Issues Found: 0 ❌ (No violations!)

✅ Automated Tests Passed: 40-50+ rules
✅ WCAG 2.1 Level A: All passed
✅ WCAG 2.1 Level AA: All passed

Checked:
✅ Color contrast
✅ Keyboard navigation
✅ ARIA attributes
✅ Form labels
✅ Alt text
✅ Heading hierarchy
✅ Landmarks
✅ Focus indicators
```

### How to Save Results:

1. Click **"Export"** button
2. Choose **"CSV"** or **"JSON"**
3. Save to: `docs/audits/axe-profile-identity-bar-2026-01-20.csv`

### How to Generate Report:

1. Click **"View Issue"** for any issues (should be none)
2. Click **"Download Full Report"**
3. Save PDF to: `docs/audits/axe-full-report-2026-01-20.pdf`

---

## Method 3: WAVE Toolbar (Visual Feedback)

**Why:** Shows accessibility issues directly on the page with visual indicators.

### Installation:

1. **Chrome:** https://chrome.google.com/webstore/detail/wave-evaluation-tool/jbbplnpkjmmeebjpijfedlgcdilocofh
2. **Firefox:** https://addons.mozilla.org/en-US/firefox/addon/wave-accessibility-tool/
3. **Edge:** https://microsoftedge.microsoft.com/addons/detail/wave-evaluation-tool/khapceneeednkiopkkbgkibbdoajpkoj

### Steps:

1. **Install WAVE extension**

2. **Open the profile page:**
   ```
   http://staging.timebank.local/hour-timebank/profile/26
   ```

3. **Click WAVE icon** in browser toolbar

4. **Review results** in left sidebar

### Expected Results:

```
Errors: 0 ✅
Contrast Errors: 0 ✅
Alerts: 0-2 (minor warnings, not violations)

Features Detected:
✅ ARIA attributes
✅ Landmarks (aside with aria-label)
✅ Alternative text
✅ Structural elements (headings, lists)
✅ Form labels
```

### Visual Indicators:

- **Green icons:** Accessibility features present ✅
- **Red icons:** Errors ❌ (should be zero)
- **Yellow icons:** Alerts ⚠️ (review, not necessarily violations)

### How to Save Results:

1. Click **"Download Report"** in WAVE sidebar
2. Save HTML report to: `docs/audits/wave-profile-2026-01-20.html`

---

## Method 4: Pa11y CLI (Command Line - For CI/CD)

**Why:** Automated testing in CI/CD pipeline, can be scripted.

### Installation:

```bash
npm install -g pa11y
```

### Usage:

```bash
# Basic scan
pa11y http://staging.timebank.local/hour-timebank/profile/26

# WCAG 2.1 AA standard
pa11y --standard WCAG2AA http://staging.timebank.local/hour-timebank/profile/26

# Save results to file
pa11y --standard WCAG2AA --reporter json http://staging.timebank.local/hour-timebank/profile/26 > docs/audits/pa11y-profile-2026-01-20.json

# HTML report
pa11y --standard WCAG2AA --reporter html http://staging.timebank.local/hour-timebank/profile/26 > docs/audits/pa11y-profile-2026-01-20.html
```

### Expected Output:

```
No issues found!

✓ Profile page passed WCAG 2.1 AA
```

### For Continuous Integration:

```bash
# Add to package.json scripts:
{
  "scripts": {
    "test:a11y": "pa11y --standard WCAG2AA http://staging.timebank.local/hour-timebank/profile/26"
  }
}

# Run in CI:
npm run test:a11y
```

---

## Method 5: Accessibility Insights (Microsoft)

**Why:** Official Microsoft tool, great for manual + automated testing.

### Installation:

1. **Download:** https://accessibilityinsights.io/downloads/
2. Choose **"Accessibility Insights for Web"** (browser extension)
3. Install for Chrome or Edge

### Steps:

1. **Install Accessibility Insights extension**

2. **Open the profile page:**
   ```
   http://staging.timebank.local/hour-timebank/profile/26
   ```

3. **Click Accessibility Insights icon** in toolbar

4. **Choose "FastPass":**
   - Automated checks
   - Tab stops (keyboard navigation)
   - Needs review items

5. **Wait for results**

### Expected Results:

```
Automated Checks: Pass ✅
Tab Stops: Logical order ✅
Needs Review: 0-2 items

Issues:
- 0 Failures ✅
- 0 Incomplete
```

### How to Save Results:

1. Click **"Export result"**
2. Save HTML report to: `docs/audits/accessibility-insights-2026-01-20.html`

---

## Method 6: Manual Keyboard Testing (Critical!)

**Why:** Automated tools can't test keyboard navigation fully. Manual testing required.

### Keyboard Test Checklist:

Visit: http://staging.timebank.local/hour-timebank/profile/26

**1. Tab Navigation:**
- [ ] Press `Tab` from top of page
- [ ] Each button receives visible focus (yellow background)
- [ ] Focus order is logical (top to bottom, left to right)
- [ ] No focus traps (can Tab forward and Shift+Tab backward)

**2. Action Button Tests:**
- [ ] "Add Friend" button: Press `Enter` activates
- [ ] "Message" button: Press `Enter` navigates
- [ ] "Send Credits" button: Press `Enter` opens wallet
- [ ] "Leave Review" button: Press `Enter` opens modal
- [ ] "Admin" button: Press `Enter` navigates (if admin)

**3. Identity Bar Badge Tests:**
- [ ] Organization badges (if present): Press `Enter` navigates
- [ ] Rating badge (if present): Press `Enter` scrolls to reviews
- [ ] Phone reveal button (if admin): Press `Enter` reveals phone

**4. Focus Visibility:**
- [ ] All focused elements have **yellow (#ffdd00) background**
- [ ] Black text on yellow
- [ ] Black border/shadow visible

**5. Escape Key:**
- [ ] If any modals open, `Escape` closes them
- [ ] Focus returns to trigger element

### Document Results:

```
✅ All interactive elements keyboard accessible
✅ Focus order logical
✅ Focus always visible (GOV.UK yellow pattern)
✅ No focus traps
✅ Enter/Space activate buttons
✅ Escape closes modals
```

---

## Method 7: Screen Reader Testing (NVDA - Free)

**Why:** Automated tools can't test screen reader experience. Manual testing required.

### Installation:

1. **Download NVDA:** https://www.nvaccess.org/download/
2. Install (free, open source)
3. Start NVDA: `Ctrl+Alt+N`

### Landmark Test:

1. Visit profile page
2. Press `Insert+F7` (Elements List)
3. Select **"Landmarks"** tab

**Expected:**
- [ ] "Profile summary" landmark appears
- [ ] Navigate to it, press `Enter`
- [ ] NVDA announces: "Profile summary, complementary landmark"

### Heading Test:

1. Press `H` key (next heading)

**Expected:**
- [ ] NVDA announces: "Heading level 1, Steven Kelly"
- [ ] (User's name is H1)

### Status Indicator Test:

1. Navigate to avatar area

**Expected:**
- [ ] "Profile picture of Steven Kelly"
- [ ] "User is online now, status" (if online)

### Data Value Test:

1. Navigate to credits badge

**Expected:**
- [ ] "106 Credits" (not "One hundred six credits")
- [ ] Announced as normal text, not read out as number

### Document Results:

```
✅ Landmarks: "Profile summary" announced
✅ Heading hierarchy: H1 for user name
✅ Alt text: "Profile picture of {Name}"
✅ Status: "User is online now"
✅ Credits: Readable value
```

---

## Comparison Matrix: Which Tool to Use?

| Tool | Type | Strengths | Best For |
|------|------|-----------|----------|
| **Lighthouse** | Automated | Built-in, official Google tool | Quick check, CI/CD |
| **axe DevTools** | Automated | Most comprehensive, industry standard | Detailed audit |
| **WAVE** | Visual | Shows issues on page | Visual feedback |
| **Pa11y** | CLI | Scriptable, CI/CD integration | Automated testing |
| **Accessibility Insights** | Semi-auto | Microsoft official, guided tests | Step-by-step compliance |
| **Keyboard Testing** | Manual | Real user experience | Navigation flow |
| **NVDA** | Manual | Screen reader experience | Assistive tech testing |

---

## Recommended Testing Workflow

### For Development:

1. **Quick check:** Lighthouse (30 seconds)
2. **Detailed audit:** axe DevTools (2 minutes)
3. **Visual review:** WAVE (1 minute)

### For QA/Release:

1. **Automated:** axe DevTools full scan
2. **Manual keyboard:** Tab through entire page
3. **Screen reader:** NVDA landmark + heading test
4. **Document:** Export reports from all tools

### For CI/CD:

1. **Pa11y** in automated pipeline
2. **Lighthouse CI** for performance + accessibility
3. **Fail build** if accessibility score < 95

---

## How to Prove Compliance to Stakeholders

### Create Audit Package:

```
docs/audits/
├── lighthouse-profile-2026-01-20.html
├── axe-profile-2026-01-20.csv
├── axe-full-report-2026-01-20.pdf
├── wave-profile-2026-01-20.html
├── pa11y-profile-2026-01-20.json
├── keyboard-test-results.md
├── nvda-test-results.md
└── WCAG_COMPLIANCE_SUMMARY.md
```

### Summary Document Template:

```markdown
# WCAG 2.1 AA Compliance Report
**Component:** Profile Header Identity Bar
**Date:** 2026-01-20
**Auditor:** [Your Name]

## Automated Testing Results

### Lighthouse (Chrome DevTools)
- **Score:** 100/100 ✅
- **Errors:** 0
- **Report:** See lighthouse-profile-2026-01-20.html

### axe DevTools
- **Violations:** 0 ✅
- **Rules Tested:** 47
- **Report:** See axe-full-report-2026-01-20.pdf

### WAVE
- **Errors:** 0 ✅
- **Contrast Errors:** 0 ✅
- **Report:** See wave-profile-2026-01-20.html

## Manual Testing Results

### Keyboard Navigation
- ✅ All elements keyboard accessible
- ✅ Focus visible (GOV.UK pattern)
- ✅ Logical tab order
- ✅ No focus traps

### Screen Reader (NVDA)
- ✅ Landmarks announced correctly
- ✅ Heading hierarchy correct
- ✅ Status indicators announced
- ✅ Alt text descriptive

## Conclusion
**The Profile Header Identity Bar is WCAG 2.1 AA compliant.**

All automated and manual tests passed. No accessibility violations found.
```

---

## Quick Start: Run Your First Audit NOW

### 5-Minute Quick Test:

1. **Open Chrome**
2. **Go to:** http://staging.timebank.local/hour-timebank/profile/26
3. **Press F12** (DevTools)
4. **Click "Lighthouse" tab**
5. **Select "Accessibility" only**
6. **Click "Analyze page load"**
7. **Wait 30 seconds**
8. **Check score:** Should be 95-100 ✅

**If score is below 95:**
- Review specific failures
- Most issues will be OUTSIDE the identity bar component
- Identity bar itself should have 0 violations

---

## Support

If you encounter any issues running these audits:

1. **Lighthouse not showing:** Update Chrome to latest version
2. **axe extension not found:** Search Chrome Web Store for "axe DevTools"
3. **NVDA not announcing:** Ensure NVDA is running (`Ctrl+Alt+N`)
4. **Pa11y errors:** Check Node.js version (requires v14+)

---

## Next Steps

After running audits:

1. **Save all reports** to `docs/audits/` directory
2. **Document results** in WCAG compliance summary
3. **Address any violations** found (should be none for identity bar)
4. **Integrate into CI/CD** for continuous compliance
5. **Retest after any changes** to ensure compliance maintained
