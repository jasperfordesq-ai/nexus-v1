/**
 * Add SPDX license headers to all source files.
 * Matches the V2 format exactly:
 *
 * // Copyright © 2024–2026 Jasper Ford
 * // SPDX-License-Identifier: AGPL-3.0-or-later
 * // Author: Jasper Ford
 * // See NOTICE file for attribution and acknowledgements.
 *
 * Usage: node scripts/add-spdx-headers.mjs [--dry-run]
 */

import { readdir, readFile, writeFile } from 'fs/promises';
import { join, extname } from 'path';

const HEADER_LINES = [
  'Copyright © 2024–2026 Jasper Ford',
  'SPDX-License-Identifier: AGPL-3.0-or-later',
  'Author: Jasper Ford',
  'See NOTICE file for attribution and acknowledgements.',
];

const SPDX_MARKER = 'SPDX-License-Identifier';

const DRY_RUN = process.argv.includes('--dry-run');

// Directories to process
const DIRS = [
  { path: 'src', extensions: ['.php'] },
  { path: 'httpdocs', extensions: ['.php'] },
  { path: 'tests', extensions: ['.php'] },
  { path: join('react-frontend', 'src'), extensions: ['.ts', '.tsx'] },
];

// Directories to skip
const SKIP_DIRS = ['node_modules', 'vendor', 'dist', '.git', '__pycache__'];

async function* walkDir(dir, extensions) {
  const entries = await readdir(dir, { withFileTypes: true });
  for (const entry of entries) {
    const fullPath = join(dir, entry.name);
    if (entry.isDirectory()) {
      if (!SKIP_DIRS.includes(entry.name)) {
        yield* walkDir(fullPath, extensions);
      }
    } else if (extensions.includes(extname(entry.name))) {
      yield fullPath;
    }
  }
}

function makePhpHeader() {
  return HEADER_LINES.map(line => `// ${line}`).join('\n') + '\n';
}

function makeTsHeader() {
  return HEADER_LINES.map(line => `// ${line}`).join('\n') + '\n';
}

function addHeaderToPhp(content) {
  // Already has SPDX header
  if (content.includes(SPDX_MARKER)) return null;

  // Find the <?php tag
  const phpTagMatch = content.match(/^<\?php\s*/);
  if (!phpTagMatch) return null; // Not a PHP file with opening tag

  const afterTag = content.slice(phpTagMatch[0].length);
  const header = makePhpHeader();

  // Insert header right after <?php with a blank line before and after
  return `<?php\n${header}\n${afterTag}`;
}

function addHeaderToTs(content) {
  // Already has SPDX header
  if (content.includes(SPDX_MARKER)) return null;

  const header = makeTsHeader();

  // If file starts with a docblock comment, insert header before it
  // Otherwise just prepend
  return `${header}\n${content}`;
}

async function processFile(filePath, ext) {
  const content = await readFile(filePath, 'utf-8');

  let newContent;
  if (ext === '.php') {
    newContent = addHeaderToPhp(content);
  } else {
    newContent = addHeaderToTs(content);
  }

  if (newContent === null) {
    return false; // Skipped (already has header or not applicable)
  }

  if (!DRY_RUN) {
    await writeFile(filePath, newContent, 'utf-8');
  }
  return true;
}

async function main() {
  let totalProcessed = 0;
  let totalSkipped = 0;
  let totalFiles = 0;

  console.log(DRY_RUN ? '=== DRY RUN ===' : '=== Adding SPDX headers ===');
  console.log('');

  for (const { path: dirPath, extensions } of DIRS) {
    let dirProcessed = 0;
    let dirSkipped = 0;

    try {
      for await (const filePath of walkDir(dirPath, extensions)) {
        totalFiles++;
        const ext = extname(filePath);
        const processed = await processFile(filePath, ext);
        if (processed) {
          dirProcessed++;
          totalProcessed++;
        } else {
          dirSkipped++;
          totalSkipped++;
        }
      }
    } catch (err) {
      if (err.code === 'ENOENT') {
        console.log(`  [SKIP] Directory not found: ${dirPath}`);
        continue;
      }
      throw err;
    }

    console.log(`  ${dirPath}: ${dirProcessed} updated, ${dirSkipped} skipped`);
  }

  console.log('');
  console.log(`Total: ${totalFiles} files found, ${totalProcessed} updated, ${totalSkipped} skipped`);
  if (DRY_RUN) {
    console.log('(Dry run — no files were modified)');
  }
}

main().catch(err => {
  console.error('Error:', err);
  process.exit(1);
});
