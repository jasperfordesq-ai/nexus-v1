// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Sync the shared public-frontend presentational core into this app.
 *
 * Source of truth: react-frontend/src/public-shared/ (the Vite SPA compiles it
 * directly). This copies it to next-public-frontend/src/_shared/ so Next compiles
 * it with THIS app's own single React (no cross-package dedup hazard, narrow
 * Turbopack root, fast dev). The copy is gitignored and regenerated on
 * predev/prebuild/pretest, so there is exactly one editable source of truth.
 *
 * Drift is impossible: both hosts render the same component source, and a CI
 * parity test renders it under both host runtimes and asserts identical HTML.
 */

import { cpSync, existsSync, mkdirSync, rmSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const appRoot = resolve(here, '..');
const SRC = resolve(appRoot, '../react-frontend/src/public-shared');
const DEST = resolve(appRoot, 'src/_shared');

if (!existsSync(SRC)) {
  console.error(`[sync-shared] source not found: ${SRC}`);
  process.exit(1);
}

rmSync(DEST, { recursive: true, force: true });
mkdirSync(DEST, { recursive: true });
cpSync(SRC, DEST, {
  recursive: true,
  filter: (s) => !/[\\/]__tests__[\\/]/.test(s) && !/\.test\.tsx?$/.test(s),
});

console.log(`[sync-shared] copied ${SRC} -> ${DEST}`);
