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
const scriptPath = path.join(__dirname, 'generate-certification-worksheets.mjs');

test('generate-certification-worksheets writes module worksheets without certifying ASP.NET', () => {
  const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'nexus-cert-worksheets-'));
  const inventoryPath = path.join(tmp, 'api-calls.json');
  const outDir = path.join(tmp, 'worksheets');

  fs.writeFileSync(inventoryPath, JSON.stringify({
    summary: {
      unique_endpoints: 3,
      aspnet_status: { not_checked: 3 },
    },
    endpoints: [
      {
        priority: 'P0',
        module: 'auth',
        method: 'POST',
        endpoint: '/auth/login',
        laravel_openapi_status: 'not_found',
        laravel_openapi_path: null,
        aspnet_status: 'not_checked',
        contract_risk: 'auth',
        auth_required: false,
        tenant_required: true,
        files: [{ file: 'src/contexts/AuthContext.tsx', lines: [221] }],
      },
      {
        priority: 'P1',
        module: 'marketplace',
        method: 'GET',
        endpoint: '/v2/marketplace/listings',
        laravel_openapi_status: 'matched',
        laravel_openapi_path: '/api/v2/marketplace/listings',
        aspnet_status: 'not_checked',
        contract_risk: 'standard-json',
        auth_required: true,
        tenant_required: true,
        files: [{ file: 'src/pages/marketplace/MarketplacePage.tsx', lines: [42] }],
      },
      {
        priority: 'P1',
        module: 'marketplace',
        method: 'POST',
        endpoint: '/v2/upload',
        laravel_openapi_status: 'matched',
        laravel_openapi_path: '/api/v2/upload',
        aspnet_status: 'not_checked',
        contract_risk: 'upload',
        auth_required: true,
        tenant_required: true,
        upload_field: 'image',
        files: [{ file: 'src/pages/marketplace/SellerPage.tsx', lines: [88] }],
      },
    ],
  }, null, 2));

  execFileSync(process.execPath, [scriptPath, '--inventory', inventoryPath, '--out', outDir], { encoding: 'utf8' });

  const index = fs.readFileSync(path.join(outDir, 'README.md'), 'utf8');
  assert.match(index, /ASP.NET compatibility is not certified/);
  assert.match(index, /\| auth \| 1 \| 1 \| 0 \|/);
  assert.match(index, /\| marketplace \| 2 \| 0 \| 2 \|/);

  const auth = fs.readFileSync(path.join(outDir, 'auth.md'), 'utf8');
  assert.match(auth, /# Auth Certification Worksheet/);
  assert.match(auth, /ASP.NET status starts as `not_checked`/);
  assert.match(auth, /\| P0 \| POST \| `\/auth\/login` \| not_found \| not_checked \|/);
  assert.match(auth, /Required proof before certification/);

  const marketplace = fs.readFileSync(path.join(outDir, 'marketplace.md'), 'utf8');
  assert.match(marketplace, /\| P1 \| GET \| `\/v2\/marketplace\/listings` \| matched: `\/api\/v2\/marketplace\/listings` \| not_checked \|/);
  assert.match(marketplace, /\| P1 \| POST \| `\/v2\/upload` \| matched: `\/api\/v2\/upload` \| not_checked \|/);
});
