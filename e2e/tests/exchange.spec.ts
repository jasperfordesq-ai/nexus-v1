// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { test, expect } from '@playwright/test';
import { loginAsUser, logout } from '../helpers/auth';
import { testUsers, testTenant, selectors } from '../helpers/fixtures';

/**
 * Exchange Workflow E2E.
 *
 * HONESTY NOTE (see PRODUCTION-READINESS.md §3, journey J1):
 * The money-mutating steps below (request / accept / complete / review / decline)
 * were previously written as `if (await button.isVisible()) { ... }` with no
 * `else` branch. On an empty or unseeded database those branches never execute,
 * so the tests passed having asserted NOTHING — "green theatre". A broken
 * credit-minting path was invisible to CI.
 *
 * Those steps are now marked `test.fixme` so a green run no longer implies the
 * money path works. They require a deterministic, seeded TWO-actor harness
 * (a provider AND a requester, with known balances) that does not yet exist at
 * the browser layer — promote them back to `test(...)` once it does, and assert
 * real outcomes (status transitions + wallet balance deltas), not just toasts.
 *
 * Until then, the exchange money path IS guarded by a real, deterministic,
 * blocking-gate test at the service layer:
 *   tests/Laravel/Integration/ExchangeWorkflowTest.php
 *     ::test_completing_an_exchange_credits_exact_hours_and_conserves_money
 * which asserts the exact credit math, money conservation, and the single
 * transaction row. That is the right layer for a money-conservation assertion.
 */
test.describe('Exchange Workflow', () => {
  test.beforeEach(async ({ page }) => {
    // Login as primary user
    await loginAsUser(page, testUsers.primary.email, testUsers.primary.password, testTenant.slug);
  });

  test('should browse listings as logged-in user @smoke @critical', async ({ page }) => {
    await page.goto(`/${testTenant.slug}/listings`);

    // Verify listings are visible
    await expect(page.locator(selectors.listingCard)).toBeVisible({ timeout: 10000 });

    // Verify we can see listing details
    const listingCount = await page.locator(selectors.listingCard).count();
    expect(listingCount).toBeGreaterThan(0);
  });

  // FIXME(J1): asserts nothing real — the request control is only clicked "if visible".
  // Needs a seeded listing owned by a *different* user the primary user can request.
  // Money path is covered by ExchangeWorkflowTest::test_completing_an_exchange_credits_exact_hours_and_conserves_money.
  test.fixme('should request exchange on a listing @critical', async ({ page }) => {
    // Navigate to listings
    await page.goto(`/${testTenant.slug}/listings`);

    // Wait for listings to load
    await page.waitForSelector(selectors.listingCard, { state: 'visible', timeout: 10000 });

    // Click on first listing
    await page.locator(selectors.listingCard).first().click();

    // Wait for detail page
    await page.waitForURL(/\/listings\/\d+/);

    // Look for "Request Exchange" or similar button
    const requestButton = page.locator('button:has-text("Request"), button:has-text("Exchange"), a:has-text("Request")');

    if (await requestButton.isVisible()) {
      await requestButton.click();

      // Fill in exchange request form if modal appears
      const messageField = page.locator('textarea[name="message"], textarea[placeholder*="message"]');
      if (await messageField.isVisible()) {
        await messageField.fill('I would like to request this exchange for E2E testing purposes.');
      }

      // Submit request
      await page.click(selectors.submitButton);

      // Wait for success message
      await expect(page.locator(selectors.toast)).toBeVisible({ timeout: 5000 });
    }
  });

  test('should view pending exchanges @critical', async ({ page }) => {
    // Navigate to exchanges page
    await page.goto(`/${testTenant.slug}/exchanges`);

    // Wait for page to load
    await expect(page.locator('h1, h2').filter({ hasText: /exchange/i })).toBeVisible();

    // List should be present (may be empty) — asserts the page rendered a real
    // pending/active/empty state rather than an error page.
    await expect(page.locator('text=/pending|active|no exchanges/i')).toBeVisible({ timeout: 5000 });
  });

  // FIXME(J1): "Accept" is only clicked if it happens to be visible — passes on empty data.
  // Needs a seeded pending exchange where the logged-in user is the provider.
  test.fixme('should accept exchange as listing owner @critical', async ({ page }) => {
    // This test requires a pending exchange to exist
    // Navigate to exchanges page
    await page.goto(`/${testTenant.slug}/exchanges`);

    // Look for "Accept" button on a pending exchange
    const acceptButton = page.locator('button:has-text("Accept")').first();

    if (await acceptButton.isVisible({ timeout: 5000 })) {
      await acceptButton.click();

      // Confirm in modal if needed
      const confirmButton = page.locator(selectors.confirmButton);
      if (await confirmButton.isVisible({ timeout: 2000 })) {
        await confirmButton.click();
      }

      // Wait for success toast
      await expect(page.locator(selectors.toast)).toBeVisible({ timeout: 5000 });
    }
  });

  // FIXME(J1): the critical money step — but it only checks a toast, never that a
  // wallet balance changed. Needs a seeded in-progress exchange + balance assertions.
  // Covered deterministically by ExchangeWorkflowTest (service layer).
  test.fixme('should mark exchange as complete @critical', async ({ page }) => {
    // Navigate to exchanges page
    await page.goto(`/${testTenant.slug}/exchanges`);

    // Look for "Complete" or "Mark Complete" button
    const completeButton = page.locator('button:has-text("Complete"), button:has-text("Finish")').first();

    if (await completeButton.isVisible({ timeout: 5000 })) {
      await completeButton.click();

      // Confirm completion
      const confirmButton = page.locator(selectors.confirmButton);
      if (await confirmButton.isVisible({ timeout: 2000 })) {
        await confirmButton.click();
      }

      // Wait for success message
      await expect(page.locator(selectors.toast)).toBeVisible({ timeout: 5000 });
    }
  });

  // FIXME(J1): review controls only exercised "if visible" — passes with zero completed exchanges.
  test.fixme('should leave review after exchange @critical', async ({ page }) => {
    // Navigate to a completed exchange (this requires setup)
    await page.goto(`/${testTenant.slug}/exchanges`);

    // Look for "Leave Review" button
    const reviewButton = page.locator('button:has-text("Review"), a:has-text("Review")').first();

    if (await reviewButton.isVisible({ timeout: 5000 })) {
      await reviewButton.click();

      // Wait for review form
      await page.waitForSelector('form, [role="dialog"]', { state: 'visible' });

      // Select rating (assuming star rating)
      const fiveStars = page.locator('[data-rating="5"], button[aria-label*="5 star"]');
      if (await fiveStars.isVisible()) {
        await fiveStars.click();
      }

      // Add review comment
      const commentField = page.locator('textarea[name="comment"], textarea[name="review"]');
      if (await commentField.isVisible()) {
        await commentField.fill('Excellent exchange! Great communication and service.');
      }

      // Submit review
      await page.click(selectors.submitButton);

      // Wait for success
      await expect(page.locator(selectors.toast)).toBeVisible({ timeout: 5000 });
    }
  });

  // FIXME(J1): decline only exercised "if visible" — passes with no pending exchange.
  test.fixme('should decline exchange request @regression', async ({ page }) => {
    // Navigate to exchanges page
    await page.goto(`/${testTenant.slug}/exchanges`);

    // Look for "Decline" or "Reject" button
    const declineButton = page.locator('button:has-text("Decline"), button:has-text("Reject")').first();

    if (await declineButton.isVisible({ timeout: 5000 })) {
      await declineButton.click();

      // Confirm decline
      const confirmButton = page.locator(selectors.confirmButton);
      if (await confirmButton.isVisible({ timeout: 2000 })) {
        await confirmButton.click();
      }

      // Wait for success message
      await expect(page.locator(selectors.toast)).toBeVisible({ timeout: 5000 });
    }
  });

  // FIXME(J1): history tab only clicked "if visible" — passes whether or not history renders.
  test.fixme('should view exchange history @regression', async ({ page }) => {
    await page.goto(`/${testTenant.slug}/exchanges`);

    // Look for "History" or "Completed" tab/filter
    const historyTab = page.locator('button:has-text("History"), button:has-text("Completed"), a:has-text("History")');

    if (await historyTab.isVisible()) {
      await historyTab.click();

      // Wait for content to update
      await page.waitForTimeout(1000);

      // Verify we're viewing history section
      await expect(page.locator('text=/completed|history/i')).toBeVisible();
    }
  });
});
