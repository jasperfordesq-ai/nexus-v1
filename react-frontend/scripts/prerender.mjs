// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Build-time static pre-rendering for public pages.
 *
 * Runs after `vite build` — spins up a local static server for the built
 * dist/ folder, visits each public route with Playwright, captures the
 * fully-rendered HTML (with React Helmet meta tags baked in), and writes
 * it as a static .html file in dist/.
 *
 * nginx serves these files directly to ALL visitors (users AND bots).
 * React hydrates on top and takes over client-side routing.
 *
 * Usage:
 *   node scripts/prerender.mjs                    # Pre-render all routes
 *   node scripts/prerender.mjs --routes /about    # Pre-render specific route
 *
 * Requirements:
 *   - dist/ must exist (run `vite build` first)
 *   - @playwright/test must be installed
 *   - VITE_API_BASE env var for API calls during rendering
 */

import { chromium } from '@playwright/test';
import { createServer } from 'http';
import { readFileSync, writeFileSync, mkdirSync, existsSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const DIST_DIR = join(__dirname, '..', 'dist');
const PORT = 4173;

// ─── Public routes to pre-render ─────────────────────────────────────────────
// These are pages that must be indexable by Google and should load instantly.
// Protected/auth routes are NOT pre-rendered (they redirect to login anyway).
const PUBLIC_ROUTES = [
  '/',
  '/about',
  '/faq',
  '/contact',
  '/help',
  '/explore',
  '/listings',
  '/blog',
  '/terms',
  '/privacy',
  '/accessibility',
  '/cookies',
  '/community-guidelines',
  '/acceptable-use',
  '/legal',
  '/timebanking-guide',
  '/platform/terms',
  '/platform/privacy',
  '/platform/disclaimer',
];

// ─── Lightweight static file server ──────────────────────────────────────────
// Serves dist/ locally so Playwright can visit pages. Mimics nginx behaviour:
// known files are served directly, everything else gets index.html (SPA routing).
function createStaticServer() {
  const mimeTypes = {
    '.html': 'text/html',
    '.js': 'application/javascript',
    '.css': 'text/css',
    '.json': 'application/json',
    '.svg': 'image/svg+xml',
    '.png': 'image/png',
    '.ico': 'image/x-icon',
    '.woff2': 'font/woff2',
  };

  // Read the SPA shell once at startup (before pre-rendering overwrites index.html)
  const spaShell = readFileSync(join(DIST_DIR, 'index.html'), 'utf-8');

  return createServer((req, res) => {
    const urlPath = req.url.split('?')[0]; // strip query params
    let filePath = join(DIST_DIR, urlPath === '/' ? '/index.html' : urlPath);

    // If the file doesn't exist, serve the cached SPA shell
    if (!existsSync(filePath)) {
      res.writeHead(200, { 'Content-Type': 'text/html' });
      res.end(spaShell);
      return;
    }

    const ext = filePath.substring(filePath.lastIndexOf('.'));
    const contentType = mimeTypes[ext] || 'application/octet-stream';

    try {
      const content = readFileSync(filePath);
      res.writeHead(200, { 'Content-Type': contentType });
      res.end(content);
    } catch {
      // Fallback to cached SPA shell
      res.writeHead(200, { 'Content-Type': 'text/html' });
      res.end(spaShell);
    }
  });
}

// ─── Pre-render a single route ───────────────────────────────────────────────
async function prerenderRoute(page, route) {
  const url = `http://localhost:${PORT}${route}`;

  try {
    await page.goto(url, { waitUntil: 'networkidle', timeout: 30000 });

    // Wait for React Helmet to inject meta tags (data-rh attribute)
    await page.waitForSelector('[data-rh]', { timeout: 10000 }).catch(() => {
      // Some pages may not have Helmet tags — that's ok
    });

    // Wait a bit more for any lazy-loaded content
    await page.waitForTimeout(1000);

    // Get the full rendered HTML
    let html = await page.content();

    // Clean up: remove Vite HMR scripts that shouldn't be in static output
    html = html.replace(/<script[^>]*type="module"[^>]*src="\/src\/[^"]*"[^>]*><\/script>/g, '');

    // Write the static HTML file
    const outputPath = route === '/'
      ? join(DIST_DIR, 'index.html')
      : join(DIST_DIR, route, 'index.html');

    const outputDir = dirname(outputPath);
    if (!existsSync(outputDir)) {
      mkdirSync(outputDir, { recursive: true });
    }

    writeFileSync(outputPath, html, 'utf-8');

    const size = Buffer.byteLength(html, 'utf-8');
    console.log(`  ✓ ${route} (${(size / 1024).toFixed(1)}KB)`);
    return true;
  } catch (err) {
    console.error(`  ✗ ${route} — ${err.message}`);
    return false;
  }
}

// ─── Main ────────────────────────────────────────────────────────────────────
async function main() {
  // Parse --routes flag for selective pre-rendering
  const args = process.argv.slice(2);
  const routesIdx = args.indexOf('--routes');
  const routes = routesIdx !== -1
    ? args.slice(routesIdx + 1).filter(r => r.startsWith('/'))
    : PUBLIC_ROUTES;

  if (!existsSync(DIST_DIR)) {
    console.error('dist/ not found. Run `vite build` first.');
    process.exit(1);
  }

  // Save the original index.html before we overwrite it with the pre-rendered homepage
  const originalIndex = readFileSync(join(DIST_DIR, 'index.html'), 'utf-8');
  const spaFallbackPath = join(DIST_DIR, '_spa.html');
  writeFileSync(spaFallbackPath, originalIndex, 'utf-8');

  console.log(`\nPre-rendering ${routes.length} public pages...\n`);

  const server = createStaticServer();
  await new Promise(resolve => server.listen(PORT, resolve));

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    userAgent: 'NexusPrerender/1.0',
    viewport: { width: 1280, height: 720 },
  });

  let success = 0;
  let failed = 0;

  for (const route of routes) {
    const page = await context.newPage();
    const ok = await prerenderRoute(page, route);
    ok ? success++ : failed++;
    await page.close();
  }

  await browser.close();
  server.close();

  console.log(`\nDone: ${success} succeeded, ${failed} failed`);
  console.log(`SPA fallback saved as dist/_spa.html`);

  if (failed > 0) {
    process.exit(1);
  }
}

main().catch(err => {
  console.error('Pre-render failed:', err);
  process.exit(1);
});
