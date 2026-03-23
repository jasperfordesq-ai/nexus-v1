// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for TenantLogo component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';

const mockTenantBase = {
  tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
  tenantSlug: 'test',
  tenantPath: (p: string) => '/test' + p,
  isLoading: false,
  hasFeature: vi.fn(() => true),
  hasModule: vi.fn(() => true),
};

const mockUseTenant = vi.fn(() => ({
  ...mockTenantBase,
  branding: {
    name: 'Test Timebank',
    logo: null as string | null,
    primaryColor: '#6366f1',
    tagline: 'Helping each other' as string | null,
  },
}));

vi.mock('@/contexts', () => ({
  useToast: vi.fn(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() })),
  useAuth: vi.fn(() => ({
    isAuthenticated: false,
    user: null,
    login: vi.fn(),
    logout: vi.fn(),
    register: vi.fn(),
    updateUser: vi.fn(),
    refreshUser: vi.fn(),
    status: 'idle',
    error: null,
  })),
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
}));

import { TenantLogo } from '../TenantLogo';

describe('TenantLogo', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockUseTenant.mockReturnValue({
      ...mockTenantBase,
      branding: {
        name: 'Test Timebank',
        logo: null,
        primaryColor: '#6366f1',
        tagline: 'Helping each other',
      },
    });
  });

  it('renders without crashing', () => {
    render(<TenantLogo />);
    expect(screen.getByRole('link')).toBeInTheDocument();
  });

  it('renders a link to the tenant home', () => {
    render(<TenantLogo />);
    const link = screen.getByRole('link');
    expect(link).toHaveAttribute('href', '/test/');
  });

  it('renders tenant name when showName is true (default)', () => {
    render(<TenantLogo />);
    expect(screen.getByText('Test Timebank')).toBeInTheDocument();
  });

  it('renders avatar with initials when no logo', () => {
    render(<TenantLogo />);
    // Avatar should be present with initials TT (Test Timebank)
    expect(screen.getByText('TT')).toBeInTheDocument();
  });

  it('renders logo image when branding has logo', () => {
    mockUseTenant.mockReturnValue({
      ...mockTenantBase,
      branding: {
        name: 'Test Timebank',
        logo: '/logo.png',
        primaryColor: '#6366f1',
        tagline: null,
      },
    });

    render(<TenantLogo />);
    const img = screen.getByAltText('Test Timebank');
    expect(img).toBeInTheDocument();
    expect(img).toHaveAttribute('src', '/logo.png');
  });

  it('hides name when showName is false', () => {
    render(<TenantLogo showName={false} />);
    expect(screen.queryByText('Test Timebank')).not.toBeInTheDocument();
  });

  it('hides name in compact mode', () => {
    render(<TenantLogo compact={true} />);
    expect(screen.queryByText('Test Timebank')).not.toBeInTheDocument();
  });

  it('renders tagline when showTagline is true', () => {
    render(<TenantLogo showTagline={true} />);
    expect(screen.getByText('Helping each other')).toBeInTheDocument();
  });

  it('does not render tagline when showTagline is false', () => {
    render(<TenantLogo showTagline={false} />);
    expect(screen.queryByText('Helping each other')).not.toBeInTheDocument();
  });

  it('does not render tagline when branding has no tagline', () => {
    mockUseTenant.mockReturnValue({
      ...mockTenantBase,
      branding: {
        name: 'Test Timebank',
        logo: null,
        primaryColor: '#6366f1',
        tagline: null,
      },
    });

    render(<TenantLogo showTagline={true} />);
    // Only the name should appear, no tagline
    expect(screen.getByText('Test Timebank')).toBeInTheDocument();
  });

  it('applies custom className', () => {
    render(<TenantLogo className="my-custom-class" />);
    const link = screen.getByRole('link');
    expect(link.className).toContain('my-custom-class');
  });
});
