// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * FeedSkeleton — Enhanced loading placeholder for feed cards.
 *
 * Shared across FeedPage, HashtagPage, and PostDetailPage.
 * Uses HeroUI Skeleton with shimmer animation.
 */

import { Skeleton, Divider } from '@heroui/react';
import { GlassCard } from '@/components/ui';

export function FeedSkeleton() {
  return (
    <GlassCard className="overflow-hidden">
      <div className="p-5">
        {/* Header: Avatar + Name + Timestamp */}
        <div className="flex items-center gap-3 mb-4">
          <Skeleton className="w-10 h-10 rounded-full flex-shrink-0" />
          <div className="flex-1 min-w-0">
            <Skeleton className="h-4 w-28 rounded-lg mb-2" />
            <Skeleton className="h-3 w-20 rounded-lg" />
          </div>
        </div>

        {/* Content lines (varying widths) */}
        <div className="space-y-2 mb-4">
          <Skeleton className="h-4 w-full rounded-lg" />
          <Skeleton className="h-4 w-4/5 rounded-lg" />
          <Skeleton className="h-4 w-3/5 rounded-lg" />
        </div>
      </div>

      {/* Image placeholder (16:9 aspect ratio) */}
      <Skeleton className="w-full aspect-video rounded-none" />

      <div className="p-5 pt-4">
        {/* Divider */}
        <Divider className="mb-3" />

        {/* Action bar: 4 buttons */}
        <div className="flex items-center justify-around gap-2">
          <Skeleton className="h-8 w-20 rounded-lg" />
          <Skeleton className="h-8 w-24 rounded-lg" />
          <Skeleton className="h-8 w-20 rounded-lg" />
          <Skeleton className="h-8 w-20 rounded-lg" />
        </div>
      </div>
    </GlassCard>
  );
}
