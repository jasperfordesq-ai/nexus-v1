// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * check-php-i18n-coverage.mjs — Verify every __() and trans() key in PHP
 * code resolves to a value in lang/en/*.json or lang/en/*.php.
 *
 * Catches the class of bug where email listeners call
 * __('emails_misc.admin_notify.new_user_title') but the key is missing
 * or the locale file isn't loaded — rendering raw keys in sent emails.
 *
 * Walks app/ and src/ (both Laravel and legacy dirs).
 * Uses a ratcheting baseline at .github/php-i18n-baseline.json.
 *
 * Usage:
 *   node scripts/check-php-i18n-coverage.mjs          # check vs baseline
 *   node scripts/check-php-i18n-coverage.mjs --list   # list missing keys
 *   node scripts/check-php-i18n-coverage.mjs --baseline  # regenerate baseline
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '..');
const PHP_DIRS = [path.join(ROOT, 'app'), path.join(ROOT, 'src')];
const LANG = path.join(ROOT, 'lang/en');
const BASELINE = path.join(ROOT, '.github/php-i18n-baseline.json');

function flatten(obj, prefix = '', out = new Set()) {
  for (const [k, v] of Object.entries(obj)) {
    const p = prefix ? `${prefix}.${k}` : k;
    if (v !== null && typeof v === 'object' && !Array.isArray(v)) flatten(v, p, out);
    else out.add(p);
  }
  return out;
}

function parsePhpReturnArray(text) {
  // Very small heuristic PHP array parser. Extracts top-level and nested string keys.
  // Good enough for lang files which are "return ['key' => 'value', 'section' => ['nested' => '...']]".
  // Returns a Set of dotted keys.
  const keys = new Set();
  // Strip PHP comments
  const src = text.replace(/\/\*[\s\S]*?\*\//g, '').replace(/\/\/[^\n]*/g, '').replace(/#[^\n]*/g, '');
  // Walk character-by-character tracking depth and key/value positions
  // We only track keys before '=>'
  const stack = [];
  let i = 0;
  const n = src.length;
  while (i < n) {
    const ch = src[i];
    if (ch === '[') {
      stack.push({ expectingKey: true, currentKey: null });
      i++;
      continue;
    }
    if (ch === ']') {
      stack.pop();
      i++;
      continue;
    }
    if (!stack.length) { i++; continue; }
    const top = stack[stack.length - 1];
    if (ch === "'" || ch === '"') {
      const q = ch;
      let j = i + 1;
      let str = '';
      while (j < n && src[j] !== q) {
        if (src[j] === '\\' && j + 1 < n) { str += src[j + 1]; j += 2; }
        else { str += src[j]; j++; }
      }
      i = j + 1;
      if (top.expectingKey) {
        top.currentKey = str;
      }
      continue;
    }
    if (ch === '=' && src[i + 1] === '>') {
      i += 2;
      if (top.currentKey !== null) {
        // Look ahead: is value a '[' (nested)?
        let j = i;
        while (j < n && /\s/.test(src[j])) j++;
        const pathSoFar = stack.slice(0, -1).map((f) => f.currentKey).filter(Boolean).concat(top.currentKey).join('.');
        if (src[j] === '[') {
          // Nested — push fake frame with current key as prefix will be applied on pop
          // Don't add to keys yet; descend
          // Keep currentKey so children frames can see it via stack
        } else {
          keys.add(pathSoFar);
        }
      }
      top.expectingKey = false;
      continue;
    }
    if (ch === ',') {
      top.expectingKey = true;
      top.currentKey = null;
      i++;
      continue;
    }
    i++;
  }
  return keys;
}

function loadNamespaces() {
  const nss = new Map();
  if (!fs.existsSync(LANG)) return nss;
  for (const file of fs.readdirSync(LANG)) {
    const full = path.join(LANG, file);
    if (file.endsWith('.json')) {
      const ns = file.replace('.json', '');
      const data = JSON.parse(fs.readFileSync(full, 'utf8'));
      nss.set(ns, flatten(data));
    } else if (file.endsWith('.php')) {
      const ns = file.replace('.php', '');
      try {
        const keys = parsePhpReturnArray(fs.readFileSync(full, 'utf8'));
        const existing = nss.get(ns) || new Set();
        for (const k of keys) existing.add(k);
        nss.set(ns, existing);
      } catch {}
    }
  }
  return nss;
}

function walkPhp(dir, out = []) {
  if (!fs.existsSync(dir)) return out;
  for (const e of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, e.name);
    if (e.isDirectory()) {
      if (e.name === 'vendor' || e.name === 'node_modules' || e.name.startsWith('.')) continue;
      walkPhp(p, out);
    } else if (e.isFile() && e.name.endsWith('.php')) {
      out.push(p);
    }
  }
  return out;
}

function extractCalls(src) {
  const calls = [];
  // __('key'...) or trans('key'...) or Lang::get('key'...) — only static strings
  const re = /(?:\b__|\btrans|Lang::(?:get|has|trans))\(\s*(['"])([^'"\n]+)\1/g;
  let m;
  while ((m = re.exec(src)) !== null) {
    const key = m[2];
    // Skip keys that don't look like namespaced translation keys
    if (!key.includes('.')) continue;
    // Skip dynamic keys (PHP string interpolation: "foo.{$var}_bar" or "foo.$var")
    if (key.includes('{$') || key.includes('{')) continue;
    if (/\$\w/.test(key)) continue;
    // Skip keys that end in '.' (truncated by concatenation)
    if (key.endsWith('.')) continue;
    const lineNum = src.slice(0, m.index).split('\n').length;
    calls.push({ key, line: lineNum });
  }
  return calls;
}

const args = process.argv.slice(2);
const listMode = args.includes('--list');
const baselineMode = args.includes('--baseline');

const namespaces = loadNamespaces();

const files = PHP_DIRS.flatMap((d) => walkPhp(d));
const missing = [];
for (const file of files) {
  const rel = path.relative(ROOT, file).replace(/\\/g, '/');
  const src = fs.readFileSync(file, 'utf8');
  const calls = extractCalls(src);
  for (const c of calls) {
    const firstDot = c.key.indexOf('.');
    const ns = c.key.slice(0, firstDot);
    const rest = c.key.slice(firstDot + 1);
    const keyset = namespaces.get(ns);
    if (!keyset) continue; // unknown namespace — don't flag (could be validation.*, auth.*, etc. Laravel built-ins)
    if (!keyset.has(rest)) {
      missing.push({ ns, key: rest, full: c.key, file: rel, line: c.line });
    }
  }
}

const unique = [];
const seen = new Set();
for (const m of missing) {
  const id = m.full;
  if (seen.has(id)) continue;
  seen.add(id);
  unique.push(m);
}

if (baselineMode) {
  fs.mkdirSync(path.dirname(BASELINE), { recursive: true });
  fs.writeFileSync(BASELINE, JSON.stringify({ count: unique.length, updated: new Date().toISOString() }, null, 2) + '\n');
  console.log(`Baseline written: ${unique.length} missing PHP translation keys`);
  process.exit(0);
}

if (listMode) {
  for (const m of unique) console.log(`  [${m.ns}] ${m.full}  (${m.file}:${m.line})`);
}

const baseline = fs.existsSync(BASELINE) ? JSON.parse(fs.readFileSync(BASELINE, 'utf8')) : { count: 0 };
console.log('============================================================');
console.log('  PHP i18n Coverage Check (__/trans/Lang::get across app/ + src/)');
console.log('============================================================');
console.log(`  Files scanned:      ${files.length}`);
console.log(`  Known namespaces:   ${namespaces.size}`);
console.log(`  Missing (unique):   ${unique.length}`);
console.log(`  Baseline:           ${baseline.count}`);

if (unique.length > baseline.count) {
  console.error('');
  console.error(`  ✗ FAIL: ${unique.length - baseline.count} new missing PHP translation key(s).`);
  console.error('  Run with --list to see gaps.');
  process.exit(1);
}

console.log('  ✓ No PHP i18n coverage regression.');
process.exit(0);
