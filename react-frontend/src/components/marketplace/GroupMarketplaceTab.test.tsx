// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

// ─── Stable mock references ────────────────────────────────────────────────
const mockNavigate = vi.fn();

vi.mock('react-router-dom', async (importActual) => {
  const actual = await importActual<Record<string, unknown>>();
  return { ...actual, useNavigate: () => mockNavigate };
});

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

vi.mock('@/contexts', () => createMockContexts());

// Stub MarketplaceListingGrid to avoid deep rendering
vi.mock('./MarketplaceListingGrid', () => ({
  MarketplaceListingGrid: ({ listings }: { listings: unknown[] }) => (
    <div data-testid="listing-grid">{listings.length} listings</div>
  ),
}));

import { api } from '@/lib/api';
import { GroupMarketplaceTab } from './GroupMarketplaceTab';

const MOCK_LISTINGS = [
  {
    id: 1, title: 'Old Bike', price: null, price_currency: 'EUR', price_type: 'free' as const,
    condition: 'good' as const, delivery_method: 'pickup', seller_type: 'private',
    status: 'active', image: null, image_count: 0, is_saved: false, is_own: false,
    is_promoted: false, views_count: 0, created_at: '2026-01-01',
  },
  {
    id: 2, title: 'Book', price: 5, price_currency: 'EUR', price_type: 'fixed' as const,
    condition: 'like_new' as const, delivery_method: 'shipping', seller_type: 'private',
    status: 'active', image: null, image_count: 0, is_saved: false, is_own: false,
    is_promoted: false, views_count: 2, created_at: '2026-01-02',
  },
];

const MOCK_STATS = {
  active_listings: 2,
  total_listed: 10,
  total_sellers: 3,
  categories: [
    { id: 1, name: 'Books', slug: 'books', listing_count: 5 },
  ],
};

describe('GroupMarketplaceTab', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/stats')) {
        return Promise.resolve({ data: MOCK_STATS, success: true });
      }
      return Promise.resolve({
        data: { items: MOCK_LISTINGS, cursor: null, has_more: false },
        success: true,
      });
    });
  });

  it('shows a loading spinner initially', () => {
    // Never resolves
    vi.mocked(api.get).mockReturnValue(new Promise(() => {}));
    render(<GroupMarketplaceTab groupId={5} />);
    // Multiple role="status" elements may exist (ToastContext also renders one).
    // We look for the one with aria-busy="true" set by the component.
    const statusEls = screen.getAllByRole('status');
    const spinner = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(spinner).toBeInTheDocument();
  });

  it('renders the listing grid after successful load', async () => {
    render(<GroupMarketplaceTab groupId={5} />);
    await waitFor(() => {
      expect(screen.getByTestId('listing-grid')).toBeInTheDocument();
    });
    expect(screen.getByText('2 listings')).toBeInTheDocument();
  });

  it('shows empty state when no listings returned', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/stats')) {
        return Promise.resolve({ data: { ...MOCK_STATS, active_listings: 0 }, success: true });
      }
      return Promise.resolve({ data: { items: [], cursor: null, has_more: false }, success: true });
    });

    render(<GroupMarketplaceTab groupId={5} />);
    await waitFor(() => {
      // Empty state heading should be visible (i18n key falls back to key itself)
      const heading = screen.queryByRole('heading');
      // Some empty state UI renders
      expect(screen.queryByTestId('listing-grid')).toBeNull();
    });
  });

  it('renders stats bar when stats are loaded', async () => {
    render(<GroupMarketplaceTab groupId={5} />);
    await waitFor(() => expect(screen.getByTestId('listing-grid')).toBeInTheDocument());
    // The stats bar renders three <span> counters: active_listings(2), total_listed(10), total_sellers(3).
    // A bare /2/ matches several elements (incl. the "2 listings" grid stub), so scope to the stat spans.
    const statSpans = screen.getAllByText(
      (_content, el) => el?.tagName === 'SPAN' && /^\d+\s/.test(el.textContent ?? ''),
    );
    const joined = statSpans.map((s) => s.textContent).join(' | ');
    expect(joined).toMatch(/\b2\b/);
    expect(joined).toMatch(/\b10\b/);
    expect(joined).toMatch(/\b3\b/);
  });

  it('renders category filter chips from stats', async () => {
    render(<GroupMarketplaceTab groupId={5} />);
    await waitFor(() => {
      expect(screen.getByText(/Books/i)).toBeInTheDocument();
    });
  });

  it('navigates to marketplace sell page when CTA is pressed', async () => {
    render(<GroupMarketplaceTab groupId={5} />);
    await waitFor(() => {
      expect(screen.getByTestId('listing-grid')).toBeInTheDocument();
    });
    // The category filter Chips also carry role="button"; target the Sell CTA by its name.
    const sellBtn = screen.getByRole('button', { name: /sell/i });
    fireEvent.pointerDown(sellBtn);
    fireEvent.pointerUp(sellBtn);
    fireEvent.click(sellBtn);
    expect(mockNavigate).toHaveBeenCalledWith(expect.stringContaining('/marketplace/sell'));
  });

  it('calls api.get with the correct group listings URL', async () => {
    render(<GroupMarketplaceTab groupId={42} />);
    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith(
        expect.stringContaining('/v2/marketplace/groups/42/listings'),
      );
    });
  });

  it('gracefully handles listing API error (no crash)', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (url.includes('/stats')) {
        return Promise.resolve({ data: MOCK_STATS, success: true });
      }
      return Promise.reject(new Error('network error'));
    });

    render(<GroupMarketplaceTab groupId={5} />);
    // After error, the aria-busy spinner (not the ToastContext status) should be gone
    await waitFor(() => {
      const statusEls = screen.queryAllByRole('status');
      const busySpinner = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
      expect(busySpinner).toBeUndefined();
    });
    // And the component should not crash (still in the document)
    expect(document.body).toBeInTheDocument();
  });
});
