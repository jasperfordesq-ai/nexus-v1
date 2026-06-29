// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for PricingPage
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';

// vi.mock factories are hoisted — use vi.hoisted() for variables shared with factories.
const { mockGet, mockPost, mockShowToast, mockHasFeature, mockTenantPath } = vi.hoisted(() => ({
  mockGet: vi.fn(),
  mockPost: vi.fn(),
  mockShowToast: vi.fn(),
  mockHasFeature: vi.fn(() => true),
  mockTenantPath: (p: string) => `/test${p}`,
}));

vi.mock('@/components/donations/DonationCheckout', () => ({
  DonationCheckout: ({ isOpen }: { isOpen: boolean }) => (
    isOpen ? <div data-testid="donation-checkout">One-off donation checkout</div> : null
  ),
}));

vi.mock('@/lib/api', () => ({
  api: {
    get: mockGet,
    post: mockPost,
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
  default: {
    get: mockGet,
    post: mockPost,
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/lib/logger', () => ({
  logError: vi.fn(),
}));

vi.mock('@/hooks', () => ({
  usePageTitle: vi.fn(),
}));

vi.mock('@/contexts', () => ({
  useToast: () => ({
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn(),
    warning: vi.fn(),
    showToast: mockShowToast,
  }),
  useAuth: () => ({
    user: { id: 1, name: 'User' },
    isAuthenticated: true,
    login: vi.fn(),
    logout: vi.fn(),
    register: vi.fn(),
    updateUser: vi.fn(),
    refreshUser: vi.fn(),
    status: 'idle' as const,
    error: null,
  }),
  useTenant: () => ({
    tenant: { id: 2, name: 'Test', slug: 'test' },
    tenantPath: mockTenantPath,
    hasFeature: mockHasFeature,
    hasModule: vi.fn(() => true),
  }),
  useTheme: () => ({ resolvedTheme: 'light', theme: 'system', toggleTheme: vi.fn(), setTheme: vi.fn() }),
  useNotifications: () => ({
    unreadCount: 0,
    counts: {},
    notifications: [],
    markAsRead: vi.fn(),
    markAllAsRead: vi.fn(),
    hasMore: false,
    loadMore: vi.fn(),
    isLoading: false,
    refresh: vi.fn(),
  }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({
    consent: null,
    showBanner: false,
    openPreferences: vi.fn(),
    resetConsent: vi.fn(),
    saveConsent: vi.fn(),
    hasConsent: vi.fn(() => true),
    updateConsent: vi.fn(),
  }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  usePresence: () => ({ status: 'offline', setStatus: vi.fn(), getPresence: vi.fn(), isOnline: vi.fn(() => false) }),
  usePresenceOptional: () => null,
}));

// Stub window.location.href to prevent real navigation in tests
Object.defineProperty(window, 'location', {
  value: { href: 'http://localhost/', origin: 'http://localhost', assign: vi.fn(), replace: vi.fn() },
  configurable: true,
  writable: true,
});

import { PricingPage } from './PricingPage';

const TIER_BASIC = {
  id: 1,
  slug: 'basic',
  name: 'Basic',
  description: 'Essential features for individuals',
  monthly_price_cents: 500,
  yearly_price_cents: 4800,
  features: ['feature_a', 'feature_b'],
  sort_order: 1,
  is_active: true,
};

const TIER_PRO = {
  id: 2,
  slug: 'pro',
  name: 'Pro',
  description: 'Everything in Basic plus more',
  monthly_price_cents: 1500,
  yearly_price_cents: 14400,
  features: ['feature_a', 'feature_b', 'feature_c'],
  sort_order: 2,
  is_active: true,
};

const TIER_FREE = {
  id: 3,
  slug: 'free',
  name: 'Free Tier',
  description: null,
  monthly_price_cents: 0,
  yearly_price_cents: 0,
  features: [],
  sort_order: 0,
  is_active: true,
};

describe('PricingPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockHasFeature.mockReturnValue(true);
    window.location.href = 'http://localhost/';
  });

  it('shows loading spinner while fetching tiers', () => {
    mockGet.mockReturnValue(new Promise(() => {}));
    render(<PricingPage />);
    // HeroUI Spinner renders multiple role=status elements
    expect(screen.getAllByRole('status').length).toBeGreaterThan(0);
  });

  it('renders tier names after loading', async () => {
    mockGet.mockResolvedValue({ success: true, data: { tiers: [TIER_BASIC, TIER_PRO] } });
    render(<PricingPage />);

    await waitFor(() => {
      expect(screen.getByText('Basic')).toBeInTheDocument();
      expect(screen.getByText('Pro')).toBeInTheDocument();
    });
  });

  it('presents the module as community donations', async () => {
    mockGet.mockResolvedValue({ success: true, data: { tiers: [TIER_BASIC] } });
    render(<PricingPage />);

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: 'Donate' })).toBeInTheDocument();
      expect(screen.getByText('Support this community')).toBeInTheDocument();
    });
  });

  it('opens one-off donation checkout from the support page', async () => {
    mockGet.mockResolvedValue({ success: true, data: { tiers: [TIER_BASIC] } });
    render(<PricingPage />);

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /make a one-off donation/i })).toBeInTheDocument();
    });

    fireEvent.click(screen.getByRole('button', { name: /make a one-off donation/i }));

    expect(screen.getByTestId('donation-checkout')).toBeInTheDocument();
  });

  it('renders tier descriptions', async () => {
    mockGet.mockResolvedValue({ success: true, data: { tiers: [TIER_BASIC] } });
    render(<PricingPage />);

    await waitFor(() => {
      expect(screen.getByText('Essential features for individuals')).toBeInTheDocument();
    });
  });

  it('shows monthly price by default (500 cents = ~€5)', async () => {
    mockGet.mockResolvedValue({ success: true, data: { tiers: [TIER_BASIC] } });
    render(<PricingPage />);

    await waitFor(() => {
      // "€5" or "5" appears in the formatted price
      const els = screen.getAllByText(/€?5([.,]00)?/);
      expect(els.length).toBeGreaterThan(0);
    });
  });

  it('shows "Free" label for zero-price tier', async () => {
    mockGet.mockResolvedValue({ success: true, data: { tiers: [TIER_FREE] } });
    render(<PricingPage />);

    await waitFor(() => {
      expect(screen.getByText('Free Tier')).toBeInTheDocument();
      // The "Free" pricing label from i18n key premium.free
      const freeEls = screen.getAllByText(/free/i);
      expect(freeEls.length).toBeGreaterThan(0);
    });
  });

  it('shows "no tiers" empty state when tiers array is empty', async () => {
    mockGet.mockResolvedValue({ success: true, data: { tiers: [] } });
    render(<PricingPage />);

    await waitFor(() => {
      expect(screen.queryByRole('button', { name: /subscribe/i })).not.toBeInTheDocument();
    });
  });

  it('does not render tiers when member_premium feature is disabled', () => {
    mockHasFeature.mockReturnValue(false);
    render(<PricingPage />);
    // Feature disabled — tiers section not shown
    expect(screen.queryByText('Basic')).not.toBeInTheDocument();
  });

  it('renders a regular donation button for each tier', async () => {
    mockGet.mockResolvedValue({ success: true, data: { tiers: [TIER_BASIC, TIER_PRO] } });
    render(<PricingPage />);

    await waitFor(() => {
      const subscribeBtns = screen.getAllByRole('button', { name: /donate regularly/i });
      expect(subscribeBtns).toHaveLength(2);
    });
  });

  it('calls checkout API and redirects to checkout_url on regular donation', async () => {
    mockGet.mockResolvedValue({ success: true, data: { tiers: [TIER_BASIC] } });
    mockPost.mockResolvedValue({
      success: true,
      data: { checkout_url: 'https://stripe.com/checkout/session_123', session_id: 'session_123' },
    });

    render(<PricingPage />);

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /donate regularly/i })).toBeInTheDocument();
    });

    fireEvent.click(screen.getByRole('button', { name: /donate regularly/i }));

    await waitFor(() => {
      expect(mockPost).toHaveBeenCalledWith(
        '/v2/member-premium/checkout',
        expect.objectContaining({ tier_id: 1, interval: 'monthly' })
      );
    });

    expect(window.location.href).toBe('https://stripe.com/checkout/session_123');
  });

  it('shows a billing interval toggle (monthly/yearly switch)', async () => {
    mockGet.mockResolvedValue({ success: true, data: { tiers: [TIER_BASIC] } });
    render(<PricingPage />);

    await waitFor(() => {
      expect(screen.getByRole('switch', { name: /billing|toggle/i })).toBeInTheDocument();
    });
  });

  it('renders yearly price when yearly interval is selected', async () => {
    mockGet.mockResolvedValue({ success: true, data: { tiers: [TIER_BASIC] } });
    render(<PricingPage />);

    await waitFor(() => {
      expect(screen.getByRole('switch', { name: /billing|toggle/i })).toBeInTheDocument();
    });

    fireEvent.click(screen.getByRole('switch', { name: /billing|toggle/i }));

    await waitFor(() => {
      // Yearly price for TIER_BASIC is 4800 cents = €48
      const els = screen.getAllByText(/48/);
      expect(els.length).toBeGreaterThan(0);
    });
  });

  it('shows an error toast when checkout POST fails', async () => {
    mockGet.mockResolvedValue({ success: true, data: { tiers: [TIER_BASIC] } });
    mockPost.mockRejectedValue(new Error('Network error'));

    render(<PricingPage />);

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /donate regularly/i })).toBeInTheDocument();
    });

    fireEvent.click(screen.getByRole('button', { name: /donate regularly/i }));

    await waitFor(() => {
      expect(mockShowToast).toHaveBeenCalled();
    });
  });
});
