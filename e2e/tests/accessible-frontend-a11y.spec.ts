// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

const alphaPages = [
  '/',
  '/hour-timebank/alpha',
  '/hour-timebank/alpha/login',
  '/hour-timebank/alpha/register',
  '/hour-timebank/alpha/feed',
  '/hour-timebank/alpha/listings',
  '/hour-timebank/alpha/members',
];

for (const path of alphaPages) {
  test(`Accessible frontend smoke: ${path}`, async ({ page }) => {
    await page.goto(path);

    await expect(page.locator('main#main-content')).toBeVisible();
    await expect(page.locator('h1')).toBeVisible();
    await expect(page.locator('.govuk-skip-link')).toHaveAttribute('href', '#main-content');
    await expect(page.locator('.govuk-phase-banner')).toBeVisible();

    const results = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa'])
      .analyze();

    const serious = results.violations.filter((violation) =>
      violation.impact === 'serious' || violation.impact === 'critical',
    );
    expect(serious).toEqual([]);
  });
}
