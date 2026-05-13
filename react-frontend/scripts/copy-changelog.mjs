// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * copy-changelog.mjs
 *
 * Copies the root CHANGELOG.md into react-frontend/public/changelog.md so the
 * Vite build serves it as a static asset that the SPA can fetch and render
 * on the in-app /changelog route.
 *
 * Runs as part of `prebuild` and `predev` (see package.json). Failing to find
 * the source file is a hard error — we never want the in-app changelog page
 * to silently fall back to a stale copy.
 */

import { copyFile, mkdir, access } from 'node:fs/promises';
import { constants } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, '..', '..');
const source = path.join(repoRoot, 'CHANGELOG.md');
const target = path.resolve(__dirname, '..', 'public', 'changelog.md');

try {
  await access(source, constants.R_OK);
} catch {
  console.error(`copy-changelog: source not found at ${source}`);
  process.exit(1);
}

await mkdir(path.dirname(target), { recursive: true });
await copyFile(source, target);
console.log(`copy-changelog: ${path.relative(repoRoot, source)} → ${path.relative(repoRoot, target)}`);
