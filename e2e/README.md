# Project NEXUS - E2E Tests

End-to-end tests for Project NEXUS using Playwright. These tests cover critical user flows across the platform.

## Setup

### Prerequisites

- Node.js 18+ installed
- Docker running (for local dev environment)
- Project NEXUS running locally:
  - React frontend: http://localhost:5173
  - PHP API: http://localhost:8090

### Installation

```bash
cd e2e
npm install
npm run install  # Install Playwright browsers
```

### Configuration

Copy `.env.e2e.example` to `.env.e2e` and configure test credentials:

```bash
cp .env.e2e.example .env.e2e
```

Edit `.env.e2e` with your test user credentials and tenant information.

## Running Tests

### All Tests

```bash
npm test
```

### Headed Mode (See Browser)

```bash
npm run test:headed
```

### Debug Mode

```bash
npm run test:debug
```

### Interactive UI Mode

```bash
npm run test:ui
```

### Specific Test Tags

```bash
npm run test:smoke      # Smoke tests only
npm run test:critical   # Critical path tests
npm run test:regression # Regression tests
```

### Single Test File

```bash
npx playwright test tests/auth.spec.ts
```

### Single Test

```bash
npx playwright test tests/auth.spec.ts -g "should login with valid credentials"
```

## Test Reports

After running tests, view the HTML report:

```bash
npm run report
```

Reports are generated in `playwright-report/`.

## Test Structure

```
e2e/
├── tests/                  # Test specs
│   ├── auth.spec.ts       # Authentication flows
│   ├── listings.spec.ts   # Marketplace tests
│   ├── exchange.spec.ts   # Exchange workflow
│   ├── messages.spec.ts   # Messaging system
│   └── responsive.spec.ts # Mobile responsiveness
├── helpers/               # Test helpers
│   ├── auth.ts           # Login/logout helpers
│   └── fixtures.ts       # Test data generators
├── playwright.config.ts   # Playwright configuration
├── package.json          # Dependencies
└── .env.e2e             # Test environment config
```

## Test Coverage

### Authentication (`auth.spec.ts`) - 10 tests
- ✅ Display login page
- ✅ Login with valid credentials
- ✅ Show error with invalid credentials
- ✅ Logout successfully
- ✅ Display registration page
- ✅ Register new user
- ✅ Show validation errors
- ✅ Redirect to login for protected routes
- ✅ Display password reset link
- ✅ Navigate to registration from login

### Listings Marketplace (`listings.spec.ts`) - 8 tests
- ✅ Display listings page
- ✅ Create new listing
- ✅ Search for listings
- ✅ View listing detail
- ✅ Edit own listing
- ✅ Delete own listing
- ✅ Filter listings by category
- ✅ Show validation errors

### Exchange Workflow (`exchange.spec.ts`) - 8 tests
- ✅ Browse listings
- ✅ Request exchange
- ✅ View pending exchanges
- ✅ Accept exchange
- ✅ Mark exchange complete
- ✅ Leave review
- ✅ Decline exchange
- ✅ View exchange history

### Messaging System (`messages.spec.ts`) - 7 tests
- ✅ Display messages page
- ✅ Send new message
- ✅ View message thread
- ✅ Reply to message
- ✅ Show notification badge
- ✅ Archive conversation
- ✅ Search messages
- ✅ Delete message

### Responsive Design (`responsive.spec.ts`) - 8 tests
- ✅ Display mobile drawer
- ✅ Navigate using mobile drawer
- ✅ Display forms on mobile
- ✅ Display cards in grid on mobile
- ✅ Display navbar on tablet
- ✅ Handle orientation change
- ✅ Display modals on mobile
- ✅ Render touch-friendly buttons

**Total: 41 E2E tests**

## Test Tags

Tests are tagged for selective execution:

- `@smoke` - Quick smoke tests (critical functionality)
- `@critical` - Critical path tests (must pass)
- `@regression` - Regression tests (thorough coverage)

## Debugging Failures

### Screenshots

Screenshots are automatically captured on failure in `test-results/`.

### Videos

Videos are recorded on first retry, saved in `test-results/`.

### Traces

Playwright traces are captured on retry. View with:

```bash
npx playwright show-trace test-results/path-to-trace.zip
```

### Debug Mode

Run specific test in debug mode:

```bash
npx playwright test tests/auth.spec.ts --debug
```

## CI/CD Integration

### GitHub Actions

Add to `.github/workflows/e2e.yml`:

```yaml
name: E2E Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: actions/setup-node@v3
        with:
          node-version: 18
      - name: Install dependencies
        run: |
          cd e2e
          npm ci
          npx playwright install --with-deps
      - name: Start services
        run: docker compose up -d
      - name: Run E2E tests
        run: cd e2e && npm test
      - name: Upload test results
        if: always()
        uses: actions/upload-artifact@v3
        with:
          name: playwright-report
          path: e2e/playwright-report/
```

## Best Practices

1. **Clean State**: Each test starts from a clean state using `beforeEach`
2. **Data Isolation**: Use unique test data per run (timestamps in names)
3. **Explicit Waits**: Always wait for elements/URLs explicitly
4. **Selectors**: Use data-testid attributes where possible, fallback to text/role
5. **Assertions**: Verify both UI state and navigation
6. **Screenshots**: Automatically captured on failure
7. **Retries**: Tests retry 2 times on failure

## Common Issues

### Tests Fail Locally But Pass in CI

- Ensure local environment matches CI (Docker setup, database state)
- Check for timing issues (increase timeouts if needed)

### Authentication Failures

- Verify test user exists in database
- Check credentials in `.env.e2e` match database
- Ensure tenant_id is correct for test user

### Element Not Found

- Check if element selector changed
- Verify page loaded completely before interaction
- Use more specific selectors (data-testid)

### Flaky Tests

- Add explicit waits for dynamic content
- Increase timeout for slow operations
- Check for race conditions in test code

## Contributing

When adding new features:

1. Add E2E tests for new user flows
2. Tag tests appropriately (@smoke, @critical, @regression)
3. Update this README with new test coverage
4. Ensure all tests pass before committing

## License

Copyright © 2024–2026 Jasper Ford
SPDX-License-Identifier: AGPL-3.0-or-later

See NOTICE file for attribution and acknowledgements.
