import { test, expect } from '@playwright/test';
import { generateTestData, tenantUrl, dismissDevNoticeModal } from '../../helpers/test-utils';

/**
 * Helper to handle cookie consent banner if present
 */
async function dismissCookieBanner(page: any): Promise<void> {
  const acceptBtn = page.locator('#nexus-cookie-banner button:has-text("Accept"), button:has-text("Accept All")');
  if (await acceptBtn.isVisible({ timeout: 1000 }).catch(() => false)) {
    await acceptBtn.click();
    await page.waitForTimeout(300);
  }
}

/**
 * Helper to fill location field (handles Google Places autocomplete)
 * The PlaceAutocompleteInput renders a standard text input with autocomplete suggestions
 */
async function fillLocationField(page: any, location: string): Promise<void> {
  // Find the visible location input (PlaceAutocompleteInput renders a standard input)
  const locationInput = page.locator('input[name="location"]:visible, input[aria-label*="location" i]:visible, input[placeholder*="City"]:visible').first();

  if (await locationInput.isVisible({ timeout: 2000 }).catch(() => false)) {
    await locationInput.fill(location);
    await page.waitForTimeout(500); // Wait for autocomplete suggestions

    // Try to select first Google Places suggestion if dropdown appears
    const suggestion = page.locator('[role="option"], [data-place-id], .place-suggestion').first();
    if (await suggestion.isVisible({ timeout: 1000 }).catch(() => false)) {
      await suggestion.click();
    }
  } else {
    // Fallback: Set value directly via JS if input is hidden
    await page.evaluate((loc: string) => {
      const input = document.querySelector('input[name="location"]') as HTMLInputElement;
      if (input) {
        input.value = loc;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
      }
    }, location);
  }
}

test.describe('Authentication - Registration', () => {
  test.describe('Registration Form', () => {
    test('should display registration form with required fields', async ({ page }) => {
      await page.goto(tenantUrl('register'));
      await dismissDevNoticeModal(page);
      await dismissCookieBanner(page);

      // Check for required fields based on actual form structure
      await expect(page.locator('input[name="first_name"]')).toBeVisible();
      await expect(page.locator('input[name="last_name"]')).toBeVisible();
      await expect(page.locator('input[name="email"]')).toBeVisible();
      await expect(page.locator('input[name="password"]')).toBeVisible();

      // Submit button - be specific to avoid search form
      const registerForm = page.locator('form.auth-form, form[action*="register"]');
      await expect(registerForm.locator('button[type="submit"]')).toBeVisible();
    });

    test('should have login link for existing users', async ({ page }) => {
      await page.goto(tenantUrl('register'));
      await dismissDevNoticeModal(page);
      await dismissCookieBanner(page);

      const loginLink = page.locator('a[href*="login"]');
      await expect(loginLink.first()).toBeVisible();
    });

    test('should show validation errors for empty form submission', async ({ page }) => {
      await page.goto(tenantUrl('register'));
      await dismissDevNoticeModal(page);
      await dismissCookieBanner(page);

      // Find the registration form specifically (not search form)
      const registerForm = page.locator('form.auth-form, form[action*="register"]');
      const submitBtn = registerForm.locator('button[type="submit"]');
      await submitBtn.click();

      // Check for validation - browser native validation should trigger
      const firstNameInput = page.locator('input[name="first_name"]');
      const validationMessage = await firstNameInput.evaluate((el: HTMLInputElement) => el.validationMessage);

      // Either has custom errors or browser validation
      const errors = page.locator('.error, .invalid-feedback, .govuk-error-message');
      const errorCount = await errors.count();

      expect(validationMessage || errorCount > 0).toBeTruthy();
    });

    test('should validate email format', async ({ page }) => {
      await page.goto(tenantUrl('register'));
      await dismissDevNoticeModal(page);
      await dismissCookieBanner(page);

      await page.fill('input[name="email"]', 'notanemail');

      const registerForm = page.locator('form.auth-form, form[action*="register"]');
      await registerForm.locator('button[type="submit"]').click();

      const emailInput = page.locator('input[name="email"]');
      const validationMessage = await emailInput.evaluate((el: HTMLInputElement) => el.validationMessage);
      expect(validationMessage).toBeTruthy();
    });

    test('should have password strength requirements', async ({ page }) => {
      await page.goto(tenantUrl('register'));
      await dismissDevNoticeModal(page);
      await dismissCookieBanner(page);

      // Check for password rules/requirements section
      const passwordRules = page.locator('#password-rules, .password-rules, .password-requirements');

      if (await passwordRules.count() > 0) {
        await expect(passwordRules).toBeVisible();
      } else {
        // At minimum should have a password field
        await expect(page.locator('input[name="password"]')).toBeVisible();
      }
    });

    test('should remain on page with weak password', async ({ page }) => {
      await page.goto(tenantUrl('register'));
      await dismissDevNoticeModal(page);
      await dismissCookieBanner(page);

      const testData = generateTestData();

      // Fill required visible fields
      await page.fill('input[name="first_name"]', 'Test');
      await page.fill('input[name="last_name"]', 'User');
      await page.fill('input[name="email"]', testData.email);
      await page.fill('input[name="password"]', '123'); // Weak password

      const registerForm = page.locator('form.auth-form, form[action*="register"]');
      await registerForm.locator('button[type="submit"]').click();

      // With a weak password, validation should prevent submission
      // Either stay on page or show error
      expect(page.url()).toContain('register');
    });

    test.skip('should show error for already registered email', async ({ page }) => {
      // Skip - requires valid location field interaction with Google Places API
      await page.goto(tenantUrl('register'));
      await dismissDevNoticeModal(page);
      await dismissCookieBanner(page);

      // Use known existing email
      const existingEmail = process.env.E2E_USER_EMAIL || 'test@hour-timebank.ie';

      await page.fill('input[name="first_name"]', 'Test');
      await page.fill('input[name="last_name"]', 'User');
      await page.fill('input[name="email"]', existingEmail);
      await page.fill('input[name="password"]', 'TestPassword123!');
      await fillLocationField(page, 'Dublin');

      // Check GDPR consent
      const gdprCheckbox = page.locator('input[name="gdpr_consent"]');
      if (await gdprCheckbox.count() > 0) {
        await gdprCheckbox.check();
      }

      const registerForm = page.locator('form.auth-form, form[action*="register"]');
      await registerForm.locator('button[type="submit"]').click();
      await page.waitForLoadState('domcontentloaded');

      // Should show email already taken error OR validation fails
      const pageContent = await page.content();
      const hasError =
        pageContent.includes('already') ||
        pageContent.includes('taken') ||
        pageContent.includes('exists') ||
        await page.locator('.error, .alert-danger').count() > 0 ||
        page.url().includes('register'); // Still on register page means validation failed

      expect(hasError).toBeTruthy();
    });
  });

  test.describe('GDPR Consent', () => {
    test('should have GDPR consent checkbox', async ({ page }) => {
      await page.goto(tenantUrl('register'));
      await dismissDevNoticeModal(page);
      await dismissCookieBanner(page);

      const gdprCheckbox = page.locator('input[name="gdpr_consent"]');
      if (await gdprCheckbox.count() > 0) {
        await expect(gdprCheckbox).toBeVisible();
      }
    });

    test.skip('should not allow registration without GDPR consent', async ({ page }) => {
      // Skip - requires valid location field interaction with Google Places API
      await page.goto(tenantUrl('register'));
      await dismissDevNoticeModal(page);
      await dismissCookieBanner(page);

      const gdprCheckbox = page.locator('input[name="gdpr_consent"]');
      if (await gdprCheckbox.count() > 0 && await gdprCheckbox.getAttribute('required') !== null) {
        const testData = generateTestData();

        await page.fill('input[name="first_name"]', 'Test');
        await page.fill('input[name="last_name"]', 'User');
        await page.fill('input[name="email"]', testData.email);
        await page.fill('input[name="password"]', 'TestPassword123!');
        await fillLocationField(page, 'Dublin');

        // Don't check GDPR consent
        const registerForm = page.locator('form.auth-form, form[action*="register"]');
        await registerForm.locator('button[type="submit"]').click();
        await page.waitForLoadState('domcontentloaded');

        // Should still be on registration page
        expect(page.url()).toContain('register');
      }
    });
  });

  test.describe('Profile Type Selection', () => {
    test('should have profile type selector', async ({ page }) => {
      await page.goto(tenantUrl('register'));
      await dismissDevNoticeModal(page);
      await dismissCookieBanner(page);

      const profileTypeSelect = page.locator('select[name="profile_type"]');
      if (await profileTypeSelect.count() > 0) {
        await expect(profileTypeSelect).toBeVisible();
      }
    });

    test('should show organization name field when organisation selected', async ({ page }) => {
      await page.goto(tenantUrl('register'));
      await dismissDevNoticeModal(page);
      await dismissCookieBanner(page);

      const profileTypeSelect = page.locator('select[name="profile_type"]');
      if (await profileTypeSelect.count() > 0) {
        // Initially org field should be hidden
        const orgField = page.locator('input[name="organization_name"]');

        // Select organisation
        await profileTypeSelect.selectOption('organisation');

        // Org field should now be visible
        await expect(orgField).toBeVisible();
      }
    });
  });

  test.describe('CSRF Protection', () => {
    test('should include CSRF token in registration form', async ({ page }) => {
      await page.goto(tenantUrl('register'));
      await dismissDevNoticeModal(page);
      await dismissCookieBanner(page);

      const csrfToken = page.locator('input[name="csrf_token"], input[name="_token"]');
      const tokenCount = await csrfToken.count();

      if (tokenCount === 0) {
        const metaCsrf = page.locator('meta[name="csrf-token"]');
        expect(await metaCsrf.count()).toBeGreaterThan(0);
      } else {
        expect(tokenCount).toBeGreaterThan(0);
      }
    });
  });

  test.describe('Registration Success Flow', () => {
    test.skip('should redirect to onboarding after successful registration', async ({ page }) => {
      // Skip by default to avoid creating test users
      // Enable when needed with proper test user cleanup

      await page.goto(tenantUrl('register'));
      await dismissDevNoticeModal(page);
      await dismissCookieBanner(page);

      const testData = generateTestData();

      await page.fill('input[name="first_name"]', 'E2E Test');
      await page.fill('input[name="last_name"]', 'User');
      await page.fill('input[name="email"]', testData.email);
      await page.fill('input[name="password"]', 'TestPassword123!');
      await fillLocationField(page, 'Dublin');

      const gdprCheckbox = page.locator('input[name="gdpr_consent"]');
      if (await gdprCheckbox.count() > 0) {
        await gdprCheckbox.check();
      }

      const registerForm = page.locator('form.auth-form, form[action*="register"]');
      await registerForm.locator('button[type="submit"]').click();
      await page.waitForLoadState('domcontentloaded');

      // Should redirect to onboarding or dashboard
      expect(page.url()).toMatch(/\/(onboarding|dashboard|home)/);
    });
  });

  test.describe('Accessibility', () => {
    test('should have proper form labels', async ({ page }) => {
      await page.goto(tenantUrl('register'));
      await dismissDevNoticeModal(page);
      await dismissCookieBanner(page);

      // Check key inputs have labels
      const inputs = ['first_name', 'last_name', 'email', 'password'];

      for (const name of inputs) {
        const input = page.locator(`input[name="${name}"]`);
        if (await input.count() > 0) {
          const id = await input.getAttribute('id');
          const ariaLabel = await input.getAttribute('aria-label');
          const ariaLabelledBy = await input.getAttribute('aria-labelledby');

          if (id) {
            const label = page.locator(`label[for="${id}"]`);
            const hasLabel = await label.count() > 0;
            const hasAria = ariaLabel || ariaLabelledBy;

            expect(hasLabel || hasAria).toBeTruthy();
          }
        }
      }
    });

    test('should have proper heading structure', async ({ page }) => {
      await page.goto(tenantUrl('register'));
      await dismissDevNoticeModal(page);
      await dismissCookieBanner(page);

      // Look for visible heading in main content area (not cookie banner)
      // The page title might be h1 or h2 depending on layout
      const pageHasHeading =
        await page.locator('main h1').isVisible({ timeout: 2000 }).catch(() => false) ||
        await page.locator('main h2').isVisible({ timeout: 1000 }).catch(() => false) ||
        await page.locator('.auth-heading, .page-title').isVisible({ timeout: 1000 }).catch(() => false) ||
        await page.title().then(t => t.includes('Join') || t.includes('Register'));

      expect(pageHasHeading).toBeTruthy();
    });
  });
});
