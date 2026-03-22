// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import React from 'react';

// Mock react-router-dom to prevent heavy bundle import
const mockNavigate = vi.fn();
vi.mock('react-router-dom', () => ({
  Navigate: ({ to }: { to: string }) => <div data-testid="navigate" data-to={to} />,
  useNavigate: () => mockNavigate,
  useLocation: () => ({ pathname: '/dashboard', search: '', hash: '', state: null, key: 'default' }),
  Outlet: () => <div data-testid="outlet" />,
}));

vi.mock('framer-motion', () => ({
  motion: new Proxy({}, {
    get: (_target: Record<string, unknown>, tag: string) => {
      return React.forwardRef(({ children, ...rest }: Record<string, unknown>, ref: React.Ref<HTMLElement>) =>
        React.createElement(typeof tag === 'string' ? tag : 'div', { ...rest, ref }, children as React.ReactNode)
      );
    },
  }),
  AnimatePresence: ({ children }: { children: React.ReactNode }) => children,
}));

// Mock contexts
const mockUseAuth = vi.fn();
const mockUseTenant = vi.fn();
vi.mock('@/contexts', () => ({
  useAuth: (...args: unknown[]) => mockUseAuth(...args),
  useTenant: (...args: unknown[]) => mockUseTenant(...args),

  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }),
}));

vi.mock('@/components/feedback', () => ({
  LoadingScreen: ({ message }: { message?: string }) => <div data-testid="loading">{message}</div>,
}));

// Mock heavy dependencies that ProtectedRoute might import transitively
// api.get must return a Promise because useLegalGate calls .then() on the result
vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(() => Promise.resolve({ success: true, data: { has_pending: false, documents: [] } })),
    post: vi.fn(() => Promise.resolve({ success: true, data: {} })),
    put: vi.fn(() => Promise.resolve({ success: true, data: {} })),
    delete: vi.fn(() => Promise.resolve({ success: true, data: {} })),
  },
  tokenManager: { getAccessToken: vi.fn(), getTenantId: vi.fn() },
}));

import { ProtectedRoute } from './ProtectedRoute';

describe('ProtectedRoute', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockUseAuth.mockReturnValue({
      isAuthenticated: true,
      isLoading: false,
      status: 'authenticated',
      user: { id: 1, first_name: 'Test', last_name: 'User', onboarding_completed: true },
    });
    mockUseTenant.mockReturnValue({
      tenantPath: (p: string) => `/test${p}`,
    });
  });

  it('renders children when authenticated', () => {
    render(
      <ProtectedRoute>
        <div>Protected Content</div>
      </ProtectedRoute>
    );
    expect(screen.getByText('Protected Content')).toBeInTheDocument();
  });

  it('shows loading screen when auth is loading', () => {
    mockUseAuth.mockReturnValue({
      isAuthenticated: false,
      isLoading: true,
      status: 'loading',
      user: null,
    });

    render(
      <ProtectedRoute>
        <div>Protected Content</div>
      </ProtectedRoute>
    );
    expect(screen.getByTestId('loading')).toBeInTheDocument();
  });

  it('redirects to login when not authenticated', () => {
    mockUseAuth.mockReturnValue({
      isAuthenticated: false,
      isLoading: false,
      status: 'unauthenticated',
      user: null,
    });

    render(
      <ProtectedRoute>
        <div>Protected Content</div>
      </ProtectedRoute>
    );
    expect(screen.queryByText('Protected Content')).not.toBeInTheDocument();
  });
});
