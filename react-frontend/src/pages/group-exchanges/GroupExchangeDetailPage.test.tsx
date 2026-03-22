// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for GroupExchangeDetailPage
 */

import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    delete: vi.fn(),
  },
}));
import { api } from '@/lib/api';

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 1, first_name: 'Alice', name: 'Alice Organizer' },
    isAuthenticated: true,
  })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, slug: 'test' },
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: vi.fn(() => true),
  })),
  useToast: vi.fn(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn() })),
  ToastProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,

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

vi.mock('@/contexts/ToastContext', () => ({
  useToast: vi.fn(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn() })),
  ToastProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: vi.fn((url) => url || '/default-avatar.png'),
}));

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    useParams: () => ({ id: '1' }),
    useNavigate: () => vi.fn(),
  };
});

vi.mock('framer-motion', async () => {
  const { framerMotionMock } = await import('@/test/mocks');
  return framerMotionMock;
});

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

vi.mock('@/components/navigation', () => ({
  Breadcrumbs: ({ items }: { items: { label: string }[] }) => (
    <nav>{items.map((i) => <span key={i.label}>{i.label}</span>)}</nav>
  ),
}));

vi.mock('@/components/feedback', () => ({
  LoadingScreen: ({ message }: { message: string }) => <div data-testid="loading-screen">{message}</div>,
  EmptyState: ({ title, description }: { title: string; description?: string }) => (
    <div data-testid="empty-state">
      <h2>{title}</h2>
      {description && <p>{description}</p>}
    </div>
  ),
}));

import { GroupExchangeDetailPage } from './GroupExchangeDetailPage';

const mockGroupExchange = {
  id: 1,
  title: 'Community Skills Swap',
  description: 'A group exchange for community skills',
  status: 'pending_participants',
  organizer_id: 1,
  organizer_name: 'Alice Organizer',
  organizer_avatar: null,
  split_type: 'equal',
  total_hours: 6,
  participants: [
    { user_id: 1, user_name: 'Alice Organizer', avatar: null, role: 'provider', hours: 3, weight: 1, confirmed: false },
    { user_id: 2, user_name: 'Bob Receiver', avatar: null, role: 'receiver', hours: 3, weight: 1, confirmed: false },
  ],
  created_at: '2026-01-15T10:00:00Z',
};

describe('GroupExchangeDetailPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    api.get.mockResolvedValue({ success: true, data: mockGroupExchange });
    api.post.mockResolvedValue({ success: true });
    api.delete.mockResolvedValue({ success: true });
  });

  it('shows loading screen initially', () => {
    api.get.mockImplementation(() => new Promise(() => {}));
    render(<GroupExchangeDetailPage />);
    expect(screen.getByTestId('loading-screen')).toBeInTheDocument();
  });

  it('renders group exchange title after load', async () => {
    render(<GroupExchangeDetailPage />);
    await waitFor(() => {
      expect(screen.getAllByText('Community Skills Swap').length).toBeGreaterThanOrEqual(1);
    });
  });

  it('renders organizer name', async () => {
    render(<GroupExchangeDetailPage />);
    await waitFor(() => {
      expect(screen.getAllByText(/Alice Organizer/i).length).toBeGreaterThanOrEqual(1);
    });
  });

  it('renders participant names in participants table', async () => {
    render(<GroupExchangeDetailPage />);
    await waitFor(() => {
      expect(screen.getAllByText(/Bob Receiver/i).length).toBeGreaterThanOrEqual(1);
    });
  });

  it('shows empty state on API error', async () => {
    api.get.mockRejectedValue(new Error('Not found'));
    render(<GroupExchangeDetailPage />);
    await waitFor(() => {
      expect(screen.getByTestId('empty-state')).toBeInTheDocument();
    });
  });

  it('renders split type information', async () => {
    render(<GroupExchangeDetailPage />);
    await waitFor(() => {
      // split_type 'equal' renders as 'Equal split'
      expect(screen.getAllByText(/Equal split/i).length).toBeGreaterThanOrEqual(1);
    });
  });

  it('renders total hours', async () => {
    render(<GroupExchangeDetailPage />);
    await waitFor(() => {
      expect(screen.getByText('6')).toBeInTheDocument();
    });
  });
});
