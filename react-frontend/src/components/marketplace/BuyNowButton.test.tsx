// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
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
  StripeCheckoutModal: ({ isOpen }: { isOpen: boolean }) =>
    isOpen ? <div data-testid="stripe-modal" /> : null,
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
