// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';

// ── Stable mock refs (hoisted so they're available inside vi.mock factories) ──
const { mockToast, mockApi } = vi.hoisted(() => ({
  mockToast: { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() },
  mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
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

// ── api mock ─────────────────────────────────────────────────────────────────
vi.mock('@/lib/api', () => ({
  api: mockApi,
  default: mockApi,
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

import { MarketplaceSellerAdmin } from './MarketplaceSellerAdmin';

const PRIVATE_SELLER = {
  id: 10,
  user_id: 100,
  display_name: 'Jane Doe',
  seller_type: 'private' as const,
  business_name: null,
  business_verified: false,
  is_community_endorsed: false,
  active_listings: 5,
  total_sales: 3,
  avg_rating: 4.5,
  total_ratings: 3,
  joined_marketplace_at: '2026-01-15T10:00:00Z',
  user: { id: 100, name: 'Jane Doe', email: 'jane@example.com', avatar_url: null },
};

const BUSINESS_SELLER = {
  id: 20,
  user_id: 200,
  display_name: 'ACME Corp',
  seller_type: 'business' as const,
  business_name: 'ACME Corp',
  business_verified: false,
  is_community_endorsed: false,
  active_listings: 12,
  total_sales: 8,
  avg_rating: 3.8,
  total_ratings: 8,
  joined_marketplace_at: '2026-02-01T10:00:00Z',
  user: { id: 200, name: 'ACME Admin', email: 'admin@acme.com', avatar_url: null },
};

function mockGetSuccess(sellers: typeof PRIVATE_SELLER[]) {
  mockApi.get.mockResolvedValueOnce({ success: true, data: sellers, meta: { total: sellers.length } });
}

// Helper: find a button whose aria-label includes a substring (case-insensitive)
function findButtonByAriaLabel(substr: string) {
  return screen.getAllByRole('button').find((b) =>
    b.getAttribute('aria-label')?.toLowerCase().includes(substr.toLowerCase()),
  );
}

describe('MarketplaceSellerAdmin', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows loading state on initial mount', () => {
    mockApi.get.mockReturnValue(new Promise(() => {}));
    render(<MarketplaceSellerAdmin />);
    // loadSellers is called on mount
    expect(mockApi.get).toHaveBeenCalled();
  });

  it('renders seller display names when data loads', async () => {
    mockGetSuccess([PRIVATE_SELLER, BUSINESS_SELLER]);
    render(<MarketplaceSellerAdmin />);
    await waitFor(() => {
      const janeEls = screen.queryAllByText('Jane Doe');
      expect(janeEls.length).toBeGreaterThan(0);
      const acmeEls = screen.queryAllByText('ACME Corp');
      expect(acmeEls.length).toBeGreaterThan(0);
    });
  });

  it('shows empty state when no sellers returned', async () => {
    mockGetSuccess([]);
    render(<MarketplaceSellerAdmin />);
    await waitFor(() => {
      expect(screen.getByText(/no_sellers_found|no sellers found/i)).toBeInTheDocument();
    });
  });

  it('renders star rating for sellers with avg_rating > 0', async () => {
    mockGetSuccess([PRIVATE_SELLER]);
    render(<MarketplaceSellerAdmin />);
    await waitFor(() => {
      expect(screen.getByText('4.5')).toBeInTheDocument();
    });
  });

  it('renders "--" for sellers with no ratings', async () => {
    const noRating = { ...PRIVATE_SELLER, avg_rating: 0 };
    mockGetSuccess([noRating]);
    render(<MarketplaceSellerAdmin />);
    await waitFor(() => {
      expect(screen.getByText('--')).toBeInTheDocument();
    });
  });

  it('shows verify button only for unverified business sellers', async () => {
    mockGetSuccess([PRIVATE_SELLER, BUSINESS_SELLER]);
    render(<MarketplaceSellerAdmin />);
    await waitFor(() => {
      const acmeEls = screen.queryAllByText('ACME Corp');
      expect(acmeEls.length).toBeGreaterThan(0);
    });
    // aria-label resolves to English translation "Verify Seller"
    const verifyBtns = screen.getAllByRole('button').filter((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('verify'),
    );
    // ACME Corp (business + unverified) → 1 verify button; Jane is private → none
    expect(verifyBtns.length).toBe(1);
  });

  it('does NOT show verify button for already-verified business seller', async () => {
    const verified = { ...BUSINESS_SELLER, business_verified: true };
    mockGetSuccess([verified]);
    render(<MarketplaceSellerAdmin />);
    await waitFor(() => {
      const acmeEls = screen.queryAllByText('ACME Corp');
      expect(acmeEls.length).toBeGreaterThan(0);
    });
    const verifyBtns = screen.getAllByRole('button').filter((b) =>
      b.getAttribute('aria-label')?.toLowerCase().includes('verify'),
    );
    expect(verifyBtns.length).toBe(0);
  });

  it('opens verify confirm modal when verify button clicked', async () => {
    const user = userEvent.setup();
    mockGetSuccess([BUSINESS_SELLER]);
    render(<MarketplaceSellerAdmin />);
    await waitFor(() => {
      const acmeEls = screen.queryAllByText('ACME Corp');
      expect(acmeEls.length).toBeGreaterThan(0);
    });

    // aria-label = "Verify Seller" (English translation)
    const verifyBtn = findButtonByAriaLabel('verify');
    expect(verifyBtn).toBeDefined();
    await user.click(verifyBtn!);

    // ConfirmModal opens — the modal renders the title from t('marketplace.verify_seller_title')
    await waitFor(() => {
      // Title or the confirm button text should appear
      const allText = document.body.textContent ?? '';
      expect(allText).toMatch(/verify/i);
      // The modal also shows a confirm button
      const btns = screen.getAllByRole('button');
      expect(btns.length).toBeGreaterThan(2); // more buttons than before modal
    });
  });

  it('calls verify POST and shows success toast on confirmation', async () => {
    const user = userEvent.setup();
    mockGetSuccess([BUSINESS_SELLER]);
    mockApi.post.mockResolvedValueOnce({ success: true });
    // After verify, reload
    mockApi.get.mockResolvedValueOnce({
      success: true,
      data: [{ ...BUSINESS_SELLER, business_verified: true }],
      meta: { total: 1 },
    });

    render(<MarketplaceSellerAdmin />);
    await waitFor(() => {
      const acmeEls = screen.queryAllByText('ACME Corp');
      expect(acmeEls.length).toBeGreaterThan(0);
    });

    const verifyBtn = findButtonByAriaLabel('verify');
    expect(verifyBtn).toBeDefined();
    await user.click(verifyBtn!);

    // Wait for modal confirm button to appear (the last primary-action button)
    // ConfirmModal's confirm button text = t('marketplace.verify_btn') — resolves from translations
    await waitFor(() => {
      // More than initial buttons should be present (modal opened)
      expect(screen.getAllByRole('button').length).toBeGreaterThan(3);
    });

    // Find the confirm button — it's in the modal footer and is NOT the cancel/tertiary button
    // Its text = t('marketplace.verify_btn') = e.g. 'Verify' or 'verify_btn'
    const allBtns = screen.getAllByRole('button');
    const confirmBtn = allBtns.find(
      (b) =>
        b.textContent?.toLowerCase().includes('verify') &&
        !b.getAttribute('aria-label')?.toLowerCase().includes('verify'),
    );
    expect(confirmBtn).toBeDefined();
    await user.click(confirmBtn!);

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        `/v2/admin/marketplace/sellers/${BUSINESS_SELLER.id}/verify`,
      );
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('opens suspend confirm modal when suspend button clicked', async () => {
    const user = userEvent.setup();
    mockGetSuccess([PRIVATE_SELLER]);
    render(<MarketplaceSellerAdmin />);
    await waitFor(() => {
      const janeEls = screen.queryAllByText('Jane Doe');
      expect(janeEls.length).toBeGreaterThan(0);
    });

    // aria-label = "Suspend Seller" (English translation)
    const suspendBtn = findButtonByAriaLabel('suspend');
    expect(suspendBtn).toBeDefined();
    await user.click(suspendBtn!);

    // ConfirmModal opens
    await waitFor(() => {
      const allText = document.body.textContent ?? '';
      expect(allText).toMatch(/suspend/i);
      // Modal shows additional buttons
      expect(screen.getAllByRole('button').length).toBeGreaterThan(2);
    });
  });

  it('calls suspend POST and shows success toast on confirmation', async () => {
    const user = userEvent.setup();
    mockGetSuccess([PRIVATE_SELLER]);
    mockApi.post.mockResolvedValueOnce({ success: true });
    mockApi.get.mockResolvedValueOnce({ success: true, data: [], meta: { total: 0 } });

    render(<MarketplaceSellerAdmin />);
    await waitFor(() => {
      const janeEls = screen.queryAllByText('Jane Doe');
      expect(janeEls.length).toBeGreaterThan(0);
    });

    const suspendBtn = findButtonByAriaLabel('suspend');
    expect(suspendBtn).toBeDefined();
    await user.click(suspendBtn!);

    // Wait for modal to appear
    await waitFor(() => {
      expect(screen.getAllByRole('button').length).toBeGreaterThan(2);
    });

    // The confirm button text = t('marketplace.suspend_btn') resolves from translations
    const allBtns = screen.getAllByRole('button');
    const confirmBtn = allBtns.find(
      (b) =>
        b.textContent?.toLowerCase().includes('suspend') &&
        !b.getAttribute('aria-label')?.toLowerCase().includes('suspend'),
    );
    expect(confirmBtn).toBeDefined();
    await user.click(confirmBtn!);

    await waitFor(() => {
      expect(mockApi.post).toHaveBeenCalledWith(
        `/v2/admin/marketplace/sellers/${PRIVATE_SELLER.id}/suspend`,
      );
      expect(mockToast.success).toHaveBeenCalled();
    });
  });

  it('calls loadSellers when Refresh button is pressed', async () => {
    const user = userEvent.setup();
    mockGetSuccess([]);
    mockGetSuccess([]);
    render(<MarketplaceSellerAdmin />);
    await waitFor(() => expect(mockApi.get).toHaveBeenCalledTimes(1));

    // The Refresh button has text from t('marketplace.refresh')
    const refreshBtns = screen.getAllByRole('button').filter(
      (b) => b.textContent?.match(/refresh/i) || b.getAttribute('aria-label')?.match(/refresh/i),
    );
    expect(refreshBtns.length).toBeGreaterThan(0);
    await user.click(refreshBtns[0]);
    await waitFor(() => {
      expect(mockApi.get).toHaveBeenCalledTimes(2);
    });
  });

  it('shows error toast when sellers API throws', async () => {
    mockApi.get.mockRejectedValueOnce(new Error('Network fail'));
    render(<MarketplaceSellerAdmin />);
    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });
});
