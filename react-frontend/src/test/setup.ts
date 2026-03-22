// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Vitest Test Setup
 * Configures testing environment with jest-dom matchers
 */

/// <reference types="vitest" />
/// <reference types="@testing-library/jest-dom" />

import '@testing-library/jest-dom';
import * as axeMatchers from 'vitest-axe/matchers';
import { expect, vi, beforeAll, afterAll, afterEach } from 'vitest';
import { cleanup } from '@testing-library/react';
expect.extend(axeMatchers);

// Ensure DOM is cleaned up after every test so renders from one test never
// bleed into the next — critical for singleFork mode where jsdom is shared.
afterEach(() => {
  cleanup();
});

// Global mock for @/components/seo — PageMeta calls useTenant() for branding which
// is never present in test mocks. Make it a no-op for all tests globally.
vi.mock('@/components/seo', () => ({
  PageMeta: () => null,
  default: () => null,
}));

// Suppress noisy console output that pollutes test logs and slows I/O
const originalError = console.error.bind(console);
const originalWarn = console.warn.bind(console);
const originalLog = console.log.bind(console);

beforeAll(() => {
  console.error = (...args: unknown[]) => {
    const msg = String(args[0] ?? '');
    // Suppress act() warnings, React Router future flags, i18next backend noise
    if (
      msg.includes('not wrapped in act') ||
      msg.includes('React Router Future Flag') ||
      msg.includes('Warning: An update to') ||
      msg.includes('Maximum update depth')
    ) return;
    originalError(...args);
  };
  console.warn = (...args: unknown[]) => {
    const msg = String(args[0] ?? '');
    if (
      msg.includes('React Router Future Flag') ||
      msg.includes('i18next') ||
      msg.includes('i18next:')
    ) return;
    originalWarn(...args);
  };
  console.log = (...args: unknown[]) => {
    const msg = String(args[0] ?? '');
    // Suppress i18next debug/info logging (from @/i18n.ts debug:true in DEV mode)
    if (
      msg.includes('i18next') ||
      msg.includes('i18next:') ||
      msg.includes('locize') ||
      msg.includes('Locize') ||
      msg.includes('🌐')
    ) return;
    originalLog(...args);
  };
});

afterAll(() => {
  console.error = originalError;
  console.warn = originalWarn;
  console.log = originalLog;
  // Clean up any lingering DOM nodes between test files in singleFork mode.
  // React Aria / HeroUI can leave portal elements attached to document.body
  // that persist across files unless explicitly cleared here.
  if (typeof document !== 'undefined') {
    document.body.innerHTML = '';
  }
  // Force GC after each test file to prevent heap accumulation across files.
  // Workers are long-lived (handle many files); --expose-gc in vitest.config enables this.
  if (typeof (globalThis as Record<string, unknown>).gc === 'function') {
    (globalThis as Record<string, unknown>).gc();
  }
});

// Initialize i18next with the committed English translation files so components
// render real English text in tests (same as users see in production).
import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';
import fs from 'fs';
import path from 'path';

const localesDir = path.resolve(__dirname, '../../public/locales/en');
const resources: Record<string, Record<string, unknown>> = {};
if (fs.existsSync(localesDir)) {
  for (const file of fs.readdirSync(localesDir)) {
    if (file.endsWith('.json')) {
      const ns = file.replace('.json', '');
      resources[ns] = JSON.parse(fs.readFileSync(path.join(localesDir, file), 'utf-8'));
    }
  }
}

if (!i18n.isInitialized) {
  i18n.use(initReactI18next).init({
    lng: 'en',
    fallbackLng: 'en',
    defaultNS: 'common',
    resources: { en: resources },
    interpolation: { escapeValue: false },
    initImmediate: false, // synchronous init
  });
}

// Mock window.matchMedia for components that use media queries
Object.defineProperty(window, 'matchMedia', {
  writable: true,
  value: (query: string) => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: () => {},
    removeListener: () => {},
    addEventListener: () => {},
    removeEventListener: () => {},
    dispatchEvent: () => false,
  }),
});

// Mock IntersectionObserver
class MockIntersectionObserver {
  observe = () => {};
  unobserve = () => {};
  disconnect = () => {};
}
Object.defineProperty(window, 'IntersectionObserver', {
  writable: true,
  configurable: true,
  value: MockIntersectionObserver,
});

// Mock ResizeObserver
class MockResizeObserver {
  observe = () => {};
  unobserve = () => {};
  disconnect = () => {};
}
Object.defineProperty(window, 'ResizeObserver', {
  writable: true,
  value: MockResizeObserver,
});

// Mock scrollTo
Object.defineProperty(window, 'scrollTo', {
  writable: true,
  value: () => {},
});

// Mock URL.createObjectURL / revokeObjectURL (not available in jsdom)
if (!URL.createObjectURL) {
  URL.createObjectURL = () => 'blob:mock-url';
}
if (!URL.revokeObjectURL) {
  URL.revokeObjectURL = () => {};
}
