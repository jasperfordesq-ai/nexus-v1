import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
  plugins: [react()],
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.ts'],
    include: ['src/**/*.{test,spec}.{js,ts,jsx,tsx}'],
    pool: 'forks',
    poolOptions: {
      forks: {
        // Prevent OOM crashes with large test suite (72 files)
        maxForks: 2,
        minForks: 1,
        isolate: true,
      },
    },
    fileParallelism: false,
    testTimeout: 30000, // 30s per test — prevents hanging on CI
    hookTimeout: 30000, // 30s for beforeAll/afterAll hooks
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
        // Minimum coverage thresholds — raise these over time
        // Current baseline: ~40%. Target: 70%+
        statements: 30,
        branches: 25,
        functions: 25,
        lines: 30,
      },
    },
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
});
