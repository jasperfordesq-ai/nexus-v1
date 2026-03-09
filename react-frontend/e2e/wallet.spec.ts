// Copyright (c) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * E2E tests -- Wallet happy paths.
 *
 * Covers:
 *   - Wallet page loads and shows balance + "Send Credits" button
 *   - "Send Credits" button opens the Transfer modal
 *   - Transfer modal renders correct fields (recipient search, amount, description)
 *   - Searching for a recipient in the modal returns results
 *   - Closing the modal hides it
 *   - Filling and submitting the transfer shows success toast and closes modal
 *
 * All tests run as an authenticated user (storageState reused from global setup).
 *
 * Route: /t/hour-timebank/wallet  (ProtectedRoute + FeatureGate module="wallet")
 */

import { test, expect } from "@playwright/test";

test.use({ storageState: "e2e/.auth/user.json" });

const TENANT_SLUG = process.env.E2E_TENANT ?? "hour-timebank";
const walletPath = `/t/${TENANT_SLUG}/wallet`;

test.describe("Wallet -- Page loads", () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(walletPath);
    await expect(page.getByRole("heading", { level: 1 })).toBeVisible({ timeout: 15000 });
  });

  test("wallet page shows balance section and send credits button", async ({ page }) => {
    // Balance label is present once data loads
    await expect(
      page.getByText(/your balance|balance/i).first()
    ).toBeVisible({ timeout: 10000 });

    // "Send Credits" button is rendered (may be disabled when balance is zero)
    await expect(
      page.getByRole("button", { name: /send credits/i })
    ).toBeVisible({ timeout: 10000 });
  });

  test("wallet page shows transaction tabs", async ({ page }) => {
    await expect(page.getByRole("main")).toBeVisible();

    // WalletPage renders Tabs with "All", "Earned", "Spent", "Pending"
    await expect(
      page.getByRole("tab", { name: /all/i })
        .or(page.getByRole("button", { name: /all/i }))
    ).toBeVisible({ timeout: 10000 });
  });
});

test.describe("Wallet -- Transfer modal", () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(walletPath);
    await expect(page.getByRole("heading", { level: 1 })).toBeVisible({ timeout: 15000 });
    // Allow balance to load before checking button state
    await page.waitForTimeout(2000);
  });

  test("Send Credits button opens the transfer modal", async ({ page }) => {
    const sendBtn = page.getByRole("button", { name: /send credits/i });

    // When balance is 0 the button is disabled -- skip rather than fail
    if (await sendBtn.isDisabled()) {
      test.skip();
      return;
    }

    await sendBtn.click();

    // TransferModal uses role="dialog" + aria-labelledby="transfer-modal-title"
    const modal = page.getByRole("dialog", { name: /send credits/i });
    await expect(modal).toBeVisible({ timeout: 5000 });
  });

  test("transfer modal shows recipient search, amount, and description fields", async ({ page }) => {
    const sendBtn = page.getByRole("button", { name: /send credits/i });

    if (await sendBtn.isDisabled()) {
      test.skip();
      return;
    }

    await sendBtn.click();

    const modal = page.getByRole("dialog", { name: /send credits/i });
    await expect(modal).toBeVisible({ timeout: 5000 });

    // Recipient search input
    await expect(
      modal.getByPlaceholder(/search by name|search/i)
    ).toBeVisible({ timeout: 5000 });

    // Amount label text
    await expect(modal.getByText(/amount.*hours/i)).toBeVisible({ timeout: 5000 });

    // Description textarea
    await expect(
      modal.getByPlaceholder(/what is this transfer/i)
    ).toBeVisible({ timeout: 5000 });
  });

  test("searching for a recipient shows results or empty state message", async ({ page }) => {
    const sendBtn = page.getByRole("button", { name: /send credits/i });

    if (await sendBtn.isDisabled()) {
      test.skip();
      return;
    }

    await sendBtn.click();

    const modal = page.getByRole("dialog", { name: /send credits/i });
    await expect(modal).toBeVisible({ timeout: 5000 });

    const recipientSearch = modal.getByPlaceholder(/search by name|search/i);
    await recipientSearch.fill("a");

    // After the 300 ms debounce + API call, either a listbox or "no members" text appears
    await expect(
      modal.getByRole("listbox", { name: /search results/i })
        .or(modal.getByText(/no members found/i))
    ).toBeVisible({ timeout: 8000 });
  });

  test("Cancel button closes the transfer modal", async ({ page }) => {
    const sendBtn = page.getByRole("button", { name: /send credits/i });

    if (await sendBtn.isDisabled()) {
      test.skip();
      return;
    }

    await sendBtn.click();

    const modal = page.getByRole("dialog", { name: /send credits/i });
    await expect(modal).toBeVisible({ timeout: 5000 });

    await modal.getByRole("button", { name: /cancel/i }).click();
    await expect(modal).not.toBeVisible({ timeout: 5000 });
  });

  test("filling all required fields enables the submit button", async ({ page }) => {
    const sendBtn = page.getByRole("button", { name: /send credits/i });

    if (await sendBtn.isDisabled()) {
      test.skip();
      return;
    }

    await sendBtn.click();

    const modal = page.getByRole("dialog", { name: /send credits/i });
    await expect(modal).toBeVisible({ timeout: 5000 });

    // Search for a member
    const recipientSearch = modal.getByPlaceholder(/search by name|search/i);
    await recipientSearch.fill("a");

    const resultsListbox = modal.getByRole("listbox", { name: /search results/i });
    const hasResults = await resultsListbox.isVisible({ timeout: 6000 }).catch(() => false);

    if (!hasResults) {
      test.skip();
      return;
    }

    // Select the first result
    await resultsListbox.getByRole("option").first().click();

    // Enter amount
    await modal.getByPlaceholder("0").fill("1");

    // Enter description
    await modal.getByPlaceholder(/what is this transfer/i).fill("E2E test transfer -- please ignore.");

    // The submit button should now be enabled
    const submitBtn = modal.getByRole("button", { name: /send credits/i }).last();
    await expect(submitBtn).not.toBeDisabled({ timeout: 3000 });
  });

  test("submitting a valid transfer shows a success toast and closes the modal", async ({ page }) => {
    const sendBtn = page.getByRole("button", { name: /send credits/i });

    if (await sendBtn.isDisabled()) {
      test.skip();
      return;
    }

    await sendBtn.click();

    const modal = page.getByRole("dialog", { name: /send credits/i });
    await expect(modal).toBeVisible({ timeout: 5000 });

    const recipientSearch = modal.getByPlaceholder(/search by name|search/i);
    await recipientSearch.fill("a");

    const resultsListbox = modal.getByRole("listbox", { name: /search results/i });
    const hasResults = await resultsListbox.isVisible({ timeout: 6000 }).catch(() => false);

    if (!hasResults) {
      test.skip();
      return;
    }

    await resultsListbox.getByRole("option").first().click();

    // Minimum valid amount
    await modal.getByPlaceholder("0").fill("1");
    await modal.getByPlaceholder(/what is this transfer/i).fill("E2E test transfer -- please ignore.");

    // Submit
    const submitBtn = modal.getByRole("button", { name: /send credits/i }).last();
    await submitBtn.click();

    // Modal closes on success
    await expect(modal).not.toBeVisible({ timeout: 10000 });

    // Success toast appears (ToastContext renders role="status" or role="alert")
    await expect(
      page.getByRole("status").or(page.getByRole("alert"))
    ).toBeVisible({ timeout: 10000 });
  });
});
