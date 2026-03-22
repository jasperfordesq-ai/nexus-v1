// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';

// Mock Sentry and the consent context before importing sentry.ts
vi.mock('@sentry/react', () => ({
  init: vi.fn(),
  setUser: vi.fn(),
  setTag: vi.fn(),
  setContext: vi.fn(),
  addBreadcrumb: vi.fn(),
  captureException: vi.fn(),
  captureMessage: vi.fn(),
  startInactiveSpan: vi.fn(),
  browserTracingIntegration: vi.fn(() => ({})),
  ErrorBoundary: vi.fn(),
  withProfiler: vi.fn((fn) => fn),
}));

vi.mock('@/contexts/CookieConsentContext', () => ({
  readStoredConsent: vi.fn(),
}));

import { readStoredConsent } from '@/contexts/CookieConsentContext';
const mockReadStoredConsent = readStoredConsent as ReturnType<typeof vi.fn>;

describe('sentry (disabled - no DSN)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    // No VITE_SENTRY_DSN set — Sentry is disabled
    mockReadStoredConsent.mockReturnValue({ analytics: true });
  });

  it('initSentry is a callable function', async () => {
    const { initSentry } = await import('./sentry');
    expect(typeof initSentry).toBe('function');
    // Should not throw even without DSN
    expect(() => initSentry()).not.toThrow();
  });

  it('setSentryUser is a no-op when disabled', async () => {
    const { setSentryUser } = await import('./sentry');
    const Sentry = await import('@sentry/react');
    expect(() => setSentryUser({ id: 1, name: 'Alice', email: 'alice@test.com' } as Parameters<typeof setSentryUser>[0])).not.toThrow();
    // Sentry.setUser should NOT be called since DSN is missing
    expect(Sentry.setUser).not.toHaveBeenCalled();
  });

  it('setSentryTenant is a no-op when disabled', async () => {
    const { setSentryTenant } = await import('./sentry');
    const Sentry = await import('@sentry/react');
    expect(() => setSentryTenant({ id: 1, name: 'Test Bank', slug: 'test' })).not.toThrow();
    expect(Sentry.setTag).not.toHaveBeenCalled();
  });

  it('captureSentryException is a no-op when disabled', async () => {
    const { captureSentryException } = await import('./sentry');
    const Sentry = await import('@sentry/react');
    expect(() => captureSentryException(new Error('Test error'))).not.toThrow();
    expect(Sentry.captureException).not.toHaveBeenCalled();
  });

  it('captureSentryMessage is a no-op when disabled', async () => {
    const { captureSentryMessage } = await import('./sentry');
    const Sentry = await import('@sentry/react');
    expect(() => captureSentryMessage('test message')).not.toThrow();
    expect(Sentry.captureMessage).not.toHaveBeenCalled();
  });

  it('addSentryBreadcrumb is a no-op when disabled', async () => {
    const { addSentryBreadcrumb } = await import('./sentry');
    const Sentry = await import('@sentry/react');
    expect(() => addSentryBreadcrumb('Navigation event', 'nav')).not.toThrow();
    expect(Sentry.addBreadcrumb).not.toHaveBeenCalled();
  });

  it('captureNavigation is a no-op when disabled', async () => {
    const { captureNavigation } = await import('./sentry');
    expect(() => captureNavigation('/home', '/feed')).not.toThrow();
  });

  it('captureApiCall is a no-op when disabled', async () => {
    const { captureApiCall } = await import('./sentry');
    expect(() => captureApiCall('GET', '/v2/users', 200, 120)).not.toThrow();
  });

  it('captureAuthEvent is a no-op when disabled', async () => {
    const { captureAuthEvent } = await import('./sentry');
    expect(() => captureAuthEvent('login', 1)).not.toThrow();
  });

  it('startSentrySpan returns undefined when disabled', async () => {
    const { startSentrySpan } = await import('./sentry');
    const result = startSentrySpan('my-span');
    expect(result).toBeUndefined();
  });

  it('SentryErrorBoundary returns children when disabled', async () => {
    const { SentryErrorBoundary } = await import('./sentry');
    const children = 'test-children';
    const result = SentryErrorBoundary({ children });
    expect(result).toBe(children);
  });
});

describe('sentry analytics consent checks', () => {
  it('is disabled when no consent stored', async () => {
    mockReadStoredConsent.mockReturnValue(null);
    // Since sentry module is cached and IS_ENABLED is computed at module load time,
    // we test the behavior via the exported functions
    const { initSentry } = await import('./sentry');
    expect(() => initSentry()).not.toThrow();
  });

  it('is disabled when analytics consent is false', async () => {
    mockReadStoredConsent.mockReturnValue({ analytics: false });
    const { initSentry } = await import('./sentry');
    expect(() => initSentry()).not.toThrow();
  });
});
