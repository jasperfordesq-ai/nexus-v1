// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { defineConfig } from 'i18next-cli';

const locales = ['ar', 'de', 'en', 'es', 'fr', 'ga', 'it', 'ja', 'nl', 'pl', 'pt'];
const secondaryLanguages = locales.filter((locale) => locale !== 'en');

export default defineConfig({
  locales,
  extract: {
    input: ['src/**/*.{ts,tsx}'],
    output: 'public/locales/{{language}}/{{namespace}}.json',
    ignore: ['src/**/*.test.{ts,tsx}', 'src/test/**', 'dist/**', 'node_modules/**'],
    functions: ['t', '*.t', 'i18n.t'],
    transComponents: ['Trans', 'Translation'],
    useTranslationNames: ['useTranslation'],
    defaultNS: 'common',
    keySeparator: '.',
    nsSeparator: ':',
    sort: true,
    primaryLanguage: 'en',
    secondaryLanguages,
    defaultValue: '',
    removeUnusedKeys: false,
    extractFromComments: false,
    preservePatterns: [
      'tenant_features.label_*',
      'tenant_features.desc_*',
      'config.module_name_*',
      'config.module_desc_*',
      'jobs.status_*',
      'jobs.stage_*',
      'jobs.pipeline_status_*',
      'system.lang_*',
      'federation.scope_*',
      'federation.webhook_*',
      'safeguarding.trigger_*_label',
      'safeguarding.trigger_*_desc',
    ],
  },
  lint: {
    ignoredAttributes: ['data-testid', 'aria-label', 'aria-hidden', 'role'],
    ignoredTags: ['code', 'pre'],
    ignore: ['src/**/*.test.{ts,tsx}', 'src/test/**', 'src/admin/**'],
    checkInterpolationParams: true,
  },
  types: {
    input: ['public/locales/en/*.json'],
    basePath: 'public/locales/en',
    output: 'src/i18next.d.ts',
  },
});
