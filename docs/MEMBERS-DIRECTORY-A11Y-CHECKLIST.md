# Members Directory Accessibility Checklist

**Page**: `/members` (views/civicone/members/index.php)
**Template**: Template A - Directory/List Page
**Date**: 2026-01-21
**Status**: ✅ Phase 3 Complete - Ready for Testing

---

## Compliance Summary

✅ **GOV.UK Page Template Boilerplate** (Section 10.0)
✅ **Page Hero Contract** (Section 9C)
✅ **MOJ Filter Pattern** (Section 10.2)
✅ **List Layout** (NOT card grid)
✅ **Pagination** (GOV.UK component)
✅ **Extracted JavaScript** (per CLAUDE.md)

---

## Structure Checklist

### GOV.UK Boilerplate (Section 10.0)

- [x] Skip link present (first focusable element)
- [x] `civicone-width-container` wraps content (max-width: 1020px)
- [x] `civicone-main-wrapper` adds vertical padding
- [x] `<main id="main-content">` present
- [x] All content inside width container

### Page Hero (Section 9C)

- [x] Hero renders after header include
- [x] Hero inside `<main>` element
- [x] Uses `render-hero.php` partial
- [x] H1 present in hero (auto-generated from config)

### MOJ Filter Pattern (Section 10.2)

- [x] Filter panel: 1/4 width (`civicone-grid-column-one-quarter`)
- [x] Results panel: 3/4 width (`civicone-grid-column-three-quarters`)
- [x] Filter panel has `role="search"` and `aria-label="Filter members"`
- [x] Filters stack above results on mobile (<641px)

---

## Filter Panel Checklist

### Search Input

- [x] Has visible `<label for="member-search">`
- [x] Input has clear placeholder
- [x] Search icon present with `aria-hidden="true"`
- [x] Spinner has `aria-live="polite"` and `aria-label="Searching"`

### Selected Filters

- [x] Only shown when filters are active
- [x] Filter tags have "Remove filter" label
- [x] Clicking tag removes filter (navigates to clean URL)
- [x] Tags keyboard accessible

---

## Results List Checklist

### Semantic Structure

- [x] Uses `<ul class="civicone-results-list">` with `role="list"`
- [x] Each member is `<li class="civicone-member-item">`
- [x] NOT using card grid layout
- [x] List items have logical reading order

### Results Header

- [x] Shows "Showing X of Y members"
- [x] Count updates dynamically via AJAX
- [x] Has `id="results-count"` for programmatic updates

### Member List Items

- [x] Avatar uses proper alt text (empty alt="" for decorative)
- [x] Online indicator has `aria-label="Currently online"`
- [x] Member name in `<h3>` with link
- [x] Location icon has `aria-hidden="true"`
- [x] "View profile" button has clear label

### Empty State

- [x] Shown when no results found
- [x] SVG has `aria-hidden="true"`
- [x] Has clear heading and message
- [x] Suggests user actions

---

## Pagination Checklist (GOV.UK Component)

- [x] Uses `<nav class="civicone-pagination">`
- [x] Has `aria-label="Member list pagination"`
- [x] Shows "Showing X to Y of Z results"
- [x] Current page marked with `aria-current="page"`
- [x] Previous/Next links have hidden context ("Go to previous page")
- [x] Page number links have `aria-label="Go to page X"`
- [x] Ellipsis has `aria-hidden="true"`
- [x] Query params preserved in pagination links

---

## JavaScript Checklist

### File Organization (CLAUDE.md Compliance)

- [x] Extracted to `civicone-members-directory.js`
- [x] Minified version created (`civicone-members-directory.min.js`)
- [x] Loaded with `defer` attribute
- [x] Size reduction: 5.8KB → 3.5KB (40% reduction)

### AJAX Search

- [x] Debounced input (400ms)
- [x] Shows spinner during search
- [x] Updates results dynamically
- [x] Updates results count
- [x] Shows/hides empty state
- [x] Proper error handling (console.error)
- [x] HTML escaping function prevents XSS

### Dynamic Rendering

- [x] Creates list items (not card divs)
- [x] Preserves semantic HTML structure
- [x] Handles missing data (avatar, location)
- [x] Calculates online status correctly
- [x] Uses template literals safely

---

## Keyboard Navigation Checklist

### Manual Testing Required

- [ ] **Tab Order**: Skip link → Search input → Filter tags → Member links → Pagination
- [ ] **Search Input**: Can type and trigger search
- [ ] **Filter Tags**: Can focus and activate with Enter
- [ ] **Member Links**: Can navigate with Tab, activate with Enter
- [ ] **Pagination**: Can navigate all page links with Tab
- [ ] **Focus Visible**: Yellow (#ffdd00) focus indicator on all elements
- [ ] **No Keyboard Traps**: Can Tab through entire page without getting stuck

---

## Screen Reader Checklist

### Manual Testing Required (NVDA/JAWS)

- [ ] **Page Title**: "Members Directory" announced (from hero H1)
- [ ] **Skip Link**: Announced and functional (skips to #main-content)
- [ ] **Filter Panel**: Announced as "search" landmark
- [ ] **Search Input**: Label "Search by name or location" announced
- [ ] **Results Count**: "Showing X of Y members" announced
- [ ] **List Items**: Navigate with Up/Down arrow in list mode
- [ ] **Member Names**: H3 headings navigable with H key
- [ ] **Online Status**: "Currently online" announced for active members
- [ ] **Pagination**: Announced as "navigation" landmark
- [ ] **Current Page**: "Page 2, current page" announced
- [ ] **Empty State**: Heading and message announced clearly

---

## Visual Regression Checklist

### Manual Testing Required

- [ ] **Desktop (1920px)**: Filter panel 1/4 width, results 3/4 width
- [ ] **Tablet (768px)**: Layout maintains, no horizontal scroll
- [ ] **Mobile (375px)**: Filter stacks above results, single column
- [ ] **Zoom 200%**: No horizontal scroll, all content accessible
- [ ] **Zoom 400%**: Content reflows to single column
- [ ] **Focus States**: Yellow background with black text on all interactive elements
- [ ] **Empty State**: SVG and message centered and visible
- [ ] **Pagination**: Links wrap cleanly on narrow viewports

---

## AJAX Search Testing

### Manual Testing Required

- [ ] Type in search box → Spinner appears after 400ms
- [ ] Search returns results → List updates, count updates, spinner hides
- [ ] Search returns 0 results → Empty state shows
- [ ] Clear search (empty input) → Page reloads to full list
- [ ] Search preserves focus → Focus stays on search input
- [ ] Error handling → Console error logged (test with invalid endpoint)

---

## Performance Checklist

- [x] JavaScript deferred (doesn't block page load)
- [x] JavaScript minified (40% size reduction)
- [x] CSS uses design tokens (already done in Phase 2)
- [x] Debounced search (400ms prevents excessive requests)
- [ ] Images lazy-loaded (check if implemented)
- [ ] Pagination prevents loading entire dataset

---

## Code Quality Checklist

- [x] No inline `<style>` blocks (per CLAUDE.md)
- [x] No inline `<script>` blocks >10 lines (per CLAUDE.md)
- [x] All CSS scoped under `.nexus-skin-civicone`
- [x] GOV.UK/MOJ component classes used correctly
- [x] Semantic HTML (`<ul>`, `<li>`, `<nav>`, `<h3>`)
- [x] Proper escaping (htmlspecialchars in PHP, escapeHtml in JS)
- [x] Comments explain purpose of sections

---

## Browser Testing Matrix

### Desktop

- [ ] Chrome (latest) - Layout, search, pagination
- [ ] Firefox (latest) - Layout, search, pagination
- [ ] Safari (latest) - Layout, search, pagination
- [ ] Edge (latest) - Layout, search, pagination

### Mobile

- [ ] iOS Safari (latest) - Touch targets, layout, search
- [ ] Android Chrome (latest) - Touch targets, layout, search

---

## Known Issues / Notes

None currently. All Phase 3 fixes applied:
- ✅ Grid proportions corrected (1/4 + 3/4)
- ✅ Duplicate closing tags removed
- ✅ Inline JavaScript extracted and minified

---

## Next Steps

1. **Manual Testing**: Complete keyboard navigation walkthrough
2. **Screen Reader Testing**: Test with NVDA/JAWS
3. **Visual Regression**: Screenshot comparison at all breakpoints
4. **Axe DevTools**: Run automated accessibility scan
5. **Real Device Testing**: Test on actual iOS/Android devices

---

## Sign-off

- [ ] Keyboard navigation verified
- [ ] Screen reader tested (NVDA/JAWS)
- [ ] Visual regression passed
- [ ] Axe audit passed (0 violations)
- [ ] Real device testing complete
- [ ] Code review approved

**Tester**: ___________________
**Date**: ___________________
