import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss()],
  server: {
    port: 5173,
    // Proxy for local development (alternative to CORS)
    // Uncomment if you want to use proxy instead of CORS
    // proxy: {
    //   '/api': {
    //     target: 'http://staging.timebank.local',
    //     changeOrigin: true,
    //   },
    // },
  },
  build: {
    outDir: 'dist',
    sourcemap: false,
  },
})
