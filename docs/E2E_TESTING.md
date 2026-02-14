# E2E Testing Guide for Project NEXUS

This document describes the end-to-end (E2E) testing setup for Project NEXUS using Playwright.

## Overview

The E2E test suite provides comprehensive coverage of:
- User authentication flows (login, register, logout)
- Social feed interactions
- Listings marketplace
- Direct messaging
- Events management
- Groups/Hubs
- Wallet/Time credit transfers
- Member directory and profiles
- Admin panel operations
- Mobile responsiveness
- Accessibility compliance

## Quick Start

### Prerequisites

1. Node.js 18+ installed
2. Local development server running at `http://staging.timebank.local`
3. Test user accounts created in the database

### Installation

```bash
# Install dependencies (including Playwright)
npm install

# Install Playwright browsers
npx playwright install chromium
# Or install all browsers
npx playwright install
```

### Configuration

1. Copy the environment template:
```bash
cp e2e/.env.example e2e/.env.test
```

2. Edit `e2e/.env.test` with your test credentials:
```
E2E_BASE_URL=http://staging.timebank.local
E2E_TENANT=hour-timebank
E2E_USER_EMAIL=test@hour-timebank.ie
E2E_USER_PASSWORD=TestPassword123!
E2E_ADMIN_EMAIL=admin@hour-timebank.ie
E2E_ADMIN_PASSWORD=AdminPassword123!
```

### Running Tests

```bash
# Run all tests
npm run test:e2e

# Run tests in headed mode (see browser)
npm run test:e2e:headed

# Run tests with Playwright UI
npm run test:e2e:ui

# Run tests in debug mode
npm run test:e2e:debug

# Run specific test projects
npm run test:e2e:chrome      # Chrome
npm run test:e2e:firefox     # Firefox
npm run test:e2e:mobile      # Mobile Chrome
npm run test:e2e:auth        # Authentication tests only
npm run test:e2e:admin       # Admin panel tests only

# View test report
npm run test:e2e:report

# Generate tests interactively
npm run test:e2e:codegen
```

## Directory Structure

```
e2e/
├── fixtures/           # Test data and authentication state
│   └── .auth/         # Stored authentication sessions
├── helpers/           # Utility functions
│   └── test-utils.ts  # Common test helpers
├── page-objects/      # Page Object Models
│   ├── BasePage.ts    # Base page with common functionality
│   ├── LoginPage.ts   # Login page interactions
│   ├── DashboardPage.ts
│   ├── ListingsPage.ts
│   ├── MessagesPage.ts
│   ├── EventsPage.ts
│   ├── GroupsPage.ts
│   ├── WalletPage.ts
│   ├── MembersPage.ts
│   ├── AdminPage.ts
│   └── index.ts       # Exports all page objects
├── tests/             # Test files organized by feature
│   ├── auth/          # Authentication tests
│   ├── feed/          # Social feed tests
│   ├── listings/      # Listings marketplace tests
│   ├── messages/      # Messaging tests
│   ├── events/        # Events tests
│   ├── groups/        # Groups/Hubs tests
│   ├── wallet/        # Wallet tests
│   ├── members/       # Member directory tests
│   └── admin/         # Admin panel tests
├── reports/           # Generated test reports
├── screenshots/       # Screenshot captures
├── test-results/      # Test artifacts
├── global.setup.ts    # Runs before all tests
├── global.teardown.ts # Runs after all tests
└── .env.example       # Environment template
```

## Test Projects

The test suite is configured with multiple projects:

| Project | Description |
|---------|-------------|
| `chromium` | Chrome (React frontend) |
| `firefox` | Firefox (React frontend) |
| `mobile-chrome` | Mobile Chrome (Pixel 5) |
| `mobile-safari` | Mobile Safari (iPhone 12) |
| `admin` | Admin panel tests with admin auth |
| `unauthenticated` | Public pages and auth flow tests |

## Writing Tests

### Using Page Objects

```typescript
import { test, expect } from '@playwright/test';
import { ListingsPage, CreateListingPage } from '../../page-objects';

test('should create a new listing', async ({ page }) => {
  const listingsPage = new ListingsPage(page);
  await listingsPage.navigate();
  await listingsPage.clickCreateListing();

  const createPage = new CreateListingPage(page);
  await createPage.fillForm({
    title: 'Test Listing',
    description: 'Test description',
    type: 'offer',
  });
  await createPage.submit();

  expect(page.url()).toMatch(/listings\/\d+/);
});
```

### Using Test Utilities

```typescript
import { tenantUrl, generateTestData, waitForAjax } from '../../helpers/test-utils';

test('should search listings', async ({ page }) => {
  await page.goto(tenantUrl('listings'));

  const testData = generateTestData();
  await page.fill('input[name="search"]', testData.title);
  await page.press('input[name="search"]', 'Enter');

  await waitForAjax(page, '/api/listings');
});
```

### Test File Naming

- Test files must end with `.spec.ts`
- Place tests in the appropriate feature directory
- Use descriptive test names

### Test Isolation

Each test should:
- Be independent and not rely on other tests
- Clean up any data it creates (when practical)
- Use generated unique identifiers for test data

## Authentication

The test suite handles authentication automatically:

1. **Global Setup** (`global.setup.ts`) creates authenticated sessions
2. Sessions are stored in `e2e/fixtures/.auth/`
3. Each project uses the appropriate auth state

To test with a specific user type:
```typescript
test.use({ storageState: 'e2e/fixtures/.auth/admin.json' });

test('admin can access settings', async ({ page }) => {
  await page.goto('/admin/settings');
  // ...
});
```

## Skipped Tests

Some tests are skipped by default to prevent:
- Accidental data modification
- Real transfers or transactions
- Registration of new users

To run these tests, remove `test.skip()` and ensure proper test data cleanup.

## CI/CD Integration

### GitHub Actions

The workflow (`.github/workflows/e2e-tests.yml`) runs:

1. **On every PR**: Basic tests (Chrome, unauthenticated)
2. **On main branch push**: Full test suite including cross-browser
3. **Manual trigger**: Select specific project to run

### Required Secrets

Set these in your repository settings:
- `E2E_BASE_URL`: Test environment URL
- `E2E_TENANT`: Tenant slug
- `E2E_USER_EMAIL`: Test user email
- `E2E_USER_PASSWORD`: Test user password
- `E2E_ADMIN_EMAIL`: Admin email
- `E2E_ADMIN_PASSWORD`: Admin password

## Best Practices

### Do's

- Use Page Objects for reusable interactions
- Generate unique test data with `generateTestData()`
- Wait for network idle after actions
- Check for both success states and error handling
- Test accessibility basics (headings, labels, focus)
- Test light and dark mode when making UI changes

### Don'ts

- Don't hardcode test data that conflicts with real data
- Don't skip waiting for page loads
- Don't test implementation details (test user flows)
- Don't modify production data in tests

## Debugging

### Visual Debugging

```bash
# Run with browser visible
npm run test:e2e:headed

# Run with Playwright UI
npm run test:e2e:ui

# Run in debug mode with breakpoints
npm run test:e2e:debug
```

### Traces and Screenshots

Failed tests automatically capture:
- Screenshots at failure point
- Trace files for step-by-step replay

View traces:
```bash
npx playwright show-trace e2e/test-results/path-to-trace.zip
```

### Generating Tests

Use Playwright Codegen to record interactions:
```bash
npm run test:e2e:codegen
```

## Troubleshooting

### Tests fail with "server not accessible"

1. Ensure local server is running
2. Check `E2E_BASE_URL` is correct
3. Verify the tenant slug exists

### Authentication fails

1. Check test user credentials in `.env.test`
2. Verify users exist in database
3. Check for CSRF or session issues

### Flaky tests

1. Add explicit waits for network idle
2. Use more specific selectors
3. Check for animation timings
4. Consider test data isolation

### Mobile tests fail

1. Ensure responsive CSS is loaded
2. Check viewport-specific selectors
3. Verify touch interactions work

## Contributing

When adding new tests:

1. Follow existing patterns and conventions
2. Add Page Objects for new pages
3. Update this documentation
4. Ensure tests pass locally before pushing
5. Test in both light and dark mode

## Resources

- [Playwright Documentation](https://playwright.dev/docs/intro)
- [Page Object Model](https://playwright.dev/docs/pom)
- [Best Practices](https://playwright.dev/docs/best-practices)
