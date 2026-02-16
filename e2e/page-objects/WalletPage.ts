import { Page, Locator, expect } from '@playwright/test';
import { BasePage } from './BasePage';

/**
 * Wallet Page Object (React with GlassCard and TransferModal)
 *
 * The React wallet page uses:
 * - GlassCard for balance display and stats
 * - Transaction list with filter chips (All, Earned, Spent, Pending)
 * - TransferModal (modal overlay, NOT a separate route)
 * - Load More button for pagination
 */
export class WalletPage extends BasePage {
  // Balance section
  readonly balanceCard: Locator;
  readonly balance: Locator;
  readonly balanceLabel: Locator;

  // Stats
  readonly totalEarnedStat: Locator;
  readonly totalSpentStat: Locator;
  readonly pendingTransactionsStat: Locator;

  // Actions
  readonly transferButton: Locator;

  // Transaction list
  readonly transactionsList: Locator;
  readonly transactionItems: Locator;
  readonly noTransactionsMessage: Locator;

  // Filter chips
  readonly filterChips: Locator;
  readonly allFilterChip: Locator;
  readonly earnedFilterChip: Locator;
  readonly spentFilterChip: Locator;
  readonly pendingFilterChip: Locator;

  // Pagination
  readonly loadMoreButton: Locator;

  // Transfer Modal elements
  readonly transferModal: Locator;
  readonly modalTitle: Locator;
  readonly recipientSearchInput: Locator;
  readonly recipientResults: Locator;
  readonly selectedRecipient: Locator;
  readonly amountInput: Locator;
  readonly descriptionInput: Locator;
  readonly modalSubmitButton: Locator;
  readonly modalCancelButton: Locator;
  readonly modalCloseButton: Locator;
  readonly availableBalanceDisplay: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    // Balance section - GlassCard with balance display
    this.balanceCard = page.locator('[class*="glass"]').filter({ hasText: 'Available Balance' }).or(
      page.locator('[class*="glass"]').filter({ hasText: 'Current Balance' })
    );
    this.balance = page.locator('text=/\\d+(\\.\\d+)?\\s*hours?/').first();
    this.balanceLabel = page.locator('text=Available Balance, text=Current Balance').first();

    // Stats cards
    this.totalEarnedStat = page.locator('text=Total Earned').locator('..').locator('p, span').filter({ hasText: /\d+/ });
    this.totalSpentStat = page.locator('text=Total Spent').locator('..').locator('p, span').filter({ hasText: /\d+/ });
    this.pendingTransactionsStat = page.locator('text=Pending').locator('..').locator('p, span').filter({ hasText: /\d+/ });

    // Transfer button (gradient button)
    this.transferButton = page.locator('button:has-text("Send Credits"), button:has-text("Transfer")').first();

    // Transaction list
    this.transactionsList = page.locator('[class*="glass"]').filter({ hasText: 'Transaction History' }).or(
      page.locator('[class*="glass"]').filter({ hasText: 'Recent Transactions' })
    );
    this.transactionItems = page.locator('[class*="glass"]').filter({ hasText: 'Transaction History' })
      .locator('> div').filter({ has: page.locator('text=/\\d+(\\.\\d+)?\\s*hours?/') });
    this.noTransactionsMessage = page.locator('text=No transactions yet, text=No transactions found');

    // Filter chips (All, Earned, Spent, Pending)
    this.filterChips = page.locator('button:has-text("All"), button:has-text("Earned"), button:has-text("Spent"), button:has-text("Pending")');
    this.allFilterChip = page.locator('button:has-text("All")').first();
    this.earnedFilterChip = page.locator('button:has-text("Earned")').first();
    this.spentFilterChip = page.locator('button:has-text("Spent")').first();
    this.pendingFilterChip = page.locator('button:has-text("Pending")').first();

    // Load more button
    this.loadMoreButton = page.locator('button:has-text("Load More")');

    // Transfer Modal (overlay, NOT a route)
    this.transferModal = page.locator('[role="dialog"][aria-modal="true"]:has-text("Send Credits")');
    this.modalTitle = this.transferModal.locator('#transfer-modal-title, h2:has-text("Send Credits")');
    this.recipientSearchInput = this.transferModal.locator('input[placeholder*="Search"]');
    this.recipientResults = page.locator('[role="listbox"][aria-label="Search results"]');
    this.selectedRecipient = this.transferModal.locator('.bg-theme-elevated:has(img[alt])').first();
    this.amountInput = this.transferModal.locator('input[type="number"], input[name="amount"]');
    this.descriptionInput = this.transferModal.locator('textarea[placeholder*="description" i], textarea[name="description"]');
    this.modalSubmitButton = this.transferModal.locator('button[type="submit"]:has-text("Send")');
    this.modalCancelButton = this.transferModal.locator('button:has-text("Cancel")');
    this.modalCloseButton = this.transferModal.locator('button[aria-label="Close modal"]');
    this.availableBalanceDisplay = this.transferModal.locator('text=Available Balance').locator('..').locator('span').filter({ hasText: /\d+/ });
  }

  /**
   * Navigate to wallet page
   */
  async navigate(): Promise<void> {
    await this.goto('wallet');
  }

  /**
   * Wait for wallet page to load
   */
  async waitForLoad(): Promise<void> {
    await this.page.waitForLoadState('domcontentloaded');
    // Wait for balance or empty state to appear
    await this.page.locator('[class*="glass"], text=No transactions yet').first().waitFor({
      state: 'visible',
      timeout: 15000
    }).catch(() => {});
  }

  /**
   * Get current balance as number
   */
  async getBalance(): Promise<number> {
    const balanceText = await this.balance.textContent() || '0';
    // Extract numeric value (handles formats like "5.5 hours", "5:30", "5.5")
    const match = balanceText.match(/[\d.]+/);
    return match ? parseFloat(match[0]) : 0;
  }

  /**
   * Get balance as formatted string
   */
  async getBalanceText(): Promise<string> {
    return (await this.balance.textContent())?.trim() || '';
  }

  /**
   * Get number of visible transactions
   */
  async getTransactionCount(): Promise<number> {
    // Count transaction items within the transaction list
    const items = this.page.locator('[class*="glass"]').filter({ hasText: 'Transaction History' })
      .locator('> div > div').filter({ has: this.page.locator('text=/\\d+(\\.\\d+)?\\s*hours?/') });
    return await items.count();
  }

  /**
   * Check if there are no transactions
   */
  async hasNoTransactions(): Promise<boolean> {
    return await this.noTransactionsMessage.isVisible();
  }

  /**
   * Get pending transaction count from stat card
   */
  async getPendingCount(): Promise<number> {
    if (await this.pendingTransactionsStat.count() > 0) {
      const text = await this.pendingTransactionsStat.textContent() || '0';
      return parseInt(text.replace(/\D/g, ''), 10);
    }
    return 0;
  }

  /**
   * Open transfer modal by clicking Send Credits button
   */
  async clickTransfer(): Promise<void> {
    await this.transferButton.click();
    // Wait for modal to appear
    await this.transferModal.waitFor({ state: 'visible', timeout: 3000 });
  }

  /**
   * Filter transactions by type
   */
  async filterTransactions(filter: 'all' | 'earned' | 'spent' | 'pending'): Promise<void> {
    const chipMap = {
      all: this.allFilterChip,
      earned: this.earnedFilterChip,
      spent: this.spentFilterChip,
      pending: this.pendingFilterChip,
    };

    await chipMap[filter].click();
    await this.page.waitForTimeout(500);
  }

  /**
   * Load more transactions
   */
  async loadMore(): Promise<void> {
    await this.loadMoreButton.click();
    await this.page.waitForTimeout(1000);
  }

  /**
   * Get transaction details by index
   */
  async getTransactionDetails(index: number = 0): Promise<{
    amount: string;
    description: string;
    date: string;
    type: 'sent' | 'received' | 'pending';
  }> {
    const items = this.page.locator('[class*="glass"]').filter({ hasText: 'Transaction History' })
      .locator('> div > div').filter({ has: this.page.locator('text=/\\d+(\\.\\d+)?\\s*hours?/') });

    const transaction = items.nth(index);

    const amount = await transaction.locator('text=/[+-]?\\d+(\\.\\d+)?\\s*hours?/').first().textContent() || '';
    const description = await transaction.locator('p, span').filter({ hasText: /\w+/ }).first().textContent() || '';
    const date = await transaction.locator('time, .text-xs').first().textContent() || '';

    let type: 'sent' | 'received' | 'pending' = 'received';
    const amountText = amount.toLowerCase();
    if (amountText.includes('-') || amountText.includes('sent')) {
      type = 'sent';
    } else if (amountText.includes('pending')) {
      type = 'pending';
    }

    return { amount: amount.trim(), description: description.trim(), date: date.trim(), type };
  }
}

/**
 * Transfer Modal Helper (NOT a separate page/route)
 *
 * This represents the transfer modal that appears as an overlay on the wallet page.
 * Use WalletPage.clickTransfer() to open it.
 */
export class TransferPage extends BasePage {
  readonly modal: Locator;
  readonly recipientInput: Locator;
  readonly recipientSuggestions: Locator;
  readonly selectedRecipientDisplay: Locator;
  readonly removeRecipientButton: Locator;
  readonly amountInput: Locator;
  readonly descriptionInput: Locator;
  readonly availableBalance: Locator;
  readonly submitButton: Locator;
  readonly cancelButton: Locator;
  readonly closeButton: Locator;
  readonly errorMessage: Locator;
  readonly successMessage: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    this.modal = page.locator('[role="dialog"][aria-modal="true"]:has-text("Send Credits")');
    this.recipientInput = this.modal.locator('input[placeholder*="Search"]');
    this.recipientSuggestions = page.locator('[role="listbox"][aria-label="Search results"]');
    this.selectedRecipientDisplay = this.modal.locator('.bg-theme-elevated:has(img[alt])').first();
    this.removeRecipientButton = this.selectedRecipientDisplay.locator('button[aria-label*="Remove"]');
    this.amountInput = this.modal.locator('input[type="number"], input[name="amount"]');
    this.descriptionInput = this.modal.locator('textarea[placeholder*="description" i]');
    this.availableBalance = this.modal.locator('text=Available Balance').locator('..').locator('span').filter({ hasText: /\d+/ });
    this.submitButton = this.modal.locator('button[type="submit"]:has-text("Send")');
    this.cancelButton = this.modal.locator('button:has-text("Cancel")');
    this.closeButton = this.modal.locator('button[aria-label="Close modal"]');
    this.errorMessage = page.locator('[role="alert"], .error, text=Error').filter({ hasText: /error|failed/i });
    this.successMessage = page.locator('[role="alert"], text=successful, text=sent').filter({ hasText: /success|sent/i });
  }

  /**
   * Check if modal is open
   */
  async isOpen(): Promise<boolean> {
    return await this.modal.isVisible();
  }

  /**
   * Close modal via cancel button
   */
  async cancel(): Promise<void> {
    await this.cancelButton.click();
    await this.modal.waitFor({ state: 'hidden', timeout: 2000 });
  }

  /**
   * Close modal via X button
   */
  async close(): Promise<void> {
    await this.closeButton.click();
    await this.modal.waitFor({ state: 'hidden', timeout: 2000 });
  }

  /**
   * Search for recipient
   */
  async searchRecipient(query: string): Promise<void> {
    await this.recipientInput.fill(query);
    await this.page.waitForTimeout(500); // Wait for autocomplete
  }

  /**
   * Select recipient from search results
   */
  async selectRecipient(index: number = 0): Promise<void> {
    const results = this.recipientSuggestions.locator('button[role="option"]');
    await results.nth(index).click();
    await this.page.waitForTimeout(300);
  }

  /**
   * Get number of search results
   */
  async getResultsCount(): Promise<number> {
    if (await this.recipientSuggestions.isVisible()) {
      return await this.recipientSuggestions.locator('button[role="option"]').count();
    }
    return 0;
  }

  /**
   * Fill transfer form
   */
  async fillTransferForm(data: {
    recipient: string;
    amount: string;
    description?: string;
  }): Promise<void> {
    // Search and select recipient
    await this.searchRecipient(data.recipient);
    await this.page.waitForTimeout(500);

    const resultsCount = await this.getResultsCount();
    if (resultsCount > 0) {
      await this.selectRecipient(0);
    }

    // Fill amount
    await this.amountInput.fill(data.amount);

    // Fill description if provided
    if (data.description) {
      await this.descriptionInput.fill(data.description);
    }
  }

  /**
   * Submit transfer
   */
  async submit(): Promise<void> {
    await this.submitButton.click();
    await this.page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
  }

  /**
   * Make a complete transfer
   */
  async transfer(data: {
    recipient: string;
    amount: string;
    description?: string;
  }): Promise<void> {
    await this.fillTransferForm(data);
    await this.submit();
  }

  /**
   * Check if transfer was successful
   */
  async isSuccess(): Promise<boolean> {
    return await this.successMessage.isVisible({ timeout: 3000 }).catch(() => false);
  }

  /**
   * Check if there's an error
   */
  async hasError(): Promise<boolean> {
    return await this.errorMessage.isVisible({ timeout: 2000 }).catch(() => false);
  }

  /**
   * Get error message
   */
  async getErrorMessage(): Promise<string> {
    if (await this.hasError()) {
      return await this.errorMessage.textContent() || '';
    }
    return '';
  }

  /**
   * Check if submit button is disabled
   */
  async isSubmitDisabled(): Promise<boolean> {
    return await this.submitButton.isDisabled();
  }

  /**
   * Get available balance from modal
   */
  async getAvailableBalance(): Promise<number> {
    const balanceText = await this.availableBalance.textContent() || '0';
    const match = balanceText.match(/[\d.]+/);
    return match ? parseFloat(match[0]) : 0;
  }
}

/**
 * Insights Page Object (if separate insights page exists)
 *
 * NOTE: Check if this is a separate route or part of wallet page
 */
export class InsightsPage extends BasePage {
  readonly totalEarned: Locator;
  readonly totalSpent: Locator;
  readonly chartContainer: Locator;
  readonly periodSelector: Locator;
  readonly categoryBreakdown: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    this.totalEarned = page.locator('text=Total Earned').locator('..').locator('span, p').filter({ hasText: /\d+/ });
    this.totalSpent = page.locator('text=Total Spent').locator('..').locator('span, p').filter({ hasText: /\d+/ });
    this.chartContainer = page.locator('canvas, [class*="chart"]').first();
    this.periodSelector = page.locator('button[role="combobox"], select').filter({ hasText: /Week|Month|Year/ });
    this.categoryBreakdown = page.locator('text=Category').locator('..').locator('..');
  }

  /**
   * Navigate to insights page (if separate route exists)
   */
  async navigate(): Promise<void> {
    await this.goto('wallet/insights');
  }

  /**
   * Get total earned
   */
  async getTotalEarned(): Promise<string> {
    return (await this.totalEarned.textContent())?.trim() || '';
  }

  /**
   * Get total spent
   */
  async getTotalSpent(): Promise<string> {
    return (await this.totalSpent.textContent())?.trim() || '';
  }

  /**
   * Check if chart is loaded
   */
  async isChartLoaded(): Promise<boolean> {
    return await this.chartContainer.isVisible();
  }
}
