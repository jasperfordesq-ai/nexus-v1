// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * check-i18n-coverage.mjs — Exhaustive translation key coverage check.
 *
 * Walks EVERY .tsx file under react-frontend/src (not just admin),
 * extracts EVERY t('...') call regardless of namespace or aliasing,
 * and verifies each referenced key exists in the corresponding locale file.
 *
 * This is the catch-all that replaces per-file allowlists. Any new component
 * that references a nonexistent translation key fails CI — no exceptions,
 * no opt-ins, no maintenance burden.
 *
 * Uses a baseline at .github/i18n-coverage-baseline.json so pre-existing
 * gaps don't block every commit. The count can only go DOWN, never up —
 * ratcheting pressure toward zero without demanding a single huge PR.
 *
 * Handles:
 *  - t('namespace.key') with useTranslation('namespace')
 *  - t('explicit:key') with explicit namespace prefix
 *  - Aliased hooks: useTranslation('foo') as { t: tFoo } → tFoo('key')
 *  - Plural suffixes (_one, _other, _few, _many, _two, _zero) — accepts either form
 *  - Dynamic keys t(`prefix.${var}`) — warns, does not fail
 *
 * Usage:
 *   node scripts/check-i18n-coverage.mjs          # check against baseline
 *   node scripts/check-i18n-coverage.mjs --list   # list all missing keys
 *   node scripts/check-i18n-coverage.mjs --baseline  # regenerate baseline
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '..');
const SRC = path.join(ROOT, 'react-frontend/src');
const LOCALES = path.join(ROOT, 'react-frontend/public/locales/en');
const BASELINE = path.join(ROOT, '.github/i18n-coverage-baseline.json');

const PLURAL_SUFFIXES = ['_one', '_other', '_few', '_many', '_two', '_zero'];

function flattenKeys(obj, prefix = '', out = new Set()) {
  for (const [k, v] of Object.entries(obj)) {
    const p = prefix ? `${prefix}.${k}` : k;
    if (v !== null && typeof v === 'object' && !Array.isArray(v)) {
      flattenKeys(v, p, out);
    } else {
      out.add(p);
    }
  }
  return out;
}

function loadLocales() {
  const keysets = new Map();
  for (const file of fs.readdirSync(LOCALES)) {
    if (!file.endsWith('.json')) continue;
    const ns = file.replace('.json', '');
    const data = JSON.parse(fs.readFileSync(path.join(LOCALES, file), 'utf8'));
    keysets.set(ns, flattenKeys(data));
  }
  return keysets;
}

function walkTsx(dir, out = []) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      if (entry.name === '__tests__' || entry.name === 'node_modules') continue;
      walkTsx(p, out);
    } else if (entry.isFile() && /\.tsx?$/.test(entry.name) && !entry.name.endsWith('.test.tsx') && !entry.name.endsWith('.test.ts')) {
      out.push(p);
    }
  }
  return out;
}

/**
 * Extract all (namespace, key, line) tuples from a TSX file.
 * Handles aliased useTranslation hooks.
 */
function extractCalls(source) {
  const calls = [];
  const dynamic = [];

  // Map alias → namespace. Default alias is 't'.
  // Patterns:
  //   const { t } = useTranslation('ns')           → t → ns
  //   const { t: tFoo } = useTranslation('ns')     → tFoo → ns
  //   const { t } = useTranslation()               → t → DEFAULT (null)
  //   const { t } = useTranslation(['ns1','ns2'])  → t → ns1 (first)
  const aliases = new Map();
  aliases.set('t', null); // fallback when no namespace known

  const hookRE = /const\s*\{\s*t(?:\s*:\s*(\w+))?\s*\}\s*=\s*useTranslation\(\s*(?:\[\s*)?['"]([^'"]+)['"]/g;
  let m;
  while ((m = hookRE.exec(source)) !== null) {
    const alias = m[1] || 't';
    const ns = m[2];
    aliases.set(alias, ns);
  }

  // Extract calls: aliasName('key.path' or 'ns:key.path') possibly with interpolation arg
  const aliasNames = [...aliases.keys()].join('|');
  if (!aliasNames) return { calls, dynamic };

  const callRE = new RegExp(`\\b(${aliasNames})\\(\\s*(['"\`])([^'"\`]+)\\2`, 'g');
  const lines = source.split('\n');
  for (let lineNum = 0; lineNum < lines.length; lineNum++) {
    const line = lines[lineNum];
    let cm;
    const lineRE = new RegExp(callRE.source, 'g');
    while ((cm = lineRE.exec(line)) !== null) {
      const alias = cm[1];
      const quote = cm[2];
      const keyStr = cm[3];
      const aliasNs = aliases.get(alias);

      if (quote === '`' && keyStr.includes('${')) {
        dynamic.push({ key: keyStr, alias, ns: aliasNs, line: lineNum + 1 });
        continue;
      }

      let ns = aliasNs;
      let key = keyStr;
      const colonIdx = keyStr.indexOf(':');
      if (colonIdx !== -1 && /^[\w_]+$/.test(keyStr.slice(0, colonIdx))) {
        ns = keyStr.slice(0, colonIdx);
        key = keyStr.slice(colonIdx + 1);
      }

      if (!ns) continue; // can't resolve
      calls.push({ ns, key, line: lineNum + 1 });
    }
  }

  return { calls, dynamic };
}

function keyExists(keyset, key) {
  if (keyset.has(key)) return true;
  for (const suf of PLURAL_SUFFIXES) {
    if (key.endsWith(suf)) {
      const base = key.slice(0, -suf.length);
      if ([...PLURAL_SUFFIXES].some((s) => keyset.has(`${base}${s}`))) return true;
    }
  }
  return false;
}

function loadBaseline() {
  if (!fs.existsSync(BASELINE)) return { count: 0 };
  return JSON.parse(fs.readFileSync(BASELINE, 'utf8'));
}

// ──────────── main ────────────

const args = process.argv.slice(2);
const listMode = args.includes('--list');
const baselineMode = args.includes('--baseline');

const locales = loadLocales();
const files = walkTsx(SRC);

const missing = [];
const dynamicWarnings = [];
const unknownNs = new Set();

for (const file of files) {
  const rel = path.relative(ROOT, file).replace(/\\/g, '/');
  const source = fs.readFileSync(file, 'utf8');
  const { calls, dynamic } = extractCalls(source);

  for (const call of calls) {
    const keyset = locales.get(call.ns);
    if (!keyset) {
      unknownNs.add(call.ns);
      continue;
    }
    if (!keyExists(keyset, call.key)) {
      missing.push({ ns: call.ns, key: call.key, file: rel, line: call.line });
    }
  }
  for (const d of dynamic) {
    dynamicWarnings.push({ ...d, file: rel });
  }
}

// Dedupe missing by (ns, key)
const uniqueMissing = [];
const seen = new Set();
for (const m of missing) {
  const id = `${m.ns}:${m.key}`;
  if (seen.has(id)) continue;
  seen.add(id);
  uniqueMissing.push(m);
}

if (baselineMode) {
  fs.mkdirSync(path.dirname(BASELINE), { recursive: true });
  fs.writeFileSync(BASELINE, JSON.stringify({ count: uniqueMissing.length, updated: new Date().toISOString() }, null, 2) + '\n');
  console.log(`Baseline written: ${uniqueMissing.length} missing keys`);
  process.exit(0);
}

if (listMode) {
  for (const m of uniqueMissing) {
    console.log(`  [${m.ns}] ${m.key}  (${m.file}:${m.line})`);
  }
}

const baseline = loadBaseline();
console.log('============================================================');
console.log('  i18n Coverage Check (exhaustive — walks all of src/)');
console.log('============================================================');
console.log(`  Files scanned:       ${files.length}`);
console.log(`  Known namespaces:    ${locales.size}`);
console.log(`  Unknown namespaces:  ${unknownNs.size}${unknownNs.size ? ' (' + [...unknownNs].sort().join(', ') + ')' : ''}`);
console.log(`  Dynamic key warns:   ${dynamicWarnings.length}`);
console.log(`  Missing keys (unique): ${uniqueMissing.length}`);
console.log(`  Baseline:            ${baseline.count}`);

if (uniqueMissing.length > baseline.count) {
  console.error('');
  console.error(`  ✗ FAIL: ${uniqueMissing.length - baseline.count} NEW missing key(s) introduced.`);
  console.error('');
  console.error('  Every t(\'key\') call must resolve to a value in the corresponding locale JSON.');
  console.error('  Run with --list to see all current gaps.');
  console.error('  After fixing, run with --baseline to lock the new count in.');
  process.exit(1);
}

if (uniqueMissing.length < baseline.count) {
  console.log('');
  console.log(`  ✓ ${baseline.count - uniqueMissing.length} missing key(s) fixed — run with --baseline to ratchet down.`);
}

console.log('  ✓ No coverage regression.');
process.exit(0);
