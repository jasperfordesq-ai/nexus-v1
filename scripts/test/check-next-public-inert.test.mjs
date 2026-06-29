#!/usr/bin/env node
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';

import { checkNextPublicInert } from '../check-next-public-inert.mjs';

function makeFixture(overrides = {}) {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'nexus-next-inert-'));
  const files = {
    'config/app.php': `<?php
return [
    'next_public_frontend_routing_enabled' => filter_var(
        env('NEXT_PUBLIC_FRONTEND_ROUTING_ENABLED', false),
        FILTER_VALIDATE_BOOL,
    ),
];
`,
    'compose.bluegreen.yml': `services:
  app:
    image: nexus-php-app

  frontend:
    image: nexus-react-prod

  next_public_frontend:
    profiles:
      - next-public-shadow
    ports:
      - "\${NEXUS_NEXT_PUBLIC_PORT:-3200}:3000"
`,
    'scripts/deploy/bluegreen-deploy.sh': '#!/usr/bin/env bash\nset -euo pipefail\n',
    'scripts/deploy/apache/next-public-foundation-canary.conf.example': 'RewriteEngine On\nRewriteRule ^/$ http://127.0.0.1:${NEXUS_NEXT_PUBLIC_PORT}/ [P,L]\n',
    'react-frontend/scripts/prerender.mjs': 'export {};\n',
  };

  for (const [relativePath, contents] of Object.entries({ ...files, ...overrides })) {
    const fullPath = path.join(root, relativePath);
    fs.mkdirSync(path.dirname(fullPath), { recursive: true });
    fs.writeFileSync(fullPath, contents);
  }

  return root;
}

function issueCodes(result) {
  return result.issues.map((issue) => issue.code);
}

function test(name, fn) {
  try {
    fn();
    console.log(`ok - ${name}`);
  } catch (error) {
    console.error(`not ok - ${name}`);
    throw error;
  }
}

test('passes when the Next public frontend is shadow-only and not included by deploy', () => {
  const root = makeFixture();

  const result = checkNextPublicInert({ root, env: {} });

  assert.equal(result.status, 'pass');
  assert.deepEqual(result.issues, []);
  assert.equal(result.checks.cutoverEnvFlagOff.status, 'pass');
  assert.equal(result.checks.apacheCanaryTemplateNotIncluded.status, 'pass');
  assert.equal(result.checks.shadowComposeProfile.status, 'pass');
  assert.equal(result.checks.prerenderFallbackPresent.status, 'pass');
});

test('fails when the cutover environment flag is enabled', () => {
  const root = makeFixture();

  const result = checkNextPublicInert({
    root,
    env: { NEXT_PUBLIC_FRONTEND_ROUTING_ENABLED: 'true' },
  });

  assert.equal(result.status, 'blocker');
  assert.deepEqual(issueCodes(result), ['cutover_env_flag_enabled']);
});

test('fails when the Apache canary example is included by deploy automation', () => {
  const root = makeFixture({
    'scripts/deploy/bluegreen-deploy.sh': 'Include scripts/deploy/apache/next-public-foundation-canary.conf.example\n',
  });

  const result = checkNextPublicInert({ root, env: {} });

  assert.equal(result.status, 'blocker');
  assert.deepEqual(issueCodes(result), ['apache_canary_template_referenced_by_deploy']);
});

test('fails when the Next service is no longer behind the shadow compose profile', () => {
  const root = makeFixture({
    'compose.bluegreen.yml': `services:
  next_public_frontend:
    ports:
      - "\${NEXUS_NEXT_PUBLIC_PORT:-3200}:3000"
`,
  });

  const result = checkNextPublicInert({ root, env: {} });

  assert.equal(result.status, 'blocker');
  assert.deepEqual(issueCodes(result), ['next_public_compose_profile_missing']);
});

test('root package exposes the inertness check command', () => {
  const packageJson = JSON.parse(fs.readFileSync(path.join(process.cwd(), 'package.json'), 'utf8'));

  assert.equal(
    packageJson.scripts['check:next-public:inert'],
    'node scripts/check-next-public-inert.mjs',
  );
});
