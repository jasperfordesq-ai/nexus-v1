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
    // Emit /build-info.json into the dist root at build time.
    // This file is NOT in the workbox precache glob patterns (*.{js,css,html,...}),
    // so old service workers pass fetch requests for it straight to nginx.
    // nginx serves it with no-cache (location / rule). The useVersionCheck hook
    // polls this file to detect deploys independently of SW update mechanics,
    // rescuing users who have a stale or broken service worker.
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
        globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2}'],
        cleanupOutdatedCaches: true,
        // Do not register API calls with Workbox. Leaving them unhandled lets
        // the browser perform normal fetch/CORS handling and avoids Workbox
        // wrapping transient API/CORS failures as uncaught "no-response" errors.
        runtimeCaching: [
          {
            urlPattern: /^https?.*\/locales\//,
            handler: 'StaleWhileRevalidate',
            options: {
              cacheName: 'nexus-locales',
              expiration: { maxEntries: 50, maxAgeSeconds: 86400 },
            },
          },
        ],
        navigateFallback: '/index.html',
        navigateFallbackDenylist: [/^\/api\//, /^\/admin-legacy\//, /^\/health\.php/],
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
