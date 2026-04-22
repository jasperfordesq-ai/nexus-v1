// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * check-i18n-drift.mjs — Detect translation key mismatches across languages.
 *
 * Compares all non-English translation files against the English source and
 * reports missing keys, extra keys, and missing namespace files.
 *
 * Exit code 0 = all languages match English.
 * Exit code 1 = mismatches found (blocks CI).
 *
 * Usage:
 *   node scripts/check-i18n-drift.mjs            # Full check
 *   node scripts/check-i18n-drift.mjs --summary   # Counts only, no key details
 */

import { readdirSync, readFileSync, statSync, existsSync } from 'fs';
import { join } from 'path';

const LOCALES_DIR = join(process.cwd(), 'react-frontend', 'public', 'locales');
const SUMMARY_ONLY = process.argv.includes('--summary');

// ─── Helpers ─────────────────────────────────────────────────

function flattenKeys(obj, prefix = '') {
  const keys = [];
  for (const [key, val] of Object.entries(obj)) {
    const fullKey = prefix ? `${prefix}.${key}` : key;
    if (typeof val === 'object' && val !== null && !Array.isArray(val)) {
      keys.push(...flattenKeys(val, fullKey));
    } else {
      keys.push(fullKey);
    }
  }
  return keys;
}

// ─── Main ────────────────────────────────────────────────────

if (!existsSync(LOCALES_DIR)) {
  console.error(`Locales directory not found: ${LOCALES_DIR}`);
  process.exit(1);
}

const langs = readdirSync(LOCALES_DIR)
  .filter(d => statSync(join(LOCALES_DIR, d)).isDirectory())
  .sort();

const enDir = join(LOCALES_DIR, 'en');
if (!existsSync(enDir)) {
  console.error('English (en) locale directory not found');
  process.exit(1);
}

// Admin locale files are EXCLUDED from drift checks. Admin panel is English-only
// by design — other-language admin files will be deleted. See memory/feedback_admin_english_only.md.
const ADMIN_LOCALE_FILES = new Set([
  'admin.json',
  'admin_nav.json',
  'admin_dashboard.json',
  'super_admin.json',
]);

const enFiles = readdirSync(enDir)
  .filter(f => f.endsWith('.json') && !ADMIN_LOCALE_FILES.has(f))
  .sort();
const nonEnLangs = langs.filter(l => l !== 'en');

console.log('============================================================');
console.log('  i18n Translation Drift Check');
console.log('============================================================');
console.log(`  English namespaces: ${enFiles.length}`);
console.log(`  Languages: ${langs.join(', ')}`);
console.log('');

let totalMissing = 0;
let totalExtra = 0;
let totalMissingFiles = 0;
let totalFilesChecked = 0;

for (const file of enFiles) {
  const enContent = JSON.parse(readFileSync(join(enDir, file), 'utf8'));
  const enKeys = new Set(flattenKeys(enContent));

  for (const lang of nonEnLangs) {
    const langFile = join(LOCALES_DIR, lang, file);
    totalFilesChecked++;

    if (!existsSync(langFile)) {
      console.log(`[MISSING FILE] ${lang}/${file} — entire namespace missing`);
      totalMissingFiles++;
      totalMissing += enKeys.size;
      continue;
    }

    const langContent = JSON.parse(readFileSync(langFile, 'utf8'));
    const langKeys = new Set(flattenKeys(langContent));

    const missing = [...enKeys].filter(k => !langKeys.has(k));
    const extra = [...langKeys].filter(k => !enKeys.has(k));

    if (missing.length > 0) {
      totalMissing += missing.length;
      console.log(`[MISSING KEYS] ${lang}/${file} — ${missing.length} key(s) not translated`);
      if (!SUMMARY_ONLY) {
        missing.forEach(k => console.log(`    - ${k}`));
      }
    }

    if (extra.length > 0) {
      totalExtra += extra.length;
      console.log(`[EXTRA KEYS]   ${lang}/${file} — ${extra.length} key(s) not in English source`);
      if (!SUMMARY_ONLY) {
        extra.forEach(k => console.log(`    + ${k}`));
      }
    }
  }
}

console.log('');
console.log('============================================================');
console.log(`  Files checked: ${totalFilesChecked}`);
console.log(`  Missing files: ${totalMissingFiles}`);
console.log(`  Missing keys:  ${totalMissing}`);
console.log(`  Extra keys:    ${totalExtra}`);
console.log('============================================================');

if (totalMissing > 0 || totalMissingFiles > 0) {
  console.log('');
  console.log('FAIL: Translation drift detected. Add missing keys/files before merging.');
  console.log('  Run: node scripts/check-i18n-drift.mjs');
  console.log('  Fix: Add translations for all missing keys to each language file.');
  process.exit(1);
} else if (totalExtra > 0) {
  console.log('');
  console.log('WARN: Extra keys found (not in English source). These may be stale — review and remove if unused.');
  process.exit(0);
} else {
  console.log('');
  console.log('PASS: All translations match English source. No drift detected.');
  process.exit(0);
}
