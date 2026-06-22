// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Barrel smoke-test: every named re-export from src/lib/index.ts must be defined.
 *
 * This verifies that the barrel does not silently drop exports due to
 * circular-import changes or accidental deletions.
 */

import { describe, it, expect, vi } from 'vitest';

// ── Provide minimal stubs for heavy transitive deps ──────────────────────────
// api.ts pulls in fetch, logger, sentry, safeStorage, api-validation, etc.
// helpers.ts pulls in i18n, logger, safeStorage.
// We stub only the modules that would error during import in a jsdom env.

vi.mock('@/lib/sentry', () => ({
  captureApiCall: vi.fn(),
  addSentryBreadcrumb: vi.fn(),
  captureSentryMessage: vi.fn(),
  captureSentryException: vi.fn(),
}));

vi.mock('@/lib/api-validation', () => ({
  validateResponse: vi.fn(),
}));

vi.mock('@/lib/api-schemas', () => ({
  apiResponseSchema: { safeParse: vi.fn(() => ({ success: true, data: {} })) },
}));

vi.mock('@/lib/supportDiagnostics', () => ({
  recordApiDiagnostic: vi.fn(),
}));

vi.mock('@/lib/safeStorage', () => ({
  safeLocalStorageSet: vi.fn(),
  safeLocalStorageGet: vi.fn(() => null),
  safeLocalStorageRemove: vi.fn(),
  safeLocalStorageGetJSON: vi.fn(() => null),
  safeLocalStorageSetJSON: vi.fn(() => true),
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
  logWarn: vi.fn(),
  logInfo: vi.fn(),
}));

vi.mock('../i18n', () => ({
  default: {
    language: 'en',
    t: vi.fn((k: string) => k),
  },
}));

// ── Import the barrel under test (after mocks are registered) ────────────────
import * as barrel from './index';

describe('src/lib/index barrel exports', () => {
  // ── Exports re-exported from ./api ──────────────────────────────────────────
  it('exports `api` object', () => {
    expect(barrel.api).toBeDefined();
  });

  it('exports `tokenManager` object', () => {
    expect(barrel.tokenManager).toBeDefined();
  });

  it('exports `checkBackendHealth` function', () => {
    expect(typeof barrel.checkBackendHealth).toBe('function');
  });

  it('exports `SESSION_EXPIRED_EVENT` constant', () => {
    expect(typeof barrel.SESSION_EXPIRED_EVENT).toBe('string');
    expect(barrel.SESSION_EXPIRED_EVENT.length).toBeGreaterThan(0);
  });

  // ── Exports re-exported from ./helpers (via export *) ──────────────────────
  it('exports `resolveAssetUrl` function', () => {
    expect(typeof barrel.resolveAssetUrl).toBe('function');
  });

  it('exports `resolveAvatarUrl` function', () => {
    expect(typeof barrel.resolveAvatarUrl).toBe('function');
  });

  it('exports `getUserDisplayName` function', () => {
    expect(typeof barrel.getUserDisplayName).toBe('function');
  });

  it('exports `getUserInitials` function', () => {
    expect(typeof barrel.getUserInitials).toBe('function');
  });

  it('exports `formatRelativeTime` function', () => {
    expect(typeof barrel.formatRelativeTime).toBe('function');
  });

  it('exports `formatDateValue` function', () => {
    expect(typeof barrel.formatDateValue).toBe('function');
  });

  it('exports `formatDate` function', () => {
    expect(typeof barrel.formatDate).toBe('function');
  });

  it('exports `formatDateTime` function', () => {
    expect(typeof barrel.formatDateTime).toBe('function');
  });

  it('exports `formatTime` function', () => {
    expect(typeof barrel.formatTime).toBe('function');
  });

  it('exports `formatNumber` function', () => {
    expect(typeof barrel.formatNumber).toBe('function');
  });

  it('exports `formatCurrency` function', () => {
    expect(typeof barrel.formatCurrency).toBe('function');
  });

  it('exports `formatMonthShort` function', () => {
    expect(typeof barrel.formatMonthShort).toBe('function');
  });

  it('exports `formatDayOfMonth` function', () => {
    expect(typeof barrel.formatDayOfMonth).toBe('function');
  });

  it('exports `truncate` function', () => {
    expect(typeof barrel.truncate).toBe('function');
  });

  it('exports `formatHours` function', () => {
    expect(typeof barrel.formatHours).toBe('function');
  });

  it('exports `debounce` function', () => {
    expect(typeof barrel.debounce).toBe('function');
  });

  it('exports `cn` function', () => {
    expect(typeof barrel.cn).toBe('function');
  });

  it('exports `storage` object with get/set/remove', () => {
    expect(barrel.storage).toBeDefined();
    expect(typeof barrel.storage.get).toBe('function');
    expect(typeof barrel.storage.set).toBe('function');
    expect(typeof barrel.storage.remove).toBe('function');
  });
});
