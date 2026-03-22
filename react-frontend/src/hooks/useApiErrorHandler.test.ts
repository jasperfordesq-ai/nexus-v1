// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for useApiErrorHandler hook
 */

import { describe, it, expect, vi, afterEach } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { useApiErrorHandler } from './useApiErrorHandler';
import { API_ERROR_EVENT } from '@/lib/api';

// Mock ToastContext
const mockError = vi.fn();
vi.mock('@/contexts', () => ({
  useToast: () => ({
    error: mockError,
    success: vi.fn(),
    warning: vi.fn(),
    info: vi.fn(),
    toasts: [],
    addToast: vi.fn(),
    removeToast: vi.fn(),
  }),

  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  useAuth: () => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null }),
  useTenant: () => ({ tenant: { id: 2, name: 'Test', slug: 'test', tagline: null }, branding: { name: 'Test', logo_url: null }, tenantSlug: 'test', tenantPath: (p) => '/test' + p, isLoading: false, hasFeature: vi.fn(() => true), hasModule: vi.fn(() => true) }),
}));

describe('useApiErrorHandler', () => {
  afterEach(() => {
    vi.clearAllMocks();
  });

  it('shows toast for network error', () => {
    renderHook(() => useApiErrorHandler());

    act(() => {
      window.dispatchEvent(
        new CustomEvent(API_ERROR_EVENT, {
          detail: { message: 'Network error', code: 'NETWORK_ERROR' },
        })
      );
    });

    expect(mockError).toHaveBeenCalledWith(
      'Request Failed',
      'Unable to connect to the server. Please check your internet connection.'
    );
  });

  it('shows toast for HTTP 404', () => {
    renderHook(() => useApiErrorHandler());

    act(() => {
      window.dispatchEvent(
        new CustomEvent(API_ERROR_EVENT, {
          detail: { message: 'Not found', code: 'HTTP_404' },
        })
      );
    });

    expect(mockError).toHaveBeenCalledWith(
      'Request Failed',
      'The requested resource was not found.'
    );
  });

  it('shows toast for HTTP 500', () => {
    renderHook(() => useApiErrorHandler());

    act(() => {
      window.dispatchEvent(
        new CustomEvent(API_ERROR_EVENT, {
          detail: { message: 'Server error', code: 'HTTP_500' },
        })
      );
    });

    expect(mockError).toHaveBeenCalledWith(
      'Request Failed',
      'An unexpected server error occurred. Please try again later.'
    );
  });

  it('shows toast for rate limiting (HTTP 429)', () => {
    renderHook(() => useApiErrorHandler());

    act(() => {
      window.dispatchEvent(
        new CustomEvent(API_ERROR_EVENT, {
          detail: { message: 'Rate limited', code: 'HTTP_429' },
        })
      );
    });

    expect(mockError).toHaveBeenCalledWith(
      'Request Failed',
      'Too many requests. Please wait a moment and try again.'
    );
  });

  it('does NOT show toast for SESSION_EXPIRED', () => {
    renderHook(() => useApiErrorHandler());

    act(() => {
      window.dispatchEvent(
        new CustomEvent(API_ERROR_EVENT, {
          detail: { message: 'Session expired', code: 'SESSION_EXPIRED' },
        })
      );
    });

    expect(mockError).not.toHaveBeenCalled();
  });

  it('uses fallback message for unknown error code', () => {
    renderHook(() => useApiErrorHandler());

    act(() => {
      window.dispatchEvent(
        new CustomEvent(API_ERROR_EVENT, {
          detail: { message: 'Something weird happened', code: 'UNKNOWN_CODE' },
        })
      );
    });

    expect(mockError).toHaveBeenCalledWith(
      'Request Failed',
      'Something weird happened'
    );
  });

  it('cleans up event listener on unmount', () => {
    const { unmount } = renderHook(() => useApiErrorHandler());
    unmount();

    act(() => {
      window.dispatchEvent(
        new CustomEvent(API_ERROR_EVENT, {
          detail: { message: 'Test', code: 'HTTP_500' },
        })
      );
    });

    expect(mockError).not.toHaveBeenCalled();
  });
});
