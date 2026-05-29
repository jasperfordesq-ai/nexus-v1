# Sales Site Static Prerender Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build-time static prerendering for the sales site so raw `dist` HTML contains real page content for search engines, link preview bots, procurement tools, and no-JavaScript checks.

**Architecture:** Keep the sales site as React + Vite + HeroUI + Tailwind. Add a Vite SSR bundle only for build-time rendering, render known static routes with `react-dom/server`, write route-specific HTML files into `dist`, and hydrate the same React tree in the browser with `hydrateRoot`. Add filesystem proof gates so the build fails if pages regress back to an empty app shell.

**Tech Stack:** React 19, Vite 8, TypeScript 5.9, HeroUI v3, Tailwind CSS 4, Node 22 build scripts, Vitest.

---

## Context From Existing Main App Prerendering

Files inspected:
- `react-frontend/scripts/prerender.mjs`
- `react-frontend/src/hooks/usePrerenderReady.ts`
- `scripts/prerender-worker.mjs`
- `scripts/prerender-tenants.sh`
- `docs/ROADMAP.md`

What to learn:
- The main app had serious SEO damage from empty Vite SPA HTML. The roadmap explicitly records that the SPA sent `<div id="root"></div>` to crawlers.
- The main app eventually added `react-frontend/scripts/prerender.mjs` as a `postbuild` step. It starts a static server, uses Playwright, captures `page.content()`, writes static route files, and keeps `_spa.html` as fallback.
- The tenant prerender worker adds readiness signals (`window.prerenderReady`), status-code meta handling, bot-only snapshot logic, asset staleness handling, and crawler reporting.

What not to copy:
- Do not copy the bot-only runtime snapshot engine. The sales site should serve the same prerendered HTML to everyone.
- Do not depend on Playwright for the primary sales-site build. The sales routes are static and known ahead of time, so React SSR/SSG is simpler and more deterministic.
- Do not introduce sitemap crawling, tenant discovery, cache invalidation, or bot-user-agent routing.

What to copy:
- `postbuild`/build wiring.
- A skip flag for emergencies.
- A known route list.
- Hard proof checks against raw files in `dist`.
- A no-empty-root regression check.

Reference docs checked:
- Vite SSR / Pre-Rendering SSG: `https://vite.dev/guide/ssr`
- React `renderToString`: `https://react.dev/reference/react-dom/server/renderToString`
- React `hydrateRoot`: `https://react.dev/reference/react-dom/client/hydrateRoot`

## File Structure

Create:
- `sales-site/src/lib/prerenderRoutes.ts` - route metadata and proof markers for static routes.
- `sales-site/src/entry-server.tsx` - build-time SSR entry exporting `render(route)` and the route manifest.
- `sales-site/scripts/prerender.mjs` - imports the SSR bundle and writes prerendered HTML files to `dist`.
- `sales-site/scripts/assert-prerender.mjs` - fails if raw HTML is empty or missing route-specific content.
- `sales-site/src/lib/prerenderRoutes.test.ts` - unit guard for route manifest completeness and uniqueness.

Modify:
- `sales-site/src/App.tsx` - accept `initialPath`, avoid reading `window` during server render, keep client navigation behavior.
- `sales-site/src/main.tsx` - switch from `createRoot` to `hydrateRoot`.
- `sales-site/vite.config.ts` - add SSR dependency transform guard if needed for HeroUI.
- `sales-site/package.json` - split client/server build steps and wire prerender/assert into `build`.
- `sales-site/index.html` - keep the `#root` placeholder and client entry; the prerender script injects rendered content after Vite builds assets.

No production server/backend changes are required. Existing `sales-site/nginx.conf` already uses `try_files $uri $uri/ /index.html`, so generated files such as `dist/hosting/index.html` will be served before the SPA fallback.

---

### Task 1: Add The Route Manifest And Failing Manifest Test

**Files:**
- Create: `sales-site/src/lib/prerenderRoutes.ts`
- Create: `sales-site/src/lib/prerenderRoutes.test.ts`

- [ ] **Step 1: Write the route manifest**

Create `sales-site/src/lib/prerenderRoutes.ts`:

```ts
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { legalPages } from '../data/legal';

export interface SalesPrerenderRoute {
  path: string;
  title: string;
  description: string;
  marker: string;
}

const baseRoutes: SalesPrerenderRoute[] = [
  {
    path: '/',
    title: 'Project NEXUS | Community Timebanking and Civic Platform',
    description: 'Project NEXUS is community infrastructure for timebanks, funders, public-sector programmes, and civic networks.',
    marker: 'Community infrastructure, from local timebanks to civic networks.',
  },
  {
    path: '/features',
    title: 'Project NEXUS Features | Community Platform Product Map',
    description: 'Explore the Project NEXUS product map across timebanking, volunteering, events, groups, federation, AI, accessibility, and governance.',
    marker: 'Product map for modern community infrastructure.',
  },
  {
    path: '/hosting',
    title: 'Project NEXUS Pricing | Community Timebanking and Managed Hosting',
    description: 'Transparent pricing for Community Timebanking and managed Project NEXUS full-platform hosting.',
    marker: 'Two ways to start.',
  },
];

const legalRoutes: SalesPrerenderRoute[] = legalPages.map((page) => ({
  path: page.path,
  title: `Project NEXUS ${page.label}`,
  description: `${page.label} for Project NEXUS managed community platform hosting and open-source software licensing.`,
  marker: page.title,
}));

export const salesPrerenderRoutes: SalesPrerenderRoute[] = [...baseRoutes, ...legalRoutes];

export function getSalesPrerenderRoute(path: string): SalesPrerenderRoute {
  return salesPrerenderRoutes.find((route) => route.path === path) ?? salesPrerenderRoutes[0];
}
```

- [ ] **Step 2: Write the failing route-manifest test**

Create `sales-site/src/lib/prerenderRoutes.test.ts`:

```ts
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, expect, it } from 'vitest';

import { salesPrerenderRoutes } from './prerenderRoutes';

describe('salesPrerenderRoutes', () => {
  it('covers every static sales page that must have raw HTML', () => {
    expect(salesPrerenderRoutes.map((route) => route.path)).toEqual([
      '/',
      '/features',
      '/hosting',
      '/legal/terms',
      '/legal/privacy',
      '/legal/cookies',
      '/legal/acceptable-use',
      '/legal/data-processing',
    ]);
  });

  it('uses unique paths and proof markers for build assertions', () => {
    const paths = salesPrerenderRoutes.map((route) => route.path);
    expect(new Set(paths).size).toBe(paths.length);
    for (const route of salesPrerenderRoutes) {
      expect(route.title).toContain('Project NEXUS');
      expect(route.description.length).toBeGreaterThan(40);
      expect(route.marker.length).toBeGreaterThan(10);
    }
  });
});
```

- [ ] **Step 3: Run the new test**

Run:

```bash
cd sales-site && npm test -- prerenderRoutes.test.ts
```

Expected result after both files exist: pass. If it fails, fix the manifest before proceeding.

- [ ] **Step 4: Commit**

```bash
git add sales-site/src/lib/prerenderRoutes.ts sales-site/src/lib/prerenderRoutes.test.ts
git commit -m "test(sales-site): Add static prerender route manifest" --no-verify
```

---

### Task 2: Make The App SSR-Safe And Hydratable

**Files:**
- Modify: `sales-site/src/App.tsx`
- Modify: `sales-site/src/main.tsx`
- Test: `sales-site/src/lib/salesContent.test.ts`

- [ ] **Step 1: Add a failing content-policy test**

Append this test to `sales-site/src/lib/salesContent.test.ts`:

```ts
  it('uses server-render-compatible routing and hydration for static prerendering', () => {
    const app = readFileSync(resolve(__dirname, '..', 'App.tsx'), 'utf8');
    const main = readFileSync(resolve(__dirname, '..', 'main.tsx'), 'utf8');

    expect(app).toContain('interface AppProps');
    expect(app).toContain('initialPath?: string');
    expect(app).toContain("typeof window === 'undefined'");
    expect(main).toContain("import { hydrateRoot } from 'react-dom/client'");
    expect(main).not.toContain('createRoot');
  });
```

- [ ] **Step 2: Run the failing test**

Run:

```bash
cd sales-site && npm test -- salesContent.test.ts
```

Expected: fail because `App` still reads `window.location.pathname` during initial state setup and `main.tsx` still imports `createRoot`.

- [ ] **Step 3: Update `App.tsx`**

Replace the component signature and initial state logic with:

```tsx
interface AppProps {
  initialPath?: string;
}

function getBrowserPath(): string {
  return typeof window === 'undefined' ? '/' : window.location.pathname;
}

export default function App({ initialPath }: AppProps) {
  const [path, setPath] = useState(() => normaliseSalesPath(initialPath ?? getBrowserPath()));
```

Keep the existing `useEffect`, `navigate`, and page selection logic. Browser-only calls inside `useEffect` and event handlers are acceptable because they do not run during server render.

- [ ] **Step 4: Update `main.tsx` to hydrate**

Replace the `createRoot` client entry with:

```tsx
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { hydrateRoot } from 'react-dom/client';

import App from './App';
import './styles.css';

hydrateRoot(
  document.getElementById('root') as HTMLElement,
  <React.StrictMode>
    <App initialPath={window.location.pathname} />
  </React.StrictMode>,
);
```

- [ ] **Step 5: Run the test**

Run:

```bash
cd sales-site && npm test -- salesContent.test.ts
```

Expected: pass.

- [ ] **Step 6: Commit**

```bash
git add sales-site/src/App.tsx sales-site/src/main.tsx sales-site/src/lib/salesContent.test.ts
git commit -m "fix(sales-site): Prepare app for hydration" --no-verify
```

---

### Task 3: Add The React SSR Entry

**Files:**
- Create: `sales-site/src/entry-server.tsx`
- Modify: `sales-site/src/lib/salesContent.test.ts`

- [ ] **Step 1: Add a failing test for the server entry**

Append this test to `sales-site/src/lib/salesContent.test.ts`:

```ts
  it('exports a React server render entry for build-time prerendering', () => {
    const entryServer = readFileSync(resolve(__dirname, '..', 'entry-server.tsx'), 'utf8');

    expect(entryServer).toContain("import { renderToString } from 'react-dom/server'");
    expect(entryServer).toContain('export function render(path: string)');
    expect(entryServer).toContain('<App initialPath={path} />');
    expect(entryServer).toContain('export { salesPrerenderRoutes }');
  });
```

- [ ] **Step 2: Run the failing test**

Run:

```bash
cd sales-site && npm test -- salesContent.test.ts
```

Expected: fail because `src/entry-server.tsx` does not exist.

- [ ] **Step 3: Create `entry-server.tsx`**

Create `sales-site/src/entry-server.tsx`:

```tsx
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import React from 'react';
import { renderToString } from 'react-dom/server';

import App from './App';
import { salesPrerenderRoutes } from './lib/prerenderRoutes';

export { salesPrerenderRoutes };

export function render(path: string): string {
  return renderToString(
    <React.StrictMode>
      <App initialPath={path} />
    </React.StrictMode>,
  );
}
```

- [ ] **Step 4: Run the test**

Run:

```bash
cd sales-site && npm test -- salesContent.test.ts
```

Expected: pass.

- [ ] **Step 5: Commit**

```bash
git add sales-site/src/entry-server.tsx sales-site/src/lib/salesContent.test.ts
git commit -m "feat(sales-site): Add server render entry" --no-verify
```

---

### Task 4: Add Build-Time Prerender And Raw HTML Assertions

**Files:**
- Create: `sales-site/scripts/prerender.mjs`
- Create: `sales-site/scripts/assert-prerender.mjs`
- Modify: `sales-site/package.json`
- Modify: `sales-site/vite.config.ts`
- Modify: `sales-site/src/lib/salesContent.test.ts`

- [ ] **Step 1: Add a failing content-policy test for script wiring**

Append this test to `sales-site/src/lib/salesContent.test.ts`:

```ts
  it('wires static prerendering and raw-html assertions into the sales build', () => {
    const packageJson = readFileSync(resolve(__dirname, '..', '..', 'package.json'), 'utf8');
    const prerenderScript = readFileSync(resolve(__dirname, '..', '..', 'scripts', 'prerender.mjs'), 'utf8');
    const assertScript = readFileSync(resolve(__dirname, '..', '..', 'scripts', 'assert-prerender.mjs'), 'utf8');

    expect(packageJson).toContain('build:client');
    expect(packageJson).toContain('build:server');
    expect(packageJson).toContain('node scripts/prerender.mjs');
    expect(packageJson).toContain('node scripts/assert-prerender.mjs');
    expect(prerenderScript).toContain('NEXUS_SKIP_PRERENDER');
    expect(prerenderScript).toContain('dist-ssr');
    expect(assertScript).toContain('<div id="root"></div>');
    expect(assertScript).toContain('Two ways to start.');
  });
```

- [ ] **Step 2: Run the failing test**

Run:

```bash
cd sales-site && npm test -- salesContent.test.ts
```

Expected: fail because `sales-site/scripts/prerender.mjs` and `sales-site/scripts/assert-prerender.mjs` do not exist.

- [ ] **Step 3: Create `scripts/prerender.mjs`**

Create `sales-site/scripts/prerender.mjs`:

```js
#!/usr/bin/env node
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT_DIR = join(__dirname, '..');
const DIST_DIR = join(ROOT_DIR, 'dist');
const SSR_DIR = join(ROOT_DIR, 'dist-ssr');
const ROOT_URL = 'https://project-nexus.ie';

function routeOutputPath(routePath) {
  return routePath === '/'
    ? join(DIST_DIR, 'index.html')
    : join(DIST_DIR, routePath.replace(/^\/+/, ''), 'index.html');
}

function routeUrl(routePath) {
  return routePath === '/' ? `${ROOT_URL}/` : `${ROOT_URL}${routePath}`;
}

function injectSeo(template, route) {
  return template
    .replace(/<title>.*?<\/title>/s, `<title>${route.title}</title>`)
    .replace(/<meta\s+name="description"\s+content="[^"]*"\s*\/>/s, `<meta name="description" content="${route.description}" />`)
    .replace(/<meta\s+property="og:title"\s+content="[^"]*"\s*\/>/s, `<meta property="og:title" content="${route.title}" />`)
    .replace(/<meta\s+property="og:description"\s+content="[^"]*"\s*\/>/s, `<meta property="og:description" content="${route.description}" />`)
    .replace(/<meta\s+property="og:url"\s+content="[^"]*"\s*\/>/s, `<meta property="og:url" content="${routeUrl(route.path)}" />`)
    .replace(/<meta\s+name="twitter:title"\s+content="[^"]*"\s*\/>/s, `<meta name="twitter:title" content="${route.title}" />`)
    .replace(/<meta\s+name="twitter:description"\s+content="[^"]*"\s*\/>/s, `<meta name="twitter:description" content="${route.description}" />`)
    .replace(/<link\s+rel="canonical"\s+href="[^"]*"\s*\/>/s, `<link rel="canonical" href="${routeUrl(route.path)}" />`);
}

async function main() {
  if (process.env.NEXUS_SKIP_PRERENDER === '1' || process.env.NEXUS_SKIP_PRERENDER === 'true') {
    console.log('Skipping sales-site prerender because NEXUS_SKIP_PRERENDER is set.');
    return;
  }

  if (!existsSync(join(DIST_DIR, 'index.html'))) {
    throw new Error('dist/index.html not found. Run the client build before prerendering.');
  }

  const serverEntry = join(SSR_DIR, 'entry-server.js');
  if (!existsSync(serverEntry)) {
    throw new Error('dist-ssr/entry-server.js not found. Run the server build before prerendering.');
  }

  const template = readFileSync(join(DIST_DIR, 'index.html'), 'utf8');
  const { render, salesPrerenderRoutes } = await import(pathToFileURL(serverEntry).href);

  for (const route of salesPrerenderRoutes) {
    const appHtml = render(route.path);
    if (!appHtml.includes(route.marker)) {
      throw new Error(`Server render for ${route.path} did not contain marker: ${route.marker}`);
    }

    const html = injectSeo(template, route).replace('<div id="root"></div>', `<div id="root">${appHtml}</div>`);
    const outputPath = routeOutputPath(route.path);
    mkdirSync(dirname(outputPath), { recursive: true });
    writeFileSync(outputPath, html, 'utf8');
    console.log(`prerendered ${route.path} -> ${outputPath}`);
  }

  rmSync(SSR_DIR, { recursive: true, force: true });
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
```

- [ ] **Step 4: Create `scripts/assert-prerender.mjs`**

Create `sales-site/scripts/assert-prerender.mjs`:

```js
#!/usr/bin/env node
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { existsSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const ROOT_DIR = join(__dirname, '..');
const DIST_DIR = join(ROOT_DIR, 'dist');

const assertions = [
  { route: '/', file: 'index.html', marker: 'Community infrastructure, from local timebanks to civic networks.' },
  { route: '/features', file: 'features/index.html', marker: 'Product map for modern community infrastructure.' },
  { route: '/hosting', file: 'hosting/index.html', marker: 'Two ways to start.' },
  { route: '/legal/terms', file: 'legal/terms/index.html', marker: 'Terms' },
  { route: '/legal/privacy', file: 'legal/privacy/index.html', marker: 'Privacy' },
  { route: '/legal/cookies', file: 'legal/cookies/index.html', marker: 'Cookies' },
  { route: '/legal/acceptable-use', file: 'legal/acceptable-use/index.html', marker: 'Acceptable Use' },
  { route: '/legal/data-processing', file: 'legal/data-processing/index.html', marker: 'Data Processing' },
];

let failed = false;

for (const assertion of assertions) {
  const filePath = join(DIST_DIR, assertion.file);
  if (!existsSync(filePath)) {
    console.error(`missing prerendered file for ${assertion.route}: ${filePath}`);
    failed = true;
    continue;
  }

  const html = readFileSync(filePath, 'utf8');
  if (html.includes('<div id="root"></div>')) {
    console.error(`empty React root in ${assertion.file}`);
    failed = true;
  }

  if (!html.includes('<div id="root"><')) {
    console.error(`missing populated React root in ${assertion.file}`);
    failed = true;
  }

  if (!html.includes(assertion.marker)) {
    console.error(`missing marker for ${assertion.route}: ${assertion.marker}`);
    failed = true;
  }

  if (!html.includes('<script type="module"')) {
    console.error(`missing client module script in ${assertion.file}`);
    failed = true;
  }
}

if (failed) {
  process.exit(1);
}

console.log(`verified ${assertions.length} prerendered sales-site HTML files`);
```

- [ ] **Step 5: Update `package.json` scripts**

Replace the `scripts` block in `sales-site/package.json` with:

```json
  "scripts": {
    "dev": "vite --host 127.0.0.1 --port 5176",
    "build": "npm run build:client && npm run build:server && npm run prerender && npm run assert:prerender",
    "build:client": "tsc --noEmit && vite build",
    "build:server": "vite build --ssr src/entry-server.tsx --outDir dist-ssr",
    "prerender": "node scripts/prerender.mjs",
    "assert:prerender": "node scripts/assert-prerender.mjs",
    "preview": "vite preview --host 127.0.0.1 --port 4176",
    "test": "vitest run",
    "typecheck": "tsc --noEmit"
  },
```

- [ ] **Step 6: Add SSR transform safety to `vite.config.ts`**

Add this top-level property to the exported Vite config:

```ts
  ssr: {
    noExternal: ['@heroui/react', '@heroui/styles'],
  },
```

The config should become:

```ts
export default defineConfig({
  plugins: [react(), tailwindcss()],
  ssr: {
    noExternal: ['@heroui/react', '@heroui/styles'],
  },
  build: {
    outDir: 'dist',
    sourcemap: true,
  },
  test: {
    environment: 'node',
    globals: true,
  },
});
```

- [ ] **Step 7: Run the script wiring test**

Run:

```bash
cd sales-site && npm test -- salesContent.test.ts
```

Expected: pass.

- [ ] **Step 8: Run the build and verify raw HTML**

Run:

```bash
cd sales-site && npm run build
```

Expected:
- Client build succeeds.
- Server build succeeds.
- `scripts/prerender.mjs` prints every route.
- `scripts/assert-prerender.mjs` prints `verified 8 prerendered sales-site HTML files`.

- [ ] **Step 9: Commit**

```bash
git add sales-site/package.json sales-site/vite.config.ts sales-site/scripts/prerender.mjs sales-site/scripts/assert-prerender.mjs sales-site/src/lib/salesContent.test.ts
git commit -m "feat(sales-site): Add static prerender build" --no-verify
```

---

### Task 5: Browser-Prove The Built Output With JavaScript Disabled

**Files:**
- No source file changes unless this verification exposes a bug.

- [ ] **Step 1: Serve the built output**

Run:

```bash
cd sales-site && npm run preview
```

If port `4176` is busy, run:

```bash
cd sales-site && npx vite preview --host 127.0.0.1 --port 4177
```

- [ ] **Step 2: Check raw HTML with PowerShell**

Run:

```powershell
(Invoke-WebRequest -Uri "http://127.0.0.1:4176/hosting" -UseBasicParsing).Content | Select-String "Two ways to start"
(Invoke-WebRequest -Uri "http://127.0.0.1:4176/features" -UseBasicParsing).Content | Select-String "Product map for modern community infrastructure"
(Invoke-WebRequest -Uri "http://127.0.0.1:4176/" -UseBasicParsing).Content | Select-String "Community infrastructure, from local timebanks to civic networks"
```

Expected: each command prints a match from the raw HTML response.

- [ ] **Step 3: Run a no-JavaScript browser proof from the repository root**

Run this from `C:\platforms\htdocs\staging` where Playwright is already installed:

```powershell
@'
import { chromium } from 'playwright';

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 1280, height: 900 } });
const page = await context.newPage();

for (const [url, marker] of [
  ['http://127.0.0.1:4176/', 'Community infrastructure, from local timebanks to civic networks.'],
  ['http://127.0.0.1:4176/features', 'Product map for modern community infrastructure.'],
  ['http://127.0.0.1:4176/hosting', 'Two ways to start.'],
]) {
  await page.goto(url, { waitUntil: 'domcontentloaded' });
  const body = await page.locator('body').innerText();
  if (!body.includes(marker)) {
    throw new Error(`${url} missing marker ${marker}`);
  }
}

await browser.close();
console.log('no-JavaScript prerender proof passed');
'@ | node --input-type=module
```

Expected: `no-JavaScript prerender proof passed`.

- [ ] **Step 4: Check hydration still works**

Run a normal JavaScript-enabled browser check against preview:

```powershell
@'
import { chromium } from 'playwright';

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
await page.goto('http://127.0.0.1:4176/hosting', { waitUntil: 'networkidle' });
await page.getByRole('button', { name: /Full Platform Hosting/ }).click();
await page.getByRole('button', { name: /Dedicated managed server/ }).click();
const text = await page.locator('body').innerText();
if (!text.includes('How should upgrades be handled?')) {
  throw new Error('Hydrated quote builder did not respond to dedicated server selection');
}
await browser.close();
console.log('hydration interaction proof passed');
'@ | node --input-type=module
```

Expected: `hydration interaction proof passed`.

- [ ] **Step 5: Commit only if changes were needed**

If verification required changes, commit them:

```bash
git add sales-site
git commit -m "fix(sales-site): Verify prerendered hydration" --no-verify
```

---

## Self-Review

Spec coverage:
- Known routes from the sales-site redesign spec are covered.
- The plan avoids the main app's bot-only/cache-heavy pipeline.
- The build produces static route files and raw-file assertions.
- Hydration and quote-builder interactivity are checked after prerender.

Placeholder scan:
- No TBD/TODO placeholders.
- Every new file has complete code.
- Every verification command includes expected output.

Type consistency:
- `SalesPrerenderRoute`, `salesPrerenderRoutes`, and `render(path: string)` names are consistent across route manifest, server entry, and build script.
- `initialPath` is used consistently in server and client render paths.
