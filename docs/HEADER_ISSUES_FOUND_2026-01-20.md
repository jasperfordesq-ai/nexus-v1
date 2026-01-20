# CivicOne Header Issues Found - Visual Inspection

**Date:** 2026-01-20
**Severity:** HIGH
**Status:** NEEDS IMMEDIATE FIX

---

## Issues Identified from Screenshot

Based on visual inspection of the rendered header, the following critical violations of Section 9A (Global Header & Navigation Contract) were found:

### ❌ CRITICAL ISSUE 1: Service Navigation Not Visible

**Violation:** Rule HL-004, PN-001-008

**What should appear:**
```
[Logo: Project NEXUS]  Feed  Members  Groups  Listings  Volunteering  Events
```

**What actually appears:**
- Service navigation is NOT visible on screen
- Only utility bar and large purple hero section visible

**Root Cause:**
- Service navigation partial is included in code (`site-header.php` line 15)
- CSS may be hiding it
- OR: Hero section is overlapping/covering it
- OR: Service navigation has `display: none` or `hidden` attribute

**Fix Required:**

1. **Check CSS for service navigation visibility:**
   ```bash
   grep -A 5 "civicone-service-navigation {" httpdocs/assets/css/civicone-header.css
   ```

2. **Check if service navigation has inline `hidden` attribute:**
   ```bash
   grep "hidden" views/layouts/civicone/partials/service-navigation.php
   ```

3. **Check z-index stacking:**
   - Service navigation may be behind hero section
   - Verify `.civicone-header` has correct z-index

**Expected CSS:**
```css
.civicone-service-navigation {
    display: block; /* NOT display: none */
    visibility: visible; /* NOT visibility: hidden */
    position: relative; /* NOT position: absolute with off-screen coords */
}
```

---

### ❌ CRITICAL ISSUE 2: Utility Bar Contains Navigation Items

**Violation:** Rule HL-003

**What's wrong:**

The utility bar contains these items that should NOT be there:

| Item | Should Be In | Why It's Wrong |
|------|--------------|----------------|
| **"+ Create" dropdown** | Page content OR floating action button | Rule PN-002: NO CTAs in primary nav. Utility bar is for platform/auth ONLY. |
| **"Partner Communities" dropdown** | Separate scope switcher (Section 9B) | Federation scope switcher MUST appear between header and main content, NOT in utility bar (Section 9B.2 Rule FS-003) |
| **"Admin" link** | Acceptable but suboptimal | Clutters utility bar; better in user dropdown |
| **"Ranking" link** | Remove or move to Admin dropdown | Not a utility function |

**Rule HL-003:**
> "Utility bar MUST contain ONLY: platform switcher, contrast toggle, auth links"

**Current utility bar:**
```
Platform | Contrast | Layout | + Create | Partner Communities | Admin | Ranking | [Messages] | [Notifications] | [User Avatar]
```

**Correct utility bar:**
```
Platform | Contrast | Layout | [Messages] | [Notifications] | [User Avatar + Sign Out]
```

**Fix Required:**

1. **Remove "+ Create" from utility bar** (`utility-bar.php` lines 93-114)
   - Move to floating action button (FAB) in page content
   - OR: Add to service navigation mobile panel only

2. **Remove "Partner Communities" from utility bar** (`utility-bar.php` lines 117-160)
   - Extract to separate `federation-scope-switcher.php` partial
   - Render BELOW service navigation, NOT in utility bar
   - Follow Section 9B.2 MOJ Organisation Switcher pattern

3. **Move "Admin" and "Ranking" into User dropdown** (`utility-bar.php` lines 176-182)
   - Add to user dropdown menu (lines 229-243)
   - Reduces utility bar clutter

---

### ❌ CRITICAL ISSUE 3: Search Not in Correct Layer

**Violation:** Rule HL-005

**What's wrong:**

Search bar appears in utility bar (visible in screenshot as search box on right side of utility bar).

**Rule HL-005:**
> "Search MAY appear inside service nav OR immediately below, but NEVER as separate competing header block"

**Fix Required:**

Search is already correctly implemented in `site-header.php` lines 19-66 (below service navigation). The issue is that utility bar ALSO has a search box, creating duplication.

**Action:**
- Remove search from utility bar (if present)
- Keep only the search in `site-header.php` (Layer 5)

---

### ❌ CRITICAL ISSUE 4: Large Purple "Featured Content" Section

**Violation:** Anti-pattern (Section 9A.5)

**What's wrong:**

A large purple section labeled "Featured Content" and "Partner Communities" appears below the utility bar. This is misusing the hero partial.

**Problems:**
1. **Takes up too much vertical space** (pushes main content down)
2. **Visually dominates** over service navigation (which should be primary nav)
3. **Breaks hierarchy** (hero should be page-specific, not global)
4. **Contains "Partner Communities"** text - this should be in scope switcher, not hero

**Expected hero usage:**
- Hero should be page-specific (e.g., homepage welcome banner)
- Should NOT contain navigation elements
- Should be optional (not shown on every page)
- Should NOT exceed 200px height

**Fix Required:**

1. **Check `hero.php` partial:**
   ```bash
   cat views/layouts/civicone/partials/hero.php
   ```

2. **Options:**
   - **Option A:** Remove hero from global header (include per-page instead)
   - **Option B:** Make hero conditional (only show on homepage)
   - **Option C:** Reduce hero to single-line banner (max 60px height)

3. **Remove "Partner Communities" from hero:**
   - This belongs in federation scope switcher (Section 9B.2)
   - NOT in hero section

---

### ⚠️ WARNING ISSUE 5: Layer Order Incorrect

**Violation:** Rule HL-002-006

**Current visual order:**
1. ✅ Phase banner (green bar at top)
2. ✅ Utility bar
3. ❌ **Large purple section** (should be service navigation)
4. ❌ **Service navigation missing**
5. ❌ **Search in utility bar** (should be Layer 5 below service nav)

**Correct order (Section 9A.2):**
1. ✅ Skip link (first focusable - not visible until Tab pressed)
2. ✅ Phase banner (green bar)
3. ⚠️ Utility bar (needs cleanup per Issue 2)
4. ❌ **PRIMARY NAVIGATION** (service navigation - MISSING FROM SCREEN)
5. ⚠️ Search (exists in code, but may be hidden or overlapped)
6. ❌ Hero (should be removed or made page-specific)

---

## Summary of Fixes Required

### Priority 1: Make Service Navigation Visible (CRITICAL)

**Estimated time:** 30 minutes

1. **Check CSS visibility:**
   ```bash
   # Find service navigation styles
   grep -B 2 -A 10 ".civicone-service-navigation {" httpdocs/assets/css/civicone-header.css

   # Check for display: none or visibility: hidden
   grep "display: none\|visibility: hidden" httpdocs/assets/css/civicone-header.css | grep -i "service"
   ```

2. **Check for hidden attribute in PHP:**
   ```bash
   grep "hidden" views/layouts/civicone/partials/service-navigation.php
   ```

3. **Check z-index stacking:**
   - Service navigation may be rendered but behind hero section
   - Verify `.civicone-header` has `z-index: 100` or higher
   - Verify hero section has lower z-index

4. **Test rendering:**
   - Temporarily add inline style to service navigation: `style="background: red; min-height: 60px;"`
   - Reload page - if red bar appears, CSS is the issue
   - If still not visible, check JavaScript for hiding logic

### Priority 2: Clean Up Utility Bar (HIGH)

**Estimated time:** 1 hour

1. **Remove "+ Create" dropdown:**
   - Comment out lines 93-114 in `utility-bar.php`
   - Create floating action button (FAB) partial for page content
   - OR: Add "Create" to mobile service navigation panel only

2. **Extract "Partner Communities" to scope switcher:**
   - Create `views/layouts/civicone/partials/federation-scope-switcher.php`
   - Copy lines 117-160 from `utility-bar.php` to new partial
   - Update `site-header.php` to include scope switcher BELOW service nav
   - Follow Section 9B.2 MOJ Organisation Switcher pattern

3. **Move Admin/Ranking to user dropdown:**
   - Remove lines 176-182 from top-level utility bar
   - Add to user dropdown menu (after "Dashboard", before "Sign Out")

### Priority 3: Fix Hero Section (HIGH)

**Estimated time:** 30 minutes

1. **Make hero conditional:**
   ```php
   // In header.php, replace:
   require __DIR__ . '/partials/hero.php';

   // With:
   if ($showHero ?? false) {
       require __DIR__ . '/partials/hero.php';
   }
   ```

2. **OR: Remove hero from global header:**
   - Comment out line in `header.php` that includes `hero.php`
   - Include hero per-page in individual view files instead

3. **Reduce hero height:**
   - Update hero CSS to max-height: 200px (currently may be 400px+)
   - Remove "Partner Communities" content from hero

### Priority 4: Verify Search Placement (MEDIUM)

**Estimated time:** 15 minutes

1. **Check if search is duplicated in utility bar:**
   ```bash
   grep -n "search" views/layouts/civicone/partials/utility-bar.php
   ```

2. **If found, remove search from utility bar**
   - Search should ONLY be in `site-header.php` (Layer 5)

3. **Verify search is visible:**
   - Check CSS for `.civicone-search-container`
   - Ensure not hidden on desktop

---

## Testing After Fixes

After implementing fixes, verify:

1. **Service navigation visible:**
   - [ ] Logo + nav items (Feed, Members, Groups, Listings, etc.) visible on desktop
   - [ ] Active page highlighted
   - [ ] Hamburger menu visible on mobile (375px)

2. **Utility bar clean:**
   - [ ] Only contains: Platform, Contrast, Layout, Messages, Notifications, User Avatar
   - [ ] NO "+ Create" dropdown
   - [ ] NO "Partner Communities" dropdown
   - [ ] NO "Admin" or "Ranking" links (moved to user dropdown)

3. **Layer order correct:**
   - [ ] Phase banner (green) at top
   - [ ] Utility bar below phase banner
   - [ ] Service navigation below utility bar
   - [ ] Search below service navigation
   - [ ] Hero section removed OR very small (60px max)

4. **Visual regression:**
   - [ ] Screenshot at 1920px shows service navigation clearly
   - [ ] Screenshot at 375px shows mobile menu
   - [ ] No horizontal scroll at any viewport

---

## Code Locations for Fixes

| File | Lines | Change Required |
|------|-------|----------------|
| `utility-bar.php` | 93-114 | Remove "+ Create" dropdown |
| `utility-bar.php` | 117-160 | Move "Partner Communities" to new partial |
| `utility-bar.php` | 176-182 | Move "Admin"/"Ranking" to user dropdown |
| `utility-bar.php` | 229-243 | Add "Admin"/"Ranking" menu items |
| `site-header.php` | After line 17 | Add federation scope switcher include |
| `header.php` | Line with `hero.php` | Make conditional or remove |
| `civicone-header.css` | `.civicone-service-navigation` | Verify visibility styles |
| **NEW:** `federation-scope-switcher.php` | N/A | Create new partial (Section 9B.2) |

---

## Expected Visual Result

After fixes, header should look like this at 1920px:

```
┌────────────────────────────────────────────────────────────────┐
│ Phase Banner (green):                                          │
│ "ACCESSIBLE Experimental in Development | WCAG 2.1 AA..."     │
└────────────────────────────────────────────────────────────────┘
┌────────────────────────────────────────────────────────────────┐
│ Utility Bar (light grey):                                      │
│ Platform ▾ | Contrast | Layout ▾ | [✉️ Messages] |             │
│ [🔔 Notifications] | [Avatar: Jasper ▾]                        │
└────────────────────────────────────────────────────────────────┘
┌────────────────────────────────────────────────────────────────┐
│ Service Navigation (white, inside 1020px container):           │
│ [Logo: Project NEXUS]  Feed  Members  Groups  Listings        │
│                        Volunteering  Events                    │
│                       (Active page highlighted)                │
└────────────────────────────────────────────────────────────────┘
┌────────────────────────────────────────────────────────────────┐
│ Search (inside 1020px container):                              │
│ [Search input field................................] [🔍]       │
└────────────────────────────────────────────────────────────────┘
┌────────────────────────────────────────────────────────────────┐
│ Main Content (starts here, inside 1020px container)           │
│ ...                                                            │
```

**Key visual indicators of success:**
- ✅ Service navigation clearly visible with logo + nav items
- ✅ Utility bar has max 6-7 items (Platform, Contrast, Layout, Messages, Notifications, User)
- ✅ NO large purple section
- ✅ Page content constrained to ~1020px width
- ✅ Active page in service navigation highlighted (different background)

---

## Next Steps

1. **Implement Priority 1 fix** (make service navigation visible)
2. **Take screenshot** after Priority 1 fix
3. **Implement Priority 2-3 fixes** (clean up utility bar, fix hero)
4. **Run verification script:**
   ```bash
   bash scripts/verify-header-refactor.sh
   ```
5. **Run visual testing:**
   Follow `docs/HEADER_VISUAL_TESTING_GUIDE.md`
6. **Update diagnostic:**
   Update `docs/HEADER_REFACTOR_DIAGNOSTIC_2026-01-20.md` with fix results

---

## References

- **WCAG 2.1 AA Source of Truth:** Section 9A (Global Header & Navigation Contract)
- **Federation Mode:** Section 9B (Partner Communities)
- **GOV.UK Service Navigation:** https://design-system.service.gov.uk/components/service-navigation/
- **MOJ Organisation Switcher:** https://design-patterns.service.justice.gov.uk/components/organisation-switcher/
