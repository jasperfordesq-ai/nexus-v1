import { Page, Locator, expect } from '@playwright/test';
import { BasePage } from './BasePage';

/**
 * Wallet Page Object - Time Credit Management
 */
export class WalletPage extends BasePage {
  readonly balance: Locator;
  readonly transactionsList: Locator;
  readonly transactionItems: Locator;
  readonly transferButton: Locator;
  readonly insightsLink: Locator;
  readonly filterDropdown: Locator;
  readonly pendingCount: Locator;
  readonly noTransactionsMessage: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    this.balance = page.locator('.wallet-balance-amount, .wallet-balance, [data-balance], .balance-display');
    this.transactionsList = page.locator('.wallet-transactions, .transaction-list, .transactions');
    this.transactionItems = page.locator('.wallet-tx-row, .transaction, .transaction-item, [data-transaction]');
    this.transferButton = page.locator('.wallet-submit-btn, .transfer-btn, a[href*="transfer"], button:has-text("Send")');
    this.insightsLink = page.locator('.wallet-insights-link, a[href*="insights"], .insights-link');
    this.filterDropdown = page.locator('select[name="filter"], [data-filter]');
    this.pendingCount = page.locator('.pending-count, [data-pending]');
    this.noTransactionsMessage = page.locator('.no-transactions, .empty-state, .wallet-empty');
  }

  /**
   * Navigate to wallet page
   */
  async navigate(): Promise<void> {
    await this.goto('wallet');
  }

  /**
   * Get current balance
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
   * Get number of transactions shown
   */
  async getTransactionCount(): Promise<number> {
    return await this.transactionItems.count();
  }

  /**
   * Click transfer button
   */
  async clickTransfer(): Promise<void> {
    await this.transferButton.click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Go to insights page
   */
  async goToInsights(): Promise<void> {
    await this.insightsLink.click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Filter transactions
   */
  async filterTransactions(filter: 'all' | 'sent' | 'received' | 'pending'): Promise<void> {
    await this.filterDropdown.selectOption(filter);
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Get pending transaction count
   */
  async getPendingCount(): Promise<number> {
    if (await this.pendingCount.count() > 0) {
      const text = await this.pendingCount.textContent() || '0';
      return parseInt(text.replace(/\D/g, ''), 10);
    }
    return 0;
  }

  /**
   * Check if there are no transactions
   */
  async hasNoTransactions(): Promise<boolean> {
    return await this.noTransactionsMessage.isVisible();
  }

  /**
   * Get transaction details
   */
  async getTransactionDetails(index: number = 0): Promise<{
    amount: string;
    description: string;
    date: string;
    type: 'sent' | 'received' | 'pending';
  }> {
    const transaction = this.transactionItems.nth(index);

    const amount = await transaction.locator('.amount, [data-amount]').textContent() || '';
    const description = await transaction.locator('.description, [data-description]').textContent() || '';
    const date = await transaction.locator('.date, [data-date], time').textContent() || '';

    let type: 'sent' | 'received' | 'pending' = 'received';
    const classes = await transaction.getAttribute('class') || '';
    if (classes.includes('sent') || classes.includes('debit')) {
      type = 'sent';
    } else if (classes.includes('pending')) {
      type = 'pending';
    }

    return { amount: amount.trim(), description: description.trim(), date: date.trim(), type };
  }
}

/**
 * Transfer Page Object
 */
export class TransferPage extends BasePage {
  readonly recipientInput: Locator;
  readonly recipientSuggestions: Locator;
  readonly amountInput: Locator;
  readonly descriptionInput: Locator;
  readonly submitButton: Locator;
  readonly cancelButton: Locator;
  readonly errorMessage: Locator;
  readonly successMessage: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    this.recipientInput = page.locator('input[name="recipient"], .recipient-search, #recipient');
    this.recipientSuggestions = page.locator('.recipient-suggestion, .user-suggestion, [data-user-id]');
    this.amountInput = page.locator('input[name="amount"], input[name="hours"]');
    this.descriptionInput = page.locator('textarea[name="description"], input[name="description"]');
    this.submitButton = page.locator('button[type="submit"], .transfer-submit');
    this.cancelButton = page.locator('.cancel-btn, a[href*="wallet"]');
    this.errorMessage = page.locator('.error, .alert-danger, .govuk-error-summary');
    this.successMessage = page.locator('.success, .alert-success, .govuk-notification-banner--success');
  }

  /**
   * Search for recipient
   */
  async searchRecipient(query: string): Promise<void> {
    await this.recipientInput.fill(query);
    await this.page.waitForTimeout(500); // Wait for autocomplete
  }

  /**
   * Select recipient from suggestions
   */
  async selectRecipient(index: number = 0): Promise<void> {
    await this.recipientSuggestions.nth(index).click();
  }

  /**
   * Fill transfer form
   */
  async fillTransferForm(data: {
    recipient: string;
    amount: string;
    description?: string;
  }): Promise<void> {
    await this.searchRecipient(data.recipient);
    await this.selectRecipient(0);
    await this.amountInput.fill(data.amount);

    if (data.description) {
      await this.descriptionInput.fill(data.description);
    }
  }

  /**
   * Submit transfer
   */
  async submit(): Promise<void> {
    await this.submitButton.click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Make a transfer
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
    return await this.successMessage.isVisible();
  }

  /**
   * Check if there's an error
   */
  async hasError(): Promise<boolean> {
    return await this.errorMessage.isVisible();
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
}

/**
 * Insights Page Object
 */
export class InsightsPage extends BasePage {
  readonly totalEarned: Locator;
  readonly totalSpent: Locator;
  readonly chartContainer: Locator;
  readonly periodSelector: Locator;
  readonly categoryBreakdown: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    this.totalEarned = page.locator('.total-earned, [data-earned]');
    this.totalSpent = page.locator('.total-spent, [data-spent]');
    this.chartContainer = page.locator('.chart-container, canvas, .insights-chart');
    this.periodSelector = page.locator('select[name="period"], [data-period]');
    this.categoryBreakdown = page.locator('.category-breakdown, .breakdown');
  }

  /**
   * Navigate to insights page
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
   * Change time period
   */
  async changePeriod(period: 'week' | 'month' | 'year' | 'all'): Promise<void> {
    await this.periodSelector.selectOption(period);
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Check if chart is loaded
   */
  async isChartLoaded(): Promise<boolean> {
    return await this.chartContainer.isVisible();
  }
}
