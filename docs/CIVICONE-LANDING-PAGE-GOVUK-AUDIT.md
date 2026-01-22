# CivicOne Landing Page - GOV.UK Component Audit

**Created:** 2026-01-22
**Purpose:** Analyze if all necessary GOV.UK Frontend components have been extracted for the CivicOne landing page

---

## Current Landing Page Structure

**URL:** `http://staging.timebank.local/hour-timebank/` (root `/`)
**Controller:** `Nexus\Controllers\HomeController@index`
**Views:**
- Entry: `views/civicone/home.php` (sets hero overrides)
- Content: `views/civicone/feed/index.php` (900 lines - full community feed)

---

## Landing Page Components In Use

### 1. Hero Section ✅ EXTRACTED
**Current Implementation:** `civicone-hero.css` (Enhanced visual design)
**GOV.UK Equivalent:** `civicone-hero-govuk.css` (Pure GOV.UK patterns)

**Features on Landing:**
- Banner variant hero
- Title: "Welcome to Your Community"
- Lead text: "Connect, collaborate, and make a difference..."
- CTA: "Get started" button → `/join`

**Status:** ✅ **GOV.UK version extracted** (Panel + Page Template + Start Button)

---

### 2. Buttons ✅ EXTRACTED
**Current CSS:** `civicone-govuk-buttons.min.css` (Line 138)

**Buttons on Landing:**
- Primary buttons (`.civic-btn`, `.civic-btn-primary`)
- Start button with arrow icon (`.civicone-button--start`)
- Social interaction buttons (Like, Comment, Share)
- "Get started" CTA button
- Post composer submit button

**Status:** ✅ **Fully extracted from GOV.UK Frontend** (includes start button variant)

---

### 3. Forms ✅ EXTRACTED
**Current CSS:** `civicone-govuk-forms.min.css` (Line 139)

**Forms on Landing:**
- Post composer textarea
- File upload input (image upload)
- Comment input fields
- CSRF token fields

**GOV.UK Components Available:**
- ✅ Textarea component
- ✅ File upload component
- ✅ Input component
- ✅ Error message component (for validation)
- ✅ Error summary component

**Status:** ✅ **Extracted but may need enhancement** - Current form composer uses custom styles

---

### 4. Typography ✅ EXTRACTED
**Current CSS:** `civicone-govuk-typography.min.css` (Line 135)

**Typography on Landing:**
- Page heading (H1 in hero)
- Content headings (H2, H3 in feed items)
- Body text (posts, comments, descriptions)
- Lead paragraphs (hero lead text)
- Small text (timestamps, metadata)

**Status:** ✅ **Fully extracted from GOV.UK Frontend**

---

### 5. Focus States ✅ EXTRACTED
**Current CSS:** `civicone-govuk-focus.min.css` (Line 137)

**Interactive Elements:**
- All buttons (like, comment, share)
- Text inputs (post composer, comment fields)
- Links (profile links, content links)
- File upload button

**Status:** ✅ **GOV.UK yellow (#ffdd00) focus ring extracted**

---

### 6. Feed Item Cards ⚠️ NEEDS REVIEW
**Current CSS:** `civicone-feed-item.css`, `feed-item.css`

**Feed Components:**
- Post cards (author, timestamp, content, image)
- Listing cards (buy/sell/request items)
- Event cards (date, location, description)
- Poll cards (voting interface)
- Volunteering opportunity cards
- Goal cards

**GOV.UK Equivalent:**
- ❓ **Card component** - NOT in GOV.UK Frontend core
- ❓ **Summary list** - Could be used for metadata
- ❓ **Details component** - Expandable content

**Status:** ⚠️ **No direct GOV.UK card pattern** - Feed cards are custom implementation

---

### 7. Social Interactions ⚠️ CUSTOM
**Current CSS:** `social-interactions.min.css` (Line 132)

**Features:**
- Like button with counter
- Comment button with counter
- Share button
- Nested comments with replies
- Comment reactions
- Edit/delete actions

**GOV.UK Equivalent:**
- ❌ **No social interaction patterns in GOV.UK Frontend**
- This is appropriate - GOV.UK doesn't do social features

**Status:** ⚠️ **Custom implementation required** (not a GOV.UK use case)

---

### 8. Lists/Pagination ❌ NOT EXTRACTED
**Current Implementation:** Custom feed stream (reversed `<ol>`)

**Feed Features:**
- Ordered list of feed items (`.civicone-feed-list`)
- Infinite scroll loading
- No pagination currently visible

**GOV.UK Components Available:**
- ❌ **Pagination component** - NOT YET EXTRACTED
- ❌ **Table component** - NOT YET EXTRACTED (if needed for data tables)

**Status:** ❌ **MISSING** - Should extract if pagination is planned

---

### 9. Navigation (Header/Footer) ✅ CUSTOM + PARTIAL
**Current CSS:**
- `civicone-header.min.css` (Line 143)
- `civicone-footer.min.css` (Line 144)

**Navigation Elements:**
- Top utility bar (user menu, notifications)
- Main site header (logo, primary nav, search)
- Mobile bottom navigation
- Breadcrumbs (not on landing page)

**GOV.UK Components:**
- ⚠️ **Service navigation** - NOT YET EXTRACTED (but similar to current header nav)
- ❌ **Breadcrumbs** - NOT YET EXTRACTED
- ❌ **Back link** - NOT YET EXTRACTED

**Status:** ⚠️ **PARTIAL** - Current header works but could align closer to GOV.UK patterns

---

### 10. Notifications/Feedback ❌ NOT EXTRACTED
**Current Implementation:**
- Toast notifications (custom JS: `showToast()`)
- Success messages after posting
- Error messages (AJAX errors)

**GOV.UK Components Available:**
- ❌ **Notification banner** - NOT YET EXTRACTED
- ❌ **Warning text** - NOT YET EXTRACTED
- ❌ **Inset text** - NOT YET EXTRACTED

**Status:** ❌ **MISSING** - Should extract for consistent messaging

---

## Summary: What's Missing for Landing Page?

### ✅ ALREADY EXTRACTED (In Use)
1. Hero components (Panel, Page Template, Start Button)
2. Button components (Primary, Secondary, Start)
3. Form components (Input, Textarea, File upload, Error messages)
4. Typography styles (Headings, body, lead)
5. Focus states (Yellow ring)
6. Spacing utilities

### ⚠️ PARTIALLY EXTRACTED (Could Improve)
1. **Navigation patterns** - Current header works but not pure GOV.UK pattern
2. **Card layouts** - No GOV.UK equivalent (custom is fine)

### ❌ MISSING (Should Extract)
1. **Notification banner** - For success/error feedback instead of toast
2. **Warning text** - For important notices
3. **Inset text** - For highlighted content
4. **Pagination** - If implementing pagination for feed
5. **Breadcrumbs** - For sub-pages (not needed on landing)
6. **Back link** - For sub-pages (not needed on landing)
7. **Details component** - For expandable sections
8. **Summary list** - For key-value pairs

---

## Recommendations

### Priority 1: Extract Immediately ⚡
These components would improve the landing page NOW:

1. **Notification Banner**
   - Source: `govuk-frontend/components/notification-banner/`
   - Use: Replace custom toast notifications with GOV.UK pattern
   - Example: "Your post has been published" success banner

2. **Warning Text**
   - Source: `govuk-frontend/components/warning-text/`
   - Use: Important notices (e.g., "Your account needs verification")

3. **Inset Text**
   - Source: `govuk-frontend/components/inset-text/`
   - Use: Highlighted information in feed items

### Priority 2: Extract Soon 📋
These components would be useful for future enhancements:

4. **Pagination**
   - Source: `govuk-frontend/components/pagination/`
   - Use: If replacing infinite scroll with pagination

5. **Details (Accordion)**
   - Source: `govuk-frontend/components/details/`
   - Use: Expandable content in feed items or FAQs

6. **Summary List**
   - Source: `govuk-frontend/components/summary-list/`
   - Use: Displaying metadata (event details, listing specs)

### Priority 3: Consider Later 🤔
These components are for sub-pages, not landing:

7. **Breadcrumbs**
   - Use: Event detail pages, group pages, profile pages

8. **Back Link**
   - Use: Multi-step forms, detail pages

9. **Service Navigation**
   - Use: If restructuring header to match GOV.UK pattern exactly

---

## Current CSS Load Order (Landing Page)

```php
<!-- Line 94-147 of assets-css.php -->
design-tokens.min.css          // ✅ Design tokens
layout-isolation.min.css       // ✅ Layout system
nexus-phoenix.min.css          // ✅ Core framework
branding.min.css               // ✅ Global styles
nexus-civicone.min.css         // ✅ Theme override
civicone-mobile.min.css        // ✅ Mobile enhancements
civicone-native.min.css        // ✅ Native app styles
nexus-native-nav-v2.min.css    // ✅ Mobile nav
mobile-sheets.min.css          // ✅ Bottom sheets
social-interactions.min.css    // ✅ Like/comment/share

<!-- GOV.UK Components (Lines 135-140) -->
civicone-govuk-typography.min.css  // ✅
civicone-govuk-spacing.min.css     // ✅
civicone-govuk-focus.min.css       // ✅
civicone-govuk-buttons.min.css     // ✅
civicone-govuk-forms.min.css       // ✅
civicone-govuk-components.min.css  // ✅

<!-- Layout Components -->
civicone-header.min.css        // ✅ Header
civicone-footer.min.css        // ✅ Footer
civicone-hero.min.css          // ✅ Hero (enhanced version)
```

---

## Action Items

### ✅ COMPLETED (2026-01-22)
- [x] Extract **Notification Banner** from GOV.UK Frontend
- [x] Extract **Warning Text** from GOV.UK Frontend
- [x] Extract **Inset Text** from GOV.UK Frontend
- [x] Extract **Pagination** component
- [x] Extract **Details** component
- [x] Extract **Summary List** component
- [x] Extract **Breadcrumbs** for sub-pages
- [x] Extract **Back Link** for sub-pages
- [x] Create `civicone-govuk-feedback.css` file
- [x] Create `civicone-govuk-navigation.css` file
- [x] Create `civicone-govuk-content.css` file
- [x] Add to `purgecss.config.js`
- [x] Update documentation

### Next Steps
- [ ] Add CSS files to `assets-css.php` header
- [ ] Run `npm run purgecss` to generate minified versions
- [ ] Replace custom toast notifications with Notification Banner
- [ ] Add pagination to members/events/listings directories
- [ ] Add breadcrumbs to sub-pages
- [ ] Use Summary Lists for event/listing metadata

### Future Consideration
- [ ] Review feed item cards for GOV.UK alignment
- [ ] Review header navigation vs GOV.UK Service Navigation pattern
- [ ] Extract Table component if needed for data tables

---

## Conclusion

**Have we pulled everything we need?**

**For the LANDING PAGE specifically:**
- ✅ **Core components:** YES (hero, buttons, forms, typography, focus)
- ⚠️ **Feedback components:** NO - Missing notification banner, warning text, inset text
- ⚠️ **Navigation components:** NO - Missing breadcrumbs (but not needed on landing)
- ⚠️ **List components:** NO - Missing pagination (but currently using infinite scroll)

**Overall Assessment:**
We have ~70% of what's needed. The main gaps are **feedback/notification components** which would significantly improve the user experience. The landing page functions well but could benefit from extracting notification patterns to replace custom toast messages.

**Next Step:** Extract Notification Banner, Warning Text, and Inset Text components from GOV.UK Frontend to complete the landing page component set.
