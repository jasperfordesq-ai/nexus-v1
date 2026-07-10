// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { readdirSync, readFileSync } from 'node:fs';
import { join, relative, sep } from 'node:path';
import { describe, expect, it } from 'vitest';

const sourceRoot = join(process.cwd(), 'src');

function productionTsxFiles(directory: string): string[] {
  return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const path = join(directory, entry.name);
    if (entry.isDirectory()) return productionTsxFiles(path);
    if (!entry.name.endsWith('.tsx') || entry.name.includes('.test.') || entry.name.includes('.spec.')) {
      return [];
    }
    return [path];
  });
}

function lineNumber(source: string, offset: number): number {
  return source.slice(0, offset).split('\n').length;
}

describe('tenant-aware internal navigation', () => {
  it('does not introduce literal root SPA destinations at navigation sinks', () => {
    const findings: string[] = [];

    for (const file of productionTsxFiles(sourceRoot)) {
      const source = readFileSync(file, 'utf8');
      const displayPath = relative(process.cwd(), file).split(sep).join('/');

      for (const match of source.matchAll(/<[^>]+\bto=["']\/(?!\/)[^"']*["'][^>]*>/g)) {
        const markup = match[0];
        // These redirect components accept an unqualified route by design and
        // apply tenantPath internally before rendering React Router Navigate.
        if (/<Tenant(?:Param|Splat)?Redirect\b/.test(markup)) continue;
        findings.push(`${displayPath}:${lineNumber(source, match.index)} direct to`);
      }

      for (const match of source.matchAll(/\bnavigate\(\s*["']\/(?!\/)/g)) {
        findings.push(`${displayPath}:${lineNumber(source, match.index)} direct navigate`);
      }

      for (const match of source.matchAll(/<a\b[^>]*\bhref=["'](\/[^"']*)["'][^>]*>/g)) {
        const destination = match[1] ?? '';
        if (destination.startsWith('/api/')) continue;
        // Unknown-tenant recovery intentionally performs a fresh document load
        // to clear TenantShell's document-scoped sticky slug (documented there).
        if (
          displayPath.endsWith('components/routing/TenantShell.tsx') &&
          (destination === '/' || destination === '/login')
        ) {
          continue;
        }
        findings.push(
          `${displayPath}:${lineNumber(source, match.index)} direct anchor ${destination}`,
        );
      }
    }

    expect(findings, findings.join('\n')).toEqual([]);
  });
});
