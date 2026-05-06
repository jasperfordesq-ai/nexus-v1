// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { FullConfig } from '@playwright/test';

/**
 * Global Teardown for E2E Tests
 *
 * This script runs once after all tests to:
 * 1. Clean up test data
 * 2. Reset any modified state
 * 3. Generate final reports
 */

async function globalTeardown(config: FullConfig) {
  console.log('\n🧹 Running E2E Global Teardown...\n');

  // Add any cleanup logic here
  // For example:
  // - Delete test users created during tests
  // - Reset database state
  // - Clear temporary files

  console.log('✅ Global teardown complete!\n');
}

export default globalTeardown;
