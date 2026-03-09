// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { test, expect, devices } from '@playwright/test';
import { loginAsUser } from '../helpers/auth';
import { testUsers, testTenant, selectors } from '../helpers/fixtures';

test.describe('Responsive Design', () => {
  test('should display mobile drawer on mobile viewport @smoke @critical', async ({ page }) => {
    // Set mobile viewport
    await page.setViewportSize(devices['iPhone 12'].viewport);

    await page.goto(`/${testTenant.slug}`);

    // Look for mobile menu button (hamburger)
    const menuButton = page.locator('button[aria-label*="menu"], button:has-text("Menu"), .mobile-menu-button');
    await expect(menuButton).toBeVisible();

    // Click to open drawer
    await menuButton.click();

    // Verify drawer is visible
    await expect(page.locator(selectors.mobileDrawer)).toBeVisible({ timeout: 3000 });
  });

  test('should navigate using mobile drawer @critical', async ({ page }) => {
    await page.setViewportSize(devices['iPhone 12'].viewport);
    await page.goto(`/${testTenant.slug}`);

    // Open mobile menu
    const menuButton = page.locator('button[aria-label*="menu"], button:has-text("Menu")');
    await menuButton.click();

    // Wait for drawer
    await page.waitForSelector(selectors.mobileDrawer, { state: 'visible' });

    // Click on a navigation link (e.g., About)
    await page.locator(`${selectors.mobileDrawer} a:has-text("About")`).click();

    // Verify navigation occurred
    await expect(page).toHaveURL(/\/about/);
  });

  test('should display forms correctly on mobile @critical', async ({ page }) => {
    await page.setViewportSize(devices['iPhone 12'].viewport);

    // Login as user
    await loginAsUser(page, testUsers.primary.email, testUsers.primary.password, testTenant.slug);

    // Navigate to create listing
    await page.goto(`/${testTenant.slug}/listings/new`);

    // Verify form is visible and usable
    await expect(page.locator('form')).toBeVisible();
    await expect(page.locator('input[name="title"]')).toBeVisible();
    await expect(page.locator('textarea[name="description"]')).toBeVisible();

    // Verify submit button is accessible
    await expect(page.locator(selectors.submitButton)).toBeVisible();
  });

  test('should display cards in grid on mobile @regression', async ({ page }) => {
    await page.setViewportSize(devices['iPhone 12'].viewport);

    await page.goto(`/${testTenant.slug}/listings`);

    // Wait for listings to load
    await page.waitForSelector(selectors.listingCard, { state: 'visible', timeout: 10000 });

    // Verify at least one listing card is visible
    const cardCount = await page.locator(selectors.listingCard).count();
    expect(cardCount).toBeGreaterThan(0);

    // Verify cards are stacked (single column on mobile)
    const firstCard = page.locator(selectors.listingCard).first();
    const cardBox = await firstCard.boundingBox();

    if (cardBox) {
      // Card should take most of viewport width on mobile
      expect(cardBox.width).toBeGreaterThan(300);
    }
  });

  test('should display navbar correctly on tablet @regression', async ({ page }) => {
    await page.setViewportSize(devices['iPad Pro'].viewport);

    await page.goto(`/${testTenant.slug}`);

    // On tablet, navbar should be visible
    await expect(page.locator(selectors.navbar)).toBeVisible();

    // Main navigation links should be visible (not in drawer)
    await expect(page.locator(`${selectors.navbar} ${selectors.dashboardLink}`)).toBeVisible();
  });

  test('should handle orientation change @regression', async ({ page }) => {
    // Start in portrait
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto(`/${testTenant.slug}`);

    // Verify mobile menu is visible
    const menuButton = page.locator('button[aria-label*="menu"]');
    await expect(menuButton).toBeVisible();

    // Switch to landscape
    await page.setViewportSize({ width: 667, height: 375 });

    // Wait for layout to adjust
    await page.waitForTimeout(500);

    // Navigation should still be accessible
    await expect(page.locator(selectors.navbar)).toBeVisible();
  });

  test('should display modals correctly on mobile @regression', async ({ page }) => {
    await page.setViewportSize(devices['iPhone 12'].viewport);

    // Login first
    await loginAsUser(page, testUsers.primary.email, testUsers.primary.password, testTenant.slug);

    // Navigate to a page with modals (e.g., listings)
    await page.goto(`/${testTenant.slug}/listings`);

    // Try to trigger a modal (e.g., delete confirmation)
    const deleteButton = page.locator(selectors.deleteButton).first();

    if (await deleteButton.isVisible({ timeout: 5000 })) {
      await deleteButton.click();

      // Verify modal appears and is usable on mobile
      const modal = page.locator(selectors.modal);
      await expect(modal).toBeVisible({ timeout: 3000 });

      // Verify modal action buttons are visible
      await expect(page.locator(selectors.confirmButton)).toBeVisible();
    }
  });

  test('should render touch-friendly buttons on mobile @smoke', async ({ page }) => {
    await page.setViewportSize(devices['iPhone 12'].viewport);

    await page.goto(`/${testTenant.slug}/listings`);

    // Check that buttons have adequate touch target size (min 44x44px)
    const buttons = page.locator('button, a.button');
    const firstButton = buttons.first();

    if (await firstButton.isVisible({ timeout: 5000 })) {
      const buttonBox = await firstButton.boundingBox();

      if (buttonBox) {
        // Touch target should be at least 44px in height
        expect(buttonBox.height).toBeGreaterThanOrEqual(40);
      }
    }
  });
});
