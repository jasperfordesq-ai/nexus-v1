// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Link } from 'react-router-dom';
import { Skeleton } from '@heroui/react';
import ArrowRight from 'lucide-react/icons/arrow-right';
import HandHeart from 'lucide-react/icons/hand-heart';
import Sparkles from 'lucide-react/icons/sparkles';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { useTenant } from '@/contexts';
import { useApi } from '@/hooks/useApi';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface ReciprocalBalanceData {
  lifetime_given: number;
  lifetime_received: number;
  net_balance: number;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function formatHours(hours: number): string {
  return hours.toLocaleString(undefined, {
    minimumFractionDigits: hours % 1 === 0 ? 0 : 1,
    maximumFractionDigits: 1,
  });
}

// ---------------------------------------------------------------------------
// Widget
// ---------------------------------------------------------------------------

/**
 * Compact embeddable card showing the member's reciprocal balance — given
 * vs received hours, with a friendly "net banked" line and a link through
 * to the full Future Care Fund page.
 *
 * Designed to fit a 320px sidebar slot. Self-fetches its data.
 */
export function ReciprocalBalanceWidget() {
  const { t } = useTranslation('common');
  const { hasFeature, tenantPath } = useTenant();

  const { data, isLoading, error } = useApi<ReciprocalBalanceData>(
    '/api/v2/caring-community/my-future-care-fund',
    { immediate: hasFeature('caring_community') },
  );

  if (!hasFeature('caring_community')) return null;

  if (isLoading) {
    return (
      <GlassCard className="p-4">
        <Skeleton className="mb-3 h-4 w-1/2 rounded" />
        <Skeleton className="mb-2 h-3 w-3/4 rounded" />
        <Skeleton className="mb-3 h-3 w-3/4 rounded" />
        <Skeleton className="h-3 w-1/2 rounded" />
      </GlassCard>
    );
  }

  if (error || !data) return null;

  const isPositive = data.net_balance >= 0;
  const absNet = Math.abs(data.net_balance);

  return (
    <GlassCard className="p-4 sm:p-5">
      <div className="mb-3 flex items-center gap-2">
        <div
          className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-rose-500/15"
          aria-hidden="true"
        >
          <HandHeart className="h-4 w-4 text-rose-600 dark:text-rose-400" />
        </div>
        <h3 className="text-sm font-semibold text-theme-primary">
          {t('reciprocity.title')}
        </h3>
      </div>

      <ul className="space-y-1.5 text-sm text-theme-muted">
        <li>
          {t('reciprocity.given', { hours: formatHours(data.lifetime_given) })}
        </li>
        <li>
          {t('reciprocity.received', { hours: formatHours(data.lifetime_received) })}
        </li>
      </ul>

      <div className="mt-3 flex items-center gap-1.5 text-sm font-semibold text-theme-primary">
        {isPositive && (
          <Sparkles
            className="h-4 w-4 text-amber-500 dark:text-amber-400"
            aria-hidden="true"
          />
        )}
        <span>
          {isPositive
            ? t('reciprocity.net_positive', { hours: formatHours(absNet) })
            : t('reciprocity.net_negative', { hours: `-${formatHours(absNet)}` })}
        </span>
      </div>

      <Link
        to={tenantPath('/caring-community/future-care-fund')}
        className="mt-4 inline-flex items-center gap-1 text-sm font-medium text-[var(--color-primary)] hover:underline"
      >
        {t('reciprocity.view_fund')}
        <ArrowRight className="h-3.5 w-3.5" aria-hidden="true" />
      </Link>
    </GlassCard>
  );
}

export default ReciprocalBalanceWidget;
