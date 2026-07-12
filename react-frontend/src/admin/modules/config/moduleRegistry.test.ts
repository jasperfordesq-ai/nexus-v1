// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { getFeatureModules } from './moduleRegistry';

const adminLocale = JSON.parse(
  readFileSync('public/locales/en/admin_config.json', 'utf8')
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
    // Promoted out of alpha (2026-07-03) — absent stage means stable/GA.
    expect(podcasts?.stage).toBeUndefined();
    expect(podcasts?.configSource).toBe('podcast_config');
    expect(podcasts?.detailPageUrl).toBe('/admin/podcasts');

    const options = new Map(podcasts?.configOptions.map(option => [option.key, option]));
    expect(options.get('podcasts.allow_member_show_creation')?.defaultValue).toBe(true);
    expect(options.get('podcasts.moderation_enabled')?.defaultValue).toBe(false);
    expect(options.get('podcasts.enable_rss_feed')?.defaultValue).toBe(true);
    expect(options.get('podcasts.enable_transcripts')?.defaultValue).toBe(true);
    expect(options.get('podcasts.enable_chapters')?.defaultValue).toBe(true);
  });

  it('places podcasts after courses and before donations support', () => {
    const ids = getFeatureModules().map(module => module.id);

    expect(ids.indexOf('courses')).toBeLessThan(ids.indexOf('podcasts'));
    expect(ids.indexOf('podcasts')).toBeLessThan(ids.indexOf('member_premium'));
  });

  it('links the donations support module to its admin detail page', () => {
    const support = getFeatureModules().find(module => module.id === 'member_premium');

    expect(support).toBeDefined();
    expect(support?.name).toBe('Donations & Support');
    expect(support?.description).toBe('One-off and recurring donations with recognition, not paid features.');
    expect(support?.detailPageUrl).toBe('/admin/member-premium');
    expect(support?.configOptions).toEqual([]);
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

describe('module registry authentication modules', () => {
  it('registers lockout-safe two-factor and passkey enrollment controls', () => {
    const modules = getFeatureModules();
    const twoFactor = modules.find(module => module.id === 'two_factor_authentication');
    const passkeys = modules.find(module => module.id === 'biometric_login');

    expect(twoFactor?.configSource).toBe('authentication_config');
    expect(passkeys?.configSource).toBe('authentication_config');

    expect(twoFactor?.configOptions.map(option => option.key)).toEqual([
      'two_factor.allow_trusted_devices',
      'two_factor.trusted_device_days',
      'two_factor.backup_code_count',
    ]);
    expect(passkeys?.configOptions.map(option => option.key)).toEqual([
      'passkeys.enrollment_enabled',
      'passkeys.conditional_autofill',
      'passkeys.max_credentials_per_user',
    ]);
  });

  it('has translated names, descriptions, categories, and option copy', () => {
    const config = adminLocale.config;

    for (const moduleId of ['two_factor_authentication', 'biometric_login']) {
      const module = getFeatureModules().find(candidate => candidate.id === moduleId);
      expect(module).toBeDefined();
      expect(config[`module_name_${moduleId}`]).toBeTypeOf('string');
      expect(config[`module_desc_${moduleId}`]).toBeTypeOf('string');

      for (const option of module?.configOptions ?? []) {
        const token = optionToken(option.key);
        expect(config[`option_${token}_label`]).toBeTypeOf('string');
        expect(config[`option_${token}_desc`]).toBeTypeOf('string');
        expect(config[`option_category_${slugConfigText(option.category)}`]).toBeTypeOf('string');
      }
    }
  });
});
