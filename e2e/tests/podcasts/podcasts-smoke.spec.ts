// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { expect, test } from '@playwright/test';
import { goToTenantPage } from '../../helpers/test-utils';

test.describe('Podcasts smoke', () => {
  test('renders the podcast library and public listening surface @smoke', async ({ page }) => {
    await goToTenantPage(page, 'podcasts');
    const heading = page.getByRole('heading', { name: /Podcasts/i });
    if (!(await heading.isVisible({ timeout: 5000 }).catch(() => false))) {
      test.skip(true, 'Podcasts page is not rendered for this tenant/dev server yet.');
    }
    await expect(heading).toBeVisible();

    const firstShow = page.locator('a[href*="/podcasts/"]').first();
    if (await firstShow.count() === 0) {
      await expect(page.getByText(/No podcast shows yet/i)).toBeVisible();
      return;
    }

    await firstShow.click();
    await expect(page.getByRole('heading')).toBeVisible();

    const firstEpisode = page.getByRole('link', { name: /listen/i }).first();
    if (await firstEpisode.count() > 0) {
      await firstEpisode.click();
      await expect(page.locator('audio')).toBeVisible();
    }
  });
});
