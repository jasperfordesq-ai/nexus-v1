// Copyright (C) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const scriptPath = path.join(__dirname, 'generate-laravel-smoke-manifest.mjs');

test('generate-laravel-smoke-manifest writes Laravel-only smoke checklist', () => {
  const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'nexus-laravel-smoke-'));
  const out = path.join(tmp, 'smoke');

  execFileSync(process.execPath, [scriptPath, '--out', out], { encoding: 'utf8' });

  const json = JSON.parse(fs.readFileSync(path.join(out, 'laravel-smoke-manifest.json'), 'utf8'));
  const markdown = fs.readFileSync(path.join(out, 'laravel-smoke-manifest.md'), 'utf8');

  assert.equal(json.backend_target, 'laravel');
  assert.equal(json.aspnet_status, 'not_applicable');
  assert.equal(json.certifies_aspnet, false);
  assert.ok(json.workflows.length >= 8);
  assert.ok(json.workflows.some((workflow) => workflow.id === 'auth-login'));
  assert.ok(json.workflows.some((workflow) => workflow.id === 'tenant-bootstrap'));
  assert.ok(json.workflows.every((workflow) => workflow.status === 'manual_not_run'));
  assert.match(markdown, /Laravel Mode Smoke Manifest/);
  assert.match(markdown, /This manifest does not run or certify ASP.NET/);
  assert.match(markdown, /\| auth-login \| P0 \|/);
});
