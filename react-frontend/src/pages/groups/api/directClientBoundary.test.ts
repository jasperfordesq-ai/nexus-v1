// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { readdirSync, readFileSync } from 'node:fs';
import { dirname, extname, join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import * as ts from 'typescript';
import { describe, expect, it } from 'vitest';

const SHARED_API_MODULE = '@/lib/api';
const API_DIRECTORY = dirname(fileURLToPath(import.meta.url));
const GROUPS_DIRECTORY = dirname(API_DIRECTORY);

interface DirectClientDependency {
  file: string;
  imports: string[];
  calls: string[];
}

/**
 * Transitional architecture manifest. Each entry identifies a legacy source
 * that still reaches through the Groups adapter boundary to the shared client.
 * Remove entries as consumers migrate; do not add entries for new work.
 *
 * Calls are structural fingerprints (method + endpoint expression), not a raw
 * total. Duplicate fingerprints remain duplicated, so an extra call to an
 * already-used endpoint still fails the ratchet.
 */
const LEGACY_DIRECT_CLIENT_ALLOWLIST = [
  {
    file: 'components/GroupNotificationPrefs.test.tsx',
    imports: ['named:api->api'],
    calls: [],
  },
  {
    file: 'components/WelcomeConfigPanel.test.tsx',
    imports: ['named:api->api'],
    calls: [],
  },
  {
    file: 'CreateGroupPage.test.tsx',
    imports: ['named:api->api'],
    calls: [],
  },
  {
    file: 'GroupsPage.test.tsx',
    imports: ['named:api->api'],
    calls: [],
  },
] as const satisfies readonly DirectClientDependency[];

function normalizePath(path: string): string {
  return path.replaceAll('\\', '/');
}

function comparePortableText(left: string, right: string): number {
  const foldedLeft = left.toLowerCase();
  const foldedRight = right.toLowerCase();
  if (foldedLeft < foldedRight) return -1;
  if (foldedLeft > foldedRight) return 1;
  if (left < right) return -1;
  if (left > right) return 1;
  return 0;
}

function collectSourceFiles(directory: string): string[] {
  const files: string[] = [];

  for (const entry of readdirSync(directory, { withFileTypes: true })) {
    const absolutePath = join(directory, entry.name);
    if (entry.isDirectory()) {
      if (absolutePath !== API_DIRECTORY) files.push(...collectSourceFiles(absolutePath));
      continue;
    }

    if (!entry.isFile()) continue;
    if (!/\.(?:ts|tsx)$/.test(entry.name) || entry.name.endsWith('.d.ts')) continue;
    files.push(absolutePath);
  }

  return files.sort(comparePortableText);
}

function describeImports(sourceFile: ts.SourceFile): { bindings: Set<string>; descriptions: string[] } {
  const bindings = new Set<string>();
  const descriptions: string[] = [];

  for (const statement of sourceFile.statements) {
    if (!ts.isImportDeclaration(statement)) continue;
    if (!ts.isStringLiteral(statement.moduleSpecifier)) continue;
    if (statement.moduleSpecifier.text !== SHARED_API_MODULE) continue;

    const clause = statement.importClause;
    if (!clause || clause.isTypeOnly) continue;

    if (clause.name) {
      bindings.add(clause.name.text);
      descriptions.push(`default:${clause.name.text}`);
    }

    const namedBindings = clause.namedBindings;
    if (namedBindings && ts.isNamespaceImport(namedBindings)) {
      bindings.add(namedBindings.name.text);
      descriptions.push(`namespace:${namedBindings.name.text}`);
    } else if (namedBindings) {
      for (const element of namedBindings.elements) {
        if (element.isTypeOnly) continue;
        const importedName = element.propertyName?.text ?? element.name.text;
        bindings.add(element.name.text);
        descriptions.push(`named:${importedName}->${element.name.text}`);
      }
    }
  }

  function visitUnsupportedImport(node: ts.Node): void {
    if (
      ts.isCallExpression(node)
      && node.expression.kind === ts.SyntaxKind.ImportKeyword
      && ts.isStringLiteralLike(node.arguments[0])
      && node.arguments[0].text === SHARED_API_MODULE
    ) {
      descriptions.push('dynamic-import');
    } else if (
      ts.isCallExpression(node)
      && ts.isIdentifier(node.expression)
      && node.expression.text === 'require'
      && ts.isStringLiteralLike(node.arguments[0])
      && node.arguments[0].text === SHARED_API_MODULE
    ) {
      descriptions.push('require');
    } else if (
      ts.isExportDeclaration(node)
      && node.moduleSpecifier
      && ts.isStringLiteral(node.moduleSpecifier)
      && node.moduleSpecifier.text === SHARED_API_MODULE
    ) {
      descriptions.push('re-export');
    } else if (
      ts.isImportEqualsDeclaration(node)
      && ts.isExternalModuleReference(node.moduleReference)
      && node.moduleReference.expression
      && ts.isStringLiteral(node.moduleReference.expression)
      && node.moduleReference.expression.text === SHARED_API_MODULE
    ) {
      descriptions.push(`import-equals:${node.name.text}`);
    }
    ts.forEachChild(node, visitUnsupportedImport);
  }

  visitUnsupportedImport(sourceFile);

  return { bindings, descriptions: descriptions.sort() };
}

function unwrapExpression(expression: ts.Expression): ts.Expression {
  let current = expression;
  while (ts.isParenthesizedExpression(current) || ts.isNonNullExpression(current)) {
    current = current.expression;
  }
  return current;
}

function describeDirectMethod(
  expression: ts.LeftHandSideExpression,
  bindings: ReadonlySet<string>,
): string | null {
  const unwrapped = unwrapExpression(expression);
  if (ts.isIdentifier(unwrapped)) {
    return bindings.has(unwrapped.text) ? '<call>' : null;
  }

  if (ts.isPropertyAccessExpression(unwrapped)) {
    const receiver = unwrapExpression(unwrapped.expression);
    return ts.isIdentifier(receiver) && bindings.has(receiver.text)
      ? unwrapped.name.text
      : null;
  }

  if (ts.isElementAccessExpression(unwrapped)) {
    const receiver = unwrapExpression(unwrapped.expression);
    const key = unwrapped.argumentExpression;
    return ts.isIdentifier(receiver) && bindings.has(receiver.text) && ts.isStringLiteralLike(key)
      ? key.text
      : null;
  }

  return null;
}

function normalizeExpressionText(node: ts.Node, sourceFile: ts.SourceFile): string {
  return node.getText(sourceFile).replace(/\s+/g, ' ').trim();
}

function describeEndpoint(argument: ts.Expression | undefined, sourceFile: ts.SourceFile): string {
  if (!argument) return '<no-argument>';
  if (ts.isStringLiteralLike(argument)) return argument.text;
  return normalizeExpressionText(argument, sourceFile);
}

function describeCalls(sourceFile: ts.SourceFile, bindings: ReadonlySet<string>): string[] {
  const calls: string[] = [];

  function visit(node: ts.Node): void {
    if (ts.isCallExpression(node)) {
      const method = describeDirectMethod(node.expression, bindings);
      if (method) calls.push(`${method} ${describeEndpoint(node.arguments[0], sourceFile)}`);
    }
    ts.forEachChild(node, visit);
  }

  visit(sourceFile);
  return calls.sort();
}

function inventoryDirectClientDependencies(): DirectClientDependency[] {
  const dependencies: DirectClientDependency[] = [];

  for (const absolutePath of collectSourceFiles(GROUPS_DIRECTORY)) {
    const extension = extname(absolutePath);
    const sourceFile = ts.createSourceFile(
      absolutePath,
      readFileSync(absolutePath, 'utf8'),
      ts.ScriptTarget.Latest,
      true,
      extension === '.tsx' ? ts.ScriptKind.TSX : ts.ScriptKind.TS,
    );
    const { bindings, descriptions } = describeImports(sourceFile);
    if (descriptions.length === 0) continue;

    dependencies.push({
      file: normalizePath(relative(GROUPS_DIRECTORY, absolutePath)),
      imports: descriptions,
      calls: describeCalls(sourceFile, bindings),
    });
  }

  return dependencies.sort((left, right) => comparePortableText(left.file, right.file));
}

function describeWindowOpenCalls(sourceFile: ts.SourceFile): string[] {
  const calls: string[] = [];

  function visit(node: ts.Node): void {
    if (ts.isCallExpression(node)) {
      const expression = unwrapExpression(node.expression);
      if (ts.isPropertyAccessExpression(expression)) {
        const receiver = unwrapExpression(expression.expression);
        if (
          ts.isIdentifier(receiver)
          && (receiver.text === 'window' || receiver.text === 'globalThis')
          && expression.name.text === 'open'
        ) {
          calls.push(normalizeExpressionText(node, sourceFile));
        }
      } else if (ts.isElementAccessExpression(expression)) {
        const receiver = unwrapExpression(expression.expression);
        if (
          ts.isIdentifier(receiver)
          && (receiver.text === 'window' || receiver.text === 'globalThis')
          && ts.isStringLiteralLike(expression.argumentExpression)
          && expression.argumentExpression.text === 'open'
        ) {
          calls.push(normalizeExpressionText(node, sourceFile));
        }
      }
    }
    ts.forEachChild(node, visit);
  }

  visit(sourceFile);
  return calls.sort();
}

function inventoryProductionWindowOpenCalls(): Array<{ file: string; calls: string[] }> {
  return collectSourceFiles(GROUPS_DIRECTORY)
    .filter((absolutePath) => !/\.(?:test|spec)\.[jt]sx?$/.test(absolutePath))
    .map((absolutePath) => {
      const extension = extname(absolutePath);
      const sourceFile = ts.createSourceFile(
        absolutePath,
        readFileSync(absolutePath, 'utf8'),
        ts.ScriptTarget.Latest,
        true,
        extension === '.tsx' ? ts.ScriptKind.TSX : ts.ScriptKind.TS,
      );
      return {
        file: normalizePath(relative(GROUPS_DIRECTORY, absolutePath)),
        calls: describeWindowOpenCalls(sourceFile),
      };
    })
    .filter((entry) => entry.calls.length > 0);
}

describe('Groups API adapter boundary', () => {
  it('fingerprints aliases and duplicate calls structurally', () => {
    const fixture = ts.createSourceFile(
      'fixture.ts',
      [
        `import { api as client } from '${SHARED_API_MODULE}';`,
        "client.get('/v2/groups');",
        "client.get('/v2/groups');",
      ].join('\n'),
      ts.ScriptTarget.Latest,
      true,
      ts.ScriptKind.TS,
    );
    const { bindings, descriptions } = describeImports(fixture);

    expect(descriptions).toEqual(['named:api->client']);
    expect(describeCalls(fixture, bindings)).toEqual([
      'get /v2/groups',
      'get /v2/groups',
    ]);
  });

  it('surfaces non-static ways to bypass the adapter boundary', () => {
    const fixture = ts.createSourceFile(
      'fixture.ts',
      [
        `const dynamicClient = import('${SHARED_API_MODULE}');`,
        `const requiredClient = require('${SHARED_API_MODULE}');`,
        `export { api } from '${SHARED_API_MODULE}';`,
      ].join('\n'),
      ts.ScriptTarget.Latest,
      true,
      ts.ScriptKind.TS,
    );

    expect(describeImports(fixture).descriptions).toEqual([
      'dynamic-import',
      're-export',
      'require',
    ]);
  });

  it('detects browser-window download bypasses structurally', () => {
    const fixture = ts.createSourceFile(
      'fixture.ts',
      [
        "window.open('/protected');",
        "window['open']('/also-protected');",
        "globalThis.open('/third');",
      ].join('\n'),
      ts.ScriptTarget.Latest,
      true,
      ts.ScriptKind.TS,
    );

    expect(describeWindowOpenCalls(fixture)).toHaveLength(3);
  });

  it('keeps protected Groups downloads inside authenticated adapters', () => {
    expect(
      inventoryProductionWindowOpenCalls(),
      'Use an authenticated Groups adapter backed by api.download instead of window.open.',
    ).toEqual([]);
  });

  it('does not add or silently change direct shared-client dependencies', () => {
    expect(
      inventoryDirectClientDependencies(),
      'Migrate the consumer through pages/groups/api and tighten LEGACY_DIRECT_CLIENT_ALLOWLIST.',
    ).toEqual(LEGACY_DIRECT_CLIENT_ALLOWLIST);
  });
});
