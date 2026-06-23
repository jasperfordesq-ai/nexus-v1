// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import React from 'react';

// ─── Stub MarketplaceListingCardSkeleton ─────────────────────────────────────
// The real card uses HeroUI Card/Skeleton which can be heavy; stub it so we
// can count rendered instances deterministically.
vi.mock('./MarketplaceListingCardSkeleton', () => ({
  MarketplaceListingCardSkeleton: () => <div data-testid="card-skeleton" />,
  default: () => <div data-testid="card-skeleton" />,
}));

// ─────────────────────────────────────────────────────────────────────────────
describe('MarketplaceListingGridSkeleton', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders 8 skeleton cards by default', async () => {
    const { MarketplaceListingGridSkeleton } = await import('./MarketplaceListingGridSkeleton');
    render(<MarketplaceListingGridSkeleton />);

    const cards = screen.getAllByTestId('card-skeleton');
    expect(cards).toHaveLength(8);
  });

  it('renders the correct number of cards when count prop is provided', async () => {
    const { MarketplaceListingGridSkeleton } = await import('./MarketplaceListingGridSkeleton');
    render(<MarketplaceListingGridSkeleton count={4} />);

    const cards = screen.getAllByTestId('card-skeleton');
    expect(cards).toHaveLength(4);
  });

  it('renders 1 card when count=1', async () => {
    const { MarketplaceListingGridSkeleton } = await import('./MarketplaceListingGridSkeleton');
    render(<MarketplaceListingGridSkeleton count={1} />);

    const cards = screen.getAllByTestId('card-skeleton');
    expect(cards).toHaveLength(1);
  });

  it('renders 12 cards when count=12', async () => {
    const { MarketplaceListingGridSkeleton } = await import('./MarketplaceListingGridSkeleton');
    render(<MarketplaceListingGridSkeleton count={12} />);

    const cards = screen.getAllByTestId('card-skeleton');
    expect(cards).toHaveLength(12);
  });

  it('renders a grid container wrapping the cards', async () => {
    const { MarketplaceListingGridSkeleton } = await import('./MarketplaceListingGridSkeleton');
    const { container } = render(<MarketplaceListingGridSkeleton count={3} />);

    // The grid div is the direct parent of each card-skeleton
    const cards = screen.getAllByTestId('card-skeleton');
    // All cards must be inside the same parent element
    const parent = cards[0].parentElement;
    expect(parent).not.toBeNull();
    cards.forEach((card) => {
      expect(card.parentElement).toBe(parent);
    });

    // The grid container should have grid CSS classes
    expect(parent?.className).toContain('grid');
    void container; // suppress unused var warning
  });

  it('renders 0 skeleton cards when count=0', async () => {
    const { MarketplaceListingGridSkeleton } = await import('./MarketplaceListingGridSkeleton');
    render(<MarketplaceListingGridSkeleton count={0} />);

    const cards = screen.queryAllByTestId('card-skeleton');
    expect(cards).toHaveLength(0);
  });
});
