# Dashboard Refactor to Account Area Template

**Date:** 2026-01-20
**Template:** Account Area Template (Template G)
**Status:** COMPLETE - Requires Testing
**Pattern Sources:**
- MOJ Sub navigation: https://design-patterns.service.justice.gov.uk/components/sub-navigation/
- MOJ Notification badge: https://design-patterns.service.justice.gov.uk/components/notification-badge/
- GOV.UK Page Template: https://design-system.service.gov.uk/styles/page-template/

---

## Summary of Changes

The CivicOne dashboard has been refactored from a single-page tabbed interface to a proper **Account Area** with dedicated pages for each section, following UK Government design patterns.

### Key Changes:

1. **Removed module-level tabs** (violates WCAG 1.3.1, 2.4.1, 2.4.8)
2. **Added MOJ Sub navigation** for account sections
3. **Created dedicated pages** for each account section with separate routes
4. **Preserved all existing functionality** (no features removed)
5. **Maintained all JavaScript hooks** (dashboard FAB, notifications, wallet transfer, etc.)

---

## Files Created

### New View Files (dedicated pages)

| File | Purpose | Route |
|------|---------|-------|
| `views/civicone/dashboard/partials/_overview.php` | Overview tab content extracted | N/A (partial) |
| `views/civicone/dashboard/notifications.php` | Notifications page | `/dashboard/notifications` |
| `views/civicone/dashboard/hubs.php` | My Hubs page | `/dashboard/hubs` |
| `views/civicone/dashboard/listings.php` | My Listings page | `/dashboard/listings` |
| `views/civicone/dashboard/wallet.php` | Wallet page | `/dashboard/wallet` |
| `views/civicone/dashboard/events.php` | My Events page | `/dashboard/events` |

### New Layout Partials

| File | Purpose |
|------|---------|
| `views/layouts/civicone/partials/account-navigation.php` | MOJ Sub navigation for account sections |

### New CSS Files

| File | Purpose | Scoped |
|------|---------|--------|
| `httpdocs/assets/css/civicone-account-nav.css` | MOJ Sub navigation styles | Yes (`.civicone`, `.civicone-account-area`) |

---

## Files Modified

### Controllers

**`src/Controllers/DashboardController.php`**
- Added `notifications()` method
- Added `hubs()` method
- Added `listings()` method
- Added `wallet()` method
- Added `events()` method
- Removed `$activeTab` logic from `index()` method

### Routes

**`httpdocs/routes.php`** (lines 481-486)
- Added `/dashboard/notifications` route
- Added `/dashboard/hubs` route
- Added `/dashboard/listings` route
- Added `/dashboard/wallet` route
- Added `/dashboard/events` route

### Views

**`views/civicone/dashboard.php`** (completely refactored)
- Removed tab navigation UI
- Removed all tab panel content (extracted to partials/separate pages)
- Added account navigation include
- Now serves as "Overview" hub page only
- Preserved FAB (floating action button)
- Preserved all JavaScript hooks

### Layouts

**`views/layouts/civicone/partials/assets-css.php`**
- Added `civicone-account-nav.css` include (line 139)

---

## Navigation Structure

### Before (Tabs - INCORRECT)

```
/dashboard?tab=overview       # Same page, different content
/dashboard?tab=notifications  # Same page, different content
/dashboard?tab=groups         # Same page, different content
/dashboard?tab=listings       # Same page, different content
/dashboard?tab=wallet         # Same page, different content
/dashboard?tab=events         # Same page, different content
```

**Problems:**
- âAll tabs share single `<main>` landmark (breaks screen readers)
- âNo URL context for current section (breaks WCAG 2.4.8)
- âSkip links don't work properly (breaks WCAG 2.4.1)
- âTabs require JavaScript (breaks progressive enhancement)

### After (Secondary Nav - CORRECT)

```
/dashboard                    # Overview (hub page)
/dashboard/notifications      # Dedicated Notifications page
/dashboard/hubs              # Dedicated My Hubs page
/dashboard/listings          # Dedicated My Listings page
/dashboard/wallet            # Dedicated Wallet page
/dashboard/events            # Dedicated My Events page
```

**Benefits:**
- âEach page has its own `<main>` landmark
- âURL reflects current section (WCAG 2.4.8)
- âSkip links work correctly
- âWorks without JavaScript (progressive enhancement)
- âBookmarkable, shareable URLs
- âBrowser back/forward works correctly

---

## Account Navigation (MOJ Sub Navigation)

### Implementation

Each account area page includes the navigation partial:

```php
<!-- views/civicone/dashboard.php -->
<div class="civic-dashboard civicone-account-area">
    <!-- Account Area Secondary Navigation -->
    <?php require dirname(__DIR__) . '/layouts/civicone/partials/account-navigation.php'; ?>

    <!-- Page content here -->
</div>
```

### Navigation Items

| Label | URL | Icon | Badge |
|-------|-----|------|-------|
| Overview | `/dashboard` | `fa-house` | - |
| Notifications | `/dashboard/notifications` | `fa-bell` | Unread count |
| My Hubs | `/dashboard/hubs` | `fa-users` | - |
| My Listings | `/dashboard/listings` | `fa-list` | - |
| Wallet | `/dashboard/wallet` | `fa-wallet` | - |
| Events | `/dashboard/events` | `fa-calendar` | Only if feature enabled |

### Active State

The current page is marked with:
- `aria-current="page"` on the link
- `.moj-sub-navigation__item--active` class on the list item
- Border bottom highlight (4px blue on desktop, 4px left on mobile)
- Font weight 700

### Responsive Behaviour

- **Desktop (>640px):** Horizontal navigation with bottom border
- **Mobile (â¤640px):** Stacked vertical navigation with left border for active

---

## Accessibility Compliance

### WCAG 2.1 AA Requirements Met

| Rule | Before | After | Status |
|------|--------|-------|--------|
| **1.3.1 Info and Relationships** | âFailed (shared `<main>` landmark) | âPassed (separate pages) | Fixed |
| **2.4.1 Bypass Blocks** | âFailed (skip links ineffective) | âPassed (skip to main works) | Fixed |
| **2.4.7 Focus Visible** | âPassed (had focus styles) | âPassed (GOV.UK yellow focus) | Maintained |
| **2.4.8 Location** | âFailed (no URL context) | âPassed (URL shows section) | Fixed |
| **4.1.2 Name, Role, Value** | âPassed | âPassed | Maintained |

### Keyboard Navigation

All navigation items are keyboard accessible:
- **Tab:** Move to next navigation item
- **Shift+Tab:** Move to previous navigation item
- **Enter:** Activate link (navigate to page)
- **Escape:** (not applicable - links, not disclosure widgets)

### Screen Reader Support

- Navigation wrapped in `<nav>` with `aria-label="Account sections"`
- Active page marked with `aria-current="page"`
- Notification badge includes `aria-label` (e.g., "3 unread")
- Icons marked `aria-hidden="true"` (text labels provided)

---

## CSS Scoping

All new CSS is scoped to prevent bleed to Modern layout:

```css
/* Scoped under .civicone or .civicone-account-area */
.civicone .moj-sub-navigation { ... }
.civicone-account-area .moj-sub-navigation { ... }
```

**Important:** The CSS does NOT use `.civicone--govuk` scope. It uses `.civicone` and `.civicone-account-area` because the navigation is part of the core Account Area Template, not an experimental redesign.

---

## JavaScript Hooks Preserved

### Dashboard-wide Hooks

| Hook | File | Purpose | Status |
|------|------|---------|--------|
| `toggleCivicFab()` | dashboard.php | FAB menu toggle | âPreserved |
| `initCivicOneDashboard()` | civicone-dashboard.js | Dashboard init | âPreserved |

### Notifications Page Hooks

| Hook | File | Purpose | Status |
|------|------|---------|--------|
| `openEventsModal()` | notifications.php | Show notification triggers modal | âPreserved |
| `toggleNotifSettings()` | notifications.php | Show/hide settings panel | âPreserved |
| `window.nexusNotifications.markAllRead()` | notifications.php | Mark all notifications read | âPreserved |
| `window.nexusNotifications.markOneRead()` | notifications.php | Mark single notification read | âPreserved |
| `deleteNotificationDashboard()` | notifications.php | Delete notification | âPreserved |
| `updateNotifSetting()` | notifications.php | Update notification frequency | âPreserved |

### Listings Page Hooks

| Hook | File | Purpose | Status |
|------|------|---------|--------|
| `deleteListing()` | listings.php | Delete a listing | âPreserved |

### Wallet Page Hooks

| Hook | File | Purpose | Status |
|------|------|---------|--------|
| `validateDashTransfer()` | wallet.php | Validate transfer form | âPreserved |
| `clearDashSelection()` | wallet.php | Clear recipient selection | âPreserved |
| User search autocomplete | wallet.php | Search for transfer recipient | âPreserved |

---

## Testing Checklist

### Functional Testing

- [ ] **Overview page loads** at `/dashboard`
- [ ] **Notifications page loads** at `/dashboard/notifications`
- [ ] **My Hubs page loads** at `/dashboard/hubs`
- [ ] **My Listings page loads** at `/dashboard/listings`
- [ ] **Wallet page loads** at `/dashboard/wallet`
- [ ] **My Events page loads** at `/dashboard/events`
- [ ] **Navigation highlights active page** correctly
- [ ] **Notification badge shows count** on Overview page link
- [ ] **Events nav item only shows** when feature is enabled

### Keyboard Testing

- [ ] **Tab through navigation** - focus visible (yellow #ffdd00)
- [ ] **Enter activates links** - navigates to correct page
- [ ] **Focus order is logical** (skip link â nav â content)
- [ ] **No focus traps**

### Screen Reader Testing (NVDA/JAWS)

- [ ] **Navigation landmark announced** ("Account sections navigation")
- [ ] **Active page announced** ("current page")
- [ ] **Notification badge announced** ("3 unread")
- [ ] **Icons not announced** (aria-hidden works)

### Responsive Testing

- [ ] **Desktop (1920px):** Horizontal nav, bottom border
- [ ] **Tablet (768px):** Horizontal nav, may wrap
- [ ] **Mobile (375px):** Vertical nav, left border for active
- [ ] **No horizontal scroll** at any viewport

### Visual Regression Testing

- [ ] **Overview page matches old "overview" tab** (no visual changes)
- [ ] **Notifications page matches old "notifications" tab**
- [ ] **Hubs page matches old "groups" tab**
- [ ] **Listings page matches old "listings" tab**
- [ ] **Wallet page matches old "wallet" tab**
- [ ] **Events page matches old "events" tab**

### JavaScript Hooks Testing

- [ ] **FAB opens/closes** on Overview page
- [ ] **Notification settings toggle** works on Notifications page
- [ ] **Mark all read** works on Notifications page
- [ ] **Delete listing** works on Listings page
- [ ] **Transfer form validation** works on Wallet page
- [ ] **User search autocomplete** works on Wallet page

### Browser Compatibility

- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)
- [ ] Safari iOS (latest)
- [ ] Chrome Android (latest)

---

## Migration Notes for Developers

### Old Tab Links

If you have any hardcoded links to the old tab system:

```php
<!-- OLD (don't use) -->
<a href="/dashboard?tab=notifications">Notifications</a>

<!-- NEW (correct) -->
<a href="/dashboard/notifications">Notifications</a>
```

### Controller Updates

If your controller redirects to a specific dashboard tab:

```php
// OLD (don't use)
header('Location: /dashboard?tab=wallet');

// NEW (correct)
header('Location: /dashboard/wallet');
```

### External Links

Update any external documentation, emails, or help text that reference the old tab URLs.

---

## Known Issues / Future Work

### Not Included in This Refactor

1. **GOV.UK Summary list for wallet balance** - Currently uses custom `.civic-wallet-balance-card`. Future work should convert to GOV.UK Summary list component.

2. **GOV.UK Table for transactions** - Currently uses custom `.civic-transactions-table`. Future work should convert to GOV.UK Table component.

3. **Profile Settings page** - Not part of dashboard refactor. Will be handled separately.

4. **Account Settings page** - Not part of dashboard refactor. Will be handled separately.

### Completed Post-Refactor Tasks

✅ **Minified CSS** - `civicone-account-nav.min.css` generated (4.4KB → 3.3KB, 24.2% smaller)

✅ **Backward-compatible redirects** - Old `?tab=X` URLs automatically redirect to new dedicated pages with 301 status

### Backward Compatibility

**Old tab URLs are automatically redirected** to new dedicated pages with 301 Permanent Redirect:

| Old URL | Redirects To | Status |
|---------|-------------|--------|
| `/dashboard?tab=overview` | `/dashboard` | 301 Permanent |
| `/dashboard?tab=notifications` | `/dashboard/notifications` | 301 Permanent |
| `/dashboard?tab=groups` | `/dashboard/hubs` | 301 Permanent |
| `/dashboard?tab=hubs` | `/dashboard/hubs` | 301 Permanent |
| `/dashboard?tab=listings` | `/dashboard/listings` | 301 Permanent |
| `/dashboard?tab=wallet` | `/dashboard/wallet` | 301 Permanent |
| `/dashboard?tab=events` | `/dashboard/events` | 301 Permanent |

**Implementation:** Redirect logic added to `DashboardController::index()` method (lines 76-94).

**SEO Benefits:**
- 301 status tells search engines the URL has permanently moved
- Bookmark links automatically update to new URLs
- No duplicate content issues

---

## Appendix: Pattern Justification

### Why Tabs Are Not Module Navigation

From ONS/SIS/NICE/GOV.UK tabs guidance:

> "Tabs are for switching between closely-related views within a single module. If your 'tabs' are actually switching between different functional modules (e.g., Dashboard â Wallet â Messages), you MUST use secondary navigation with separate pages instead."

### Why MOJ Sub Navigation

- âProven accessible pattern used across UK Government services
- âWorks without JavaScript (progressive enhancement)
- âClear visual hierarchy (primary nav â secondary nav â content)
- âMobile responsive out of the box
- âSupports notification badges natively
- âKeyboard accessible by design
- âScreen reader tested

### Why Separate Pages

- âEach section has distinct functionality (not closely-related views)
- âDeep linking and bookmarking support
- âBrowser history works correctly
- âBetter SEO (unique URLs per section)
- âEasier to add new sections in future
- âRespects user expectation (URL = location)

---

## Document History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0.0 | 2026-01-20 | Claude | Initial documentation of dashboard refactor |
| 1.1.0 | 2026-01-20 | Claude | Added minified CSS and backward-compatible redirects |

---

## Approval

This refactor implements Template G: Account Area Template as defined in `CIVICONE_WCAG21AA_SOURCE_OF_TRUTH.md` (Section 10.7).

**Status:** â IMPLEMENTATION COMPLETE - Awaiting Testing

