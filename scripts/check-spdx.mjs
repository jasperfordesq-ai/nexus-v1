// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { execFileSync } from 'child_process';
import { existsSync, readFileSync } from 'fs';

const EXCLUDED_FILENAMES = new Set([
  'expo-env.d.ts',
]);

const EXCLUDED_PATH_PARTS = new Set([
  'node_modules',
  'vendor',
  'dist',
  'build',
  '.git',
  '.expo',
]);

function isExcluded(path) {
  const normalized = path.replaceAll('\\', '/');
  const parts = normalized.split('/');
  const filename = parts.at(-1);

  return EXCLUDED_FILENAMES.has(filename) || parts.some((part) => EXCLUDED_PATH_PARTS.has(part));
}

const trackedFiles = execFileSync('git', ['ls-files', '*.php', '*.ts', '*.tsx'], {
  encoding: 'utf8',
})
  .split(/\r?\n/)
  .filter(Boolean)
  .filter((path) => !isExcluded(path));

const missing = [];

for (const file of trackedFiles) {
  if (!existsSync(file)) {
    continue;
  }
  const content = readFileSync(file, 'utf8');
  if (!content.includes('SPDX-License-Identifier')) {
    missing.push(file);
  }
}

const existingFiles = trackedFiles.filter((file) => existsSync(file));
const withHeader = existingFiles.length - missing.length;

console.log(`tracked source: ${existingFiles.length} files, ${withHeader} with header, ${missing.length} missing`);
if (missing.length > 0) {
  missing.forEach((file) => console.log(`  MISSING: ${file}`));
}

if (missing.length === 0) {
  console.log('ALL FILES HAVE SPDX HEADERS');
} else {
  console.error(`ERROR: ${missing.length} files are missing SPDX headers!`);
  process.exitCode = 1;
}
