// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { readdir } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const localesDir = path.resolve(__dirname, '..', 'public', 'locales');
const allowedExtensions = new Set(['.json']);

async function collectFiles(dir) {
  const entries = await readdir(dir, { withFileTypes: true });
  const files = [];

  for (const entry of entries) {
    const entryPath = path.join(dir, entry.name);

    if (entry.isDirectory()) {
      files.push(...await collectFiles(entryPath));
      continue;
    }

    if (entry.isFile()) {
      files.push(entryPath);
    }
  }

  return files;
}

const files = await collectFiles(localesDir);
const unsafeFiles = files.filter((file) => !allowedExtensions.has(path.extname(file)));

if (unsafeFiles.length > 0) {
  const relativePaths = unsafeFiles
    .map((file) => path.relative(localesDir, file))
    .sort()
    .join('\n');

  throw new Error(`Public locales must be JSON-only. Remove these files:\n${relativePaths}`);
}

console.log(`[i18n] verified ${files.length} public locale JSON files`);
