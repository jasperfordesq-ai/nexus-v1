// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * check-i18n-vars.mjs — Detect translation variable-name mismatches.
 *
 * When a call like  t('foo.bar', { count: n })  is paired with a translation
 * value "Hello {{name}}", i18next leaves "{{name}}" as literal text on screen
 * because 'name' is not in the variable object. This script catches those
 * mismatches.
 *
 * For every static t('key', {a, b}) call in react-frontend/src:
 *   - Parse the key against the matching en/<namespace>.json
 *   - Extract all {{var}} placeholders from the translation value
 *   - Verify every placeholder has a matching key in the options object
 *
 * Ignores reserved i18next options: count, context, defaultValue, ns,
 * replace, lng, fallbackLng, returnObjects, returnDetails.
 *
 * Exit 0 if no mismatches. Exit 1 otherwise.
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '..');
const SRC = path.join(ROOT, 'react-frontend/src');
const LOCALES = path.join(ROOT, 'react-frontend/public/locales/en');
const BASELINE = path.join(ROOT, '.github/i18n-vars-baseline.json');

const RESERVED = new Set([
  'count', 'context', 'defaultValue', 'ns', 'replace', 'lng',
  'fallbackLng', 'returnObjects', 'returnDetails', 'keySeparator',
  'nsSeparator', 'postProcess', 'interpolation',
]);

function flatten(obj, prefix = '', out = {}) {
  for (const [k, v] of Object.entries(obj)) {
    const p = prefix ? `${prefix}.${k}` : k;
    if (v !== null && typeof v === 'object' && !Array.isArray(v)) flatten(v, p, out);
    else out[p] = v;
  }
  return out;
}

function loadLocales() {
  const nss = new Map();
  for (const file of fs.readdirSync(LOCALES)) {
    if (!file.endsWith('.json')) continue;
    const data = JSON.parse(fs.readFileSync(path.join(LOCALES, file), 'utf8'));
    nss.set(file.replace('.json', ''), flatten(data));
  }
  return nss;
}

function walkTsx(dir, out = []) {
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, e.name);
    if (e.isDirectory()) {
      if (e.name === '__tests__' || e.name === 'node_modules') continue;
      walkTsx(p, out);
    } else if (e.isFile() && /\.tsx?$/.test(e.name) && !e.name.endsWith('.test.tsx') && !e.name.endsWith('.test.ts')) {
      out.push(p);
    }
  }
  return out;
}

function extractAliases(source) {
  const aliases = new Map();
  aliases.set('t', null);
  const re = /const\s*\{\s*t(?:\s*:\s*(\w+))?\s*\}\s*=\s*useTranslation\(\s*(?:\[\s*)?['"]([^'"]+)['"]/g;
  let m;
  while ((m = re.exec(source)) !== null) {
    aliases.set(m[1] || 't', m[2]);
  }
  return aliases;
}

function extractVarsObject(src, startIdx) {
  // Parse a {...} JS object literal starting near startIdx. Return set of top-level keys.
  // Keeps tracking brace depth; extracts top-level identifier keys before ':'.
  let depth = 0;
  let i = startIdx;
  // Advance to the opening '{'
  while (i < src.length && src[i] !== '{' && src[i] !== ')') i++;
  if (src[i] !== '{') return null;
  i++; depth = 1;
  const keys = new Set();
  let tokenStart = null;
  let expectingKey = true;
  while (i < src.length && depth > 0) {
    const ch = src[i];
    if (ch === '{' || ch === '(' || ch === '[') depth++;
    else if (ch === '}' || ch === ')' || ch === ']') {
      depth--;
      if (depth === 0) break;
    } else if (depth === 1) {
      if (expectingKey) {
        if (/\w/.test(ch)) {
          if (tokenStart === null) tokenStart = i;
        } else if (ch === ':' || ch === ',' || ch === '\n' || ch === ' ' || ch === '\t') {
          if (tokenStart !== null && ch === ':') {
            keys.add(src.slice(tokenStart, i).trim());
            tokenStart = null;
            expectingKey = false;
          } else if (tokenStart !== null && ch === ',') {
            // Shorthand { foo, bar } — foo is both key and value
            keys.add(src.slice(tokenStart, i).trim());
            tokenStart = null;
          }
        } else if (ch === '.' || ch === '[') {
          // Computed/nested — give up tracking this token
          tokenStart = null;
        }
      } else {
        if (ch === ',') expectingKey = true;
      }
    }
    i++;
  }
  // Trailing shorthand
  if (tokenStart !== null && depth === 0) {
    const tail = src.slice(tokenStart, i).trim();
    if (tail) keys.add(tail);
  }
  return keys;
}

function extractPlaceholders(value) {
  if (typeof value !== 'string') return new Set();
  const out = new Set();
  for (const m of value.matchAll(/\{\{\s*(\w+)(?:,[^}]*)?\s*\}\}/g)) {
    out.add(m[1]);
  }
  return out;
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

const mismatches = [];

for (const file of files) {
  const rel = path.relative(ROOT, file).replace(/\\/g, '/');
  const src = fs.readFileSync(file, 'utf8');
  const aliases = extractAliases(src);
  const aliasNames = [...aliases.keys()].join('|');
  if (!aliasNames) continue;

  // Match t('key', {options}) — literal key + options object
  const re = new RegExp(`\\b(${aliasNames})\\(\\s*['"]([^'"\n]+)['"]\\s*,\\s*\\{`, 'g');
  let m;
  while ((m = re.exec(src)) !== null) {
    const alias = m[1];
    let ns = aliases.get(alias);
    let key = m[2];
    const colonIdx = key.indexOf(':');
    if (colonIdx !== -1 && /^[\w_]+$/.test(key.slice(0, colonIdx))) {
      ns = key.slice(0, colonIdx);
      key = key.slice(colonIdx + 1);
    }
    if (!ns) continue;
    const kset = locales.get(ns);
    if (!kset) continue;
    const value = kset[key];
    if (value === undefined) continue;

    const placeholders = extractPlaceholders(value);
    if (placeholders.size === 0) continue;

    // Parse the options object starting at position m.index + m[0].length - 1
    const objStart = m.index + m[0].length - 1;
    const vars = extractVarsObject(src, objStart);
    if (vars === null) continue;

    // For each placeholder, verify matching var OR reserved (count is auto-handled)
    for (const ph of placeholders) {
      if (RESERVED.has(ph)) continue;
      if (!vars.has(ph)) {
        const line = src.slice(0, m.index).split('\n').length;
        mismatches.push({ ns, key, placeholder: ph, vars: [...vars].join(','), file: rel, line });
      }
    }
  }
}

if (baselineMode) {
  fs.mkdirSync(path.dirname(BASELINE), { recursive: true });
  fs.writeFileSync(BASELINE, JSON.stringify({ count: mismatches.length, updated: new Date().toISOString() }, null, 2) + '\n');
  console.log(`Baseline written: ${mismatches.length} mismatches`);
  process.exit(0);
}

if (listMode) {
  for (const m of mismatches) {
    console.log(`  [${m.ns}] ${m.key}  placeholder {{${m.placeholder}}} not in vars {${m.vars}}  (${m.file}:${m.line})`);
  }
}

const baseline = loadBaseline();
console.log('============================================================');
console.log('  i18n Variable-Name Mismatch Check');
console.log('============================================================');
console.log(`  Files scanned:        ${files.length}`);
console.log(`  Mismatches found:     ${mismatches.length}`);
console.log(`  Baseline:             ${baseline.count}`);

if (mismatches.length > baseline.count) {
  console.error('');
  console.error(`  ✗ FAIL: ${mismatches.length - baseline.count} new variable mismatch(es).`);
  console.error('  A placeholder like {{count}} in the translation value must match the variable');
  console.error('  name passed to t(). Otherwise i18next renders the raw {{placeholder}} on screen.');
  console.error('  Run with --list to see details.');
  process.exit(1);
}

console.log('  ✓ No variable-mismatch regression.');
process.exit(0);
