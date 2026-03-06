import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import { VitePWA } from 'vite-plugin-pwa'
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

export default defineConfig({
  plugins: [
    react(),
    tailwindcss(),
    VitePWA({
      registerType: 'prompt',
      // Don't inject SW registration into index.html automatically —
      // we handle it in main.tsx so it only fires in production builds
      injectRegister: null,
      manifest: false, // We use our own public/manifest.json
      workbox: {
        globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2}'],
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
  server: {
    port: 5173,
    host: '0.0.0.0', // Required for Docker
    watch: {
      usePolling: true, // Required for Docker on Windows (bind mounts don't trigger inotify)
      interval: 1000,
    },
    proxy: {
      // Proxy API requests to PHP backend
      // Uses Docker service name 'app' when running in Docker, localhost:8090 otherwise
      '/api': {
        target: process.env.VITE_API_URL || 'http://localhost:8090',
        changeOrigin: true,
        secure: false,
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
      // Proxy health check
      '/health.php': {
        target: process.env.VITE_API_URL || 'http://localhost:8090',
        changeOrigin: true,
      },
    },
  },
  build: { outDir: 'dist' },
})
