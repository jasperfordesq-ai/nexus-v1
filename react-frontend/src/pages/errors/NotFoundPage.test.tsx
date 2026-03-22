// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for NotFoundPage
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

import { NotFoundPage } from './NotFoundPage';

describe('NotFoundPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the 404 text', () => {
    render(<NotFoundPage />);
    expect(screen.getByText('404')).toBeInTheDocument();
  });

  it('renders a page heading', () => {
    render(<NotFoundPage />);
    expect(screen.getByRole('heading', { level: 1 })).toBeInTheDocument();
  });

  it('renders a link to go home', () => {
    render(<NotFoundPage />);
    const homeLink = screen.getAllByRole('link').find(link =>
      link.getAttribute('href')?.includes('/')
    );
    expect(homeLink).toBeTruthy();
  });

  it('renders a link to the search page', () => {
    render(<NotFoundPage />);
    const searchLink = screen.getAllByRole('link').find(link =>
      link.getAttribute('href')?.includes('/search')
    );
    expect(searchLink).toBeTruthy();
  });

  it('renders a go back button', () => {
    // window.history.back spy
    const historyBackSpy = vi.spyOn(window.history, 'back').mockImplementation(() => {});
    render(<NotFoundPage />);
    // The go back button is a HeroUI Button (role=button) with aria label or text
    const buttons = screen.getAllByRole('button');
    expect(buttons.length).toBeGreaterThan(0);
    historyBackSpy.mockRestore();
  });

  it('calls window.history.back when the go-back button is clicked', () => {
    const historyBackSpy = vi.spyOn(window.history, 'back').mockImplementation(() => {});
    render(<NotFoundPage />);
    // Find the back button by text content
    const backButtons = screen.getAllByRole('button');
    // Click the last button (go back)
    fireEvent.click(backButtons[backButtons.length - 1]);
    expect(historyBackSpy).toHaveBeenCalled();
    historyBackSpy.mockRestore();
  });
});
