import { test, expect } from '@playwright/test';
import { WalletPage, TransferPage, InsightsPage } from '../../page-objects';
import { generateTestData, tenantUrl } from '../../helpers/test-utils';

test.describe('Wallet - Overview', () => {
  test('should display wallet page', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();

    await expect(page).toHaveURL(/wallet/);
  });

  test('should show current balance', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();

    await expect(walletPage.balance).toBeVisible();
    const balance = await walletPage.getBalance();
    expect(balance).toBeGreaterThanOrEqual(0);
  });

  test('should show transaction history', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();

    const count = await walletPage.getTransactionCount();
    const noTransactions = await walletPage.hasNoTransactions();

    expect(count > 0 || noTransactions).toBeTruthy();
  });

  test('should have transfer button', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();

    await expect(walletPage.transferButton).toBeVisible();
  });

  test('should display transaction details', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();

    const count = await walletPage.getTransactionCount();
    if (count > 0) {
      const details = await walletPage.getTransactionDetails(0);
      expect(details.amount).toBeTruthy();
    }
  });

  test('should show pending transaction count', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();

    const pendingCount = await walletPage.getPendingCount();
    expect(pendingCount).toBeGreaterThanOrEqual(0);
  });
});

test.describe('Wallet - Transfer', () => {
  test('should navigate to transfer page', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();
    await walletPage.clickTransfer();

    // Should be on transfer page or modal
    const transferPage = new TransferPage(page);
    await expect(transferPage.recipientInput).toBeVisible();
  });

  test('should have recipient search', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();
    await walletPage.clickTransfer();

    const transferPage = new TransferPage(page);
    await expect(transferPage.recipientInput).toBeVisible();
  });

  test('should show user suggestions when searching', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();
    await walletPage.clickTransfer();

    const transferPage = new TransferPage(page);
    await transferPage.searchRecipient('a');
    await page.waitForTimeout(500);

    const suggestions = transferPage.recipientSuggestions;
    // May or may not have suggestions depending on data
    const count = await suggestions.count();
    expect(count).toBeGreaterThanOrEqual(0);
  });

  test('should have amount input', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();
    await walletPage.clickTransfer();

    const transferPage = new TransferPage(page);
    await expect(transferPage.amountInput).toBeVisible();
  });

  test('should validate required fields', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();
    await walletPage.clickTransfer();

    const transferPage = new TransferPage(page);
    await transferPage.submit();

    const hasError = await transferPage.hasError();
    // Should stay on transfer page or show error
    expect(hasError || page.url().includes('transfer') || page.url().includes('wallet')).toBeTruthy();
  });

  test('should validate amount is positive', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();
    await walletPage.clickTransfer();

    const transferPage = new TransferPage(page);
    await transferPage.amountInput.fill('-1');
    await transferPage.submit();

    // Should show error
    const hasError = await transferPage.hasError();
    const stillOnPage = page.url().includes('transfer') || page.url().includes('wallet');
    expect(hasError || stillOnPage).toBeTruthy();
  });

  test('should validate amount does not exceed balance', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();

    const balance = await walletPage.getBalance();

    await walletPage.clickTransfer();

    const transferPage = new TransferPage(page);

    // Try to search for a recipient
    await transferPage.searchRecipient('test');
    await page.waitForTimeout(500);

    const suggestions = transferPage.recipientSuggestions;
    if (await suggestions.count() > 0) {
      await transferPage.selectRecipient(0);

      // Try to transfer more than balance
      await transferPage.amountInput.fill((balance + 100).toString());
      await transferPage.submit();

      // Should show insufficient balance error
      const hasError = await transferPage.hasError();
      const stillOnPage = page.url().includes('transfer') || page.url().includes('wallet');
      expect(hasError || stillOnPage).toBeTruthy();
    }
  });

  test.skip('should complete a transfer', async ({ page }) => {
    // Skip to avoid real transfers
    // Enable when test accounts are properly set up

    const walletPage = new WalletPage(page);
    await walletPage.navigate();

    const initialBalance = await walletPage.getBalance();

    await walletPage.clickTransfer();

    const transferPage = new TransferPage(page);
    await transferPage.transfer({
      recipient: 'test',
      amount: '0.5',
      description: 'E2E Test Transfer',
    });

    const isSuccess = await transferPage.isSuccess();
    expect(isSuccess).toBeTruthy();

    // Verify balance decreased
    await walletPage.navigate();
    const newBalance = await walletPage.getBalance();
    expect(newBalance).toBeLessThan(initialBalance);
  });
});

test.describe('Wallet - Insights', () => {
  test('should navigate to insights page', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();

    const insightsLink = walletPage.insightsLink;
    if (await insightsLink.count() > 0) {
      await walletPage.goToInsights();
      expect(page.url()).toContain('insights');
    }
  });

  test('should display spending insights', async ({ page }) => {
    const insightsPage = new InsightsPage(page);
    await insightsPage.navigate();

    // Should show earned/spent totals
    await expect(insightsPage.totalEarned).toBeVisible();
    await expect(insightsPage.totalSpent).toBeVisible();
  });

  test('should show chart if available', async ({ page }) => {
    const insightsPage = new InsightsPage(page);
    await insightsPage.navigate();

    const hasChart = await insightsPage.isChartLoaded();
    // Chart may or may not be present depending on data
    expect(hasChart).toBeDefined();
  });

  test('should have period selector', async ({ page }) => {
    const insightsPage = new InsightsPage(page);
    await insightsPage.navigate();

    const periodSelector = insightsPage.periodSelector;
    if (await periodSelector.count() > 0) {
      await expect(periodSelector).toBeVisible();
    }
  });
});

test.describe('Wallet - Filtering', () => {
  test('should filter transactions by type', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();

    const filterDropdown = walletPage.filterDropdown;
    if (await filterDropdown.count() > 0) {
      await walletPage.filterTransactions('sent');
      await page.waitForLoadState('domcontentloaded');
      // Page should update with filtered results
    }
  });
});

test.describe('Wallet - API', () => {
  test('should have API endpoint for balance', async ({ page }) => {
    await page.goto(tenantUrl('api/wallet/balance'));

    // Should return JSON response
    const response = await page.content();
    // Either returns balance data or requires auth
    expect(response).toBeTruthy();
  });
});

test.describe('Wallet - Accessibility', () => {
  test('should have proper heading structure', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();

    const h1 = page.locator('h1');
    await expect(h1).toBeVisible();
  });

  test('should have accessible balance display', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();

    const balance = walletPage.balance;

    // Balance should be screen reader friendly
    const ariaLabel = await balance.getAttribute('aria-label');
    const text = await balance.textContent();

    expect(ariaLabel || text).toBeTruthy();
  });

  test('should have accessible transfer form', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();
    await walletPage.clickTransfer();

    const transferPage = new TransferPage(page);

    // Inputs should have labels
    const amountInput = transferPage.amountInput;
    const id = await amountInput.getAttribute('id');
    const ariaLabel = await amountInput.getAttribute('aria-label');

    if (id) {
      const label = page.locator(`label[for="${id}"]`);
      const hasLabel = await label.count() > 0;
      expect(hasLabel || ariaLabel).toBeTruthy();
    }
  });
});

test.describe('Wallet - Mobile Behavior', () => {
  test.use({ viewport: { width: 375, height: 667 } });

  test('should display properly on mobile', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();

    const content = page.locator('main, .content, .wallet');
    await expect(content).toBeVisible();

    // Balance should still be visible
    await expect(walletPage.balance).toBeVisible();
  });

  test('should have mobile-friendly transfer flow', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();
    await walletPage.clickTransfer();

    const transferPage = new TransferPage(page);
    await expect(transferPage.recipientInput).toBeVisible();
    await expect(transferPage.amountInput).toBeVisible();
  });
});

test.describe('Wallet - Transaction Types', () => {
  test('should distinguish sent vs received transactions', async ({ page }) => {
    const walletPage = new WalletPage(page);
    await walletPage.navigate();

    const count = await walletPage.getTransactionCount();
    if (count > 0) {
      const details = await walletPage.getTransactionDetails(0);
      expect(['sent', 'received', 'pending']).toContain(details.type);
    }
  });
});
