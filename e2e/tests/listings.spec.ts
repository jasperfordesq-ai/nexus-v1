// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { test, expect } from '@playwright/test';
import { loginAsUser } from '../helpers/auth';
import { testUsers, testTenant, generateTestData, selectors, waitForToast } from '../helpers/fixtures';

test.describe('Listings Marketplace', () => {
  test.beforeEach(async ({ page }) => {
    // Login before each test
    await loginAsUser(page, testUsers.primary.email, testUsers.primary.password, testTenant.slug);
  });

  test('should display listings page @smoke @critical', async ({ page }) => {
    await page.goto(`/${testTenant.slug}/listings`);

    // Verify listings page elements
    await expect(page.locator('h1, h2').filter({ hasText: /listings|marketplace/i })).toBeVisible();

    // Search box should be visible
    await expect(page.locator('input[type="search"], input[placeholder*="Search"]')).toBeVisible();
  });

  test('should create new listing @critical', async ({ page }) => {
    const listing = generateTestData().listing;

    // Navigate to create listing page
    await page.goto(`/${testTenant.slug}/listings/new`);

    // Wait for form to load
    await page.waitForSelector('form', { state: 'visible' });

    // Fill in listing details
    await page.fill('input[name="title"], input[placeholder*="title"]', listing.title);
    await page.fill('textarea[name="description"], textarea[placeholder*="description"]', listing.description);

    // Select category if dropdown exists
    const categorySelect = page.locator('select[name="category"], select[name="category_id"]');
    if (await categorySelect.isVisible()) {
      await categorySelect.selectOption({ label: listing.category });
    }

    // Select type (offer/request)
    const typeRadio = page.locator(`input[type="radio"][value="${listing.type}"]`);
    if (await typeRadio.isVisible()) {
      await typeRadio.check();
    }

    // Fill duration if field exists
    const durationInput = page.locator('input[name="duration"], input[placeholder*="duration"]');
    if (await durationInput.isVisible()) {
      await durationInput.fill(listing.duration.toString());
    }

    // Submit form
    await page.click(selectors.submitButton);

    // Wait for redirect or success message
    await page.waitForURL(/\/listings\/\d+/, { timeout: 10000 });

    // Verify listing was created
    await expect(page.locator(`text=${listing.title}`)).toBeVisible();
  });

  test('should search for listings @critical', async ({ page }) => {
    await page.goto(`/${testTenant.slug}/listings`);

    // Find search input
    const searchInput = page.locator('input[type="search"], input[placeholder*="Search"]');
    await searchInput.fill('test');

    // Wait for search results to update
    await page.waitForTimeout(1000);

    // Verify search was performed (URL or results updated)
    // This might depend on whether search is client-side or server-side
  });

  test('should view listing detail @smoke @critical', async ({ page }) => {
    // Go to listings page
    await page.goto(`/${testTenant.slug}/listings`);

    // Wait for listings to load
    await page.waitForSelector(selectors.listingCard, { state: 'visible', timeout: 10000 });

    // Click first listing
    const firstListing = page.locator(selectors.listingCard).first();
    await firstListing.click();

    // Wait for detail page
    await page.waitForURL(/\/listings\/\d+/, { timeout: 5000 });

    // Verify detail page elements
    await expect(page.locator('h1, h2')).toBeVisible();
    await expect(page.locator('text=/description|details/i')).toBeVisible();
  });

  test('should edit own listing @critical', async ({ page }) => {
    // Create a listing first
    const listing = generateTestData().listing;
    await page.goto(`/${testTenant.slug}/listings/new`);
    await page.fill('input[name="title"]', listing.title);
    await page.fill('textarea[name="description"]', listing.description);
    await page.click(selectors.submitButton);
    await page.waitForURL(/\/listings\/\d+/);

    // Get listing ID from URL
    const url = page.url();
    const listingId = url.match(/\/listings\/(\d+)/)?.[1];

    // Navigate to edit page
    await page.goto(`/${testTenant.slug}/listings/${listingId}/edit`);

    // Update title
    const updatedTitle = `${listing.title} - Updated`;
    await page.fill('input[name="title"]', updatedTitle);

    // Save changes
    await page.click(selectors.saveButton);

    // Verify update
    await expect(page.locator(`text=${updatedTitle}`)).toBeVisible({ timeout: 5000 });
  });

  test('should delete own listing @critical', async ({ page }) => {
    // Create a listing first
    const listing = generateTestData().listing;
    await page.goto(`/${testTenant.slug}/listings/new`);
    await page.fill('input[name="title"]', listing.title);
    await page.fill('textarea[name="description"]', listing.description);
    await page.click(selectors.submitButton);
    await page.waitForURL(/\/listings\/\d+/);

    // Click delete button
    await page.click(selectors.deleteButton);

    // Confirm deletion in modal
    const confirmButton = page.locator(selectors.confirmButton);
    await confirmButton.click();

    // Verify redirect to listings page
    await page.waitForURL(/\/listings\/?$/, { timeout: 5000 });
  });

  test('should filter listings by category @regression', async ({ page }) => {
    await page.goto(`/${testTenant.slug}/listings`);

    // Look for category filter
    const categoryFilter = page.locator('select[name="category"], button:has-text("Category")');

    if (await categoryFilter.isVisible()) {
      await categoryFilter.click();

      // Select a category option
      await page.locator('[role="option"], option').first().click();

      // Wait for filtered results
      await page.waitForTimeout(1000);

      // Verify listings are displayed
      await expect(page.locator(selectors.listingCard)).toBeVisible();
    }
  });

  test('should show validation errors for incomplete listing @regression', async ({ page }) => {
    await page.goto(`/${testTenant.slug}/listings/new`);

    // Try to submit without required fields
    await page.click(selectors.submitButton);

    // Check for validation messages
    const titleInput = page.locator('input[name="title"]');
    const isInvalid = await titleInput.evaluate((el: HTMLInputElement) => !el.validity.valid);

    expect(isInvalid).toBeTruthy();
  });
});
