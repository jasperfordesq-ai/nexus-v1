// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MarketplaceListingCardSkeleton — Loading placeholder matching the
 * exact dimensions and layout of MarketplaceListingCard.
 *
 * Uses HeroUI Skeleton for consistent pulse animation.
 */

import { Card, CardBody, Skeleton } from '@heroui/react';

export function MarketplaceListingCardSkeleton() {
  return (
    <Card className="bg-default-50 border border-default-200">
      {/* Image area — matches aspect-video */}
      <div className="relative aspect-video overflow-hidden rounded-t-lg">
        <Skeleton className="w-full h-full rounded-none" />
        {/* Price badge placeholder — bottom-left */}
        <div className="absolute bottom-2 left-2">
          <Skeleton className="w-16 h-6 rounded-full" />
        </div>
        {/* Condition badge placeholder — top-left */}
        <div className="absolute top-2 left-2">
          <Skeleton className="w-12 h-5 rounded-full" />
        </div>
      </div>

      {/* Content — matches CardBody p-3 gap-1.5 */}
      <CardBody className="p-3 gap-1.5">
        {/* Title — 2 lines */}
        <Skeleton className="w-full h-4 rounded-md" />
        <Skeleton className="w-3/4 h-4 rounded-md" />

        {/* Location */}
        <div className="flex items-center gap-1 mt-0.5">
          <Skeleton className="w-3 h-3 rounded-full" />
          <Skeleton className="w-24 h-3 rounded-md" />
        </div>

        {/* Seller name */}
        <Skeleton className="w-20 h-3 rounded-md" />
      </CardBody>
    </Card>
  );
}

export default MarketplaceListingCardSkeleton;
