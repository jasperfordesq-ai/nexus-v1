// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for ComposeSubmitContext — provider + useComposeSubmit hook
 */

import { describe, it, expect, vi } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { render, screen } from '@/test/test-utils';
import { ComposeSubmitProvider, useComposeSubmit } from '../ComposeSubmitContext';
import type { ComposeSubmitRegistration } from '../types';

vi.mock('@/contexts', () => ({
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
  useTenant: () => ({ tenant: { id: 2, name: 'Test', slug: 'test', tagline: null }, branding: { name: 'Test', logo_url: null }, tenantSlug: 'test', tenantPath: (p: string) => '/test' + p, isLoading: false, hasFeature: vi.fn(() => true), hasModule: vi.fn(() => true) }),
  useToast: vi.fn(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn() })),
}));

describe('ComposeSubmitContext', () => {
  it('provides null registration by default', () => {
    const { result } = renderHook(() => useComposeSubmit(), {
      wrapper: ({ children }) => (
        <ComposeSubmitProvider>{children}</ComposeSubmitProvider>
      ),
    });

    expect(result.current.registration).toBeNull();
  });

  it('returns no-op stubs when used outside provider', () => {
    const { result } = renderHook(() => useComposeSubmit());

    expect(result.current.registration).toBeNull();
    // Should not throw
    result.current.register({
      canSubmit: true,
      isSubmitting: false,
      onSubmit: vi.fn(),
      buttonLabel: 'Test',
      gradientClass: 'from-a to-b',
    });
    result.current.unregister();
  });

  it('registers and exposes a submit registration', () => {
    const reg: ComposeSubmitRegistration = {
      canSubmit: true,
      isSubmitting: false,
      onSubmit: vi.fn(),
      buttonLabel: 'Post',
      gradientClass: 'from-indigo-500 to-purple-600',
    };

    const { result } = renderHook(() => useComposeSubmit(), {
      wrapper: ({ children }) => (
        <ComposeSubmitProvider>{children}</ComposeSubmitProvider>
      ),
    });

    act(() => {
      result.current.register(reg);
    });

    expect(result.current.registration).toEqual(reg);
  });

  it('unregisters and clears the registration', () => {
    const reg: ComposeSubmitRegistration = {
      canSubmit: true,
      isSubmitting: false,
      onSubmit: vi.fn(),
      buttonLabel: 'Post',
      gradientClass: 'from-indigo-500 to-purple-600',
    };

    const { result } = renderHook(() => useComposeSubmit(), {
      wrapper: ({ children }) => (
        <ComposeSubmitProvider>{children}</ComposeSubmitProvider>
      ),
    });

    act(() => {
      result.current.register(reg);
    });
    expect(result.current.registration).not.toBeNull();

    act(() => {
      result.current.unregister();
    });
    expect(result.current.registration).toBeNull();
  });

  it('renders children inside the provider', () => {
    render(
      <ComposeSubmitProvider>
        <div data-testid="child">Hello</div>
      </ComposeSubmitProvider>,
    );

    expect(screen.getByTestId('child')).toBeInTheDocument();
  });
});
