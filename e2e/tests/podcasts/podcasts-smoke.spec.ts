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
      // The audio element is owned by the global player context (hidden);
      // the page surface is the explicit Play control.
      await expect(page.getByRole('button', { name: /^Play$/i }).first()).toBeVisible();
      await expect(page.locator('audio[data-podcast-player]')).toBeAttached();
    }
  });

  test('mini-player persists playback across navigation', async ({ page }) => {
    await goToTenantPage(page, 'podcasts');
    const heading = page.getByRole('heading', { name: /Podcasts/i });
    if (!(await heading.isVisible({ timeout: 5000 }).catch(() => false))) {
      test.skip(true, 'Podcasts page is not rendered for this tenant/dev server yet.');
    }

    const firstShow = page.locator('a[href*="/podcasts/"]').first();
    if (await firstShow.count() === 0) {
      test.skip(true, 'No podcast shows seeded for this tenant.');
    }
    await firstShow.click();

    const firstEpisode = page.getByRole('link', { name: /listen/i }).first();
    if (await firstEpisode.count() === 0) {
      test.skip(true, 'No episodes seeded for this show.');
    }
    await firstEpisode.click();

    const playButton = page.getByRole('button', { name: /^Play$/i }).first();
    if (!(await playButton.isVisible({ timeout: 5000 }).catch(() => false))) {
      test.skip(true, 'Player controls not rendered (episode may lack audio).');
    }
    await playButton.click();

    const audio = page.locator('audio[data-podcast-player]');
    await expect(audio).toHaveAttribute('src', /.+/);
    const srcBefore = await audio.getAttribute('src');

    // Navigate away — the docked mini-player stays and the source is untouched.
    await goToTenantPage(page, 'podcasts');
    const miniPlayer = page.locator('[data-podcast-miniplayer]');
    await expect(miniPlayer).toBeVisible();
    await expect(audio).toHaveAttribute('src', srcBefore ?? '');

    // Dismissing the mini-player clears the dock.
    await miniPlayer.getByRole('button', { name: /Close player/i }).click();
    await expect(miniPlayer).not.toBeVisible();
  });
});
