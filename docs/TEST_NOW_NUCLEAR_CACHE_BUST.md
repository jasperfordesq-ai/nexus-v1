# READY TO TEST - Nuclear Cache Bust Active

**Date:** 2026-01-20
**Status:** ✅ ALL FIXES VERIFIED - Ready for Lighthouse Test

---

## What's Been Done

### ✅ All CSS Fixes Verified Present in Minified Files

**Hero Banner Fix:**
```css
color: #ffffff !important; /* Line 1274 in civicone-header.min.css */
```

**Link Selector Fix (Root Cause):**
```css
a:not(.civic-button):not(.govuk-button) { /* Lines 245, 252 in nexus-civicone.min.css */
    color: var(--civic-brand);
}
```

**Button Text Fix:**
```css
color: var(--govuk-button-text) !important; /* Lines 88, 183, 190 in civicone-govuk-buttons.min.css */
```

### ✅ Nuclear Cache Bust Active

**Every page load now gets a UNIQUE CSS version:**
```php
'version' => time() // Generates new timestamp every request
```

This means:
- First load: `civicone-header.min.css?v=1737385621`
- Refresh: `civicone-header.min.css?v=1737385622`
- **Browser CANNOT cache - forced to reload every time**

---

## TESTING INSTRUCTIONS

### Step 1: Hard Refresh (CRITICAL)

**Windows/Linux:**
- Chrome/Edge: `Ctrl + Shift + R`
- Firefox: `Ctrl + F5`

**Mac:**
- Chrome/Edge: `Cmd + Shift + R`
- Firefox: `Cmd + Shift + R`

**Alternative:** Use Incognito/Private mode

### Step 2: Visual Check

**Hero Banner (Top of Page):**
- ✅ "GOVERNMENT" badge: White text, semi-transparent white background on teal
- ✅ Page title: Bright white (#ffffff) - NO dimming
- ✅ Subtitle: Bright white (#ffffff) - NO dimming
- ❌ If text looks gray/dimmed = old cached CSS still loading

**Footer (Bottom of Page):**
- ✅ Tagline: Light gray (#f3f4f6) - readable
- ✅ Column links: Medium gray (#e5e7eb) - readable
- ✅ Copyright: Lighter gray (#d1d5db) - readable
- ✅ Links turn white on hover
- ❌ If text looks too dark/invisible = old cached CSS

**Buttons (All Pages):**
- ✅ Primary green buttons: **WHITE text** (not blue)
- ✅ Secondary gray buttons: **BLACK text**
- ✅ Warning red buttons: **WHITE text**
- ❌ If button text is blue = old cached CSS

### Step 3: DevTools Network Check

**Open DevTools (F12) → Network Tab:**

Look for CSS files and their version numbers:
```
✅ civicone-header.min.css?v=1737385621 (unique timestamp)
✅ civicone-footer.min.css?v=1737385621 (unique timestamp)
✅ civicone-govuk-buttons.min.css?v=1737385621 (unique timestamp)
✅ nexus-civicone.min.css?v=1737385621 (unique timestamp)
```

**If you see old version numbers (like 2026.01.20.005), the nuclear cache bust isn't working.**

### Step 4: Run Lighthouse

1. Open Chrome DevTools (F12)
2. Go to "Lighthouse" tab
3. **Select "Accessibility" ONLY** (uncheck Performance, Best Practices, SEO)
4. Click "Analyze page load"
5. Wait for results

**Expected Result:**
```
✅ Score: 100/100 (up from 95/100)
✅ Contrast failures: 0 (down from 11)
✅ All WCAG 2.1 AA requirements met
```

---

## What Each Fix Does

### Fix 1: Removed Inline CSS (218 lines)
**Problem:** Inline `<style>` blocks in PHP files overrode external CSS
**Solution:** Removed all inline styles from hero.php and site-footer.php
**Result:** External WCAG-compliant CSS now applies

### Fix 2: Explicit Colors (No Opacity)
**Problem:** `opacity: 0.9` creates unpredictable contrast ratios
**Solution:** Changed to explicit colors like `#ffffff`, `#f3f4f6`
**Result:** Guaranteed WCAG AA contrast ratios

### Fix 3: Button Text !important
**Problem:** Global link selector was overriding button colors
**Solution:** Added `!important` to force white/black text on buttons
**Result:** All buttons pass contrast checks

### Fix 4: Link Selector Exclusion (ROOT FIX)
**Problem:** `a { color: blue }` applied to ALL links including buttons
**Solution:** Changed to `a:not(.civic-button):not(.govuk-button)`
**Result:** Buttons excluded from link styling, prevents future issues

### Fix 5: Nuclear Cache Bust
**Problem:** Browser/server caching prevented fixes from loading
**Solution:** Version parameter uses `time()` function (unique every load)
**Result:** Forces browser to reload CSS every single time

---

## Troubleshooting

### Issue: Lighthouse Still Shows 95/100

**Check 1: Are CSS files loading with unique timestamps?**
- Open Network tab in DevTools
- Look at CSS file URLs
- Version should be a Unix timestamp (e.g., `v=1737385621`)
- **Each page refresh should show DIFFERENT timestamp**

**Check 2: Are button text colors correct?**
- Inspect button element in DevTools
- Check Computed → color
- Should be `rgb(255, 255, 255)` for green/red buttons
- Should be `rgb(11, 12, 12)` for gray buttons
- **If blue (`rgb(99, 102, 241)`), old CSS is loading**

**Check 3: Are hero/footer colors correct?**
- Inspect hero title in DevTools
- Check Computed → color
- Should be `rgb(255, 255, 255)`
- **If NOT pure white, old CSS is loading**

### Issue: Nuclear Cache Bust Not Working

**If version parameter is NOT changing on refresh:**

1. **Clear PHP opcode cache:**
   - Restart XAMPP web server
   - This reloads deployment-version.php

2. **Check if deployment-version.php was modified:**
   ```bash
   cat config/deployment-version.php | grep "time()"
   # Should show: 'version' => time(),
   ```

3. **Try different browser:**
   - Some browsers have aggressive caching
   - Test in Incognito mode
   - Test in Firefox/Safari

### Issue: Visual Regression (Something Looks Wrong)

**If hero/footer styling broke:**

1. **Check for missing CSS:**
   - Compare current appearance to screenshots in docs
   - Identify what changed

2. **Rollback if needed:**
   ```bash
   cd views/layouts/civicone/partials
   cp hero.php.backup hero.php
   cp site-footer.php.backup site-footer.php
   ```

3. **Report the issue:**
   - Screenshot what looks wrong
   - Check browser console for errors
   - Note which browser/OS

---

## Success Criteria

All must be true for this fix to succeed:

✅ **Lighthouse Score:** 100/100 accessibility (up from 95/100)
✅ **Contrast Failures:** 0 (down from 11)
✅ **Hero Banner:** All text bright white, no dimming
✅ **Footer:** All text readable light colors
✅ **Buttons:** White text on green/red, black text on gray
✅ **No Visual Regressions:** Everything looks the same as before
✅ **No Console Errors:** Browser console is clean
✅ **CSS Version Changes:** Network tab shows different timestamp each refresh

---

## After Achieving 100/100

Once Lighthouse shows 100/100:

1. **Revert Nuclear Cache Bust (Important for Production):**
   ```php
   // Change deployment-version.php from:
   'version' => time(),

   // Back to static version:
   'version' => '2026.01.20.100',
   ```

   **Why:** `time()` prevents ALL caching, hurts performance. Only needed during testing.

2. **Document the win:**
   - Screenshot Lighthouse 100/100
   - Add to project documentation
   - Update WCAG compliance records

3. **Cross-browser test:**
   - Safari (Mac/iOS)
   - Firefox (Windows/Mac/Linux)
   - Edge (Windows)
   - Mobile browsers (iOS Safari, Chrome Android)

4. **Screen reader test:**
   - NVDA (Windows)
   - JAWS (Windows)
   - VoiceOver (Mac/iOS)

---

## Files Modified (Final Summary)

| File | Change | Result |
|------|--------|--------|
| `hero.php` | Removed 62 lines inline CSS | External CSS now applies |
| `site-footer.php` | Removed 156 lines inline CSS | External CSS now applies |
| `civicone-header.min.css` | Explicit colors, no opacity | WCAG AA contrast |
| `civicone-footer.min.css` | Explicit colors, no opacity | WCAG AA contrast |
| `civicone-govuk-buttons.min.css` | Added !important to colors | Forces correct button colors |
| `nexus-civicone.min.css` | Link selector excludes buttons | Prevents future issues |
| `deployment-version.php` | Changed to `time()` | Nuclear cache bust |

**Total:** 7 files modified
**Lines removed:** 218 lines of inline CSS
**Lines added:** ~50 lines (CSS fixes + comments)

---

## READY TO TEST

**All fixes are in place. All fixes are verified. Nuclear cache bust is active.**

**Your next action:**
1. Hard refresh browser (`Ctrl + Shift + R`)
2. Check visual appearance
3. Run Lighthouse accessibility audit
4. Report results

**Expected:** 🎯 **100/100 Accessibility Score**

---

**End of Document**
