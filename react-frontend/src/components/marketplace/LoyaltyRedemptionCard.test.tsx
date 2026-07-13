// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ─── Stable mock references (GOTCHA 1: never inline per-call) ───────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };
const mockUser = { id: 1, name: 'Alice' };

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    upload: vi.fn(),
  },
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({ user: mockUser, isAuthenticated: true, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle' as const, error: null }),
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: (f: string) => f === 'caring_community',
      hasModule: vi.fn(() => true),
    }),
  }),
);

// Stub out the HeroUI Slider and GlassCard/Chip so we don't need full HeroUI
vi.mock('@/components/ui/Slider', () => ({
  Slider: ({ value, onChange, 'aria-label': ariaLabel }: { value: number; onChange: (v: number) => void; 'aria-label': string }) => (
    <input
      type="range"
      aria-label={ariaLabel}
      value={value}
      onChange={(e) => onChange(Number(e.target.value))}
      data-testid="loyalty-slider"
    />
  ),
}));

vi.mock('@/components/ui/GlassCard', () => ({
  GlassCard: ({ children, className }: { children: React.ReactNode; className?: string }) => (
    <div data-testid="glass-card" className={className}>{children}</div>
  ),
}));

import { api } from '@/lib/api';
import { LoyaltyRedemptionCard } from './LoyaltyRedemptionCard';
import React from 'react';

const VALID_QUOTE = {
  accepts: true,
  member_credits: 5,
  exchange_rate_chf: 2,
  max_discount_pct: 20,
  max_credits_usable: 3,
  max_discount_chf: 6,
};

describe('LoyaltyRedemptionCard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders nothing while loading', () => {
    // Never resolves during this check
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    const { container } = render(
      <LoyaltyRedemptionCard sellerId={99} listingId={1} orderTotalChf={50} currency="CHF" />,
    );
    expect(screen.queryByText(/loyalty/i)).toBeNull();
    expect(container.querySelector('[data-testid="glass-card"]')).toBeNull();
  });

  it('renders nothing when user is the seller', async () => {
    // user.id === sellerId → validInputs = false
    render(
      <LoyaltyRedemptionCard sellerId={1} listingId={1} orderTotalChf={50} currency="CHF" />,
    );
    await waitFor(() => {
      expect(screen.queryByTestId('glass-card')).toBeNull();
    });
  });

  it('renders nothing when quote says accepts=false', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: { ...VALID_QUOTE, accepts: false } });
    render(
      <LoyaltyRedemptionCard sellerId={99} listingId={1} orderTotalChf={50} currency="CHF" />,
    );
    await waitFor(() => {
      expect(screen.queryByTestId('glass-card')).toBeNull();
    });
  });

  it('renders the card when quote is valid', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: VALID_QUOTE });
    render(
      <LoyaltyRedemptionCard sellerId={99} listingId={1} orderTotalChf={50} currency="CHF" />,
    );
    await waitFor(() => {
      expect(screen.getByTestId('loyalty-slider')).toBeInTheDocument();
    });
  });

  it('shows the apply button disabled when credits=0', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: VALID_QUOTE });
    render(
      <LoyaltyRedemptionCard sellerId={99} listingId={1} orderTotalChf={50} currency="CHF" />,
    );
    await waitFor(() => {
      expect(screen.getByTestId('loyalty-slider')).toBeInTheDocument();
    });
    // Button should be disabled (credits=0)
    const buttons = screen.getAllByRole('button');
    const applyBtn = buttons.find(b => b.hasAttribute('disabled') || b.getAttribute('aria-disabled') === 'true');
    expect(applyBtn).toBeDefined();
  });

  it('calls POST /v2/caring-community/loyalty/redeem on apply', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: VALID_QUOTE });
    vi.mocked(api.post).mockResolvedValueOnce({
      success: true,
      data: { discount_chf: 4, redemption_id: 77, new_wallet_balance: 1 },
    });

    render(
      <LoyaltyRedemptionCard sellerId={99} listingId={1} orderTotalChf={50} currency="CHF" />,
    );
    await waitFor(() => {
      expect(screen.getByTestId('loyalty-slider')).toBeInTheDocument();
    });

    // Move slider to a non-zero value (simulate onChange with value 2)
    const slider = screen.getByTestId('loyalty-slider');
    fireEvent.change(slider, { target: { value: '2' } });

    await waitFor(() => {
      const buttons = screen.getAllByRole('button');
      const applyBtn = buttons.find(b => !b.hasAttribute('disabled') && b.getAttribute('aria-disabled') !== 'true');
      if (applyBtn) fireEvent.click(applyBtn);
    });

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith(
        '/v2/caring-community/loyalty/redeem',
        expect.objectContaining({ seller_id: 99, listing_id: 1 }),
      );
    });
  });

  it('shows success state (confirmed view) after successful redemption', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: VALID_QUOTE });
    vi.mocked(api.post).mockResolvedValueOnce({
      success: true,
      data: { discount_chf: 4, redemption_id: 77, new_wallet_balance: 1 },
    });

    render(
      <LoyaltyRedemptionCard sellerId={99} listingId={1} orderTotalChf={50} currency="CHF" />,
    );
    await waitFor(() => expect(screen.getByTestId('loyalty-slider')).toBeInTheDocument());

    const slider = screen.getByTestId('loyalty-slider');
    fireEvent.change(slider, { target: { value: '2' } });

    // Click whichever button is now enabled
    await waitFor(async () => {
      const buttons = screen.getAllByRole('button');
      const applyBtn = buttons.find(b => !b.hasAttribute('disabled') && b.getAttribute('aria-disabled') !== 'true');
      if (applyBtn) fireEvent.click(applyBtn);
    });

    await waitFor(() => {
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('exposes the pending redemption and server-adjusted total to checkout', async () => {
    const onRedemptionChange = vi.fn();
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: VALID_QUOTE });
    vi.mocked(api.post).mockResolvedValueOnce({
      success: true,
      data: {
        discount_chf: 4,
        adjusted_total_chf: 46,
        redemption_id: 77,
      },
    });

    render(
      <LoyaltyRedemptionCard
        sellerId={99}
        listingId={1}
        orderTotalChf={50}
        currency="CHF"
        onRedemptionChange={onRedemptionChange}
      />,
    );
    await waitFor(() => expect(screen.getByTestId('loyalty-slider')).toBeInTheDocument());
    fireEvent.change(screen.getByTestId('loyalty-slider'), { target: { value: '2' } });
    const applyButton = screen.getAllByRole('button').find(
      (button) => !button.hasAttribute('disabled') && button.getAttribute('aria-disabled') !== 'true',
    );
    if (applyButton) fireEvent.click(applyButton);

    await waitFor(() => {
      expect(onRedemptionChange).toHaveBeenCalledWith({
        redemptionId: 77,
        discountChf: 4,
        adjustedTotalChf: 46,
      });
    });
  });

  it('shows error toast on redemption failure', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: true, data: VALID_QUOTE });
    vi.mocked(api.post).mockResolvedValueOnce({ success: false, error: 'Insufficient balance' });

    render(
      <LoyaltyRedemptionCard sellerId={99} listingId={1} orderTotalChf={50} currency="CHF" />,
    );
    await waitFor(() => expect(screen.getByTestId('loyalty-slider')).toBeInTheDocument());

    const slider = screen.getByTestId('loyalty-slider');
    fireEvent.change(slider, { target: { value: '2' } });

    await waitFor(async () => {
      const buttons = screen.getAllByRole('button');
      const applyBtn = buttons.find(b => !b.hasAttribute('disabled') && b.getAttribute('aria-disabled') !== 'true');
      if (applyBtn) fireEvent.click(applyBtn);
    });

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('renders nothing when quote fetch fails', async () => {
    vi.mocked(api.get).mockRejectedValueOnce(new Error('network'));
    render(
      <LoyaltyRedemptionCard sellerId={99} listingId={1} orderTotalChf={50} currency="CHF" />,
    );
    await waitFor(() => {
      expect(screen.queryByTestId('glass-card')).toBeNull();
    });
  });
});
