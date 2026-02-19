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
    get: (_: any, tag: string) => {
      return React.forwardRef(({ children, initial, animate, exit, transition, variants, ...rest }: any, ref: any) =>
        React.createElement(typeof tag === 'string' ? tag : 'div', { ...rest, ref }, children)
      );
    },
  }),
  AnimatePresence: ({ children }: any) => children,
}));

// Mock contexts
const mockUseAuth = vi.fn();
const mockUseTenant = vi.fn();
vi.mock('@/contexts', () => ({
  useAuth: (...args: any[]) => mockUseAuth(...args),
  useTenant: (...args: any[]) => mockUseTenant(...args),
}));

vi.mock('@/components/feedback', () => ({
  LoadingScreen: ({ message }: any) => <div data-testid="loading">{message}</div>,
}));

// Mock heavy dependencies that ProtectedRoute might import transitively
vi.mock('@/lib/api', () => ({
  api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
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
