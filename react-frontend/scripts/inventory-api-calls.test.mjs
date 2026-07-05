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
const scriptPath = path.join(__dirname, 'inventory-api-calls.mjs');

test('inventory-api-calls classifies modules and contract hints from runtime sources', () => {
  const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'nexus-api-inventory-'));
  const src = path.join(tmp, 'src');
  const out = path.join(tmp, 'out');

  fs.mkdirSync(path.join(src, 'pages', 'auth'), { recursive: true });
  fs.mkdirSync(path.join(src, 'admin', 'modules', 'courses'), { recursive: true });
  fs.mkdirSync(path.join(src, 'pages', 'caring-community'), { recursive: true });

  fs.writeFileSync(path.join(src, 'pages', 'auth', 'LoginPage.tsx'), `
    import { api } from '@/lib/api';
    export function login(email: string) {
      return api.post('/auth/login', { email }, { skipAuth: true, skipTenant: true });
    }
  `);

  fs.writeFileSync(path.join(src, 'admin', 'modules', 'courses', 'CoursesAdmin.tsx'), `
    import { api } from '@/lib/api';
    export function loadCourses(page: number) {
      return api.get(\`/v2/admin/courses?page=\${page}\`);
    }
  `);

  fs.writeFileSync(path.join(src, 'pages', 'caring-community', 'WarmthPassPage.tsx'), `
    import { api } from '@/lib/api';
    export function uploadEvidence(file: File) {
      return api.upload('/v2/caring-community/evidence', file, 'evidence_file', { onUploadProgress: () => {} });
    }
    export function downloadStatement(id: number) {
      return api.download(\`/v2/caring-community/statements/\${id}/export\`);
    }
  `);

  execFileSync(process.execPath, [scriptPath, '--src', src, '--out', out], { encoding: 'utf8' });

  const report = JSON.parse(fs.readFileSync(path.join(out, 'api-calls.json'), 'utf8'));
  assert.equal(report.summary.scanned_files, 3);
  assert.equal(report.summary.call_sites, 4);
  assert.deepEqual(report.summary.by_module, {
    admin: 1,
    auth: 1,
    'caring-community': 2,
  });

  const byEndpoint = new Map(report.endpoints.map((endpoint) => [endpoint.endpoint, endpoint]));
  assert.equal(byEndpoint.get('/auth/login').module, 'auth');
  assert.equal(byEndpoint.get('/auth/login').auth_required, false);
  assert.equal(byEndpoint.get('/auth/login').tenant_required, false);
  assert.equal(byEndpoint.get('/auth/login').priority, 'P0');

  const courses = byEndpoint.get('/v2/admin/courses?page={page}');
  assert.equal(courses.module, 'admin');
  assert.equal(courses.auth_required, true);
  assert.equal(courses.tenant_required, true);
  assert.equal(courses.priority, 'P2');

  const upload = byEndpoint.get('/v2/caring-community/evidence');
  assert.equal(upload.module, 'caring-community');
  assert.equal(upload.upload_field, 'evidence_file');
  assert.match(upload.contract_risk, /upload/);
  assert.equal(upload.priority, 'P1');

  const download = byEndpoint.get('/v2/caring-community/statements/{id}/export');
  assert.match(download.contract_risk, /download/);
});

test('inventory-api-calls matches compatible Laravel OpenAPI operations and keeps ASP.NET unchecked', () => {
  const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'nexus-api-inventory-openapi-'));
  const src = path.join(tmp, 'src');
  const out = path.join(tmp, 'out');
  const openapi = path.join(tmp, 'openapi.json');

  fs.mkdirSync(path.join(src, 'pages', 'courses'), { recursive: true });
  fs.writeFileSync(path.join(src, 'pages', 'courses', 'CoursePage.tsx'), `
    import { api } from '@/lib/api';
    export function loadCourse(id: number) {
      return api.get(\`/v2/courses/\${id}\`);
    }
    export function createCourse(title: string) {
      return api.post('/v2/admin/courses', { title });
    }
    export function missingRoute() {
      return api.delete('/v2/admin/courses/archive-all');
    }
  `);

  fs.writeFileSync(openapi, JSON.stringify({
    openapi: '3.0.3',
    paths: {
      '/api/v2/courses/{id}': {
        get: { operationId: 'courses.show' },
      },
      '/api/v2/admin/courses': {
        post: { operationId: 'admin.courses.store' },
      },
    },
  }, null, 2));

  execFileSync(process.execPath, [scriptPath, '--src', src, '--out', out, '--openapi', openapi], { encoding: 'utf8' });

  const report = JSON.parse(fs.readFileSync(path.join(out, 'api-calls.json'), 'utf8'));
  const byEndpoint = new Map(report.endpoints.map((endpoint) => [endpoint.endpoint, endpoint]));

  const show = byEndpoint.get('/v2/courses/{id}');
  assert.equal(show.laravel_openapi_status, 'matched');
  assert.equal(show.laravel_openapi_path, '/api/v2/courses/{id}');
  assert.equal(show.laravel_operation_id, 'courses.show');
  assert.equal(show.aspnet_status, 'not_checked');
  assert.equal(show.aspnet_route, null);

  const create = byEndpoint.get('/v2/admin/courses');
  assert.equal(create.laravel_openapi_status, 'matched');
  assert.equal(create.laravel_openapi_path, '/api/v2/admin/courses');

  const missing = byEndpoint.get('/v2/admin/courses/archive-all');
  assert.equal(missing.laravel_openapi_status, 'not_found');
  assert.equal(missing.aspnet_status, 'not_checked');

  assert.equal(report.summary.laravel_openapi.matched, 2);
  assert.equal(report.summary.laravel_openapi.not_found, 1);
  assert.equal(report.summary.aspnet_status.not_checked, 3);
});
