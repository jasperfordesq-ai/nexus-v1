// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * BrokerStatCard — the broker panel's KPI tile.
 *
 * One semantic color language across every page (see brokerStatus.tsx):
 *   accent  = exchanges / matching / general workload
 *   danger  = risk & safeguarding
 *   success = compliance (vetting, insurance)
 *   warning = messages / monitoring / expiring
 *
 * Features over the generic admin StatCard: animated count-up, optional
 * trend delta + sparkline, whole-card deep-link with hover lift, and a
 * skeleton loading state. Numbers animate only when motion is allowed
 * (useCountUp handles reduced-motion and test mode).
 */

import type { ReactNode } from 'react';
import { isValidElement } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import TrendingUp from 'lucide-react/icons/trending-up';
import TrendingDown from 'lucide-react/icons/trending-down';
import ChevronRight from 'lucide-react/icons/chevron-right';
import type { LucideIcon } from 'lucide-react';
import { Card, Skeleton } from '@/components/ui';
import { useCountUp } from './useCountUp';
import { BrokerSparkline } from './BrokerSparkline';

export type BrokerStatColor = 'accent' | 'success' | 'warning' | 'danger' | 'neutral';

interface BrokerStatCardProps {
  label: string;
  /** Numbers get an animated count-up + toLocaleString; strings render as-is. */
  value: number | string | null | undefined;
  icon: LucideIcon | ReactNode;
  color?: BrokerStatColor;
  loading?: boolean;
  /** Whole card becomes a link — use for drill-down into a pre-filtered list. */
  to?: string;
  /** Short helper line under the value (e.g. "3 need review today"). */
  description?: string;
  /** Percentage delta vs the previous period; renders ▲/▼ with color. */
  delta?: number;
  /** Label after the delta (e.g. "vs last month"). */
  deltaLabel?: string;
  /** Recent data points (oldest first) — renders a small sparkline. */
  trend?: number[];
  /** Accessible label when the card is a link; falls back to `label`. */
  linkAriaLabel?: string;
}

const tileClass: Record<BrokerStatColor, string> = {
  accent: 'text-accent bg-accent/10',
  success: 'text-success bg-success/10',
  warning: 'text-warning bg-warning/10',
  danger: 'text-danger bg-danger/10',
  neutral: 'text-muted bg-surface-tertiary',
};

const sparkClass: Record<BrokerStatColor, string> = {
  accent: 'text-accent',
  success: 'text-success',
  warning: 'text-warning',
  danger: 'text-danger',
  neutral: 'text-muted',
};

function AnimatedNumber({ value }: { value: number }) {
  const display = useCountUp(value);
  return <>{display.toLocaleString()}</>;
}

export function BrokerStatCard({
  label,
  value,
  icon: Icon,
  color = 'accent',
  loading = false,
  to,
  description,
  delta,
  deltaLabel,
  trend,
  linkAriaLabel,
}: BrokerStatCardProps) {
  const { t } = useTranslation('broker');

  const IconAsComponent = Icon as LucideIcon;
  const iconNode = isValidElement(Icon) ? Icon : <IconAsComponent size={22} />;

  const body = (
    <div className="flex w-full flex-row items-center gap-4 p-4 sm:p-5">
      <div
        className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ring-1 ring-inset ring-current/10 ${tileClass[color]}`}
      >
        {iconNode}
      </div>
      <div className="min-w-0 flex-1">
        <p className="truncate text-sm font-medium text-muted">{label}</p>
        {loading ? (
          <Skeleton
            role="status"
            aria-busy="true"
            aria-label={t('common.loading')}
            className="mt-1.5 h-7 w-16 rounded-md bg-surface-tertiary"
          />
        ) : (
          <p className="mt-0.5 text-2xl font-semibold tracking-tight text-foreground tabular-nums">
            {typeof value === 'number' ? <AnimatedNumber value={value} /> : (value ?? '—')}
          </p>
        )}
        {description && !loading && <p className="mt-0.5 truncate text-xs text-muted">{description}</p>}
        {delta !== undefined && !loading && (
          <span className="mt-0.5 flex items-center gap-1">
            {delta >= 0 ? (
              <TrendingUp size={13} className="text-success" aria-hidden="true" />
            ) : (
              <TrendingDown size={13} className="text-danger" aria-hidden="true" />
            )}
            <span className={`text-xs font-medium tabular-nums ${delta >= 0 ? 'text-success' : 'text-danger'}`}>
              {delta > 0 ? '+' : ''}
              {delta}%
            </span>
            {deltaLabel && <span className="truncate text-xs text-muted">{deltaLabel}</span>}
          </span>
        )}
      </div>
      <div className="flex shrink-0 flex-col items-end gap-1.5">
        {trend && trend.length >= 2 && !loading && (
          <BrokerSparkline points={trend} className={sparkClass[color]} />
        )}
        {to && (
          <ChevronRight
            size={16}
            className="text-muted/60 transition-transform group-hover:translate-x-0.5 group-hover:text-muted motion-reduce:transition-none"
            aria-hidden="true"
          />
        )}
      </div>
    </div>
  );

  if (to) {
    return (
      <Card
        isPressable
        as={Link}
        to={to}
        aria-label={linkAriaLabel ?? label}
        className="group rounded-2xl border border-divider/70 bg-surface text-left shadow-sm shadow-black/[0.03] transition-all hover:-translate-y-0.5 hover:border-divider hover:shadow-md motion-reduce:transition-none motion-reduce:hover:translate-y-0"
      >
        {body}
      </Card>
    );
  }

  return (
    <Card className="rounded-2xl border border-divider/70 bg-surface shadow-sm shadow-black/[0.03]">
      {body}
    </Card>
  );
}

export default BrokerStatCard;
