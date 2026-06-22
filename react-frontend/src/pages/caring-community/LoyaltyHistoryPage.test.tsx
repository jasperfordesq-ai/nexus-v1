// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { api } from '@/lib/api';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

vi.mock('@/contexts', () => ({
  useAuth: () => ({
    user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(),
    register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(),
    status: 'idle' as const, error: null,
  }),
  useTenant: () => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    branding: { name: 'Test Tenant' },
    tenantSlug: 'test',
    tenantPath: (p: string) => `/test${p}`,
    isLoading: false,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  }),
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }),
  useTheme: () => ({ resolvedTheme: 'light', theme: 'system', toggleTheme: vi.fn(), setTheme: vi.fn() }),
  useNotifications: () => ({
    unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(),
    markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn(),
  }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({
    consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(),
    saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn(),
  }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  usePresence: () => ({ status: 'offline', setStatus: vi.fn(), getPresence: vi.fn(), isOnline: vi.fn(() => false) }),
  usePresenceOptional: () => null,
}));

import { LoyaltyHistoryPage } from './LoyaltyHistoryPage';

// Real English strings from public/locales/en/common.json
const PAGE_TITLE = 'My Time Credit Redemptions';
const EMPTY_TITLE = 'No Redemptions Yet';
const ERROR_TEXT = 'Failed to load loyalty history';

const MOCK_ITEMS = [
  {
    id: 10,
    credits_used: 2.0,
    exchange_rate_chf: 10,
    discount_chf: 20.0,
    order_total_chf: 80.0,
    status: 'applied' as const,
    redeemed_at: '2026-05-15T14:00:00Z',
    merchant_id: 7,
    merchant_name: 'The Green Cafe',
    marketplace_listing_id: null,
    listing_title: null,
  },
  {
    id: 11,
    credits_used: 1.5,
    exchange_rate_chf: 10,
    discount_chf: 15.0,
    order_total_chf: 45.0,
    status: 'pending' as const,
    redeemed_at: '2026-06-01T10:00:00Z',
    merchant_id: 8,
    merchant_name: 'Book Nook',
    marketplace_listing_id: 42,
    listing_title: 'Python for Beginners',
  },
];

describe('LoyaltyHistoryPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows skeleton loaders while fetching', () => {
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    render(<LoyaltyHistoryPage />);
    // The page renders a <div role="status" aria-busy="true"> for the loading state.
    // (ToastProvider also emits a role="status" node — filter by aria-busy.)
    const statusNodes = screen.getAllByRole('status');
    const loadingNode = statusNodes.find((n) => n.getAttribute('aria-busy') === 'true');
    expect(loadingNode).toBeInTheDocument();
  });

  it('renders the page heading', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { items: MOCK_ITEMS },
    });
    render(<LoyaltyHistoryPage />);
    await waitFor(() => {
      expect(screen.getByRole('heading', { level: 1, name: PAGE_TITLE })).toBeInTheDocument();
    });
  });

  it('renders a table of redemptions when the API returns items', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url === '/v2/caring-community/loyalty/my-history') {
        return Promise.resolve({ success: true, data: { items: MOCK_ITEMS } });
      }
      return Promise.resolve({ success: true, data: { items: [] } });
    });

    render(<LoyaltyHistoryPage />);

    await waitFor(() => {
      expect(screen.getByText('The Green Cafe')).toBeInTheDocument();
    });
    expect(screen.getByText('Book Nook')).toBeInTheDocument();
  });

  it('renders a linked listing title when marketplace_listing_id is set', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { items: MOCK_ITEMS },
    });

    render(<LoyaltyHistoryPage />);

    await waitFor(() => {
      expect(screen.getByText('Python for Beginners')).toBeInTheDocument();
    });
    const link = screen.getByText('Python for Beginners').closest('a');
    expect(link).toBeInTheDocument();
  });

  it('renders the discount as a CHF amount chip', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { items: MOCK_ITEMS },
    });

    render(<LoyaltyHistoryPage />);

    await waitFor(() => {
      expect(screen.getByText('CHF 20.00')).toBeInTheDocument();
    });
  });

  it('shows empty state when items list is empty', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: { items: [] },
    });

    render(<LoyaltyHistoryPage />);

    await waitFor(() => {
      expect(screen.getByText(EMPTY_TITLE)).toBeInTheDocument();
    });
  });

  it('shows an error alert when the API returns a non-success response', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: false, data: null });

    render(<LoyaltyHistoryPage />);

    await waitFor(() => {
      // The page renders a <p role="alert"> with the error text.
      // (ToastProvider also renders a role="alert" portal — use text query.)
      expect(screen.getByText(ERROR_TEXT)).toBeInTheDocument();
    });
  });

  it('shows an error alert when the API call throws', async () => {
    vi.mocked(api.get).mockRejectedValue(new Error('timeout'));

    render(<LoyaltyHistoryPage />);

    await waitFor(() => {
      expect(screen.getByText(ERROR_TEXT)).toBeInTheDocument();
    });
  });
});
