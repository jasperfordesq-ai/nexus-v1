// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

const alphaPages = [
  '/',
  '/hour-timebank/accessible',
  '/hour-timebank/accessible/login',
  '/hour-timebank/accessible/register',
  '/hour-timebank/accessible/contact',
  '/hour-timebank/accessible/feed',
  '/hour-timebank/accessible/listings',
  '/hour-timebank/accessible/messages',
  '/hour-timebank/accessible/events',
  '/hour-timebank/accessible/volunteering',
  '/hour-timebank/accessible/members',
  '/hour-timebank/accessible/about',
  '/hour-timebank/accessible/trust-and-safety',
  '/hour-timebank/accessible/accessibility',
  '/hour-timebank/accessible/legal',
  '/hour-timebank/accessible/legal/terms',
  '/hour-timebank/accessible/legal/privacy',
  '/hour-timebank/accessible/legal/cookies',
  '/hour-timebank/accessible/legal/community-guidelines',
  '/hour-timebank/accessible/legal/acceptable-use',
  '/hour-timebank/accessible/help',
  '/hour-timebank/accessible/kb',
  '/hour-timebank/accessible/blog',
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
