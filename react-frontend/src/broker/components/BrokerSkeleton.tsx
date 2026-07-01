// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * BrokerSkeleton — shaped loading placeholders so pages settle without
 * layout shift. Variants mirror the layouts broker pages actually use:
 *   stats  — a row of stat-card silhouettes
 *   table  — header bar + n rows
 *   cards  — n content-card silhouettes
 *   detail — header + two-column detail silhouette
 */

import { useTranslation } from 'react-i18next';
import { Card, Skeleton } from '@/components/ui';

interface BrokerSkeletonProps {
  variant?: 'stats' | 'table' | 'cards' | 'detail';
  /** stats: tile count · table: row count · cards: card count. */
  count?: number;
  className?: string;
}

function Bone({ className = '' }: { className?: string }) {
  return <Skeleton className={`rounded-md bg-surface-tertiary ${className}`} />;
}

const statGridCols = 'grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4';

export function BrokerSkeleton({ variant = 'table', count, className = '' }: BrokerSkeletonProps) {
  const { t } = useTranslation('broker');
  const wrapperProps = {
    role: 'status' as const,
    'aria-busy': true,
    'aria-label': t('common.loading'),
  };

  if (variant === 'stats') {
    const tiles = count ?? 4;
    return (
      <div {...wrapperProps} className={`${statGridCols} ${className}`}>
        {Array.from({ length: tiles }, (_, i) => (
          <Card key={i} className="rounded-2xl border border-divider/70 bg-surface shadow-sm shadow-black/[0.03]">
            <div className="flex items-center gap-4 p-4 sm:p-5">
              <Bone className="h-11 w-11 rounded-xl" />
              <div className="flex-1 space-y-2">
                <Bone className="h-3.5 w-24" />
                <Bone className="h-7 w-14" />
              </div>
            </div>
          </Card>
        ))}
      </div>
    );
  }

  if (variant === 'cards') {
    const cards = count ?? 3;
    return (
      <div {...wrapperProps} className={`space-y-4 ${className}`}>
        {Array.from({ length: cards }, (_, i) => (
          <Card key={i} className="rounded-2xl border border-divider/70 bg-surface shadow-sm shadow-black/[0.03]">
            <div className="space-y-3 p-4 sm:p-5">
              <div className="flex items-center gap-3">
                <Bone className="h-10 w-10 rounded-full" />
                <div className="flex-1 space-y-2">
                  <Bone className="h-4 w-40" />
                  <Bone className="h-3 w-24" />
                </div>
              </div>
              <Bone className="h-3.5 w-full" />
              <Bone className="h-3.5 w-2/3" />
            </div>
          </Card>
        ))}
      </div>
    );
  }

  if (variant === 'detail') {
    return (
      <div {...wrapperProps} className={`space-y-4 ${className}`}>
        <Card className="rounded-2xl border border-divider/70 bg-surface shadow-sm shadow-black/[0.03]">
          <div className="flex items-center gap-4 p-4 sm:p-5">
            <Bone className="h-12 w-12 rounded-xl" />
            <div className="flex-1 space-y-2">
              <Bone className="h-5 w-56" />
              <Bone className="h-3.5 w-80 max-w-full" />
            </div>
          </div>
        </Card>
        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
          {[0, 1].map((i) => (
            <Card key={i} className="rounded-2xl border border-divider/70 bg-surface shadow-sm shadow-black/[0.03]">
              <div className="space-y-3 p-4 sm:p-5">
                <Bone className="h-4 w-32" />
                <Bone className="h-3.5 w-full" />
                <Bone className="h-3.5 w-3/4" />
                <Bone className="h-3.5 w-1/2" />
              </div>
            </Card>
          ))}
        </div>
      </div>
    );
  }

  // table (default)
  const rows = count ?? 6;
  return (
    <Card
      {...wrapperProps}
      className={`rounded-2xl border border-divider/70 bg-surface shadow-sm shadow-black/[0.03] ${className}`}
    >
      <div className="p-4 sm:p-5">
        <div className="mb-4 flex items-center justify-between gap-4">
          <Bone className="h-4 w-40" />
          <Bone className="h-8 w-28 rounded-lg" />
        </div>
        <div className="space-y-3">
          {Array.from({ length: rows }, (_, i) => (
            <div key={i} className="flex items-center gap-4">
              <Bone className="h-9 w-9 rounded-full" />
              <Bone className="h-4 flex-1" />
              <Bone className="hidden h-4 w-24 sm:block" />
              <Bone className="h-6 w-16 rounded-full" />
            </div>
          ))}
        </div>
      </div>
    </Card>
  );
}

export default BrokerSkeleton;
