// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * PriceBadge - Price display component for marketplace listings
 *
 * Renders contextual price badges based on price type: free, fixed,
 * negotiable, contact, or hybrid (currency + time credits).
 */

import { Chip } from '@heroui/react';
import { useTranslation } from 'react-i18next';

interface PriceBadgeProps {
  price: number | null;
  currency: string;
  priceType: string;
  timeCreditPrice?: number | null;
}

/**
 * Formats a price value with the given currency symbol.
 * Uses Intl.NumberFormat for locale-aware formatting.
 */
function formatPrice(price: number, currency: string): string {
  try {
    return new Intl.NumberFormat(undefined, {
      style: 'currency',
      currency,
      minimumFractionDigits: 0,
      maximumFractionDigits: 2,
    }).format(price);
  } catch {
    // Fallback for unrecognised currency codes
    return `${currency} ${price}`;
  }
}

export function PriceBadge({ price, currency, priceType, timeCreditPrice }: PriceBadgeProps) {
  const { t } = useTranslation('marketplace');

  if (priceType === 'free') {
    return (
      <Chip color="success" variant="solid" size="sm" className="font-semibold">
        {t('price.free', 'Free')}
      </Chip>
    );
  }

  if (priceType === 'contact') {
    return (
      <Chip color="default" variant="flat" size="sm">
        {t('price.contact', 'Contact for price')}
      </Chip>
    );
  }

  if (price == null) {
    return null;
  }

  const formattedPrice = formatPrice(price, currency);
  const timeCreditSuffix =
    timeCreditPrice != null && timeCreditPrice > 0
      ? ` + ${timeCreditPrice} TC`
      : '';

  if (priceType === 'negotiable') {
    return (
      <span className="inline-flex items-center gap-1.5">
        <span className="text-lg font-bold text-theme-primary">
          {formattedPrice}{timeCreditSuffix}
        </span>
        <Chip color="warning" variant="flat" size="sm">
          {t('price.negotiable', 'Negotiable')}
        </Chip>
      </span>
    );
  }

  // Fixed or auction pricing
  return (
    <span className="text-lg font-bold text-theme-primary">
      {formattedPrice}{timeCreditSuffix}
    </span>
  );
}

export default PriceBadge;
