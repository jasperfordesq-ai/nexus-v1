// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { spawnSync } from 'node:child_process';

const channel = process.argv[2];
if (!['staging', 'production'].includes(channel)) {
  console.error('Usage: node scripts/publish-update.mjs <staging|production>');
  process.exit(64);
}
if (channel === 'production' && process.env.NEXUS_APPROVE_PRODUCTION_OTA !== 'yes') {
  console.error('Production OTA requires NEXUS_APPROVE_PRODUCTION_OTA=yes after staging verification.');
  process.exit(77);
}
const status = spawnSync('git', ['status', '--porcelain'], { encoding: 'utf8' });
const branch = spawnSync('git', ['branch', '--show-current'], { encoding: 'utf8' });
if (status.status !== 0 || status.stdout.trim() || (channel === 'production' && branch.stdout.trim() !== 'main')) {
  console.error('OTA publication requires a clean worktree; production also requires main.');
  process.exit(1);
}
const sha = spawnSync('git', ['rev-parse', 'HEAD'], { encoding: 'utf8' }).stdout.trim();
const result = spawnSync('npx', ['eas-cli@latest', 'update', '--channel', channel, '--message', `NEXUS ${sha}`, '--environment', channel], {
  stdio: 'inherit',
  shell: process.platform === 'win32',
});
process.exit(result.status ?? 1);
