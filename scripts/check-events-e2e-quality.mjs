// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { readdirSync, readFileSync } from 'node:fs';
import { extname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(fileURLToPath(new URL('..', import.meta.url)));
const eventsSpecDirectory = join(root, 'e2e', 'tests', 'events');
const pageObject = join(root, 'e2e', 'page-objects', 'EventsPage.ts');

const rules = [
  {
    name: 'disabled test',
    pattern: /\b(?:test(?:\.describe)?|it|describe)\.(?:skip|fixme|todo)\s*\(/g,
  },
  {
    name: 'constant true fallback',
    pattern: /\|\|\s*true\b|\btrue\s*\|\|/g,
  },
  {
    name: 'constant false conjunction',
    pattern: /&&\s*false\b|\bfalse\s*&&/g,
  },
  {
    name: 'constant assertion',
    pattern: /\bexpect\s*\(\s*(?:true|false)\s*\)/g,
  },
  {
    name: 'non-negative count assertion',
    pattern: /\.toBeGreaterThanOrEqual\(\s*0\s*\)/g,
  },
];

function collectTypeScriptFiles(directory) {
  return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const path = join(directory, entry.name);
    if (entry.isDirectory()) {
      return collectTypeScriptFiles(path);
    }

    return ['.ts', '.tsx'].includes(extname(entry.name)) ? [path] : [];
  });
}

function lineAndColumn(source, index) {
  const before = source.slice(0, index);
  const lines = before.split(/\r?\n/);
  return { line: lines.length, column: (lines.at(-1)?.length ?? 0) + 1 };
}

const targets = [...collectTypeScriptFiles(eventsSpecDirectory), pageObject];
const findings = [];

for (const file of targets) {
  const source = readFileSync(file, 'utf8');

  for (const rule of rules) {
    rule.pattern.lastIndex = 0;
    for (const match of source.matchAll(rule.pattern)) {
      const location = lineAndColumn(source, match.index ?? 0);
      findings.push({
        file: relative(root, file).replaceAll('\\', '/'),
        line: location.line,
        column: location.column,
        rule: rule.name,
        excerpt: match[0],
      });
    }
  }
}

if (findings.length > 0) {
  process.stderr.write('[events-e2e-quality] Events E2E quality gate failed:\n');
  for (const finding of findings) {
    process.stderr.write(
      `  ${finding.file}:${finding.line}:${finding.column} ${finding.rule}: ${finding.excerpt}\n`,
    );
  }
  process.exit(1);
}

process.stdout.write(
  `[events-e2e-quality] Passed: ${targets.length} file(s), zero disabled tests and zero tautological assertions.\n`,
);
