import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import path from 'path'

export default defineConfig({
  plugins: [react(), tailwindcss()],
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
