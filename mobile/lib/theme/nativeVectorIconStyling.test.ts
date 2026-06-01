// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import fs from 'node:fs';
import path from 'node:path';

const SOURCE_ROOTS = ['app', 'components'];

function sourceFiles(dir: string): string[] {
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const fullPath = path.join(dir, entry.name);

    if (entry.isDirectory()) {
      return sourceFiles(fullPath);
    }

    if (!entry.name.endsWith('.tsx') || entry.name.endsWith('.test.tsx')) {
      return [];
    }

    return [fullPath];
  });
}

describe('native vector icon styling', () => {
  it('uses native color props instead of className on Ionicons', () => {
    const root = path.resolve(__dirname, '..', '..');
    const offenders = SOURCE_ROOTS.flatMap((sourceRoot) => sourceFiles(path.join(root, sourceRoot)))
      .flatMap((filePath) => {
        const source = fs.readFileSync(filePath, 'utf8');
        const matches = Array.from(source.matchAll(/<Ionicons\b[^>]*\bclassName=/gs));

        return matches.map((match) => {
          const line = source.slice(0, match.index).split(/\r?\n/).length;
          return `${path.relative(root, filePath)}:${line}`;
        });
      });

    expect(offenders).toEqual([]);
  });
});
