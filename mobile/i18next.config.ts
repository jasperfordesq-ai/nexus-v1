// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

export default {
  locales: ['de', 'en', 'es', 'fr', 'ga', 'it', 'pt'],
  extract: {
    input: ['app/**/*.{ts,tsx}', 'components/**/*.{ts,tsx}', 'lib/**/*.{ts,tsx}'],
    output: 'locales/{{language}}/{{namespace}}.json',
    ignore: ['**/*.test.{ts,tsx}', '__mocks__/**', 'node_modules/**'],
    functions: ['t', '*.t', 'i18n.t'],
    transComponents: ['Trans', 'Translation'],
    useTranslationNames: ['useTranslation'],
    defaultNS: 'common',
    keySeparator: '.',
    nsSeparator: ':',
    primaryLanguage: 'en',
    secondaryLanguages: ['de', 'es', 'fr', 'ga', 'it', 'pt'],
    defaultValue: '',
    removeUnusedKeys: false,
    extractFromComments: false,
  },
  lint: {
    ignoredAttributes: ['testID', 'accessibilityRole', 'accessibilityState'],
    ignoredTags: ['Code'],
    ignore: ['**/*.test.{ts,tsx}', '__mocks__/**', 'node_modules/**'],
    checkInterpolationParams: true,
  },
};
