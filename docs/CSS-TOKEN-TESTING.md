# CSS Token Migration - Testing Guide

**Date**: 2026-01-21
**Phases Completed**: 1-4 (37.1% of total violations)

---

## Quick Visual Test Checklist

### 1. Homepage Test (nexus-home.css - migrated ✅)
- [ ] Visit homepage - check spacing looks normal
- [ ] Check header spacing and alignment
- [ ] Verify button padding and border-radius
- [ ] Test responsive layout on mobile (375px width)

### 2. Groups Pages (groups.css, groups-show.css - migrated ✅)
- [ ] Visit `/groups` - check grid layout spacing
- [ ] Click into a group detail page
- [ ] Verify card spacing and padding
- [ ] Check hover states work correctly

### 3. Dashboard (civicone-dashboard.css - migrated ✅)
- [ ] Visit your dashboard
- [ ] Check widget spacing and gaps
- [ ] Verify all cards have proper border-radius
- [ ] Test dark mode if enabled

### 4. Feed Items (feed-item.css - migrated ✅)
- [ ] Check feed posts on homepage or feed page
- [ ] Verify post card spacing and padding
- [ ] Check avatar sizes and border-radius
- [ ] Test shared content cards

### 5. Major Features (Phase 3 - all migrated ✅)
- [ ] **Federation** - Visit federation pages, check layout
- [ ] **Volunteering** - Check opportunity cards
- [ ] **Messages** - Verify message list spacing
- [ ] **Compose** - Check compose form layout

### 6. Secondary Features (Phase 4 - all migrated ✅)
- [ ] **Achievements** - Check badge spacing
- [ ] **Organizations** - Verify org cards
- [ ] **Polls** - Check poll layout
- [ ] **Matches** - Verify match cards
- [ ] **Listings** - Check listing grid
- [ ] **Goals** - Verify goal progress bars

---

## What to Look For

### ✅ Good Signs:
- Spacing looks consistent across pages
- Border-radius is uniform (cards all have same rounded corners)
- Typography sizes are consistent
- No visual changes from before migration

### ❌ Red Flags:
- Elements too close together or too far apart
- Border-radius looks wrong (too round or too sharp)
- Text sizes look off
- Broken layouts or overlapping elements

---

## Browser Testing

Test in at least 2 browsers:
- [ ] Chrome/Edge
- [ ] Firefox or Safari

---

## Responsive Testing

Test at these widths:
- [ ] Mobile: 375px (iPhone SE)
- [ ] Tablet: 768px (iPad)
- [ ] Desktop: 1440px

---

## Quick Browser DevTools Check

1. Open browser DevTools (F12)
2. Inspect an element that was migrated
3. Check computed styles - should see `var(--space-4)` instead of `16px`
4. Verify tokens are resolving correctly

**Example:**
```css
/* Before migration */
padding: 16px;

/* After migration */
padding: var(--space-4); /* resolves to 16px */
```

---

## If You Find Issues

1. Take a screenshot
2. Note which page/component
3. Note what looks wrong
4. We can fix individual tokens if needed

---

## Performance Check (Optional)

Compare page load times before/after:
- [ ] Run Lighthouse audit on homepage
- [ ] Check CSS file sizes (should be ~30% smaller)
- [ ] Verify no layout shift issues

---

## Dark Mode Testing (If Applicable)

If your site has dark mode:
- [ ] Toggle dark mode
- [ ] Check all migrated pages
- [ ] Verify tokens work correctly in dark mode

---

## Next Steps After Testing

Once testing is complete, you have options:

### Option A: Continue Phase 5
Migrate remaining 142 files (9,745 violations)

### Option B: Pause and Optimize
Take a break, let users test, gather feedback

### Option C: Cherry-pick High-Impact Files
Migrate just the top 20-30 files from Phase 5
