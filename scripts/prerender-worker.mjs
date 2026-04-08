#!/usr/bin/env node
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Prerender Worker — runs inside a Playwright Docker container.
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

async function renderPage(page, url) {
  await page.goto(url, { waitUntil: 'networkidle', timeout: TIMEOUT });

  // Wait for React Helmet to inject meta tags
  await page.waitForSelector('[data-rh]', { timeout: 10000 }).catch(() => {});

  // Extra settle time for tenant bootstrap + content rendering
  await page.waitForTimeout(SETTLE_TIME);

  return await page.content();
}

async function main() {
  // Read manifest from stdin
  const input = readFileSync('/dev/stdin', 'utf-8');
  const manifest = JSON.parse(input);

  if (!manifest.urls || !manifest.urls.length) {
    console.error('No URLs in manifest');
    process.exit(1);
  }

  console.log(`Pre-rendering ${manifest.urls.length} pages...`);

  const browser = await chromium.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
  });

  const context = await browser.newContext({
    userAgent: 'NexusPrerender/1.0 (internal; deploy-time rendering)',
    viewport: { width: 1280, height: 720 },
  });

  let success = 0;
  let failed = 0;

  for (const entry of manifest.urls) {
    const page = await context.newPage();
    try {
      const html = await renderPage(page, entry.url);
      const size = Buffer.byteLength(html, 'utf-8');

      // Skip if too small (likely error page or empty)
      if (size < 3000) {
        console.log(`  ✗ ${entry.url} — too small (${size}B), skipped`);
        failed++;
        await page.close();
        continue;
      }

      const outputDir = dirname(entry.output);
      mkdirSync(outputDir, { recursive: true });
      writeFileSync(entry.output, html, 'utf-8');

      console.log(`  ✓ ${entry.url} (${(size / 1024).toFixed(1)}KB)`);
      success++;
    } catch (err) {
      console.log(`  ✗ ${entry.url} — ${err.message}`);
      failed++;
    }
    await page.close();
  }

  await browser.close();

  console.log(`\nDone: ${success} succeeded, ${failed} failed`);
  process.exit(failed > 0 ? 1 : 0);
}

main();
