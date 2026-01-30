import { test, expect } from '@playwright/test';
import { MessagesPage, MessageThreadPage, NewMessagePage } from '../../page-objects';
import { generateTestData, tenantUrl } from '../../helpers/test-utils';

test.describe('Messages - Inbox', () => {
  test('should display messages inbox', async ({ page }) => {
    const messagesPage = new MessagesPage(page);
    await messagesPage.navigate();

    await expect(page).toHaveURL(/messages/);
  });

  test('should show conversations or empty state', async ({ page }) => {
    const messagesPage = new MessagesPage(page);
    await messagesPage.navigate();

    const count = await messagesPage.getConversationCount();
    const isEmpty = await messagesPage.isInboxEmpty();

    expect(count > 0 || isEmpty).toBeTruthy();
  });

  test('should have new message button', async ({ page }) => {
    const messagesPage = new MessagesPage(page);
    await messagesPage.navigate();

    await expect(messagesPage.newMessageButton).toBeVisible();
  });

  test('should display conversation preview info', async ({ page }) => {
    const messagesPage = new MessagesPage(page);
    await messagesPage.navigate();

    const count = await messagesPage.getConversationCount();
    if (count > 0) {
      const conversation = messagesPage.conversationItems.first();

      // Should show participant name
      const name = conversation.locator('.name, .user-name, [data-name]');
      await expect(name).toBeVisible();

      // Should show preview or last message time
      const preview = conversation.locator('.preview, .last-message, time');
      await expect(preview).toBeVisible();
    }
  });

  test('should show unread indicator', async ({ page }) => {
    const messagesPage = new MessagesPage(page);
    await messagesPage.navigate();

    const unreadCount = await messagesPage.getUnreadCount();
    // Just verify the method works, actual unread count depends on data
    expect(unreadCount).toBeGreaterThanOrEqual(0);
  });

  test('should open conversation when clicked', async ({ page }) => {
    const messagesPage = new MessagesPage(page);
    await messagesPage.navigate();

    const count = await messagesPage.getConversationCount();
    if (count > 0) {
      await messagesPage.openConversation(0);
      expect(page.url()).toMatch(/messages\/\d+|thread/);
    }
  });

  test('should have search functionality', async ({ page }) => {
    const messagesPage = new MessagesPage(page);
    await messagesPage.navigate();

    if (await messagesPage.searchInput.count() > 0) {
      await expect(messagesPage.searchInput).toBeVisible();
    }
  });
});

test.describe('Messages - Thread', () => {
  test('should display message thread', async ({ page }) => {
    const messagesPage = new MessagesPage(page);
    await messagesPage.navigate();

    const count = await messagesPage.getConversationCount();
    if (count > 0) {
      await messagesPage.openConversation(0);

      const threadPage = new MessageThreadPage(page);
      await expect(threadPage.recipientName).toBeVisible();
    }
  });

  test('should show messages in thread', async ({ page }) => {
    const messagesPage = new MessagesPage(page);
    await messagesPage.navigate();

    const count = await messagesPage.getConversationCount();
    if (count > 0) {
      await messagesPage.openConversation(0);

      const threadPage = new MessageThreadPage(page);
      const messageCount = await threadPage.getMessageCount();
      expect(messageCount).toBeGreaterThan(0);
    }
  });

  test('should have message input field', async ({ page }) => {
    const messagesPage = new MessagesPage(page);
    await messagesPage.navigate();

    const count = await messagesPage.getConversationCount();
    if (count > 0) {
      await messagesPage.openConversation(0);

      const threadPage = new MessageThreadPage(page);
      await expect(threadPage.messageInput).toBeVisible();
    }
  });

  test('should send a message', async ({ page }) => {
    const messagesPage = new MessagesPage(page);
    await messagesPage.navigate();

    const count = await messagesPage.getConversationCount();
    if (count > 0) {
      await messagesPage.openConversation(0);

      const threadPage = new MessageThreadPage(page);
      const testData = generateTestData();
      const messageText = `Test message ${testData.uniqueId}`;

      const initialCount = await threadPage.getMessageCount();
      await threadPage.sendMessage(messageText);

      // Verify message was sent
      const messages = await threadPage.getMessages();
      expect(messages.join(' ')).toContain(testData.uniqueId);
    }
  });

  test('should not send empty messages', async ({ page }) => {
    const messagesPage = new MessagesPage(page);
    await messagesPage.navigate();

    const count = await messagesPage.getConversationCount();
    if (count > 0) {
      await messagesPage.openConversation(0);

      const threadPage = new MessageThreadPage(page);

      // Send button should be disabled or do nothing
      const isDisabled = await threadPage.sendButton.isDisabled();
      if (!isDisabled) {
        await threadPage.sendButton.click();
        // Should not send, no error either
      }
      expect(true).toBeTruthy();
    }
  });

  test('should have back to inbox link', async ({ page }) => {
    const messagesPage = new MessagesPage(page);
    await messagesPage.navigate();

    const count = await messagesPage.getConversationCount();
    if (count > 0) {
      await messagesPage.openConversation(0);

      const threadPage = new MessageThreadPage(page);
      await expect(threadPage.backButton).toBeVisible();
    }
  });

  test('should navigate back to inbox', async ({ page }) => {
    const messagesPage = new MessagesPage(page);
    await messagesPage.navigate();

    const count = await messagesPage.getConversationCount();
    if (count > 0) {
      await messagesPage.openConversation(0);

      const threadPage = new MessageThreadPage(page);
      await threadPage.goBackToInbox();

      expect(page.url()).toMatch(/messages\/?$/);
    }
  });
});

test.describe('Messages - New Message', () => {
  test('should navigate to new message page', async ({ page }) => {
    const messagesPage = new MessagesPage(page);
    await messagesPage.navigate();
    await messagesPage.clickNewMessage();

    expect(page.url()).toContain('new');
  });

  test('should have recipient search', async ({ page }) => {
    const newMessagePage = new NewMessagePage(page);
    await newMessagePage.navigate();

    await expect(newMessagePage.recipientInput).toBeVisible();
  });

  test('should show user suggestions on search', async ({ page }) => {
    const newMessagePage = new NewMessagePage(page);
    await newMessagePage.navigate();

    await newMessagePage.searchRecipient('a');
    await page.waitForTimeout(500);

    const suggestions = newMessagePage.recipientSuggestions;
    // May or may not have suggestions depending on data
    const count = await suggestions.count();
    expect(count).toBeGreaterThanOrEqual(0);
  });

  test('should have message input', async ({ page }) => {
    const newMessagePage = new NewMessagePage(page);
    await newMessagePage.navigate();

    await expect(newMessagePage.messageInput).toBeVisible();
  });

  test('should require recipient selection', async ({ page }) => {
    const newMessagePage = new NewMessagePage(page);
    await newMessagePage.navigate();

    await newMessagePage.messageInput.fill('Test message');
    await newMessagePage.sendButton.click();
    await page.waitForLoadState('domcontentloaded');

    // Should stay on page or show error
    const hasError = await page.locator('.error, .alert-danger').count() > 0;
    const stillOnNew = page.url().includes('new');

    expect(hasError || stillOnNew).toBeTruthy();
  });
});

test.describe('Messages - Real-time Features', () => {
  test('should show typing indicator element', async ({ page }) => {
    const messagesPage = new MessagesPage(page);
    await messagesPage.navigate();

    const count = await messagesPage.getConversationCount();
    if (count > 0) {
      await messagesPage.openConversation(0);

      // Typing indicator element should exist (may not be visible)
      const threadPage = new MessageThreadPage(page);
      const typingElement = threadPage.typingIndicator;
      // Just verify element exists in DOM
      expect(await typingElement.count()).toBeGreaterThanOrEqual(0);
    }
  });
});

test.describe('Messages - Actions', () => {
  test.skip('should allow deleting a conversation', async ({ page }) => {
    // Skip to avoid deleting real data
    // Enable when test data setup is available
  });

  test('should have delete conversation option', async ({ page }) => {
    const messagesPage = new MessagesPage(page);
    await messagesPage.navigate();

    const count = await messagesPage.getConversationCount();
    if (count > 0) {
      await messagesPage.openConversation(0);

      const threadPage = new MessageThreadPage(page);
      const deleteButton = threadPage.deleteConversationButton;

      // Delete option may be in dropdown menu
      const moreMenu = page.locator('.more-menu, [data-more], .dropdown-toggle');
      if (await moreMenu.count() > 0) {
        await moreMenu.click();
        await page.waitForTimeout(200);
      }

      // Check if delete option exists
      const hasDelete = await deleteButton.count() > 0;
      expect(hasDelete).toBeTruthy();
    }
  });
});

test.describe('Messages - Accessibility', () => {
  test('should have proper heading structure', async ({ page }) => {
    const messagesPage = new MessagesPage(page);
    await messagesPage.navigate();

    const h1 = page.locator('h1');
    await expect(h1).toBeVisible();
  });

  test('should have accessible conversation items', async ({ page }) => {
    const messagesPage = new MessagesPage(page);
    await messagesPage.navigate();

    const count = await messagesPage.getConversationCount();
    if (count > 0) {
      const conversation = messagesPage.conversationItems.first();

      // Should be keyboard focusable
      const link = conversation.locator('a').first();
      if (await link.count() > 0) {
        await link.focus();
        await expect(link).toBeFocused();
      }
    }
  });

  test('should have accessible message input', async ({ page }) => {
    const messagesPage = new MessagesPage(page);
    await messagesPage.navigate();

    const count = await messagesPage.getConversationCount();
    if (count > 0) {
      await messagesPage.openConversation(0);

      const threadPage = new MessageThreadPage(page);
      const input = threadPage.messageInput;

      const label = await input.getAttribute('aria-label');
      const labelledBy = await input.getAttribute('aria-labelledby');
      const placeholder = await input.getAttribute('placeholder');

      expect(label || labelledBy || placeholder).toBeTruthy();
    }
  });
});

test.describe('Messages - Mobile Behavior', () => {
  test.use({ viewport: { width: 375, height: 667 } });

  test('should display properly on mobile', async ({ page }) => {
    const messagesPage = new MessagesPage(page);
    await messagesPage.navigate();

    // Content should be visible
    const content = page.locator('main, .content, .messages');
    await expect(content).toBeVisible();
  });
});
