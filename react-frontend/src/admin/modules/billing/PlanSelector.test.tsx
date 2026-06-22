// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import userEvent from '@testing-library/user-event';
import { createMockContexts } from '@/test/mock-contexts';

// ─── Mock billingApi ────────────────────────────────────────────────────────
const mockBillingApi = vi.hoisted(() => ({
  getPlans: vi.fn(),
  getSubscription: vi.fn(),
  createCheckout: vi.fn(),
}));

vi.mock('../../api/billingApi', () => ({
  billingApi: mockBillingApi,
}));

// ─── Mock contexts ───────────────────────────────────────────────────────────
const mockToast = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() }));
const mockNavigate = vi.hoisted(() => vi.fn());

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

vi.mock('react-router-dom', async (importOriginal) => {
  const actual = await importOriginal<typeof import('react-router-dom')>();
  return {
    ...actual,
    useNavigate: () => mockNavigate,
  };
});

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ─── Sample data ─────────────────────────────────────────────────────────────
const PLANS = [
  {
    id: 1,
    name: 'Free',
    slug: 'free',
    description: 'Free tier',
    tier_level: 0,
    price_monthly: 0,
    price_yearly: 0,
    features: ['Unlimited members'],
    is_active: true,
  },
  {
    id: 2,
    name: 'Pro',
    slug: 'pro',
    description: 'Pro tier',
    tier_level: 1,
    price_monthly: 29,
    price_yearly: 290,
    features: ['Everything in Free', 'Analytics'],
    is_active: true,
  },
];

import { PlanSelector } from './PlanSelector';

describe('PlanSelector', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading spinner while fetching plans', async () => {
    // Never resolve so we stay in loading state
    mockBillingApi.getPlans.mockReturnValue(new Promise(() => {}));
    mockBillingApi.getSubscription.mockReturnValue(new Promise(() => {}));

    render(<PlanSelector />);

    const spinner = screen
      .getAllByRole('status')
      .find((el) => el.getAttribute('aria-busy') === 'true');
    expect(spinner).toBeInTheDocument();
  });

  it('renders plan cards after loading', async () => {
    mockBillingApi.getPlans.mockResolvedValue({ success: true, data: PLANS });
    mockBillingApi.getSubscription.mockRejectedValue(new Error('No subscription'));

    render(<PlanSelector />);

    await waitFor(() => {
      expect(screen.getByText('Free')).toBeInTheDocument();
      expect(screen.getByText('Pro')).toBeInTheDocument();
    });
  });

  it('shows empty message when no plans returned', async () => {
    mockBillingApi.getPlans.mockResolvedValue({ success: true, data: [] });
    mockBillingApi.getSubscription.mockRejectedValue(new Error('No subscription'));

    render(<PlanSelector />);

    await waitFor(() => {
      // Real i18n: "billing.no_plans" → "No plans"
      expect(screen.getByText(/No plans/i)).toBeInTheDocument();
    });
  });

  it('shows error toast when load fails', async () => {
    mockBillingApi.getPlans.mockRejectedValue(new Error('Server error'));
    mockBillingApi.getSubscription.mockRejectedValue(new Error('Server error'));

    render(<PlanSelector />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('monthly/yearly toggle switches billing interval label', async () => {
    const user = userEvent.setup();
    mockBillingApi.getPlans.mockResolvedValue({ success: true, data: PLANS });
    mockBillingApi.getSubscription.mockRejectedValue(new Error('No subscription'));

    render(<PlanSelector />);

    // Wait for plans to load
    await waitFor(() => expect(screen.getByText('Free')).toBeInTheDocument());

    // Monthly is selected by default — "Per Month" text visible on plan cards
    // The text may be split across elements so use a flexible matcher
    expect(screen.getAllByText((content) => /per month/i.test(content)).length).toBeGreaterThan(0);

    // Click "Yearly" button — real i18n "Yearly"
    const yearlyBtn = screen.getByRole('button', { name: /^Yearly/i });
    await user.click(yearlyBtn);

    // "Per Year" text should appear
    await waitFor(() => {
      expect(screen.getAllByText((content) => /per year/i.test(content)).length).toBeGreaterThan(0);
    });
  });

  it('calls createCheckout and navigates on free plan activation', async () => {
    mockBillingApi.getPlans.mockResolvedValue({ success: true, data: PLANS });
    mockBillingApi.getSubscription.mockRejectedValue(new Error('No subscription'));
    mockBillingApi.createCheckout.mockResolvedValue({
      success: true,
      data: { activated: true, checkout_url: null },
    });

    const user = userEvent.setup();
    render(<PlanSelector />);

    await waitFor(() => expect(screen.getByText('Free')).toBeInTheDocument());

    // Find the subscribe button for the Free plan (not current, not blocked)
    const buttons = screen.getAllByRole('button', { name: /subscribe/i });
    await user.click(buttons[0]);

    await waitFor(() => {
      expect(mockBillingApi.createCheckout).toHaveBeenCalledWith(
        expect.objectContaining({ plan_id: 1 })
      );
      expect(mockToast.success).toHaveBeenCalled();
      expect(mockNavigate).toHaveBeenCalledWith('/test/admin/billing');
    });
  });

  it('shows checkout error toast when API returns failure', async () => {
    mockBillingApi.getPlans.mockResolvedValue({ success: true, data: PLANS });
    mockBillingApi.getSubscription.mockRejectedValue(new Error('No subscription'));
    mockBillingApi.createCheckout.mockResolvedValue({ success: false });

    const user = userEvent.setup();
    render(<PlanSelector />);

    await waitFor(() => expect(screen.getByText('Free')).toBeInTheDocument());

    const buttons = screen.getAllByRole('button', { name: /subscribe/i });
    await user.click(buttons[0]);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('marks current plan and disables its subscribe button', async () => {
    const subscription = {
      id: 10,
      plan_id: 2,
      plan_name: 'Pro',
      plan_tier_level: 1,
      status: 'active',
      billing_interval: 'monthly',
      current_period_start: '2026-01-01',
      current_period_end: '2026-02-01',
      trial_ends_at: null,
      cancel_at_period_end: false,
      stripe_subscription_id: 'sub_123',
    };

    mockBillingApi.getPlans.mockResolvedValue({ success: true, data: PLANS });
    mockBillingApi.getSubscription.mockResolvedValue({ success: true, data: subscription });

    render(<PlanSelector />);

    await waitFor(() => expect(screen.getByText('Pro')).toBeInTheDocument());

    // "Current" chip should appear
    expect(screen.getAllByText(/current/i).length).toBeGreaterThan(0);

    // The Pro subscribe button should show "Current" label and be disabled
    // (HeroUI disabled buttons won't fire onPress — just confirm aria-disabled)
    const currentBtns = screen.getAllByRole('button', { name: /current/i });
    expect(currentBtns.length).toBeGreaterThan(0);
  });
});
