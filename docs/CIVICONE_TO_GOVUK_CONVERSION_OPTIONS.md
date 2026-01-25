# CivicOne to GOV.UK Frontend 100% Conversion - All Options Report

**Date:** 2026-01-25
**Current Compliance:** 92/100 (A- Grade)
**Target:** 100/100 (Perfect Match)
**GOV.UK Frontend Version:** 6.0.0-beta.2

---

## Executive Summary

**Current Status:**
- 149 CivicOne CSS files (plain CSS)
- 217 CivicOne PHP templates
- 92/100 GOV.UK compliance
- Custom enhancements (NavigationConfig, categorized dropdown)

**GOV.UK Frontend:**
- 73 SCSS component files
- 40+ Nunjucks templates
- 100% WCAG 2.2 AA compliant
- Official UK government standard

**Gap to 100%:** 8 points (missing exact breakpoints, some micro-spacing differences, custom extensions)

---

## 🎯 OPTION 1: Full NPM Package Integration (COMPLETE REBUILD)

### Overview
Install GOV.UK Frontend as an npm package and rebuild CivicOne entirely using official SCSS and compiled CSS.

### Implementation Steps

#### 1.1 Install GOV.UK Frontend Package
```bash
cd /c/xampp/htdocs/staging
npm install govuk-frontend@6.0.0-beta.2
```

#### 1.2 Install Sass Compiler
```bash
npm install sass --save-dev
npm install postcss postcss-cli autoprefixer --save-dev
```

#### 1.3 Create SCSS Entry File
**New file:** `httpdocs/assets/scss/civicone-govuk.scss`
```scss
// Import all GOV.UK Frontend
@import "node_modules/govuk-frontend/dist/govuk/all";

// Custom CivicOne extensions
.civicone-nav-dropdown-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: govuk-spacing(5) govuk-spacing(6);
}

.civicone-nav-dropdown-heading {
  @include govuk-font($size: 16, $weight: bold);
  color: govuk-colour("dark-grey");
  border-bottom: 2px solid govuk-colour("mid-grey");
  margin-bottom: govuk-spacing(2);
  padding-bottom: govuk-spacing(2);
}
```

#### 1.4 Build Script
**Add to `package.json`:**
```json
"scripts": {
  "build:scss": "sass httpdocs/assets/scss/civicone-govuk.scss httpdocs/assets/css/civicone-govuk.css --style=expanded",
  "build:scss:min": "sass httpdocs/assets/scss/civicone-govuk.scss httpdocs/assets/css/civicone-govuk.min.css --style=compressed",
  "watch:scss": "sass --watch httpdocs/assets/scss:httpdocs/assets/css"
}
```

#### 1.5 Convert Templates (Nunjucks → PHP)
**Manual conversion** of all 40+ components from `.njk` to `.php`

**Example - Button Component:**
```php
<!-- Before (Nunjucks) -->
{{ govukButton({
  text: "Save and continue"
}) }}

<!-- After (PHP) -->
<button class="govuk-button" data-module="govuk-button">
  Save and continue
</button>
```

#### 1.6 Replace All CivicOne CSS Files
```bash
# Remove old files
rm httpdocs/assets/css/civicone-*.css

# Use compiled GOV.UK CSS
cp node_modules/govuk-frontend/dist/govuk/govuk-frontend.min.css httpdocs/assets/css/
```

### Pros ✅
- ✅ **100% GOV.UK compliance** - Official package
- ✅ **Future updates easy** - `npm update govuk-frontend`
- ✅ **All components available** - 40+ official components
- ✅ **Official JavaScript** - Validated behaviors
- ✅ **Design tokens** - Access to all Sass variables
- ✅ **Best practices** - Official GOV.UK patterns

### Cons ❌
- ❌ **MASSIVE effort** - 200-400 hours estimated
- ❌ **All templates rewritten** - 217 PHP files need conversion
- ❌ **New build pipeline** - Sass compilation required
- ❌ **Breaking changes** - Entire CivicOne theme rebuild
- ❌ **Lost customizations** - NavigationConfig, dropdowns need rebuilding
- ❌ **Testing required** - Every page, every component
- ❌ **Maintenance burden** - Keep up with GOV.UK updates

### Estimated Effort
- **Time:** 200-400 hours (5-10 weeks full-time)
- **Complexity:** 🔴 EXTREME
- **Risk:** 🔴 HIGH (Breaking all existing pages)
- **Cost:** ~£20,000-£40,000 (if outsourced)

### Compliance Achievement
- **Result:** 100/100 (Perfect match)
- **Is it worth it?** ⚠️ **Probably not** - 8% improvement for massive effort

---

## 🔧 OPTION 2: Hybrid Approach (SCSS Import + CSS Output)

### Overview
Use GOV.UK SCSS as a library, compile to CSS, keep PHP templates, selectively adopt components.

### Implementation Steps

#### 2.1 Install and Configure
```bash
npm install govuk-frontend@6.0.0-beta.2
npm install sass --save-dev
```

#### 2.2 Create Selective Import File
**New file:** `httpdocs/assets/scss/civicone-selective.scss`
```scss
// Import only what we need
@import "node_modules/govuk-frontend/dist/govuk/settings/all";
@import "node_modules/govuk-frontend/dist/govuk/tools/all";
@import "node_modules/govuk-frontend/dist/govuk/helpers/all";

// Import specific components
@import "node_modules/govuk-frontend/dist/govuk/components/service-navigation/service-navigation";
@import "node_modules/govuk-frontend/dist/govuk/components/button/button";
@import "node_modules/govuk-frontend/dist/govuk/components/header/header";
@import "node_modules/govuk-frontend/dist/govuk/components/footer/footer";

// Keep our custom extensions
@import "civicone-custom-dropdown";
@import "civicone-navigation-config";
```

#### 2.3 Gradually Replace Components
**Phase 1:** Service Navigation (✅ Already 92% done)
**Phase 2:** Buttons
**Phase 3:** Forms
**Phase 4:** Header/Footer
**Phase 5:** Remaining components

#### 2.4 Keep PHP Templates (No Nunjucks Conversion)
Continue using PHP templates, just update classes to match GOV.UK exactly.

### Pros ✅
- ✅ **Gradual migration** - Component by component
- ✅ **Keep PHP templates** - No Nunjucks conversion
- ✅ **Access to Sass variables** - Use design tokens
- ✅ **Less risky** - Can roll back any component
- ✅ **Keep customizations** - NavigationConfig stays
- ✅ **Selective adoption** - Only import what we need

### Cons ❌
- ❌ **Still requires Sass** - Build pipeline needed
- ❌ **Partial coverage** - Not all 40+ components
- ❌ **Maintenance** - Track which components are GOV.UK vs custom
- ❌ **Not 100%** - Will still have gaps
- ❌ **Complexity** - Mixing two systems

### Estimated Effort
- **Time:** 80-120 hours (2-3 weeks)
- **Complexity:** 🟡 MODERATE
- **Risk:** 🟡 MEDIUM
- **Cost:** ~£8,000-£12,000 (if outsourced)

### Compliance Achievement
- **Result:** 95-98/100 (A+ Grade)
- **Is it worth it?** ⚠️ **Maybe** - Decent improvement, moderate effort

---

## 📐 OPTION 3: Manual Value Extraction (CURRENT APPROACH+)

### Overview
Continue current approach but extract ALL exact values from GOV.UK SCSS to reach 100%.

### Implementation Steps

#### 3.1 Systematic Component Audit
For each CivicOne component:
1. Open GOV.UK demo: http://localhost:3000/components/[component]
2. Read SCSS: `packages/govuk-frontend/src/govuk/components/[component]/_index.scss`
3. Extract ALL values (not just major ones)
4. Update our CSS with exact values
5. Test side-by-side

#### 3.2 Extract Missing Values

**What we're missing for 100%:**

**Responsive Breakpoints:**
```scss
// GOV.UK uses specific breakpoints
$breakpoint-mobile: 320px;
$breakpoint-tablet: 641px;
$breakpoint-desktop: 769px;

// We need to match these exactly in media queries
@media (max-width: 640px) { /* mobile */ }
@media (min-width: 641px) and (max-width: 768px) { /* tablet */ }
@media (min-width: 769px) { /* desktop */ }
```

**Micro-spacing:**
```css
/* GOV.UK has very specific spacing in edge cases */
.govuk-service-navigation__item--active {
  padding-bottom: calc(15px - 5px); /* 10px (padding minus border) */
}

/* We currently use: */
.govuk-service-navigation__item--active {
  padding-bottom: 15px; /* Missing the border subtraction */
}
```

**Focus States:**
```css
/* GOV.UK has very specific focus styles */
.govuk-service-navigation__link:focus {
  outline: 3px solid #ffdd00;
  outline-offset: 0;
  background-color: #ffdd00;
  box-shadow: 0 -2px #ffdd00, 0 4px #0b0c0c;
  color: #0b0c0c;
}

/* We're close but missing box-shadow details */
```

**Hover Transitions:**
```css
/* GOV.UK uses specific easing */
.govuk-service-navigation__link {
  transition: border-color 0.15s ease-in-out;
}

/* We have this but need to verify ALL interactive elements */
```

#### 3.3 Create Exact Match Checklist

**Per Component:**
- [ ] Font sizes (all breakpoints)
- [ ] Line heights (fractional values like 29/19)
- [ ] Padding (top, right, bottom, left - all breakpoints)
- [ ] Margin (all sides, all breakpoints)
- [ ] Border widths
- [ ] Border colors
- [ ] Background colors
- [ ] Text colors
- [ ] Hover states
- [ ] Focus states
- [ ] Active states
- [ ] Disabled states
- [ ] Responsive breakpoints
- [ ] Transitions/animations
- [ ] z-index values
- [ ] Box shadows

#### 3.4 Update Documentation Headers

**Add to every CSS file:**
```css
/**
 * Component: [Name]
 *
 * SOURCE OF TRUTH: GOV.UK Frontend GitHub Repository
 * Component: https://github.com/alphagov/govuk-frontend/tree/main/packages/govuk-frontend/src/govuk/components/[component]
 * Styles: https://github.com/alphagov/govuk-frontend/blob/main/packages/govuk-frontend/src/govuk/components/[component]/_index.scss
 *
 * EXACT VALUES EXTRACTED (verified 2026-01-25):
 * [List all values with line numbers from SCSS]
 *
 * COMPLIANCE: 100/100
 * Last synced: 2026-01-25
 * Verified against: GOV.UK Frontend v6.0.0-beta.2
 */
```

### Pros ✅
- ✅ **No build changes** - Keep plain CSS
- ✅ **No template changes** - Keep PHP
- ✅ **Keep customizations** - NavigationConfig, dropdowns
- ✅ **Incremental** - Fix one component at a time
- ✅ **Low risk** - Easy to test and verify
- ✅ **Full control** - Know exactly what changed

### Cons ❌
- ❌ **Manual work** - Extract each value by hand
- ❌ **Tedious** - 149 files to audit
- ❌ **Maintenance** - Manual updates when GOV.UK changes
- ❌ **Documentation heavy** - Must document every value
- ❌ **Potential for errors** - Manual transcription mistakes

### Estimated Effort
- **Time:** 40-60 hours (1-1.5 weeks)
- **Complexity:** 🟢 LOW-MODERATE
- **Risk:** 🟢 LOW
- **Cost:** ~£4,000-£6,000 (if outsourced)

### Compliance Achievement
- **Result:** 98-100/100 (A+ to Perfect)
- **Is it worth it?** ✅ **YES** - Best effort-to-value ratio

---

## 🤖 OPTION 4: Automated SCSS-to-CSS Converter Tool

### Overview
Build a custom tool to automatically convert GOV.UK SCSS values to our CSS format.

### Implementation Steps

#### 4.1 Create Converter Script
**New file:** `scripts/govuk-scss-to-css-converter.js`
```javascript
const sass = require('sass');
const fs = require('fs');

// Parse GOV.UK SCSS
const scssFile = 'node_modules/govuk-frontend/dist/govuk/components/button/_index.scss';
const result = sass.compile(scssFile);

// Extract computed values
// Convert Sass variables to CSS custom properties
// Generate plain CSS output

// Example output:
// .govuk-button {
//   padding: 15px 20px; /* computed from govuk-spacing(3) govuk-spacing(4) */
//   font-size: 1.1875rem; /* computed from govuk-font(19) */
// }
```

#### 4.2 Limitations
- Sass functions like `govuk-spacing()` need runtime evaluation
- Mixins like `@include govuk-font()` need expansion
- Conditional logic needs interpretation
- Some values are context-dependent

### Pros ✅
- ✅ **Automated** - Less manual work
- ✅ **Consistent** - No transcription errors
- ✅ **Repeatable** - Can re-run for updates
- ✅ **Documentation** - Auto-generate comments with sources

### Cons ❌
- ❌ **Complex to build** - 20-40 hours to create tool
- ❌ **Limited value** - Still need manual verification
- ❌ **Maintenance** - Tool breaks when GOV.UK changes format
- ❌ **Not 100% accurate** - Some values need human interpretation

### Estimated Effort
- **Tool development:** 20-40 hours
- **Running conversions:** 10-20 hours
- **Verification:** 20-30 hours
- **Total:** 50-90 hours (1.5-2.5 weeks)
- **Complexity:** 🟡 MODERATE-HIGH
- **Risk:** 🟡 MEDIUM
- **Cost:** ~£5,000-£9,000 (if outsourced)

### Compliance Achievement
- **Result:** 95-99/100 (A+ Grade)
- **Is it worth it?** ⚠️ **Maybe** - Depends on future update frequency

---

## 🔍 OPTION 5: Component-by-Component Deep Dive

### Overview
Focus on highest-impact components first, achieve 100% on those, leave others at 92%.

### Priority Components (Based on Usage)

**Tier 1 - Critical (Achieve 100%):**
1. Service Navigation ✅ (Already 92%)
2. Button
3. Form inputs (text, textarea, select)
4. Header
5. Footer
6. Utility bar

**Tier 2 - Important (Achieve 95%+):**
7. Breadcrumbs
8. Notification banner
9. Error messages
10. Tables
11. Tabs

**Tier 3 - Nice to Have (Keep at 90%+):**
12. Accordion
13. Character count
14. Cookie banner
15. Date input
16. Pagination
17. Summary list
18. Tag
19. Task list
20. Warning text

### Implementation

**For each Tier 1 component:**
1. Full side-by-side comparison with demo
2. Extract ALL SCSS values
3. Test all states (default, hover, focus, active, disabled)
4. Test all breakpoints (mobile, tablet, desktop)
5. Validate with automated tools
6. Document 100% compliance

**For Tier 2/3:**
- Extract major values only
- Accept minor differences
- Focus on accessibility over pixel-perfect

### Pros ✅
- ✅ **Pragmatic** - Focus on what matters
- ✅ **Quick wins** - Tier 1 done in days
- ✅ **Measurable** - Clear metrics
- ✅ **User-focused** - Prioritize visible components

### Cons ❌
- ❌ **Not 100% overall** - Only Tier 1 perfect
- ❌ **Inconsistent** - Different compliance levels
- ❌ **Decision fatigue** - What belongs in which tier?

### Estimated Effort
- **Time:** 20-40 hours (Tier 1 only)
- **Complexity:** 🟢 LOW-MODERATE
- **Risk:** 🟢 LOW
- **Cost:** ~£2,000-£4,000 (if outsourced)

### Compliance Achievement
- **Overall:** 94-96/100 (A Grade)
- **Tier 1 components:** 100/100 each
- **Is it worth it?** ✅ **YES** - Best quick-win approach

---

## 📊 OPTION 6: Automated Visual Regression Testing

### Overview
Use visual regression testing to identify pixel differences, fix systematically.

### Implementation Steps

#### 6.1 Install Tools
```bash
npm install --save-dev playwright
npm install --save-dev pixelmatch
```

#### 6.2 Create Test Suite
**New file:** `tests/visual-regression/service-navigation.test.js`
```javascript
const { test, expect } = require('@playwright/test');

test('service navigation matches GOV.UK', async ({ page }) => {
  // Screenshot GOV.UK demo
  await page.goto('http://localhost:3000/components/service-navigation');
  const govukScreenshot = await page.screenshot();

  // Screenshot our CivicOne
  await page.goto('http://staging.timebank.local/hour-timebank/');
  const civicOneScreenshot = await page.screenshot();

  // Compare
  expect(civicOneScreenshot).toMatchSnapshot(govukScreenshot, {
    threshold: 0.01 // 1% difference allowed
  });
});
```

#### 6.3 Generate Diff Reports
Tool outputs highlighted differences showing:
- Font size discrepancies
- Spacing differences
- Color variations
- Border width differences

#### 6.4 Fix Systematically
Work through diff report, fix each identified issue.

### Pros ✅
- ✅ **Objective** - Pixel-perfect comparison
- ✅ **Comprehensive** - Catches everything
- ✅ **Automated** - Run on every change
- ✅ **Documentation** - Visual proof of compliance

### Cons ❌
- ❌ **False positives** - Content differences trigger errors
- ❌ **Setup time** - 10-20 hours to configure
- ❌ **Maintenance** - Screenshots need updating
- ❌ **Can't test custom features** - Our dropdown has no GOV.UK equivalent

### Estimated Effort
- **Setup:** 10-20 hours
- **Running tests:** 5-10 hours
- **Fixing issues:** 20-40 hours
- **Total:** 35-70 hours (1-2 weeks)
- **Complexity:** 🟡 MODERATE
- **Risk:** 🟢 LOW
- **Cost:** ~£3,500-£7,000 (if outsourced)

### Compliance Achievement
- **Result:** 98-100/100 (Perfect match)
- **Is it worth it?** ✅ **YES** - Great for ongoing compliance

---

## 🎨 OPTION 7: GOV.UK Prototype Kit Integration

### Overview
Use the official GOV.UK Prototype Kit to generate pages, then adapt to PHP.

### Implementation

#### 7.1 Install GOV.UK Prototype Kit
```bash
cd /c/xampp/htdocs
npx govuk-prototype-kit create govuk-prototype
cd govuk-prototype
npm start
```

#### 7.2 Build Pages in Prototype Kit
Use Nunjucks templates in prototype kit to build our pages.

#### 7.3 Export Generated HTML
Save rendered HTML and extract for PHP conversion.

#### 7.4 Convert HTML to PHP
Manual conversion of static HTML to dynamic PHP.

### Pros ✅
- ✅ **Official tool** - GOV.UK approved
- ✅ **100% accurate** - Generates perfect markup
- ✅ **Fast prototyping** - Quick to build pages
- ✅ **Examples included** - Lots of patterns

### Cons ❌
- ❌ **Static output** - Not dynamic like our PHP
- ❌ **Conversion overhead** - HTML → PHP manual work
- ❌ **Duplication** - Maintain prototype AND production
- ❌ **Limited value** - Same as manual extraction

### Estimated Effort
- **Time:** 60-100 hours
- **Complexity:** 🟡 MODERATE
- **Risk:** 🟡 MEDIUM
- **Cost:** ~£6,000-£10,000

### Compliance Achievement
- **Result:** 100/100 (Perfect markup)
- **Is it worth it?** ❌ **NO** - Too much overhead

---

## 📋 Comparison Matrix

| Option | Effort (Hours) | Cost | Compliance | Risk | Keep Customs? | Recommended? |
|--------|---------------|------|------------|------|---------------|--------------|
| **1. Full NPM Integration** | 200-400 | £20k-£40k | 100/100 | 🔴 HIGH | ❌ No | ❌ NO |
| **2. Hybrid SCSS** | 80-120 | £8k-£12k | 95-98/100 | 🟡 MEDIUM | ✅ Yes | ⚠️ MAYBE |
| **3. Manual Extraction+** | 40-60 | £4k-£6k | 98-100/100 | 🟢 LOW | ✅ Yes | ✅ **YES** |
| **4. Automated Converter** | 50-90 | £5k-£9k | 95-99/100 | 🟡 MEDIUM | ✅ Yes | ⚠️ MAYBE |
| **5. Component Priority** | 20-40 | £2k-£4k | 94-96/100 | 🟢 LOW | ✅ Yes | ✅ **YES** |
| **6. Visual Regression** | 35-70 | £3.5k-£7k | 98-100/100 | 🟢 LOW | ✅ Yes | ✅ **YES** |
| **7. Prototype Kit** | 60-100 | £6k-£10k | 100/100 | 🟡 MEDIUM | ❌ No | ❌ NO |

---

## 🏆 RECOMMENDED STRATEGY: Combined Approach

### Best of Both Worlds

**Phase 1: Quick Wins (Week 1)**
- ✅ **Option 5** - Component Priority Deep Dive
- Focus on Tier 1 components (6 components)
- Achieve 100% on service navigation, button, forms, header, footer
- **Effort:** 20-40 hours
- **Result:** 94-96/100 overall

**Phase 2: Systematic Improvement (Week 2-3)**
- ✅ **Option 3** - Manual Value Extraction for remaining components
- Extract exact values for all 149 CSS files
- Document every component thoroughly
- **Effort:** 40-60 hours
- **Result:** 98-100/100 overall

**Phase 3: Automated Validation (Ongoing)**
- ✅ **Option 6** - Visual Regression Testing
- Set up Playwright tests
- Run on every deployment
- Catch regressions automatically
- **Effort:** 35-70 hours setup, then automated
- **Result:** Maintained 98-100/100

**Total Combined Effort:** 95-170 hours (2.5-4.5 weeks)
**Total Cost:** ~£9,500-£17,000 (if outsourced)
**Final Compliance:** 98-100/100 with ongoing validation

---

## ⚖️ FINAL RECOMMENDATION

### For NEXUS Project: Option 3 + Option 5

**Rationale:**
1. We're already at 92/100 with current approach
2. 8-point gap is achievable without massive rebuild
3. Keep our valuable customizations (NavigationConfig, dropdown)
4. No build pipeline changes needed
5. Low risk, high value

### Action Plan

**Week 1:**
- [ ] Audit Tier 1 components (service-nav, button, forms, header, footer, utility-bar)
- [ ] Extract ALL exact values from GOV.UK SCSS
- [ ] Update CSS files with exact values
- [ ] Test side-by-side with demo
- [ ] Document 100% compliance per component

**Week 2:**
- [ ] Audit remaining high-use components
- [ ] Extract values systematically
- [ ] Update CSS files
- [ ] Test responsive breakpoints
- [ ] Verify all states (hover, focus, active, disabled)

**Week 3:**
- [ ] Final audit of all 149 CSS files
- [ ] Fix micro-spacing issues
- [ ] Validate focus states
- [ ] Test on multiple browsers
- [ ] Document compliance status

**Ongoing:**
- [ ] Set up visual regression tests (optional)
- [ ] Monitor GOV.UK Frontend updates
- [ ] Update when new versions release

### Expected Outcome
- **Compliance:** 98-100/100
- **Effort:** 40-60 hours
- **Cost:** £4,000-£6,000 (if outsourced)
- **Risk:** LOW
- **Customs:** PRESERVED
- **Timeline:** 1-1.5 weeks

---

## 🚫 What NOT to Do

❌ **Don't:** Rebuild everything from scratch (Option 1)
❌ **Don't:** Copy compiled CSS blindly
❌ **Don't:** Try to convert Nunjucks templates to PHP automatically
❌ **Don't:** Abandon our custom enhancements
❌ **Don't:** Change the build pipeline unless absolutely necessary

## ✅ What TO Do

✅ **Do:** Extract exact values manually (Option 3)
✅ **Do:** Focus on high-impact components first (Option 5)
✅ **Do:** Document every value with GOV.UK source
✅ **Do:** Test side-by-side with demo at http://localhost:3000
✅ **Do:** Keep NavigationConfig and custom dropdown
✅ **Do:** Maintain plain CSS (no Sass required)

---

## Conclusion

**92/100 → 100/100 is achievable** without massive changes.

The gap is primarily:
- Minor responsive breakpoint differences
- Micro-spacing edge cases (like padding minus border)
- Some focus state details
- A few transition timings

**All fixable with 40-60 hours of careful extraction and testing.**

The GOV.UK Frontend demo at http://localhost:3000 is your **visual source of truth** for validation.

**Recommended:** Option 3 (Manual Extraction+) + Option 5 (Component Priority) = 98-100/100 in 1-1.5 weeks.

---

**Report Date:** 2026-01-25
**Author:** Claude Code Analysis
**Next Steps:** Begin Phase 1 - Tier 1 Component Deep Dive
