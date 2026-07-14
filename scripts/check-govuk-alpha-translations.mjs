// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Detect untranslated English-copy values in the accessible frontend PHP
 * translations. Key parity alone is not enough for govuk_alpha.php because the
 * alpha UI is fully user-facing.
 */

import { existsSync } from 'fs';
import { join } from 'path';
import { loadPhpArray } from './lib/load-php-array.mjs';

const LANG_DIR = join(process.cwd(), 'lang');
const SOURCE_LOCALE = 'en';
const TARGET_LOCALES = ['ga', 'de', 'fr', 'it', 'pt', 'es', 'nl', 'pl', 'ja', 'ar'];

const ALWAYS_ALLOWED_KEYS = new Set([
  'feedback_url',
  'service_name',
  'profile_settings.languages.en',
  'profile_settings.languages.ga',
  'profile_settings.languages.de',
  'profile_settings.languages.fr',
  'profile_settings.languages.it',
  'profile_settings.languages.pt',
  'profile_settings.languages.es',
  'profile_settings.languages.nl',
  'profile_settings.languages.pl',
  'profile_settings.languages.ja',
  'profile_settings.languages.ar',
]);

const LEGAL_OR_BRAND_TOKENS = new Set([
  'AGPL-3.0-or-later',
  'NOTICE',
  'Project NEXUS',
  'Jasper Ford',
  'hOUR Timebank CLG',
  'WCAG 2.2 Level AA',
]);

function flatten(value, prefix = '', out = new Map()) {
  if (Array.isArray(value)) {
    value.forEach((item, index) => flatten(item, prefix ? `${prefix}.${index}` : String(index), out));
    return out;
  }

  if (value !== null && typeof value === 'object') {
    for (const [key, item] of Object.entries(value)) {
      flatten(item, prefix ? `${prefix}.${key}` : key, out);
    }
    return out;
  }

  out.set(prefix, String(value));
  return out;
}

function isPlaceholderOnly(value) {
  return /^(:[A-Za-z_][A-Za-z0-9_]*)(\s+:[A-Za-z_][A-Za-z0-9_]*)*$/.test(value.trim());
}

function isUrlOrEmail(value) {
  return /^(https?:\/\/|mailto:)/i.test(value) || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
}

function isNumberOrSymbolOnly(value) {
  return value.trim() !== '' && !/[\p{L}]/u.test(value);
}

function isLegalOrBrandOnly(value) {
  return LEGAL_OR_BRAND_TOKENS.has(value.trim());
}

function isAcronymOnly(value) {
  const stripped = value
    .replace(/:[A-Za-z_][A-Za-z0-9_]*/g, '')
    .replace(/[0-9\s.,:;!?()[\]{}%/*+\-–—]/g, '')
    .trim();

  return stripped !== '' && /^[A-Z]{2,}$/.test(stripped);
}

function isAllowedSameAsEnglish(key, value) {
  return value === ''
    || ALWAYS_ALLOWED_KEYS.has(key)
    || isPlaceholderOnly(value)
    || isUrlOrEmail(value)
    || isNumberOrSymbolOnly(value)
    || isLegalOrBrandOnly(value)
    || isAcronymOnly(value);
}

const sourceFile = join(LANG_DIR, SOURCE_LOCALE, 'govuk_alpha.php');
if (!existsSync(sourceFile)) {
  console.error(`Missing source translation file: ${sourceFile}`);
  process.exit(1);
}

const source = flatten(loadPhpArray(sourceFile));
const failures = [];
const allowed = [];

for (const locale of TARGET_LOCALES) {
  const file = join(LANG_DIR, locale, 'govuk_alpha.php');
  if (!existsSync(file)) {
    failures.push({ locale, key: '(file)', value: 'missing govuk_alpha.php' });
    continue;
  }

  const translated = flatten(loadPhpArray(file));
  for (const [key, englishValue] of source.entries()) {
    const translatedValue = translated.get(key);
    if (translatedValue === undefined || translatedValue !== englishValue) {
      continue;
    }

    if (isAllowedSameAsEnglish(key, englishValue)) {
      allowed.push({ locale, key, value: englishValue });
      continue;
    }

    failures.push({ locale, key, value: englishValue });
  }
}

console.log('============================================================');
console.log('  GOV.UK Alpha Same-As-English Translation Check');
console.log('============================================================');
console.log(`  Source keys:       ${source.size}`);
console.log(`  Locales checked:   ${TARGET_LOCALES.join(', ')}`);
console.log(`  Allowed unchanged: ${allowed.length}`);
console.log(`  Untranslated:      ${failures.length}`);

if (failures.length > 0) {
  console.error('');
  console.error('FAIL: govuk_alpha.php still contains English-copy values.');
  for (const item of failures.slice(0, 200)) {
    console.error(`  ${item.locale}: ${item.key} = ${JSON.stringify(item.value)}`);
  }
  if (failures.length > 200) {
    console.error(`  ...and ${failures.length - 200} more`);
  }
  process.exit(1);
}

console.log('');
console.log('PASS: govuk_alpha.php values are translated or intentionally allowlisted.');
