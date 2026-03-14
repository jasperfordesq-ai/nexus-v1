// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * WidgetSkeleton - Reusable loading skeleton for sidebar widgets
 */

import { Skeleton } from '@heroui/react';
import { GlassCard } from '@/components/ui';

interface WidgetSkeletonProps {
  lines?: number;
}

export function WidgetSkeleton({ lines = 3 }: WidgetSkeletonProps) {
  return (
    <GlassCard className="p-4">
      <Skeleton className="h-4 w-32 rounded mb-4" />
      <div className="space-y-3">
        {Array.from({ length: lines }, (_, i) => (
          <div key={i} className="flex items-center gap-3">
            <Skeleton className="w-8 h-8 rounded-full flex-shrink-0" />
            <div className="flex-1">
              <Skeleton className="h-3.5 w-full rounded mb-1.5" />
              <Skeleton className="h-3 w-3/5 rounded" />
            </div>
          </div>
        ))}
      </div>
    </GlassCard>
  );
}

export default WidgetSkeleton;
