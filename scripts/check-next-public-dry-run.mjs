#!/usr/bin/env node
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { spawnSync } from 'node:child_process';
import process from 'node:process';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

export const nextPublicDryRunChecks = [
  {
    key: 'inertness_guard',
    command: 'npm run check:next-public:inert',
  },
  {
    key: 'next_shadow_check',
    command: 'npm --prefix next-public-frontend run check',
  },
  {
    key: 'php_readiness_contract',
    command: 'vendor/bin/phpunit --no-coverage tests/Laravel/Unit/Services/NextPublicFrontendReadinessServiceTest.php tests/Laravel/Feature/Controllers/AdminNextPublicFrontendControllerTest.php',
  },
  {
    key: 'react_typecheck',
    command: 'cd react-frontend && npx tsc --noEmit',
  },
  {
    key: 'react_build',
    command: 'npm --prefix react-frontend run build',
  },
];

export function commandForPlatform(check, platform = process.platform) {
  if (platform === 'win32' && check.key === 'php_readiness_contract') {
    return check.command.replace('vendor/bin/phpunit', 'vendor\\bin\\phpunit.bat');
  }

  return check.command;
}

function defaultRun(check, options = {}) {
  const result = spawnSync(commandForPlatform(check), {
    cwd: options.cwd ?? process.cwd(),
    env: options.env ?? process.env,
    shell: true,
    stdio: 'inherit',
  });

  return {
    status: result.status ?? 1,
    signal: result.signal,
    error: result.error,
  };
}

/**
 * @param {{run?: (check: {key: string, command: string}) => {status?: number | null, signal?: string | null, error?: Error | null}, cwd?: string, env?: NodeJS.ProcessEnv}} options
 * @returns {{status: string, productionEffect: string, activationAvailable: boolean, checks: Array<{key: string, command: string, status: string, exitCode: number | null}>, failedCheck: {key: string, command: string, status: string, exitCode: number | null} | null}}
 */
export function runNextPublicDryRun(options = {}) {
  const run = options.run ?? ((check) => defaultRun(check, options));
  const checks = [];
  let failedCheck = null;

  for (const check of nextPublicDryRunChecks) {
    const result = run(check);
    const exitCode = typeof result.status === 'number' ? result.status : 1;
    const checkResult = {
      key: check.key,
      command: check.command,
      status: exitCode === 0 ? 'pass' : 'blocker',
      exitCode,
    };

    checks.push(checkResult);

    if (exitCode !== 0) {
      failedCheck = checkResult;
      break;
    }
  }

  return {
    status: failedCheck === null ? 'pass' : 'blocker',
    productionEffect: 'none',
    activationAvailable: false,
    checks,
    failedCheck,
  };
}

function printResult(result) {
  if (result.status === 'pass') {
    console.log('Next public frontend dry-run checks passed. No production routing was changed.');
    return;
  }

  console.error('Next public frontend dry-run checks failed:');
  if (result.failedCheck) {
    console.error(`- ${result.failedCheck.key}: ${result.failedCheck.command}`);
  }
  console.error('No production routing was changed.');
}

const isCli = process.argv[1] && fileURLToPath(import.meta.url) === path.resolve(process.argv[1]);

if (isCli) {
  const result = runNextPublicDryRun();
  printResult(result);
  process.exit(result.status === 'pass' ? 0 : 1);
}
