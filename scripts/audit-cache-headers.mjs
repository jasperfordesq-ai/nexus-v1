// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';

const baseUrl = (process.env.CACHE_AUDIT_BASE_URL || process.env.PERF_AUDIT_BASE_URL || process.env.E2E_BASE_URL || 'http://localhost:5173').replace(/\/+$/, '');
const tenantSlug = process.env.CACHE_AUDIT_TENANT_SLUG || process.env.PERF_AUDIT_TENANT_SLUG || process.env.E2E_TENANT_SLUG || 'hour-timebank';
const outputRoot = process.env.CACHE_AUDIT_OUTPUT_DIR || path.join('.local-docs-archive', 'performance-traces', 'latest');
const timeoutMs = Number.parseInt(process.env.CACHE_AUDIT_TIMEOUT_MS || '8000', 10);
const configuredThumbnailUrl = process.env.CACHE_AUDIT_THUMBNAIL_URL || '';
const isLocalDevTarget = /^https?:\/\/(?:localhost|127\.0\.0\.1|\[::1\])(?::\d+)?(?:\/|$)/i.test(baseUrl);
const configWarnings = [];

if (isLocalDevTarget) {
  configWarnings.push('Local Vite/dev-server target detected; service-worker and CDN cache headers may differ from production build/Apache/CDN behavior.');
}

function header(headers, name) {
  return headers.get(name) || '';
}

async function discoverThumbnailUrl() {
  if (configuredThumbnailUrl !== '') {
    return configuredThumbnailUrl;
  }

  const routeAuditPath = path.join(outputRoot, 'route-audit.json');
  try {
    const raw = await readFile(routeAuditPath, 'utf8');
    const audit = JSON.parse(raw);
    for (const result of audit.results ?? []) {
      for (const response of result.responses ?? []) {
        if (typeof response.url === 'string' && /\/api\/v2\/media\/thumbnail/i.test(response.url)) {
          console.log(`Discovered thumbnail URL from ${routeAuditPath}`);
          return response.url;
        }
      }
    }
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    configWarnings.push(`Could not read route-audit thumbnail evidence from ${routeAuditPath}: ${message}`);
  }

  return '';
}

function buildChecks(thumbnailUrl) {
  return [
    {
      name: 'app index',
      url: `${baseUrl}/${tenantSlug}/login`,
      expect: 'html',
      required: true,
    },
    {
      name: 'service worker',
      url: `${baseUrl}/sw.js`,
      expect: 'service-worker',
      required: false,
    },
    {
      name: 'manifest',
      url: `${baseUrl}/manifest.json`,
      expect: 'manifest',
      required: false,
    },
    {
      name: 'thumbnail',
      url: thumbnailUrl,
      expect: 'thumbnail',
      required: false,
      skippedReason: thumbnailUrl === '' ? 'Set CACHE_AUDIT_THUMBNAIL_URL to a real /api/v2/media/thumbnail?... URL, or run audit:performance:routes first so this command can reuse an observed thumbnail URL.' : '',
    },
  ].filter((check) => check.url !== '' || check.skippedReason);
}

function hasLongLivedCache(cacheControl) {
  const maxAge = /(?:^|,\s*)max-age=(\d+)/i.exec(cacheControl);
  return /immutable/i.test(cacheControl) || (maxAge !== null && Number(maxAge[1]) >= 86400);
}

function hasNoStoreOrRevalidate(cacheControl) {
  return /no-store|no-cache|must-revalidate|max-age=0/i.test(cacheControl);
}

function assess(check, response, error) {
  if (check.skippedReason) {
    return {
      name: check.name,
      url: check.url || '(not configured)',
      status: 'skipped',
      warnings: [check.skippedReason],
      headers: {},
    };
  }

  if (error) {
    return {
      name: check.name,
      url: check.url,
      status: check.required ? 'fail' : 'warn',
      warnings: [`Request failed: ${error}`],
      headers: {},
    };
  }

  const headers = response.headers;
  const cacheControl = header(headers, 'cache-control');
  const contentType = header(headers, 'content-type');
  const etag = header(headers, 'etag');
  const lastModified = header(headers, 'last-modified');
  const vary = header(headers, 'vary');
  const cdnCache = header(headers, 'cf-cache-status') || header(headers, 'x-cache') || header(headers, 'x-cache-status');
  const warnings = [];

  if (!response.ok) {
    warnings.push(`Unexpected HTTP status ${response.status}`);
  }

  if (check.expect === 'html') {
    if (!/text\/html/i.test(contentType)) {
      warnings.push(`Expected HTML content-type, got "${contentType || 'missing'}"`);
    }
    if (!hasNoStoreOrRevalidate(cacheControl)) {
      warnings.push(`HTML shell should be revalidation/no-cache guarded, got Cache-Control "${cacheControl || 'missing'}"`);
    }
  }

  if (check.expect === 'service-worker') {
    if (!/javascript|text\/plain|application\/octet-stream/i.test(contentType)) {
      warnings.push(`Service worker content-type looked unusual: "${contentType || 'missing'}"`);
    }
    if (!hasNoStoreOrRevalidate(cacheControl)) {
      warnings.push(`Service worker should revalidate aggressively, got Cache-Control "${cacheControl || 'missing'}"`);
    }
  }

  if (check.expect === 'manifest') {
    if (!/manifest|json/i.test(contentType)) {
      warnings.push(`Manifest content-type looked unusual: "${contentType || 'missing'}"`);
    }
    if (cacheControl === '') {
      warnings.push('Manifest Cache-Control header is missing.');
    }
  }

  if (check.expect === 'thumbnail') {
    if (!/image\//i.test(contentType)) {
      warnings.push(`Thumbnail should return an image content-type, got "${contentType || 'missing'}"`);
    }
    if (!hasLongLivedCache(cacheControl)) {
      warnings.push(`Thumbnail should be long-lived/immutable cached, got Cache-Control "${cacheControl || 'missing'}"`);
    }
    if (!etag && !lastModified) {
      warnings.push('Thumbnail should expose ETag or Last-Modified for conditional requests.');
    }
  }

  return {
    name: check.name,
    url: check.url,
    status: warnings.length > 0 ? (check.required ? 'fail' : 'warn') : 'ok',
    httpStatus: response.status,
    warnings,
    headers: {
      cacheControl,
      contentType,
      etag,
      lastModified,
      vary,
      cdnCache,
    },
  };
}

async function fetchCheck(check) {
  if (check.skippedReason) {
    return assess(check, null, null);
  }

  try {
    const response = await fetch(check.url, {
      redirect: 'follow',
      signal: AbortSignal.timeout(timeoutMs),
      headers: {
        'User-Agent': 'Project-NEXUS-cache-audit/1.0',
      },
    });

    return assess(check, response, null);
  } catch (error) {
    return assess(check, null, error instanceof Error ? error.message : String(error));
  }
}

function markdownReport(results) {
  const lines = [
    '# Cache Header Audit',
    '',
    `- Base URL: ${baseUrl}`,
    `- Tenant: ${tenantSlug}`,
    `- Generated: ${new Date().toISOString()}`,
    `- Timeout: ${timeoutMs} ms`,
    '',
    '| Check | Status | Cache-Control | Content-Type | CDN Cache | Warnings |',
    '|---|---:|---|---|---|---|',
  ];

  if (configWarnings.length > 0) {
    lines.splice(6, 0, '', '## Configuration Warnings', '', ...configWarnings.map((warning) => `- ${warning}`));
  }

  for (const result of results) {
    lines.push(markdownRow([
      result.name,
      result.status,
      result.headers.cacheControl || '',
      result.headers.contentType || '',
      result.headers.cdnCache || '',
      result.warnings.length > 0 ? result.warnings.join('<br>') : 'OK',
    ]));
  }

  lines.push('', '## Details', '');
  for (const result of results) {
    lines.push(`### ${result.name}`, '');
    lines.push(`- URL: ${result.url}`);
    lines.push(`- Status: ${result.status}${result.httpStatus ? ` (${result.httpStatus})` : ''}`);
    if (result.warnings.length > 0) {
      lines.push(`- Warnings: ${result.warnings.join('; ')}`);
    }
    for (const [name, value] of Object.entries(result.headers)) {
      if (value) {
        lines.push(`- ${name}: ${value}`);
      }
    }
    lines.push('');
  }

  return `${lines.join('\n')}\n`;
}

function markdownRow(cells) {
  return `| ${cells.map((cell) => String(cell).replace(/\|/g, '\\|')).join(' | ')} |`;
}

async function main() {
  await mkdir(outputRoot, { recursive: true });

  const thumbnailUrl = await discoverThumbnailUrl();
  const checks = buildChecks(thumbnailUrl);
  const results = [];
  for (const check of checks) {
    console.log(`Checking ${check.name}...`);
    results.push(await fetchCheck(check));
  }

  const json = JSON.stringify({
    baseUrl,
    tenantSlug,
    generatedAt: new Date().toISOString(),
    timeoutMs,
    isLocalDevTarget,
    configWarnings,
    results,
  }, null, 2);
  const markdown = markdownReport(results);
  await writeFile(path.join(outputRoot, 'cache-header-audit.json'), json);
  await writeFile(path.join(outputRoot, 'cache-header-audit.md'), markdown);

  const failures = results.filter((result) => result.status === 'fail');
  const warnings = results.filter((result) => result.status === 'warn' || result.status === 'skipped');
  console.log(`Wrote ${path.join(outputRoot, 'cache-header-audit.md')}`);
  console.log(`Failures: ${failures.length}; warnings/skips: ${warnings.length + configWarnings.length}`);

  if (failures.length > 0 || ((warnings.length > 0 || configWarnings.length > 0) && process.env.CACHE_AUDIT_STRICT === '1')) {
    process.exitCode = 1;
  }
}

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
