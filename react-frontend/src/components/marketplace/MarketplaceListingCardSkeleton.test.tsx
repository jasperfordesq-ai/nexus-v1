// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render } from '@/test/test-utils';
import React from 'react';

// ─── Stub HeroUI Skeleton so jsdom renders predictable markup ────────────────
vi.mock('@heroui/react', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@heroui/react')>();
  return {
    ...orig,
    Skeleton: ({ className }: { className?: string }) => (
      <div data-testid="skeleton" className={className} aria-hidden="true" />
    ),
    Card: ({ children, className }: { children: React.ReactNode; className?: string }) => (
      <div data-testid="card" className={className}>{children}</div>
    ),
    CardBody: ({ children, className }: { children: React.ReactNode; className?: string }) => (
      <div data-testid="card-body" className={className}>{children}</div>
    ),
  };
});

// Also stub the @/components/ui barrel (re-exports HeroUI components)
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...orig,
    Skeleton: ({ className }: { className?: string }) => (
      <div data-testid="skeleton" className={className} aria-hidden="true" />
    ),
    Card: ({ children, className }: { children: React.ReactNode; className?: string }) => (
      <div data-testid="card" className={className}>{children}</div>
    ),
    CardBody: ({ children, className }: { children: React.ReactNode; className?: string }) => (
      <div data-testid="card-body" className={className}>{children}</div>
    ),
  };
});

// ─────────────────────────────────────────────────────────────────────────────
describe('MarketplaceListingCardSkeleton', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders a Card container', async () => {
    const { MarketplaceListingCardSkeleton } = await import('./MarketplaceListingCardSkeleton');
    const { getByTestId } = render(<MarketplaceListingCardSkeleton />);
    expect(getByTestId('card')).toBeInTheDocument();
  });

  it('renders a CardBody', async () => {
    const { MarketplaceListingCardSkeleton } = await import('./MarketplaceListingCardSkeleton');
    const { getByTestId } = render(<MarketplaceListingCardSkeleton />);
    expect(getByTestId('card-body')).toBeInTheDocument();
  });

  it('renders multiple Skeleton placeholders (≥ 5)', async () => {
    const { MarketplaceListingCardSkeleton } = await import('./MarketplaceListingCardSkeleton');
    const { getAllByTestId } = render(<MarketplaceListingCardSkeleton />);
    const skeletons = getAllByTestId('skeleton');
    // image area + price badge + condition badge + 2 title lines + location icon + location text + seller = 8
    expect(skeletons.length).toBeGreaterThanOrEqual(5);
  });

  it('all skeleton placeholders are aria-hidden', async () => {
    const { MarketplaceListingCardSkeleton } = await import('./MarketplaceListingCardSkeleton');
    const { getAllByTestId } = render(<MarketplaceListingCardSkeleton />);
    const skeletons = getAllByTestId('skeleton');
    skeletons.forEach((el) => {
      expect(el.getAttribute('aria-hidden')).toBe('true');
    });
  });

  it('includes an aspect-video image area', async () => {
    const { MarketplaceListingCardSkeleton } = await import('./MarketplaceListingCardSkeleton');
    const { container } = render(<MarketplaceListingCardSkeleton />);
    const imageArea = container.querySelector('.aspect-video');
    expect(imageArea).not.toBeNull();
  });

  it('has a skeleton that fills the image area (w-full h-full)', async () => {
    const { MarketplaceListingCardSkeleton } = await import('./MarketplaceListingCardSkeleton');
    const { getAllByTestId } = render(<MarketplaceListingCardSkeleton />);
    const skeletons = getAllByTestId('skeleton');
    const fullAreaSkeleton = skeletons.find(
      (el) => el.className.includes('w-full') && el.className.includes('h-full'),
    );
    expect(fullAreaSkeleton).toBeDefined();
  });

  it('includes a price badge skeleton (rounded-full)', async () => {
    const { MarketplaceListingCardSkeleton } = await import('./MarketplaceListingCardSkeleton');
    const { getAllByTestId } = render(<MarketplaceListingCardSkeleton />);
    const skeletons = getAllByTestId('skeleton');
    const roundedFull = skeletons.filter((el) => el.className.includes('rounded-full'));
    // price badge + condition badge + location icon = at least 2 rounded-full skeletons
    expect(roundedFull.length).toBeGreaterThanOrEqual(2);
  });

  it('includes title-line skeletons (full-width and 3/4-width)', async () => {
    const { MarketplaceListingCardSkeleton } = await import('./MarketplaceListingCardSkeleton');
    const { getAllByTestId } = render(<MarketplaceListingCardSkeleton />);
    const skeletons = getAllByTestId('skeleton');
    const fullWidth = skeletons.filter((el) => el.className.includes('w-full'));
    expect(fullWidth.length).toBeGreaterThanOrEqual(1);
    const threeQuarter = skeletons.filter((el) => el.className.includes('w-3/4'));
    expect(threeQuarter.length).toBeGreaterThanOrEqual(1);
  });

  it('renders without crashing when called multiple times', async () => {
    const { MarketplaceListingCardSkeleton } = await import('./MarketplaceListingCardSkeleton');
    const { getAllByTestId } = render(
      <>
        <MarketplaceListingCardSkeleton />
        <MarketplaceListingCardSkeleton />
        <MarketplaceListingCardSkeleton />
      </>,
    );
    const cards = getAllByTestId('card');
    expect(cards.length).toBe(3);
  });

  it('card has border and bg-surface classes', async () => {
    const { MarketplaceListingCardSkeleton } = await import('./MarketplaceListingCardSkeleton');
    const { getByTestId } = render(<MarketplaceListingCardSkeleton />);
    const card = getByTestId('card');
    expect(card.className).toContain('border');
    expect(card.className).toContain('bg-surface');
  });

  it('contains positioned badge containers (absolute positioning)', async () => {
    const { MarketplaceListingCardSkeleton } = await import('./MarketplaceListingCardSkeleton');
    const { container } = render(<MarketplaceListingCardSkeleton />);
    const absoluteEls = container.querySelectorAll('.absolute');
    // price badge (bottom-2 left-2) and condition badge (top-2 left-2)
    expect(absoluteEls.length).toBeGreaterThanOrEqual(2);
  });
});
