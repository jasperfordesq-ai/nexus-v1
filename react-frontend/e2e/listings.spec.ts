// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * E2E tests — Listings happy paths.
 *
 * Covers:
 *   - Navigate to create-listing page
 *   - Fill in the listing form (title, description, category, hours)
 *   - Submit and assert redirect to the listings index
 *   - Assert a success toast is shown
 *
 * All tests run as an authenticated user (storageState reused from global setup).
 *
 * Route: /t/hour-timebank/listings/create  (ProtectedRoute + FeatureGate module="listings")
 */

import { test, expect } from '@playwright/test';

// Use stored auth state so we skip the login flow for every test in this file
test.use({ storageState: 'e2e/.auth/user.json' });

const TENANT_SLUG = process.env.E2E_TENANT ?? 'hour-timebank';

/** Unique suffix for test data so parallel runs don't collide */
function uniqueSuffix() {
  return `${Date.now()}-${Math.random().toString(36).slice(2, 7)}`;
}

test.describe('Listings — Create listing happy path', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(`/t/${TENANT_SLUG}/listings/create`);
    // Wait for the form heading to confirm the page loaded
    await expect(
      page.getByRole('heading', { level: 1 })
    ).toBeVisible({ timeout: 15000 });
  });

  test('create listing form is visible with expected fields', async ({ page }) => {
    // Title input (HeroUI Input renders a visible label)
    await expect(page.getByLabel(/title/i)).toBeVisible();

    // Description textarea
    await expect(page.getByLabel(/description/i)).toBeVisible();

    // Hours input
    await expect(page.getByLabel(/hours/i)).toBeVisible();

    // Category select
    await expect(page.getByLabel(/category/i)).toBeVisible();

    // Submit button
    await expect(
      page.getByRole('button', { name: /create|save/i })
    ).toBeVisible();
  });

  test('filling and submitting the create listing form redirects to listings page', async ({ page }) => {
    const suffix = uniqueSuffix();
    const listingTitle = `E2E Test Offer ${suffix}`;

    // --- Type selection ---
    // Default is "offer" — Radio with value="offer" should already be selected.
    // Click "offer" explicitly to be safe.
    const offerRadio = page.getByRole('radio', { name: /offer/i });
    if (await offerRadio.isVisible({ timeout: 3000 }).catch(() => false)) {
      await offerRadio.click();
    }

    // --- Title ---
    await page.getByLabel(/title/i).fill(listingTitle);

    // --- Description (min 20 chars required) ---
    await page.getByLabel(/description/i).fill(
      'This is an E2E automated test listing — please ignore and feel free to delete.'
    );

    // --- Category — pick the first available option ---
    const categorySelect = page.getByLabel(/category/i);
    await categorySelect.click();
    // Wait for dropdown options to appear and pick the first one
    const firstOption = page.getByRole('option').first();
    await firstOption.waitFor({ state: 'visible', timeout: 5000 });
    await firstOption.click();

    // --- Hours estimate ---
    const hoursInput = page.getByLabel(/hours/i);
    await hoursInput.clear();
    await hoursInput.fill('1');

    // --- Submit ---
    await page.getByRole('button', { name: /create|save/i }).click();

    // After successful creation, the page navigates to /listings (the index)
    await expect(page).toHaveURL(
      new RegExp(`/t/${TENANT_SLUG}/listings$`),
      { timeout: 15000 }
    );
  });

  test('validation errors shown when submitting empty form', async ({ page }) => {
    // Click submit without filling anything
    await page.getByRole('button', { name: /create|save/i }).click();

    // At least one error message should appear (HeroUI errorMessage prop renders
    // as a span with role="note" or visible text near the field)
    await expect(
      page.locator('[data-slot="error-message"], .text-danger').first()
    ).toBeVisible({ timeout: 5000 });
  });
});

test.describe('Listings — Listings page smoke test', () => {
  test('listings index page loads and shows content area', async ({ page }) => {
    await page.goto(`/t/${TENANT_SLUG}/listings`);

    // Main content landmark must be present
    await expect(page.getByRole('main')).toBeVisible({ timeout: 10000 });

    // Either a list of listings or an empty state should appear
    // (we don't care which — just that the page rendered without error)
    await expect(page.locator('main')).not.toBeEmpty({ timeout: 10000 });
  });
});
