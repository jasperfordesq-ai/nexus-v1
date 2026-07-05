// Copyright (C) 2024-2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Build a local inventory of API calls made by the Laravel React frontend.
 *
 * This is a read-only preparation tool for future ASP.NET contract parity work.
 * It does not contact either backend and intentionally writes generated output
 * under .local-docs-archive/ so public docs stay curated.
 */

import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const ts = require('typescript');

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const frontendRoot = path.resolve(__dirname, '..');
const repoRoot = path.resolve(frontendRoot, '..');
const defaultSrcRoot = path.join(frontendRoot, 'src');
const defaultOutDir = path.join(repoRoot, '.local-docs-archive', 'react-api-inventory', 'latest');
const defaultOpenApiPath = path.join(repoRoot, 'openapi.json');
const apiMethods = new Set(['get', 'post', 'put', 'patch', 'delete', 'download', 'upload']);
const sourceExtensions = new Set(['.ts', '.tsx']);

function parseArgs(argv) {
  const options = {
    outDir: defaultOutDir,
    srcRoot: defaultSrcRoot,
    openApiPath: fs.existsSync(defaultOpenApiPath) ? defaultOpenApiPath : null,
    includeTests: false,
  };

  for (let index = 0; index < argv.length; index += 1) {
    const arg = argv[index];
    if (arg === '--out') {
      const value = argv[index + 1];
      if (!value) throw new Error('--out requires a directory');
      options.outDir = path.resolve(process.cwd(), value);
      index += 1;
    } else if (arg === '--src') {
      const value = argv[index + 1];
      if (!value) throw new Error('--src requires a directory');
      options.srcRoot = path.resolve(process.cwd(), value);
      index += 1;
    } else if (arg === '--openapi') {
      const value = argv[index + 1];
      if (!value) throw new Error('--openapi requires a file path');
      options.openApiPath = path.resolve(process.cwd(), value);
      index += 1;
    } else if (arg === '--include-tests') {
      options.includeTests = true;
    } else if (arg === '--help' || arg === '-h') {
      printHelp();
      process.exit(0);
    } else {
      throw new Error(`Unknown argument: ${arg}`);
    }
  }

  return options;
}

function printHelp() {
  console.log(`Usage: node scripts/inventory-api-calls.mjs [--src <dir>] [--out <dir>] [--openapi <file>] [--include-tests]

Writes:
  api-calls.json
  api-calls.md

Default output:
  ${path.relative(repoRoot, defaultOutDir)}
`);
}

function toPosix(relativePath) {
  return relativePath.split(path.sep).join('/');
}

function walk(dir, includeTests) {
  const entries = fs.readdirSync(dir, { withFileTypes: true });
  const files = [];

  for (const entry of entries) {
    const fullPath = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      if (shouldSkipDirectory(entry.name, includeTests)) continue;
      files.push(...walk(fullPath, includeTests));
      continue;
    }

    if (!entry.isFile()) continue;
    if (!sourceExtensions.has(path.extname(entry.name))) continue;
    if (!includeTests && shouldSkipFile(entry.name)) continue;
    files.push(fullPath);
  }

  return files;
}

function shouldSkipDirectory(name, includeTests) {
  if (['node_modules', 'dist', 'coverage'].includes(name)) return true;
  if (includeTests) return false;
  return ['__tests__', '__mocks__', 'test', 'tests'].includes(name);
}

function shouldSkipFile(name) {
  return /\.(test|spec)\.[cm]?[tj]sx?$/.test(name)
    || /\.stories\.[cm]?[tj]sx?$/.test(name)
    || name.endsWith('.d.ts');
}

function lineAndColumn(sourceFile, node) {
  const pos = sourceFile.getLineAndCharacterOfPosition(node.getStart(sourceFile));
  return {
    line: pos.line + 1,
    column: pos.character + 1,
  };
}

function endpointFromExpression(node, sourceFile) {
  if (!node) return null;

  if (ts.isStringLiteralLike(node)) {
    return {
      value: node.text,
      dynamic: false,
    };
  }

  if (ts.isNoSubstitutionTemplateLiteral(node)) {
    return {
      value: node.text,
      dynamic: false,
    };
  }

  if (ts.isTemplateExpression(node)) {
    let value = node.head.text;
    for (const span of node.templateSpans) {
      value += `{${span.expression.getText(sourceFile)}}${span.literal.text}`;
    }
    return {
      value,
      dynamic: true,
    };
  }

  return null;
}

function looksLikeEndpoint(endpoint) {
  return endpoint.startsWith('/')
    || endpoint.startsWith('http://')
    || endpoint.startsWith('https://')
    || endpoint.includes('/v2/')
    || endpoint.includes('/api/');
}

function methodFromFetchOptions(optionsNode) {
  if (!optionsNode || !ts.isObjectLiteralExpression(optionsNode)) return 'GET';

  for (const property of optionsNode.properties) {
    if (!ts.isPropertyAssignment(property)) continue;
    const name = property.name;
    const isMethod = (ts.isIdentifier(name) && name.text === 'method')
      || (ts.isStringLiteral(name) && name.text === 'method');
    if (!isMethod) continue;

    const initializer = property.initializer;
    if (ts.isStringLiteralLike(initializer)) return initializer.text.toUpperCase();
    return 'DYNAMIC';
  }

  return 'GET';
}

function optionFlagsForApiCall(args, method) {
  const flags = [];
  const optionsArg = method === 'get' || method === 'delete' || method === 'download'
    ? args[1]
    : args[2];

  if (method === 'upload') flags.push('multipart');
  if (method === 'download') flags.push('blob');
  if (!optionsArg || !ts.isObjectLiteralExpression(optionsArg)) return flags;

  for (const property of optionsArg.properties) {
    if (!ts.isPropertyAssignment(property)) continue;
    const name = property.name;
    if (ts.isIdentifier(name)) flags.push(name.text);
    if (ts.isStringLiteral(name)) flags.push(name.text);
  }

  return [...new Set(flags)].sort();
}

function maybeApiCall(node, sourceFile, relativeFile) {
  if (!ts.isPropertyAccessExpression(node.expression)) return null;

  const method = node.expression.name.text;
  if (!apiMethods.has(method)) return null;

  const endpoint = endpointFromExpression(node.arguments[0], sourceFile);
  if (!endpoint || !looksLikeEndpoint(endpoint.value)) return null;

  const location = lineAndColumn(sourceFile, node);
  const flags = optionFlagsForApiCall(node.arguments, method);
  return {
    method: httpMethodForApiMethod(method),
    transport: method,
    endpoint: endpoint.value,
    dynamic: endpoint.dynamic,
    module: moduleFromFile(relativeFile),
    file: relativeFile,
    line: location.line,
    column: location.column,
    flags,
    auth_required: !flags.includes('skipAuth'),
    tenant_required: !flags.includes('skipTenant'),
    upload_field: uploadFieldName(node.arguments, method),
    response_type: responseType(node.arguments, method),
  };
}

function httpMethodForApiMethod(method) {
  if (method === 'upload') return 'POST';
  if (method === 'download') return 'GET';
  return method.toUpperCase();
}

function maybeFetchCall(node, sourceFile, relativeFile) {
  if (!ts.isIdentifier(node.expression) || node.expression.text !== 'fetch') return null;

  const endpoint = endpointFromExpression(node.arguments[0], sourceFile);
  if (!endpoint || !looksLikeEndpoint(endpoint.value)) return null;

  const location = lineAndColumn(sourceFile, node);
  return {
    method: methodFromFetchOptions(node.arguments[1]),
    transport: 'fetch',
    endpoint: endpoint.value,
    dynamic: endpoint.dynamic,
    module: moduleFromFile(relativeFile),
    file: relativeFile,
    line: location.line,
    column: location.column,
    flags: [],
    auth_required: null,
    tenant_required: null,
    upload_field: null,
    response_type: null,
  };
}

function uploadFieldName(args, method) {
  if (method !== 'upload') return null;
  const fieldArg = args[2];
  return fieldArg && ts.isStringLiteralLike(fieldArg) ? fieldArg.text : 'file';
}

function responseType(args, method) {
  if (method === 'download') return 'blob';

  const optionsArg = method === 'get' || method === 'delete'
    ? args[1]
    : args[2];

  if (!optionsArg || !ts.isObjectLiteralExpression(optionsArg)) return 'json';

  for (const property of optionsArg.properties) {
    if (!ts.isPropertyAssignment(property)) continue;
    const name = property.name;
    const isResponseType = (ts.isIdentifier(name) && name.text === 'responseType')
      || (ts.isStringLiteral(name) && name.text === 'responseType');
    if (!isResponseType) continue;
    return ts.isStringLiteralLike(property.initializer) ? property.initializer.text : 'dynamic';
  }

  return 'json';
}

function moduleFromFile(relativeFile) {
  const file = relativeFile.replace(/^src\//, '');
  if (file.startsWith('admin/')) return 'admin';
  if (file.startsWith('broker/')) return 'broker';
  if (file.startsWith('contexts/Auth') || file.startsWith('pages/auth/') || file.includes('webauthn')) return 'auth';
  if (file.includes('caring-community')) return 'caring-community';
  if (file.includes('marketplace')) return 'marketplace';
  if (file.includes('courses') || file.includes('course')) return 'courses';
  if (file.includes('podcast')) return 'podcasts';
  if (file.includes('message')) return 'messages';
  if (file.includes('wallet') || file.includes('exchange')) return 'wallet';
  if (file.includes('notification')) return 'notifications';
  if (file.includes('feed')) return 'feed';
  if (file.includes('listing')) return 'listings';
  if (file.includes('group')) return 'groups';
  if (file.includes('event')) return 'events';
  if (file.includes('job')) return 'jobs';
  if (file.includes('volunteer')) return 'volunteering';
  if (file.includes('tenant')) return 'tenant';
  if (file.startsWith('pages/settings/')) return 'settings';
  if (file.startsWith('pages/')) return 'member';
  if (file.startsWith('components/')) return 'shared-components';
  if (file.startsWith('hooks/')) return 'hooks';
  if (file.startsWith('lib/')) return 'lib';
  return 'unclassified';
}

function collectCalls(filePath, sourceRoot) {
  const sourceText = fs.readFileSync(filePath, 'utf8');
  const sourceFile = ts.createSourceFile(filePath, sourceText, ts.ScriptTarget.Latest, true);
  const relativeFile = relativeSourceFile(filePath, sourceRoot);
  const calls = [];

  function visit(node) {
    if (ts.isCallExpression(node)) {
      const apiCall = maybeApiCall(node, sourceFile, relativeFile);
      if (apiCall) calls.push(apiCall);

      const fetchCall = maybeFetchCall(node, sourceFile, relativeFile);
      if (fetchCall) calls.push(fetchCall);
    }

    ts.forEachChild(node, visit);
  }

  visit(sourceFile);
  return calls;
}

function relativeSourceFile(filePath, sourceRoot) {
  const relativeToFrontend = path.relative(frontendRoot, filePath);
  if (!relativeToFrontend.startsWith('..') && !path.isAbsolute(relativeToFrontend)) {
    return toPosix(relativeToFrontend);
  }

  return toPosix(path.relative(sourceRoot, filePath));
}

function endpointKey(call) {
  return `${call.method} ${call.endpoint}`;
}

function aggregate(calls, openApiIndex) {
  const byEndpoint = new Map();

  for (const call of calls) {
    const key = endpointKey(call);
    const current = byEndpoint.get(key) ?? {
      method: call.method,
      endpoint: call.endpoint,
      dynamic: call.dynamic,
      count: 0,
      transports: new Set(),
      flags: new Set(),
      modules: new Set(),
      authValues: new Set(),
      tenantValues: new Set(),
      uploadFields: new Set(),
      responseTypes: new Set(),
      files: new Map(),
    };

    current.count += 1;
    current.dynamic = current.dynamic || call.dynamic;
    current.transports.add(call.transport);
    current.modules.add(call.module);
    current.authValues.add(String(call.auth_required));
    current.tenantValues.add(String(call.tenant_required));
    if (call.upload_field) current.uploadFields.add(call.upload_field);
    if (call.response_type) current.responseTypes.add(call.response_type);
    for (const flag of call.flags) current.flags.add(flag);

    const locations = current.files.get(call.file) ?? [];
    locations.push(call.line);
    current.files.set(call.file, locations);
    byEndpoint.set(key, current);
  }

  return [...byEndpoint.values()]
    .map((entry) => {
      const laravelOpenApi = matchOpenApi(entry.method, entry.endpoint, openApiIndex);
      return {
        method: entry.method,
        endpoint: entry.endpoint,
        dynamic: entry.dynamic,
        count: entry.count,
        module: setLabel(entry.modules),
        transports: [...entry.transports].sort(),
        flags: [...entry.flags].sort(),
        auth_required: requirementValue(entry.authValues),
        tenant_required: requirementValue(entry.tenantValues),
        upload_field: setLabel(entry.uploadFields),
        response_type: setLabel(entry.responseTypes),
        contract_risk: contractRisk(entry),
        priority: priorityForEndpoint(entry),
        laravel_openapi_status: laravelOpenApi.status,
        laravel_openapi_path: laravelOpenApi.path,
        laravel_operation_id: laravelOpenApi.operationId,
        aspnet_status: 'not_checked',
        aspnet_route: null,
        aspnet_notes: 'ASP.NET backend not certified from this frontend inventory.',
        files: [...entry.files.entries()]
          .map(([file, lines]) => ({
            file,
            lines: [...new Set(lines)].sort((a, b) => a - b),
          }))
          .sort((a, b) => a.file.localeCompare(b.file)),
      };
    })
    .sort((a, b) => `${a.endpoint} ${a.method}`.localeCompare(`${b.endpoint} ${b.method}`));
}

function loadOpenApiIndex(openApiPath) {
  if (!openApiPath || !fs.existsSync(openApiPath)) return null;

  const raw = JSON.parse(fs.readFileSync(openApiPath, 'utf8'));
  const paths = raw.paths && typeof raw.paths === 'object' ? raw.paths : {};
  const index = new Map();

  for (const [openApiPathKey, operations] of Object.entries(paths)) {
    if (!operations || typeof operations !== 'object') continue;
    for (const [method, operation] of Object.entries(operations)) {
      const upperMethod = method.toUpperCase();
      if (!['GET', 'POST', 'PUT', 'PATCH', 'DELETE'].includes(upperMethod)) continue;
      index.set(`${upperMethod} ${normalizePathForMatch(openApiPathKey)}`, {
        path: openApiPathKey,
        operationId: operation && typeof operation === 'object' ? operation.operationId ?? null : null,
      });
    }
  }

  return index;
}

function matchOpenApi(method, endpoint, openApiIndex) {
  if (!openApiIndex) {
    return {
      status: 'not_checked',
      path: null,
      operationId: null,
    };
  }

  const candidates = candidatePaths(endpoint);
  for (const candidate of candidates) {
    const match = openApiIndex.get(`${method} ${normalizePathForMatch(candidate)}`);
    if (match) {
      return {
        status: 'matched',
        path: match.path,
        operationId: match.operationId,
      };
    }
  }

  return {
    status: 'not_found',
    path: null,
    operationId: null,
  };
}

function candidatePaths(endpoint) {
  const withoutOrigin = endpoint.replace(/^https?:\/\/[^/]+/i, '');
  const withoutQuery = withoutOrigin.split('?')[0] || withoutOrigin;
  const candidates = new Set([withoutQuery]);

  if (withoutQuery.startsWith('/v2/')) candidates.add(`/api${withoutQuery}`);
  if (withoutQuery.startsWith('/auth/')) candidates.add(`/api/v2${withoutQuery}`);
  if (withoutQuery.startsWith('/webauthn/')) candidates.add(`/api/v2${withoutQuery}`);
  if (withoutQuery.startsWith('/api/v2/')) candidates.add(withoutQuery.replace(/^\/api/, ''));
  if (withoutQuery.startsWith('{API_BASE}')) candidates.add(withoutQuery.replace(/^\{API_BASE\}/, '/api').split('?')[0]);
  if (withoutQuery.startsWith('{apiBase}')) candidates.add(withoutQuery.replace(/^\{apiBase\}/, '/api').split('?')[0]);

  return [...candidates].filter((candidate) => candidate.startsWith('/'));
}

function normalizePathForMatch(pathValue) {
  return pathValue
    .split('?')[0]
    .replace(/\/+$/, '')
    .replace(/\{[^}/]+\}/g, '{}');
}

function setLabel(values) {
  const items = [...values].filter(Boolean).sort();
  if (items.length === 0) return null;
  if (items.length === 1) return items[0];
  return 'mixed';
}

function requirementValue(values) {
  if (values.has('true')) return true;
  if (values.has('false') && values.size === 1) return false;
  return null;
}

function contractRisk(entry) {
  const risks = new Set();
  if (entry.dynamic) risks.add('dynamic-path');
  if (entry.transports.has('fetch')) risks.add('raw-fetch');
  if (entry.transports.has('upload')) risks.add('upload');
  if (entry.transports.has('download')) risks.add('download');
  if (entry.endpoint.startsWith('http://') || entry.endpoint.startsWith('https://')) risks.add('absolute-url');
  if (entry.endpoint.includes('auth') || entry.modules.has('auth')) risks.add('auth');
  if (entry.flags.has('skipTenant')) risks.add('tenant-special-case');
  if (entry.flags.has('responseType')) risks.add('non-json-response');
  return [...risks].sort().join(', ') || 'standard-json';
}

function priorityForEndpoint(entry) {
  const risk = contractRisk(entry);
  if (entry.modules.has('auth') || entry.endpoint.includes('/csrf-token') || entry.endpoint.includes('/tenant')) return 'P0';
  if (risk.includes('upload') || risk.includes('download') || risk.includes('raw-fetch') || risk.includes('absolute-url')) return 'P1';
  if (entry.endpoint.startsWith('/v2/admin')) return 'P2';
  return 'P1';
}

function endpointBucket(endpoint) {
  if (endpoint.startsWith('/v2/admin')) return 'admin-v2';
  if (endpoint.startsWith('/v2/')) return 'member-v2';
  if (endpoint.startsWith('/admin')) return 'admin';
  if (endpoint.startsWith('/auth') || endpoint.includes('/login') || endpoint.includes('/register')) return 'auth';
  if (endpoint.includes('webauthn')) return 'auth';
  if (endpoint.includes('upload') || endpoint.includes('storage')) return 'upload';
  if (endpoint.startsWith('/api/')) return 'api-prefixed';
  if (endpoint.startsWith('/')) return 'relative';
  return 'absolute';
}

function buildSummary(calls, endpoints, scannedFiles, sourceRoot) {
  const byMethod = {};
  const byBucket = {};
  const byModule = {};
  const byPriority = {};
  const laravelOpenApi = {};
  const aspnetStatus = {};
  const dynamicEndpoints = endpoints.filter((endpoint) => endpoint.dynamic).length;

  for (const call of calls) {
    byModule[call.module] = (byModule[call.module] ?? 0) + 1;
  }

  for (const endpoint of endpoints) {
    byMethod[endpoint.method] = (byMethod[endpoint.method] ?? 0) + 1;
    byPriority[endpoint.priority] = (byPriority[endpoint.priority] ?? 0) + 1;
    laravelOpenApi[endpoint.laravel_openapi_status] = (laravelOpenApi[endpoint.laravel_openapi_status] ?? 0) + 1;
    aspnetStatus[endpoint.aspnet_status] = (aspnetStatus[endpoint.aspnet_status] ?? 0) + 1;
    const bucket = endpointBucket(endpoint.endpoint);
    byBucket[bucket] = (byBucket[bucket] ?? 0) + 1;
  }

  return {
    generated_at: new Date().toISOString(),
    source_root: toPosix(path.relative(repoRoot, sourceRoot)),
    scanned_files: scannedFiles,
    call_sites: calls.length,
    unique_endpoints: endpoints.length,
    dynamic_endpoints: dynamicEndpoints,
    by_method: Object.fromEntries(Object.entries(byMethod).sort()),
    by_module: Object.fromEntries(Object.entries(byModule).sort()),
    by_bucket: Object.fromEntries(Object.entries(byBucket).sort()),
    by_priority: Object.fromEntries(Object.entries(byPriority).sort()),
    laravel_openapi: Object.fromEntries(Object.entries(laravelOpenApi).sort()),
    aspnet_status: Object.fromEntries(Object.entries(aspnetStatus).sort()),
  };
}

function markdownReport(summary, endpoints) {
  const lines = [
    '# Laravel React API Call Inventory',
    '',
    'Generated by `npm --prefix react-frontend run inventory:api-calls`.',
    '',
    'This is a local preparation artifact. It does not prove ASP.NET readiness.',
    'Use it as the checklist for making ASP.NET match the production Laravel React frontend contract.',
    '',
    '## Summary',
    '',
    `- Generated at: ${summary.generated_at}`,
    `- Source root: ${summary.source_root}`,
    `- Scanned files: ${summary.scanned_files}`,
    `- API call sites: ${summary.call_sites}`,
    `- Unique method/endpoint pairs: ${summary.unique_endpoints}`,
    `- Dynamic endpoint patterns: ${summary.dynamic_endpoints}`,
    '',
    '## Methods',
    '',
    '| Method | Unique endpoints |',
    '| --- | ---: |',
    ...Object.entries(summary.by_method).map(([method, count]) => `| ${method} | ${count} |`),
    '',
    '## Modules',
    '',
    '| Module | Call sites |',
    '| --- | ---: |',
    ...Object.entries(summary.by_module).map(([module, count]) => `| ${module} | ${count} |`),
    '',
    '## Priorities',
    '',
    '| Priority | Unique endpoints | Meaning |',
    '| --- | ---: | --- |',
    ...Object.entries(summary.by_priority).map(([priority, count]) => `| ${priority} | ${count} | ${priorityMeaning(priority)} |`),
    '',
    '## Laravel OpenAPI',
    '',
    '| Status | Unique endpoints |',
    '| --- | ---: |',
    ...Object.entries(summary.laravel_openapi).map(([status, count]) => `| ${status} | ${count} |`),
    '',
    '## ASP.NET Status',
    '',
    '| Status | Unique endpoints |',
    '| --- | ---: |',
    ...Object.entries(summary.aspnet_status).map(([status, count]) => `| ${status} | ${count} |`),
    '',
    '## Buckets',
    '',
    '| Bucket | Unique endpoints |',
    '| --- | ---: |',
    ...Object.entries(summary.by_bucket).map(([bucket, count]) => `| ${bucket} | ${count} |`),
    '',
    '## Endpoint Matrix',
    '',
    '| Priority | Module | Method | Endpoint pattern | Laravel OpenAPI | ASP.NET | Auth | Tenant | Risk | Dynamic | Call sites | Transports | Flags | First locations |',
    '| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | ---: | --- | --- | --- |',
  ];

  for (const endpoint of endpoints) {
    const locations = endpoint.files
      .slice(0, 3)
      .map((entry) => `${entry.file}:${entry.lines[0]}`)
      .join('<br>');
    const more = endpoint.files.length > 3 ? `<br>+${endpoint.files.length - 3} files` : '';
    lines.push([
      endpoint.priority,
      endpoint.module,
      endpoint.method,
      code(endpoint.endpoint),
      openApiLabel(endpoint),
      endpoint.aspnet_status,
      requirementLabel(endpoint.auth_required),
      requirementLabel(endpoint.tenant_required),
      endpoint.contract_risk,
      endpoint.dynamic ? 'yes' : 'no',
      String(endpoint.count),
      endpoint.transports.join(', '),
      endpoint.flags.join(', '),
      `${locations}${more}`,
    ].map(tableCell).join(' | ').replace(/^/, '| ').replace(/$/, ' |'));
  }

  lines.push('');
  return lines.join('\n');
}

function openApiLabel(endpoint) {
  if (endpoint.laravel_openapi_status !== 'matched') return endpoint.laravel_openapi_status;
  return `${endpoint.laravel_openapi_status}: ${endpoint.laravel_openapi_path}`;
}

function priorityMeaning(priority) {
  if (priority === 'P0') return 'auth, tenant bootstrap, or session-critical';
  if (priority === 'P1') return 'member workflow, upload/download, raw fetch, or higher-risk contract';
  if (priority === 'P2') return 'admin or lower-risk contract follow-up';
  return 'unclassified';
}

function requirementLabel(value) {
  if (value === true) return 'yes';
  if (value === false) return 'no';
  return 'unknown';
}

function code(value) {
  return `\`${value.replaceAll('`', '\\`')}\``;
}

function tableCell(value) {
  return String(value)
    .replaceAll('|', '\\|')
    .replace(/\r?\n/g, '<br>');
}

async function main() {
  const options = parseArgs(process.argv.slice(2));
  const files = walk(options.srcRoot, options.includeTests);
  const calls = files.flatMap((file) => collectCalls(file, options.srcRoot))
    .sort((a, b) => `${a.file}:${a.line}:${a.column}`.localeCompare(`${b.file}:${b.line}:${b.column}`));
  const openApiIndex = loadOpenApiIndex(options.openApiPath);
  const endpoints = aggregate(calls, openApiIndex);
  const summary = buildSummary(calls, endpoints, files.length, options.srcRoot);
  const payload = {
    summary,
    endpoints,
    calls,
  };

  fs.mkdirSync(options.outDir, { recursive: true });
  const jsonPath = path.join(options.outDir, 'api-calls.json');
  const markdownPath = path.join(options.outDir, 'api-calls.md');
  fs.writeFileSync(jsonPath, `${JSON.stringify(payload, null, 2)}\n`, 'utf8');
  fs.writeFileSync(markdownPath, markdownReport(summary, endpoints), 'utf8');

  console.log(`inventory-api-calls: scanned ${summary.scanned_files} files`);
  console.log(`inventory-api-calls: found ${summary.call_sites} call sites / ${summary.unique_endpoints} unique method-endpoint pairs`);
  console.log(`inventory-api-calls: wrote ${path.relative(repoRoot, jsonPath)}`);
  console.log(`inventory-api-calls: wrote ${path.relative(repoRoot, markdownPath)}`);
}

main().catch((error) => {
  console.error(error instanceof Error ? error.message : error);
  process.exit(1);
});
