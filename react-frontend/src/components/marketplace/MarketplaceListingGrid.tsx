// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MarketplaceListingGrid - Responsive grid layout for marketplace listings
 *
 * Renders a CSS grid of MarketplaceListingCard components with responsive
 * column counts: 2 on mobile, 3 on tablet, 4 on desktop.
 * Shows an empty state when no listings are available.
 */

import { MarketplaceListingCard } from './MarketplaceListingCard';
import { MarketplaceEmptyState } from './MarketplaceEmptyState';
import type { MarketplaceListingItem } from '@/types/marketplace';

interface MarketplaceListingGridProps {
  listings: MarketplaceListingItem[];
  onSave?: (id: number) => void;
  onUnsave?: (id: number) => void;
}

export function MarketplaceListingGrid({ listings, onSave, onUnsave }: MarketplaceListingGridProps) {
  if (listings.length === 0) {
    return <MarketplaceEmptyState />;
  }

  return (
    <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
      {listings.map((listing) => (
        <MarketplaceListingCard
          key={listing.id}
          listing={listing}
          onSave={onSave}
          onUnsave={onUnsave}
        />
      ))}
    </div>
  );
}

export default MarketplaceListingGrid;
