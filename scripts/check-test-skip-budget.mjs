// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * check-test-skip-budget.mjs — Ratchet guard against schema-driven test skips.
 *
 * A large share of the PHPUnit suite calls markTestSkipped() with reasons like
 * "Required tables missing" / "column not present in test schema" / "run
 * migrations first". Those tests provide ZERO regression protection: when the
 * test database (built from database/schema/mysql-schema.sql) drifts from
 * production, whole feature areas silently skip and CI stays green.
 *
 * This guard does NOT fix the drift — it freezes it. It counts schema-driven
 * skips and fails if the count rises above a committed baseline, so new
 * schema-skip debt cannot be added. The number can only go DOWN: when the
 * schema dump is refreshed and a skip is converted to a real assertion, lower
 * BASELINE accordingly.
 *
 * Companion fix (manual, requires production access — see AGENTS.md):
 *   bash scripts/refresh-schema-dump.sh --production   # then rebuild test DB
 *
 * Uses only built-in Node modules so it runs in CI without `npm install`.
 *
 * Usage:
 *   node scripts/check-test-skip-budget.mjs            # enforce baseline
 *   node scripts/check-test-skip-budget.mjs --report   # list every skip, no fail
 *   TEST_SKIP_BUDGET=200 node scripts/check-test-skip-budget.mjs  # override ceiling
 *
 * Exit 0 = at or below budget. Exit 1 = budget exceeded (or --report finds none).
 */

import { readdirSync, readFileSync, statSync } from 'fs';
import { join, relative } from 'path';

const PROJECT_ROOT = process.cwd();
const TESTS_DIR = join(PROJECT_ROOT, 'tests');

// Committed baseline. Current count is ~172 schema-driven skips; this ceiling
// carries a small buffer so it never flaps. LOWER THIS (never raise it) as skips
// are converted back into real tests. Run with --report to see the live count.
const BASELINE = 180;
const BUDGET = Number.parseInt(process.env.TEST_SKIP_BUDGET ?? '', 10) || BASELINE;

const REPORT_ONLY = process.argv.includes('--report');

// A skip is "schema-driven" (actionable debt) when its reason points at a
// missing table/column/migration rather than a legitimate runtime guard
// (Redis unavailable, no second tenant, driver not installed, etc.).
const SCHEMA_SKIP = /missing|not present|run migrations?|schema|\bcolumn\b|\btable\b|not available in/i;
// Reasons that are legitimate environment guards — never counted as debt.
const ENV_GUARD = /redis|memcached|extension|sqlite|driver not|connection refused|no second tenant|requires? the/i;

function walk(dir) {
  const out = [];
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const full = join(dir, entry.name);
    if (entry.isDirectory()) out.push(...walk(full));
    else if (entry.name.endsWith('.php')) out.push(full);
  }
  return out;
}

/** Extract the first quoted string argument of each markTestSkipped( call. */
function extractSkipReasons(source) {
  const reasons = [];
  const needle = 'markTestSkipped(';
  let i = 0;
  while ((i = source.indexOf(needle, i)) !== -1) {
    const after = source.slice(i + needle.length, i + needle.length + 400);
    // First quoted literal (single or double), possibly on the next line.
    const m = /(['"])((?:\\.|(?!\1).)*)\1/s.exec(after);
    reasons.push(m ? m[2] : '');
    i += needle.length;
  }
  return reasons;
}

const files = (() => {
  try {
    statSync(TESTS_DIR);
  } catch {
    console.error(`tests/ directory not found at ${TESTS_DIR}`);
    process.exit(2);
  }
  return walk(TESTS_DIR);
})();

let total = 0;
let schemaCount = 0;
const schemaHits = [];

for (const file of files) {
  const source = readFileSync(file, 'utf8');
  const reasons = extractSkipReasons(source);
  for (const reason of reasons) {
    total += 1;
    const isSchema = SCHEMA_SKIP.test(reason) && !ENV_GUARD.test(reason);
    if (isSchema) {
      schemaCount += 1;
      schemaHits.push(`${relative(PROJECT_ROOT, file).replaceAll('\\', '/')} :: ${reason || '(no inline reason)'}`);
    }
  }
}

console.log('============================================================');
console.log('  Test-skip budget (schema-driven skips)');
console.log('============================================================');
console.log(`  Test files scanned:     ${files.length}`);
console.log(`  markTestSkipped total:  ${total}`);
console.log(`  Schema-driven skips:    ${schemaCount}`);
console.log(`  Budget (ceiling):       ${BUDGET}`);
console.log('============================================================');

if (REPORT_ONLY) {
  for (const hit of schemaHits.sort()) console.log(`  • ${hit}`);
  console.log('');
  console.log(`Report only — ${schemaCount} schema-driven skips listed above.`);
  process.exit(0);
}

if (schemaCount > BUDGET) {
  console.log('');
  console.log(`FAIL: schema-driven test skips rose to ${schemaCount} (budget ${BUDGET}).`);
  console.log('  These tests do not run — they skip when the test DB lacks a table/column.');
  console.log('  Either add a real test (preferred) or refresh the schema so it runs:');
  console.log('    bash scripts/refresh-schema-dump.sh --production');
  console.log('  Do NOT raise the budget to make this pass.');
  process.exit(1);
}

if (schemaCount < BUDGET) {
  console.log('');
  console.log(`PASS — and progress! ${schemaCount} < budget ${BUDGET}.`);
  console.log(`  Lower BASELINE in this script to ${schemaCount} to lock in the gain.`);
  process.exit(0);
}

console.log('');
console.log('PASS: schema-driven skips at budget. Drive this number DOWN over time.');
process.exit(0);
