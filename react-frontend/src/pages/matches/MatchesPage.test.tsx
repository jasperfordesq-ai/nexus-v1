// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for MatchesPage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import React from 'react';

const mockApiGet = vi.fn();

vi.mock('@/lib/api', () => ({
  api: {
    get: (...args: unknown[]) => mockApiGet(...args),
    post: vi.fn().mockResolvedValue({ success: true }),
    put: vi.fn().mockResolvedValue({ success: true }),
  },
  tokenManager: { getTenantId: vi.fn() },
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: (url: string | null) => url || '/default-avatar.png',
  formatRelativeTime: (d: string) => d,
}));

const stableToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };
const stableTenant = {
  tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
  branding: { name: 'Test Community' },
  tenantPath: (p: string) => `/test${p}`,
  hasFeature: vi.fn(() => true),
  hasModule: vi.fn(() => true),
  isLoading: false,
};

vi.mock('@/contexts', () => ({
  useTenant: vi.fn(() => stableTenant),
  useAuth: vi.fn(() => ({ user: { id: 1, first_name: 'Test' }, isAuthenticated: true, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null })),
  useToast: vi.fn(() => stableToast),
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
vi.mock('@/components/seo', () => ({ PageMeta: () => null }));
vi.mock('@/components/navigation', () => ({
  Breadcrumbs: () => <nav aria-label="breadcrumb" />,
}));
vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title, description }: { title: string; description?: string }) => (
    <div data-testid="empty-state">
      <div>{title}</div>
      {description && <div>{description}</div>}
    </div>
  ),
}));
vi.mock('@/components/ui', () => ({
  GlassCard: ({ children, className }: { children: React.ReactNode; className?: string }) => (
    <div data-testid="glass-card" className={className}>{children}</div>
  ),
  AlgorithmLabel: () => <span data-testid="algorithm-label" />,
  GlassButton: ({ children }: Record<string, unknown>) => children as never,
  GlassInput: () => null,
  BackToTop: () => null,
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

vi.mock('framer-motion', () => {
  const motionProps = new Set(['variants', 'initial', 'animate', 'layout', 'transition', 'exit', 'whileHover', 'whileTap', 'whileInView', 'viewport']);
  const filterMotion = (props: Record<string, unknown>) => {
    const filtered: Record<string, unknown> = {};
    for (const [k, v] of Object.entries(props)) { if (!motionProps.has(k)) filtered[k] = v; }
    return filtered;
  };
  return {
    motion: {
      div: ({ children, ...props }: Record<string, unknown>) => <div {...filterMotion(props)}>{children}</div>,
    },
    AnimatePresence: ({ children }: { children: React.ReactNode }) => <>{children}</>,
  };
});

import { MatchesPage } from './MatchesPage';

describe('MatchesPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing and shows content after loading', async () => {
    mockApiGet.mockResolvedValue({ success: true, data: [] });
    render(<MatchesPage />);
    // The page renders heading via t('heading') — with mock i18n, the raw key 'heading' is returned
    // However, the heading text is rendered inside nested elements (icon div + text)
    // Instead, check for the stats and empty state which appear after loading completes
    await waitFor(() => {
      expect(screen.getByTestId('empty-state')).toBeInTheDocument();
    });
  });

  it('renders stats glass cards', async () => {
    mockApiGet.mockResolvedValue({ success: true, data: [] });
    render(<MatchesPage />);
    await waitFor(() => {
      const glassCards = screen.getAllByTestId('glass-card');
      // At least 4 stats cards are rendered
      expect(glassCards.length).toBeGreaterThanOrEqual(4);
    });
  });

  it('renders match items when API returns data', async () => {
    mockApiGet.mockResolvedValue({
      success: true,
      data: [
        {
          id: 1,
          source_type: 'listing',
          source_id: 10,
          match_score: 85,
          title: 'Gardening Help',
          reasons: ['Skill match'],
          matched_at: '2026-01-01',
        },
      ],
    });
    render(<MatchesPage />);
    await waitFor(() => {
      expect(screen.getByText('Gardening Help')).toBeInTheDocument();
    });
  });

  it('renders breadcrumb navigation', async () => {
    mockApiGet.mockResolvedValue({ success: true, data: [] });
    render(<MatchesPage />);
    expect(screen.getByRole('navigation', { name: /breadcrumb/i })).toBeInTheDocument();
  });
});
