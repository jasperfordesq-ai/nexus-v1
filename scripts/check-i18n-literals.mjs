// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * check-i18n-literals.mjs — Ratchet hardcoded React strings using i18next-cli.
 *
 * The admin app is English-only by product policy, so this guard ignores
 * react-frontend/src/admin/** and protects the public/member React surface.
 *
 * Usage:
 *   node scripts/check-i18n-literals.mjs
 *   node scripts/check-i18n-literals.mjs --list
 *   node scripts/check-i18n-literals.mjs --baseline
 */

import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '..');
const BASELINE_PATH = path.join(ROOT, '.github', 'i18n-literal-baseline.json');
const ADMIN_PATH_PATTERN = /^src[\\/]+admin[\\/]+/;
const LITERAL_PATTERN = /Error: Found hardcoded string:/;
const NON_TRANSLATABLE_VALUES = new Set([
  '--',
  '.ics',
  '0%',
  '95%',
  '100%',
  '€16 : €1',
  '%)',
  'AGPL-3.0-or-later',
  'Google',
  'Outlook',
  'XP',
  'KB',
  'KB)',
  'MB',
  'MB)',
  'TC',
  'cr',
  'hrs',
  'km',
  'min',
  'vs',
]);
const NON_TRANSLATABLE_PATTERNS = [
  /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
  /^[\d.,]+$/,
  /^[+\-]?\d+(\.\d+)?%$/,
  /^[A-Z]{2,5}$/,
];

const args = process.argv.slice(2);
const listMode = args.includes('--list');
const baselineMode = args.includes('--baseline');

function runI18nextLint() {
  const command = process.platform === 'win32' ? (process.env.ComSpec || 'cmd.exe') : 'npm';
  const commandArgs = process.platform === 'win32'
    ? ['/d', '/s', '/c', 'npm --prefix react-frontend run i18n:lint']
    : ['--prefix', 'react-frontend', 'run', 'i18n:lint'];
  const result = spawnSync(command, commandArgs, {
    cwd: ROOT,
    encoding: 'utf8',
    shell: false,
    maxBuffer: 50 * 1024 * 1024,
  });

  return {
    status: result.status ?? 1,
    output: `${result.stdout ?? ''}${result.stderr ?? ''}${result.error ? `\n${result.error.message}` : ''}`,
  };
}

function parseLiterals(output) {
  const literals = [];
  let currentFile = '';

  for (const rawLine of output.split(/\r?\n/)) {
    const line = rawLine.trimEnd();

    if (/^src[\\/].+\.(tsx?|jsx?)$/.test(line.trim())) {
      currentFile = line.trim();
      continue;
    }

    if (!LITERAL_PATTERN.test(line)) {
      continue;
    }

    if (!currentFile || ADMIN_PATH_PATTERN.test(currentFile)) {
      continue;
    }

    const match = line.match(/^\s*(\d+):\s*Error: Found hardcoded string:\s*(.*)$/);
    const value = match ? match[2] : line.trim();
    if (isIgnorableLiteral(value)) {
      continue;
    }

    literals.push({
      file: currentFile.replace(/\\/g, '/'),
      line: match ? Number(match[1]) : null,
      value,
    });
  }

  return literals;
}

function normaliseLiteralValue(rawValue) {
  const trimmed = rawValue.trim();
  try {
    const parsed = JSON.parse(trimmed);
    if (typeof parsed === 'string') {
      return parsed.trim();
    }
  } catch {
    // i18next-cli can print the first line of a multiline string; keep raw text.
  }
  return trimmed.replace(/^"+|"+$/g, '').trim();
}

function isIgnorableLiteral(rawValue) {
  const value = normaliseLiteralValue(rawValue);
  if (NON_TRANSLATABLE_VALUES.has(value)) {
    return true;
  }
  return NON_TRANSLATABLE_PATTERNS.some((pattern) => pattern.test(value));
}

function loadBaseline() {
  if (!fs.existsSync(BASELINE_PATH)) {
    return { count: 0 };
  }
  return JSON.parse(fs.readFileSync(BASELINE_PATH, 'utf8'));
}

function summarizeByFile(literals) {
  const counts = new Map();
  for (const literal of literals) {
    counts.set(literal.file, (counts.get(literal.file) ?? 0) + 1);
  }
  return [...counts.entries()]
    .map(([file, count]) => ({ file, count }))
    .sort((a, b) => b.count - a.count || a.file.localeCompare(b.file));
}

const lintResult = runI18nextLint();
const literals = parseLiterals(lintResult.output);
const byFile = summarizeByFile(literals);

if (lintResult.status !== 0 && literals.length === 0) {
  console.error('i18next-cli lint failed, but no hardcoded-string diagnostics were parsed.');
  console.error(lintResult.output.trim());
  process.exit(lintResult.status);
}

if (baselineMode) {
  fs.mkdirSync(path.dirname(BASELINE_PATH), { recursive: true });
  fs.writeFileSync(
    BASELINE_PATH,
    `${JSON.stringify({
      count: literals.length,
      updated: new Date().toISOString(),
      source: 'scripts/check-i18n-literals.mjs',
      scope: 'react-frontend/src excluding src/admin',
      topFiles: byFile.slice(0, 25),
    }, null, 2)}\n`,
  );
  console.log(`Baseline written: ${literals.length} non-admin React hardcoded string(s).`);
  process.exit(0);
}

if (listMode) {
  for (const literal of literals) {
    const line = literal.line === null ? '' : `:${literal.line}`;
    console.log(`${literal.file}${line} ${literal.value}`);
  }
  if (literals.length > 0) {
    console.log('');
  }
}

const baseline = loadBaseline();

console.log('============================================================');
console.log('  React i18n Literal String Check');
console.log('============================================================');
console.log(`  Current non-admin literals: ${literals.length}`);
console.log(`  Baseline:                   ${baseline.count}`);
console.log('  Admin scope:                ignored by policy');

if (literals.length > baseline.count) {
  console.error('');
  console.error(`  FAIL: ${literals.length - baseline.count} new non-admin literal string(s) introduced.`);
  console.error('');
  console.error('  Top files currently carrying literals:');
  for (const item of byFile.slice(0, 10)) {
    console.error(`   ${item.file}: ${item.count}`);
  }
  console.error('');
  console.error('  Fix new public/member UI strings with t()/Trans, or refresh the baseline only after review:');
  console.error('   node scripts/check-i18n-literals.mjs --baseline');
  process.exit(1);
}

if (literals.length < baseline.count) {
  console.log('');
  console.log(`  ${baseline.count - literals.length} literal string(s) fixed. Run --baseline to lock in the improvement.`);
}

console.log('  No literal-string regression.');
process.exit(0);
