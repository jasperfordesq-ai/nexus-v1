#!/usr/bin/env node
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Prerender Worker - runs inside a Playwright Docker container.
 *
 * Reads a JSON manifest from stdin:
 *   { "urls": [{ "url": "https://hour-timebank.ie/about", "output": "/out/hour-timebank.ie/about/index.html" }, ...] }
 *
 * Visits each URL with headless Chromium, waits for React to render with
 * real tenant data, and saves the fully rendered HTML to the output path.
 */

import { chromium } from 'playwright';
import { writeFileSync, mkdirSync, readFileSync } from 'fs';
import { dirname } from 'path';

const TIMEOUT = 30000;
const SETTLE_TIME = 2000; // Wait for React hydration + API calls
const CONCURRENCY = Math.max(1, Number.parseInt(process.env.PRERENDER_CONCURRENCY || '4', 10) || 4);

async function waitForUsefulRender(page) {
  await page.waitForFunction(() => {
    const root = document.querySelector('#root');
    const bodyText = document.body?.innerText?.trim() || '';
    return Boolean(root?.children.length) && bodyText.length > 200;
  }, { timeout: 15000 }).catch(() => {});
}

async function renderPage(page, url) {
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: TIMEOUT });

  await waitForUsefulRender(page);

  // Wait for React Helmet to inject meta tags
  await page.waitForSelector('[data-rh]', { timeout: 10000 }).catch(() => {});

  // Network idle is useful when it happens, but public pages can contain
  // authenticated pollers or third-party noise. The render contract is DOM
  // content plus SEO tags, so do not let a stray request block the whole tenant.
  await page.waitForLoadState('networkidle', { timeout: 5000 }).catch(() => {});

  // Extra settle time for tenant bootstrap + content rendering
  await page.waitForTimeout(SETTLE_TIME);

  // Strip dynamically injected Google Maps assets. They are added at runtime
  // by APIProvider. Leaving them in the prerendered HTML causes double-loading
  // when the SPA boots.
  await page.evaluate(() => {
    const HOST_RE = /maps\.googleapis\.com|maps-api-v3|maps\.gstatic\.com/;
    document.querySelectorAll('script[src]').forEach((s) => {
      if (HOST_RE.test(s.getAttribute('src') || '')) s.remove();
    });
    document.querySelectorAll('link[href]').forEach((l) => {
      const rel = (l.getAttribute('rel') || '').toLowerCase();
      const href = l.getAttribute('href') || '';
      if (
        (rel.includes('preload') || rel.includes('modulepreload') ||
         rel.includes('prefetch') || rel.includes('preconnect') ||
         rel.includes('dns-prefetch')) &&
        HOST_RE.test(href)
      ) {
        l.remove();
      }
    });
  });

  return await page.content();
}

async function main() {
  const input = readFileSync('/dev/stdin', 'utf-8');
  const manifest = JSON.parse(input);

  if (!manifest.urls || !manifest.urls.length) {
    console.error('No URLs in manifest');
    process.exit(1);
  }

  console.log(`Pre-rendering ${manifest.urls.length} pages with concurrency ${CONCURRENCY}...`);

  const browser = await chromium.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
  });

  const context = await browser.newContext({
    userAgent: 'NexusPrerender/1.0 (internal; deploy-time rendering)',
    viewport: { width: 1280, height: 720 },
  });

  const results = {
    success: 0,
    failed: 0,
    startedAt: new Date().toISOString(),
    finishedAt: null,
    entries: [],
  };
  const successfulCachePaths = [];
  const failedCachePaths = [];
  let nextIndex = 0;

  async function renderEntry(entry) {
    const page = await context.newPage();
    const label = entry.canonicalUrl || entry.url;
    try {
      const html = await renderPage(page, entry.url);
      const size = Buffer.byteLength(html, 'utf-8');

      if (size < 3000) {
        console.log(`  skipped ${label} - too small (${size}B)`);
        results.failed++;
        results.entries.push({
          url: label,
          output: entry.output,
          cachePath: entry.cachePath || null,
          status: 'failed',
          reason: `too small (${size}B)`,
        });
        if (entry.cachePath) failedCachePaths.push(entry.cachePath);
        return;
      }

      const outputDir = dirname(entry.output);
      mkdirSync(outputDir, { recursive: true });
      writeFileSync(entry.output, html, 'utf-8');

      console.log(`  rendered ${label} (${(size / 1024).toFixed(1)}KB)`);
      results.success++;
      results.entries.push({
        url: label,
        output: entry.output,
        cachePath: entry.cachePath || null,
        status: 'rendered',
        bytes: size,
      });
      if (entry.cachePath) successfulCachePaths.push(entry.cachePath);
    } catch (err) {
      console.log(`  failed ${label} - ${err.message}`);
      results.failed++;
      results.entries.push({
        url: label,
        output: entry.output,
        cachePath: entry.cachePath || null,
        status: 'failed',
        reason: err.message,
      });
      if (entry.cachePath) failedCachePaths.push(entry.cachePath);
    } finally {
      await page.close();
    }
  }

  async function worker() {
    while (nextIndex < manifest.urls.length) {
      const entry = manifest.urls[nextIndex];
      nextIndex++;
      await renderEntry(entry);
    }
  }

  await Promise.all(
    Array.from({ length: Math.min(CONCURRENCY, manifest.urls.length) }, () => worker())
  );

  await browser.close();

  results.finishedAt = new Date().toISOString();
  writeFileSync('/output/.prerender-results.json', JSON.stringify(results, null, 2), 'utf-8');
  writeFileSync('/output/.prerender-successes.txt', `${successfulCachePaths.join('\n')}\n`, 'utf-8');
  writeFileSync('/output/.prerender-failures.txt', `${failedCachePaths.join('\n')}\n`, 'utf-8');

  console.log(`\nDone: ${results.success} succeeded, ${results.failed} failed`);
  process.exit(results.failed > 0 ? 1 : 0);
}

main();
