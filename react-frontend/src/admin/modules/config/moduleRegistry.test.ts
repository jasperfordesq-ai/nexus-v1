// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';
import { getFeatureModules } from './moduleRegistry';

describe('module registry podcast module', () => {
  it('registers podcasts with editable member authoring and moderation options', () => {
    const modules = getFeatureModules();
    const podcasts = modules.find(module => module.id === 'podcasts');

    expect(podcasts).toBeDefined();
    expect(podcasts?.stage).toBe('alpha');
    expect(podcasts?.configSource).toBe('podcast_config');
    expect(podcasts?.detailPageUrl).toBe('/admin/podcasts');

    const options = new Map(podcasts?.configOptions.map(option => [option.key, option]));
    expect(options.get('podcasts.allow_member_show_creation')?.defaultValue).toBe(true);
    expect(options.get('podcasts.moderation_enabled')?.defaultValue).toBe(false);
    expect(options.get('podcasts.enable_rss_feed')?.defaultValue).toBe(true);
    expect(options.get('podcasts.enable_transcripts')?.defaultValue).toBe(true);
    expect(options.get('podcasts.enable_chapters')?.defaultValue).toBe(true);
  });

  it('places podcasts after courses and before premium community upsells', () => {
    const ids = getFeatureModules().map(module => module.id);

    expect(ids.indexOf('courses')).toBeLessThan(ids.indexOf('podcasts'));
    expect(ids.indexOf('podcasts')).toBeLessThan(ids.indexOf('member_premium'));
  });
});
