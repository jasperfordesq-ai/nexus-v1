// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import React from 'react';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';

// ─── Hoisted mocks ────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: { get: vi.fn(), post: vi.fn(), put: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}));

vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));
vi.mock('@/components/seo/PageMeta', () => ({ PageMeta: () => null }));
vi.mock('@/hooks', () => ({ usePageTitle: vi.fn(), useMediaQuery: vi.fn(() => false) }));

vi.mock('@/components/feedback', () => ({
  EmptyState: ({ title, description }: { title: string; description?: string }) => (
    <div data-testid="empty-state">
      <p>{title}</p>
      {description && <p>{description}</p>}
    </div>
  ),
}));

// Stub heavy marketplace grid components
vi.mock('@/components/marketplace', () => ({
  MarketplaceListingGrid: ({ listings }: { listings: object[] }) => (
    <div data-testid="listing-grid">{listings.length} listings</div>
  ),
  MarketplaceListingGridSkeleton: () => <div data-testid="grid-skeleton" />,
}));

// Stub Breadcrumbs
vi.mock('@/components/navigation/Breadcrumbs', () => ({
  Breadcrumbs: ({ items }: { items: { label: string }[] }) => (
    <nav aria-label="breadcrumb">
      {items.map((item) => <span key={item.label}>{item.label}</span>)}
    </nav>
  ),
}));

const mockNavigate = vi.fn();
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn() };

vi.mock('react-router-dom', async (importOriginal) => {
  const orig = await importOriginal<typeof import('react-router-dom')>();
  return {
    ...orig,
    useNavigate: () => mockNavigate,
    useParams: () => ({ slug: 'electronics' }),
    useSearchParams: () => [new URLSearchParams(), vi.fn()],
  };
});

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: { id: 1, name: 'Alice' },
      isAuthenticated: true,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      updateUser: vi.fn(),
      refreshUser: vi.fn(),
      status: 'idle' as const,
      error: null,
    }),
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

// ─── Fixtures ─────────────────────────────────────────────────────────────────
const makeCategory = (overrides = {}) => ({
  id: 10,
  name: 'Electronics',
  slug: 'electronics',
  description: 'Gadgets and devices',
  icon: '💻',
  listing_count: 42,
  parent_id: null,
  ...overrides,
});

const makeListing = (id: number, overrides = {}) => ({
  id,
  title: `Listing ${id}`,
  price: 10 * id,
  currency: 'EUR',
  condition: 'good',
  is_saved: false,
  images: [],
  seller: { id: 99, name: 'Seller' },
  created_at: '2025-01-01T00:00:00Z',
  ...overrides,
});

const makeCatResponse = (cats: object[]) => ({ success: true, data: cats });
const makeListingsResponse = (data: object[], meta = {}) => ({
  success: true,
  data,
  meta: { has_more: false, cursor: null, ...meta },
});

/** Sets up 3 API calls in order: categories, template fields, listings */
function setupMocks(cats: object[], listings: object[], listingsMeta = {}) {
  mockApi.get
    .mockResolvedValueOnce(makeCatResponse(cats))                  // /v2/marketplace/categories
    .mockResolvedValueOnce({ success: true, data: { fields: [] } }) // /v2/marketplace/categories/{id}/template
    .mockResolvedValueOnce(makeListingsResponse(listings, listingsMeta)); // /v2/marketplace/listings
}

// ─────────────────────────────────────────────────────────────────────────────
describe('MarketplaceCategoryPage', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('shows loading spinner while category loads', async () => {
    mockApi.get.mockImplementation(() => new Promise(() => {}));
    const { MarketplaceCategoryPage } = await import('./MarketplaceCategoryPage');
    render(<MarketplaceCategoryPage />);

    const statusEls = screen.getAllByRole('status');
    const busy = statusEls.find((el) => el.getAttribute('aria-busy') === 'true');
    expect(busy).toBeDefined();
  });

  it('renders category header when category is found', async () => {
    setupMocks([makeCategory()], []);
    const { MarketplaceCategoryPage } = await import('./MarketplaceCategoryPage');
    render(<MarketplaceCategoryPage />);

    await waitFor(() => {
      // Multiple elements may have "Electronics" (heading + breadcrumb)
      const els = screen.getAllByText('Electronics');
      expect(els.length).toBeGreaterThanOrEqual(1);
    });
    expect(screen.getByText('Gadgets and devices')).toBeInTheDocument();
  });

  it('renders breadcrumb with category name', async () => {
    setupMocks([makeCategory()], []);
    const { MarketplaceCategoryPage } = await import('./MarketplaceCategoryPage');
    render(<MarketplaceCategoryPage />);

    await waitFor(() => {
      const els = screen.getAllByText('Electronics');
      expect(els.length).toBeGreaterThanOrEqual(1);
    });
    const breadcrumb = screen.getByRole('navigation', { name: /breadcrumb/i });
    expect(breadcrumb).toHaveTextContent('Electronics');
  });

  it('renders empty state when category has no listings', async () => {
    setupMocks([makeCategory()], []);
    const { MarketplaceCategoryPage } = await import('./MarketplaceCategoryPage');
    render(<MarketplaceCategoryPage />);

    await waitFor(() => {
      expect(screen.getByTestId('empty-state')).toBeInTheDocument();
    });
    expect(screen.queryByTestId('listing-grid')).toBeNull();
  });

  it('renders listing grid when listings are returned', async () => {
    setupMocks([makeCategory()], [makeListing(1), makeListing(2)]);
    const { MarketplaceCategoryPage } = await import('./MarketplaceCategoryPage');
    render(<MarketplaceCategoryPage />);

    await waitFor(() => {
      expect(screen.getByTestId('listing-grid')).toBeInTheDocument();
    });
    expect(screen.getByTestId('listing-grid')).toHaveTextContent('2 listings');
  });

  it('shows not-found empty state when category slug does not match', async () => {
    // When slug doesn't match, no template or listing calls are made
    mockApi.get.mockResolvedValueOnce(makeCatResponse([makeCategory({ slug: 'other' })]));
    const { MarketplaceCategoryPage } = await import('./MarketplaceCategoryPage');
    render(<MarketplaceCategoryPage />);

    await waitFor(() => {
      expect(screen.getByTestId('empty-state')).toBeInTheDocument();
    });
  });

  it('shows error toast when listings API fails', async () => {
    mockApi.get
      .mockResolvedValueOnce(makeCatResponse([makeCategory()]))
      .mockResolvedValueOnce({ success: true, data: { fields: [] } })  // template
      .mockRejectedValueOnce(new Error('network'));                     // listings

    const { MarketplaceCategoryPage } = await import('./MarketplaceCategoryPage');
    render(<MarketplaceCategoryPage />);

    await waitFor(() => {
      expect(mockToast.error).toHaveBeenCalled();
    });
  });

  it('shows Load More button when has_more is true', async () => {
    setupMocks([makeCategory()], [makeListing(1)], { has_more: true });
    const { MarketplaceCategoryPage } = await import('./MarketplaceCategoryPage');
    render(<MarketplaceCategoryPage />);

    await waitFor(() => {
      const loadMore = screen.getAllByRole('button').find((b) =>
        b.textContent?.toLowerCase().includes('more') || b.textContent?.toLowerCase().includes('load')
      );
      expect(loadMore).toBeInTheDocument();
    });
  });

  it('displays listing count from category header', async () => {
    setupMocks([makeCategory({ listing_count: 42 })], []);
    const { MarketplaceCategoryPage } = await import('./MarketplaceCategoryPage');
    render(<MarketplaceCategoryPage />);

    await waitFor(() => {
      // Category listing_count appears somewhere in the header
      expect(document.body.textContent).toContain('42');
    });
  });

  it('does not render listing grid when no results', async () => {
    setupMocks([makeCategory()], []);
    const { MarketplaceCategoryPage } = await import('./MarketplaceCategoryPage');
    render(<MarketplaceCategoryPage />);

    await waitFor(() => {
      // Loading finishes
      expect(screen.queryByTestId('grid-skeleton')).toBeNull();
    });
    expect(screen.queryByTestId('listing-grid')).toBeNull();
  });
});
