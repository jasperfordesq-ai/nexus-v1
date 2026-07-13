// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { dateLocale } from '@/lib/utils/dateLocale';

const ZERO_DECIMAL_MARKETPLACE_CURRENCIES = new Set([
  'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG',
  'RWF', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
]);

/** Format a marketplace amount that is already expressed in major units. */
export function formatMarketplaceCurrency(
  value: number,
  currency?: string | null,
): string {
  const amount = Number(value);
  const safeAmount = Number.isFinite(amount) ? amount : 0;
  const normalizedCurrency = currency?.trim().toUpperCase() ?? '';

  if (!normalizedCurrency) {
    return new Intl.NumberFormat(dateLocale()).format(safeAmount);
  }

  try {
    return new Intl.NumberFormat(dateLocale(), {
      style: 'currency',
      currency: normalizedCurrency,
    }).format(safeAmount);
  } catch {
    return `${normalizedCurrency} ${new Intl.NumberFormat(dateLocale()).format(safeAmount)}`;
  }
}

/**
 * Merchant coupon columns predate multi-currency support and store fixed
 * values in hundredths. Keep that legacy conversion isolated from normal
 * marketplace prices, which are already major-unit amounts.
 */
export function formatLegacyCouponMinorAmount(
  value: number,
  currency?: string | null,
): string {
  return formatMarketplaceCurrency(Number(value) / 100, currency);
}

/** Format a provider minor-unit amount using the marketplace currency exponent. */
export function formatMarketplaceMinorAmount(
  value: number,
  currency?: string | null,
): string {
  const normalizedCurrency = currency?.trim().toUpperCase() ?? '';
  const divisor = ZERO_DECIMAL_MARKETPLACE_CURRENCIES.has(normalizedCurrency) ? 1 : 100;
  return formatMarketplaceCurrency(Number(value) / divisor, normalizedCurrency);
}
