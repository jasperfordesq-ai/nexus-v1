// Copyright (C) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import assert from 'node:assert/strict';
import { execFileSync, spawnSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const scriptPath = path.join(__dirname, 'check-backend-guardrails.mjs');

function writeFixture(root, overrides = {}) {
  const frontend = path.join(root, 'react-frontend');
  fs.mkdirSync(path.join(frontend, 'src', 'config'), { recursive: true });
  fs.mkdirSync(path.join(frontend, 'src', 'pages'), { recursive: true });
  fs.mkdirSync(path.join(frontend, 'src', 'components'), { recursive: true });

  fs.writeFileSync(path.join(frontend, 'package.json'), JSON.stringify({
    scripts: {
      dev: 'vite',
      build: 'vite build',
      'dev:dotnet': 'cross-env VITE_BACKEND_TARGET=dotnet npm run dev -- --mode dotnet',
      ...(overrides.scripts ?? {}),
    },
  }, null, 2));

  fs.writeFileSync(path.join(frontend, 'src', 'config', 'backendTarget.ts'), overrides.backendTarget ?? `
    export type BackendTarget = 'laravel' | 'dotnet';
    export function normalizeBackendTarget(value: string | undefined): BackendTarget {
      return value === 'dotnet' || value === 'laravel' ? value : 'laravel';
    }
  `);

  fs.writeFileSync(path.join(frontend, 'src', 'pages', 'Dashboard.tsx'), overrides.page ?? `
    export function Dashboard() {
      return <div>Dashboard</div>;
    }
  `);

  fs.writeFileSync(path.join(frontend, 'src', 'components', 'Widget.tsx'), overrides.component ?? `
    export function Widget() {
      return <div>Widget</div>;
    }
  `);

  return frontend;
}

test('check-backend-guardrails passes when Laravel remains default and pages stay backend-neutral', () => {
  const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'nexus-guardrails-ok-'));
  const frontend = writeFixture(tmp);

  const output = execFileSync(process.execPath, [scriptPath, '--root', frontend], { encoding: 'utf8' });

  assert.match(output, /Backend guardrails OK/);
});

test('check-backend-guardrails fails when pages use backend-specific flags', () => {
  const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'nexus-guardrails-bad-'));
  const frontend = writeFixture(tmp, {
    page: `
      import { isDotnetBackend } from '../config/backendTarget';
      export function Dashboard() {
        return isDotnetBackend ? <div>ASP.NET</div> : <div>Laravel</div>;
      }
    `,
  });

  const result = spawnSync(process.execPath, [scriptPath, '--root', frontend], { encoding: 'utf8' });

  assert.notEqual(result.status, 0);
  assert.match(result.stderr, /backend-specific frontend conditionals/);
});
