#!/usr/bin/env node
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const scriptPath = path.join(process.cwd(), 'scripts/check-next-public-dry-run.mjs');
const scriptUrl = pathToFileURL(scriptPath).href;

const tests = [];

function test(name, fn) {
  tests.push({ name, fn });
}

test('root package exposes the Next public dry-run check command', () => {
  const packageJson = JSON.parse(fs.readFileSync(path.join(process.cwd(), 'package.json'), 'utf8'));

  assert.equal(
    packageJson.scripts['check:next-public:dry-run'],
    'node scripts/check-next-public-dry-run.mjs',
  );
});

test('dry-run script defines the pre-cutover verification sequence', async () => {
  assert.equal(fs.existsSync(scriptPath), true, 'scripts/check-next-public-dry-run.mjs should exist');

  const { nextPublicDryRunChecks } = await import(scriptUrl);

  assert.deepEqual(
    nextPublicDryRunChecks.map((check) => check.key),
    [
      'inertness_guard',
      'next_shadow_check',
      'php_readiness_contract',
      'react_typecheck',
      'react_build',
    ],
  );
  assert.deepEqual(
    nextPublicDryRunChecks.map((check) => check.command),
    [
      'npm run check:next-public:inert',
      'npm --prefix next-public-frontend run check',
      'vendor/bin/phpunit --no-coverage tests/Laravel/Unit/Services/NextPublicFrontendReadinessServiceTest.php tests/Laravel/Feature/Controllers/AdminNextPublicFrontendControllerTest.php',
      'cd react-frontend && npx tsc --noEmit',
      'npm --prefix react-frontend run build',
    ],
  );
});

test('dry-run runner stops on the first failed check and reports no production effect', async () => {
  assert.equal(fs.existsSync(scriptPath), true, 'scripts/check-next-public-dry-run.mjs should exist');

  const { runNextPublicDryRun } = await import(scriptUrl);
  const calls = [];
  const result = runNextPublicDryRun({
    run: (check) => {
      calls.push(check.key);
      return {
        status: check.key === 'next_shadow_check' ? 1 : 0,
      };
    },
  });

  assert.deepEqual(calls, ['inertness_guard', 'next_shadow_check']);
  assert.equal(result.status, 'blocker');
  assert.equal(result.productionEffect, 'none');
  assert.equal(result.activationAvailable, false);
  assert.deepEqual(result.failedCheck?.key, 'next_shadow_check');
});

test('dry-run runner uses the Windows phpunit shim without changing operator copy', async () => {
  assert.equal(fs.existsSync(scriptPath), true, 'scripts/check-next-public-dry-run.mjs should exist');

  const { commandForPlatform, nextPublicDryRunChecks } = await import(scriptUrl);
  const phpCheck = nextPublicDryRunChecks.find((check) => check.key === 'php_readiness_contract');

  assert.equal(
    phpCheck.command,
    'vendor/bin/phpunit --no-coverage tests/Laravel/Unit/Services/NextPublicFrontendReadinessServiceTest.php tests/Laravel/Feature/Controllers/AdminNextPublicFrontendControllerTest.php',
  );
  assert.equal(
    commandForPlatform(phpCheck, 'win32'),
    'vendor\\bin\\phpunit.bat --no-coverage tests/Laravel/Unit/Services/NextPublicFrontendReadinessServiceTest.php tests/Laravel/Feature/Controllers/AdminNextPublicFrontendControllerTest.php',
  );
  assert.equal(commandForPlatform(phpCheck, 'linux'), phpCheck.command);
});

for (const { name, fn } of tests) {
  try {
    await fn();
    console.log(`ok - ${name}`);
  } catch (error) {
    console.error(`not ok - ${name}`);
    throw error;
  }
}
