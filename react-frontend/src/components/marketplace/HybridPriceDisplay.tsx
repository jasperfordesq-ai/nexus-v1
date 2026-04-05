// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * HybridPriceDisplay — Prominent hybrid pricing display for marketplace.
 *
 * Shows currency price + time credits side by side with visual separation,
 * a time credit icon, and a tooltip explaining hybrid pricing.
 * This is a NEXUS differentiator: pay with a mix of cash and time credits.
 */

import { Tooltip, Chip } from '@heroui/react';
import { Clock, HelpCircle } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router-dom';
import { useTenant } from '@/contexts';

interface HybridPriceDisplayProps {
  /** Cash price amount */
  price: number;
  /** Currency code (e.g., 'EUR', 'USD') */
  currency: string;
  /** Time credit price amount */
  timeCreditPrice: number;
  /** Price type for context */
  priceType?: string;
  /** Size variant */
  size?: 'sm' | 'md' | 'lg';
}

/**
 * Format a price with Intl.NumberFormat for locale-aware display.
 */
function formatCurrency(price: number, currency: string): string {
  try {
    return new Intl.NumberFormat(undefined, {
      style: 'currency',
      currency,
      minimumFractionDigits: 0,
      maximumFractionDigits: 2,
    }).format(price);
  } catch {
    return `${currency} ${price}`;
  }
}

export function HybridPriceDisplay({
  price,
  currency,
  timeCreditPrice,
  priceType,
  size = 'md',
}: HybridPriceDisplayProps) {
  const { t } = useTranslation('marketplace');
  const { tenantPath } = useTenant();

  const sizeClasses = {
    sm: { price: 'text-base', tc: 'text-sm', icon: 'w-3.5 h-3.5', chip: 'text-xs' },
    md: { price: 'text-xl', tc: 'text-base', icon: 'w-4 h-4', chip: 'text-sm' },
    lg: { price: 'text-2xl', tc: 'text-lg', icon: 'w-5 h-5', chip: 'text-base' },
  };

  const classes = sizeClasses[size];

  return (
    <div className="flex flex-col gap-2">
      {/* Price row */}
      <div className="flex items-center gap-2 flex-wrap">
        {/* Cash portion */}
        <span className={`${classes.price} font-bold text-theme-primary`}>
          {formatCurrency(price, currency)}
        </span>

        {/* Separator */}
        <span className={`${classes.tc} font-medium text-theme-muted`}>+</span>

        {/* Time credit portion */}
        <Tooltip
          content={t(
            'hybrid_pricing.tooltip',
            'Time credits earned through community timebanking'
          )}
        >
          <div className="flex items-center gap-1.5 px-3 py-1 rounded-full bg-primary/10 border border-primary/20">
            <Clock className={`${classes.icon} text-primary`} />
            <span className={`${classes.tc} font-bold text-primary`}>
              {timeCreditPrice} TC
            </span>
          </div>
        </Tooltip>

        {/* Negotiable badge */}
        {priceType === 'negotiable' && (
          <Chip color="warning" variant="flat" size="sm">
            {t('price.negotiable', 'Negotiable')}
          </Chip>
        )}
      </div>

      {/* Explainer row */}
      <div className="flex items-center gap-1.5">
        <Tooltip
          content={t(
            'hybrid_pricing.explanation',
            'Hybrid pricing lets you pay with a combination of regular currency and time credits you have earned through community service.'
          )}
        >
          <span className="inline-flex items-center gap-1 text-xs text-theme-muted cursor-help">
            <HelpCircle className="w-3 h-3" />
            {t('hybrid_pricing.label', 'Pay with cash + time credits')}
          </span>
        </Tooltip>
        <span className="text-xs text-theme-muted">|</span>
        <Link
          to={tenantPath('/help')}
          className="text-xs text-primary hover:underline"
        >
          {t('hybrid_pricing.learn_more', 'What are time credits?')}
        </Link>
      </div>
    </div>
  );
}

export default HybridPriceDisplay;
