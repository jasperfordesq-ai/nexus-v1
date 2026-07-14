// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Reject fixed prose passed directly to admin API response helpers.
 *
 * React admin screens commonly surface `ApiResponse.error`. A literal message
 * in an Admin* controller therefore bypasses both Laravel's locale catalogue
 * and the React translation catalogue. This lightweight PHP-aware scanner
 * follows balanced call arguments so multiline calls are covered as well as
 * one-line calls.
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '..');
const CONTROLLER_ROOT = path.join(ROOT, 'app', 'Http', 'Controllers', 'Api');
const SERVICE_ROOT = path.join(ROOT, 'app', 'Services');
const SUPPRESSION_MARKER = 'admin-i18n-ignore';

const RESPONSE_MESSAGE_ARGUMENTS = new Map([
  ['respondWithError', 1],
  ['respondUnauthorized', 0],
  ['respondForbidden', 0],
  ['respondNotFound', 0],
  ['respondServerError', 0],
]);
const DISPLAY_FIELD_PATTERN = /(['"])(message|error|title|description|details|issue|label|name|author_name)\1\s*=>\s*(['"])((?:\\.|(?!\3)[\s\S])*)\3/g;

const results = [];
const controllerFiles = [
  ...fs.readdirSync(CONTROLLER_ROOT)
    .filter((name) => /^Admin.*\.php$/.test(name))
    .map((name) => path.join(CONTROLLER_ROOT, name)),
  ...walkPhp(path.join(CONTROLLER_ROOT, 'Admin')),
  ...walkPhp(path.join(CONTROLLER_ROOT, 'SuperAdmin')),
  ...[...walkPhp(path.join(CONTROLLER_ROOT, 'Verein'))]
    .filter((filePath) => path.basename(filePath).includes('Admin')),
].sort();

for (const filePath of controllerFiles) {
  auditController(filePath);
}

const serviceFiles = [
  path.join(SERVICE_ROOT, 'AchievementCampaignService.php'),
  path.join(SERVICE_ROOT, 'AI', 'AiModuleDocsService.php'),
  path.join(SERVICE_ROOT, 'BadgeDefinitionService.php'),
  path.join(SERVICE_ROOT, 'FederationFeatureService.php'),
  path.join(SERVICE_ROOT, 'GamificationService.php'),
  path.join(SERVICE_ROOT, 'OnboardingConfigService.php'),
];
auditAdminServiceMetadata(serviceFiles);

results.sort((left, right) => (
  left.file.localeCompare(right.file) || left.line - right.line || left.column - right.column
));

const listMode = process.argv.includes('--list') || process.argv.includes('--json');
if (process.argv.includes('--json')) {
  console.log(JSON.stringify(results, null, 2));
} else {
  console.log('============================================================');
  console.log('  Admin API Literal Check');
  console.log('============================================================');
  console.log(`  Controllers: ${controllerFiles.length}`);
  console.log(`  Services:    ${serviceFiles.length}`);
  console.log(`  Violations:  ${results.length}`);
  if (listMode && results.length > 0) {
    console.log('');
    for (const result of results) {
      console.log(`${result.file}:${result.line}:${result.column} [${result.method}] ${JSON.stringify(result.value)}`);
    }
  }
}

if (results.length > 0) {
  if (!process.argv.includes('--json')) {
    console.error('');
    console.error('FAIL: Admin API response prose must use Laravel translations or stable codes/parameters.');
    console.error('Use a line-scoped `admin-i18n-ignore: <reason>` only for invariant protocol text.');
  }
  process.exitCode = 1;
}

function auditController(filePath) {
  const source = fs.readFileSync(filePath, 'utf8');
  const lines = source.split(/\r?\n/);

  for (const [method, argumentIndex] of RESPONSE_MESSAGE_ARGUMENTS) {
    for (const call of findMethodCalls(source, method)) {
      const argument = call.arguments[argumentIndex];
      if (!argument) continue;
      const literal = leadingPhpString(argument.text);
      if (!literal || !looksLikeProse(literal.value)) continue;

      const location = lineAndColumn(source, argument.offset + literal.relativeOffset);
      const sourceLine = lines[location.line - 1] ?? '';
      const previousLine = lines[location.line - 2] ?? '';
      if (sourceLine.includes(SUPPRESSION_MARKER) || previousLine.includes(SUPPRESSION_MARKER)) continue;

      results.push({
        file: normalizePath(path.relative(ROOT, filePath)),
        line: location.line,
        column: location.column,
        method,
        value: literal.value,
      });
    }
  }

  let payloadMatch;
  DISPLAY_FIELD_PATTERN.lastIndex = 0;
  while ((payloadMatch = DISPLAY_FIELD_PATTERN.exec(source)) !== null) {
    const value = payloadMatch[4].replace(/\\(['"\\])/g, '$1').trim();
    if (!looksLikeProse(value)) continue;
    const valueOffset = payloadMatch.index + payloadMatch[0].lastIndexOf(payloadMatch[3] + payloadMatch[4]);
    const location = lineAndColumn(source, valueOffset);
    const sourceLine = lines[location.line - 1] ?? '';
    const previousLine = lines[location.line - 2] ?? '';
    if (sourceLine.includes(SUPPRESSION_MARKER) || previousLine.includes(SUPPRESSION_MARKER)) continue;
    results.push({
      file: normalizePath(path.relative(ROOT, filePath)),
      line: location.line,
      column: location.column,
      method: `payload:${payloadMatch[2]}`,
      value,
    });
  }
}

/**
 * Protect fixed metadata returned through admin controller dependencies.
 *
 * AI module docs and static badge definitions intentionally retain canonical
 * English domain content for persistence, notifications, and admin editing.
 * Their admin contracts are safe only when they also expose semantic codes so
 * React can localize unchanged built-ins without translating tenant-authored
 * content. The other services must not contain fixed display payload fields.
 */
function auditAdminServiceMetadata(filePaths) {
  const directDisplayFiles = filePaths.filter((filePath) => ![
    'AiModuleDocsService.php',
    'GamificationService.php',
  ].includes(path.basename(filePath)));

  for (const filePath of directDisplayFiles) {
    const source = fs.readFileSync(filePath, 'utf8');
    const lines = source.split(/\r?\n/);
    let match;
    DISPLAY_FIELD_PATTERN.lastIndex = 0;
    while ((match = DISPLAY_FIELD_PATTERN.exec(source)) !== null) {
      const value = match[4].replace(/\\(['"\\])/g, '$1').trim();
      if (!looksLikeProse(value)) continue;
      const location = lineAndColumn(source, match.index);
      const sourceLine = lines[location.line - 1] ?? '';
      const previousLine = lines[location.line - 2] ?? '';
      if (sourceLine.includes(SUPPRESSION_MARKER) || previousLine.includes(SUPPRESSION_MARKER)) continue;
      results.push({
        file: normalizePath(path.relative(ROOT, filePath)),
        line: location.line,
        column: location.column,
        method: `service-payload:${match[2]}`,
        value,
      });
    }
  }

  requireServiceContract(
    path.join(SERVICE_ROOT, 'AI', 'AiModuleDocsService.php'),
    ["$row->default_title_code", 'hash_equals'],
    'unchanged AI seed titles must expose default_title_code provenance',
  );
  requireServiceContract(
    path.join(SERVICE_ROOT, 'GamificationService.php'),
    ["'name_code'", "'description_code'"],
    'static badge definitions must expose semantic display codes',
  );
  requireServiceContract(
    path.join(SERVICE_ROOT, 'BadgeDefinitionService.php'),
    ["'name_code'", "'description_code'", 'custom_name', 'custom_description'],
    'database badge metadata must distinguish built-in copy from tenant overrides',
  );

  const campaignPath = path.join(SERVICE_ROOT, 'AchievementCampaignService.php');
  const campaignSource = fs.readFileSync(campaignPath, 'utf8');
  const constants = campaignSource.slice(
    campaignSource.indexOf('public const TYPES'),
    campaignSource.indexOf('private static array $typeToDbMap'),
  );
  const proseValue = constants.match(/=>\s*(['"])([^'"\r\n]*\s+[^'"\r\n]*)\1/);
  if (proseValue) {
    const location = lineAndColumn(campaignSource, campaignSource.indexOf(proseValue[0]));
    results.push({
      file: normalizePath(path.relative(ROOT, campaignPath)),
      line: location.line,
      column: location.column,
      method: 'service-contract:campaign-option',
      value: proseValue[2],
    });
  }
}

function requireServiceContract(filePath, fragments, message) {
  const source = fs.readFileSync(filePath, 'utf8');
  if (fragments.every((fragment) => source.includes(fragment))) return;
  results.push({
    file: normalizePath(path.relative(ROOT, filePath)),
    line: 1,
    column: 1,
    method: 'service-contract',
    value: message,
  });
}

function* findMethodCalls(source, method) {
  const pattern = new RegExp(`(?:->|::)\\s*${method}\\s*\\(`, 'g');
  let match;
  while ((match = pattern.exec(source)) !== null) {
    const openParen = source.indexOf('(', match.index);
    const parsed = parseArguments(source, openParen);
    if (!parsed) continue;
    yield parsed;
    pattern.lastIndex = parsed.endOffset;
  }
}

function parseArguments(source, openParen) {
  const argumentsList = [];
  let argumentStart = openParen + 1;
  let quote = null;
  let escaped = false;
  let roundDepth = 1;
  let squareDepth = 0;
  let curlyDepth = 0;

  for (let index = openParen + 1; index < source.length; index++) {
    const char = source[index];

    if (quote !== null) {
      if (escaped) {
        escaped = false;
      } else if (char === '\\') {
        escaped = true;
      } else if (char === quote) {
        quote = null;
      }
      continue;
    }

    if (char === "'" || char === '"') {
      quote = char;
      continue;
    }
    if (char === '(') roundDepth++;
    else if (char === ')') roundDepth--;
    else if (char === '[') squareDepth++;
    else if (char === ']') squareDepth--;
    else if (char === '{') curlyDepth++;
    else if (char === '}') curlyDepth--;

    const atArgumentBoundary = roundDepth === 1 && squareDepth === 0 && curlyDepth === 0 && char === ',';
    const atCallEnd = roundDepth === 0;
    if (!atArgumentBoundary && !atCallEnd) continue;

    argumentsList.push(trimmedSlice(source, argumentStart, index));
    if (atCallEnd) {
      return { arguments: argumentsList, endOffset: index + 1 };
    }
    argumentStart = index + 1;
  }

  return null;
}

function trimmedSlice(source, start, end) {
  const raw = source.slice(start, end);
  const leadingWhitespace = raw.match(/^\s*/)?.[0].length ?? 0;
  return {
    text: raw.slice(leadingWhitespace).trimEnd(),
    offset: start + leadingWhitespace,
  };
}

function leadingPhpString(argument) {
  const match = argument.match(/^(['"])((?:\\.|(?!\1)[\s\S])*)\1/);
  if (!match) return null;
  return {
    value: match[2].replace(/\\(['"\\])/g, '$1').trim(),
    relativeOffset: 0,
  };
}

function looksLikeProse(value) {
  if (!value || value.length < 2) return false;
  if (/^[A-Z0-9_]{2,}$/.test(value)) return false;
  if (/^(?:https?:\/\/|\/|[a-z0-9_.-]+\/[a-z0-9_.-]+$)/i.test(value)) return false;
  return /\p{L}/u.test(value) && (/\s/u.test(value) || /^\p{Lu}/u.test(value));
}

function lineAndColumn(source, offset) {
  const before = source.slice(0, offset);
  const line = (before.match(/\n/g)?.length ?? 0) + 1;
  const lastNewline = before.lastIndexOf('\n');
  return { line, column: offset - lastNewline };
}

function normalizePath(filePath) {
  return filePath.replaceAll('\\', '/');
}

function* walkPhp(directory) {
  if (!fs.existsSync(directory)) return;
  for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
    const fullPath = path.join(directory, entry.name);
    if (entry.isDirectory()) yield* walkPhp(fullPath);
    else if (entry.name.endsWith('.php')) yield fullPath;
  }
}
