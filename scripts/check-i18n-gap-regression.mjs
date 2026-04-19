// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const LOCALES_DIR = path.resolve(__dirname, '../react-frontend/public/locales');
const BASELINE_PATH = path.resolve(__dirname, '../.github/i18n-gap-baseline.json');

const SUPPORTED_LANGUAGES = ['de', 'fr', 'it', 'pt', 'es', 'nl', 'pl', 'ja', 'ar', 'ga'];

const SKIP_NAMESPACES = new Set([
  'admin.json',
  'admin_dashboard.json',
  'admin_nav.json',
  'admin.php',
  'admin_dashboard.php',
  'admin_nav.php',
  'api.php',
  'api_controllers_1.json',
  'api_controllers_2.json',
  'api_controllers_3.json',
  'super_admin.json',
]);

const NO_TRANSLATE_PATTERNS = [
  /^https?:\/\//,
  /^\{\{.*\}\}$/,
  /^[a-zA-Z0-9_]+$/,
  /^\d+$/,
  /^[A-Z_]+$/,
];

function flattenKeys(obj, prefix = '') {
  const result = {};

  for (const [key, value] of Object.entries(obj)) {
    const nextKey = prefix ? `${prefix}.${key}` : key;
    if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
      Object.assign(result, flattenKeys(value, nextKey));
    } else {
      result[nextKey] = value;
    }
  }

  return result;
}

function shouldSkipValue(value) {
  if (typeof value !== 'string') return true;
  if (!value.trim()) return true;
  return NO_TRANSLATE_PATTERNS.some((pattern) => pattern.test(value.trim()));
}

function readJson(filePath) {
  return JSON.parse(fs.readFileSync(filePath, 'utf8'));
}

function buildSnapshot() {
  const enDir = path.join(LOCALES_DIR, 'en');
  const enFiles = fs.readdirSync(enDir).filter((file) => file.endsWith('.json')).sort();
  const files = {};
  let totalGaps = 0;

  for (const lang of SUPPORTED_LANGUAGES) {
    const langDir = path.join(LOCALES_DIR, lang);

    for (const file of enFiles) {
      if (SKIP_NAMESPACES.has(file)) continue;

      const enPath = path.join(enDir, file);
      const langPath = path.join(langDir, file);
      const enData = readJson(enPath);
      const langData = fs.existsSync(langPath) ? readJson(langPath) : {};
      const enFlat = flattenKeys(enData);
      const langFlat = flattenKeys(langData);

      let gapCount = 0;

      for (const [key, enValue] of Object.entries(enFlat)) {
        if (typeof enValue !== 'string') continue;
        if (shouldSkipValue(enValue)) continue;

        const langValue = langFlat[key];
        if (langValue === undefined || langValue === enValue) {
          gapCount += 1;
        }
      }

      if (gapCount > 0) {
        files[`${lang}/${file}`] = gapCount;
        totalGaps += gapCount;
      }
    }
  }

  return {
    generatedAt: new Date().toISOString(),
    source: 'scripts/check-i18n-gap-regression.mjs',
    localesDir: 'react-frontend/public/locales',
    languages: SUPPORTED_LANGUAGES,
    totalGaps,
    files,
  };
}

function compareAgainstBaseline(current, baseline) {
  const regressions = [];
  const baselineFiles = baseline.files ?? {};
  const allFiles = new Set([...Object.keys(baselineFiles), ...Object.keys(current.files)]);

  for (const file of allFiles) {
    const baselineCount = baselineFiles[file] ?? 0;
    const currentCount = current.files[file] ?? 0;

    if (currentCount > baselineCount) {
      regressions.push({
        file,
        baseline: baselineCount,
        current: currentCount,
        delta: currentCount - baselineCount,
      });
    }
  }

  regressions.sort((a, b) => b.delta - a.delta || a.file.localeCompare(b.file));
  return regressions;
}

const args = process.argv.slice(2);
const shouldWriteBaseline = args.includes('--write-baseline');
const currentSnapshot = buildSnapshot();

if (shouldWriteBaseline) {
  fs.writeFileSync(BASELINE_PATH, `${JSON.stringify(currentSnapshot, null, 2)}\n`);
  console.log('✅ Wrote i18n gap baseline.');
  console.log(`   File: ${path.relative(path.resolve(__dirname, '..'), BASELINE_PATH)}`);
  console.log(`   Total non-admin gaps: ${currentSnapshot.totalGaps}`);
  process.exit(0);
}

if (!fs.existsSync(BASELINE_PATH)) {
  console.error('❌ Missing .github/i18n-gap-baseline.json');
  console.error('   Run: node scripts/check-i18n-gap-regression.mjs --write-baseline');
  process.exit(1);
}

const baselineSnapshot = readJson(BASELINE_PATH);
const regressions = compareAgainstBaseline(currentSnapshot, baselineSnapshot);

if (regressions.length === 0) {
  const improvement = baselineSnapshot.totalGaps - currentSnapshot.totalGaps;
  console.log(`✅ Non-admin i18n gap count did not regress (${currentSnapshot.totalGaps} current vs ${baselineSnapshot.totalGaps} baseline)`);
  if (improvement > 0) {
    console.log(`   Improvement: ${improvement} fewer untranslated or English-fallback strings.`);
  }
  process.exit(0);
}

console.error('❌ Non-admin i18n gap regression detected.');
console.error(`   Current total: ${currentSnapshot.totalGaps}`);
console.error(`   Baseline total: ${baselineSnapshot.totalGaps}`);
console.error('');
console.error('Files that regressed:');

for (const regression of regressions.slice(0, 25)) {
  console.error(`   ${regression.file}: ${regression.baseline} → ${regression.current} (+${regression.delta})`);
}

if (regressions.length > 25) {
  console.error(`   ...and ${regressions.length - 25} more file(s)`);
}

console.error('');
console.error('If this increase is intentional, review the locale changes and refresh the baseline:');
console.error('   node scripts/check-i18n-gap-regression.mjs --write-baseline');
process.exit(1);
