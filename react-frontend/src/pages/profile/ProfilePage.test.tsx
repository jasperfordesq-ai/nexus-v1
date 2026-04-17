// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for ProfilePage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';

const { mockApiGet, mockUseFeature } = vi.hoisted(() => ({
  mockApiGet: vi.fn(),
  mockUseFeature: vi.fn(() => true),
}));

function installDefaultApiMocks() {
  mockApiGet.mockImplementation((url: string) => {
    if (url.includes('/verification-badges')) {
      return Promise.resolve({ success: true, data: [] });
    }

    if (url.includes('/v2/gamification/profile')) {
      return Promise.resolve({
        success: true,
        data: {
          xp: 120,
          level: 2,
          badges_count: 1,
        },
      });
    }

    if (url.includes('/v2/gamification/badges')) {
      return Promise.resolve({
        success: true,
        data: [
          {
            badge_key: 'first_exchange',
            name: 'First Exchange',
            description: 'Completed first exchange',
            icon: 'trophy',
            earned_at: '2026-01-01T00:00:00Z',
          },
        ],
      });
    }

    if (url.includes('/v2/users/')) {
      return Promise.resolve({
        success: true,
        data: {
          id: 42,
          first_name: 'John',
          last_name: 'Doe',
          name: 'John Doe',
          bio: 'Test bio',
          location: 'Dublin',
          avatar_url: null,
          joined_at: '2025-01-01',
          hours_given: 10,
          hours_received: 5,
          listings_count: 3,
        },
      });
    }

    return Promise.resolve({ success: true, data: [] });
  });
}

vi.mock('@/lib/api', () => ({
  api: {
    get: mockApiGet,
    post: vi.fn().mockResolvedValue({ success: true }),
    delete: vi.fn().mockResolvedValue({ success: true }),
  },
  tokenManager: { getTenantId: vi.fn() },
}));

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    useParams: vi.fn(() => ({ id: '42' })),
  };
});

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, first_name: 'Test' },
    isAuthenticated: true,
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  })),
  useFeature: mockUseFeature,
  useToast: vi.fn(() => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
  })),
  useTheme: () => ({ resolvedTheme: 'light', toggleTheme: vi.fn(), theme: 'system', setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useModule: vi.fn(() => true),
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: vi.fn((url) => url || '/default-avatar.png'),
  resolveAssetUrl: vi.fn((url) => url || ''),
  formatRelativeTime: vi.fn(() => '2 hours ago'),
}));
vi.mock('@/components/feedback', () => ({
  LoadingScreen: () => <div data-testid="loading-screen">Loading...</div>,
  EmptyState: ({ title, action }: { title: string; action?: unknown }) => (
    <div data-testid="empty-state">
      <div>{title}</div>
      {action}
    </div>
  ),
}));
vi.mock('@/components/location', () => ({
  LocationMapCard: () => <div data-testid="location-map">Map</div>,
}));
vi.mock('@/components/seo', () => ({ PageMeta: () => null }));
vi.mock('dompurify', () => ({
  default: { sanitize: (html: string) => html },
}));
vi.mock('framer-motion', () => {
  const motionProps = new Set(['variants', 'initial', 'animate', 'layout', 'transition', 'exit', 'whileHover', 'whileTap', 'whileInView', 'viewport']);
  const filterMotion = (props: Record<string, unknown>) => {
    const filtered: Record<string, unknown> = {};
    for (const [key, value] of Object.entries(props)) {
      if (!motionProps.has(key)) filtered[key] = value;
    }
    return filtered;
  };

  return {
    motion: {
      div: ({ children, ...props }: Record<string, unknown>) => <div {...filterMotion(props)}>{children}</div>,
    },
    AnimatePresence: ({ children }: { children: unknown }) => <>{children}</>,
  };
});

import { ProfilePage } from './ProfilePage';

describe('ProfilePage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockUseFeature.mockReturnValue(true);
    installDefaultApiMocks();
  });

  it('renders without crashing', () => {
    render(<ProfilePage />);
    expect(document.body).toBeTruthy();
  });

  it('loads profile data from API', async () => {
    const { api } = await import('@/lib/api');
    render(<ProfilePage />);

    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith(expect.stringContaining('/v2/users/42'));
    });
  });

  it('shows profile name after loading', async () => {
    render(<ProfilePage />);

    await waitFor(() => {
      expect(screen.getByText('John Doe')).toBeInTheDocument();
    });
  });

  it('uses Explore as the fallback destination when connections are disabled', async () => {
    mockUseFeature.mockImplementation((feature: string) => feature !== 'connections');
    mockApiGet.mockImplementation((url: string) => {
      if (url.includes('/v2/users/')) {
        return Promise.resolve({ success: false, code: 'NOT_FOUND' });
      }

      return Promise.resolve({ success: true, data: [] });
    });

    render(<ProfilePage />);

    await waitFor(() => {
      expect(screen.getByText('Back to Explore')).toBeInTheDocument();
    });

    expect(screen.getByRole('link', { name: /Back to Explore/i })).toHaveAttribute('href', '/test/explore');
  });
});
