// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Stat Card
 * Displays a key metric with label, value, and optional trend indicator.
 * When `to` is provided, the whole card becomes a clickable link that
 * drills into the relevant filtered view — a chevron hint is shown on hover.
 */

import { Card, CardBody } from '@heroui/react';
import { Link } from 'react-router-dom';
import ChevronRight from 'lucide-react/icons/chevron-right';
import TrendingUp from 'lucide-react/icons/trending-up';
import TrendingDown from 'lucide-react/icons/trending-down';
import type { LucideIcon } from 'lucide-react';

interface StatCardProps {
  label: string;
  value: string | number;
  icon: LucideIcon;
  trend?: number;
  trendLabel?: string;
  description?: string;
  color?: 'primary' | 'success' | 'warning' | 'danger' | 'secondary' | 'default';
  loading?: boolean;
  /** When set, the card acts as a react-router Link to this path. */
  to?: string;
  /** Accessible hint shown to screen readers when the card is a link. */
  linkAriaLabel?: string;
}

const colorMap = {
  primary: 'text-primary bg-primary/10',
  success: 'text-success bg-success/10',
  warning: 'text-warning bg-warning/10',
  danger: 'text-danger bg-danger/10',
  secondary: 'text-secondary bg-secondary/10',
  default: 'text-default-600 bg-default/20',
};

export function StatCard({
  label,
  value,
  icon: Icon,
  trend,
  trendLabel,
  description,
  color = 'primary',
  loading = false,
  to,
  linkAriaLabel,
}: StatCardProps) {
  const body = (
    <CardBody className="flex flex-row items-center gap-4 p-4">
      <div className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-xl ${colorMap[color]}`}>
        <Icon size={24} />
      </div>
      <div className="min-w-0 flex-1">
        <p className="text-sm text-default-500">{label}</p>
        {loading ? (
          <div className="mt-1 h-7 w-20 animate-pulse rounded bg-default-200" />
        ) : (
          <p className="text-2xl font-bold text-foreground">
            {typeof value === 'number' ? value.toLocaleString() : value}
          </p>
        )}
        {description && (
          <p className="mt-0.5 text-xs text-default-400">{description}</p>
        )}
        {trend !== undefined && (
          <div className="mt-0.5 flex items-center gap-1">
            {trend >= 0 ? (
              <TrendingUp size={14} className="text-success" />
            ) : (
              <TrendingDown size={14} className="text-danger" />
            )}
            <span className={`text-xs font-medium ${trend >= 0 ? 'text-success' : 'text-danger'}`}>
              {trend > 0 ? '+' : ''}{trend}%
            </span>
            {trendLabel && (
              <span className="text-xs text-default-400">{trendLabel}</span>
            )}
          </div>
        )}
      </div>
      {to && (
        <ChevronRight
          size={18}
          className="shrink-0 text-default-300 transition-transform group-hover:translate-x-0.5 group-hover:text-default-500"
          aria-hidden="true"
        />
      )}
    </CardBody>
  );

  if (to) {
    return (
      <Card
        shadow="sm"
        isPressable
        as={Link}
        to={to}
        aria-label={linkAriaLabel ?? label}
        className="group text-left transition-shadow hover:shadow-md"
      >
        {body}
      </Card>
    );
  }

  return <Card shadow="sm">{body}</Card>;
}

export default StatCard;
