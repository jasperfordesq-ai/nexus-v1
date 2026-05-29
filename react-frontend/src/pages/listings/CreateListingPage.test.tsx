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
    listingConfig: {
      'listing.min_title_length': 5,
      'listing.min_description_length': 20,
      'listing.require_category': true,
      'listing.require_hours_estimate': false,
    },
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
  cn: (...classes: unknown[]) => classes.filter(Boolean).join(' '),
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

vi.mock('@/lib/motion', async () => {
  const { framerMotionMock } = await import('@/test/mocks');
  return framerMotionMock;
});

vi.mock('@/components/ui', async () => (await import('@/test/uiMock')).uiMock);

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
      expect(screen.getAllByText('Offer Help').length).toBeGreaterThan(0);
      expect(screen.getAllByText('Request Help').length).toBeGreaterThan(0);
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
    const { container } = render(<CreateListingPage />);
    await waitFor(() => screen.getByText(/Create New Listing/i));

    // The generic Button stub forces type="button", so submit the <form>
    // directly to exercise the real handleSubmit/validateForm path. The Input
    // stub does not render the `errorMessage` prop, so we assert the
    // behavioural consequence of validation failing on empty fields: the
    // create endpoint is never called.
    const form = container.querySelector('form') as HTMLFormElement;
    fireEvent.submit(form);

    await waitFor(() => {
      expect(api.post).not.toHaveBeenCalled();
    });
  });

  it('shows description validation error when too short', async () => {
    const { container } = render(<CreateListingPage />);
    await waitFor(() => screen.getByText(/Create New Listing/i));

    // Fill title (>=5 chars) — the Input stub forwards `placeholder`, not the
    // floating `label`, so query by placeholder. Leave description empty.
    const titleInput = screen.getByPlaceholderText(/grocery shopping/i);
    fireEvent.change(titleInput, { target: { value: 'Valid title here' } });

    // With a valid title but empty description, validation still fails so the
    // create endpoint must not be called.
    const form = container.querySelector('form') as HTMLFormElement;
    fireEvent.submit(form);

    await waitFor(() => {
      expect(api.post).not.toHaveBeenCalled();
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
