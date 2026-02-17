import { defineConfig, devices } from '@playwright/test';
import * as dotenv from 'dotenv';
import * as path from 'path';

dotenv.config({ path: path.join(__dirname, 'e2e/.env.test') });

/**
 * Project NEXUS - E2E Test Configuration
 *
 * This configuration supports:
 * - Multi-tenant testing (hour-timebank tenant)
 * - React frontend only
 * - Parallel test execution
 * - Visual regression testing
 * - Mobile viewport testing
 */

export default defineConfig({
  // Test directory
  testDir: './e2e/tests',

  // Test file pattern
  testMatch: '**/*.spec.ts',

  // Global timeout for each test
  timeout: 30000,

  // Expect timeout
  expect: {
    timeout: 5000,
  },

  // Run tests in files in parallel
  fullyParallel: true,

  // Fail the build on CI if you accidentally left test.only in the source code
  forbidOnly: !!process.env.CI,

  // Retry on CI only
  retries: process.env.CI ? 2 : 0,

  // Use fewer workers to avoid resource contention
  workers: process.env.CI ? 1 : 4,

  // Reporter to use
  reporter: [
    ['html', { outputFolder: 'e2e/reports/html' }],
    ['json', { outputFile: 'e2e/reports/results.json' }],
    ['list'],
  ],

  // Shared settings for all projects
  use: {
    // Base URL for the local development server
    baseURL: process.env.E2E_BASE_URL || 'http://staging.timebank.local',

    // Default tenant for tests
    extraHTTPHeaders: {
      'X-Test-Tenant': 'hour-timebank',
    },

    // Collect trace when retrying the failed test
    trace: 'on-first-retry',

    // Screenshot on failure
    screenshot: 'only-on-failure',

    // Video recording
    video: 'on-first-retry',

    // Accept self-signed certificates
    ignoreHTTPSErrors: true,
  },

  // Configure projects for major browsers
  projects: [
    // Setup project for authentication state
    {
      name: 'setup',
      testMatch: /global\.setup\.ts/,
    },

    // Desktop Chrome - React app
    {
      name: 'chromium-modern',
      use: {
        ...devices['Desktop Chrome'],
        storageState: 'e2e/fixtures/.auth/user.json',
      },
      dependencies: ['setup'],
    },

    // Mobile Chrome - Modern Theme
    {
      name: 'mobile-chrome',
      use: {
        ...devices['Pixel 5'],
        storageState: 'e2e/fixtures/.auth/user.json',
      },
      dependencies: ['setup'],
    },

    // Admin user tests
    {
      name: 'admin',
      use: {
        ...devices['Desktop Chrome'],
        storageState: 'e2e/fixtures/.auth/admin.json',
      },
      dependencies: ['setup'],
      testMatch: ['**/admin/**/*.spec.ts', '**/broker/**/*.spec.ts'],
    },

    // Unauthenticated tests (public pages, login, register)
    {
      name: 'unauthenticated',
      use: {
        ...devices['Desktop Chrome'],
        // No storage state - fresh browser
      },
      testMatch: '**/auth/**/*.spec.ts',
    },
  ],

  // Output folder for test artifacts
  outputDir: 'e2e/test-results',

  // Global setup and teardown
  globalSetup: require.resolve('./e2e/global.setup.ts'),
  globalTeardown: require.resolve('./e2e/global.teardown.ts'),
});
