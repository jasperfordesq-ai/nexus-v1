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
 * SPA traffic pinning (was the v1 limitation): the production bundle hard-codes
 * the absolute live API origin, so loading the candidate frontend from
 * 127.0.0.1 would send the SPA's own bootstrap/data fetches cross-origin to the
 * LIVE colour — and they are CORS-blocked from a 127.0.0.1 origin, leaving the
 * SPA stuck on "Loading community". pinSpaApiToCandidate() (e2e/helpers/
 * test-utils.ts), wired into smoke.spec.ts's beforeEach, intercepts those calls
 * and proxies them to the candidate API (E2E_API_URL, reachable via the
 * runner's --network host), fulfilling with CORS headers. The gate therefore
 * now exercises the candidate's own frontend AND API end-to-end. Direct API
 * assertions (login/bootstrap/categories) already hit the candidate API.
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
