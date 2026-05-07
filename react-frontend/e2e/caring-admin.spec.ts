// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { expect, test } from '@playwright/test';
import { AUTH_FILE, TENANT_SLUG } from './global-setup';

test.use({ storageState: AUTH_FILE });

test.describe('Dedicated Caring admin panel', () => {
  test('legacy Caring admin URLs redirect to /caring without horizontal page overflow', async ({ page }) => {
    await page.goto(`/t/${TENANT_SLUG}/admin/caring-community/loyalty`);
    await expect(page).toHaveURL(new RegExp(`/t/${TENANT_SLUG}/caring/loyalty$`));

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
    expect(overflow).toBe(false);
  });
});
