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

5. **Documentation**
   - ✅ Created `ROUTE_MAPPING.md` with legacy → React route map
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
These should mostly work but need verification:
- `dashboard.spec.ts` - May reference legacy dashboard
- `events.spec.ts` - Event creation/detail pages
- `groups.spec.ts` - Group pages
- `listings.spec.ts` - Listing pages (should mostly work)
- `feed.spec.ts` - Feed page (should mostly work)
- `wallet.spec.ts` - Wallet page (should mostly work)
- `messages.spec.ts` - Messages (should mostly work)
- `legal-pages.spec.ts` - Legal pages (should mostly work)

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
3. **Priority 3**: Verify other specs work with current React frontend
4. **Priority 4**: Update `super-admin.spec.ts` (if super admin migrated to React)

## Notes

- React admin is at `/admin/*` (primary)
- Legacy PHP admin is at `/admin-legacy/*` (deprecated, may be removed)
- All routes use tenant slug prefix: `/{tenant}/route`
- Use `tenantUrl()` helper for all navigation
