// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { fireEvent, render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ── mock api (hoisted so vi.mocked() sees the same ref) ──────────────────────
const mockApi = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  put: vi.fn(),
  patch: vi.fn(),
  delete: vi.fn(),
}));

vi.mock('@/lib/api', () => ({ default: mockApi, api: mockApi }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ── mock contexts ─────────────────────────────────────────────────────────────
const mockToast = vi.hoisted(() => ({
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
}));
const mockHasFeature = vi.hoisted(() => vi.fn(() => false)); // coupons OFF by default

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
    useAuth: () => ({
      user: { id: 99, name: 'Buyer' } as any,
      isAuthenticated: true,
      login: vi.fn(), logout: vi.fn(), register: vi.fn(),
      updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle' as const, error: null,
    }),
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: mockHasFeature,
      hasModule: vi.fn(() => true),
    }),
  }),
);

// ── mock StripeCheckoutModal ──────────────────────────────────────────────────
vi.mock('./StripeCheckoutModal', () => ({
  StripeCheckoutModal: ({ isOpen, amount }: { isOpen: boolean; amount: number }) =>
    isOpen ? <div data-testid="stripe-modal">Amount {amount}</div> : null,
}));

import { BuyNowButton } from './BuyNowButton';

const BASE_PROPS = {
  listingId: 42,
  listingTitle: 'Cool Widget',
  price: 29.99,
  currency: 'EUR',
  sellerId: 1,   // different from buyer (id=99)
  onSuccess: vi.fn(),
};

describe('BuyNowButton', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockHasFeature.mockReturnValue(false);
    // Default: no pickup slots
    mockApi.get.mockResolvedValue({ success: true, data: [] });
  });

  // ── rendering ─────────────────────────────────────────────────────────────

  it('renders a Buy Now button', async () => {
    render(<BuyNowButton {...BASE_PROPS} />);
    await waitFor(() => {
      expect(screen.getByRole('button', { name: /buy now/i })).toBeInTheDocument();
    });
  });

  it('shows the formatted price in the button', async () => {
    render(<BuyNowButton {...BASE_PROPS} price={29.99} currency="EUR" />);
    await waitFor(() => {
      const btn = screen.getByRole('button', { name: /buy now/i });
      expect(btn.textContent).toMatch(/29/);
    });
  });

  it('is disabled when the viewer is the seller (sellerId === user.id)', async () => {
    // Re-render with sellerId matching the user.id in mock (99)
    render(<BuyNowButton {...BASE_PROPS} sellerId={99} />);
    await waitFor(() => {
      const btn = screen.getByRole('button', { name: /buy now/i });
      const isDisabled =
        btn.hasAttribute('disabled') ||
        btn.getAttribute('aria-disabled') === 'true' ||
        btn.hasAttribute('data-disabled');
      expect(isDisabled).toBe(true);
    });
  });

  // ── loading pickup slots ──────────────────────────────────────────────────

  it('fetches pickup slots on mount', async () => {
    render(<BuyNowButton {...BASE_PROPS} />);
    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledWith(
        `/v2/marketplace/listings/${BASE_PROPS.listingId}/pickup-slots`,
      );
    });
  });

  it('shows pickup slot selector when slots are returned', async () => {
    mockApi.get.mockResolvedValueOnce({
      success: true,
      data: [{ id: 5, slot_start: '2025-07-01T10:00:00Z', slot_end: null, remaining: 3 }],
    });

    render(<BuyNowButton {...BASE_PROPS} />);

    // HeroUI Select renders a button trigger (not role=combobox in all versions)
    await waitFor(() => {
      // The select trigger should appear — look for any button or trigger
      const buttons = screen.getAllByRole('button');
      // There should be at least 2 buttons: the pickup slot selector + buy now
      expect(buttons.length).toBeGreaterThanOrEqual(2);
    });
  });

  // ── happy path purchase ───────────────────────────────────────────────────

  it('creates order then payment intent and redirects to checkout_url', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: [] });
    mockApi.post
      .mockResolvedValueOnce({ success: true, data: { id: 10, order_number: 'ORD-001', status: 'pending' } })
      .mockResolvedValueOnce({ success: true, data: { checkout_url: 'https://stripe.com/pay/123' } });

    const origLocation = window.location;
    // @ts-expect-error jsdom workaround
    delete window.location;
    window.location = { href: '' } as Location;

    const user = userEvent.setup();
    render(<BuyNowButton {...BASE_PROPS} />);
    await waitFor(() => screen.getByRole('button', { name: /buy now/i }));

    await user.click(screen.getByRole('button', { name: /buy now/i }));

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith('/v2/marketplace/orders', expect.objectContaining({ listing_id: 42 }));
      expect(mockApi.post).toHaveBeenCalledWith('/v2/marketplace/payments/create-intent', expect.objectContaining({ order_id: 10 }));
    });

    expect(window.location.href).toBe('https://stripe.com/pay/123');
    // @ts-expect-error restore
    window.location = origLocation;
  });

  it.each([
    ['free', 0],
    ['time_credits', 2],
  ] as const)('completes %s checkout without creating a Stripe intent', async (paymentMethod, timeCredits) => {
    const onSuccess = vi.fn();
    mockApi.post.mockResolvedValueOnce({
      success: true,
      data: { id: 12, order_number: 'ORD-012', status: 'paid', requires_payment: false },
    });

    const user = userEvent.setup();
    render(
      <BuyNowButton
        {...BASE_PROPS}
        price={0}
        paymentMethod={paymentMethod}
        timeCredits={timeCredits}
        onSuccess={onSuccess}
      />,
    );
    await user.click(await screen.findByRole('button', { name: /buy now/i }));

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith('/v2/marketplace/orders', expect.objectContaining({
        listing_id: 42,
        payment_method: paymentMethod,
      }));
      expect(onSuccess).toHaveBeenCalledOnce();
    });
    expect(mockApi.post).toHaveBeenCalledTimes(1);
  });

  it('includes accepted offer id when checking out an accepted offer', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: [] });
    mockApi.post
      .mockResolvedValueOnce({ success: true, data: { id: 14, order_number: 'ORD-014', status: 'pending' } })
      .mockResolvedValueOnce({ success: true, data: {} });

    const user = userEvent.setup();
    render(<BuyNowButton {...BASE_PROPS} offerId={77} allowCoupons={false} />);
    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledWith(
        `/v2/marketplace/listings/${BASE_PROPS.listingId}/pickup-slots?offer_id=77`,
      );
      expect(screen.getByRole('button', { name: /buy now/i })).toBeInTheDocument();
    });

    await user.click(screen.getByRole('button', { name: /buy now/i }));

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith('/v2/marketplace/orders', expect.objectContaining({
        listing_id: 42,
        offer_id: 77,
      }));
    });
  });

  it('sends the selected shipping option id without a client-controlled shipping cost', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: [] });
    mockApi.post
      .mockResolvedValueOnce({ success: true, data: { id: 15, order_number: 'ORD-015', status: 'pending' } })
      .mockResolvedValueOnce({ success: true, data: {} });

    const user = userEvent.setup();
    render(
      <BuyNowButton
        {...BASE_PROPS}
        selectedShippingOption={{
          id: 3,
          courier_name: 'Express Courier',
          price: 5,
          currency: 'EUR',
          is_default: false,
          is_active: true,
        }}
        shippingRequired
      />
    );

    await waitFor(() => {
      expect(screen.getByText(/shipping/i)).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /34/ })).toBeInTheDocument();
    });

    await user.click(screen.getByRole('button', { name: /buy now/i }));

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith('/v2/marketplace/orders', expect.objectContaining({
        shipping_option_id: 3,
      }));
      const orderPayload = mockApi.post.mock.calls.find(
        ([url]) => url === '/v2/marketplace/orders',
      )?.[1];
      expect(orderPayload).not.toHaveProperty('shipping_cost');
      expect(orderPayload).not.toHaveProperty('shipping_method');
    });
  });

  it('normalizes decimal strings before calculating the displayed total', async () => {
    render(
      <BuyNowButton
        {...BASE_PROPS}
        price={'25.00' as unknown as number}
        selectedShippingOption={{
          id: 3,
          courier_name: 'Courier',
          price: '5.00' as unknown as number,
          currency: 'EUR',
          is_default: false,
          is_active: true,
        }}
        shippingRequired
      />,
    );

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /30/ })).toBeInTheDocument();
      expect(screen.queryByText(/NaN/)).not.toBeInTheDocument();
    });
  });

  it('attaches a pending loyalty redemption and displays its adjusted total', async () => {
    mockApi.post
      .mockResolvedValueOnce({ success: true, data: { id: 18, order_number: 'ORD-018', status: 'pending' } })
      .mockResolvedValueOnce({ success: true, data: {} });

    const user = userEvent.setup();
    render(
      <BuyNowButton
        {...BASE_PROPS}
        loyaltyRedemptionId={77}
        loyaltyDiscount={4}
      />,
    );

    await waitFor(() => expect(screen.getByRole('button', { name: /25/ })).toBeInTheDocument());
    await user.click(screen.getByRole('button', { name: /buy now/i }));

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith('/v2/marketplace/orders', expect.objectContaining({
        loyalty_redemption_id: 77,
      }));
    });
  });

  it('reuses one idempotency key when order creation is retried', async () => {
    mockApi.post
      .mockResolvedValueOnce({ success: false, error: 'Temporary failure' })
      .mockResolvedValueOnce({ success: false, error: 'Temporary failure' });

    const user = userEvent.setup();
    render(<BuyNowButton {...BASE_PROPS} />);
    const buyButton = await screen.findByRole('button', { name: /buy now/i });
    await user.click(buyButton);
    await waitFor(() => expect(mockApi.post).toHaveBeenCalledTimes(1));
    await user.click(buyButton);
    await waitFor(() => expect(mockApi.post).toHaveBeenCalledTimes(2));

    const firstKey = mockApi.post.mock.calls[0][1].idempotency_key;
    const secondKey = mockApi.post.mock.calls[1][1].idempotency_key;
    expect(typeof firstKey).toBe('string');
    expect(firstKey.length).toBeGreaterThanOrEqual(16);
    expect(secondKey).toBe(firstKey);
  });

  it('submits the pickup slot atomically and blocks payment when order creation fails', async () => {
    mockApi.get.mockResolvedValueOnce({
      success: true,
      data: [{ id: 5, slot_start: '2025-07-01T10:00:00Z', slot_end: null, remaining: 3 }],
    });
    mockApi.post.mockResolvedValueOnce({ success: false, error: 'Slot full' });

    const user = userEvent.setup();
    render(<BuyNowButton {...BASE_PROPS} />);

    await waitFor(() => {
      expect(document.querySelector('select option[value="5"]')).not.toBeNull();
    });
    fireEvent.change(document.querySelector('select') as HTMLSelectElement, { target: { value: '5' } });
    await user.click(screen.getByRole('button', { name: /buy now/i }));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalledWith('Slot full');
      expect(mockApi.post).toHaveBeenCalledWith(
        '/v2/marketplace/orders',
        expect.objectContaining({
          shipping_method: 'pickup',
          pickup_slot_id: 5,
        }),
      );
    });
    expect(mockApi.post).toHaveBeenCalledTimes(1);
    expect(mockApi.post).not.toHaveBeenCalledWith(
      '/v2/marketplace/payments/create-intent',
      expect.anything(),
    );
  });

  it('shows coupon discount in the checkout total', async () => {
    mockHasFeature.mockReturnValue(true);
    mockApi.get.mockResolvedValue({ success: true, data: [] });
    mockApi.post.mockResolvedValueOnce({
      success: true,
      data: { discount_amount: 5, discount_cents: 500, currency: 'EUR' },
    });

    const user = userEvent.setup();
    render(<BuyNowButton {...BASE_PROPS} />);

    await user.type(screen.getByPlaceholderText(/coupon/i), 'SAVE5');
    await user.click(screen.getByRole('button', { name: /apply/i }));

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith('/v2/coupons/validate', {
        code: 'SAVE5',
        listing_id: 42,
      });
      expect(screen.getByText(/discount/i)).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /24/ })).toBeInTheDocument();
    });
  });

  it('uses the major-unit coupon quote for a zero-decimal currency', async () => {
    mockHasFeature.mockReturnValue(true);
    mockApi.get.mockResolvedValue({ success: true, data: [] });
    mockApi.post.mockResolvedValueOnce({
      success: true,
      data: { discount_amount: 100, discount_cents: 100, currency: 'JPY' },
    });

    const user = userEvent.setup();
    render(<BuyNowButton {...BASE_PROPS} price={1000} currency="JPY" />);

    await user.type(screen.getByPlaceholderText(/coupon/i), 'SAVE10');
    await user.click(screen.getByRole('button', { name: /apply/i }));

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /900/ })).toBeInTheDocument();
    });
  });

  it('opens Stripe checkout modal when client_secret is returned', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: [] });
    mockApi.post
      .mockResolvedValueOnce({ success: true, data: { id: 11, order_number: 'ORD-002', status: 'pending' } })
      .mockResolvedValueOnce({ success: true, data: { client_secret: 'pi_secret_abc' } });

    const user = userEvent.setup();
    render(<BuyNowButton {...BASE_PROPS} />);
    await waitFor(() => screen.getByRole('button', { name: /buy now/i }));

    await user.click(screen.getByRole('button', { name: /buy now/i }));

    await waitFor(() => {
      expect(screen.getByTestId('stripe-modal')).toBeInTheDocument();
    });
  });

  // ── error cases ───────────────────────────────────────────────────────────

  it('shows error toast when order creation fails', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: [] });
    mockApi.post.mockResolvedValueOnce({ success: false, error: 'Out of stock' });

    const user = userEvent.setup();
    render(<BuyNowButton {...BASE_PROPS} />);
    await waitFor(() => screen.getByRole('button', { name: /buy now/i }));

    await user.click(screen.getByRole('button', { name: /buy now/i }));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('shows error toast when payment intent creation fails', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: [] });
    mockApi.post
      .mockResolvedValueOnce({ success: true, data: { id: 12, order_number: 'ORD-003', status: 'pending' } })
      .mockResolvedValueOnce({ success: false, error: 'Stripe error' });

    const user = userEvent.setup();
    render(<BuyNowButton {...BASE_PROPS} />);
    await waitFor(() => screen.getByRole('button', { name: /buy now/i }));

    await user.click(screen.getByRole('button', { name: /buy now/i }));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('shows info toast and calls onSuccess when no checkout_url or client_secret', async () => {
    mockApi.get.mockResolvedValue({ success: true, data: [] });
    mockApi.post
      .mockResolvedValueOnce({ success: true, data: { id: 13, order_number: 'ORD-004', status: 'pending' } })
      .mockResolvedValueOnce({ success: true, data: {} }); // no checkout_url, no client_secret

    const user = userEvent.setup();
    render(<BuyNowButton {...BASE_PROPS} />);
    await waitFor(() => screen.getByRole('button', { name: /buy now/i }));

    await user.click(screen.getByRole('button', { name: /buy now/i }));

    await waitFor(() => {
      expect(mockToast.info).toHaveBeenCalled();
      expect(BASE_PROPS.onSuccess).toHaveBeenCalled();
    });
  });
});
