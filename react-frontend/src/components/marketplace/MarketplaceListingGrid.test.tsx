// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import React from 'react';
import type { MarketplaceListingItem } from '@/types/marketplace';

vi.mock('@/contexts', () => {
  const { createMockContexts } = require('@/test/mock-contexts');
  return createMockContexts();
});

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// Stub the heavy card and empty-state children; we test the grid's own logic.
vi.mock('./MarketplaceListingCard', () => ({
  MarketplaceListingCard: ({ listing }: { listing: MarketplaceListingItem }) => (
    <div data-testid="listing-card" data-id={listing.id}>
      {listing.title}
    </div>
  ),
}));

vi.mock('./MarketplaceEmptyState', () => ({
  MarketplaceEmptyState: () => (
    <div data-testid="marketplace-empty-state">No listings found</div>
  ),
}));

// ─── Fixture ─────────────────────────────────────────────────────────────────
function makeListing(id: number, overrides: Partial<MarketplaceListingItem> = {}): MarketplaceListingItem {
  return {
    id,
    title: `Listing ${id}`,
    price: 10,
    price_currency: 'EUR',
    price_type: 'fixed',
    condition: 'good',
    delivery_method: 'pickup',
    seller_type: 'individual',
    status: 'active',
    image: null,
    image_count: 0,
    is_saved: false,
    is_own: false,
    is_promoted: false,
    views_count: 0,
    created_at: '2025-01-01T00:00:00Z',
    ...overrides,
  };
}

// ─────────────────────────────────────────────────────────────────────────────
describe('MarketplaceListingGrid', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders the empty state when listings array is empty', async () => {
    const { MarketplaceListingGrid } = await import('./MarketplaceListingGrid');
    render(<MarketplaceListingGrid listings={[]} />);
    expect(screen.getByTestId('marketplace-empty-state')).toBeInTheDocument();
  });

  it('does not render any listing cards when listings is empty', async () => {
    const { MarketplaceListingGrid } = await import('./MarketplaceListingGrid');
    render(<MarketplaceListingGrid listings={[]} />);
    expect(screen.queryByTestId('listing-card')).toBeNull();
  });

  it('renders exactly one card for a single listing', async () => {
    const { MarketplaceListingGrid } = await import('./MarketplaceListingGrid');
    render(<MarketplaceListingGrid listings={[makeListing(1)]} />);
    expect(screen.getAllByTestId('listing-card')).toHaveLength(1);
  });

  it('renders the correct number of cards for multiple listings', async () => {
    const { MarketplaceListingGrid } = await import('./MarketplaceListingGrid');
    const listings = [makeListing(1), makeListing(2), makeListing(3)];
    render(<MarketplaceListingGrid listings={listings} />);
    expect(screen.getAllByTestId('listing-card')).toHaveLength(3);
  });

  it('does not render the empty state when there are listings', async () => {
    const { MarketplaceListingGrid } = await import('./MarketplaceListingGrid');
    render(<MarketplaceListingGrid listings={[makeListing(1)]} />);
    expect(screen.queryByTestId('marketplace-empty-state')).toBeNull();
  });

  it('passes the listing title through to each card', async () => {
    const { MarketplaceListingGrid } = await import('./MarketplaceListingGrid');
    render(<MarketplaceListingGrid listings={[makeListing(42, { title: 'Handmade Vase' })]} />);
    expect(screen.getByText('Handmade Vase')).toBeInTheDocument();
  });

  it('passes the correct listing id to each card via data-id', async () => {
    const { MarketplaceListingGrid } = await import('./MarketplaceListingGrid');
    render(<MarketplaceListingGrid listings={[makeListing(7), makeListing(13)]} />);
    const cards = screen.getAllByTestId('listing-card');
    expect(cards[0]).toHaveAttribute('data-id', '7');
    expect(cards[1]).toHaveAttribute('data-id', '13');
  });

  it('renders a grid wrapper (not the empty state) when listings are provided', async () => {
    const { MarketplaceListingGrid } = await import('./MarketplaceListingGrid');
    const { container } = render(
      <MarketplaceListingGrid listings={[makeListing(1)]} />,
    );
    // The outermost element rendered by the grid (after the test-utils ToastProvider wrapper)
    // is a <div> with grid classes, not the empty-state element.
    const gridDiv = container.querySelector('.grid');
    expect(gridDiv).toBeInTheDocument();
  });

  it('renders 4 cards for 4 listings', async () => {
    const { MarketplaceListingGrid } = await import('./MarketplaceListingGrid');
    const listings = [1, 2, 3, 4].map((id) => makeListing(id));
    render(<MarketplaceListingGrid listings={listings} />);
    expect(screen.getAllByTestId('listing-card')).toHaveLength(4);
  });

  it('accepts optional onSave/onUnsave callbacks without crashing', async () => {
    const { MarketplaceListingGrid } = await import('./MarketplaceListingGrid');
    const onSave = vi.fn();
    const onUnsave = vi.fn();
    render(
      <MarketplaceListingGrid
        listings={[makeListing(1)]}
        onSave={onSave}
        onUnsave={onUnsave}
      />,
    );
    // Grid renders the card; callbacks haven't fired since no interaction occurred
    expect(screen.getByTestId('listing-card')).toBeInTheDocument();
    expect(onSave).not.toHaveBeenCalled();
    expect(onUnsave).not.toHaveBeenCalled();
  });

  it('renders listings in the order supplied', async () => {
    const { MarketplaceListingGrid } = await import('./MarketplaceListingGrid');
    const listings = [makeListing(5, { title: 'First' }), makeListing(6, { title: 'Second' })];
    render(<MarketplaceListingGrid listings={listings} />);
    const cards = screen.getAllByTestId('listing-card');
    expect(cards[0]).toHaveTextContent('First');
    expect(cards[1]).toHaveTextContent('Second');
  });

  it('handles a large listing set without error', async () => {
    const { MarketplaceListingGrid } = await import('./MarketplaceListingGrid');
    const listings = Array.from({ length: 20 }, (_, i) => makeListing(i + 1));
    render(<MarketplaceListingGrid listings={listings} />);
    expect(screen.getAllByTestId('listing-card')).toHaveLength(20);
  });
});
