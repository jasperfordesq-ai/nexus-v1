// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Playwright global setup — authenticates once and saves browser storage state.
 *
 * Runs before the test suite. Navigates to the hOUR Timebank login page, signs
 * in with the E2E test account, and persists the session to disk so individual
 * specs can skip the login flow by referencing `e2e/.auth/user.json`.
 *
 * Environment variables (all optional — fall back to safe test-account defaults):
 *   E2E_BASE_URL   – base URL (default: http://localhost:5173)
 *   E2E_EMAIL      – test account email
 *   E2E_PASSWORD   – test account password
 *   E2E_TENANT     – tenant slug (default: hour-timebank)
 */

import { chromium, type FullConfig } from '@playwright/test';
import * as path from 'path';
import * as fs from 'fs';

export const AUTH_FILE = path.join(__dirname, '.auth', 'user.json');
export const TENANT_SLUG = process.env.E2E_TENANT ?? 'hour-timebank';
export const BASE_URL = process.env.E2E_BASE_URL ?? 'http://localhost:5173';

export default async function globalSetup(_config: FullConfig) {
  // Ensure the .auth directory exists
  const authDir = path.join(__dirname, '.auth');
  if (!fs.existsSync(authDir)) {
    fs.mkdirSync(authDir, { recursive: true });
  }

  // Skip if an existing auth file is already fresh enough (< 30 min old)
  if (fs.existsSync(AUTH_FILE)) {
    const ageMs = Date.now() - fs.statSync(AUTH_FILE).mtimeMs;
    if (ageMs < 30 * 60 * 1000) {
      console.log('[global-setup] Reusing existing auth state (< 30 min old)');
      return;
    }
  }

  const email = process.env.E2E_EMAIL ?? 'e2e-test@project-nexus.ie';
  const password = process.env.E2E_PASSWORD ?? 'E2eTestPass123!';

  const browser = await chromium.launch();
  const context = await browser.newContext({ baseURL: BASE_URL });
  const page = await context.newPage();

  // Navigate to the tenant-prefixed login page
  await page.goto(`/t/${TENANT_SLUG}/login`);

  // Wait for the login form to render
  await page.getByLabel('Email').waitFor({ state: 'visible', timeout: 15000 });

  // Fill credentials
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password').fill(password);

  // Submit
  await page.getByRole('button', { name: /sign in|log in/i }).click();

  // Wait for redirect away from login (dashboard or feed)
  await page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 15000 });

  // Persist auth cookies + localStorage
  await context.storageState({ path: AUTH_FILE });

  await browser.close();
  console.log('[global-setup] Auth state saved to', AUTH_FILE);
}
