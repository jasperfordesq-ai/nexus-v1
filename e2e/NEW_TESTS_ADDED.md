# New E2E Tests Added - Critical User Flows

## Summary

Added **5 new comprehensive test spec files** covering critical user flows with **41 total tests** across authentication, marketplace, exchanges, messaging, and responsive design.

## Files Added (New)

### Test Specifications (5 files, 41 tests)

1. **`tests/auth.spec.ts`** - NEW
   - 10 authentication flow tests
   - Login, logout, signup, validation, redirects
   - Tags: @smoke, @critical, @regression

2. **`tests/listings.spec.ts`** - NEW
   - 8 marketplace tests
   - Create, edit, delete, search, filter listings
   - Tags: @smoke, @critical, @regression

3. **`tests/exchange.spec.ts`** - NEW
   - 8 exchange workflow tests
   - Request, accept, complete, review, decline exchanges
   - Tags: @smoke, @critical, @regression

4. **`tests/messages.spec.ts`** - NEW
   - 7 messaging system tests
   - Send, reply, archive, search messages
   - Tags: @critical, @regression

5. **`tests/responsive.spec.ts`** - NEW
   - 8 responsive design tests
   - Mobile, tablet, orientation, touch targets
   - Tags: @smoke, @critical, @regression

### Helper Files (Enhanced/Created)

- **`helpers/auth.ts`** - ENHANCED
  - Added comprehensive authentication helpers
  - Functions: `loginAsUser()`, `logout()`, `signUp()`, `ensureLoggedIn()`, `isLoggedIn()`
  - Robust error handling and waiting strategies

- **`helpers/fixtures.ts`** - ENHANCED
  - Added test data generators
  - Common selector library
  - Test user configurations
  - `generateTestData()` function with timestamps
  - `waitForToast()` helper

### Configuration Files (Updated)

- **`playwright.config.ts`** - UPDATED
  - Added mobile browsers (Pixel 5, iPhone 12)
  - Enhanced retry and timeout settings
  - Video/screenshot capture on failure
  - Trace collection on retry

- **`package.json`** - UPDATED
  - Added tagged test scripts (@smoke, @critical, @regression)
  - Enhanced npm scripts for debugging

- **`.env.e2e`** - CREATED
  - Test environment configuration
  - Credentials and tenant settings

- **`.env.e2e.example`** - CREATED
  - Example environment file for setup

### Documentation

- **`CRITICAL_USER_FLOWS_TESTS.md`** - NEW
  - Comprehensive summary document
  - Test coverage statistics
  - Usage examples
  - Best practices

- **`README.md`** - UPDATED
  - Added new test coverage section
  - Enhanced troubleshooting guide
  - Added tagged test execution instructions

## Integration with Existing Tests

The new tests complement the existing E2E suite:

| Existing Tests | New Tests Added | Total Coverage |
|----------------|-----------------|----------------|
| auth/login.spec.ts | auth.spec.ts | Comprehensive auth flows |
| listings/listings.spec.ts | listings.spec.ts | Enhanced marketplace testing |
| messages/messages.spec.ts | messages.spec.ts | Messaging system coverage |
| (none) | exchange.spec.ts | NEW - Exchange workflow |
| (none) | responsive.spec.ts | NEW - Responsive design |

## Test Coverage Breakdown

### Authentication (10 tests) - @smoke @critical
- ✅ Display login page
- ✅ Login with valid credentials
- ✅ Show error with invalid credentials
- ✅ Logout successfully
- ✅ Display registration page
- ✅ Register new user
- ✅ Show validation errors for empty fields
- ✅ Redirect to login when accessing protected route
- ✅ Display password reset link
- ✅ Navigate to registration from login

### Listings Marketplace (8 tests) - @smoke @critical @regression
- ✅ Display listings page
- ✅ Create new listing
- ✅ Search for listings
- ✅ View listing detail
- ✅ Edit own listing
- ✅ Delete own listing
- ✅ Filter listings by category
- ✅ Show validation errors for incomplete listing

### Exchange Workflow (8 tests) - @smoke @critical @regression
- ✅ Browse listings as logged-in user
- ✅ Request exchange on a listing
- ✅ View pending exchanges
- ✅ Accept exchange as listing owner
- ✅ Mark exchange as complete
- ✅ Leave review after exchange
- ✅ Decline exchange request
- ✅ View exchange history

### Messaging System (7 tests) - @critical @regression
- ✅ Display messages page
- ✅ Send new message
- ✅ View message thread
- ✅ Reply to message
- ✅ Show notification badge for new messages
- ✅ Archive conversation
- ✅ Search messages
- ✅ Delete message

### Responsive Design (8 tests) - @smoke @critical @regression
- ✅ Display mobile drawer on mobile viewport
- ✅ Navigate using mobile drawer
- ✅ Display forms correctly on mobile
- ✅ Display cards in grid on mobile
- ✅ Display navbar correctly on tablet
- ✅ Handle orientation change
- ✅ Display modals correctly on mobile
- ✅ Render touch-friendly buttons on mobile

## Quick Start

### Install and Run

```bash
cd e2e
npm install
npm run install  # Install Playwright browsers

# Run all new critical flow tests
npm test tests/auth.spec.ts
npm test tests/listings.spec.ts
npm test tests/exchange.spec.ts
npm test tests/messages.spec.ts
npm test tests/responsive.spec.ts

# Or run by tag
npm run test:smoke      # Quick smoke tests
npm run test:critical   # Critical path tests
```

### Debug a Test

```bash
npm run test:debug tests/exchange.spec.ts
```

### View Results

```bash
npm run report  # Open HTML report
```

## Example Test - Complete Exchange Flow

This example shows the comprehensive testing approach:

```typescript
test('should request exchange on a listing @critical', async ({ page }) => {
  // Navigate to listings
  await page.goto(`/${testTenant.slug}/listings`);

  // Wait for listings to load (robust waiting)
  await page.waitForSelector(selectors.listingCard, {
    state: 'visible',
    timeout: 10000
  });

  // Click on first listing
  await page.locator(selectors.listingCard).first().click();

  // Wait for detail page (URL verification)
  await page.waitForURL(/\/listings\/\d+/);

  // Request exchange (flexible selector)
  const requestButton = page.locator(
    'button:has-text("Request"), button:has-text("Exchange")'
  );
  await requestButton.click();

  // Fill in exchange request form (conditional)
  const messageField = page.locator('textarea[name="message"]');
  if (await messageField.isVisible()) {
    await messageField.fill('I would like to request this exchange.');
  }

  // Submit request
  await page.click(selectors.submitButton);

  // Verify success (toast notification)
  await expect(page.locator(selectors.toast)).toBeVisible({
    timeout: 5000
  });
});
```

## Key Improvements Over Basic Tests

1. **Robust Selectors** - Multiple fallback strategies
2. **Explicit Waits** - No arbitrary timeouts
3. **Conditional Logic** - Handle optional UI elements
4. **Error Handling** - Graceful failures with screenshots
5. **Clean State** - Each test starts fresh
6. **Data Isolation** - Unique timestamps in test data
7. **Comprehensive Assertions** - Verify UI and navigation
8. **SPDX Headers** - All files compliant with AGPL-3.0

## Next Steps

### Recommended Enhancements

1. **API Mocking** - Add MSW for isolated frontend tests
2. **Visual Regression** - Screenshot comparison testing
3. **Accessibility** - Integrate axe-core audits
4. **Performance** - Lighthouse metrics collection
5. **Database Seeding** - Predictable test data setup

### Maintenance

When adding new features:
1. Add corresponding E2E test to appropriate spec file
2. Use existing helper functions (`loginAsUser`, `generateTestData`, etc.)
3. Tag tests appropriately (@smoke, @critical, @regression)
4. Update this documentation

## Test Execution Matrix

| Browser | Platform | Tests | Status |
|---------|----------|-------|--------|
| Chromium | Desktop | 41 | ✅ Ready |
| Firefox | Desktop | 41 | ✅ Ready |
| Mobile Chrome | Pixel 5 | 41 | ✅ Ready |
| Mobile Safari | iPhone 12 | 41 | ✅ Ready |

## License

Copyright © 2024–2026 Jasper Ford
SPDX-License-Identifier: AGPL-3.0-or-later

See NOTICE file for attribution and acknowledgements.
