import { Page, Locator, expect } from '@playwright/test';
import { BasePage } from './BasePage';

/**
 * Messages/Inbox Page Object
 */
export class MessagesPage extends BasePage {
  readonly conversationList: Locator;
  readonly conversationItems: Locator;
  readonly newMessageButton: Locator;
  readonly searchInput: Locator;
  readonly unreadCount: Locator;
  readonly emptyInbox: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    // React: GlassCard conversation items
    this.conversationList = page.locator('[class*="glass"]').filter({ has: page.locator('img[alt], .avatar') });
    this.conversationItems = page.locator('a[href*="/messages/"], article').filter({ has: page.locator('img[alt], .avatar') });
    this.newMessageButton = page.locator('button:has-text("New Message"), button:has-text("Compose")').first();
    this.searchInput = page.locator('input[placeholder*="Search"]');
    this.unreadCount = page.locator('.unread-badge, [class*="chip"]').filter({ hasText: /\d+/ });
    this.emptyInbox = page.locator('text=/No messages|No conversations/');
  }

  /**
   * Navigate to messages inbox
   */
  async navigate(): Promise<void> {
    await this.goto('messages');
  }

  /**
   * Wait for messages page to load
   */
  async waitForLoad(): Promise<void> {
    await this.page.waitForLoadState('domcontentloaded');
    await this.page.waitForLoadState('networkidle').catch(() => {});

    // Wait for React to hydrate - new message button should always be present
    await this.newMessageButton.waitFor({
      state: 'attached',
      timeout: 15000
    }).catch(() => {});

    // Give React time to render
    await this.page.waitForTimeout(500);
  }

  /**
   * Get number of conversations
   */
  async getConversationCount(): Promise<number> {
    return await this.conversationItems.count();
  }

  /**
   * Get number of unread conversations
   */
  async getUnreadCount(): Promise<number> {
    const unreadItems = this.conversationItems.filter({ has: this.page.locator('.unread') });
    return await unreadItems.count();
  }

  /**
   * Click on a conversation
   */
  async openConversation(index: number = 0): Promise<void> {
    await this.conversationItems.nth(index).click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Open conversation with specific user
   */
  async openConversationWith(userName: string): Promise<void> {
    await this.conversationItems.filter({ hasText: userName }).first().click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Click new message button
   */
  async clickNewMessage(): Promise<void> {
    await this.newMessageButton.click();
    await this.page.waitForTimeout(300);
  }

  /**
   * Search conversations
   */
  async searchConversations(query: string): Promise<void> {
    await this.searchInput.fill(query);
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Check if inbox is empty
   */
  async isInboxEmpty(): Promise<boolean> {
    return await this.emptyInbox.isVisible();
  }
}

/**
 * Message Thread Page Object
 */
export class MessageThreadPage extends BasePage {
  readonly messages: Locator;
  readonly messageInput: Locator;
  readonly sendButton: Locator;
  readonly recipientName: Locator;
  readonly typingIndicator: Locator;
  readonly attachmentButton: Locator;
  readonly voiceMessageButton: Locator;
  readonly deleteConversationButton: Locator;
  readonly backButton: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    this.messages = page.locator('.message, .message-item, [data-message]');
    this.messageInput = page.locator('textarea[name="message"], .message-input, input[name="message"]');
    this.sendButton = page.locator('.send-btn, button[type="submit"]:near(.message-input)');
    this.recipientName = page.locator('.recipient-name, .thread-header h2');
    this.typingIndicator = page.locator('.typing-indicator, [data-typing]');
    this.attachmentButton = page.locator('.attachment-btn, [data-attachment]');
    this.voiceMessageButton = page.locator('.voice-btn, [data-voice]');
    this.deleteConversationButton = page.locator('.delete-conversation, [data-delete-conversation]');
    this.backButton = page.locator('.back-btn, a[href*="messages"]:not([href*="new"])');
  }

  /**
   * Navigate to a specific thread
   */
  async navigateToThread(threadId: number | string): Promise<void> {
    await this.goto(`messages/${threadId}`);
  }

  /**
   * Send a message
   */
  async sendMessage(text: string): Promise<void> {
    await this.messageInput.fill(text);
    await this.sendButton.click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Get all messages in thread
   */
  async getMessages(): Promise<string[]> {
    const messageTexts = await this.messages.locator('.message-content, .message-text').allTextContents();
    return messageTexts.map(t => t.trim());
  }

  /**
   * Get message count
   */
  async getMessageCount(): Promise<number> {
    return await this.messages.count();
  }

  /**
   * Get recipient name
   */
  async getRecipientName(): Promise<string> {
    return await this.recipientName.textContent() || '';
  }

  /**
   * Check if typing indicator is visible
   */
  async isTypingIndicatorVisible(): Promise<boolean> {
    return await this.typingIndicator.isVisible();
  }

  /**
   * Go back to inbox
   */
  async goBackToInbox(): Promise<void> {
    await this.backButton.click();
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Delete conversation
   */
  async deleteConversation(): Promise<void> {
    await this.deleteConversationButton.click();
    // Handle confirmation dialog if present
    const confirmButton = this.page.locator('.confirm-delete, [data-confirm]');
    if (await confirmButton.isVisible()) {
      await confirmButton.click();
    }
    await this.page.waitForLoadState('domcontentloaded');
  }
}

/**
 * New Message Page Object
 */
export class NewMessagePage extends BasePage {
  readonly recipientInput: Locator;
  readonly recipientSuggestions: Locator;
  readonly messageInput: Locator;
  readonly sendButton: Locator;

  constructor(page: Page, tenant?: string) {
    super(page, tenant);

    this.recipientInput = page.locator('input[placeholder*="Search for a member"], input[name="recipient"], .recipient-search');
    this.recipientSuggestions = page.locator('button').filter({ has: page.locator('img, .avatar') });
    this.messageInput = page.locator('textarea[name="message"], .message-input');
    this.sendButton = page.locator('button[type="submit"], .send-btn');
  }

  /**
   * Navigate to new message page
   */
  async navigate(): Promise<void> {
    await this.goto('messages');
    const newMessageButton = this.page.locator('button:has-text("New Message")').first();
    if (await newMessageButton.count() > 0) {
      await newMessageButton.click();
      await this.page.waitForTimeout(300);
    }
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
   * Compose and send new message
   */
  async composeMessage(recipient: string, message: string): Promise<void> {
    await this.searchRecipient(recipient);
    await this.selectRecipient(0);
    await this.messageInput.fill(message);
    await this.sendButton.click();
    await this.page.waitForLoadState('domcontentloaded');
  }
}
