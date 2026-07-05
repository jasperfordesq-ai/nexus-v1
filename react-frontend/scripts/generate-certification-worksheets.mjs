// Copyright (C) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Generate local module certification worksheets from the React API inventory.
 *
 * These worksheets are preparation artifacts only. They organize future ASP.NET
 * backend parity work, but every ASP.NET row remains uncertified until separate
 * route/runtime smoke testing proves compatibility.
 */

import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const frontendRoot = path.resolve(__dirname, '..');
const repoRoot = path.resolve(frontendRoot, '..');
const defaultInventory = path.join(repoRoot, '.local-docs-archive', 'react-api-inventory', 'latest', 'api-calls.json');
const defaultOutDir = path.join(repoRoot, '.local-docs-archive', 'react-api-certification', 'latest');

function parseArgs(argv) {
  const options = {
    inventory: defaultInventory,
    outDir: defaultOutDir,
  };

  for (let index = 0; index < argv.length; index += 1) {
    const arg = argv[index];
    if (arg === '--inventory') {
      const value = argv[index + 1];
      if (!value) throw new Error('--inventory requires a file path');
      options.inventory = path.resolve(process.cwd(), value);
      index += 1;
    } else if (arg === '--out') {
      const value = argv[index + 1];
      if (!value) throw new Error('--out requires a directory');
      options.outDir = path.resolve(process.cwd(), value);
      index += 1;
    } else if (arg === '--help' || arg === '-h') {
      console.log('Usage: node scripts/generate-certification-worksheets.mjs [--inventory <file>] [--out <dir>]');
      process.exit(0);
    } else {
      throw new Error(`Unknown argument: ${arg}`);
    }
  }

  return options;
}

function slug(value) {
  return String(value || 'unclassified')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '') || 'unclassified';
}

function title(value) {
  return String(value || 'unclassified')
    .split(/[-_\s]+/)
    .filter(Boolean)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');
}

function groupByModule(endpoints) {
  const groups = new Map();
  for (const endpoint of endpoints) {
    const module = endpoint.module || 'unclassified';
    const items = groups.get(module) ?? [];
    items.push(endpoint);
    groups.set(module, items);
  }

  return [...groups.entries()]
    .map(([module, items]) => [
      module,
      items.sort((a, b) => `${a.priority} ${a.endpoint} ${a.method}`.localeCompare(`${b.priority} ${b.endpoint} ${b.method}`)),
    ])
    .sort(([a], [b]) => a.localeCompare(b));
}

function moduleSummary(module, endpoints) {
  const p0 = endpoints.filter((endpoint) => endpoint.priority === 'P0').length;
  const p1 = endpoints.filter((endpoint) => endpoint.priority === 'P1').length;
  const p2 = endpoints.filter((endpoint) => endpoint.priority === 'P2').length;
  const laravelMatched = endpoints.filter((endpoint) => endpoint.laravel_openapi_status === 'matched').length;
  const aspnetChecked = endpoints.filter((endpoint) => endpoint.aspnet_status !== 'not_checked').length;

  return {
    module,
    total: endpoints.length,
    p0,
    p1,
    p2,
    laravelMatched,
    aspnetChecked,
  };
}

function formatLaravel(endpoint) {
  if (endpoint.laravel_openapi_status === 'matched') {
    return `matched: \`${endpoint.laravel_openapi_path}\``;
  }
  return endpoint.laravel_openapi_status || 'not_checked';
}

function firstLocation(endpoint) {
  const firstFile = endpoint.files?.[0];
  if (!firstFile) return '';
  return `${firstFile.file}:${firstFile.lines?.[0] ?? 1}`;
}

function yesNoUnknown(value) {
  if (value === true) return 'yes';
  if (value === false) return 'no';
  return 'unknown';
}

function tableCell(value) {
  return String(value ?? '')
    .replaceAll('|', '\\|')
    .replace(/\r?\n/g, '<br>');
}

function code(value) {
  return `\`${String(value ?? '').replaceAll('`', '\\`')}\``;
}

function worksheetMarkdown(module, endpoints) {
  const summary = moduleSummary(module, endpoints);
  const lines = [
    `# ${title(module)} Certification Worksheet`,
    '',
    'This worksheet is generated from the Laravel React API-call inventory.',
    'ASP.NET status starts as `not_checked`; this file does not certify ASP.NET compatibility.',
    '',
    '## Summary',
    '',
    `- Total endpoint patterns: ${summary.total}`,
    `- P0: ${summary.p0}`,
    `- P1: ${summary.p1}`,
    `- P2: ${summary.p2}`,
    `- Laravel OpenAPI matched: ${summary.laravelMatched}`,
    `- ASP.NET checked: ${summary.aspnetChecked}`,
    '',
    '## Required proof before certification',
    '',
    '- ASP.NET exposes the same method/path, including `/api/v2` aliases where Laravel React expects them.',
    '- Request body, query string, upload field names, and headers are compatible.',
    '- Response envelope, pagination metadata, validation errors, auth/tenant errors, and status codes are compatible.',
    '- Focused ASP.NET regression tests pass for the row.',
    '- Runtime smoke test uses the Laravel React frontend against ASP.NET for the workflow.',
    '- Laravel mode remains green after any future change.',
    '',
    '## Endpoint Checklist',
    '',
    '| Priority | Method | React endpoint | Laravel OpenAPI | ASP.NET | Auth | Tenant | Risk | First source | Proof notes |',
    '| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |',
  ];

  for (const endpoint of endpoints) {
    lines.push([
      endpoint.priority,
      endpoint.method,
      code(endpoint.endpoint),
      formatLaravel(endpoint),
      endpoint.aspnet_status || 'not_checked',
      yesNoUnknown(endpoint.auth_required),
      yesNoUnknown(endpoint.tenant_required),
      endpoint.contract_risk || '',
      firstLocation(endpoint),
      '',
    ].map(tableCell).join(' | ').replace(/^/, '| ').replace(/$/, ' |'));
  }

  lines.push('');
  return lines.join('\n');
}

function indexMarkdown(inventory, summaries) {
  const lines = [
    '# React API Certification Worksheets',
    '',
    'Generated from the Laravel React API-call inventory.',
    '',
    '**ASP.NET compatibility is not certified by these worksheets.** Every ASP.NET row remains `not_checked` until a separate backend audit and runtime smoke test proves it.',
    '',
    '## Inventory Summary',
    '',
    `- Unique endpoint patterns: ${inventory.summary?.unique_endpoints ?? summaries.reduce((sum, item) => sum + item.total, 0)}`,
    `- ASP.NET ` + `not_checked` + ` rows: ${inventory.summary?.aspnet_status?.not_checked ?? summaries.reduce((sum, item) => sum + item.total, 0)}`,
    '',
    '## Modules',
    '',
    '| Module | Total | P0 | P1/P2 | Worksheet |',
    '| --- | ---: | ---: | ---: | --- |',
  ];

  for (const summary of summaries) {
    const filename = `${slug(summary.module)}.md`;
    lines.push(`| ${summary.module} | ${summary.total} | ${summary.p0} | ${summary.p1 + summary.p2} | [${filename}](${filename}) |`);
  }

  lines.push('');
  return lines.join('\n');
}

function main() {
  const options = parseArgs(process.argv.slice(2));
  if (!fs.existsSync(options.inventory)) {
    throw new Error(`Inventory file not found: ${options.inventory}`);
  }

  const inventory = JSON.parse(fs.readFileSync(options.inventory, 'utf8'));
  const endpoints = Array.isArray(inventory.endpoints) ? inventory.endpoints : [];
  const grouped = groupByModule(endpoints);
  const summaries = grouped.map(([module, rows]) => moduleSummary(module, rows));

  fs.mkdirSync(options.outDir, { recursive: true });
  for (const [module, rows] of grouped) {
    fs.writeFileSync(path.join(options.outDir, `${slug(module)}.md`), worksheetMarkdown(module, rows), 'utf8');
  }

  fs.writeFileSync(path.join(options.outDir, 'README.md'), indexMarkdown(inventory, summaries), 'utf8');

  console.log(`generate-certification-worksheets: wrote ${grouped.length} module worksheets to ${path.relative(repoRoot, options.outDir)}`);
}

main();
