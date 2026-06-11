// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * check-php-lang-parity.mjs — Detect translation key mismatches in PHP lang files.
 *
 * Compares every non-English lang/{locale}/*.php file against the English
 * source (lang/en/*.php) and reports missing keys, extra keys, and missing
 * namespace files. This is the PHP-side counterpart of check-i18n-drift.mjs
 * (which only covers react-frontend JSON locales) — without it, new lang/en
 * keys can sit untranslated for weeks (e.g. the 52 api.php keys of 2026-05-27).
 *
 * PHP files are parsed by shelling out to `php -r "echo json_encode(require ...)"`,
 * which is far more robust than regex-parsing PHP array syntax.
 *
 * Exit code 0 = all locales match English key sets exactly.
 * Exit code 1 = mismatches found (blocks CI).
 *
 * Usage:
 *   node scripts/check-php-lang-parity.mjs            # Full check
 *   node scripts/check-php-lang-parity.mjs --summary  # Counts only, no key details
 */

import { readdirSync, statSync, existsSync } from 'fs';
import { join } from 'path';
import { execFileSync } from 'child_process';

const LANG_DIR = join(process.cwd(), 'lang');
const SUMMARY_ONLY = process.argv.includes('--summary');

// ─── Helpers ─────────────────────────────────────────────────

function loadPhpLangFile(file) {
  // `require` the PHP array file and emit it as JSON. The file path is passed
  // as a script argument (not interpolated) so quoting is shell-safe.
  const out = execFileSync(
    'php',
    ['-d', 'display_errors=stderr', '-r', 'echo json_encode(require $argv[1]);', file],
    { encoding: 'utf8', maxBuffer: 32 * 1024 * 1024 }
  );
  const parsed = JSON.parse(out);
  if (parsed === null || typeof parsed !== 'object') {
    throw new Error(`${file} did not return a PHP array`);
  }
  return parsed;
}

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

if (!existsSync(LANG_DIR)) {
  console.error(`Lang directory not found: ${LANG_DIR}`);
  process.exit(1);
}

try {
  execFileSync('php', ['--version'], { stdio: 'ignore' });
} catch {
  console.error('PHP CLI not found on PATH — required to parse lang/*.php files.');
  process.exit(1);
}

const langs = readdirSync(LANG_DIR)
  .filter(d => statSync(join(LANG_DIR, d)).isDirectory())
  .sort();

const enDir = join(LANG_DIR, 'en');
if (!existsSync(enDir)) {
  console.error('English (en) lang directory not found');
  process.exit(1);
}

const enFiles = readdirSync(enDir)
  .filter(f => f.endsWith('.php'))
  .sort();
const nonEnLangs = langs.filter(l => l !== 'en');

console.log('============================================================');
console.log('  PHP lang/ Translation Parity Check');
console.log('============================================================');
console.log(`  English namespaces: ${enFiles.length}`);
console.log(`  Languages: ${langs.join(', ')}`);
console.log('');

let totalMissing = 0;
let totalExtra = 0;
let totalMissingFiles = 0;
let totalFilesChecked = 0;

for (const file of enFiles) {
  const enContent = loadPhpLangFile(join(enDir, file));
  const enKeys = new Set(flattenKeys(enContent));

  for (const lang of nonEnLangs) {
    const langFile = join(LANG_DIR, lang, file);
    totalFilesChecked++;

    if (!existsSync(langFile)) {
      console.log(`[MISSING FILE] ${lang}/${file} — entire namespace missing`);
      totalMissingFiles++;
      totalMissing += enKeys.size;
      continue;
    }

    const langContent = loadPhpLangFile(langFile);
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

if (totalMissing > 0 || totalMissingFiles > 0 || totalExtra > 0) {
  console.log('');
  console.log('FAIL: PHP lang parity drift detected. Fix key sets before merging.');
  console.log('  Run: node scripts/check-php-lang-parity.mjs');
  console.log('  Fix: Add real translations for every missing key to each lang/{locale}/*.php');
  console.log('       (and remove keys that no longer exist in lang/en).');
  process.exit(1);
} else {
  console.log('');
  console.log('PASS: All PHP lang files match the English key sets. No drift detected.');
  process.exit(0);
}
