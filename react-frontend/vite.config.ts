// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { defineConfig, loadEnv } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import { VitePWA } from 'vite-plugin-pwa'
import { ViteImageOptimizer } from 'vite-plugin-image-optimizer'
import path from 'path'
import { execSync } from 'child_process'
import { createRequire } from 'module'

// Inject git commit SHA at build time for version verification.
// In Docker production builds, BUILD_COMMIT is passed as a build arg (since
// .dockerignore excludes .git/). Falls back to git for local dev.
const commitHash = process.env.BUILD_COMMIT || (() => {
  try {
    return execSync('git rev-parse --short HEAD', { stdio: ['ignore', 'pipe', 'ignore'] }).toString().trim()
  } catch {
    return 'dev'
  }
})()

const usePolling = process.env.VITE_USE_POLLING === '1' || process.env.CHOKIDAR_USEPOLLING === 'true'
const require = createRequire(import.meta.url)
const canOptimizeImages = (() => {
  try {
    require.resolve('sharp')
    return true
  } catch {
    return false
  }
})()

export default defineConfig(({ command, mode }) => {
  const env = loadEnv(mode, process.cwd(), 'VITE_')
  const apiUrl = env.VITE_API_URL || process.env.VITE_API_URL || 'http://localhost:8090'

  return {
  plugins: [
    react(),
    tailwindcss(),
    // Emit /build-info.json into the dist root at build time. Kept as a
    // simple ops-debugging endpoint — `curl https://app.project-nexus.ie/build-info.json`
    // reports the deployed commit. Excluded from precache (no .json in
    // globPatterns) and served with no-cache by nginx. No frontend code
    // consumes it anymore — the api.ts stale-client gate uses the X-Build
    // response header instead.
    {
      name: 'nexus:build-info',
      generateBundle() {
        this.emitFile({
          type: 'asset',
          fileName: 'build-info.json',
          source: JSON.stringify({ commit: commitHash }),
        });
      },
    },
    // VitePWA is always loaded so its virtual module (virtual:pwa-register) is
    // resolvable in dev. The plugin is disabled in dev via the `disable` flag —
    // no service worker is generated or registered, so there is zero SW overhead.
    VitePWA({
      disable: command !== 'build',
      registerType: 'prompt',
      // Don't inject SW registration into index.html automatically —
      // we handle it in main.tsx so it only fires in production builds
      injectRegister: null,
      manifest: false, // We use our own public/manifest.json
      workbox: {
        // Import our custom push event handler into the generated SW. The file
        // lives in /public, ships to the build root, and is resolvable at the
        // SW scope (/sw-push-handler.js). Adding push/notificationclick
        // listeners to the workbox-generated SW is otherwise impossible
        // without switching to InjectManifest.
        importScripts: ['/sw-push-handler.js'],
        // PRECACHE: only content-hashed, immutable build artefacts. The HTML
        // shell (index.html) is intentionally NOT included — it's served
        // NetworkFirst at runtime so every navigation hits the network for the
        // current shell, falling back to the most recent cached copy only when
        // the network is slow or offline. This is the pattern GitHub, Linear,
        // Vercel, Notion, and most production SPAs use. Precaching the HTML
        // is what forced this codebase into the "click Update to get fresh
        // code" workflow that PWAs are notorious for. Removing it makes
        // deploys propagate to users on their next navigation, with no UI.
        globPatterns: ['**/*.{js,css,ico,png,svg,woff2}'],
        maximumFileSizeToCacheInBytes: 5 * 1024 * 1024,
        skipWaiting: true,
        clientsClaim: true,
        cleanupOutdatedCaches: true,
        runtimeCaching: [
          // HTML shell — NetworkFirst with a 3s timeout. Online: every nav
          // gets the freshly deployed shell. Slow network or offline: falls
          // back to the most recent cached HTML so the app still loads.
          // Excludes API, admin-legacy, health, and the emergency recovery
          // URLs — they MUST always go to the network so nginx can return
          // the real response (Clear-Site-Data header on /api/sw-reset and
          // /clear-site-data; live data on /api/* etc.).
          {
            urlPattern: ({ request, url }) => {
              if (request.mode !== 'navigate') return false;
              const p = url.pathname;
              if (p.startsWith('/api/')) return false;
              if (p.startsWith('/admin-legacy/')) return false;
              if (p === '/health.php') return false;
              // /api/sw-reset is the emergency recovery URL — must always
              // hit the network so nginx can return Clear-Site-Data + the
              // inline unregister script.
              if (p === '/api/sw-reset') return false;
              return true;
            },
            handler: 'NetworkFirst',
            options: {
              cacheName: 'nexus-html-shell',
              networkTimeoutSeconds: 3,
              expiration: { maxEntries: 16, maxAgeSeconds: 7 * 86400 },
              matchOptions: { ignoreSearch: true },
              // 200 only — status 0 (opaque cross-origin no-cors) responses
              // shouldn't be cached as the HTML shell. Our HTML is same-origin
              // so this would never hit, but tightening eliminates the footgun.
              cacheableResponse: { statuses: [200] },
            },
          },
          {
            urlPattern: /^https?.*\/locales\//,
            handler: 'StaleWhileRevalidate',
            options: {
              cacheName: 'nexus-locales',
              expiration: { maxEntries: 50, maxAgeSeconds: 86400 },
            },
          },
        ],
        // Explicitly disable the default NavigationRoute. vite-plugin-pwa
        // defaults navigateFallback to 'index.html', which registers a
        // precache-first NavigationRoute BEFORE the runtimeCaching rules,
        // so it would intercept every navigation and serve the stale shell
        // before the NetworkFirst handler above could see the request.
        // Setting navigateFallback to null disables this default and lets
        // the urlPattern callback above own all navigation routing.
        navigateFallback: null,
      },
    }),
    // Image optimizer only runs during build — skip in dev for faster startup.
    // Some local environments end up with an incomplete sharp install, which
    // makes vite-plugin-image-optimizer log asset errors despite a successful build.
    ...(command === 'build' && canOptimizeImages ? [ViteImageOptimizer({
      png: { quality: 80 },
      jpeg: { quality: 80 },
      jpg: { quality: 80 },
      webp: { lossless: false, quality: 80 },
      svg: { plugins: [{ name: 'preset-default' }] },
      logStats: true,
    })] : []),
    ...(command === 'build' && !canOptimizeImages ? [{
      name: 'nexus:missing-sharp-warning',
      configResolved() {
        console.warn('[vite] Skipping image optimization because sharp is not resolvable in this workspace.')
      },
    }] : []),
  ],
  define: {
    __BUILD_COMMIT__: JSON.stringify(commitHash),
    __BUILD_TIME__: JSON.stringify(new Date().toISOString()),
  },
  resolve: {
    alias: [
      { find: '@', replacement: path.resolve(__dirname, './src') },
      // lucide-react v0.453.0 has no package exports map, so sub-path imports
      // like `lucide-react/icons/lock` don't resolve. Map them to the actual
      // ESM dist files. Each file exports the icon as default, matching usage.
      {
        find: /^lucide-react\/icons\/(.+)$/,
        replacement: path.resolve(__dirname, 'node_modules/lucide-react/dist/esm/icons/$1.js'),
      },
    ],
  },
  optimizeDeps: {
    // Pre-bundle heavy deps so Vite doesn't re-crawl them on every page load.
    // lucide-react removed: imports now use individual sub-path files
    // (lucide-react/icons/*) so the full-barrel 784 KB pre-bundle is no longer needed.
    include: [
      '@heroui/react',
      'framer-motion',
      'react',
      'react-dom',
      'react-router-dom',
      'recharts',
      'i18next',
      'react-i18next',
    ],
  },
  server: {
    port: 5173,
    host: '0.0.0.0', // Required for Docker
    // Do not enable Vite warmup here. This app's root graph is large, and
    // warming it made Vite report "ready" while still spending 10s+ transforming
    // modules, which caused audit/browser navigations to time out.
    watch: {
      // Polling over Docker Desktop's Windows bind mount pegged the Vite
      // container at high idle CPU and made even static file requests take
      // seconds. Keep it opt-in for machines that truly need it for HMR.
      usePolling,
      interval: 1000,
      ignored: [
        '**/node_modules/**',
        '**/dist/**',
        '**/coverage/**',
        '**/playwright-report/**',
        '**/test-results/**',
        '**/.codex_tmp/**',
        '**/*.log',
      ],
    },
    proxy: {
      // Proxy API requests to PHP backend
      // Uses Docker service name 'app' when running in Docker, localhost:8090 otherwise
      '/api': {
        target: apiUrl,
        changeOrigin: true,
        secure: false,
        timeout: 120000,
        proxyTimeout: 120000,
        configure: (proxy) => {
          proxy.on('error', (err) => { console.error('[vite-proxy] error:', err.message); });
        },
        headers: {
          // Ensure headers are forwarded
          'X-Forwarded-Proto': 'http',
        },
      },
      // Proxy legacy admin panel to PHP backend
      '/admin-legacy': {
        target: apiUrl,
        changeOrigin: true,
      },
      // Proxy uploaded assets (images, media) to PHP backend
      '/uploads': {
        target: apiUrl,
        changeOrigin: true,
      },
      // Proxy health check
      '/health.php': {
        target: apiUrl,
        changeOrigin: true,
      },
    },
  },
  build: {
    outDir: 'dist',
    rollupOptions: {
      // Capacitor packages are optional native deps — not installed in web builds.
      // Guard all usages with window.Capacitor?.isNativePlatform?.() checks.
      external: ['@capacitor/app', '@capacitor/push-notifications'],
      output: {
        manualChunks: {
          'vendor-react': ['react', 'react-dom', 'react-router-dom'],
          'vendor-heroui': ['@heroui/react'],
          'vendor-motion': ['framer-motion'],
          'vendor-i18n': ['i18next', 'react-i18next'],
          'vendor-charts': ['recharts'],
        },
      },
    },
  },
  }
})
