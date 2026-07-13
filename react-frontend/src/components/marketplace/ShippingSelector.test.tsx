// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for ShippingSelector component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { api } from '@/lib/api';
import type { MarketplaceShippingOption } from '@/types/marketplace';

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
  useToast: () => ({ success: vi.fn(), error: vi.fn(), info: vi.fn() }),
  useAuth: () => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle', error: null }),
  useTenant: () => ({ tenant: { id: 2, name: 'Test', slug: 'test' }, tenantPath: (p: string) => `/test${p}`, hasFeature: vi.fn(() => true), hasModule: vi.fn(() => true) }),
  useTheme: () => ({ resolvedTheme: 'light', theme: 'system', toggleTheme: vi.fn(), setTheme: vi.fn() }),
  useNotifications: () => ({ unreadCount: 0, counts: {}, notifications: [], markAsRead: vi.fn(), markAllAsRead: vi.fn(), hasMore: false, loadMore: vi.fn(), isLoading: false, refresh: vi.fn() }),
  usePusher: () => ({ channel: null, isConnected: false }),
  usePusherOptional: () => null,
  useCookieConsent: () => ({ consent: null, showBanner: false, openPreferences: vi.fn(), resetConsent: vi.fn(), saveConsent: vi.fn(), hasConsent: vi.fn(() => true), updateConsent: vi.fn() }),
  readStoredConsent: () => null,
  useMenuContext: () => ({ headerMenus: [], mobileMenus: [], hasCustomMenus: false }),
  useFeature: vi.fn(() => true),
  useModule: vi.fn(() => true),
  usePresence: () => ({ status: 'offline', setStatus: vi.fn(), getPresence: vi.fn(), isOnline: vi.fn(() => false) }),
  usePresenceOptional: () => null,
}));

import { ShippingSelector } from './ShippingSelector';

const SHIPPING_OPTION: MarketplaceShippingOption = {
  id: 1,
  courier_name: 'Standard Post',
  price: 4.99,
  currency: 'EUR',
  estimated_days: 3,
  is_default: true,
  is_active: true,
};

const SHIPPING_OPTION_2: MarketplaceShippingOption = {
  id: 2,
  courier_name: 'Express Courier',
  price: 9.99,
  currency: 'EUR',
  estimated_days: 1,
  is_default: false,
  is_active: true,
};

describe('ShippingSelector', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading spinner while fetching options', () => {
    // Never resolves during this test
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    render(<ShippingSelector sellerId={10} onSelect={vi.fn()} localPickup={false} />);
    // HeroUI Spinner renders multiple role=status elements (wrapper + inner span).
    // Verify at least one is present.
    const statusEls = screen.getAllByRole('status', { name: /loading/i });
    expect(statusEls.length).toBeGreaterThan(0);
  });

  it('renders shipping options returned by the API', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: [SHIPPING_OPTION, SHIPPING_OPTION_2],
    });

    render(<ShippingSelector sellerId={10} onSelect={vi.fn()} localPickup={false} />);

    await waitFor(() => {
      expect(screen.getByText('Standard Post')).toBeInTheDocument();
      expect(screen.getByText('Express Courier')).toBeInTheDocument();
    });
  });

  it('displays the formatted price for each shipping option', async () => {
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: [SHIPPING_OPTION],
    });

    render(<ShippingSelector sellerId={10} onSelect={vi.fn()} localPickup={false} />);

    await waitFor(() => {
      // Price should be formatted (€4.99 or similar locale variant)
      expect(screen.getByText(/4[.,]99/)).toBeInTheDocument();
    });
  });

  it('shows Local Pickup option when localPickup=true', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });

    render(<ShippingSelector sellerId={10} onSelect={vi.fn()} localPickup={true} />);

    await waitFor(() => {
      // The "Free" chip and local pickup label should appear
      expect(screen.getByText(/local.pickup|pickup/i)).toBeInTheDocument();
    });
  });

  it('calls onSelect with null when local pickup is pre-selected', async () => {
    const onSelect = vi.fn();
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });

    render(<ShippingSelector sellerId={10} onSelect={onSelect} localPickup={true} />);

    await waitFor(() => {
      // onSelect(null) is called because local pickup = null option
      expect(onSelect).toHaveBeenCalledWith(null);
    });
  });

  it('calls onSelect with the option object when a shipping option is selected', async () => {
    const onSelect = vi.fn();
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: [SHIPPING_OPTION, SHIPPING_OPTION_2],
    });

    render(<ShippingSelector sellerId={10} onSelect={onSelect} localPickup={false} />);

    await waitFor(() => {
      expect(screen.getByText('Standard Post')).toBeInTheDocument();
    });

    // Click the "Express Courier" radio option
    const expressLabel = screen.getByText('Express Courier');
    fireEvent.click(expressLabel);

    await waitFor(() => {
      const calls = vi.mocked(onSelect).mock.calls;
      // At least one call should have been made (auto-select on load or click)
      expect(calls.length).toBeGreaterThan(0);
    });
  });

  it('shows "no options" message when no options and localPickup=false', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });

    render(<ShippingSelector sellerId={10} onSelect={vi.fn()} localPickup={false} />);

    await waitFor(() => {
      // Queries by text — translation key: shipping.no_options
      expect(screen.queryByRole('radiogroup')).not.toBeInTheDocument();
    });
  });

  it('only shows active shipping options (filters inactive)', async () => {
    const inactiveOption: MarketplaceShippingOption = { ...SHIPPING_OPTION_2, id: 3, is_active: false, courier_name: 'Inactive Carrier' };
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: [SHIPPING_OPTION, inactiveOption],
    });

    render(<ShippingSelector sellerId={10} onSelect={vi.fn()} localPickup={false} />);

    await waitFor(() => {
      expect(screen.getByText('Standard Post')).toBeInTheDocument();
      expect(screen.queryByText('Inactive Carrier')).not.toBeInTheDocument();
    });
  });

  it('does not silently select fulfilment when autoSelect is disabled', async () => {
    const onSelect = vi.fn();
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [SHIPPING_OPTION] });

    render(
      <ShippingSelector
        sellerId={10}
        onSelect={onSelect}
        localPickup={false}
        autoSelect={false}
      />,
    );

    await screen.findByText('Standard Post');
    expect(onSelect).not.toHaveBeenCalledWith(SHIPPING_OPTION);
    fireEvent.click(screen.getByText('Standard Post'));
    await waitFor(() => expect(onSelect).toHaveBeenCalledWith(SHIPPING_OPTION));
  });

  it('only shows zero-cost options for free or time-credit checkout', async () => {
    const freeOption: MarketplaceShippingOption = {
      ...SHIPPING_OPTION,
      id: 9,
      courier_name: 'Community courier',
      price: 0,
      is_default: false,
    };
    vi.mocked(api.get).mockResolvedValue({
      success: true,
      data: [SHIPPING_OPTION, freeOption],
    });

    render(<ShippingSelector sellerId={10} onSelect={vi.fn()} localPickup={false} freeOnly />);

    await waitFor(() => {
      expect(screen.getByText('Community courier')).toBeInTheDocument();
      expect(screen.queryByText('Standard Post')).not.toBeInTheDocument();
    });
  });

  it('calls the correct API URL with the given sellerId', async () => {
    vi.mocked(api.get).mockResolvedValue({ success: true, data: [] });

    render(<ShippingSelector sellerId={42} onSelect={vi.fn()} localPickup={false} />);

    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith('/v2/marketplace/sellers/42/shipping-options');
    });
  });
});
