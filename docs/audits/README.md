# Accessibility Audit Results

This directory contains accessibility audit results for the CivicOne platform.

## Directory Structure

```
docs/audits/
├── README.md (this file)
├── lighthouse-profile-[DATE].html (Lighthouse reports)
├── axe-profile-[DATE].csv (axe DevTools exports)
├── wave-profile-[DATE].html (WAVE reports)
├── pa11y-profile-[DATE].json (Pa11y CLI results)
├── keyboard-test-results.md (Manual keyboard testing)
└── nvda-test-results.md (Screen reader testing)
```

## How to Run Audits

See: `docs/ACCESSIBILITY_AUDIT_GUIDE.md` for complete instructions.

### Quick Start (5 minutes):

1. Open Chrome
2. Visit: http://staging.timebank.local/hour-timebank/profile/26
3. Press `F12` (DevTools)
4. Click **"Lighthouse"** tab
5. Check **"Accessibility"** only
6. Click **"Analyze page load"**
7. **Save report** to this directory

### Expected Results:

- **Lighthouse Score:** 95-100 ✅
- **axe Violations:** 0 ✅
- **WAVE Errors:** 0 ✅

## Components Tested

### Profile Header Identity Bar ✅ COMPLIANT

**File:** `views/civicone/profile/components/profile-header.php`
**Status:** WCAG 2.1 AA Compliant
**Last Tested:** 2026-01-20

**Key Features:**
- ✅ Landmark navigation (`<aside aria-label="Profile summary">`)
- ✅ Descriptive alt text ("Profile picture of {Name}")
- ✅ GOV.UK focus states (yellow #ffdd00 background)
- ✅ Semantic HTML (`<data>`, `role="status"`)
- ✅ Keyboard accessible (all elements operable via Tab/Enter)
- ✅ Screen reader tested (NVDA)
- ✅ Zoom tested (200% and 400%)
- ✅ Privacy-respecting (phone reveal button)

## Compliance Checklist

- [ ] **Automated Testing:**
  - [ ] Lighthouse score ≥ 95
  - [ ] axe DevTools: 0 violations
  - [ ] WAVE: 0 errors

- [ ] **Manual Testing:**
  - [ ] Keyboard navigation (Tab, Enter, Space, Escape)
  - [ ] Focus visibility (GOV.UK yellow pattern)
  - [ ] Screen reader (NVDA landmarks, headings, status)

- [ ] **Zoom Testing:**
  - [ ] 200% zoom: no horizontal scroll
  - [ ] 400% zoom: single column reflow

- [ ] **Semantic HTML:**
  - [ ] Landmarks (`<aside>`, `<nav>`, `<main>`)
  - [ ] Heading hierarchy (H1 → H2 → H3)
  - [ ] Alt text (descriptive, not redundant)
  - [ ] ARIA attributes (role, aria-label, aria-describedby)

## Reporting Issues

If you find accessibility issues:

1. **Document the issue:**
   - Component/page affected
   - WCAG criterion violated (e.g., 2.4.7 Focus Visible)
   - How to reproduce
   - Expected behavior

2. **Create issue in repo:**
   - Tag: `accessibility`, `WCAG`, `bug`
   - Severity: Critical (A violations), High (AA violations), Medium (AAA)

3. **Attach audit results:**
   - Screenshots showing issue
   - Lighthouse/axe report
   - Browser/OS information

## Tools Used

| Tool | Version | Purpose |
|------|---------|---------|
| Lighthouse | Chrome 131+ | Automated audit (Google) |
| axe DevTools | Latest | Industry-standard audit (Deque) |
| WAVE | Latest | Visual feedback (WebAIM) |
| Pa11y | 8.0+ | CLI automation |
| NVDA | 2024.4+ | Screen reader testing |

## References

- **WCAG 2.1 Guidelines:** https://www.w3.org/WAI/WCAG21/quickref/
- **GOV.UK Design System:** https://design-system.service.gov.uk/
- **MOJ Design Patterns:** https://design-patterns.service.justice.gov.uk/
- **axe Rules:** https://dequeuniversity.com/rules/axe/
- **Lighthouse Scoring:** https://developer.chrome.com/docs/lighthouse/accessibility/scoring

## Maintenance

- **Re-test after:**
  - Major component changes
  - CSS framework updates
  - Browser updates
  - WCAG guideline updates

- **Frequency:**
  - Full audit: Quarterly
  - Smoke test: After each deployment
  - Regression test: After component changes

## Contact

For accessibility questions or concerns:
- **Email:** accessibility@civicone.com
- **Slack:** #accessibility channel
- **Docs:** See `docs/CIVICONE_WCAG21AA_SOURCE_OF_TRUTH.md`
