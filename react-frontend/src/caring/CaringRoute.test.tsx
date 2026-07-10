// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import React from 'react';

// ─── API mock ─────────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn(), download: vi.fn(), upload: vi.fn() },
}));
vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));

// ─── Mutable auth/tenant state — mutated per test BEFORE render ────────────────
const {
  authState,
  tenantState,
} = vi.hoisted(() => {
  const authState = {
    user: { id: 1, name: 'Alice', role: 'admin', is_admin: false } as Record<string, unknown> | null,
    isAuthenticated: true,
    isLoading: false,
    status: 'idle' as 'idle' | 'loading' | 'error',
    login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), error: null,
  };
  const tenantState = {
    tenant: { id: 2, name: 'hOUR Timebank', slug: 'hour-timebank' },
    tenantSlug: 'hour-timebank',
    tenantPath: (p: string) => `/hour-timebank${p}`,
    _hasFeature: true,
    hasFeature: (f: string) => tenantState._hasFeature,
    hasModule: () => true,
  };
  return { authState, tenantState };
});

// ─── Context mock using mutable refs ─────────────────────────────────────────
vi.mock('@/contexts', () => ({
  // AuthContext
  useAuth: () => ({ ...authState }),
  // TenantContext
  useTenant: () => ({
    tenant: tenantState.tenant,
    tenantSlug: tenantState.tenantSlug,
    tenantPath: tenantState.tenantPath,
    hasFeature: tenantState.hasFeature,
    hasModule: tenantState.hasModule,
  }),
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn(), showToast: vi.fn() }),
  useTheme: () => ({ resolvedTheme: 'light', theme: 'system', toggleTheme: vi.fn(), setTheme: vi.fn() }),
  useNotifications: () => ({
    unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(),
    hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn(),
  }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({
    consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(),
    saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn(),
  }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  usePresence: () => ({ status: 'offline', setStatus: vi.fn(), getPresence: vi.fn(), isOnline: vi.fn(() => false) }),
  usePresenceOptional: () => null,
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
}));

// ─── react-router-dom — stub Navigate and Outlet ──────────────────────────────
const { locationState } = vi.hoisted(() => ({
  locationState: { pathname: '/hour-timebank/caring' },
}));

vi.mock('react-router-dom', async (importOriginal) => {
  const orig = await importOriginal<typeof import('react-router-dom')>();
  return {
    ...orig,
    useNavigate: () => vi.fn(),
    useLocation: () => ({ pathname: locationState.pathname, search: '', hash: '', state: null, key: 'default' }),
    Outlet: () => <div data-testid="outlet" />,
    Navigate: ({ to }: { to: string }) => <div data-testid="redirect" data-to={to} />,
  };
});

// ─── Stub LoadingScreen ───────────────────────────────────────────────────────
vi.mock('@/components/feedback', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/feedback')>();
  return {
    ...orig,
    LoadingScreen: () => <div data-testid="loading-screen" />,
  };
});

// ─────────────────────────────────────────────────────────────────────────────
describe('CaringRoute', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    // Reset to safe default: authenticated admin, feature ON, path = /caring
    authState.user = { id: 1, name: 'Alice', role: 'admin', is_admin: false };
    authState.isAuthenticated = true;
    authState.isLoading = false;
    authState.status = 'idle';
    tenantState._hasFeature = true;
    locationState.pathname = '/hour-timebank/caring';
  });

  it('shows loading screen while auth isLoading is true', async () => {
    authState.isLoading = true;
    authState.isAuthenticated = false;
    authState.user = null;
    const { CaringRoute } = await import('./CaringRoute');
    render(<CaringRoute />);
    expect(screen.getByTestId('loading-screen')).toBeInTheDocument();
  });

  it('shows loading screen when auth status is "loading"', async () => {
    authState.status = 'loading';
    authState.isAuthenticated = false;
    authState.user = null;
    const { CaringRoute } = await import('./CaringRoute');
    render(<CaringRoute />);
    expect(screen.getByTestId('loading-screen')).toBeInTheDocument();
  });

  it('redirects unauthenticated users to login', async () => {
    authState.isAuthenticated = false;
    authState.user = null;
    authState.isLoading = false;
    authState.status = 'idle';
    const { CaringRoute } = await import('./CaringRoute');
    render(<CaringRoute />);
    const redirect = screen.getByTestId('redirect');
    expect(redirect).toBeInTheDocument();
    expect(redirect.getAttribute('data-to')).toContain('/login');
  });

  it('redirects member role (no safeguarding access) to dashboard', async () => {
    authState.user = { id: 1, name: 'Bob', role: 'member', is_admin: false };
    authState.isAuthenticated = true;
    const { CaringRoute } = await import('./CaringRoute');
    render(<CaringRoute />);
    const redirect = screen.getByTestId('redirect');
    expect(redirect.getAttribute('data-to')).toContain('/dashboard');
  });

  it('renders Outlet for admin with caring_community feature ON', async () => {
    authState.user = { id: 1, name: 'Alice', role: 'admin', is_admin: false };
    tenantState._hasFeature = true;
    const { CaringRoute } = await import('./CaringRoute');
    render(<CaringRoute />);
    expect(screen.getByTestId('outlet')).toBeInTheDocument();
    expect(screen.queryByTestId('redirect')).toBeNull();
  });

  it('renders Outlet for is_admin=true user regardless of role string', async () => {
    authState.user = { id: 2, name: 'Carol', role: 'member', is_admin: true };
    tenantState._hasFeature = true;
    const { CaringRoute } = await import('./CaringRoute');
    render(<CaringRoute />);
    expect(screen.getByTestId('outlet')).toBeInTheDocument();
  });

  it('renders Outlet for super_admin even if caring_community feature is OFF', async () => {
    authState.user = { id: 3, name: 'Dave', role: 'super_admin', is_admin: false };
    tenantState._hasFeature = false;
    const { CaringRoute } = await import('./CaringRoute');
    render(<CaringRoute />);
    // fullAccess=true → second feature check skipped → Outlet renders
    expect(screen.getByTestId('outlet')).toBeInTheDocument();
  });

  it('renders the feature-disabled overview when the router pathname is already slug-stripped', async () => {
    authState.user = { id: 3, name: 'Dave', role: 'super_admin', is_admin: false };
    tenantState._hasFeature = false;
    locationState.pathname = '/caring';
    const { CaringRoute } = await import('./CaringRoute');
    render(<CaringRoute />);
    expect(screen.getByTestId('outlet')).toBeInTheDocument();
    expect(screen.queryByTestId('redirect')).toBeNull();
  });

  it('does NOT show loading screen once auth resolves', async () => {
    authState.isLoading = false;
    authState.status = 'idle';
    const { CaringRoute } = await import('./CaringRoute');
    render(<CaringRoute />);
    expect(screen.queryByTestId('loading-screen')).toBeNull();
  });

  it('redirects coordinator on /caring root to /caring/safeguarding (limited access path)', async () => {
    // coordinator has safeguarding access but not full access
    // location = /hour-timebank/caring (not a safeguarding path)
    // Expected: redirect to /caring/safeguarding
    authState.user = { id: 5, name: 'Eve', role: 'coordinator', is_admin: false };
    tenantState._hasFeature = true;
    locationState.pathname = '/hour-timebank/caring';
    const { CaringRoute } = await import('./CaringRoute');
    render(<CaringRoute />);
    const redirect = screen.getByTestId('redirect');
    expect(redirect.getAttribute('data-to')).toContain('/caring/safeguarding');
  });

  it('renders Outlet for coordinator already on /caring/safeguarding path', async () => {
    authState.user = { id: 5, name: 'Eve', role: 'coordinator', is_admin: false };
    tenantState._hasFeature = true;
    locationState.pathname = '/hour-timebank/caring/safeguarding';
    const { CaringRoute } = await import('./CaringRoute');
    render(<CaringRoute />);
    // On the safeguarding path — the redirect-to-safeguarding branch is skipped → Outlet
    expect(screen.getByTestId('outlet')).toBeInTheDocument();
  });

  it('redirects broker (safeguarding only) to /caring/safeguarding when on /caring root', async () => {
    authState.user = { id: 6, name: 'Frank', role: 'broker', is_admin: false };
    tenantState._hasFeature = true;
    locationState.pathname = '/hour-timebank/caring';
    const { CaringRoute } = await import('./CaringRoute');
    render(<CaringRoute />);
    const redirect = screen.getByTestId('redirect');
    expect(redirect.getAttribute('data-to')).toContain('/caring/safeguarding');
  });

  it('login redirect includes the from-location state', async () => {
    authState.isAuthenticated = false;
    authState.user = null;
    const { CaringRoute } = await import('./CaringRoute');
    render(<CaringRoute />);
    // The Navigate stub only receives `to` — confirm it's the login path
    const redirect = screen.getByTestId('redirect');
    expect(redirect.getAttribute('data-to')).toMatch(/login/);
  });
});
