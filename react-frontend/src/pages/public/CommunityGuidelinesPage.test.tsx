// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for CommunityGuidelinesPage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import React from 'react';

const mockUseLegalDocument = vi.fn();

vi.mock('@/hooks/useLegalDocument', () => ({
  useLegalDocument: (...args: unknown[]) => mockUseLegalDocument(...args),
}));

vi.mock('@/components/legal/CustomLegalDocument', () => ({
  CustomLegalDocument: ({ document }: { document: { title: string } }) => (
    <div data-testid="custom-legal-doc">{document.title}</div>
  ),
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
  useAuth: vi.fn(() => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null })),
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

import { CommunityGuidelinesPage } from './CommunityGuidelinesPage';

describe('CommunityGuidelinesPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders spinner while loading', () => {
    mockUseLegalDocument.mockReturnValue({ document: null, loading: true });
    const { container } = render(<CommunityGuidelinesPage />);
    expect(container.querySelector('div')).toBeTruthy();
  });

  it('renders custom document when available', () => {
    mockUseLegalDocument.mockReturnValue({
      document: { title: 'Custom Guidelines', content: '<p>Custom content</p>' },
      loading: false,
    });
    render(<CommunityGuidelinesPage />);
    expect(screen.getByTestId('custom-legal-doc')).toBeInTheDocument();
    expect(screen.getByText('Custom Guidelines')).toBeInTheDocument();
  });

  it('renders placeholder heading when no custom document', () => {
    mockUseLegalDocument.mockReturnValue({ document: null, loading: false });
    render(<CommunityGuidelinesPage />);
    expect(screen.getByText('Community Guidelines')).toBeInTheDocument();
  });

  it('renders not-available message when no custom document', () => {
    mockUseLegalDocument.mockReturnValue({ document: null, loading: false });
    render(<CommunityGuidelinesPage />);
    expect(screen.getByText(/community guidelines have not been published yet/i)).toBeInTheDocument();
  });

  it('renders Contact Us and All Legal Documents links', () => {
    mockUseLegalDocument.mockReturnValue({ document: null, loading: false });
    render(<CommunityGuidelinesPage />);
    expect(screen.getByText('Contact Us')).toBeInTheDocument();
    expect(screen.getByText('All Legal Documents')).toBeInTheDocument();
  });
});
