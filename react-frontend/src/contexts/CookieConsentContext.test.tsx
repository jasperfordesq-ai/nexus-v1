// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for CookieConsentContext
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, act } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { CookieConsentProvider, useCookieConsent, readStoredConsent } from './CookieConsentContext';
import { api, tokenManager } from '@/lib/api';

const initSentryAfterIdleMock = vi.fn();

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

vi.mock('@/lib/sentry', () => ({
  initSentryAfterIdle: initSentryAfterIdleMock,
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

function renderWithProvider(initialEntry = '/dashboard') {
  return render(
    <MemoryRouter initialEntries={[initialEntry]}>
      <CookieConsentProvider>
        <TestConsumer />
      </CookieConsentProvider>
    </MemoryRouter>
  );
}

describe('CookieConsentContext', () => {
  beforeEach(() => {
    localStorage.clear();
    initSentryAfterIdleMock.mockClear();
    vi.mocked(api.get).mockClear();
    vi.mocked(tokenManager.getAccessToken).mockReturnValue(null);
    vi.useFakeTimers({ shouldAdvanceTime: true });
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('shows banner when no consent stored', () => {
    renderWithProvider();

    expect(screen.getByTestId('show-banner')).toHaveTextContent('true');
    expect(screen.getByTestId('consent')).toHaveTextContent('null');
  });

  it('hides banner after accepting all', () => {
    renderWithProvider();

    act(() => {
      screen.getByRole('button', { name: 'Accept All' }).click();
    });

    expect(screen.getByTestId('show-banner')).toHaveTextContent('false');
    expect(screen.getByTestId('has-analytics')).toHaveTextContent('true');
    expect(screen.getByTestId('has-preferences')).toHaveTextContent('true');
  });

  it('sets analytics=false and preferences=false on essential only', () => {
    renderWithProvider();

    act(() => {
      screen.getByRole('button', { name: 'Essential Only' }).click();
    });

    expect(screen.getByTestId('show-banner')).toHaveTextContent('false');
    expect(screen.getByTestId('has-analytics')).toHaveTextContent('false');
    expect(screen.getByTestId('has-preferences')).toHaveTextContent('false');
  });

  it('saves custom preferences', () => {
    renderWithProvider();

    act(() => {
      screen.getByRole('button', { name: 'Custom Save' }).click();
    });

    expect(screen.getByTestId('has-analytics')).toHaveTextContent('true');
    expect(screen.getByTestId('has-preferences')).toHaveTextContent('false');
  });

  it('persists consent to localStorage', () => {
    renderWithProvider();

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

    renderWithProvider();

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

    renderWithProvider();

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

  it.each([
    '/login',
    '/hour-timebank/login',
    '/hour-timebank/register',
    '/hour-timebank/password/forgot',
    '/hour-timebank/password/reset',
    '/hour-timebank/verify-email',
    '/hour-timebank/verify-identity',
    '/hour-timebank/auth/oauth/callback',
  ])('does not initialize optional analytics on auth entry route %s', async (authPath) => {
    const consent = {
      essential: true,
      analytics: true,
      preferences: true,
      timestamp: new Date().toISOString(),
    };
    localStorage.setItem(STORAGE_KEY, JSON.stringify(consent));

    renderWithProvider(authPath);

    await vi.dynamicImportSettled();
    expect(initSentryAfterIdleMock).not.toHaveBeenCalled();
  });

  it('initializes optional analytics on non-auth routes when consent is stored', async () => {
    const consent = {
      essential: true,
      analytics: true,
      preferences: true,
      timestamp: new Date().toISOString(),
    };
    localStorage.setItem(STORAGE_KEY, JSON.stringify(consent));

    renderWithProvider('/hour-timebank/listings');

    await vi.dynamicImportSettled();
    expect(initSentryAfterIdleMock).not.toHaveBeenCalled();

    await act(async () => {
      await vi.advanceTimersByTimeAsync(52000);
    });

    await vi.dynamicImportSettled();
    expect(initSentryAfterIdleMock).toHaveBeenCalledTimes(1);
  });

  it('defers authenticated server consent restore until after idle', async () => {
    vi.mocked(tokenManager.getAccessToken).mockReturnValue('token');
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: {
        consent: {
          analytics: true,
          functional: false,
          created_at: new Date().toISOString(),
        },
      },
    });

    renderWithProvider('/hour-timebank/dashboard');

    expect(api.get).not.toHaveBeenCalledWith('/cookie-consent');

    await act(async () => {
      await vi.advanceTimersByTimeAsync(6000);
    });

    expect(api.get).toHaveBeenCalledWith('/cookie-consent');
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
