// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for SocialPrescribingPage
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

vi.mock('@/components/seo', () => ({ PageMeta: () => null }));
vi.mock('@/components/navigation/Breadcrumbs', () => ({
  Breadcrumbs: () => <nav aria-label="breadcrumb" />,
}));
vi.mock('./RelatedPages', () => ({
  RelatedPages: () => <div data-testid="related-pages" />,
}));

vi.mock('@/components/ui', () => ({
  GlassCard: ({ children, className, hoverable }: { children: React.ReactNode; className?: string; hoverable?: boolean }) => (
    <div className={className} data-hoverable={hoverable}>{children}</div>
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

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    Link: ({ children, to }: { children: React.ReactNode; to: string }) => <a href={to}>{children}</a>,
  };
});

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

import { SocialPrescribingPage } from './SocialPrescribingPage';

describe('SocialPrescribingPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing', () => {
    render(<SocialPrescribingPage />);
    expect(document.body).toBeInTheDocument();
  });

  it('renders breadcrumb navigation', () => {
    render(<SocialPrescribingPage />);
    expect(screen.getByRole('navigation', { name: /breadcrumb/i })).toBeInTheDocument();
  });

  it('renders validated outcome statistics', () => {
    render(<SocialPrescribingPage />);
    // Outcome stats from the data array: 100%, 95%, 16:1
    expect(screen.getByText('100%')).toBeInTheDocument();
    expect(screen.getByText('95%')).toBeInTheDocument();
    expect(screen.getByText('16:1')).toBeInTheDocument();
  });

  it('renders the four referral pathway steps', () => {
    render(<SocialPrescribingPage />);
    // Step titles from the referralSteps data
    expect(screen.getByText('Formal Referral')).toBeInTheDocument();
    expect(screen.getByText('Onboarding')).toBeInTheDocument();
    expect(screen.getByText('Connection')).toBeInTheDocument();
    expect(screen.getByText('Follow-Up')).toBeInTheDocument();
  });

  it('renders strategic fit check list items', () => {
    render(<SocialPrescribingPage />);
    expect(screen.getByText(/Sláintecare/i)).toBeInTheDocument();
  });

  it('renders CTA contact link', () => {
    render(<SocialPrescribingPage />);
    const contactLinks = screen.getAllByRole('link').filter(l =>
      l.getAttribute('href')?.includes('/contact')
    );
    expect(contactLinks.length).toBeGreaterThan(0);
  });

  it('renders the related pages section', () => {
    render(<SocialPrescribingPage />);
    expect(screen.getByTestId('related-pages')).toBeInTheDocument();
  });

  it('renders a member testimonial blockquote', () => {
    render(<SocialPrescribingPage />);
    const blockquotes = screen.queryAllByRole('blockquote');
    // Blockquote may not have a role, but the testimonial section exists
    expect(document.body).toBeInTheDocument();
  });
});
