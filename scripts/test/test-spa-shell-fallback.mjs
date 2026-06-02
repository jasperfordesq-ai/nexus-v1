// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { readFileSync } from 'node:fs';
import { join } from 'node:path';

const root = process.cwd();
let failures = 0;

function read(path) {
  return readFileSync(join(root, path), 'utf8');
}

function assert(condition, message) {
  if (!condition) {
    failures += 1;
    console.error(`FAIL: ${message}`);
  }
}

for (const file of ['react-frontend/nginx.conf', 'react-frontend/nginx.bluegreen.conf']) {
  const text = read(file);
  assert(
    text.includes('/_spa.html'),
    `${file} must serve the clean SPA shell before falling back to rendered index.html`,
  );
  assert(
    text.includes('add_header X-Nexus-Spa-Shell "1"'),
    `${file} must mark SPA-shell navigation responses so Workbox can avoid caching offline/error HTML`,
  );

  for (const line of text.split('\n')) {
    const trimmed = line.trim();
    if (trimmed.startsWith('try_files') && trimmed.includes('/index.html')) {
      assert(
        trimmed.includes('/_spa.html'),
        `${file} try_files line must prefer /_spa.html before /index.html: ${trimmed}`,
      );
    }
  }
}

const viteConfig = read('react-frontend/vite.config.ts');
assert(
  viteConfig.includes("cacheName: 'nexus-html-shell-v2'"),
  'vite.config.ts must use a new HTML-shell runtime cache name to stop reading older cached offline shells',
);
assert(
  viteConfig.includes("headers: { 'X-Nexus-Spa-Shell': '1' }"),
  'vite.config.ts must only cache navigation HTML explicitly marked as the SPA shell',
);

const prerenderScript = read('react-frontend/scripts/prerender.mjs');
assert(
  prerenderScript.includes('SPA_FALLBACK_PATH') && prerenderScript.includes('writeSpaFallback'),
  'prerender.mjs must always write dist/_spa.html, including when NEXUS_SKIP_PRERENDER is set',
);

if (failures > 0) {
  process.exit(1);
}

console.log('SPA shell fallback checks passed.');
