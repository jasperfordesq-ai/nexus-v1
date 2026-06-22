// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ── Stable mock refs (hoisted so they're available inside vi.mock factories) ──
const { mockToast, mockConfirm, mockMemberPremiumApi } = vi.hoisted(() => ({
  mockToast: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
  mockConfirm: vi.fn(async () => true),
  mockMemberPremiumApi: {
    listTiers: vi.fn(),
    createTier: vi.fn(),
    updateTier: vi.fn(),
    deleteTier: vi.fn(),
    syncStripe: vi.fn(),
    getTier: vi.fn(),
    listSubscribers: vi.fn(),
  },
}));

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  }),
);

// ── memberPremiumAdminApi mock ────────────────────────────────────────────────
vi.mock('@/admin/api/memberPremiumApi', () => ({
  memberPremiumAdminApi: mockMemberPremiumApi,
}));

// ── useConfirm mock ───────────────────────────────────────────────────────────
vi.mock('@/components/ui', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...actual,
    useConfirm: () => mockConfirm,
  };
});

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

import { MemberPremiumAdminPage } from './MemberPremiumAdminPage';
import userEvent from '@testing-library/user-event';

const TIER_A = {
  id: 1,
  tenant_id: 2,
  slug: 'gold',
  name: 'Gold',
  description: 'Gold tier',
  monthly_price_cents: 999,
  yearly_price_cents: 9900,
  stripe_price_id_monthly: 'price_m1',
  stripe_price_id_yearly: 'price_y1',
  features: ['Feature A', 'Feature B'],
  sort_order: 1,
  is_active: true,
  active_subscriber_count: 7,
};

describe('MemberPremiumAdminPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading spinner while tiers are fetching', () => {
    mockMemberPremiumApi.listTiers.mockReturnValue(new Promise(() => {}));
    render(<MemberPremiumAdminPage />);
    const statuses = screen.getAllByRole('status');
    const spinner = statuses.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(spinner).toBeDefined();
  });

  it('shows empty state when no tiers exist', async () => {
    mockMemberPremiumApi.listTiers.mockResolvedValueOnce({ data: { tiers: [] } });
    render(<MemberPremiumAdminPage />);
    await waitFor(() => {
      expect(screen.getByText(/empty.tiers|no tiers/i)).toBeInTheDocument();
    });
  });

  it('renders tier name in table when tiers are loaded', async () => {
    mockMemberPremiumApi.listTiers.mockResolvedValueOnce({ data: { tiers: [TIER_A] } });
    render(<MemberPremiumAdminPage />);
    await waitFor(() => {
      expect(screen.getByText('Gold')).toBeInTheDocument();
    });
  });

  it('renders tier slug and price cells', async () => {
    mockMemberPremiumApi.listTiers.mockResolvedValueOnce({ data: { tiers: [TIER_A] } });
    render(<MemberPremiumAdminPage />);
    await waitFor(() => {
      expect(screen.getByText('gold')).toBeInTheDocument();
      // 999 cents → "9.99"
      expect(screen.getByText('9.99')).toBeInTheDocument();
    });
  });

  it('shows "Synced" chip when stripe IDs are present', async () => {
    mockMemberPremiumApi.listTiers.mockResolvedValueOnce({ data: { tiers: [TIER_A] } });
    render(<MemberPremiumAdminPage />);
    await waitFor(() => {
      expect(screen.getByText(/stripe.synced|synced/i)).toBeInTheDocument();
    });
  });

  it('shows "Needs sync" chip when stripe IDs are missing', async () => {
    const unsynced = {
      ...TIER_A,
      stripe_price_id_monthly: null,
      stripe_price_id_yearly: null,
    };
    mockMemberPremiumApi.listTiers.mockResolvedValueOnce({ data: { tiers: [unsynced] } });
    render(<MemberPremiumAdminPage />);
    await waitFor(() => {
      expect(screen.getByText(/needs_sync|needs sync/i)).toBeInTheDocument();
    });
  });

  it('opens create modal when "New tier" button is pressed', async () => {
    mockMemberPremiumApi.listTiers.mockResolvedValueOnce({ data: { tiers: [] } });
    render(<MemberPremiumAdminPage />);
    await waitFor(() => screen.getByText(/actions.new_tier|New tier/i));

    fireEvent.click(screen.getByText(/actions.new_tier|New tier/i));
    await waitFor(() => {
      // Modal opens — the save/create button becomes visible
      const createBtns = screen.getAllByText(/actions.create_tier|Create tier/i);
      expect(createBtns.length).toBeGreaterThan(0);
    });
  });

  it('calls createTier and reloads on save with valid form data', async () => {
    mockMemberPremiumApi.listTiers
      .mockResolvedValueOnce({ data: { tiers: [] } })
      .mockResolvedValueOnce({ data: { tiers: [TIER_A] } });
    mockMemberPremiumApi.createTier.mockResolvedValueOnce({ data: { tier: TIER_A } });

    render(<MemberPremiumAdminPage />);
    await waitFor(() => screen.getByText(/actions.new_tier|New tier/i));
    fireEvent.click(screen.getByText(/actions.new_tier|New tier/i));

    // Fill in required fields in the modal
    await waitFor(() => screen.getAllByRole('textbox'));

    // Fill name field (first text input)
    const textboxes = screen.getAllByRole('textbox');
    // Name is first, slug is second
    fireEvent.change(textboxes[0], { target: { value: 'Gold' } });
    fireEvent.change(textboxes[1], { target: { value: 'gold' } });

    fireEvent.click(screen.getByText(/actions.create_tier|Create tier/i));

    await waitFor(() => {
      expect(mockMemberPremiumApi.createTier).toHaveBeenCalledWith(
        expect.objectContaining({ name: 'Gold', slug: 'gold' }),
      );
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('shows validation error when slug is empty on save', async () => {
    mockMemberPremiumApi.listTiers.mockResolvedValueOnce({ data: { tiers: [] } });
    render(<MemberPremiumAdminPage />);
    await waitFor(() => screen.getByText(/actions.new_tier|New tier/i));
    fireEvent.click(screen.getByText(/actions.new_tier|New tier/i));

    await waitFor(() => screen.getByText(/actions.create_tier|Create tier/i));
    fireEvent.click(screen.getByText(/actions.create_tier|Create tier/i));

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
      expect(mockMemberPremiumApi.createTier).not.toHaveBeenCalled();
    });
  });

  it('calls syncStripe when Sync Stripe button is clicked', async () => {
    mockMemberPremiumApi.listTiers
      .mockResolvedValueOnce({ data: { tiers: [TIER_A] } })
      .mockResolvedValueOnce({ data: { tiers: [TIER_A] } });
    mockMemberPremiumApi.syncStripe.mockResolvedValueOnce({ data: { tier: TIER_A } });

    render(<MemberPremiumAdminPage />);
    await waitFor(() => screen.getByText('Gold'));

    // The sync button label is translated — find by partial aria-label match
    const allButtons = screen.getAllByRole('button');
    // The icon-only sync button has aria-label containing the translation key
    const syncBtn = allButtons.find(
      (b) => b.getAttribute('aria-label')?.toLowerCase().includes('sync') ||
             b.getAttribute('aria-label')?.includes('actions.sync_stripe'),
    );
    expect(syncBtn).toBeDefined();
    fireEvent.click(syncBtn!);

    await waitFor(() => {
      expect(mockMemberPremiumApi.syncStripe).toHaveBeenCalledWith(TIER_A.id);
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('calls deleteTier after confirm dialog and reloads', async () => {
    mockMemberPremiumApi.listTiers
      .mockResolvedValueOnce({ data: { tiers: [TIER_A] } })
      .mockResolvedValueOnce({ data: { tiers: [] } });
    mockMemberPremiumApi.deleteTier.mockResolvedValueOnce({ data: { deleted: true } });
    mockConfirm.mockResolvedValueOnce(true);

    render(<MemberPremiumAdminPage />);
    await waitFor(() => screen.getByText('Gold'));

    const allButtons = screen.getAllByRole('button');
    const deleteBtn = allButtons.find(
      (b) => b.getAttribute('aria-label')?.toLowerCase().includes('delete') ||
             b.getAttribute('aria-label')?.includes('actions.delete'),
    );
    expect(deleteBtn).toBeDefined();
    fireEvent.click(deleteBtn!);

    await waitFor(() => {
      expect(mockConfirm).toHaveBeenCalled();
      expect(mockMemberPremiumApi.deleteTier).toHaveBeenCalledWith(TIER_A.id);
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('does not call deleteTier when confirm dialog is cancelled', async () => {
    mockMemberPremiumApi.listTiers.mockResolvedValueOnce({ data: { tiers: [TIER_A] } });
    // IMPORTANT: mockConfirm.mockResolvedValueOnce(false) must be set BEFORE mockConfirm is used
    // by the previous test — use a fresh setup here
    mockConfirm.mockResolvedValueOnce(false);

    render(<MemberPremiumAdminPage />);
    await waitFor(() => {
      // Use getAllByText in case the text appears in multiple places
      const goldEls = screen.queryAllByText('Gold');
      expect(goldEls.length).toBeGreaterThan(0);
    });

    // Find delete button by aria-label
    const allButtons = screen.getAllByRole('button');
    const deleteBtn = allButtons.find(
      (b) => b.getAttribute('aria-label')?.toLowerCase().includes('delete') ||
             b.getAttribute('aria-label')?.includes('actions.delete'),
    );
    expect(deleteBtn).toBeDefined();
    fireEvent.click(deleteBtn!);

    await waitFor(() => {
      expect(mockConfirm).toHaveBeenCalled();
      expect(mockMemberPremiumApi.deleteTier).not.toHaveBeenCalled();
    });
  });
});
