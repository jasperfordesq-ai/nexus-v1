import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
  plugins: [react()],
  define: {
    __BUILD_TIME__: JSON.stringify(new Date().toISOString()),
    __BUILD_COMMIT__: JSON.stringify('test'),
  },
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.ts'],
    globalSetup: ['./src/test/ci-force-exit.ts'],
    include: ['src/**/*.{test,spec}.{js,ts,jsx,tsx}'],
    pool: 'forks',
    poolOptions: {
      forks: {
        maxForks: 2,
        minForks: 1,
        isolate: true,
        singleFork: false,
        // Each test file gets a fresh fork — prevents memory accumulation
        // that causes hangs after ~60 files in singleFork mode
        execArgv: ['--max-old-space-size=4096'],
      },
    },
    fileParallelism: false,
    testTimeout: 30000,  // 30s per test
    hookTimeout: 30000,
    teardownTimeout: 10000,
    coverage: {
      provider: 'v8',
      reporter: ['text', 'json', 'html'],
      include: ['src/**/*.{ts,tsx}'],
      exclude: [
        'src/test/**',
        'src/**/*.d.ts',
        'src/main.tsx',
        'src/vite-env.d.ts',
      ],
      thresholds: {
        // Raised after adding ~99 new test files (2026-03-22)
        // Previous baseline: ~40%. New estimated coverage: ~60%+. Target: 80%+
        statements: 55,
        branches: 50,
        functions: 50,
        lines: 55,
      },
    },
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
});
