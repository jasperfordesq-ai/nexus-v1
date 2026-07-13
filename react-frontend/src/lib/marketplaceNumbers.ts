// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type {
  MarketplaceListingItem,
  MarketplaceShippingOption,
} from '@/types/marketplace';
import { getFormattingLocale } from '@/lib/helpers';

export const SUPPORTED_MARKETPLACE_CURRENCIES = [
  'EUR', 'GBP', 'USD', 'CAD', 'AUD', 'NZD', 'CHF', 'SEK', 'NOK', 'DKK', 'PLN', 'JPY',
] as const;

export function normalizeSupportedMarketplaceCurrency(value?: string | null): string {
  const candidate = value?.trim().toUpperCase() ?? '';
  return SUPPORTED_MARKETPLACE_CURRENCIES.includes(
    candidate as (typeof SUPPORTED_MARKETPLACE_CURRENCIES)[number],
  ) ? candidate : '';
}

/** Format a marketplace amount that is already expressed in major units. */
export function formatMarketplaceCurrency(
  value: number,
  currency: string,
  options?: Omit<Intl.NumberFormatOptions, 'style' | 'currency'>,
): string {
  const normalizedCurrency = currency.trim().toUpperCase();
  const amount = Number(value);

  try {
    return new Intl.NumberFormat(getFormattingLocale(), {
      style: 'currency',
      currency: normalizedCurrency,
      ...options,
    }).format(Number.isFinite(amount) ? amount : 0);
  } catch {
    const formatted = new Intl.NumberFormat(getFormattingLocale()).format(
      Number.isFinite(amount) ? amount : 0,
    );
    return `${normalizedCurrency} ${formatted}`.trim();
  }
}

/**
 * Laravel serialises decimal database columns as strings. Marketplace UI code
 * performs arithmetic on those values, so normalise them at the API boundary.
 */
export function toFiniteMarketplaceNumber(
  value: unknown,
  fallback: number | null = null,
): number | null {
  if (value === null || value === undefined || value === '') return fallback;

  const parsed = typeof value === 'number' ? value : Number(value);
  return Number.isFinite(parsed) ? parsed : fallback;
}

type MarketplaceNumericListing = Pick<MarketplaceListingItem, 'price' | 'views_count'> &
  Partial<Pick<MarketplaceListingItem,
    'time_credit_price' | 'inventory_count' | 'low_stock_threshold'>>;

export function normalizeMarketplaceListing<T extends MarketplaceNumericListing>(listing: T): T {
  return {
    ...listing,
    price: toFiniteMarketplaceNumber(listing.price),
    time_credit_price: toFiniteMarketplaceNumber(listing.time_credit_price),
    inventory_count: toFiniteMarketplaceNumber(listing.inventory_count),
    low_stock_threshold: toFiniteMarketplaceNumber(listing.low_stock_threshold),
    views_count: toFiniteMarketplaceNumber(listing.views_count, 0) ?? 0,
  } as T;
}

export function normalizeMarketplaceShippingOption(
  option: MarketplaceShippingOption,
): MarketplaceShippingOption {
  return {
    ...option,
    price: Math.max(0, toFiniteMarketplaceNumber(option.price, 0) ?? 0),
    estimated_days: option.estimated_days == null
      ? undefined
      : Math.max(0, toFiniteMarketplaceNumber(option.estimated_days, 0) ?? 0),
  };
}
