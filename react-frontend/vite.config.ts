import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import { VitePWA } from 'vite-plugin-pwa'
import { ViteImageOptimizer } from 'vite-plugin-image-optimizer'
import path from 'path'
import { execSync } from 'child_process'

// Inject git commit SHA at build time for version verification.
// In Docker production builds, BUILD_COMMIT is passed as a build arg (since
// .dockerignore excludes .git/). Falls back to git for local dev.
const commitHash = process.env.BUILD_COMMIT || (() => {
  try {
    return execSync('git rev-parse --short HEAD').toString().trim()
  } catch {
    return 'dev'
  }
})()

const isDocker = !!process.env.DOCKER_ENV

export default defineConfig(({ command }) => ({
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
    VitePWA({
      registerType: 'prompt',
      // Don't inject SW registration into index.html automatically —
      // we handle it in main.tsx so it only fires in production builds
      injectRegister: null,
      manifest: false, // We use our own public/manifest.json
      workbox: {
        globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2}'],
        cleanupOutdatedCaches: true,
        // Don't cache API calls — always network first
        runtimeCaching: [
          {
            urlPattern: /^https?.*\/api\//,
            handler: 'NetworkOnly',
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
        navigateFallback: '/index.html',
        navigateFallbackDenylist: [/^\/api\//, /^\/admin-legacy\//, /^\/health\.php/],
      },
    }),
    // Image optimizer only runs during build — skip in dev for faster startup
    ...(command === 'build' ? [ViteImageOptimizer({
      png: { quality: 80 },
      jpeg: { quality: 80 },
      jpg: { quality: 80 },
      webp: { lossless: false, quality: 80 },
      svg: { plugins: [{ name: 'preset-default' }] },
      logStats: true,
    })] : []),
  ],
  define: {
    __BUILD_COMMIT__: JSON.stringify(commitHash),
    __BUILD_TIME__: JSON.stringify(new Date().toISOString()),
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  optimizeDeps: {
    // Pre-bundle heavy deps so Vite doesn't re-crawl them on every page load
    include: [
      '@heroui/react',
      'framer-motion',
      'react',
      'react-dom',
      'react-router-dom',
      'recharts',
      'i18next',
      'react-i18next',
      'lucide-react',
    ],
  },
  server: {
    port: 5173,
    host: '0.0.0.0', // Required for Docker
    watch: {
      // Polling is only needed inside Docker on Windows (bind mounts don't trigger inotify).
      // Native Windows dev uses efficient fs events instead.
      usePolling: isDocker,
      interval: 1000,
    },
    proxy: {
      // Proxy API requests to PHP backend
      // Uses Docker service name 'app' when running in Docker, localhost:8090 otherwise
      '/api': {
        target: process.env.VITE_API_URL || 'http://localhost:8090',
        changeOrigin: true,
        secure: false,
        timeout: 120000,
        proxyTimeout: 120000,
        configure: (proxy) => {
          proxy.on('error', (err) => { console.error('[vite-proxy] error:', err.message); });
          proxy.on('proxyReq', (proxyReq, req) => { console.log('[vite-proxy]', req.method, req.url); });
        },
        headers: {
          // Ensure headers are forwarded
          'X-Forwarded-Proto': 'http',
        },
      },
      // Proxy legacy admin panel to PHP backend
      '/admin-legacy': {
        target: process.env.VITE_API_URL || 'http://localhost:8090',
        changeOrigin: true,
      },
      // Proxy uploaded assets (images, media) to PHP backend
      '/uploads': {
        target: process.env.VITE_API_URL || 'http://localhost:8090',
        changeOrigin: true,
      },
      // Proxy health check
      '/health.php': {
        target: process.env.VITE_API_URL || 'http://localhost:8090',
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
}))
