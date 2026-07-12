// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { defineConfig, devices } from '@playwright/test';
import baseConfig from './playwright.config';

function assertIsolatedFixtureTarget(): void {
  const frontendBaseUrl = process.env.E2E_BASE_URL || 'http://localhost:5173';
  const apiBaseUrl = process.env.E2E_API_URL || frontendBaseUrl || 'http://localhost:8090';

  for (const [label, rawUrl] of [['frontend', frontendBaseUrl], ['API', apiBaseUrl]] as const) {
    const hostname = new URL(rawUrl).hostname.toLowerCase();
    if (hostname === 'project-nexus.ie' || hostname.endsWith('.project-nexus.ie')) {
      throw new Error(
        `Events enterprise E2E refuses the production ${label} host: ${hostname}`,
      );
    }

    const isLoopback = hostname === 'localhost' || hostname === '127.0.0.1' || hostname === '::1';
    if (!isLoopback && process.env.E2E_EVENTS_ALLOW_REMOTE_FIXTURES !== '1') {
      throw new Error(
        `Events enterprise E2E fixture target ${hostname} is not local. `
        + 'Set E2E_EVENTS_ALLOW_REMOTE_FIXTURES=1 only for an isolated non-production environment.',
      );
    }
  }
}

// Fail before Playwright global setup can authenticate, accept legal terms, or
// perform any other setup mutation against an unsafe host.
assertIsolatedFixtureTarget();

/**
 * Explicit, destructive Events lifecycle project.
 *
 * This config is intentionally separate from the broad browser matrix. The
 * journey creates, publishes, mutates, cancels and archives real event rows,
 * so callers must opt in with `npm run test:events:e2e:enterprise` and target
 * an isolated fixture environment.
 */
export default defineConfig(baseConfig, {
  fullyParallel: false,
  workers: 1,
  projects: [
    {
      name: 'events-enterprise',
      testMatch: '**/events/enterprise-journey.spec.ts',
      use: {
        ...devices['Desktop Chrome'],
        storageState: 'e2e/fixtures/.auth/user.json',
      },
    },
  ],
});
