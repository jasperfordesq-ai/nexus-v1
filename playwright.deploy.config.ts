// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { defineConfig, devices } from '@playwright/test';

/**
 * Deploy-gate Playwright config — the "act like a real user" safety check.
 *
 * Runs the proven @smoke journeys (e2e/tests/smoke.spec.ts) against a freshly
 * built blue/green CANDIDATE on the server, BEFORE traffic is switched to it.
 * Invoked by scripts/deploy/phases/candidate-journeys.sh inside the
 * nexus-e2e-runner container; base URLs + credentials are injected via env.
 *
 * Why this differs from e2e/playwright.config.ts:
 *  - NO globalSetup / globalTeardown. The candidate shares the PRODUCTION
 *    database, so the gate must NEVER seed or write fixtures. The @smoke suite
 *    is read-only + login only, so it is safe to run against the live DB.
 *  - Single Chromium project; each test self-authenticates via the candidate
 *    API (primeApiAuth) — no shared storage-state files required.
 *  - All outputs go to /tmp so the e2e/ tree can be mounted read-only.
 *
 * Known v1 limitation: page-load tests exercise the candidate BUNDLE, but the
 * SPA's own runtime data calls use the app's configured absolute API URL (the
 * live colour). Direct API assertions (login/bootstrap/categories) DO hit the
 * candidate API. A v2 enhancement can pin SPA traffic to the candidate via
 * Playwright request routing.
 */
export default defineConfig({
  testDir: './e2e/tests',
  testMatch: 'smoke.spec.ts',
  grep: /@smoke/,

  timeout: 60_000,
  expect: { timeout: 10_000 },

  fullyParallel: false,
  forbidOnly: true,
  retries: 2,
  workers: 1,

  // Writable locations so the e2e/ source tree can be mounted read-only.
  outputDir: '/tmp/pw-deploy-gate',
  reporter: [['list']],

  use: {
    baseURL: process.env.E2E_BASE_URL || 'http://127.0.0.1:3000',
    extraHTTPHeaders: {
      'X-Test-Tenant': process.env.E2E_TENANT || 'hour-timebank',
    },
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    ignoreHTTPSErrors: true,
  },

  projects: [
    {
      name: 'deploy-gate',
      use: {
        ...devices['Desktop Chrome'],
        // Fresh browser; authenticated tests log in against the candidate API.
        storageState: { cookies: [], origins: [] },
      },
    },
  ],
});
