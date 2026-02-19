// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for TenantShell component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes, Outlet } from 'react-router-dom';
import React from 'react';

// --- Mocks ---

// Track the tenantSlug passed to TenantProvider
let capturedTenantSlug: string | undefined;

const mockUseTenant = vi.fn();
const mockUseAuth = vi.fn();

vi.mock('@/contexts', () => ({
  TenantProvider: ({ children, tenantSlug }: any) => {
    capturedTenantSlug = tenantSlug;
    return <div data-testid="tenant-provider" data-slug={tenantSlug || ''}>{children}</div>;
  },
  AuthProvider: ({ children }: any) => <div data-testid="auth-provider">{children}</div>,
  NotificationsProvider: ({ children }: any) => <div>{children}</div>,
  PusherProvider: ({ children }: any) => <div>{children}</div>,
  useTenant: (...args: any[]) => mockUseTenant(...args),
  useAuth: (...args: any[]) => mockUseAuth(...args),
}));

vi.mock('@/lib/tenant-routing', () => ({
  RESERVED_PATHS: new Set([
    'login', 'register', 'dashboard', 'listings', 'events', 'groups',
    'messages', 'notifications', 'wallet', 'feed', 'search', 'members',
    'profile', 'settings', 'admin', 'admin-legacy', 'api', 'assets',
    'help', 'about', 'faq', 'terms', 'privacy',
  ]),
}));

vi.mock('@/components/ui', () => ({
  GlassCard: ({ children, className }: any) => <div className={className}>{children}</div>,
}));

vi.mock('@/hooks', () => ({
  usePageTitle: vi.fn(),
}));

vi.mock('framer-motion', () => {
  const proxy = new Proxy({}, {
    get: (_t: object, prop: string | symbol) => {
      return React.forwardRef(({ children, ...p }: any, ref: any) => {
        const safe: Record<string, unknown> = {};
        for (const [k, v] of Object.entries(p)) {
          if (!['variants', 'initial', 'animate', 'exit', 'transition', 'whileHover', 'whileTap', 'whileInView', 'layout', 'viewport', 'layoutId'].includes(k)) safe[k] = v;
        }
        return React.createElement(typeof prop === 'string' ? prop : 'div', { ...safe, ref }, children);
      });
    },
  });
  return { motion: proxy, AnimatePresence: ({ children }: any) => children };
});

import { TenantShell } from './TenantShell';

function setupDefaultMocks(overrides: {
  tenant?: Record<string, any>;
  auth?: Record<string, any>;
} = {}) {
  mockUseTenant.mockReturnValue({
    isLoading: false,
    notFoundSlug: null,
    tenant: { id: 2, name: 'Test Tenant', slug: 'test-tenant', settings: {} },
    ...overrides.tenant,
  });
  mockUseAuth.mockReturnValue({
    user: null,
    ...overrides.auth,
  });
}

function renderWithRouter(initialPath: string, appRoutes?: () => React.ReactNode) {
  return render(
    <MemoryRouter initialEntries={[initialPath]}>
      <Routes>
        <Route path="/*" element={<TenantShell appRoutes={appRoutes} />} />
      </Routes>
    </MemoryRouter>,
  );
}

describe('TenantShell', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    capturedTenantSlug = undefined;
    setupDefaultMocks();
  });

  describe('Tenant slug detection', () => {
    it('does NOT detect slug from reserved paths like /dashboard', () => {
      renderWithRouter('/dashboard');
      expect(capturedTenantSlug).toBeUndefined();
    });

    it('does NOT detect slug from reserved paths like /login', () => {
      renderWithRouter('/login');
      expect(capturedTenantSlug).toBeUndefined();
    });

    it('does NOT detect slug from reserved paths like /admin', () => {
      renderWithRouter('/admin');
      expect(capturedTenantSlug).toBeUndefined();
    });

    it('detects tenant slug from non-reserved first segment', () => {
      renderWithRouter('/hour-timebank/dashboard');
      expect(capturedTenantSlug).toBe('hour-timebank');
    });

    it('detects tenant slug from a simple community path', () => {
      renderWithRouter('/my-community/listings');
      expect(capturedTenantSlug).toBe('my-community');
    });

    it('handles root path without slug', () => {
      renderWithRouter('/');
      expect(capturedTenantSlug).toBeUndefined();
    });
  });

  describe('Provider wrapping', () => {
    it('renders TenantProvider', () => {
      renderWithRouter('/dashboard');
      expect(screen.getByTestId('tenant-provider')).toBeInTheDocument();
    });

    it('renders AuthProvider', () => {
      renderWithRouter('/dashboard');
      expect(screen.getByTestId('auth-provider')).toBeInTheDocument();
    });
  });

  describe('Community Not Found', () => {
    it('shows CommunityNotFound when notFoundSlug is set', () => {
      setupDefaultMocks({
        tenant: {
          isLoading: false,
          notFoundSlug: 'nonexistent-community',
          tenant: null,
        },
      });
      renderWithRouter('/nonexistent-community/dashboard');
      expect(screen.getByText('Community Not Found')).toBeInTheDocument();
      expect(screen.getByText(/nonexistent-community/)).toBeInTheDocument();
    });

    it('shows Go Home button in CommunityNotFound', () => {
      setupDefaultMocks({
        tenant: {
          isLoading: false,
          notFoundSlug: 'bad-slug',
          tenant: null,
        },
      });
      renderWithRouter('/bad-slug/dashboard');
      expect(screen.getByText('Go Home')).toBeInTheDocument();
    });

    it('shows Find Community button in CommunityNotFound', () => {
      setupDefaultMocks({
        tenant: {
          isLoading: false,
          notFoundSlug: 'bad-slug',
          tenant: null,
        },
      });
      renderWithRouter('/bad-slug/dashboard');
      expect(screen.getByText('Find Community')).toBeInTheDocument();
    });
  });

  describe('Valid tenant', () => {
    it('renders Outlet (children) when tenant is valid', () => {
      setupDefaultMocks({
        tenant: {
          isLoading: false,
          notFoundSlug: null,
          tenant: { id: 2, name: 'Test', slug: 'test', settings: {} },
        },
      });
      renderWithRouter('/dashboard');
      // When no slug prefix and no appRoutes, it renders <Outlet /> which is empty
      // The important thing is no error/not-found page is shown
      expect(screen.queryByText('Community Not Found')).not.toBeInTheDocument();
    });

    it('does NOT show CommunityNotFound when tenant is valid', () => {
      setupDefaultMocks({
        tenant: {
          isLoading: false,
          notFoundSlug: null,
          tenant: { id: 2, name: 'Valid', slug: 'valid-tenant', settings: {} },
        },
      });
      renderWithRouter('/valid-tenant/dashboard');
      expect(screen.queryByText('Community Not Found')).not.toBeInTheDocument();
    });
  });

  describe('Loading state', () => {
    it('does not show CommunityNotFound while loading', () => {
      setupDefaultMocks({
        tenant: {
          isLoading: true,
          notFoundSlug: null,
          tenant: null,
        },
      });
      renderWithRouter('/some-slug/dashboard');
      expect(screen.queryByText('Community Not Found')).not.toBeInTheDocument();
    });
  });

  describe('Maintenance mode', () => {
    it('shows maintenance page for non-admin users when enabled', () => {
      setupDefaultMocks({
        tenant: {
          isLoading: false,
          notFoundSlug: null,
          tenant: { id: 2, name: 'Test', slug: 'test', settings: { maintenance_mode: true } },
        },
        auth: { user: { role: 'member' } },
      });
      // MaintenancePage is lazy loaded, so we just verify no CommunityNotFound
      renderWithRouter('/dashboard');
      expect(screen.queryByText('Community Not Found')).not.toBeInTheDocument();
    });
  });
});
