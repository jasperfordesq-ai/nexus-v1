// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for DashboardPage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';

const {
  featureFlags,
  mockApiGet,
  mockUseFeature,
  mockUseModule,
  moduleFlags,
} = vi.hoisted(() => ({
  featureFlags: {
    connections: true,
    events: true,
    gamification: true,
    groups: true,
  } as Record<string, boolean>,
  moduleFlags: {
    feed: true,
    listings: true,
    messages: true,
    profile: true,
    wallet: true,
  } as Record<string, boolean>,
  mockApiGet: vi.fn(),
  mockUseFeature: vi.fn((feature: string) => featureFlags[feature] ?? true),
  mockUseModule: vi.fn((module: string) => moduleFlags[module] ?? true),
}));

// Mock dependencies
vi.mock('@/lib/api', () => ({
  api: {
    get: mockApiGet,
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
  useModule: mockUseModule,
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

function buildApiResponse(endpoint: string) {
  switch (endpoint) {
    case '/v2/wallet/balance':
      return { success: true, data: { balance: 12 } };
    case '/v2/wallet/pending-count':
      return { success: true, data: { count: 4 } };
    case '/v2/listings?user_id=1&per_page=5':
      return {
        success: true,
        data: [{ id: 11, title: 'Garden help', description: 'Need help weeding', type: 'request', author_name: 'Pat' }],
        meta: { total_items: 1 },
      };
    case '/v2/listings?per_page=4':
      return {
        success: true,
        data: [{ id: 12, title: 'Dog walking', type: 'offer', author_name: 'Alex' }],
      };
    case '/v2/feed?per_page=5':
      return { success: true, data: [] };
    case '/v2/groups?user_id=1&per_page=3':
      return { success: true, data: [] };
    case '/v2/gamification/profile':
      return { success: true, data: { level: 2, xp: 150, level_progress: 45, badges_count: 3 } };
    case '/v2/events?when=upcoming&per_page=3':
      return { success: true, data: [] };
    case '/v2/members/1/endorsements':
      return { success: true, data: { endorsements: [] } };
    case '/v2/reviews/pending':
      return {
        success: true,
        data: [
          {
            exchange_id: 21,
            exchange_title: 'Garden exchange',
            receiver_id: 2,
            receiver_name: 'Morgan',
            receiver_avatar: null,
            completed_at: '2026-04-20T09:00:00Z',
          },
        ],
      };
    default:
      return { success: true, data: [] };
  }
}

describe('DashboardPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    Object.assign(featureFlags, {
      connections: true,
      events: true,
      gamification: true,
      groups: true,
    });
    Object.assign(moduleFlags, {
      feed: true,
      listings: true,
      messages: true,
      profile: true,
      wallet: true,
    });
    mockUseFeature.mockImplementation((feature: string) => featureFlags[feature] ?? true);
    mockUseModule.mockImplementation((module: string) => moduleFlags[module] ?? true);
    mockApiGet.mockImplementation((endpoint: string) => Promise.resolve(buildApiResponse(endpoint)));
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
    featureFlags.connections = false;

    render(<DashboardPage />);

    expect(screen.queryByText('Find Members')).not.toBeInTheDocument();
    expect(screen.getByText('Create Listing')).toBeInTheDocument();
  });

  it('renders pending reviews from the direct array payload', async () => {
    render(<DashboardPage />);

    expect(await screen.findByText('Morgan')).toBeInTheDocument();
    expect(screen.getByText('Garden exchange')).toBeInTheDocument();
    expect(screen.getByText('View All Pending')).toBeInTheDocument();
  });

  it('keeps core dashboard sections available when optional modules are disabled', async () => {
    Object.assign(featureFlags, {
      connections: false,
      events: false,
      gamification: false,
      groups: false,
    });
    Object.assign(moduleFlags, {
      feed: false,
      listings: false,
      messages: false,
      profile: false,
      wallet: false,
    });

    render(<DashboardPage />);

    await waitFor(() => {
      expect(mockApiGet).toHaveBeenCalledWith('/v2/wallet/balance');
    });

    expect(screen.getByText('Balance')).toBeInTheDocument();
    expect(screen.getByText('Active Listings')).toBeInTheDocument();
    expect(screen.getByText('Recent Listings')).toBeInTheDocument();
    expect(screen.getByText('New Listing')).toBeInTheDocument();
    expect(screen.getByText('Create Listing')).toBeInTheDocument();
    expect(screen.getByText('View Wallet')).toBeInTheDocument();
    expect(screen.queryByText('Find Members')).not.toBeInTheDocument();
  });
});
