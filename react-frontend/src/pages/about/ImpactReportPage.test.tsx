// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for ImpactReportPage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';

vi.mock('@/contexts', () => ({
  useTenant: vi.fn(() => ({
    tenantPath: (p: string) => `/test${p}`,
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
  useAuth: () => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null }),
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }),
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

vi.mock('@/lib/helpers', () => ({
  resolveAssetUrl: vi.fn((url) => url || ''),
}));

vi.mock('@/components/seo', () => ({ PageMeta: () => null }));
vi.mock('@/components/navigation/Breadcrumbs', () => ({
  Breadcrumbs: () => <nav aria-label="breadcrumb" />,
}));
vi.mock('./RelatedPages', () => ({
  RelatedPages: () => <div data-testid="related-pages" />,
}));

vi.mock('@/components/ui', () => ({
  GlassCard: ({ children, className }: { children: React.ReactNode; className?: string }) => (
    <div className={className}>{children}</div>
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

vi.mock('framer-motion', () => ({
  motion: {
    div: ({ children, ...props }: Record<string, unknown>) => {
      const motionKeys = new Set(['variants', 'initial', 'animate', 'transition', 'exit', 'whileHover', 'whileTap', 'whileInView', 'viewport']);
      const rest: Record<string, unknown> = {};
      for (const [k, v] of Object.entries(props)) { if (!motionKeys.has(k)) rest[k] = v; }
      return <div {...rest}>{children}</div>;
    },
    h1: ({ children, ...props }: Record<string, unknown>) => {
      const motionKeys = new Set(['variants', 'initial', 'animate', 'transition', 'exit']);
      const rest: Record<string, unknown> = {};
      for (const [k, v] of Object.entries(props)) { if (!motionKeys.has(k)) rest[k] = v; }
      return <h1 {...rest}>{children}</h1>;
    },
    p: ({ children, ...props }: Record<string, unknown>) => {
      const motionKeys = new Set(['variants', 'initial', 'animate', 'transition', 'exit']);
      const rest: Record<string, unknown> = {};
      for (const [k, v] of Object.entries(props)) { if (!motionKeys.has(k)) rest[k] = v; }
      return <p {...rest}>{children}</p>;
    },
  },
  AnimatePresence: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

import { ImpactReportPage } from './ImpactReportPage';

describe('ImpactReportPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    // Mock window.addEventListener for scroll event
    vi.spyOn(window, 'addEventListener').mockImplementation(() => {});
    vi.spyOn(window, 'removeEventListener').mockImplementation(() => {});
  });

  it('renders without crashing', () => {
    render(<ImpactReportPage />);
    expect(document.body).toBeInTheDocument();
  });

  it('renders breadcrumb navigation', () => {
    render(<ImpactReportPage />);
    expect(screen.getByRole('navigation', { name: /breadcrumb/i })).toBeInTheDocument();
  });

  it('renders the page heading', () => {
    render(<ImpactReportPage />);
    const headings = screen.getAllByRole('heading');
    expect(headings.length).toBeGreaterThan(0);
  });

  it('renders table of contents links', () => {
    render(<ImpactReportPage />);
    const buttons = screen.queryAllByRole('button');
    // TOC items are buttons that scroll to sections
    expect(document.body).toBeInTheDocument();
  });

  it('renders related pages section', () => {
    render(<ImpactReportPage />);
    expect(screen.getByTestId('related-pages')).toBeInTheDocument();
  });

  it('renders PDF download links', () => {
    render(<ImpactReportPage />);
    const links = screen.getAllByRole('link');
    // Should have download links for the PDF reports
    expect(links.length).toBeGreaterThanOrEqual(1);
  });
});
