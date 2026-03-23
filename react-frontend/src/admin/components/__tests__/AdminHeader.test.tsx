// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for AdminHeader — top bar with user menu, back-to-site, and notifications
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { HeroUIProvider } from '@heroui/react';

// ─── Stable mock references ─────────────────────────────────────────────────

const mockLogout = vi.fn();
const mockNavigate = vi.fn();
const mockTenantPath = (p: string) => `/test${p}`;

vi.mock('react-router-dom', async (importOriginal) => {
  const actual = await importOriginal();
  return {
    ...(actual as Record<string, unknown>),
    useNavigate: () => mockNavigate,
  };
});

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, name: 'Admin User', role: 'admin', avatar_url: null },
    isAuthenticated: true,
    logout: mockLogout,
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Community', slug: 'test', configuration: {} },
    tenantSlug: 'test',
    branding: { name: 'Test Community' },
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
    tenantPath: mockTenantPath,
  })),
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    showToast: vi.fn(),
  })),
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

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: vi.fn(() => null),
  resolveAssetUrl: vi.fn((url: string) => url),
}));

import { AdminHeader } from '../AdminHeader';

// ─── Wrapper ─────────────────────────────────────────────────────────────────

function W({ children }: { children: React.ReactNode }) {
  return (
    <HeroUIProvider>
      <MemoryRouter initialEntries={['/test/admin']}>
        {children}
      </MemoryRouter>
    </HeroUIProvider>
  );
}

// ─── Tests ───────────────────────────────────────────────────────────────────

describe('AdminHeader', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing', () => {
    const { container } = render(
      <W><AdminHeader sidebarCollapsed={false} /></W>
    );
    expect(container.querySelector('header')).toBeTruthy();
  });

  it('renders the back-to-site button', () => {
    render(<W><AdminHeader sidebarCollapsed={false} /></W>);
    expect(screen.getByText('Back to site')).toBeTruthy();
  });

  it('renders tenant name in the header', () => {
    render(<W><AdminHeader sidebarCollapsed={false} /></W>);
    expect(screen.getByText('Test Community')).toBeTruthy();
  });

  it('renders user name in the dropdown trigger', () => {
    render(<W><AdminHeader sidebarCollapsed={false} /></W>);
    expect(screen.getByText('Admin User')).toBeTruthy();
  });

  it('renders notifications bell button', () => {
    render(<W><AdminHeader sidebarCollapsed={false} /></W>);
    const bellButton = screen.getByLabelText('Notifications');
    expect(bellButton).toBeTruthy();
  });

  it('renders mobile hamburger button when onSidebarToggle is provided', () => {
    const toggle = vi.fn();
    render(<W><AdminHeader sidebarCollapsed={false} onSidebarToggle={toggle} /></W>);
    const hamburger = screen.getByLabelText('Toggle sidebar');
    expect(hamburger).toBeTruthy();
  });

  it('does not render mobile hamburger when onSidebarToggle is not provided', () => {
    render(<W><AdminHeader sidebarCollapsed={false} /></W>);
    expect(screen.queryByLabelText('Toggle sidebar')).toBeNull();
  });

  it('calls onSidebarToggle when hamburger is clicked', () => {
    const toggle = vi.fn();
    render(<W><AdminHeader sidebarCollapsed={false} onSidebarToggle={toggle} /></W>);
    fireEvent.click(screen.getByLabelText('Toggle sidebar'));
    expect(toggle).toHaveBeenCalledTimes(1);
  });

  it('applies collapsed CSS class when sidebar is collapsed', () => {
    const { container } = render(
      <W><AdminHeader sidebarCollapsed={true} /></W>
    );
    const header = container.querySelector('header');
    expect(header?.className).toContain('md:left-16');
  });

  it('applies expanded CSS class when sidebar is not collapsed', () => {
    const { container } = render(
      <W><AdminHeader sidebarCollapsed={false} /></W>
    );
    const header = container.querySelector('header');
    expect(header?.className).toContain('md:left-64');
  });
});
