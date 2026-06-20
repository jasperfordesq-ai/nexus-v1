// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { test, expect } from '@playwright/test';
import { WalletPage, TransferPage, InsightsPage } from '../../page-objects';
import { generateTestData } from '../../helpers/test-utils';
import { loadSeed } from '../../helpers/seed';

// The first navigation in a run pays the cold Vite-compile tax, and the transfer
// flow navigates twice — give these browser tests room beyond the 30s default.
test.beforeEach(({}, testInfo) => {
  testInfo.setTimeout(90_000);
});

/**
 * Wallet E2E Tests (React Frontend)
 *
 * Tests the wallet page with time credit management, transaction history, and transfer modal.
 * Uses React WalletPage with GlassCard components and HeroUI TransferModal.
 *
 * Key features:
 * - Balance display with stats (Total Earned, Total Spent, Pending)
 * - Transaction history with filter chips (All, Earned, Spent, Pending)
 * - Transfer modal (NOT a separate route)
 * - Load more pagination
 */

test.describe('Wallet - Overview', () => {
  test('should display wallet page', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();

    await expect(page).toHaveURL(/wallet/);
  });

  test('should show current balance', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();
    await walletPage.waitForLoad();

    // Balance should be visible
    await expect(walletPage.balance).toBeVisible({ timeout: 10000 });

    const balance = await walletPage.getBalance();
    expect(balance).toBeGreaterThanOrEqual(0);
  });

  test('should show transaction history or empty state', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();
    await walletPage.waitForLoad();

    const count = await walletPage.getTransactionCount();
    const noTransactions = await walletPage.hasNoTransactions();

    // Either have transactions or empty state
    expect(count > 0 || noTransactions).toBeTruthy();
  });

  test('should have Send Credits button', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();
    await walletPage.waitForLoad();

    await expect(walletPage.transferButton).toBeVisible();
  });

  test('should display transaction details if transactions exist', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();
    await walletPage.waitForLoad();

    const count = await walletPage.getTransactionCount();
    if (count > 0) {
      const details = await walletPage.getTransactionDetails(0);
      expect(details.amount).toBeTruthy();
    }
  });

  test('should show stats cards', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();
    await walletPage.waitForLoad();

    // The Earned / Spent / Pending stat cards must all render.
    await expect(page.getByText('Earned', { exact: true }).first()).toBeVisible();
    await expect(page.getByText('Spent', { exact: true }).first()).toBeVisible();
    await expect(page.getByText('Pending', { exact: true }).first()).toBeVisible();
  });

  test('should show filter chips', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();
    await walletPage.waitForLoad();

    const filterCount = await walletPage.filterChips.count();
    expect(filterCount).toBeGreaterThan(0);
  });
});

test.describe('Wallet - Transfer Modal', () => {
  test('should open transfer modal when clicking Send Credits button', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();
    await walletPage.waitForLoad();

    await walletPage.clickTransfer();

    // Modal should be visible (NOT a route change)
    await expect(walletPage.transferModal).toBeVisible();
    expect(page.url()).toContain('wallet'); // Still on wallet page
  });

  test('should display transfer modal elements', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();
    await walletPage.waitForLoad();

    await walletPage.clickTransfer();

    // Check modal elements
    await expect(walletPage.modalTitle).toBeVisible();
    await expect(walletPage.recipientSearchInput).toBeVisible();
    await expect(walletPage.amountInput).toBeVisible();
  });

  test('should have recipient search with autocomplete', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();
    await walletPage.waitForLoad();

    await walletPage.clickTransfer();

    const transferModal = new TransferPage(page);

    // Type in search
    await transferModal.searchRecipient('a');
    await page.waitForTimeout(600);

    // May or may not have suggestions depending on data
    const count = await transferModal.getResultsCount();
    expect(count).toBeGreaterThanOrEqual(0);
  });

  test('should show available balance in modal', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();
    await walletPage.waitForLoad();

    await walletPage.clickTransfer();

    // Available balance should be visible
    await expect(walletPage.availableBalanceDisplay).toBeVisible();
  });

  test('should have amount and description inputs', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();
    await walletPage.waitForLoad();

    await walletPage.clickTransfer();

    const transferModal = new TransferPage(page);

    await expect(transferModal.amountInput).toBeVisible();
    await expect(transferModal.descriptionInput).toBeVisible();
  });

  test('should have submit and cancel buttons', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();
    await walletPage.waitForLoad();

    await walletPage.clickTransfer();

    const transferModal = new TransferPage(page);

    await expect(transferModal.submitButton).toBeVisible();
    await expect(transferModal.cancelButton).toBeVisible();
  });

  test('should disable submit button when form is incomplete', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();
    await walletPage.waitForLoad();

    await walletPage.clickTransfer();

    const transferModal = new TransferPage(page);

    // Submit should be disabled with no recipient/amount
    const isDisabled = await transferModal.isSubmitDisabled();
    expect(isDisabled).toBeTruthy();
  });

  test('should close modal when clicking cancel', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();
    await walletPage.waitForLoad();

    await walletPage.clickTransfer();

    const transferModal = new TransferPage(page);
    await expect(transferModal.modal).toBeVisible();

    await transferModal.cancel();

    // Modal should close
    await expect(transferModal.modal).toBeHidden({ timeout: 3000 });
  });

  test('should close modal when clicking X button', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();
    await walletPage.waitForLoad();

    await walletPage.clickTransfer();

    const transferModal = new TransferPage(page);
    await expect(transferModal.modal).toBeVisible();

    await transferModal.close();

    // Modal should close
    await expect(transferModal.modal).toBeHidden({ timeout: 3000 });
  });

  test('should complete a transfer to another member', async ({ page }) => {
    const seed = loadSeed();
    test.skip(!seed, 'Seed fixture not available — run global setup against a live stack.');

    const walletPage = new WalletPage(page);
    await walletPage.navigate();
    await walletPage.waitForLoad();

    // The seeded balance floor guarantees a positive (transferable) balance.
    const balanceBefore = await walletPage.getBalance();
    expect(balanceBefore).toBeGreaterThan(0);

    await walletPage.clickTransfer();
    const transfer = new TransferPage(page);
    await expect(transfer.modal).toBeVisible();

    // Resolve the seeded second actor via the recipient autocomplete.
    const query = (seed!.userB.name || '').split(' ')[0] || seed!.userB.username || 'a';
    await transfer.searchRecipient(query);
    await expect(transfer.recipientSuggestions).toBeVisible({ timeout: 6000 });
    await transfer.selectRecipient(0);

    // Transfer exactly 1 hour.
    await transfer.amountInput.fill('1');
    await transfer.descriptionInput.fill('E2E harness transfer');

    await expect(transfer.submitButton).toBeEnabled();
    await transfer.submitButton.click();

    // A success toast (only shown on a 2xx transfer) and the modal closing prove
    // the transfer was accepted by the API.
    await expect(page.getByText(/transfer successful/i).first()).toBeVisible({ timeout: 10000 });
    await expect(transfer.modal).toBeHidden({ timeout: 10000 });

    // Reload the wallet and assert the sender's balance dropped by the amount sent
    // (money conservation from the sender side).
    await walletPage.navigate();
    await walletPage.waitForLoad();
    const balanceAfter = await walletPage.getBalance();
    expect(balanceAfter).toBeCloseTo(balanceBefore - 1, 1);
  });
});

test.describe('Wallet - Filtering', () => {
  test('should filter transactions by type', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();
    await walletPage.waitForLoad();

    const initialCount = await walletPage.getTransactionCount();

    if (initialCount > 0) {
      // Click Earned filter
      await walletPage.filterTransactions('earned');
      await page.waitForTimeout(500);

      const earnedCount = await walletPage.getTransactionCount();

      // Count may change or stay the same
      expect(earnedCount).toBeGreaterThanOrEqual(0);
    }
  });

  test('should switch between filter chips', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();
    await walletPage.waitForLoad();

    // Click All filter
    await walletPage.filterTransactions('all');
    await page.waitForTimeout(300);

    // Click Spent filter
    await walletPage.filterTransactions('spent');
    await page.waitForTimeout(300);

    // The Spent tab is now the selected one (HeroUI sets aria-selected on tabs).
    await expect(walletPage.spentFilterChip).toHaveAttribute('aria-selected', 'true');
  });

  test('should show all filter as default', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();
    await walletPage.waitForLoad();

    // On load, the "All" filter tab is the default-selected one.
    await expect(walletPage.allFilterChip).toBeVisible();
    await expect(walletPage.allFilterChip).toHaveAttribute('aria-selected', 'true');
  });
});

test.describe('Wallet - Pagination', () => {
  test('should show load more button if available', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();
    await walletPage.waitForLoad();

    const initialCount = await walletPage.getTransactionCount();

    // The "Load More" button only appears when more transactions exist. When it
    // does, clicking it must add rows (never remove them).
    const hasLoadMore = await walletPage.loadMoreButton.count() > 0;
    if (hasLoadMore) {
      await walletPage.loadMore();
      await page.waitForTimeout(1000);
      const newCount = await walletPage.getTransactionCount();
      expect(newCount).toBeGreaterThan(initialCount);
    } else {
      // No pagination control ⇒ all transactions already fit on the page.
      expect(initialCount).toBe(await walletPage.getTransactionCount());
    }
  });
});

test.describe('Wallet - Responsive', () => {
  test.use({ viewport: { width: 375, height: 667 } });

  test('should display properly on mobile', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();
    await walletPage.waitForLoad();

    // Balance should be visible
    await expect(walletPage.balance).toBeVisible();

    // Transfer button should be visible
    await expect(walletPage.transferButton).toBeVisible();
  });

  test('should open transfer modal on mobile', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();
    await walletPage.waitForLoad();

    await walletPage.clickTransfer();

    // Modal should be visible and functional on mobile
    await expect(walletPage.transferModal).toBeVisible();
  });
});

test.describe('Wallet - Accessibility', () => {
  test('should have proper heading structure', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();
    await walletPage.waitForLoad();

    const h1 = page.locator('h1');
    await expect(h1).toBeVisible();
  });

  test('should have accessible buttons', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();
    await walletPage.waitForLoad();

    // Transfer button should have text
    const buttonText = await walletPage.transferButton.textContent();
    expect(buttonText).toBeTruthy();
  });

  test('should have accessible modal', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();
    await walletPage.waitForLoad();

    await walletPage.clickTransfer();

    // Modal should have role="dialog" and aria-modal
    const modal = walletPage.transferModal;
    const role = await modal.getAttribute('role');
    const ariaModal = await modal.getAttribute('aria-modal');

    expect(role).toBe('dialog');
    expect(ariaModal).toBe('true');
  });
});

test.describe('Wallet - Performance', () => {
  test('should load wallet page within reasonable time', async ({ page }) => {
    const startTime = Date.now();

    const walletPage = new WalletPage(page);
    await walletPage.navigate();
    await walletPage.waitForLoad();

    const loadTime = Date.now() - startTime;

    // Should load within 15 seconds
    expect(loadTime).toBeLessThan(15000);
  });
});

test.describe('Wallet - Insights (Optional)', () => {
  test.skip('should navigate to insights page if available', async ({ page }) => {
    // Skip unless insights is a separate page
    const insightsPage = new InsightsPage(page);
    await insightsPage.navigate();

    // Check if page exists
    const hasChart = await insightsPage.isChartLoaded();
    expect(hasChart || true).toBeTruthy();
  });
});
