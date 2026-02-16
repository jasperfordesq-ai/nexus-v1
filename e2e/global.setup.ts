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
  user: {
    email: process.env.E2E_USER_EMAIL || 'test@hour-timebank.ie',
    password: process.env.E2E_USER_PASSWORD || 'TestPassword123!',
    theme: 'react',
  },
  admin: {
    email: process.env.E2E_ADMIN_EMAIL || 'admin@hour-timebank.ie',
    password: process.env.E2E_ADMIN_PASSWORD || 'AdminPassword123!',
    theme: 'react',
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

/**
 * Authenticate a React user via the API (JWT-based).
 *
 * The React app uses JWT tokens stored in localStorage,
 * not session cookies. This function calls the auth API directly,
 * then injects the JWT tokens into the browser's localStorage
 * via the storage state file.
 */
async function authenticateViaApi(
  authContext: any,
  authPage: any,
  credentials: { email: string; password: string; theme: string },
  userType: string,
  authDir: string
): Promise<void> {
  // The PHP API is accessible at the same BASE_URL for local dev (proxied)
  // or via the API_BASE_URL env var for CI/production
  const apiBaseUrl = process.env.E2E_API_URL || BASE_URL;

  console.log(`   Authenticating admin via API at ${apiBaseUrl}/api/auth/login...`);

  // Call the auth API directly to get JWT tokens
  const loginResponse = await authPage.request.post(`${apiBaseUrl}/api/auth/login`, {
    data: {
      email: credentials.email,
      password: credentials.password,
      tenant_slug: TENANT_SLUG,
    },
    headers: {
      'Content-Type': 'application/json',
      'X-Tenant-ID': TENANT_SLUG,
    },
  });

  if (!loginResponse.ok()) {
    const body = await loginResponse.text();
    throw new Error(`API login failed (${loginResponse.status()}): ${body}`);
  }

  const loginData = await loginResponse.json();

  // Extract tokens from response — API returns {success, data: {access_token, refresh_token, user, tenant_id}}
  const accessToken = loginData?.data?.access_token || loginData?.access_token;
  const refreshToken = loginData?.data?.refresh_token || loginData?.refresh_token;
  const tenantId = loginData?.data?.tenant_id || loginData?.tenant_id;

  if (!accessToken) {
    throw new Error('No access_token in login response: ' + JSON.stringify(loginData));
  }

  console.log('   JWT tokens obtained, injecting into localStorage...');

  // Ensure onboarding is marked complete to avoid redirecting to the wizard
  try {
    await authPage.request.post(`${apiBaseUrl}/v2/onboarding/complete`, {
      data: { offers: [], needs: [] },
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${accessToken}`,
        'X-Tenant-ID': TENANT_SLUG,
      },
    });
  } catch {
    // Ignore if already completed or endpoint unavailable
  }

  // Navigate to the React app origin so we can set localStorage
  // Use the React frontend URL (may differ from API URL in Docker setup)
  const reactUrl = process.env.E2E_REACT_URL || BASE_URL;
  await authPage.goto(`${reactUrl}/${TENANT_SLUG}/login`, { waitUntil: 'domcontentloaded', timeout: 10000 }).catch(() => {
    // If /login redirects or fails, try the root
    return authPage.goto(reactUrl, { waitUntil: 'domcontentloaded', timeout: 10000 });
  });

  // Inject JWT tokens into localStorage
  await authPage.evaluate(
    ({ accessToken, refreshToken, tenantId }: { accessToken: string; refreshToken?: string; tenantId?: string | number }) => {
      localStorage.setItem('nexus_access_token', accessToken);
      if (refreshToken) {
        localStorage.setItem('nexus_refresh_token', refreshToken);
      }
      if (tenantId) {
        localStorage.setItem('nexus_tenant_id', String(tenantId));
      }
      // Dismiss dev notice
      localStorage.setItem('dev_notice_dismissed', '2.1');
    },
    { accessToken, refreshToken, tenantId: tenantId ? String(tenantId) : undefined }
  );

  // Save storage state (includes localStorage with JWT tokens)
  const storagePath = path.join(authDir, `${userType}.json`);
  await authContext.storageState({ path: storagePath });
}

/**
 * Authenticate user via the legacy PHP form login (session-based).
 * Legacy-only. Not used for the React SPA.
 */
async function authenticateViaForm(
  authContext: any,
  authPage: any,
  credentials: { email: string; password: string; theme: string },
  userType: string,
  authDir: string
): Promise<void> {
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

  // Check if dev notice modal appeared
  const devNoticeBtn = authPage.locator('#dev-notice-continue');
  if (await devNoticeBtn.isVisible({ timeout: 1000 }).catch(() => false)) {
    console.log('   Dismissing dev notice modal...');
    await devNoticeBtn.click();
    await authPage.waitForTimeout(300);
  }

  // Wait for form to be ready
  await authPage.waitForSelector('input[name="email"], input[type="email"]', { timeout: 5000 });

  // Fill login form
  const emailInput = authPage.locator('form input[name="email"], form input[type="email"]').first();
  const passwordInput = authPage.locator('form input[name="password"], form input[type="password"]').first();

  await emailInput.fill(credentials.email);
  await passwordInput.fill(credentials.password);

  // Submit form
  const loginForm = authPage.locator('form:has(input[name="password"])').first();
  const submitBtn = loginForm.locator('button[type="submit"], input[type="submit"]').first();
  await submitBtn.click();

  // Wait for navigation
  await authPage.waitForURL(/\/(dashboard|home|feed|login|\/)/, { timeout: 15000 });

  // Check if login was successful
  const currentUrl = authPage.url();
  if (currentUrl.includes('/login')) {
    const errorMessage = await authPage.locator('.error, .alert-danger').textContent().catch(() => '');
    throw new Error(`Login failed: ${errorMessage || 'Invalid credentials'}`);
  }

  // Save storage state
  const storageName = userType === 'user' ? 'user' : userType;
  const storagePath = path.join(authDir, `${storageName}.json`);
  await authContext.storageState({ path: storagePath });
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
  for (const userType of ['user', 'admin']) {
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
          // All React users authenticate via the API (JWT-based)
          await authenticateViaApi(authContext, authPage, credentials, userType, authDir);

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
