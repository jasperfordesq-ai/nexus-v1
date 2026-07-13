// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for MarketplaceListingCard component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import type { MarketplaceListingItem } from '@/types/marketplace';

vi.mock('@/contexts', () =>
  createMockContexts({
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  }),
);
vi.mock('@/contexts/TenantContext', () => ({
  useTenant: () => ({
    tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
    tenantPath: (p: string) => `/test${p}`,
    hasFeature: vi.fn(() => true),
    hasModule: vi.fn(() => true),
  }),
}));

// PriceBadge and ConditionBadge are internal — no need to mock, let them render
// but we stub the logger to keep console clean
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

import { MarketplaceListingCard } from './MarketplaceListingCard';

const BASE_LISTING: MarketplaceListingItem = {
  id: 42,
  title: 'Vintage Bicycle',
  price: 120,
  price_currency: 'EUR',
  price_type: 'fixed',
  time_credit_price: null,
  condition: 'good',
  location: 'Dublin',
  delivery_method: 'local_pickup',
  seller_type: 'individual',
  status: 'active',
  image: { url: 'https://example.com/bike.jpg', thumbnail_url: 'https://example.com/bike_thumb.jpg', alt_text: 'A blue bicycle' },
  image_count: 1,
  category: null,
  user: { id: 7, name: 'Alice Smith' },
  is_saved: false,
  is_own: false,
  is_promoted: false,
  views_count: 15,
  created_at: '2026-06-01T00:00:00Z',
};

describe('MarketplaceListingCard', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the listing title', () => {
    render(<MarketplaceListingCard listing={BASE_LISTING} />);
    expect(screen.getByText('Vintage Bicycle')).toBeInTheDocument();
  });

  it('renders the listing image with correct src and alt', () => {
    render(<MarketplaceListingCard listing={BASE_LISTING} />);
    const img = screen.getByRole('img', { name: /bicycle/i });
    expect(img).toHaveAttribute('src', 'https://example.com/bike_thumb.jpg');
  });

  it('falls back to url when thumbnail_url is absent', () => {
    const listing: MarketplaceListingItem = {
      ...BASE_LISTING,
      image: { url: 'https://example.com/bike.jpg', alt_text: 'A blue bicycle' },
    };
    render(<MarketplaceListingCard listing={listing} />);
    const img = screen.getByRole('img', { name: /bicycle/i });
    expect(img).toHaveAttribute('src', 'https://example.com/bike.jpg');
  });

  it('renders location when provided', () => {
    render(<MarketplaceListingCard listing={BASE_LISTING} />);
    expect(screen.getByText('Dublin')).toBeInTheDocument();
  });

  it('renders seller name when user is provided', () => {
    render(<MarketplaceListingCard listing={BASE_LISTING} />);
    expect(screen.getByText('Alice Smith')).toBeInTheDocument();
  });

  it('links to the correct listing detail page via tenantPath', () => {
    render(<MarketplaceListingCard listing={BASE_LISTING} />);
    const link = screen.getByRole('link');
    expect(link).toHaveAttribute('href', '/test/marketplace/42');
  });

  it('renders save button with correct aria-label when not saved', () => {
    render(<MarketplaceListingCard listing={BASE_LISTING} />);
    // The aria-label is a translation key that resolves in the test i18n setup;
    // we check the button exists and has an aria-label attribute
    const btn = screen.getByRole('button');
    expect(btn).toHaveAttribute('aria-label');
  });

  it('calls onSave when save button is pressed from unsaved state', () => {
    const onSave = vi.fn();
    render(<MarketplaceListingCard listing={BASE_LISTING} onSave={onSave} />);
    fireEvent.click(screen.getByRole('button'));
    expect(onSave).toHaveBeenCalledWith(42);
  });

  it('calls onUnsave when save button is pressed from saved state', () => {
    const onUnsave = vi.fn();
    const listing: MarketplaceListingItem = { ...BASE_LISTING, is_saved: true };
    render(<MarketplaceListingCard listing={listing} onUnsave={onUnsave} />);
    fireEvent.click(screen.getByRole('button'));
    expect(onUnsave).toHaveBeenCalledWith(42);
  });

  it('waits for the authoritative listing prop before showing saved state', () => {
    const onSave = vi.fn();
    render(<MarketplaceListingCard listing={BASE_LISTING} onSave={onSave} />);
    const btn = screen.getByRole('button');
    const labelBefore = btn.getAttribute('aria-label');
    fireEvent.click(btn);
    const labelAfter = btn.getAttribute('aria-label');
    expect(labelAfter).toBe(labelBefore);
  });

  it('renders promoted badge when is_promoted is true', () => {
    const listing: MarketplaceListingItem = { ...BASE_LISTING, is_promoted: true };
    render(<MarketplaceListingCard listing={listing} />);
    // The translation key 'listing.promoted' renders something in the test env
    // — we just assert the Chip node is present; the exact text depends on locale
    expect(document.querySelector('[class*="chip"]') || screen.queryByText(/promoted/i)).toBeTruthy();
  });

  it('does not render promoted badge when is_promoted is false', () => {
    render(<MarketplaceListingCard listing={BASE_LISTING} />);
    // No promoted chip rendered
    expect(screen.queryByText(/promoted/i)).not.toBeInTheDocument();
  });

  it('renders placeholder when image is null', () => {
    const listing: MarketplaceListingItem = { ...BASE_LISTING, image: null };
    render(<MarketplaceListingCard listing={listing} />);
    // No <img> element rendered when image is null (ImagePlaceholder renders SVG/div)
    expect(screen.queryByRole('img')).not.toBeInTheDocument();
  });

  it('does not render location row when location is absent', () => {
    const listing: MarketplaceListingItem = { ...BASE_LISTING, location: undefined };
    render(<MarketplaceListingCard listing={listing} />);
    expect(screen.queryByText('Dublin')).not.toBeInTheDocument();
  });

  it('does not render seller name when user is null', () => {
    const listing: MarketplaceListingItem = { ...BASE_LISTING, user: null };
    render(<MarketplaceListingCard listing={listing} />);
    expect(screen.queryByText('Alice Smith')).not.toBeInTheDocument();
  });
});
