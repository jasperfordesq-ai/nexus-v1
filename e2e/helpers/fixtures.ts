// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Test user credentials and data
 */
export const testUsers = {
  primary: {
    email: process.env.E2E_TEST_USER_EMAIL || 'e2e-test@example.com',
    password: process.env.E2E_TEST_USER_PASSWORD || 'TestPass123!',
    firstName: process.env.E2E_TEST_USER_FIRSTNAME || 'E2E',
    lastName: process.env.E2E_TEST_USER_LASTNAME || 'Tester',
  },
  secondary: {
    email: process.env.E2E_SECOND_USER_EMAIL || 'e2e-test-2@example.com',
    password: process.env.E2E_SECOND_USER_PASSWORD || 'TestPass456!',
    firstName: 'Second',
    lastName: 'Tester',
  },
};

/**
 * Test tenant configuration
 */
export const testTenant = {
  slug: process.env.E2E_TENANT_SLUG || 'hour-timebank',
  id: parseInt(process.env.E2E_TENANT_ID || '2', 10),
};

/**
 * Generate random test data
 */
export function generateTestData() {
  const timestamp = Date.now();
  const randomId = Math.random().toString(36).substring(7);

  return {
    listing: {
      title: `E2E Test Listing ${timestamp}`,
      description: `This is a test listing created by E2E tests at ${new Date().toISOString()}`,
      category: 'Skills & Trades',
      type: 'offer',
      duration: 60,
      location: 'Test Location',
    },
    message: {
      subject: `E2E Test Message ${timestamp}`,
      content: `This is a test message sent at ${new Date().toISOString()}`,
    },
    event: {
      title: `E2E Test Event ${timestamp}`,
      description: `Test event created at ${new Date().toISOString()}`,
      location: 'Test Venue',
      startDate: new Date(Date.now() + 86400000).toISOString(), // Tomorrow
      endDate: new Date(Date.now() + 90000000).toISOString(), // Tomorrow + 1 hour
    },
    group: {
      name: `E2E Test Group ${timestamp}`,
      description: `Test group created at ${new Date().toISOString()}`,
      privacy: 'public',
    },
    user: {
      email: `e2e-${randomId}@example.com`,
      password: `TestPass${randomId}!`,
      firstName: 'Test',
      lastName: `User-${randomId}`,
    },
  };
}

/**
 * Common selectors for reusability
 */
export const selectors = {
  // Layout
  navbar: '[data-testid="navbar"], nav.navbar',
  footer: '[data-testid="footer"], footer',
  mobileDrawer: '[data-testid="mobile-drawer"]',

  // Forms
  submitButton: 'button[type="submit"]',
  cancelButton: 'button:has-text("Cancel")',
  saveButton: 'button:has-text("Save")',
  deleteButton: 'button:has-text("Delete")',

  // Modals
  modal: '[role="dialog"], .modal',
  modalClose: '[aria-label="Close"], button:has-text("Close")',
  confirmButton: 'button:has-text("Confirm"), button:has-text("Yes")',

  // Listings
  listingCard: '[data-testid="listing-card"], .listing-card',
  listingGrid: '[data-testid="listings-grid"], .listings-grid',
  listingDetail: '[data-testid="listing-detail"]',
  createListingButton: 'button:has-text("Create Listing"), a[href*="/listings/new"]',

  // Messages
  messageList: '[data-testid="messages-list"], .messages-list',
  messageThread: '[data-testid="message-thread"]',
  messageInput: '[data-testid="message-input"], textarea[placeholder*="message"]',
  sendButton: 'button:has-text("Send")',

  // Navigation
  dashboardLink: 'a[href*="/dashboard"]',
  listingsLink: 'a[href*="/listings"]',
  messagesLink: 'a[href*="/messages"]',
  walletLink: 'a[href*="/wallet"]',

  // Auth
  loginForm: 'form:has(input[type="password"])',
  emailInput: 'input[name="email"], input[type="email"]',
  passwordInput: 'input[name="password"], input[type="password"]',

  // Notifications
  toast: '[data-testid="toast"], .toast, [role="alert"]',
  notificationBadge: '[data-testid="notification-badge"], .notification-badge',
};

/**
 * Wait for toast notification
 */
export async function waitForToast(page: any, expectedText?: string): Promise<void> {
  const toast = page.locator(selectors.toast);
  await toast.waitFor({ state: 'visible', timeout: 5000 });

  if (expectedText) {
    await expect(toast).toContainText(expectedText);
  }

  // Wait for toast to disappear
  await toast.waitFor({ state: 'hidden', timeout: 10000 });
}
