// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import { MarketplaceListingDetailSkeleton } from './MarketplaceListingDetailSkeleton';

vi.mock('@/contexts', () => createMockContexts());

describe('MarketplaceListingDetailSkeleton', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing', () => {
    const { container } = render(<MarketplaceListingDetailSkeleton />);
    expect(container.firstChild).not.toBeNull();
  });

  it('renders four thumbnail skeleton blocks (w-16 h-16 each)', () => {
    const { container } = render(<MarketplaceListingDetailSkeleton />);
    // The source creates 4 thumbnail skeletons with class "w-16 h-16"
    const thumbnails = container.querySelectorAll('.w-16.h-16');
    expect(thumbnails).toHaveLength(4);
  });

  it('renders the main image skeleton (aspect-video)', () => {
    const { container } = render(<MarketplaceListingDetailSkeleton />);
    const mainImage = container.querySelector('.aspect-video');
    expect(mainImage).toBeInTheDocument();
  });

  it('renders description content skeleton rows', () => {
    const { container } = render(<MarketplaceListingDetailSkeleton />);
    // Description section has a space-y-2 container with 5 skeleton rows
    const descSection = container.querySelector('.space-y-2');
    expect(descSection).not.toBeNull();
    // 5 skeleton lines in the description block
    expect(descSection!.children).toHaveLength(5);
  });

  it('renders multiple GlassCard containers for sidebar sections', () => {
    const { container } = render(<MarketplaceListingDetailSkeleton />);
    // There are 3 GlassCard instances: price/title, seller, description.
    // GlassCard renders with a "glass-card" or similar class — check via p-5/p-6 classes.
    const glassCards = container.querySelectorAll('.p-5, .p-6');
    // price/title card (p-5), seller card (p-5), description card (p-6)
    expect(glassCards.length).toBeGreaterThanOrEqual(3);
  });

  it('renders action button skeleton slots', () => {
    const { container } = render(<MarketplaceListingDetailSkeleton />);
    // The action button area has a full-width skeleton + a flex row with two skeletons
    // The h-10 class is used for action-button-height skeletons
    const actionSkeletons = container.querySelectorAll('.h-10');
    // Expect at least 3 action skeletons (1 full-width + 2 side-by-side)
    expect(actionSkeletons.length).toBeGreaterThanOrEqual(3);
  });

  it('renders breadcrumb placeholder at the top', () => {
    const { container } = render(<MarketplaceListingDetailSkeleton />);
    // The breadcrumb row is the first flex child inside the outer container
    const outerDiv = container.firstChild as HTMLElement;
    const breadcrumbRow = outerDiv.firstElementChild;
    expect(breadcrumbRow).not.toBeNull();
    expect(breadcrumbRow!.classList.contains('flex')).toBe(true);
    // It contains exactly 3 children: back button, separator, label
    expect(breadcrumbRow!.children).toHaveLength(3);
  });

  it('renders a 5-column grid for the main content (image + sidebar)', () => {
    const { container } = render(<MarketplaceListingDetailSkeleton />);
    const grid = container.querySelector('.grid.grid-cols-1');
    expect(grid).toBeInTheDocument();
  });

  it('renders the seller avatar skeleton (rounded-full)', () => {
    const { container } = render(<MarketplaceListingDetailSkeleton />);
    const avatar = container.querySelector('.rounded-full');
    expect(avatar).toBeInTheDocument();
  });
});
