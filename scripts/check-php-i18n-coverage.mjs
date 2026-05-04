// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Verify every static __(), trans(), and Lang::get() PHP translation key resolves
 * to lang/en/*.json or lang/en/*.php.
 *
 * Usage:
 *   node scripts/check-php-i18n-coverage.mjs            # check vs baseline
 *   node scripts/check-php-i18n-coverage.mjs --list     # list missing keys
 *   node scripts/check-php-i18n-coverage.mjs --baseline # regenerate baseline
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
    if (v !== null && typeof v === 'object' && !Array.isArray(v)) {
      flatten(v, p, out);
    } else {
      out.add(p);
    }
  }
  return out;
}

function parsePhpReturnArray(text) {
  const keys = new Set();
  const src = text
    .replace(/\/\*[\s\S]*?\*\//g, '')
    .replace(/^\s*\/\/[^\n]*/gm, '')
    .replace(/^\s*#[^\n]*/gm, '');
  let i = src.indexOf('[');

  function skipWhitespace() {
    while (i < src.length && /\s/.test(src[i])) i++;
  }

  function readString() {
    const quote = src[i];
    if (quote !== "'" && quote !== '"') return null;
    i++;
    let out = '';
    while (i < src.length) {
      const ch = src[i++];
      if (ch === '\\' && i < src.length) {
        out += src[i++];
      } else if (ch === quote) {
        return out;
      } else {
        out += ch;
      }
    }
    return out;
  }

  function skipValue() {
    let depth = 0;
    let quote = null;
    while (i < src.length) {
      const ch = src[i];
      if (quote) {
        i++;
        if (ch === '\\' && i < src.length) i++;
        else if (ch === quote) quote = null;
        continue;
      }
      if (ch === "'" || ch === '"') {
        quote = ch;
        i++;
        continue;
      }
      if (ch === '[') {
        depth++;
        i++;
        continue;
      }
      if (ch === ']') {
        if (depth === 0) return;
        depth--;
        i++;
        continue;
      }
      if (ch === ',' && depth === 0) return;
      i++;
    }
  }

  function parseArray(prefix = []) {
    if (src[i] !== '[') return;
    i++;
    while (i < src.length) {
      skipWhitespace();
      if (src[i] === ']') {
        i++;
        return;
      }

      const keyStart = i;
      const key = readString();
      if (key === null) {
        skipValue();
      } else {
        skipWhitespace();
        if (src[i] === '=' && src[i + 1] === '>') {
          i += 2;
          skipWhitespace();
          const pathSoFar = [...prefix, key];
          if (src[i] === '[') {
            parseArray(pathSoFar);
          } else {
            keys.add(pathSoFar.join('.'));
            skipValue();
          }
        } else {
          i = keyStart;
          skipValue();
        }
      }

      skipWhitespace();
      if (src[i] === ',') i++;
    }
  }

  if (i >= 0) parseArray();
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
      const keys = parsePhpReturnArray(fs.readFileSync(full, 'utf8'));
      const existing = nss.get(ns) || new Set();
      for (const k of keys) existing.add(k);
      nss.set(ns, existing);
    }
  }
  return nss;
}

function walkPhp(dir, out = []) {
  if (!fs.existsSync(dir)) return out;
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      if (entry.name === 'vendor' || entry.name === 'node_modules' || entry.name.startsWith('.')) continue;
      walkPhp(p, out);
    } else if (entry.isFile() && entry.name.endsWith('.php')) {
      out.push(p);
    }
  }
  return out;
}

function extractCalls(src) {
  const calls = [];
  const scanSrc = src
    .replace(/\/\*[\s\S]*?\*\//g, '')
    .replace(/^\s*\/\/[^\n]*/gm, '')
    .replace(/^\s*#[^\n]*/gm, '');
  const re = /(?:\b__|\btrans|Lang::(?:get|has|trans))\(\s*(['"])([^'"\n]+)\1/g;
  let match;
  while ((match = re.exec(scanSrc)) !== null) {
    const key = match[2];
    if (!key.includes('.')) continue;
    const nextToken = scanSrc.slice(re.lastIndex).match(/^\s*(.)/);
    if (nextToken?.[1] === '.') continue;
    if (key.includes('{$') || key.includes('{')) continue;
    if (/\$\w/.test(key)) continue;
    if (key.endsWith('.')) continue;
    const lineNum = scanSrc.slice(0, match.index).split('\n').length;
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
  for (const call of extractCalls(src)) {
    const firstDot = call.key.indexOf('.');
    const ns = call.key.slice(0, firstDot);
    const rest = call.key.slice(firstDot + 1);
    const keyset = namespaces.get(ns);
    if (!keyset) continue;
    if (!keyset.has(rest)) {
      missing.push({ ns, key: rest, full: call.key, file: rel, line: call.line });
    }
  }
}

const unique = [];
const seen = new Set();
for (const item of missing) {
  if (seen.has(item.full)) continue;
  seen.add(item.full);
  unique.push(item);
}

if (baselineMode) {
  fs.mkdirSync(path.dirname(BASELINE), { recursive: true });
  fs.writeFileSync(BASELINE, JSON.stringify({ count: unique.length, updated: new Date().toISOString() }, null, 2) + '\n');
  console.log(`Baseline written: ${unique.length} missing PHP translation keys`);
  process.exit(0);
}

if (listMode) {
  for (const item of unique) {
    console.log(`  [${item.ns}] ${item.full}  (${item.file}:${item.line})`);
  }
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
  console.error(`  FAIL: ${unique.length - baseline.count} new missing PHP translation key(s).`);
  console.error('  Run with --list to see gaps.');
  process.exit(1);
}

console.log('  No PHP i18n coverage regression.');
process.exit(0);
