// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for AdminBreadcrumbs — auto-generates breadcrumbs from URL path
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { HeroUIProvider } from '@heroui/react';

// ─── Stable mock references ─────────────────────────────────────────────────

const mockTenantPath = (p: string) => `/test${p}`;
const mockUseTenant = vi.fn(() => ({
  tenant: { id: 2, name: 'Test Community', slug: 'test', configuration: {} },
  tenantSlug: 'test',
  branding: { name: 'Test Community' },
  hasFeature: vi.fn(() => true),
  hasModule: vi.fn(() => true),
  tenantPath: mockTenantPath,
}));
const mockUseToast = vi.fn(() => ({
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  showToast: vi.fn(),
}));

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, name: 'Admin User', role: 'admin' },
    isAuthenticated: true,
    logout: vi.fn(),
  })),
  useTenant: (...args: unknown[]) => mockUseTenant(...args),
  useToast: (...args: unknown[]) => mockUseToast(...args),
  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

import { AdminBreadcrumbs } from '../AdminBreadcrumbs';

// ─── Wrapper ─────────────────────────────────────────────────────────────────

function W({ children, path = '/test/admin/users' }: { children: React.ReactNode; path?: string }) {
  return (
    <HeroUIProvider>
      <MemoryRouter initialEntries={[path]}>
        {children}
      </MemoryRouter>
    </HeroUIProvider>
  );
}

// ─── Tests ───────────────────────────────────────────────────────────────────

describe('AdminBreadcrumbs', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing', () => {
    const { container } = render(<W><AdminBreadcrumbs /></W>);
    expect(container.querySelector('nav')).toBeTruthy();
  });

  it('renders nav element with aria-label', () => {
    const { container } = render(<W><AdminBreadcrumbs /></W>);
    const nav = container.querySelector('nav');
    expect(nav).toBeTruthy();
    expect(nav?.getAttribute('aria-label')).toBe('Breadcrumbs');
  });

  it('renders with custom items', () => {
    const items = [
      { label: 'Dashboard', href: '/admin' },
      { label: 'Users' },
    ];
    render(<W><AdminBreadcrumbs items={items} /></W>);
    expect(screen.getByText('Dashboard')).toBeTruthy();
    expect(screen.getByText('Users')).toBeTruthy();
  });

  it('renders links for non-last items', () => {
    const items = [
      { label: 'Dashboard', href: '/admin' },
      { label: 'Users', href: '/admin/users' },
      { label: 'Edit' },
    ];
    render(<W><AdminBreadcrumbs items={items} /></W>);
    // Dashboard and Users should be links, Edit should be a span
    const dashboardLink = screen.getByText('Dashboard').closest('a');
    expect(dashboardLink).toBeTruthy();
    const editSpan = screen.getByText('Edit');
    expect(editSpan.tagName).toBe('SPAN');
  });

  it('returns null when breadcrumbs have 1 or fewer items', () => {
    const items = [{ label: 'Dashboard' }];
    const { container } = render(<W><AdminBreadcrumbs items={items} /></W>);
    expect(container.querySelector('nav')).toBeNull();
  });

  it('auto-generates breadcrumbs from URL path', () => {
    render(<W path="/test/admin/users"><AdminBreadcrumbs /></W>);
    // Should generate "Admin" and "Users" breadcrumbs from URL
    expect(screen.getByText(/admin/i)).toBeTruthy();
  });

  it('skips numeric segments in URL (IDs)', () => {
    render(<W path="/test/admin/users/123/edit"><AdminBreadcrumbs /></W>);
    // Should not render "123" as a breadcrumb
    expect(screen.queryByText('123')).toBeNull();
  });

  it('renders an ordered list (ol) element', () => {
    const items = [
      { label: 'Dashboard', href: '/admin' },
      { label: 'Users' },
    ];
    const { container } = render(<W><AdminBreadcrumbs items={items} /></W>);
    expect(container.querySelector('ol')).toBeTruthy();
  });
});
