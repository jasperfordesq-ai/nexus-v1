// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { gzipSync } from 'node:zlib';
import { readFile } from 'node:fs/promises';
import path from 'node:path';

const distDir = path.resolve(import.meta.dirname, '..', 'dist', 'assets');
const indexHtmlPath = path.resolve(import.meta.dirname, '..', 'dist', 'index.html');

const budgets = {
  mainJsGzipBytes: 380 * 1024,
  mainCssGzipBytes: 100 * 1024,
};

function formatKiB(bytes) {
  return `${(bytes / 1024).toFixed(1)} KiB`;
}

async function gzipSize(filePath) {
  const contents = await readFile(filePath);
  return gzipSync(contents).byteLength;
}

async function main() {
  const html = await readFile(indexHtmlPath, 'utf8');
  const mainJs = html.match(/<script[^>]+type="module"[^>]+src="\/assets\/([^"]+\.js)"/)?.[1];
  const mainCss = html.match(/<link[^>]+rel="stylesheet"[^>]+href="\/assets\/([^"]+\.css)"/)?.[1];
  const failures = [];

  if (!mainJs) {
    failures.push('Could not find the module entry script in dist/index.html.');
  } else {
    const size = await gzipSize(path.join(distDir, mainJs));
    if (size > budgets.mainJsGzipBytes) {
      failures.push(`${mainJs} gzip size ${formatKiB(size)} exceeds ${formatKiB(budgets.mainJsGzipBytes)}.`);
    }
  }

  if (!mainCss) {
    failures.push('Could not find the stylesheet entry in dist/index.html.');
  } else {
    const size = await gzipSize(path.join(distDir, mainCss));
    if (size > budgets.mainCssGzipBytes) {
      failures.push(`${mainCss} gzip size ${formatKiB(size)} exceeds ${formatKiB(budgets.mainCssGzipBytes)}.`);
    }
  }

  if (failures.length > 0) {
    console.error('[bundle-budget] failed');
    for (const failure of failures) {
      console.error(`- ${failure}`);
    }
    process.exit(1);
  }

  console.log('[bundle-budget] passed');
}

main().catch((error) => {
  console.error('[bundle-budget] failed to inspect dist assets');
  console.error(error);
  process.exit(1);
});
