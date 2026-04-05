// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MarketplaceListingDetailSkeleton — Full-page loading skeleton
 * matching the layout of MarketplaceListingPage.
 *
 * Includes: image gallery area, price/title sidebar, seller card,
 * and description section.
 */

import { Skeleton } from '@heroui/react';
import { GlassCard } from '@/components/ui';

export function MarketplaceListingDetailSkeleton() {
  return (
    <div className="max-w-6xl mx-auto px-4 py-6 space-y-6">
      {/* Breadcrumb placeholder */}
      <div className="flex items-center gap-2">
        <Skeleton className="w-24 h-8 rounded-lg" />
        <Skeleton className="w-4 h-4 rounded-full" />
        <Skeleton className="w-20 h-4 rounded-md" />
      </div>

      {/* Main content grid */}
      <div className="grid grid-cols-1 lg:grid-cols-5 gap-6">
        {/* Image gallery — 3 cols */}
        <div className="lg:col-span-3 space-y-3">
          {/* Main image */}
          <Skeleton className="w-full aspect-video rounded-xl" />
          {/* Thumbnails */}
          <div className="flex gap-2">
            {Array.from({ length: 4 }, (_, i) => (
              <Skeleton key={i} className="w-16 h-16 rounded-lg shrink-0" />
            ))}
          </div>
        </div>

        {/* Details sidebar — 2 cols */}
        <div className="lg:col-span-2 space-y-4">
          {/* Price / title card */}
          <GlassCard className="p-5 space-y-3">
            {/* Price */}
            <div className="flex items-start justify-between gap-3">
              <Skeleton className="w-32 h-9 rounded-md" />
              <div className="flex gap-1.5">
                <Skeleton className="w-8 h-8 rounded-lg" />
                <Skeleton className="w-8 h-8 rounded-lg" />
              </div>
            </div>
            {/* Title */}
            <Skeleton className="w-full h-6 rounded-md" />
            <Skeleton className="w-3/4 h-6 rounded-md" />
            {/* Chips */}
            <div className="flex items-center gap-2">
              <Skeleton className="w-14 h-5 rounded-full" />
              <Skeleton className="w-20 h-5 rounded-full" />
            </div>
            {/* Meta info */}
            <div className="flex items-center gap-4">
              <Skeleton className="w-20 h-3 rounded-md" />
              <Skeleton className="w-16 h-3 rounded-md" />
              <Skeleton className="w-18 h-3 rounded-md" />
            </div>
            {/* Action buttons */}
            <div className="flex flex-col gap-2 pt-2">
              <Skeleton className="w-full h-10 rounded-lg" />
              <div className="flex gap-2">
                <Skeleton className="flex-1 h-10 rounded-lg" />
                <Skeleton className="flex-1 h-10 rounded-lg" />
              </div>
            </div>
          </GlassCard>

          {/* Seller card */}
          <GlassCard className="p-5 space-y-3">
            <Skeleton className="w-16 h-4 rounded-md" />
            <div className="flex items-center gap-3">
              <Skeleton className="w-12 h-12 rounded-full" />
              <div className="flex-1 space-y-1.5">
                <Skeleton className="w-28 h-4 rounded-md" />
                <Skeleton className="w-20 h-3 rounded-md" />
              </div>
            </div>
            <Skeleton className="w-full h-8 rounded-lg" />
          </GlassCard>
        </div>
      </div>

      {/* Description */}
      <GlassCard className="p-6 space-y-4">
        <Skeleton className="w-24 h-5 rounded-md" />
        <div className="space-y-2">
          <Skeleton className="w-full h-4 rounded-md" />
          <Skeleton className="w-full h-4 rounded-md" />
          <Skeleton className="w-5/6 h-4 rounded-md" />
          <Skeleton className="w-full h-4 rounded-md" />
          <Skeleton className="w-2/3 h-4 rounded-md" />
        </div>
      </GlassCard>
    </div>
  );
}

export default MarketplaceListingDetailSkeleton;
