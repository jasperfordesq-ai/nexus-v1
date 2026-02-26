// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for CookieConsentContext
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, act } from '@testing-library/react';
import { CookieConsentProvider, useCookieConsent, readStoredConsent } from './CookieConsentContext';

// Mock api module
vi.mock('@/lib/api', () => ({
  api: {
    post: vi.fn().mockResolvedValue({ success: true }),
    get: vi.fn().mockResolvedValue({ success: false, consent: null }),
  },
  tokenManager: {
    getAccessToken: vi.fn().mockReturnValue(null),
  },
}));

const STORAGE_KEY = 'nexus_cookie_consent';

// Test component that exposes context values
function TestConsumer() {
  const { consent, showBanner, hasConsent, acceptAll, acceptEssentialOnly, savePreferences, resetConsent } =
    useCookieConsent();

  return (
    <div>
      <div data-testid="show-banner">{String(showBanner)}</div>
      <div data-testid="consent">{consent ? JSON.stringify(consent) : 'null'}</div>
      <div data-testid="has-analytics">{String(hasConsent('analytics'))}</div>
      <div data-testid="has-preferences">{String(hasConsent('preferences'))}</div>
      <button onClick={acceptAll}>Accept All</button>
      <button onClick={acceptEssentialOnly}>Essential Only</button>
      <button onClick={() => savePreferences(true, false)}>Custom Save</button>
      <button onClick={resetConsent}>Reset</button>
    </div>
  );
}

describe('CookieConsentContext', () => {
  beforeEach(() => {
    localStorage.clear();
    vi.useFakeTimers({ shouldAdvanceTime: true });
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('shows banner when no consent stored', () => {
    render(
      <CookieConsentProvider>
        <TestConsumer />
      </CookieConsentProvider>
    );

    expect(screen.getByTestId('show-banner')).toHaveTextContent('true');
    expect(screen.getByTestId('consent')).toHaveTextContent('null');
  });

  it('hides banner after accepting all', () => {
    render(
      <CookieConsentProvider>
        <TestConsumer />
      </CookieConsentProvider>
    );

    act(() => {
      screen.getByRole('button', { name: 'Accept All' }).click();
    });

    expect(screen.getByTestId('show-banner')).toHaveTextContent('false');
    expect(screen.getByTestId('has-analytics')).toHaveTextContent('true');
    expect(screen.getByTestId('has-preferences')).toHaveTextContent('true');
  });

  it('sets analytics=false and preferences=false on essential only', () => {
    render(
      <CookieConsentProvider>
        <TestConsumer />
      </CookieConsentProvider>
    );

    act(() => {
      screen.getByRole('button', { name: 'Essential Only' }).click();
    });

    expect(screen.getByTestId('show-banner')).toHaveTextContent('false');
    expect(screen.getByTestId('has-analytics')).toHaveTextContent('false');
    expect(screen.getByTestId('has-preferences')).toHaveTextContent('false');
  });

  it('saves custom preferences', () => {
    render(
      <CookieConsentProvider>
        <TestConsumer />
      </CookieConsentProvider>
    );

    act(() => {
      screen.getByRole('button', { name: 'Custom Save' }).click();
    });

    expect(screen.getByTestId('has-analytics')).toHaveTextContent('true');
    expect(screen.getByTestId('has-preferences')).toHaveTextContent('false');
  });

  it('persists consent to localStorage', () => {
    render(
      <CookieConsentProvider>
        <TestConsumer />
      </CookieConsentProvider>
    );

    act(() => {
      screen.getByRole('button', { name: 'Accept All' }).click();
    });

    const stored = JSON.parse(localStorage.getItem(STORAGE_KEY) || 'null');
    expect(stored).toBeTruthy();
    expect(stored.essential).toBe(true);
    expect(stored.analytics).toBe(true);
    expect(stored.preferences).toBe(true);
    expect(stored.timestamp).toBeTruthy();
  });

  it('reads consent from localStorage on mount', () => {
    const consent = {
      essential: true,
      analytics: true,
      preferences: false,
      timestamp: new Date().toISOString(),
    };
    localStorage.setItem(STORAGE_KEY, JSON.stringify(consent));

    render(
      <CookieConsentProvider>
        <TestConsumer />
      </CookieConsentProvider>
    );

    expect(screen.getByTestId('show-banner')).toHaveTextContent('false');
    expect(screen.getByTestId('has-analytics')).toHaveTextContent('true');
    expect(screen.getByTestId('has-preferences')).toHaveTextContent('false');
  });

  it('resets consent and shows banner again', () => {
    const consent = {
      essential: true,
      analytics: true,
      preferences: true,
      timestamp: new Date().toISOString(),
    };
    localStorage.setItem(STORAGE_KEY, JSON.stringify(consent));

    render(
      <CookieConsentProvider>
        <TestConsumer />
      </CookieConsentProvider>
    );

    expect(screen.getByTestId('show-banner')).toHaveTextContent('false');

    act(() => {
      screen.getByRole('button', { name: 'Reset' }).click();
    });

    expect(screen.getByTestId('show-banner')).toHaveTextContent('true');
    expect(localStorage.getItem(STORAGE_KEY)).toBeNull();
  });

  it('throws when useCookieConsent is used outside provider', () => {
    const spy = vi.spyOn(console, 'error').mockImplementation(() => {});

    expect(() => {
      render(<TestConsumer />);
    }).toThrow('useCookieConsent must be used within CookieConsentProvider');

    spy.mockRestore();
  });
});

describe('readStoredConsent', () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it('returns null when nothing stored', () => {
    expect(readStoredConsent()).toBeNull();
  });

  it('returns consent when valid data is stored', () => {
    const consent = {
      essential: true,
      analytics: false,
      preferences: true,
      timestamp: new Date().toISOString(),
    };
    localStorage.setItem(STORAGE_KEY, JSON.stringify(consent));

    const result = readStoredConsent();
    expect(result).toBeTruthy();
    expect(result?.analytics).toBe(false);
    expect(result?.preferences).toBe(true);
  });

  it('returns null for expired consent (older than 6 months)', () => {
    const oldDate = new Date();
    oldDate.setMonth(oldDate.getMonth() - 7); // 7 months ago
    const consent = {
      essential: true,
      analytics: true,
      preferences: true,
      timestamp: oldDate.toISOString(),
    };
    localStorage.setItem(STORAGE_KEY, JSON.stringify(consent));

    expect(readStoredConsent()).toBeNull();
    // Should also remove from localStorage
    expect(localStorage.getItem(STORAGE_KEY)).toBeNull();
  });

  it('returns null for malformed data', () => {
    localStorage.setItem(STORAGE_KEY, 'not-json');
    expect(readStoredConsent()).toBeNull();
  });

  it('returns null for data missing required fields', () => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({ foo: 'bar' }));
    expect(readStoredConsent()).toBeNull();
  });
});
