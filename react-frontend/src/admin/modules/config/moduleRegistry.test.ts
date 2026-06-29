// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { getFeatureModules } from './moduleRegistry';

const adminLocale = JSON.parse(
  readFileSync('public/locales/en/admin.json', 'utf8')
) as { config: Record<string, string> };

function slugConfigText(value: string): string {
  return value
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '') || 'value';
}

function optionToken(optionKey: string): string {
  return optionKey.replace(/[^a-zA-Z0-9]+/g, '_').replace(/^_+|_+$/g, '');
}

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

  it('has admin translations for every podcast configuration control', () => {
    const podcasts = getFeatureModules().find(module => module.id === 'podcasts');
    expect(podcasts).toBeDefined();

    const config = adminLocale.config;
    for (const option of podcasts?.configOptions ?? []) {
      const token = optionToken(option.key);
      expect(config[`option_${token}_label`], `${option.key} label`).toBeTypeOf('string');
      expect(config[`option_${token}_desc`], `${option.key} description`).toBeTypeOf('string');
      expect(config[`option_category_${slugConfigText(option.category)}`], `${option.category} category`).toBeTypeOf('string');

      for (const choice of option.choices ?? []) {
        expect(
          config[`option_choice_${token}_${slugConfigText(choice.value)}`],
          `${option.key} choice ${choice.value}`,
        ).toBeTypeOf('string');
      }
    }
  });
});

describe('module registry newsletter module', () => {
  it('registers newsletter as a tenant feature with its admin surface', () => {
    const newsletter = getFeatureModules().find(module => module.id === 'newsletter');

    expect(newsletter).toBeDefined();
    expect(newsletter?.configSource).toBe('tenant_features');
    expect(newsletter?.detailPageUrl).toBe('/admin/newsletters');
  });
});
