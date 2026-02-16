# E2E Tests - React Migration Status

## Completed ✅

1. **Global Setup** (`global.setup.ts`)
   - ✅ Removed CivicOne theme auth
   - ✅ Updated to API-based JWT auth (localStorage tokens)
   - ✅ Auth files: `user.json` + `admin.json`
   - ✅ Added E2E_REACT_URL and E2E_API_URL for Docker

2. **Compose Tests** (`compose.spec.ts`)
   - ✅ Updated `compose` → `/feed` for posts
   - ✅ Updated `compose?type=listing` → `/listings/new`
   - ✅ Updated `compose?type=event` → `/events/new`
   - ✅ Updated selectors to match React/HeroUI
   - ✅ All tests now use correct React routes

3. **Admin Tests** (`admin.spec.ts` + `AdminPage.ts`)
   - ✅ Updated page objects to use React admin HeroUI components
   - ✅ Changed selectors: `.admin-stat-card` → `p.text-2xl.font-bold`, `.admin-sidebar` → `nav, aside`
   - ✅ Updated to use semantic selectors (`[role="table"]`, `button[role="switch"]`, etc.)
   - ✅ Added fallback selectors for optional features
   - ✅ Tests now target `/admin/*` (React admin, not `/admin-legacy/*`)

4. **Broker Controls Tests** (`broker-controls.spec.ts` + `BrokerControlsPage.ts`)
   - ✅ Already used HeroUI selectors (StatCard, DataTable, tabs)
   - ✅ Fixed `navigateTo()` to use `tenantUrl()` for tenant routing
   - ✅ Updated quick link count (5 cards: exchanges, risk-tags, messages, monitoring, vetting)
   - ✅ Tests already targeting React admin at `/admin/broker-controls/*`

5. **Legal Pages Tests** (`legal-pages.spec.ts`)
   - ✅ Added `tenantUrl()` to all route navigation
   - ✅ Made tests flexible for custom vs default tenant documents
   - ✅ Added test for custom document TOC rendering

6. **Dashboard Tests** (`dashboard.spec.ts` + `DashboardPage.ts`)
   - ✅ Rewrote DashboardPage page object for React dashboard with GlassCard components
   - ✅ Removed all legacy PHP sub-route tests (/dashboard/listings, /dashboard/hubs, etc.)
   - ✅ React dashboard is single page at /dashboard with sections in cards
   - ✅ Made all assertions flexible for feature-gated content (wallet, groups, events, gamification)

7. **Feed Tests** (`feed.spec.ts` + `FeedPage.ts`)
   - ✅ Created FeedPage page object for React social feed with GlassCard components
   - ✅ Replaced legacy selectors (.feed-post, .post-card) with React/HeroUI patterns
   - ✅ Tests for create post modal (text and poll modes)
   - ✅ Tests for like/comment interactions, filter chips, load more
   - ✅ Skipped destructive tests (create poll, report, hide, delete)

8. **Wallet Tests** (`wallet.spec.ts` + `WalletPage.ts`)
   - ✅ Rewrote WalletPage page object for React with GlassCard components
   - ✅ Updated TransferPage for modal-based transfer (NOT separate route `/wallet/transfer`)
   - ✅ Transfer modal opens on wallet page (no route change)
   - ✅ Tests for filter chips (All, Earned, Spent, Pending) and pagination
   - ✅ Tests for modal elements (recipient search, amount, description, balance display)

9. **Events Tests** (`events.spec.ts` + `EventsPage.ts`)
   - ✅ Rewrote EventsPage, CreateEventPage, EventDetailPage for React (515 lines)
   - ✅ Updated selectors for GlassCard event cards with date badges
   - ✅ Category filter chips (Workshop, Social, Outdoor, Online, etc.)
   - ✅ Time filter select (Upcoming, Past, All Events)
   - ✅ Event detail with tabs (Details, Attendees, Check-in for organizers)
   - ✅ RSVP buttons (Going, Interested, Not Going)
   - ✅ Create/edit form with image upload, validation tests
   - ✅ 53 tests total (browse, search, filters, create, detail, RSVP, accessibility, responsive)

10. **Groups Tests** (`groups.spec.ts` + `GroupsPage.ts`)
   - ✅ Rewrote GroupsPage, GroupDetailPage, CreateGroupPage for React (377 lines)
   - ✅ Group cards with avatar groups and member counts
   - ✅ Filter select (All Groups, My Groups, Public, Private)
   - ✅ Group detail with tabs (Discussions, Members, Events)
   - ✅ Join/Leave buttons
   - ✅ Create group form with privacy settings

11. **Documentation**
   - ✅ Created `ROUTE_MAPPING.md` with legacy → React route map
   - ✅ Created `REACT_ROUTES_REFERENCE.md` with complete React route listing
   - ✅ Created this status document

## Pending 🚧

The following test files still reference legacy PHP frontend routes/selectors and need updating:

### Admin Tests

- `super-admin.spec.ts` - Targets legacy PHP super admin
  - **Fix**: Update for React super admin (if migrated) or mark as legacy

### Page Objects

- `SuperAdminPage.ts` - Legacy selectors (if super admin migrated to React)
- Other page objects may need review for React compatibility

### Other Specs
These need verification (likely need selector updates):
- `listings.spec.ts` - Listing pages (uses ListingsPage page object - likely needs minor updates)
- `messages.spec.ts` - Messages (uses MessagesPage page object - likely needs minor updates)

## How to Fix Remaining Tests

### 1. Update Page Objects

Example for `AdminPage.ts`:

```typescript
// OLD (legacy PHP)
get statsCard() { return this.page.locator('.stat-card, .dashboard-stat'); }
get sidebarNav() { return this.page.locator('.sidebar, .admin-sidebar'); }

// NEW (React + HeroUI)
get statsCard() {
  return this.page.locator('[data-stat-card], .grid > div:has(h3)');
}
get sidebarNav() {
  return this.page.locator('nav[aria-label="Admin"], aside nav');
}
```

### 2. Update Test Expectations

- Legacy tests check for specific HTML classes (`.admin-header`, `.stat-card`, etc.)
- React tests should use semantic selectors (role, aria-label, data attributes)
- HeroUI components have different class structure

### 3. Run Tests Incrementally

```bash
# Test one spec at a time
npm run test:e2e -- admin.spec.ts

# Or test specific test
npm run test:e2e -- admin.spec.ts -g "should display admin dashboard"
```

### 4. Common Selector Patterns

| Legacy PHP | React + HeroUI |
|------------|----------------|
| `.stat-card` | `[data-testid="stat-card"]` or `.grid > div` |
| `.sidebar` | `nav[aria-label="Admin"]` or `aside` |
| `.btn-primary` | `button[color="primary"]` or HeroUI Button classes |
| `.form-control` | `input`, `textarea`, or HeroUI Input classes |
| `.alert-success` | `[role="alert"]`, `.toast`, or HeroUI Alert |

## Quick Wins

These specs likely need minimal changes:
1. `legal-pages.spec.ts` - Just updated this system, should work
2. `pwa.spec.ts` - Service worker tests, likely independent
3. `search.spec.ts` - Search functionality, may work as-is

## Next Steps

1. ~~**Priority 1**: Update `admin.spec.ts` page objects and tests~~ ✅ **DONE**
2. ~~**Priority 2**: Update `broker-controls.spec.ts`~~ ✅ **DONE**
3. ~~**Priority 3a**: Update `legal-pages.spec.ts`~~ ✅ **DONE**
4. ~~**Priority 3b**: Update `dashboard.spec.ts`~~ ✅ **DONE**
5. ~~**Priority 3c**: Update `feed.spec.ts`~~ ✅ **DONE**
6. ~~**Priority 3d**: Update `wallet.spec.ts`~~ ✅ **DONE**
7. ~~**Priority 3e**: Update `events.spec.ts`~~ ✅ **DONE**
8. ~~**Priority 3f**: Update `groups.spec.ts`~~ ✅ **DONE**
9. **Priority 3g**: Verify/update remaining specs:
   - `listings.spec.ts` - Listing pages (uses page objects, likely minor updates)
   - `messages.spec.ts` - Messages (uses page objects, likely minor updates)
10. **Priority 4**: Update `super-admin.spec.ts` (if super admin migrated to React)

## Notes

- React admin is at `/admin/*` (primary)
- Legacy PHP admin is at `/admin-legacy/*` (deprecated, may be removed)
- All routes use tenant slug prefix: `/{tenant}/route`
- Use `tenantUrl()` helper for all navigation
