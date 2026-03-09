// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * E2E tests — Messages happy paths.
 *
 * Covers:
 *   - Messages page loads and shows conversation list (or empty state)
 *   - "New Message" button opens the member search modal
 *   - Typing in the member search input triggers search results
 *   - Opening an existing conversation renders the message thread
 *   - Composing and sending a message clears the compose box
 *
 * All tests run as an authenticated user (storageState reused from global setup).
 *
 * Route: /t/hour-timebank/messages  (ProtectedRoute + FeatureGate module="messages")
 */

import { test, expect } from '@playwright/test';

test.use({ storageState: 'e2e/.auth/user.json' });

const TENANT_SLUG = process.env.E2E_TENANT ?? 'hour-timebank';
const messagesPath = `/t/${TENANT_SLUG}/messages`;

test.describe('Messages — Page loads', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(messagesPath);
    // Wait for the page heading
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible({ timeout: 15000 });
  });

  test('messages page shows inbox tab and search input', async ({ page }) => {
    // Inbox tab
    await expect(page.getByRole('tab', { name: /inbox/i })).toBeVisible();

    // Search / filter input
    await expect(page.getByPlaceholder(/search/i).first()).toBeVisible();
  });

  test('"New Message" button is present', async ({ page }) => {
    await expect(
      page.getByRole('button', { name: /new message/i })
    ).toBeVisible();
  });
});

test.describe('Messages — New message modal', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(messagesPath);
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible({ timeout: 15000 });
  });

  test('opening New Message modal shows member search input', async ({ page }) => {
    const newMsgBtn = page.getByRole('button', { name: /new message/i });

    // If the feature is disabled the button is disabled — guard the test
    if (await newMsgBtn.isDisabled()) {
      test.skip();
      return;
    }

    await newMsgBtn.click();

    // Modal dialog should appear
    const modal = page.getByRole('dialog');
    await expect(modal).toBeVisible({ timeout: 5000 });

    // Member search input inside the modal
    const searchInput = modal.getByPlaceholder(/search/i).or(modal.getByRole('textbox')).first();
    await expect(searchInput).toBeVisible({ timeout: 5000 });
  });

  test('typing in member search input triggers results or empty state', async ({ page }) => {
    const newMsgBtn = page.getByRole('button', { name: /new message/i });

    if (await newMsgBtn.isDisabled()) {
      test.skip();
      return;
    }

    await newMsgBtn.click();

    const modal = page.getByRole('dialog');
    await expect(modal).toBeVisible({ timeout: 5000 });

    const searchInput = modal.getByPlaceholder(/search/i).or(modal.getByRole('textbox')).first();
    await searchInput.fill('a');

    // After typing, either search results (buttons) or "no members" text appear
    // Give it a moment for the 300 ms debounce + API call
    await expect(
      modal.getByRole('button').or(modal.getByText(/no members|no results/i))
    ).toBeVisible({ timeout: 8000 });
  });

  test('closing the New Message modal hides it', async ({ page }) => {
    const newMsgBtn = page.getByRole('button', { name: /new message/i });

    if (await newMsgBtn.isDisabled()) {
      test.skip();
      return;
    }

    await newMsgBtn.click();

    const modal = page.getByRole('dialog');
    await expect(modal).toBeVisible({ timeout: 5000 });

    // Press Escape to close
    await page.keyboard.press('Escape');
    await expect(modal).not.toBeVisible({ timeout: 5000 });
  });
});

test.describe('Messages — Conversation happy path', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(messagesPath);
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible({ timeout: 15000 });
  });

  test('clicking an existing conversation opens the message thread', async ({ page }) => {
    // Look for a conversation link (ConversationCard renders an <a> with aria-label)
    const conversationLinks = page.getByRole('link', { name: /conversation with/i });

    const count = await conversationLinks.count();
    if (count === 0) {
      // No conversations yet — skip (inbox is empty for this test account)
      test.skip();
      return;
    }

    await conversationLinks.first().click();

    // The conversation page renders an <h1> with the other user's name or a back button
    await expect(
      page.getByRole('link', { name: /back|messages/i })
        .or(page.getByRole('heading', { level: 1 }))
    ).toBeVisible({ timeout: 10000 });

    // The URL should change to /messages/:id or /messages/new/:userId
    await expect(page).toHaveURL(new RegExp(`/t/${TENANT_SLUG}/messages/`), { timeout: 10000 });
  });

  test('sending a message in a conversation clears the compose input', async ({ page }) => {
    // Navigate directly to the first conversation if any
    const conversationLinks = page.getByRole('link', { name: /conversation with/i });

    const count = await conversationLinks.count();
    if (count === 0) {
      test.skip();
      return;
    }

    await conversationLinks.first().click();
    await expect(page).toHaveURL(new RegExp(`/t/${TENANT_SLUG}/messages/`), { timeout: 10000 });

    // The compose textarea — ConversationPage uses a Textarea for the message body
    const compose = page.getByRole('textbox', { name: /message|type/i })
      .or(page.locator('textarea[placeholder]'))
      .first();

    await compose.waitFor({ state: 'visible', timeout: 10000 });
    await compose.fill('Hello from Playwright E2E test — please ignore.');

    // Send button (role="button" with Send icon, type="submit" or labelled "Send")
    const sendBtn = page
      .getByRole('button', { name: /send/i })
      .last(); // last to avoid the "Send Credits" button if visible

    await sendBtn.click();

    // After sending, the compose box should be cleared
    await expect(compose).toHaveValue('', { timeout: 8000 });
  });
});
