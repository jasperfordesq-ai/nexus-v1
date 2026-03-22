// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for RequestExchangePage
 */

import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
  },
}));
import { api } from '@/lib/api';

vi.mock('@/contexts', () => ({
  useAuth: vi.fn(() => ({
    user: { id: 99, first_name: 'Alice', name: 'Alice Test' },
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

vi.mock('@/lib/exchange-status', () => ({
  MAX_EXCHANGE_HOURS: 100,
}));

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    useParams: () => ({ id: '10' }),
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

import { RequestExchangePage } from './RequestExchangePage';

const mockListing = {
  id: 10,
  title: 'Piano Lessons',
  description: 'Learn piano from a professional',
  type: 'offer',
  user_id: 5,
  category_name: 'Music',
  hours_estimate: 2,
  status: 'active',
  user: { id: 5, name: 'Alice Teacher', avatar: null },
};

const mockConfig = {
  exchange_workflow_enabled: true,
  require_broker_approval: false,
};

describe('RequestExchangePage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    api.get.mockImplementation((url: string) => {
      if (url.includes('/config')) return Promise.resolve({ success: true, data: mockConfig });
      return Promise.resolve({ success: true, data: mockListing });
    });
    api.post.mockResolvedValue({ success: true, data: { id: 99 } });
  });

  it('shows loading screen initially', () => {
    api.get.mockImplementation(() => new Promise(() => {}));
    render(<RequestExchangePage />);
    expect(screen.getByTestId('loading-screen')).toBeInTheDocument();
  });

  it('renders listing title in summary card', async () => {
    render(<RequestExchangePage />);
    await waitFor(() => {
      expect(screen.getAllByText('Piano Lessons').length).toBeGreaterThanOrEqual(1);
    });
  });

  it('renders exchange request form heading', async () => {
    render(<RequestExchangePage />);
    await waitFor(() => {
      expect(screen.getAllByText('Request Exchange').length).toBeGreaterThanOrEqual(1);
    });
  });

  it('renders proposed hours input', async () => {
    render(<RequestExchangePage />);
    await waitFor(() => {
      expect(screen.getByRole('spinbutton', { name: /Proposed Hours/i })).toBeInTheDocument();
    });
  });

  it('renders send request button', async () => {
    render(<RequestExchangePage />);
    await waitFor(() => {
      expect(screen.getByText('Send Request')).toBeInTheDocument();
    });
  });

  it('renders cancel button', async () => {
    render(<RequestExchangePage />);
    await waitFor(() => {
      expect(screen.getAllByText('Cancel').length).toBeGreaterThanOrEqual(1);
    });
  });

  it('shows empty state when exchange workflow not enabled', async () => {
    api.get.mockImplementation((url: string) => {
      if (url.includes('/config')) {
        return Promise.resolve({ success: true, data: { exchange_workflow_enabled: false } });
      }
      return Promise.resolve({ success: true, data: mockListing });
    });
    render(<RequestExchangePage />);
    await waitFor(() => {
      expect(screen.getByTestId('empty-state')).toBeInTheDocument();
    });
  });

  it('shows empty state when own listing', async () => {
    // User id 99 matches listing user_id 99
    api.get.mockImplementation((url: string) => {
      if (url.includes('/config')) return Promise.resolve({ success: true, data: mockConfig });
      return Promise.resolve({ success: true, data: { ...mockListing, user_id: 99 } });
    });
    render(<RequestExchangePage />);
    await waitFor(() => {
      expect(screen.getByTestId('empty-state')).toBeInTheDocument();
    });
  });

  it('pre-fills proposed hours with listing estimate', async () => {
    render(<RequestExchangePage />);
    await waitFor(() => {
      const hoursInput = screen.getByRole('spinbutton', { name: /Proposed Hours/i }) as HTMLInputElement;
      expect(hoursInput.value).toBe('2');
    });
  });
});
