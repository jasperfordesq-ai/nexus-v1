// Copyright (C) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Generate a local Laravel-mode smoke-test manifest.
 *
 * This is a checklist artifact only. It does not run Playwright, does not touch
 * production, and does not certify ASP.NET compatibility.
 */

import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const frontendRoot = path.resolve(__dirname, '..');
const repoRoot = path.resolve(frontendRoot, '..');
const defaultOutDir = path.join(repoRoot, '.local-docs-archive', 'react-laravel-smoke', 'latest');

const workflows = [
  {
    id: 'auth-login',
    priority: 'P0',
    route: '/login',
    proves: 'Laravel auth request/response envelope, trusted-device handling, and session bootstrap remain compatible.',
  },
  {
    id: 'tenant-bootstrap',
    priority: 'P0',
    route: '/',
    proves: 'Tenant detection, feature/module flags, menus, and default tenant state load in Laravel mode.',
  },
  {
    id: 'dashboard-shell',
    priority: 'P0',
    route: '/dashboard',
    proves: 'Authenticated shell, navigation, current user, and dashboard API calls load in Laravel mode.',
  },
  {
    id: 'feed-read-write',
    priority: 'P1',
    route: '/feed',
    proves: 'Feed list, create/update interactions, comments, reactions, and pagination contracts remain Laravel-compatible.',
  },
  {
    id: 'messages-thread',
    priority: 'P1',
    route: '/messages',
    proves: 'Conversation list, thread read, send message, unread count, and realtime-adjacent state still work against Laravel.',
  },
  {
    id: 'wallet-transfer',
    priority: 'P1',
    route: '/wallet',
    proves: 'Wallet balance, transaction history, and transfer workflow contracts remain Laravel-compatible.',
  },
  {
    id: 'upload-asset',
    priority: 'P1',
    route: '/admin/newsletter-builder',
    proves: 'Multipart upload fields, returned asset URLs, and email-safe image handling still match Laravel.',
  },
  {
    id: 'admin-dashboard',
    priority: 'P1',
    route: '/admin',
    proves: 'Admin auth, tenant admin policy, dashboard stats, and admin navigation contracts remain Laravel-compatible.',
  },
  {
    id: 'marketplace-browse',
    priority: 'P2',
    route: '/marketplace',
    proves: 'Marketplace list/detail filters and listing response envelopes remain Laravel-compatible.',
  },
  {
    id: 'caring-community-dashboard',
    priority: 'P2',
    route: '/caring-community',
    proves: 'Caring Community landing/member calls remain Laravel-compatible for future ASP.NET module certification.',
  },
];

function parseArgs(argv) {
  const options = {
    outDir: defaultOutDir,
  };

  for (let index = 0; index < argv.length; index += 1) {
    const arg = argv[index];
    if (arg === '--out') {
      const value = argv[index + 1];
      if (!value) throw new Error('--out requires a directory');
      options.outDir = path.resolve(process.cwd(), value);
      index += 1;
    } else if (arg === '--help' || arg === '-h') {
      console.log('Usage: node scripts/generate-laravel-smoke-manifest.mjs [--out <dir>]');
      process.exit(0);
    } else {
      throw new Error(`Unknown argument: ${arg}`);
    }
  }

  return options;
}

function manifest() {
  return {
    generated_at: new Date().toISOString(),
    backend_target: 'laravel',
    aspnet_status: 'not_applicable',
    certifies_aspnet: false,
    required_before_future_dual_backend_claims: true,
    commands: {
      guardrails: 'npm --prefix react-frontend run check:dual-backend-prep',
      laravel_build: 'npm --prefix react-frontend run build:laravel',
      manual_smoke: 'Run each workflow below against the Laravel backend/default frontend mode.',
    },
    workflows: workflows.map((workflow) => ({
      ...workflow,
      status: 'manual_not_run',
      evidence_required: [
        'Laravel mode selected or defaulted.',
        'No ASP.NET backend target selected.',
        'Screen loads without API shape errors.',
        'Primary action succeeds against Laravel.',
        'No production page/component backend-specific conditional added.',
      ],
    })),
  };
}

function markdown(data) {
  const lines = [
    '# Laravel Mode Smoke Manifest',
    '',
    'This manifest is local preparation material for protecting the production Laravel React frontend.',
    'This manifest does not run or certify ASP.NET.',
    '',
    '## Rules',
    '',
    '- Laravel is the backend target.',
    '- ASP.NET status is `not_applicable` for this manifest.',
    '- All workflows start as `manual_not_run`.',
    '- Passing this manifest in the future only proves Laravel-mode safety, not ASP.NET compatibility.',
    '',
    '## Commands',
    '',
    `- Guardrails: \`${data.commands.guardrails}\``,
    `- Laravel build: \`${data.commands.laravel_build}\``,
    '',
    '## Workflows',
    '',
    '| ID | Priority | Route | Status | Proves |',
    '| --- | --- | --- | --- | --- |',
  ];

  for (const workflow of data.workflows) {
    lines.push(`| ${workflow.id} | ${workflow.priority} | \`${workflow.route}\` | ${workflow.status} | ${workflow.proves.replaceAll('|', '\\|')} |`);
  }

  lines.push('');
  return lines.join('\n');
}

function main() {
  const options = parseArgs(process.argv.slice(2));
  const data = manifest();

  fs.mkdirSync(options.outDir, { recursive: true });
  fs.writeFileSync(path.join(options.outDir, 'laravel-smoke-manifest.json'), `${JSON.stringify(data, null, 2)}\n`, 'utf8');
  fs.writeFileSync(path.join(options.outDir, 'laravel-smoke-manifest.md'), markdown(data), 'utf8');

  console.log(`generate-laravel-smoke-manifest: wrote ${data.workflows.length} workflows to ${path.relative(repoRoot, options.outDir)}`);
}

main();
