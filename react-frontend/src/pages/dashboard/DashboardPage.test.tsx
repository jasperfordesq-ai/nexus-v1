// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for DashboardPage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';

const { mockUseFeature } = vi.hoisted(() => ({
  mockUseFeature: vi.fn(() => true),
}));

// Mock dependencies
vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: [] }),
    post: vi.fn().mockResolvedValue({ success: true }),
  },
  tokenManager: { getTenantId: vi.fn() },
}));

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, first_name: 'Test', name: 'Test User', onboarding_completed: true },
    isAuthenticated: true,
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    branding: { name: 'Test Community', logo_url: null },
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  })),
  useFeature: mockUseFeature,
  useModule: vi.fn(() => true),
  useNotifications: vi.fn(() => ({
    counts: { messages: 3, notifications: 5 },
  })),
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
  })),

  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
}));

vi.mock('@/hooks', () => ({
  usePageTitle: vi.fn(),
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: vi.fn((url) => url || '/default-avatar.png'),
  formatRelativeTime: vi.fn(() => '2 hours ago'),
  resolveAssetUrl: vi.fn((url) => url || ''),
}));

vi.mock('@/components/seo', () => ({
  PageMeta: () => null,
}));

vi.mock('framer-motion', () => {  const motionProps = new Set(['variants', 'initial', 'animate', 'layout', 'transition', 'exit', 'whileHover', 'whileTap', 'whileInView', 'viewport']);  const filterMotion = (props: Record<string, unknown>) => {    const filtered: Record<string, unknown> = {};    for (const [k, v] of Object.entries(props)) {      if (!motionProps.has(k)) filtered[k] = v;    }    return filtered;  };  return {    motion: {      div: ({ children, ...props }: Record<string, unknown>) => <div {...filterMotion(props)}>{children}</div>,    },    AnimatePresence: ({ children }: { children: React.ReactNode }) => <>{children}</>,  };});

import { DashboardPage } from './DashboardPage';

describe('DashboardPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockUseFeature.mockReturnValue(true);
  });

  it('renders without crashing', () => {
    render(<DashboardPage />);
    expect(screen.getByText(/Welcome back/i)).toBeInTheDocument();
  });

  it('shows the welcome message with user name', () => {
    render(<DashboardPage />);
    expect(screen.getByText(/Welcome back, Test!/i)).toBeInTheDocument();
  });

  it('shows community name in welcome section', () => {
    render(<DashboardPage />);
    expect(screen.getByText(/Test Community/i)).toBeInTheDocument();
  });

  it('renders stat cards', () => {
    render(<DashboardPage />);
    expect(screen.getByText('Balance')).toBeInTheDocument();
    expect(screen.getByText('Active Listings')).toBeInTheDocument();
    // "Messages" appears in both stat card and quick actions
    expect(screen.getAllByText('Messages').length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByText('Pending').length).toBeGreaterThanOrEqual(1);
  });

  it('renders Quick Actions section', () => {
    render(<DashboardPage />);
    expect(screen.getByText('Quick Actions')).toBeInTheDocument();
    expect(screen.getByText('Create Listing')).toBeInTheDocument();
    expect(screen.getByText('View Wallet')).toBeInTheDocument();
    expect(screen.getByText('Find Members')).toBeInTheDocument();
  });

  it('renders Recent Listings section', () => {
    render(<DashboardPage />);
    expect(screen.getByText('Recent Listings')).toBeInTheDocument();
  });

  it('shows New Listing button', () => {
    render(<DashboardPage />);
    expect(screen.getByText('New Listing')).toBeInTheDocument();
  });

  it('shows onboarding banner when onboarding not completed', async () => {
    const { useAuth } = await import('@/contexts');
    vi.mocked(useAuth).mockReturnValue({
      user: { id: 1, first_name: 'Test', name: 'Test User', onboarding_completed: false },
      isAuthenticated: true,
    } as ReturnType<typeof useAuth>);

    render(<DashboardPage />);
    expect(screen.getByText('Complete your profile setup')).toBeInTheDocument();
  });

  it('hides Find Members quick action when connections feature is disabled', () => {
    mockUseFeature.mockImplementation((feature: string) => feature !== 'connections');

    render(<DashboardPage />);

    expect(screen.queryByText('Find Members')).not.toBeInTheDocument();
    expect(screen.getByText('Create Listing')).toBeInTheDocument();
  });
});
