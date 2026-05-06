// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests',
  testMatch: '**/accessible-frontend-a11y.spec.ts',
  timeout: 30000,
  reporter: [['list']],
  use: {
    baseURL: process.env.E2E_ALPHA_BASE_URL || 'http://127.0.0.1:8088',
    ignoreHTTPSErrors: true,
  },
  projects: [
    {
      name: 'chromium-alpha',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
