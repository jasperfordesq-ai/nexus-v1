// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for ExplorePage — the community explore/discovery page.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';

// ── Stable mock references (avoid infinite re-render loops) ──────────────────

const mockTenantPath = (p: string) => `/test${p}`;
const mockHasFeature = vi.fn(() => true);
const mockTenant = {
  tenantPath: mockTenantPath,
  hasFeature: mockHasFeature,
  hasModule: vi.fn(() => true),
  tenant: { id: 1, name: 'Test', slug: 'test' },
  features: [],
  configuration: {},
  isLoading: false,
};

const mockAuth = {
  user: { id: 1, name: 'Test User' },
  isAuthenticated: true,
  login: vi.fn(),
  logout: vi.fn(),
  register: vi.fn(),
  updateUser: vi.fn(),
  refreshUser: vi.fn(),
  status: 'idle' as const,
  error: null,
};

const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
};

vi.mock('@/contexts', () => ({
  useTenant: () => mockTenant,
  useAuth: () => mockAuth,
  useToast: () => mockToast,
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

// ── Mock useApi with configurable return ─────────────────────────────────────

const mockExecute = vi.fn();

const mockExploreData = {
  trending_posts: [
    { id: 1, user_id: 1, excerpt: 'A trending post', image_url: null, created_at: '2026-03-22T10:00:00Z', author_name: 'Alice', author_avatar: null, likes_count: 5, comments_count: 2, engagement: 7 },
  ],
  popular_listings: [
    { id: 10, title: 'Popular Listing', type: 'offer', image_url: null, location: 'Dublin', estimated_hours: 2, created_at: '2026-03-22T10:00:00Z', view_count: 50, save_count: 10, category_name: 'Tech', category_slug: 'tech', category_color: '#3b82f6', author_name: 'Bob', author_avatar: null },
  ],
  active_groups: [],
  upcoming_events: [],
  top_contributors: [],
  trending_hashtags: [
    { id: 1, tag: 'timebanking', post_count: 42, last_used_at: '2026-03-22T10:00:00Z' },
  ],
  new_members: [
    { id: 99, name: 'Newbie', avatar: null, tagline: 'Hello!', created_at: '2026-03-22T10:00:00Z' },
  ],
  featured_challenges: [],
  community_stats: { total_members: 100, exchanges_this_month: 25, hours_exchanged: 150, active_listings: 42 },
  recommended_listings: [],
};

const mockState = {
  useApiReturn: {
    data: mockExploreData,
    isLoading: false,
    error: null,
    execute: mockExecute,
  } as Record<string, unknown>,
  categoriesReturn: {
    data: [
      { id: 1, name: 'Tech', slug: 'tech', color: '#3b82f6' },
      { id: 2, name: 'Art', slug: 'art' },
    ],
    isLoading: false,
    error: null,
    execute: vi.fn(),
  } as Record<string, unknown>,
};

vi.mock('@/hooks/useApi', () => ({
  useApi: (url: string) => {
    if (url.includes('categories')) return mockState.categoriesReturn;
    return mockState.useApiReturn;
  },
}));

vi.mock('@/hooks/usePageTitle', () => ({
  usePageTitle: vi.fn(),
}));

vi.mock('@/hooks', () => ({
  usePageTitle: vi.fn(),
  useApi: (url: string) => {
    if (url.includes('categories')) return mockState.categoriesReturn;
    return mockState.useApiReturn;
  },
}));

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, fallbackOrOpts?: string | Record<string, unknown>, opts?: Record<string, unknown>) => {
      const fallback = typeof fallbackOrOpts === 'string' ? fallbackOrOpts : key;
      const vars = typeof fallbackOrOpts === 'object' ? fallbackOrOpts : opts;
      if (!vars) return fallback;
      return fallback.replace(/\{\{(\w+)\}\}/g, (_, k) => String(vars[k] ?? `{{${k}}}`));
    },
    i18n: { changeLanguage: vi.fn() },
  }),
}));

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: (url: string | null) => url || '',
  resolveAssetUrl: (url: string | null) => url || '',
}));

vi.mock('@/components/explore', () => ({
  ExploreSection: ({ children, title, subtitle }: { children: React.ReactNode; title: string; subtitle?: string }) => (
    <section>
      <h2>{title}</h2>
      {subtitle && <p>{subtitle}</p>}
      {children}
    </section>
  ),
  ExploreStatCard: ({ label, value, suffix }: { label: string; value: number; suffix?: string; icon: unknown }) => (
    <div data-testid="stat-card">
      <span>{value}{suffix}</span>
      <span>{label}</span>
    </div>
  ),
  HorizontalScroll: ({ children }: { children: React.ReactNode }) => <div data-testid="horizontal-scroll">{children}</div>,
}));

vi.mock('framer-motion', () => ({
  motion: {
    div: ({ children, ...props }: Record<string, unknown>) => {
      const safe: Record<string, unknown> = {};
      const skip = new Set(['variants', 'initial', 'animate', 'transition', 'exit', 'whileHover', 'whileTap', 'whileInView', 'viewport']);
      for (const [k, v] of Object.entries(props)) { if (!skip.has(k)) safe[k] = v; }
      return <div {...safe}>{children as React.ReactNode}</div>;
    },
  },
  AnimatePresence: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    useNavigate: () => vi.fn(),
    Link: ({ children, to }: { children: React.ReactNode; to: string }) => <a href={to}>{children}</a>,
  };
});

import ExplorePage from '../ExplorePage';

// ── Tests ────────────────────────────────────────────────────────────────────

describe('ExplorePage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockHasFeature.mockReturnValue(true);
    mockState.useApiReturn = {
      data: mockExploreData,
      isLoading: false,
      error: null,
      execute: mockExecute,
    };
    mockState.categoriesReturn = {
      data: [
        { id: 1, name: 'Tech', slug: 'tech', color: '#3b82f6' },
        { id: 2, name: 'Art', slug: 'art' },
      ],
      isLoading: false,
      error: null,
      execute: vi.fn(),
    };
  });

  it('renders without crashing', () => {
    render(<ExplorePage />);
    expect(screen.getByText('heading')).toBeInTheDocument();
  });

  it('renders the heading and subtitle', () => {
    render(<ExplorePage />);
    expect(screen.getByText('heading')).toBeInTheDocument();
    expect(screen.getByText('subtitle')).toBeInTheDocument();
  });

  it('renders the search bar', () => {
    render(<ExplorePage />);
    // The Input has placeholder text
    expect(screen.getByPlaceholderText('search_placeholder')).toBeInTheDocument();
  });

  it('renders category chips from the categories API', () => {
    render(<ExplorePage />);
    // "Tech" may appear in both category chips and listing category — use getAllByText
    expect(screen.getAllByText('Tech').length).toBeGreaterThanOrEqual(1);
    expect(screen.getByText('Art')).toBeInTheDocument();
  });

  it('renders community stats cards', () => {
    render(<ExplorePage />);
    const statCards = screen.getAllByTestId('stat-card');
    expect(statCards).toHaveLength(4);

    // Check specific values
    expect(screen.getByText('100')).toBeInTheDocument(); // total_members
    expect(screen.getByText('25')).toBeInTheDocument(); // exchanges_this_month
    expect(screen.getByText('150h')).toBeInTheDocument(); // hours_exchanged
    expect(screen.getByText('42')).toBeInTheDocument(); // active_listings
  });

  it('renders trending posts section with data', () => {
    render(<ExplorePage />);
    expect(screen.getByText('trending_posts.title')).toBeInTheDocument();
    expect(screen.getByText('A trending post')).toBeInTheDocument();
    expect(screen.getByText('Alice')).toBeInTheDocument();
  });

  it('renders popular listings section', () => {
    render(<ExplorePage />);
    expect(screen.getByText('popular_listings.title')).toBeInTheDocument();
    expect(screen.getByText('Popular Listing')).toBeInTheDocument();
  });

  it('renders trending hashtags section', () => {
    render(<ExplorePage />);
    expect(screen.getByText('trending_hashtags.title')).toBeInTheDocument();
    expect(screen.getByText('timebanking')).toBeInTheDocument();
  });

  it('renders new members section', () => {
    render(<ExplorePage />);
    expect(screen.getByText('new_members.title')).toBeInTheDocument();
    expect(screen.getByText('Newbie')).toBeInTheDocument();
  });

  it('shows loading skeletons when isLoading is true', () => {
    mockState.useApiReturn = {
      data: null,
      isLoading: true,
      error: null,
      execute: mockExecute,
    };
    render(<ExplorePage />);
    // Stat cards show skeletons instead of real values
    expect(screen.queryByTestId('stat-card')).not.toBeInTheDocument();
  });

  it('shows error banner when API returns an error', () => {
    mockState.useApiReturn = {
      data: null,
      isLoading: false,
      error: new Error('Network failure'),
      execute: mockExecute,
    };
    render(<ExplorePage />);
    expect(screen.getByText('error_loading')).toBeInTheDocument();
    expect(screen.getByText('retry')).toBeInTheDocument();
  });

  it('does not show error banner when loading', () => {
    mockState.useApiReturn = {
      data: null,
      isLoading: true,
      error: new Error('Network failure'),
      execute: mockExecute,
    };
    render(<ExplorePage />);
    expect(screen.queryByText('error_loading')).not.toBeInTheDocument();
  });

  it('hides sections when hasFeature returns false', () => {
    mockHasFeature.mockReturnValue(false);
    render(<ExplorePage />);
    // Events and groups sections should not render
    expect(screen.queryByText('upcoming_events.title')).not.toBeInTheDocument();
    expect(screen.queryByText('active_groups.title')).not.toBeInTheDocument();
    expect(screen.queryByText('top_contributors.title')).not.toBeInTheDocument();
  });

  it('does not render category chips when none are available', () => {
    mockState.categoriesReturn = {
      data: [],
      isLoading: false,
      error: null,
      execute: vi.fn(),
    };
    render(<ExplorePage />);
    // "Tech" may still appear in the popular listings section (category chip),
    // but the category quick-filter chips section (with "Art") should be gone
    expect(screen.queryByText('Art')).not.toBeInTheDocument();
  });
});
