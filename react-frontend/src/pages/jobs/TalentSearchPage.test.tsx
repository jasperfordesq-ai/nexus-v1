// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import type { ReactNode } from 'react';

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, opts?: Record<string, unknown>) =>
      (opts?.defaultValue as string | undefined) ?? key,
  }),
}));

vi.mock('react-router-dom', async () => {
  const actual = await import('react-router-dom');
  const React = await import('react');
  return {
    ...actual,
    Link: ({ children, to, ...rest }: { children: ReactNode; to: string; [k: string]: unknown }) =>
      React.createElement('a', { href: String(to), ...rest }, children),
    useSearchParams: () => [new URLSearchParams(), vi.fn()],
  };
});

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn().mockResolvedValue({ success: true, data: null, meta: {} }),
    post: vi.fn().mockResolvedValue({ success: true }),
    put: vi.fn().mockResolvedValue({ success: true }),
    delete: vi.fn().mockResolvedValue({ success: true }),
  },
  tokenManager: { getTenantId: vi.fn() },
}));

const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };
const mockTenant = {
  tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
  tenantPath: (p: string) => `/test${p}`,
  hasFeature: vi.fn(() => true),
  hasModule: vi.fn(() => true),
};

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, first_name: 'Test', name: 'Test User' },
    isAuthenticated: true,
  })),
  useTenant: vi.fn(() => mockTenant),
  useToast: vi.fn(() => mockToast),
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
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: vi.fn((url) => url || '/default-avatar.png'),
}));

vi.mock('@/components/ui', () => ({
  GlassCard: ({ children, className }: { children: ReactNode; className?: string }) => (
    <div data-testid="glass-card" className={className}>{children}</div>
  ),
  GlassButton: ({ children }: Record<string, unknown>) => children as never,
  GlassInput: () => null,
  BackToTop: () => null,
  AlgorithmLabel: () => null,
  ImagePlaceholder: () => null,
  DynamicIcon: () => null,
  ICON_MAP: {},
  ICON_NAMES: [],
  ListingSkeleton: () => null,
  MemberCardSkeleton: () => null,
  StatCardSkeleton: () => null,
  EventCardSkeleton: () => null,
  GroupCardSkeleton: () => null,
  ConversationSkeleton: () => null,
  ExchangeCardSkeleton: () => null,
  NotificationSkeleton: () => null,
  ProfileHeaderSkeleton: () => null,
  SkeletonList: () => null,
}));

vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title, description }: { title: string; description?: string }) => (
    <div data-testid="empty-state">
      <div>{title}</div>
      {description && <div>{description}</div>}
    </div>
  ),
}));

vi.mock('framer-motion', () => ({
  motion: {
    div: ({ children, ...rest }: Record<string, unknown>) => (
      <div {...(rest as object)}>{children as ReactNode}</div>
    ),
  },
  AnimatePresence: ({ children }: { children: ReactNode }) => <>{children}</>,
}));

import { TalentSearchPage } from './TalentSearchPage';
import { api } from '@/lib/api';

function makeCandidate(overrides: Record<string, unknown> = {}) {
  return {
    id: 1,
    first_name: 'Alice',
    last_name: 'Johnson',
    name: 'Alice Johnson',
    avatar_url: null,
    headline: 'Community Organizer',
    skills: ['Communication', 'Event Planning'],
    location: 'Dublin',
    last_active: '2026-03-20T12:00:00Z',
    ...overrides,
  };
}

describe('TalentSearchPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { items: [], total: 0 },
      meta: {},
    });
  });

  it('renders without crashing', () => {
    render(<TalentSearchPage />);
    expect(document.body).toBeTruthy();
  });

  it('renders the page heading', async () => {
    render(<TalentSearchPage />);
    await waitFor(() => {
      expect(screen.getByText('talent_search.title')).toBeInTheDocument();
    });
  });

  it('renders the subtitle', async () => {
    render(<TalentSearchPage />);
    await waitFor(() => {
      expect(screen.getByText('talent_search.subtitle')).toBeInTheDocument();
    });
  });

  it('renders the search input', () => {
    render(<TalentSearchPage />);
    const searchInput = screen.getByRole('textbox');
    expect(searchInput).toBeInTheDocument();
  });

  it('renders the Filters button', () => {
    render(<TalentSearchPage />);
    expect(screen.getByText('talent_search.skills_filter')).toBeInTheDocument();
  });

  it('renders back navigation link', () => {
    render(<TalentSearchPage />);
    expect(screen.getByText('title')).toBeInTheDocument();
  });

  it('shows empty state when no results are found', async () => {
    render(<TalentSearchPage />);
    await waitFor(() => {
      expect(screen.getByTestId('empty-state')).toBeInTheDocument();
      expect(screen.getByText('talent_search.no_results_title')).toBeInTheDocument();
    });
  });

  it('renders candidate cards when results are returned', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: {
        items: [makeCandidate()],
        total: 1,
      },
      meta: {},
    });
    render(<TalentSearchPage />);
    await waitFor(() => {
      expect(screen.getByText('Alice Johnson')).toBeInTheDocument();
    });
  });

  it('shows candidate headline', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: {
        items: [makeCandidate({ headline: 'Senior Developer' })],
        total: 1,
      },
      meta: {},
    });
    render(<TalentSearchPage />);
    await waitFor(() => {
      expect(screen.getByText('Senior Developer')).toBeInTheDocument();
    });
  });

  it('shows candidate skills as chips', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: {
        items: [makeCandidate({ skills: ['React', 'TypeScript'] })],
        total: 1,
      },
      meta: {},
    });
    render(<TalentSearchPage />);
    await waitFor(() => {
      expect(screen.getByText('React')).toBeInTheDocument();
      expect(screen.getByText('TypeScript')).toBeInTheDocument();
    });
  });

  it('shows results count after search', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { items: [makeCandidate()], total: 1 },
      meta: {},
    });
    render(<TalentSearchPage />);
    await waitFor(() => {
      expect(screen.getByText('talent_search.results_count')).toBeInTheDocument();
    });
  });

  it('shows load more button when there are more results', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { items: [makeCandidate()], total: 25 },
      meta: {},
    });
    render(<TalentSearchPage />);
    await waitFor(() => {
      expect(screen.getByText('talent_search.load_more')).toBeInTheDocument();
    });
  });

  it('shows error state when API fails', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: false,
      data: null,
      meta: {},
    });
    render(<TalentSearchPage />);
    await waitFor(() => {
      expect(screen.getByText('talent_search.error')).toBeInTheDocument();
    });
  });
});
