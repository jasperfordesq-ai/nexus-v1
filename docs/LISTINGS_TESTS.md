# Listings Manual Test Plan

This document contains 10 manual tests to verify the React frontend listings functionality.

## Prerequisites

- React dev server running (`cd react-frontend && npm run dev`)
- Backend running at `http://staging.timebank.local`
- Test user account (optional - listings are public)
- Some listings exist in the database
- Browser dev tools open (Network tab)

## Test Environment

| Variable | Value |
|----------|-------|
| React URL | `http://localhost:5173` |
| API URL | `http://staging.timebank.local` |
| Tenant ID | 2 (hour-timebank) |

---

## Test 1: Listings Page - Initial Load

**Purpose**: Verify the listings page loads and displays listings.

### Steps

1. Navigate to `http://localhost:5173/listings`
2. Wait for the page to load

### Expected Results

- [ ] Page title shows "Listings"
- [ ] Subtitle shows "Browse services offered and requested by the community"
- [ ] Filter tabs visible: All / Offers / Requests
- [ ] Search input visible
- [ ] Network tab shows `GET /api/v2/listings` returning 200
- [ ] Listings are displayed in a card format
- [ ] Each listing shows: title, type badge (Offering/Requesting), description (truncated), author avatar/name, date
- [ ] "All" tab is selected by default

---

## Test 2: Filter Tabs - Type Filtering

**Purpose**: Verify filter tabs work and update URL.

### Steps

1. Go to `http://localhost:5173/listings`
2. Click "Offers" tab
3. Observe the listings and URL
4. Click "Requests" tab
5. Observe the listings and URL
6. Click "All" tab

### Expected Results

- [ ] Clicking "Offers" shows only offer-type listings
- [ ] URL changes to `/listings?type=offer`
- [ ] Network tab shows `GET /api/v2/listings?type=offer`
- [ ] Clicking "Requests" shows only request-type listings
- [ ] URL changes to `/listings?type=request`
- [ ] Clicking "All" shows all listings
- [ ] URL changes to `/listings` (no type param)
- [ ] Each filter change triggers a new API request

---

## Test 3: Search Functionality

**Purpose**: Verify search input works and updates URL.

### Steps

1. Go to `http://localhost:5173/listings`
2. Type "gardening" (or another term that exists) in the search input
3. Press Enter
4. Observe the results

### Expected Results

- [ ] Search input accepts text
- [ ] Pressing Enter submits the search
- [ ] URL changes to `/listings?q=gardening`
- [ ] Network tab shows `GET /api/v2/listings?q=gardening`
- [ ] Results are filtered to match search term
- [ ] "Showing results for 'gardening'" message appears
- [ ] "Clear" button appears next to the message
- [ ] Clicking "Clear" removes the search and resets URL

---

## Test 4: Combined Filters

**Purpose**: Verify filter tab and search work together.

### Steps

1. Go to `http://localhost:5173/listings`
2. Click "Offers" tab
3. Type "help" in search and press Enter
4. Observe URL and results

### Expected Results

- [ ] URL shows `/listings?type=offer&q=help`
- [ ] Network tab shows `GET /api/v2/listings?type=offer&q=help`
- [ ] Results are offers matching "help"
- [ ] Both filters persist together
- [ ] Changing tab keeps search term
- [ ] Clearing search keeps tab selection

---

## Test 5: URL Parity - Direct Navigation

**Purpose**: Verify URL parameters are read on page load.

### Steps

1. Navigate directly to `http://localhost:5173/listings?type=request&q=cooking`
2. Observe the page state

### Expected Results

- [ ] "Requests" tab is selected
- [ ] Search input shows "cooking"
- [ ] "Showing results for 'cooking'" message appears
- [ ] Results are filtered accordingly
- [ ] No extra API requests made (single request with correct params)

---

## Test 6: Load More Pagination

**Purpose**: Verify infinite scroll pagination works.

### Steps

1. Go to `http://localhost:5173/listings` (ensure > 20 listings exist)
2. Scroll down to see all loaded listings
3. Click "Load More" button

### Expected Results

- [ ] Initial load shows up to 20 listings
- [ ] "Load More" button visible at bottom if more exist
- [ ] Clicking "Load More" shows loading state
- [ ] Network tab shows `GET /api/v2/listings?cursor=...`
- [ ] New listings are appended below existing
- [ ] Button disappears when no more listings
- [ ] "Showing all X listings" message appears when complete

---

## Test 7: Listing Card Navigation

**Purpose**: Verify clicking a listing card navigates to detail page.

### Steps

1. Go to `http://localhost:5173/listings`
2. Click on any listing card

### Expected Results

- [ ] Entire card is clickable (not just title)
- [ ] Navigates to `/listings/:id` (e.g., `/listings/42`)
- [ ] Detail page loads with correct listing
- [ ] Browser back button returns to listings page
- [ ] Previous filter state is preserved on return

---

## Test 8: Listing Detail Page - Content Display

**Purpose**: Verify listing detail page shows all information.

### Steps

1. Navigate to a listing detail page (e.g., `http://localhost:5173/listings/1`)
2. Observe the content

### Expected Results

- [ ] "Back to Listings" button visible
- [ ] Listing title and type badge displayed
- [ ] Category badge shown (if listing has category)
- [ ] Time credits displayed (if applicable)
- [ ] Location displayed (if available)
- [ ] Likes and comments counts shown
- [ ] Full description displayed (not truncated)
- [ ] Posted date shown
- [ ] Author section with avatar, name, and timestamp
- [ ] "Message Owner" CTA button visible

---

## Test 9: Listing Detail - Auth Behavior

**Purpose**: Verify "Message Owner" behavior for auth/guest.

### Steps (Guest)

1. Clear localStorage: `localStorage.clear()`
2. Navigate to a listing detail page
3. Click "Message Owner" button

### Expected Results (Guest)

- [ ] Button shows "Sign In to Message"
- [ ] Clicking redirects to `/login`
- [ ] (Stretch) After login, returns to listing page

### Steps (Authenticated)

1. Log in with a test user
2. Navigate to a listing owned by **another** user
3. Click "Message Owner"

### Expected Results (Authenticated)

- [ ] Button shows "Message Owner"
- [ ] Clicking navigates to `/messages` (placeholder)

### Steps (Own Listing)

1. Navigate to a listing owned by the logged-in user

### Expected Results (Own Listing)

- [ ] "Message Owner" button is NOT shown
- [ ] "This is your listing" badge/chip shown instead

---

## Test 10: Error and Not Found States

**Purpose**: Verify error handling for edge cases.

### Test 10a: Non-existent Listing

1. Navigate to `http://localhost:5173/listings/999999` (invalid ID)
2. Observe the page

**Expected Results:**

- [ ] "Listing Not Found" message displayed
- [ ] Friendly icon/illustration shown
- [ ] "Browse Listings" button links to `/listings`
- [ ] No console errors

### Test 10b: Invalid Listing ID

1. Navigate to `http://localhost:5173/listings/abc` (non-numeric)
2. Observe the page

**Expected Results:**

- [ ] "Listing Not Found" message displayed
- [ ] Page doesn't crash

### Test 10c: Empty Search Results

1. Go to `http://localhost:5173/listings`
2. Search for a term that won't match anything (e.g., "xyzabc123")

**Expected Results:**

- [ ] "No listings found" message displayed
- [ ] "Try adjusting your filters or search terms" hint
- [ ] "Clear Filters" button shown
- [ ] No console errors

### Test 10d: Disabled Feature

1. If possible, disable the `listings` feature for the tenant
2. Navigate to `/listings`

**Expected Results:**

- [ ] 404 page shown (feature-gated route)
- [ ] Footer and nav don't show Listings link

---

## Test Results Template

| Test | Result | Notes |
|------|--------|-------|
| 1. Initial Load | PASS/FAIL | |
| 2. Filter Tabs | PASS/FAIL | |
| 3. Search | PASS/FAIL | |
| 4. Combined Filters | PASS/FAIL | |
| 5. URL Parity | PASS/FAIL | |
| 6. Load More | PASS/FAIL | |
| 7. Card Navigation | PASS/FAIL | |
| 8. Detail Content | PASS/FAIL | |
| 9. Auth Behavior | PASS/FAIL | |
| 10a. Not Found | PASS/FAIL | |
| 10b. Invalid ID | PASS/FAIL | |
| 10c. Empty Results | PASS/FAIL | |
| 10d. Disabled Feature | PASS/FAIL | |

---

## Files Changed

| File | Purpose |
|------|---------|
| `src/api/types.ts` | Added `ListingDetail`, `ListingAttribute`, `ListingDetailResponse` types |
| `src/api/listings.ts` | Added `getListingById()` function |
| `src/api/index.ts` | Exported `getListingById` |
| `src/pages/ListingsPage.tsx` | Added search, URL sync, improved cards with links |
| `src/pages/ListingDetailPage.tsx` | NEW - Full listing detail view |
| `src/pages/index.ts` | Exported `ListingDetailPage` |
| `src/App.tsx` | Added `/listings/:id` route |
| `docs/LISTINGS_CONTRACT.md` | API documentation |
