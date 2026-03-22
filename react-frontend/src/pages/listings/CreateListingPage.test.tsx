// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for CreateListingPage
 */

import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
    upload: vi.fn(),
  },
}));
import { api } from '@/lib/api';

vi.mock('@/contexts', () => ({
  useToast: vi.fn(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn() })),
  useTenant: vi.fn(() => ({
    tenant: { id: 2, slug: 'test' },
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  })),
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
  useAuth: () => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null }),
}));

vi.mock('@/contexts/ToastContext', () => ({
  useToast: vi.fn(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn() })),
  ToastProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/lib/helpers', () => ({
  resolveAssetUrl: vi.fn((url) => url || null),
  resolveAvatarUrl: vi.fn((url) => url || '/default-avatar.png'),
}));

vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual('react-router-dom');
  return {
    ...actual,
    useParams: () => ({ id: undefined }),
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
    <nav aria-label="breadcrumb">{items.map((i) => <span key={i.label}>{i.label}</span>)}</nav>
  ),
}));

vi.mock('@/components/feedback', () => ({
  LoadingScreen: ({ message }: { message: string }) => <div data-testid="loading-screen">{message}</div>,
}));

vi.mock('@/components/location', () => ({
  PlaceAutocompleteInput: ({ label, value, onChange }: { label: string; value: string; onChange: (v: string) => void }) => (
    <input aria-label={label} value={value} onChange={(e) => onChange(e.target.value)} />
  ),
}));

import { CreateListingPage } from './CreateListingPage';

const mockCategories = [
  { id: 1, name: 'Technology', type: 'listing' },
  { id: 2, name: 'Gardening', type: 'listing' },
];

describe('CreateListingPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    api.get.mockResolvedValue({ success: true, data: mockCategories });
    api.post.mockResolvedValue({ success: true, data: { id: 42 } });
    api.put.mockResolvedValue({ success: true });
  });

  it('renders create listing form heading', async () => {
    render(<CreateListingPage />);
    await waitFor(() => {
      expect(screen.getAllByText(/Create New Listing/i).length).toBeGreaterThanOrEqual(1);
    });
  });

  it('renders offer and request radio options', async () => {
    render(<CreateListingPage />);
    await waitFor(() => {
      expect(screen.getByText('Offer Help')).toBeInTheDocument();
      expect(screen.getByText('Request Help')).toBeInTheDocument();
    });
  });

  it('shows create form (not edit mode) with no id param', async () => {
    api.get.mockImplementation(() => new Promise(() => {})); // never resolves
    render(<CreateListingPage />);
    // In create mode (no id), the form renders immediately (no loading screen)
    // The heading should be present
    expect(screen.queryByTestId('loading-screen')).not.toBeInTheDocument();
  });

  it('shows validation errors on empty submit', async () => {
    render(<CreateListingPage />);
    await waitFor(() => screen.getByText(/Create New Listing/i));

    const submitButton = screen.getByText('Create Listing');
    fireEvent.click(submitButton);

    await waitFor(() => {
      expect(screen.getByText('Title is required')).toBeInTheDocument();
    });
  });

  it('shows description validation error when too short', async () => {
    render(<CreateListingPage />);
    await waitFor(() => screen.getByText(/Create New Listing/i));

    // Fill title (>=5 chars)
    const titleInput = screen.getByRole('textbox', { name: /title/i });
    fireEvent.change(titleInput, { target: { value: 'Valid title here' } });

    const submitButton = screen.getByText('Create Listing');
    fireEvent.click(submitButton);

    await waitFor(() => {
      expect(screen.getByText('Description is required')).toBeInTheDocument();
    });
  });

  it('renders cancel button linking to listings', async () => {
    render(<CreateListingPage />);
    await waitFor(() => {
      expect(screen.getByText('Cancel')).toBeInTheDocument();
    });
  });

  it('renders image upload area', async () => {
    render(<CreateListingPage />);
    await waitFor(() => {
      expect(screen.getByText('Click to add a photo')).toBeInTheDocument();
    });
  });
});
