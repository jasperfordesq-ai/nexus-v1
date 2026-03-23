// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for RelatedPages component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import React from 'react';

const stableTenant = {
  tenant: { id: 2, name: 'Test Tenant', slug: 'hour-timebank' },
  branding: { name: 'Test Community' },
  tenantPath: (p: string) => `/test${p}`,
  hasFeature: vi.fn(() => true),
  hasModule: vi.fn(() => true),
  isLoading: false,
};

vi.mock('@/contexts', () => ({
  useTenant: vi.fn(() => stableTenant),
  useAuth: vi.fn(() => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null })),
  useToast: vi.fn(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() })),
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
vi.mock('@/components/ui', () => ({
  GlassCard: ({ children, className }: { children: React.ReactNode; className?: string }) => (
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

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    Link: ({ children, to }: { children: React.ReactNode; to: string }) => <a href={to}>{children}</a>,
  };
});

import { RelatedPages } from './RelatedPages';

describe('RelatedPages', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing', () => {
    const { container } = render(<RelatedPages current="/timebanking-guide" />);
    expect(container.querySelector('section')).toBeTruthy();
  });

  it('renders related pages heading', () => {
    render(<RelatedPages current="/timebanking-guide" />);
    // Heading renders as "Related Pages" from the about.json locale file
    expect(screen.getByText('Related Pages')).toBeInTheDocument();
  });

  it('excludes the current page from links', () => {
    render(<RelatedPages current="/timebanking-guide" />);
    // Should NOT show the Timebanking Guide link since it's the current page
    const links = screen.getAllByRole('link');
    const timebankingLink = links.find(
      (link) => link.getAttribute('href')?.includes('/timebanking-guide')
    );
    expect(timebankingLink).toBeUndefined();
  });

  it('renders hour-timebank specific links when tenant is hour-timebank', () => {
    render(<RelatedPages current="/timebanking-guide" />);
    // Should show the partner link (hour-timebank specific)
    const links = screen.getAllByRole('link');
    const partnerLink = links.find(
      (link) => link.getAttribute('href')?.includes('/partner')
    );
    expect(partnerLink).toBeTruthy();
  });

  it('renders links as GlassCards', () => {
    render(<RelatedPages current="/timebanking-guide" />);
    const cards = screen.getAllByTestId('glass-card');
    expect(cards.length).toBeGreaterThan(0);
  });
});
