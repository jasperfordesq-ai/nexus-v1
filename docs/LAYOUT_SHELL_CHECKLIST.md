# Layout Shell Parity - Manual Test Checklist

This document contains 12 manual checks to verify the React frontend layout shell matches the legacy modern theme structure.

## Prerequisites

- React dev server running (`cd react-frontend && npm run dev`)
- Backend running at `http://staging.timebank.local`
- Test user account (with and without 2FA)
- Browser dev tools open

---

## Desktop Tests (viewport > 640px)

### Test 1: Header - Brand Display

**Steps:**
1. Open `http://localhost:5173/` in desktop browser
2. Observe the header/navbar

**Expected:**
- [ ] Logo or tenant name visible on the left
- [ ] Logo uses `tenant.branding.logo_url` if available
- [ ] Falls back to text `tenant.name` if no logo

---

### Test 2: Header - Primary Navigation

**Steps:**
1. View the header on desktop
2. Note the navigation links in the center

**Expected:**
- [ ] Navigation links centered in header
- [ ] Links visible: Home, Listings, Events, Groups, Volunteering, About, Contact
- [ ] Links hidden if feature is disabled (e.g., no Events link if `features.events = false`)
- [ ] Active link highlighted (different color/style)

---

### Test 3: Header - Guest State

**Steps:**
1. Clear localStorage: `localStorage.clear()`
2. Refresh the page
3. Observe the right side of the header

**Expected:**
- [ ] "Sign In" button visible on the right
- [ ] No avatar or user dropdown visible
- [ ] Clicking "Sign In" navigates to `/login`

---

### Test 4: Header - Authenticated State

**Steps:**
1. Log in with a test user
2. Observe the right side of the header

**Expected:**
- [ ] User avatar visible (or initials if no avatar)
- [ ] Clicking avatar opens dropdown menu
- [ ] Dropdown shows user name and email
- [ ] Dropdown contains: Dashboard, Messages (if enabled), Wallet (if enabled), Profile, Settings, Log Out
- [ ] "Log Out" triggers logout and redirects to `/login`

---

### Test 5: Footer - 4-Column Structure

**Steps:**
1. Scroll to the bottom of any page
2. Observe the footer

**Expected:**
- [ ] Footer has dark background (gray-900)
- [ ] Four columns visible on desktop:
  - Column 1: Brand/logo, tagline, social links
  - Column 2: Quick Links (Home, Listings, etc.)
  - Column 3: Resources (About, Contact, Help, etc.)
  - Column 4: Contact info (email, phone, address)
- [ ] Bottom bar with copyright and legal links (Privacy, Terms)

---

### Test 6: Footer - Feature-Gated Links

**Steps:**
1. View footer quick links section
2. Compare with tenant features

**Expected:**
- [ ] Links only show for enabled features
- [ ] If `features.events = false`, no Events link in footer
- [ ] If `features.blog = false`, no Blog link in Resources
- [ ] Social links only show if `tenant.social.{platform}` is set

---

## Mobile Tests (viewport < 640px)

### Test 7: Mobile - Header Hamburger Menu

**Steps:**
1. Resize browser to mobile width (< 640px)
2. Observe the header

**Expected:**
- [ ] Hamburger menu icon visible on the left
- [ ] Primary nav links hidden (not in center)
- [ ] Brand logo/name still visible
- [ ] User avatar or Sign In button still visible on right

---

### Test 8: Mobile - Hamburger Menu Open

**Steps:**
1. On mobile viewport, click the hamburger menu
2. Observe the dropdown/slide-out menu

**Expected:**
- [ ] Menu opens with all navigation links
- [ ] Links include: Home, Listings, Events, Groups, Volunteering, About, Contact
- [ ] If authenticated, shows Account section with: Dashboard, Messages, Wallet, Profile, Settings, Log Out
- [ ] If guest, shows "Sign In" link
- [ ] Clicking a link closes the menu and navigates

---

### Test 9: Mobile - Bottom Navigation Bar

**Steps:**
1. On mobile viewport, observe the bottom of the screen
2. Note the fixed bottom navigation bar

**Expected:**
- [ ] Bottom nav bar is fixed at screen bottom
- [ ] Shows 4-5 icon tabs: Home, Listings, Events (or Groups), Messages (if auth), Profile (if auth) or Sign In
- [ ] Active tab is highlighted (primary color)
- [ ] Tapping a tab navigates to that page
- [ ] Safe area padding at bottom (for notched phones)

---

### Test 10: Mobile - Footer Hidden

**Steps:**
1. On mobile viewport, scroll to bottom of content
2. Check if footer is visible

**Expected:**
- [ ] Desktop footer is hidden on mobile
- [ ] Content area has bottom padding for mobile nav bar (pb-20)
- [ ] No overlap between content and bottom nav

---

## Feature-Gated Navigation Tests

### Test 11: Disabled Feature - Nav Link Hidden

**Steps:**
1. Ensure tenant has a feature disabled (e.g., `features.events = false`)
2. Check header nav, hamburger menu, bottom nav, and footer

**Expected:**
- [ ] Events link NOT visible in header nav
- [ ] Events link NOT visible in hamburger menu
- [ ] Events tab NOT visible in bottom nav
- [ ] Events link NOT visible in footer quick links
- [ ] Direct navigation to `/events` shows 404 page

---

### Test 12: Auth-Only Routes - Protection

**Steps:**
1. Log out (clear session)
2. Try to navigate directly to `/dashboard`, `/messages`, `/wallet`, `/profile`, `/settings`

**Expected:**
- [ ] Redirected to `/login` for all protected routes
- [ ] After login, should redirect back to intended destination (stretch goal)
- [ ] When logged in, can access all these routes normally
- [ ] Protected routes that also need features (Messages, Wallet) show 404 if feature disabled

---

## Test Results Template

| Test | Desktop | Mobile | Notes |
|------|---------|--------|-------|
| 1. Brand Display | PASS/FAIL | N/A | |
| 2. Primary Nav | PASS/FAIL | N/A | |
| 3. Guest State | PASS/FAIL | PASS/FAIL | |
| 4. Auth State | PASS/FAIL | PASS/FAIL | |
| 5. Footer Structure | PASS/FAIL | N/A | |
| 6. Footer Feature Gates | PASS/FAIL | N/A | |
| 7. Hamburger Menu | N/A | PASS/FAIL | |
| 8. Hamburger Open | N/A | PASS/FAIL | |
| 9. Bottom Nav | N/A | PASS/FAIL | |
| 10. Footer Hidden Mobile | N/A | PASS/FAIL | |
| 11. Disabled Feature | PASS/FAIL | PASS/FAIL | |
| 12. Auth Protection | PASS/FAIL | PASS/FAIL | |

---

## Troubleshooting

### Header not showing

1. Check console for React errors
2. Verify `AppShell` is the route element wrapper in `App.tsx`
3. Check that Hero UI is properly configured

### Bottom nav overlapping content

1. Ensure main content has `pb-20 sm:pb-0` padding
2. Check that MobileNav has `z-50` and `fixed bottom-0`

### Feature links showing when disabled

1. Verify `useFeature` hook is being used correctly
2. Check that tenant bootstrap returns correct `features` object
3. Ensure `FeatureRoute` wrapper is applied to feature-gated routes

### Protected routes not redirecting

1. Check `ProtectedRoute` is wrapping the route element
2. Verify `useAuth` hook returns correct `isAuthenticated` state
3. Check that login properly sets auth state

---

## Files Changed

| File | Purpose |
|------|---------|
| `src/components/layout/Header.tsx` | NEW - Header with brand, nav, user menu |
| `src/components/layout/MobileNav.tsx` | NEW - Bottom navigation for mobile |
| `src/components/layout/Footer.tsx` | NEW - 4-column footer |
| `src/components/layout/AppShell.tsx` | NEW - Layout wrapper |
| `src/components/layout/ProtectedRoute.tsx` | NEW - Auth-only route wrapper |
| `src/components/layout/index.ts` | NEW - Layout module exports |
| `src/components/index.ts` | UPDATED - Added layout exports |
| `src/pages/PlaceholderPage.tsx` | NEW - Placeholder pages |
| `src/pages/index.ts` | UPDATED - Added placeholder exports |
| `src/App.tsx` | UPDATED - New route tree with AppShell |
