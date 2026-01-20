# Preventing Button Contrast Issues on Future Pages

**Date:** 2026-01-20
**Issue:** Button text color being overridden by global link styles
**Status:** ✅ ROOT CAUSE FIXED

---

## What Was Happening

### The Problem

Every time we refactored a page with GOV.UK buttons styled as `<a>` tags, we encountered contrast failures because:

```html
<a href="/events" class="civic-button civic-button--start">
    Explore Events
</a>
```

**CSS Cascade Issue:**
1. Global link style: `a { color: var(--civic-brand); }` (blue)
2. Button style: `.civic-button { color: var(--govuk-button-text); }` (white)
3. **Element selector `a` has SAME specificity as class `.civic-button`**
4. **Whichever loads LAST wins** → Global link color often won
5. Result: Blue text on green button → **Contrast failure**

### Why This Would Repeat

Without fixing the root cause, **every page refactor would hit this issue**:
- Dashboard buttons ✅ Fixed with `!important`
- Events page buttons → Would fail
- Profile page buttons → Would fail
- Groups page buttons → Would fail
- etc.

---

## The Root Cause Fix

### What Changed

**File:** `httpdocs/assets/css/nexus-civicone.css` (lines 244-255)

**Before (Problematic):**
```css
a {
    color: var(--civic-brand); /* Blue - applies to ALL <a> tags including buttons */
    text-decoration: underline;
}

a:hover {
    color: var(--civic-brand-hover);
}
```

**After (Fixed):**
```css
/* Link styles - EXCLUDE buttons to prevent color override */
a:not(.civic-button):not(.govuk-button) {
    color: var(--civic-brand); /* Blue - only applies to non-button links */
    text-decoration: underline;
}

a:not(.civic-button):not(.govuk-button):hover {
    color: var(--civic-brand-hover);
}
```

### How This Prevents Future Issues

**Now when a button is an `<a>` tag:**
```html
<a href="/profile" class="civic-button">View Profile</a>
```

**The CSS cascade works correctly:**
1. Global link style: **SKIPPED** (`:not(.civic-button)` excludes it)
2. Button style: `.civic-button { color: #ffffff !important; }` → **APPLIES**
3. Result: White text on green button → **WCAG AA compliant ✅**

**Regular links still get blue color:**
```html
<a href="/help">Need help?</a>
```
→ Blue text, underlined (global link style applies)

---

## What We Also Did (Defense in Depth)

### Added `!important` to Button Colors

**File:** `httpdocs/assets/css/civicone-govuk-buttons.css`

Even with the root fix, we kept the `!important` declarations as a safety net:

```css
/* Primary button */
.civic-button {
    color: var(--govuk-button-text) !important; /* White */
}

/* Secondary button */
.civic-button--secondary {
    color: var(--govuk-button-secondary-text) !important; /* Black */
}

/* Warning button */
.civic-button--warning {
    color: var(--govuk-button-text) !important; /* White */
}
```

**Why both fixes?**
- **Root fix (`:not(.civic-button)`)** → Prevents the issue at the source
- **`!important` on buttons** → Guarantees button color wins even if other CSS tries to override

This is **defense in depth** - if one protection fails, the other catches it.

---

## Testing Future Page Refactors

### Before Deploying Any Page

**1. Visual Inspection**

Check all buttons have correct text colors:
- Primary (green): White text ✅
- Secondary (gray): Black text ✅
- Warning (red): White text ✅
- Start (green w/ arrow): White text ✅

**2. Lighthouse Audit**

Run accessibility audit on the page:
```
Expected: 100/100 accessibility
Expected: 0 contrast failures
```

**3. DevTools Inspect**

If any button looks wrong:
1. Right-click button → Inspect
2. Check Computed tab → color
3. Should see: `rgb(255, 255, 255)` (white) or `rgb(11, 12, 12)` (black)
4. If wrong, check Styles tab for what's overriding

---

## Checklist for New Pages

When refactoring a new page with GOV.UK buttons:

- [ ] Use correct button classes (`civic-button`, `civic-button--secondary`, etc.)
- [ ] Hard refresh browser after deploying (`Ctrl + Shift + R`)
- [ ] Visual check: All buttons have correct text color
- [ ] Run Lighthouse: Should show 100/100 accessibility
- [ ] Check DevTools: Button text color is white or black (not blue)

**If contrast failures appear:**

1. **First check:** Is `nexus-civicone.min.css` loaded with latest version?
2. **Second check:** Does it have `:not(.civic-button)` on line 245?
3. **Third check:** Are button colors using `!important`?
4. **If still failing:** Investigate with DevTools to find what's overriding

---

## Why This Won't Repeat

### Before This Fix

**Every page refactor:**
```
1. Create page with GOV.UK buttons
2. Run Lighthouse → 95/100, contrast failures
3. Add !important to button colors → 100/100
4. REPEAT for every new page ❌
```

### After This Fix

**Every page refactor:**
```
1. Create page with GOV.UK buttons
2. Run Lighthouse → 100/100 ✅
3. Done, no fixes needed ✅
```

The root cause is eliminated.

---

## Technical Explanation

### CSS Specificity

**Specificity values:**
- Element selector (`a`): `0,0,1`
- Class selector (`.civic-button`): `0,1,0`
- Class SHOULD win... but cascade order matters

**When specificity is CLOSE or EQUAL:**
- Whichever CSS loads **LAST** in the cascade wins
- `nexus-civicone.css` often loads after `civicone-govuk-buttons.css`
- Result: `a` selector won over `.civic-button`

**Using `:not()`:**
- `a:not(.civic-button)`: `0,1,1` (higher specificity)
- More importantly: **Excludes buttons entirely**
- Button styles apply without competition

**Using `!important`:**
- `.civic-button { color: #fff !important; }`: **ALWAYS WINS**
- Even if other CSS loads later
- Nuclear option, but effective

---

## Files Changed

| File | Change | Why |
|------|--------|-----|
| `nexus-civicone.css` | Added `:not(.civic-button)` to `a` selector | Root cause fix |
| `nexus-civicone.min.css` | Regenerated | Deploy minified version |
| `civicone-govuk-buttons.css` | Added `!important` to all button colors | Safety net |
| `civicone-govuk-buttons.min.css` | Regenerated | Deploy minified version |

---

## Deployment Version

Updated to `2026.01.20.007` with description:
```
ROOT FIX: Excluded buttons from global link styles to prevent color override
```

This forces all browsers to reload the fixed CSS.

---

## Future Architecture Recommendation

### Long-Term Best Practice

**Option 1: Use `<button>` tags instead of `<a>` tags**

```html
<!-- Current (uses <a> tag) -->
<a href="/events" class="civic-button civic-button--start">
    Explore Events
</a>

<!-- Better (uses <button> tag) -->
<button onclick="window.location='/events'" class="civic-button civic-button--start">
    Explore Events
</button>
```

**Pros:**
- No conflict with link styles
- Better semantics (buttons for actions)
- Easier keyboard navigation

**Cons:**
- Requires JS for navigation
- GOV.UK uses `<a>` tags for button-styled links

**Recommendation:** Keep using `<a>` tags (matches GOV.UK), but with our fix in place, it's safe now.

---

## Summary

### Problem
- Global `a` selector applied blue color to ALL links, including button-styled links
- Caused contrast failures on every page with GOV.UK buttons

### Solution
- Excluded buttons from global link selector using `:not(.civic-button):not(.govuk-button)`
- Added `!important` to button colors as extra protection

### Result
- ✅ Future pages won't have button contrast issues
- ✅ No more repetitive fixes needed
- ✅ Architecture is now robust against color override conflicts

### Testing
- Hard refresh: `Ctrl + Shift + R`
- Run Lighthouse on dashboard
- Should show **100/100** with 0 contrast failures
- All future pages should automatically pass

---

**End of Prevention Guide**
