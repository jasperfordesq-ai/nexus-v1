import { chromium, FullConfig } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';
import * as dotenv from 'dotenv';

// Load environment variables from .env.test
dotenv.config({ path: path.join(__dirname, '.env.test') });

/**
 * Global Setup for E2E Tests
 *
 * This script runs once before all tests to:
 * 1. Create authenticated sessions for different user types
 * 2. Set up test data fixtures
 * 3. Verify the test environment is accessible
 */

// Test user credentials (should be configured in .env.test)
const TEST_USERS = {
  modern: {
    email: process.env.E2E_USER_EMAIL || 'test@hour-timebank.ie',
    password: process.env.E2E_USER_PASSWORD || 'TestPassword123!',
    theme: 'modern',
  },
  civicone: {
    email: process.env.E2E_CIVICONE_EMAIL || 'civicone@hour-timebank.ie',
    password: process.env.E2E_CIVICONE_PASSWORD || 'TestPassword123!',
    theme: 'civicone',
  },
  admin: {
    email: process.env.E2E_ADMIN_EMAIL || 'admin@hour-timebank.ie',
    password: process.env.E2E_ADMIN_PASSWORD || 'AdminPassword123!',
    theme: 'modern',
  },
};

const BASE_URL = process.env.E2E_BASE_URL || 'http://staging.timebank.local';
const TENANT_SLUG = process.env.E2E_TENANT || 'hour-timebank';
const MAX_RETRIES = 3;
const RETRY_DELAY = 2000;

async function sleep(ms: number): Promise<void> {
  return new Promise(resolve => setTimeout(resolve, ms));
}

async function checkServerWithRetry(page: any, url: string, retries: number = MAX_RETRIES): Promise<boolean> {
  for (let attempt = 1; attempt <= retries; attempt++) {
    try {
      console.log(`   Attempt ${attempt}/${retries}...`);
      const response = await page.goto(url, { timeout: 15000, waitUntil: 'domcontentloaded' });

      if (response && response.status() < 400) {
        return true;
      }

      // 503 might be temporary - retry
      if (response && response.status() === 503 && attempt < retries) {
        console.log(`   Got 503, waiting ${RETRY_DELAY}ms before retry...`);
        await sleep(RETRY_DELAY);
        continue;
      }

      console.log(`   Server returned status ${response?.status()}`);
    } catch (error) {
      if (attempt < retries) {
        console.log(`   Connection failed, waiting ${RETRY_DELAY}ms before retry...`);
        await sleep(RETRY_DELAY);
        continue;
      }
      throw error;
    }
  }
  return false;
}

async function dismissDevNoticeModal(page: any): Promise<void> {
  // The dev notice modal blocks interactions until dismissed
  // It uses localStorage key 'dev_notice_dismissed' with value matching STORAGE_VERSION
  try {
    // Set localStorage to dismiss the dev notice before it appears
    await page.addInitScript(() => {
      localStorage.setItem('dev_notice_dismissed', '2.1');
    });
  } catch (error) {
    // If we can't set localStorage, try to click the dismiss button
    try {
      const continueBtn = page.locator('#dev-notice-continue');
      if (await continueBtn.isVisible({ timeout: 2000 })) {
        await continueBtn.click();
        await page.waitForTimeout(300); // Wait for modal animation
      }
    } catch {
      // Modal might not be present, continue
    }
  }
}

async function globalSetup(config: FullConfig) {
  console.log('\n🚀 Starting E2E Global Setup...\n');

  // Create auth directory if it doesn't exist
  const authDir = path.join(__dirname, 'fixtures', '.auth');
  if (!fs.existsSync(authDir)) {
    fs.mkdirSync(authDir, { recursive: true });
  }

  // Create empty auth files first (so tests can run even if auth fails)
  const emptyState = { cookies: [], origins: [] };
  for (const userType of ['user-modern', 'user-civicone', 'admin']) {
    const storagePath = path.join(authDir, `${userType}.json`);
    if (!fs.existsSync(storagePath)) {
      fs.writeFileSync(storagePath, JSON.stringify(emptyState, null, 2));
    }
  }

  const browser = await chromium.launch();
  let serverAccessible = false;

  try {
    // Verify server is accessible
    console.log(`📡 Checking server at ${BASE_URL}...`);
    const context = await browser.newContext();
    const page = await context.newPage();

    // Dismiss dev notice modal for server check
    await dismissDevNoticeModal(page);

    try {
      serverAccessible = await checkServerWithRetry(page, `${BASE_URL}/${TENANT_SLUG}/login`);

      if (!serverAccessible) {
        console.warn('⚠️  Server returned error status. Tests will run with empty auth state.');
        console.warn('   Some tests may fail or be skipped.\n');
      } else {
        console.log('✅ Server is accessible\n');
      }
    } catch (error) {
      console.warn('⚠️  Could not connect to server:', error);
      console.warn('   Tests will run with empty auth state.\n');
    }

    await context.close();

    // Only attempt authentication if server is accessible
    if (serverAccessible) {
      // Create authenticated sessions for each user type
      for (const [userType, credentials] of Object.entries(TEST_USERS)) {
        console.log(`🔐 Creating auth session for ${userType} user...`);

        const authContext = await browser.newContext();
        const authPage = await authContext.newPage();

        // Dismiss dev notice modal for this context
        await dismissDevNoticeModal(authPage);

        try {
          // Navigate to login page with retry
          const loginAccessible = await checkServerWithRetry(
            authPage,
            `${BASE_URL}/${TENANT_SLUG}/login`,
            2
          );

          if (!loginAccessible) {
            throw new Error('Login page not accessible');
          }

          // Wait a moment for any modals to appear
          await authPage.waitForTimeout(500);

          // Check if dev notice modal appeared despite our localStorage setting
          const devNoticeBtn = authPage.locator('#dev-notice-continue');
          if (await devNoticeBtn.isVisible({ timeout: 1000 }).catch(() => false)) {
            console.log('   Dismissing dev notice modal...');
            await devNoticeBtn.click();
            await authPage.waitForTimeout(300);
          }

          // Wait for form to be ready
          await authPage.waitForSelector('input[name="email"], input[type="email"]', { timeout: 5000 });

          // Fill login form - be more specific with selectors to avoid search forms
          const emailInput = authPage.locator('form input[name="email"], form input[type="email"]').first();
          const passwordInput = authPage.locator('form input[name="password"], form input[type="password"]').first();

          await emailInput.fill(credentials.email);
          await passwordInput.fill(credentials.password);

          // Submit form - find the submit button within the same form as our inputs
          const loginForm = authPage.locator('form:has(input[name="password"])').first();
          const submitBtn = loginForm.locator('button[type="submit"], input[type="submit"]').first();
          await submitBtn.click();

          // Wait for navigation to dashboard or home (or error)
          await authPage.waitForURL(/\/(dashboard|home|feed|login|\/)/, { timeout: 15000 });

          // Check if login was successful (not still on login page with error)
          const currentUrl = authPage.url();
          if (currentUrl.includes('/login')) {
            const errorMessage = await authPage.locator('.error, .alert-danger').textContent().catch(() => '');
            throw new Error(`Login failed: ${errorMessage || 'Invalid credentials'}`);
          }

          // Save storage state
          const storagePath = path.join(authDir, `${userType === 'modern' ? 'user-modern' : userType === 'civicone' ? 'user-civicone' : userType}.json`);
          await authContext.storageState({ path: storagePath });

          console.log(`✅ Auth session saved for ${userType}\n`);
        } catch (error) {
          console.warn(`⚠️  Could not create auth session for ${userType}: ${error}`);
          console.warn(`   Tests requiring ${userType} auth may fail.\n`);
        }

        await authContext.close();
      }
    }

    console.log('✅ Global setup complete!\n');
  } finally {
    await browser.close();
  }
}

export default globalSetup;
