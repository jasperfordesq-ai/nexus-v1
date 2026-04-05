// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MarketplaceListingGridSkeleton — Loading skeleton grid matching the
 * responsive layout of MarketplaceListingGrid (2/3/4 columns).
 */

import { MarketplaceListingCardSkeleton } from './MarketplaceListingCardSkeleton';

interface MarketplaceListingGridSkeletonProps {
  /** Number of skeleton cards to render (default 8) */
  count?: number;
}

export function MarketplaceListingGridSkeleton({ count = 8 }: MarketplaceListingGridSkeletonProps) {
  return (
    <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
      {Array.from({ length: count }, (_, i) => (
        <MarketplaceListingCardSkeleton key={i} />
      ))}
    </div>
  );
}

export default MarketplaceListingGridSkeleton;
