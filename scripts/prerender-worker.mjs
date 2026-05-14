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
import { createHash } from 'crypto';
import { dirname } from 'path';

const TIMEOUT = 30000;
const SETTLE_TIME = 2000; // Wait for React hydration + API calls
const CONCURRENCY = Math.max(1, Number.parseInt(process.env.PRERENDER_CONCURRENCY || '4', 10) || 4);
// Status codes we accept from the React app via <meta name="prerender-status-code">.
// Anything else (e.g. 500) is downgraded to 200 — pre-rendering soft-errors is
// useless, and emitting 5xx from cached snapshots would make Google deindex.
const ALLOWED_STATUS_CODES = new Set([200, 301, 302, 404, 410, 503]);

async function waitForUsefulRender(page) {
  // Honour an app-level readiness signal if the page provides one. Patterns:
  //   window.prerenderReady = false  (default — explicit "still loading")
  //   window.prerenderReady = true   (set when data + UI are ready)
  // If the signal is never set, fall back to the DOM-content heuristic so
  // routes that haven't opted in still render usefully.
  const readyResult = await page.waitForFunction(() => {
    if (window.prerenderReady === true) return 'signal';
    if (window.prerenderReady === false) return false; // explicit "wait"
    const root = document.querySelector('#root');
    const bodyText = document.body?.innerText?.trim() || '';
    return Boolean(root?.children.length) && bodyText.length > 200 ? 'heuristic' : false;
  }, { timeout: 15000 }).catch(() => null);
  return readyResult ? await readyResult.jsonValue() : null;
}

/**
 * Extract the page's intended HTTP status code from a meta tag emitted by
 * the React app (e.g. NotFoundPage, TenantShell community-not-found,
 * MaintenancePage). Returns 200 if absent or unrecognised.
 */
async function extractStatusCode(page) {
  try {
    const raw = await page.evaluate(() => {
      const el = document.querySelector('meta[name="prerender-status-code"]');
      return el ? el.getAttribute('content') : null;
    });
    if (!raw) return 200;
    const n = Number.parseInt(raw, 10);
    if (!Number.isFinite(n)) return 200;
    return ALLOWED_STATUS_CODES.has(n) ? n : 200;
  } catch {
    return 200;
  }
}

/**
 * Extract a Location URL for 3xx status codes from the standard meta refresh
 * pattern or a `prerender-header` meta tag emitted by the app. Returns null
 * if status isn't a redirect or no target is found.
 */
async function extractRedirectTarget(page, status) {
  if (status !== 301 && status !== 302) return null;
  try {
    return await page.evaluate(() => {
      const headerMeta = document.querySelector('meta[name="prerender-header"][content^="Location:" i]');
      if (headerMeta) {
        const v = headerMeta.getAttribute('content') || '';
        const idx = v.toLowerCase().indexOf('location:');
        if (idx !== -1) return v.slice(idx + 9).trim() || null;
      }
      const canonical = document.querySelector('link[rel="canonical"]');
      return canonical ? canonical.getAttribute('href') : null;
    });
  } catch {
    return null;
  }
}

async function renderPage(page, url) {
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: TIMEOUT });

  const readiness = await waitForUsefulRender(page);

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

  const status = await extractStatusCode(page);
  const redirect = await extractRedirectTarget(page, status);
  const html = await page.content();
  const markdown = status === 200 ? await extractMarkdown(page) : null;
  return { html, status, redirect, readiness, markdown };
}

/**
 * Convert the rendered DOM to a clean Markdown body for AI crawlers (GPTBot,
 * ClaudeBot, Perplexity, etc). LLMs ingest Markdown more efficiently than
 * full HTML — fewer tokens, less noise, better structural signal.
 *
 * Scope: heading hierarchy, paragraphs, links, lists, code blocks, images.
 * Skips: navigation, footer, ads, scripts. Anything inside <header>, <nav>,
 * <footer>, <aside> is dropped — they're per-page chrome, not content.
 *
 * Best-effort: produces useful Markdown even on tenants that don't use
 * semantic HTML. Returns null on extraction failure.
 */
async function extractMarkdown(page) {
  try {
    return await page.evaluate(() => {
      const ROOT_SELECTORS = ['main', 'article', '[role="main"]', '#root > div > main', '#root'];
      let root = null;
      for (const sel of ROOT_SELECTORS) {
        const found = document.querySelector(sel);
        if (found) { root = found; break; }
      }
      if (!root) return null;

      // Clone so we can mutate without affecting the live DOM (and the HTML
      // snapshot, which is read separately).
      const clone = root.cloneNode(true);
      // Strip chrome + scripts before walking.
      const KILL = ['header', 'nav', 'footer', 'aside', 'script', 'style', 'noscript',
                    'form[role="search"]', '[aria-hidden="true"]', '[data-prerender-skip]'];
      for (const sel of KILL) {
        for (const el of clone.querySelectorAll(sel)) el.remove();
      }

      const out = [];
      const escapeMd = (s) => s.replace(/[\\`*_{}[\]()#+\-.!]/g, (c) => '\\' + c);
      const trimTxt = (s) => (s || '').replace(/\s+/g, ' ').trim();

      // Recursive walker — emits Markdown blocks for known structural nodes,
      // recurses through unknown containers.
      const walk = (node, depth = 0) => {
        if (!node) return;
        if (node.nodeType === 3) {
          const t = trimTxt(node.textContent || '');
          if (t) out.push({ inline: t });
          return;
        }
        if (node.nodeType !== 1) return;
        const tag = node.tagName.toLowerCase();

        const childText = () => {
          const buf = [];
          for (const c of node.childNodes) {
            if (c.nodeType === 3) {
              const t = c.textContent || '';
              if (t.trim()) buf.push(t.replace(/\s+/g, ' '));
            } else if (c.nodeType === 1) {
              const ct = c.tagName.toLowerCase();
              const inner = (c.textContent || '').replace(/\s+/g, ' ').trim();
              if (!inner) continue;
              if (ct === 'a') {
                const href = c.getAttribute('href') || '';
                buf.push(href ? `[${inner}](${href})` : inner);
              } else if (ct === 'strong' || ct === 'b') {
                buf.push(`**${inner}**`);
              } else if (ct === 'em' || ct === 'i') {
                buf.push(`*${inner}*`);
              } else if (ct === 'code') {
                buf.push('`' + inner + '`');
              } else if (ct === 'br') {
                buf.push('\n');
              } else {
                buf.push(inner);
              }
            }
          }
          return buf.join(' ').replace(/ +/g, ' ').trim();
        };

        if (/^h[1-6]$/.test(tag)) {
          const level = parseInt(tag[1], 10);
          const text = (node.textContent || '').trim();
          if (text) out.push({ block: '#'.repeat(level) + ' ' + text });
          return;
        }
        if (tag === 'p') {
          const t = childText();
          if (t) out.push({ block: t });
          return;
        }
        if (tag === 'ul' || tag === 'ol') {
          const items = [];
          let idx = 1;
          for (const li of node.querySelectorAll(':scope > li')) {
            const t = trimTxt(li.textContent || '');
            if (!t) continue;
            items.push(tag === 'ol' ? `${idx++}. ${t}` : `- ${t}`);
          }
          if (items.length) out.push({ block: items.join('\n') });
          return;
        }
        if (tag === 'pre') {
          const code = (node.textContent || '').replace(/\n+$/, '');
          if (code.trim()) out.push({ block: '```\n' + code + '\n```' });
          return;
        }
        if (tag === 'blockquote') {
          const t = (node.textContent || '').trim();
          if (t) out.push({ block: t.split('\n').map((l) => '> ' + l.trim()).join('\n') });
          return;
        }
        if (tag === 'img') {
          const src = node.getAttribute('src') || '';
          const alt = (node.getAttribute('alt') || '').trim();
          if (src) out.push({ block: `![${escapeMd(alt)}](${src})` });
          return;
        }
        if (tag === 'hr') { out.push({ block: '---' }); return; }
        if (tag === 'a' && node.childElementCount === 0) {
          const t = (node.textContent || '').trim();
          const href = node.getAttribute('href') || '';
          if (t && href) out.push({ inline: `[${t}](${href})` });
          return;
        }

        // Default: recurse into children.
        for (const c of node.childNodes) walk(c, depth + 1);
      };
      walk(clone);

      // Compose: blocks separated by blank lines, inline runs merged into paragraphs.
      const lines = [];
      let inlineBuf = [];
      const flushInline = () => {
        if (inlineBuf.length) {
          const p = inlineBuf.join(' ').replace(/ +/g, ' ').trim();
          if (p) lines.push(p);
          inlineBuf = [];
        }
      };
      for (const e of out) {
        if (e.block !== undefined) {
          flushInline();
          lines.push(e.block);
        } else if (e.inline !== undefined) {
          inlineBuf.push(e.inline);
        }
      }
      flushInline();

      const title = (document.querySelector('title')?.textContent || '').trim();
      const desc = (document.querySelector('meta[name="description"]')?.getAttribute('content') || '').trim();
      const canonical = (document.querySelector('link[rel="canonical"]')?.getAttribute('href') || '').trim();

      const header = [
        '---',
        title  ? `title: ${title.replace(/\n/g, ' ')}` : null,
        desc   ? `description: ${desc.replace(/\n/g, ' ')}` : null,
        canonical ? `canonical: ${canonical}` : null,
        `generated: ${new Date().toISOString()}`,
        '---',
        '',
      ].filter(Boolean).join('\n');

      const body = lines.join('\n\n').replace(/\n{3,}/g, '\n\n').trim();
      if (!body) return null;
      return header + body + '\n';
    });
  } catch {
    return null;
  }
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

  // Viewport variant. Default desktop; flip to mobile via PRERENDER_VIEWPORT=mobile.
  // Mobile uses a representative iPhone size + Safari Mobile UA so any mobile-only
  // CSS / responsive layouts render correctly. The serving layer (nginx) is
  // currently single-variant, so a `mobile` snapshot is only useful with a
  // follow-up routing change — the option exists so per-route mobile renders
  // can be staged independently of the routing flip.
  const variant = (process.env.PRERENDER_VIEWPORT || 'desktop').toLowerCase();
  const isMobile = variant === 'mobile';
  const context = await browser.newContext({
    userAgent: isMobile
      ? 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 NexusPrerender/1.0'
      : 'NexusPrerender/1.0 (internal; deploy-time rendering)',
    viewport: isMobile ? { width: 414, height: 896 } : { width: 1280, height: 720 },
    isMobile,
    hasTouch: isMobile,
  });
  console.log(`Viewport: ${isMobile ? 'mobile (414x896)' : 'desktop (1280x720)'}`);

  const results = {
    success: 0,
    failed: 0,
    startedAt: new Date().toISOString(),
    finishedAt: null,
    entries: [],
  };
  const successfulCachePaths = [];
  const failedCachePaths = [];
  // Per-route status overrides for nginx. Built from non-200 results so the
  // bash injection step can ship an nginx config snippet.
  // Shape: { "<cachePath>": { status: 404, location: "..." | null, route: "/foo", host: "x.tld" } }
  const statusOverrides = {};
  let nextIndex = 0;

  async function renderEntry(entry) {
    const page = await context.newPage();
    const label = entry.canonicalUrl || entry.url;
    try {
      const { html, status, redirect, readiness, markdown } = await renderPage(page, entry.url);
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

      // Integrity sidecar: SHA-256 of the bytes we just wrote. Lets the
      // PHP side detect corruption (truncated writes, downstream filesystem
      // tampering, bit rot) without re-hashing megabytes on every inspect.
      // Format: "<hex>  <byte-count>" — same shape as `sha256sum` output
      // so an operator can verify by hand.
      const sha = createHash('sha256').update(html, 'utf-8').digest('hex');
      writeFileSync(`${outputDir}/index.html.sha256`, `${sha}  ${Buffer.byteLength(html, 'utf-8')}`, 'utf-8');

      // Sidecar Markdown for AI crawlers (Phase 5). LLMs ingest Markdown
      // more efficiently than HTML — fewer tokens, less chrome noise. Only
      // for 200 responses; non-200s aren't useful as ingested content.
      if (markdown && status === 200) {
        writeFileSync(`${outputDir}/index.md`, markdown, 'utf-8');
      }

      // Drop a sidecar `_status` file so PrerenderService::inspect can surface
      // the intended HTTP status. The bash injection step also reads these
      // when assembling the nginx override map.
      if (status && status !== 200) {
        writeFileSync(`${outputDir}/_status`, String(status), 'utf-8');
        if (entry.cachePath) {
          statusOverrides[entry.cachePath] = {
            status,
            location: redirect,
            route: entry.route || null,
            host: entry.host || null,
          };
        }
      }

      console.log(`  rendered ${label} (${(size / 1024).toFixed(1)}KB) [status=${status}, ready=${readiness || 'timeout'}]`);
      results.success++;
      results.entries.push({
        url: label,
        output: entry.output,
        cachePath: entry.cachePath || null,
        status: 'rendered',
        httpStatus: status,
        redirectLocation: redirect || null,
        readiness: readiness || 'timeout',
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
  writeFileSync('/output/.prerender-status-overrides.json', JSON.stringify(statusOverrides, null, 2), 'utf-8');

  console.log(`\nDone: ${results.success} succeeded, ${results.failed} failed`);
  process.exit(results.failed > 0 ? 1 : 0);
}

main();
