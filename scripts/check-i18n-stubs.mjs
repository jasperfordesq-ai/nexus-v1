// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * check-i18n-stubs.mjs — Detect stub placeholder translation values.
 *
 * A "stub" is a translation value that was mechanically generated from its
 * key name — e.g. `"update_btn": "Update Btn"`, `"no_jobs_hint_empty": "No
 * jobs hint empty"`. These pass normal i18n checks because the key exists,
 * but they render garbage in the UI.
 *
 * This script detects values where:
 *   value.toLowerCase().replace(/\s+/g, ' ').trim() ===
 *   key.replace(/_/g, ' ').toLowerCase()
 *
 * AND the key ends in a telltale technical suffix (_msg, _btn, _aria,
 * _lbl, _hint, _desc, _tooltip, _placeholder, _title). Those suffixes
 * should NEVER appear in rendered UI text — if the value still contains
 * them, it's a stub.
 *
 * Uses a baseline at .github/i18n-stub-baseline.json so that existing
 * stubs don't block every commit — only NEW stubs introduced by a change
 * cause a failure. The baseline count can only go down over time.
 *
 * Usage:
 *   node scripts/check-i18n-stubs.mjs              # check against baseline
 *   node scripts/check-i18n-stubs.mjs --list       # list all current stubs
 *   node scripts/check-i18n-stubs.mjs --baseline   # regenerate baseline (use after fixes)
 *
 * Exit code 0 = stub count <= baseline
 * Exit code 1 = stub count > baseline (regression)
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const LOCALES_DIR = path.resolve(__dirname, '../react-frontend/public/locales');
const BASELINE_PATH = path.resolve(__dirname, '../.github/i18n-stub-baseline.json');

const TECHNICAL_SUFFIXES = [
  '_msg', '_btn', '_aria', '_lbl', '_hint', '_tooltip',
  '_placeholder', '_desc',
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

function isStub(flatKey, value) {
  if (typeof value !== 'string') return false;
  const leaf = flatKey.split('.').pop();
  if (!leaf) return false;

  const hasTechSuffix = TECHNICAL_SUFFIXES.some((s) => leaf.endsWith(s));
  if (!hasTechSuffix) return false;

  const keyAsWords = leaf.replace(/_/g, ' ').toLowerCase();
  const valNormalized = value.toLowerCase().replace(/\s+/g, ' ').trim();
  return valNormalized === keyAsWords;
}

// Admin locale files are excluded — admin panel is English-only by design.
// See memory/feedback_admin_english_only.md (2026-04-22).
const ADMIN_LOCALE_FILES = new Set([
  'admin.json', 'admin_nav.json', 'admin_dashboard.json', 'super_admin.json',
]);

function collectStubs() {
  const stubs = [];
  const langs = fs.readdirSync(LOCALES_DIR).filter((d) => {
    return fs.statSync(path.join(LOCALES_DIR, d)).isDirectory();
  });

  for (const lang of langs) {
    const langDir = path.join(LOCALES_DIR, lang);
    const files = fs.readdirSync(langDir).filter(
      (f) => f.endsWith('.json') && !ADMIN_LOCALE_FILES.has(f),
    );
    for (const file of files) {
      const data = JSON.parse(fs.readFileSync(path.join(langDir, file), 'utf8'));
      const flat = flattenKeys(data);
      for (const [k, v] of Object.entries(flat)) {
        if (isStub(k, v)) {
          stubs.push({ lang, file, key: k, value: v });
        }
      }
    }
  }
  return stubs;
}

function loadBaseline() {
  if (!fs.existsSync(BASELINE_PATH)) return { count: 0 };
  return JSON.parse(fs.readFileSync(BASELINE_PATH, 'utf8'));
}

const args = process.argv.slice(2);
const listMode = args.includes('--list');
const baselineMode = args.includes('--baseline');

const stubs = collectStubs();

if (baselineMode) {
  fs.mkdirSync(path.dirname(BASELINE_PATH), { recursive: true });
  fs.writeFileSync(
    BASELINE_PATH,
    JSON.stringify({ count: stubs.length, updated: new Date().toISOString() }, null, 2) + '\n',
  );
  console.log(`Baseline written: ${stubs.length} stubs`);
  process.exit(0);
}

if (listMode) {
  for (const s of stubs) {
    console.log(`  ${s.lang}/${s.file}  ${s.key} = ${JSON.stringify(s.value)}`);
  }
  console.log('');
}

const baseline = loadBaseline();
console.log('============================================================');
console.log('  i18n Stub Value Check');
console.log('============================================================');
console.log(`  Current stubs:  ${stubs.length}`);
console.log(`  Baseline:       ${baseline.count}`);

if (stubs.length > baseline.count) {
  console.error('');
  console.error(`  ✗ FAIL: ${stubs.length - baseline.count} NEW stub(s) introduced.`);
  console.error('');
  console.error('  A stub is a translation value that is just the key name with');
  console.error('  underscores replaced by spaces (e.g. "update_btn": "Update Btn").');
  console.error('  Fix: write the actual English UI text as the value.');
  console.error('');
  console.error('  Run with --list to see all current stubs.');
  console.error('  After fixing, run with --baseline to update the baseline.');
  process.exit(1);
}

if (stubs.length < baseline.count) {
  console.log('');
  console.log(`  ✓ ${baseline.count - stubs.length} stub(s) fixed — run with --baseline to lock it in.`);
}

console.log('  ✓ No stub regression.');
process.exit(0);
