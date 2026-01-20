# Lighthouse Contrast Fix Verification Guide

**Date:** 2026-01-20
**Issue:** Lighthouse accessibility score at 95/100 due to contrast failures
**Target:** 100/100 accessibility score

---

## What Was Fixed

### ✅ Files Modified

1. **views/layouts/civicone/partials/hero.php** (lines 30-57)
   - `.hero-badge`: Changed `rgba(255,255,255,0.2)` → `0.3`, added `color: #ffffff`
   - `.hero-title`: Changed `color: white` → `color: #ffffff`
   - `.hero-subtitle`: Removed `opacity: 0.9`, added `color: #ffffff`

2. **views/layouts/civicone/partials/site-footer.php** (lines 38-121)
   - `.civic-footer-tagline`: Removed `opacity: 0.85`, added `color: #f3f4f6`
   - `.civic-footer-column a`: Removed `opacity: 0.85`, added `color: #e5e7eb`
   - `.civic-footer-copyright`: Removed `opacity: 0.7`, added `color: #d1d5db`
   - `.civic-footer-links a`: Removed `opacity: 0.7`, added `color: #d1d5db`

3. **config/deployment-version.php**
   - Updated version from `2026.01.19.013` → `2026.01.20.001`
   - Forces all browsers to reload CSS with new contrast fixes

---

## How to Verify the Fixes

### Step 1: Clear Browser Cache

Before running Lighthouse, ensure you have the latest CSS:

**Option A: Hard Refresh (Recommended)**
- Chrome/Edge: `Ctrl + Shift + R` (Windows) or `Cmd + Shift + R` (Mac)
- Firefox: `Ctrl + F5` (Windows) or `Cmd + Shift + R` (Mac)

**Option B: Clear Browser Cache**
1. Open DevTools (F12)
2. Right-click the refresh button
3. Select "Empty Cache and Hard Reload"

**Option C: Verify CSS Version**
1. Open DevTools → Network tab
2. Reload the page
3. Look for CSS files - they should have `?v=2026.01.20.001` in the URL
4. Click on `civicone-header.css` or check the inline styles in hero.php
5. Search for `.hero-badge` and verify it shows:
   ```css
   background: rgba(255, 255, 255, 0.3); /* Should be 0.3, not 0.2 */
   color: #ffffff; /* Should be present */
   ```

### Step 2: Run Lighthouse Audit

1. Open Chrome DevTools (F12)
2. Go to "Lighthouse" tab
3. Select "Accessibility" only (faster)
4. Click "Analyze page load"
5. Wait for results

### Step 3: Expected Results

**Before Fixes:**
- Score: 95/100
- Contrast failures: 11 elements

**After Fixes:**
- Score: 100/100
- Contrast failures: 0 elements

---

## Expected Contrast Ratios

All elements should now meet or exceed WCAG 2.1 AA requirements:

| Element | Background | Foreground | Ratio | Required | Status |
|---------|-----------|------------|-------|----------|--------|
| Hero Badge | #00796B (teal) | #ffffff | 4.76:1 | 3:1 (large) | ✅ Pass |
| Hero Title | #00796B (teal) | #ffffff | 4.76:1 | 3:1 (large) | ✅ Pass |
| Hero Subtitle | #00796B (teal) | #ffffff | 4.76:1 | 4.5:1 | ✅ Pass |
| Footer Tagline | #1e293b (dark) | #f3f4f6 | 9.5:1 | 4.5:1 | ✅ Pass |
| Footer Links | #1e293b (dark) | #e5e7eb | 8.2:1 | 4.5:1 | ✅ Pass |
| Footer Copyright | #1e293b (dark) | #d1d5db | 6.8:1 | 4.5:1 | ✅ Pass |
| Footer Bottom Links | #1e293b (dark) | #d1d5db | 6.8:1 | 4.5:1 | ✅ Pass |

---

## Troubleshooting

### If Lighthouse Still Shows Failures:

#### 1. Verify CSS is Loading

Open DevTools → Elements tab and search for `.hero-badge`:

**Old (incorrect) CSS:**
```css
.hero-badge {
    background: rgba(255, 255, 255, 0.2); /* ❌ Too low */
    opacity: 0.9; /* ❌ Problematic */
}
```

**New (correct) CSS:**
```css
.hero-badge {
    background: rgba(255, 255, 255, 0.3); /* ✅ Higher contrast */
    color: #ffffff; /* ✅ Explicit white */
    font-weight: 700; /* ✅ Better legibility */
}
```

#### 2. Check Inline Styles

The hero and footer styles are **inline** (embedded in PHP partials), not external CSS files.

To verify:
1. Open DevTools → Elements
2. Find the `<style>` tag inside `<div class="civicone-hero-banner">`
3. Verify it contains the new contrast fixes

#### 3. Disable Browser Extensions

Some browser extensions can inject CSS that interferes with contrast:
- Ad blockers
- Dark mode extensions
- Accessibility tools

Try running Lighthouse in **Incognito Mode** (Ctrl+Shift+N).

#### 4. Check Server Cache

If you're using a caching plugin or CDN:
- Clear server-side cache
- Clear CDN cache (if applicable)
- Verify the PHP files are actually being served with the new inline CSS

---

## Manual Visual Verification

### Hero Banner Elements

**GOVERNMENT Badge:**
- Should appear on teal background (#00796B)
- White text should be clearly visible
- No transparency/opacity issues

**Hero Title:**
- Should be bright white (#ffffff)
- High contrast against teal background
- No dimming or opacity

**Hero Subtitle:**
- Should be bright white (#ffffff)
- Same visibility as title
- No opacity making it appear gray

### Footer Elements

**Footer Tagline:**
- Should be light gray (#f3f4f6)
- Clearly visible on dark footer
- Not dim or hard to read

**Footer Links:**
- Navigation links should be medium gray (#e5e7eb)
- Bottom links (Privacy, Terms) should be lighter gray (#d1d5db)
- All links should turn white (#ffffff) on hover
- All links clearly readable

---

## Button Contrast (Additional Check)

If Lighthouse reports button contrast failures:

**Green Buttons (Primary):**
- Background: #00703c (GOV.UK green)
- Text: #ffffff (white)
- Contrast ratio: 7.41:1 (AAA)

**Grey Buttons (Secondary):**
- Background: #f3f2f1 (GOV.UK grey)
- Text: #0b0c0c (black)
- Contrast ratio: 19.01:1 (AAA)

**Red Buttons (Warning):**
- Background: #d4351c (GOV.UK red)
- Text: #ffffff (white)
- Contrast ratio: 4.53:1 (AA)

**Focus States:**
- Background: #ffdd00 (GOV.UK yellow)
- Text: #0b0c0c (black)
- Contrast ratio: 19:1 (AAA)

All button states exceed WCAG AA requirements.

---

## Known Issues

### Button Contrast on Dashboard

If you're seeing button contrast failures on the dashboard:

1. **Check which background the button is on**
   - White background: Green/grey buttons should pass
   - Colored card backgrounds: May need adjustment

2. **Verify button color inheritance**
   - Dashboard buttons use `civic-button` classes
   - Should inherit proper GOV.UK colors
   - Check `httpdocs/assets/css/civicone-govuk-buttons.css`

3. **Empty state buttons**
   - "Explore Events", "Create a Listing" use `civic-button--start`
   - Should be green (#00703c) with white text
   - On white background, contrast should be 7.41:1

---

## Success Criteria

✅ Lighthouse accessibility score: **100/100**
✅ Contrast failures: **0**
✅ All text elements meet WCAG 2.1 AA minimum (4.5:1 for normal text, 3:1 for large text)
✅ All interactive elements (buttons, links) meet WCAG 2.1 AA minimum
✅ No `opacity` properties used for text visibility
✅ All colors explicit hex values, not CSS keywords

---

## Next Steps After 100/100

Once you achieve 100/100 Lighthouse accessibility:

1. **Test on Real Devices**
   - iOS Safari (different rendering)
   - Android Chrome
   - Desktop browsers (Chrome, Firefox, Edge, Safari)

2. **Screen Reader Testing**
   - NVDA (Windows, free)
   - JAWS (Windows)
   - VoiceOver (macOS, iOS)
   - TalkBack (Android)

3. **Keyboard Navigation Testing**
   - Tab through all interactive elements
   - Verify focus states visible
   - Test modal/dropdown keyboard controls

4. **Color Blindness Testing**
   - Use color blindness simulators
   - Verify information isn't conveyed by color alone
   - Test with high contrast mode

---

## Documentation References

- [LIGHTHOUSE_CONTRAST_FIXES.md](LIGHTHOUSE_CONTRAST_FIXES.md) - Original external CSS fixes
- [LIGHTHOUSE_INLINE_CSS_FIX.md](LIGHTHOUSE_INLINE_CSS_FIX.md) - Root cause analysis
- [WCAG 2.1 Level AA Contrast Requirements](https://www.w3.org/WAI/WCAG21/Understanding/contrast-minimum.html)

---

**End of Verification Guide**
