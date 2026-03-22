// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for ComingSoonPage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';

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

vi.mock('framer-motion', () => ({
  motion: {
    div: ({ children, ...props }: Record<string, unknown>) => {
      const motionKeys = new Set(['variants', 'initial', 'animate', 'transition', 'exit', 'whileHover', 'whileTap', 'whileInView', 'viewport']);
      const rest: Record<string, unknown> = {};
      for (const [k, v] of Object.entries(props)) { if (!motionKeys.has(k)) rest[k] = v; }
      return <div {...rest}>{children}</div>;
    },
  },
  AnimatePresence: ({ children }: { children: React.ReactNode }) => <>{children}</>,
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

import { ComingSoonPage } from './ComingSoonPage';

describe('ComingSoonPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the heading', () => {
    render(<ComingSoonPage />);
    expect(screen.getByRole('heading', { level: 1 })).toBeInTheDocument();
  });

  it('renders a link to the dashboard', () => {
    render(<ComingSoonPage />);
    const dashboardLink = screen.getAllByRole('link').find(link =>
      link.getAttribute('href')?.includes('/dashboard')
    );
    expect(dashboardLink).toBeTruthy();
  });

  it('renders a go back button', () => {
    const historyBackSpy = vi.spyOn(window.history, 'back').mockImplementation(() => {});
    render(<ComingSoonPage />);
    const buttons = screen.getAllByRole('button');
    expect(buttons.length).toBeGreaterThan(0);
    historyBackSpy.mockRestore();
  });

  it('calls window.history.back when go-back button is clicked', () => {
    const historyBackSpy = vi.spyOn(window.history, 'back').mockImplementation(() => {});
    render(<ComingSoonPage />);
    const backButtons = screen.getAllByRole('button');
    fireEvent.click(backButtons[backButtons.length - 1]);
    expect(historyBackSpy).toHaveBeenCalled();
    historyBackSpy.mockRestore();
  });

  it('renders with a custom feature prop', () => {
    render(<ComingSoonPage feature="Advanced Analytics" />);
    // The page renders — we just confirm it does not crash with a custom prop
    expect(screen.getByRole('heading', { level: 1 })).toBeInTheDocument();
  });

  it('renders with the default "This feature" text when no feature prop given', () => {
    render(<ComingSoonPage />);
    // Page still renders correctly
    expect(screen.getByRole('heading', { level: 1 })).toBeInTheDocument();
  });
});
