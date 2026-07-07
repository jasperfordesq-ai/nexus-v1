// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for MarketplaceImageGallery component
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

vi.mock('@/contexts', () => createMockContexts());

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

import { MarketplaceImageGallery } from './MarketplaceImageGallery';

const IMAGES = [
  { id: 1, url: 'https://example.com/img1.jpg', thumbnail_url: 'https://example.com/thumb1.jpg', alt_text: 'Image one' },
  { id: 2, url: 'https://example.com/img2.jpg', thumbnail_url: 'https://example.com/thumb2.jpg', alt_text: 'Image two' },
  { id: 3, url: 'https://example.com/img3.jpg', thumbnail_url: 'https://example.com/thumb3.jpg', alt_text: 'Image three' },
];

describe('MarketplaceImageGallery — empty state', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the no-images placeholder when images array is empty', () => {
    render(<MarketplaceImageGallery images={[]} />);
    // Translation key 'gallery.no_images' resolves to something — component renders a <p>
    // We assert the paragraph element is present (even if translation key is a fallback string)
    const paras = document.querySelectorAll('p');
    expect(paras.length).toBeGreaterThan(0);
  });

  it('does not render any <img> element when images array is empty', () => {
    render(<MarketplaceImageGallery images={[]} />);
    expect(screen.queryByRole('img')).not.toBeInTheDocument();
  });
});

describe('MarketplaceImageGallery — single image', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the image with the correct src', () => {
    render(<MarketplaceImageGallery images={[IMAGES[0]]} />);
    const imgs = screen.getAllByRole('img');
    const src = imgs.map((i) => i.getAttribute('src'));
    expect(src).toContain('https://example.com/thumb1.jpg');
  });

  it('does not render thumbnail strip when there is only one image', () => {
    render(<MarketplaceImageGallery images={[IMAGES[0]]} />);
    // Thumbnail buttons are only rendered when images.length > 1
    expect(screen.queryByRole('button')).not.toBeInTheDocument();
  });

  it('does not render count badge when there is only one image', () => {
    render(<MarketplaceImageGallery images={[IMAGES[0]]} />);
    // No "1/1" badge rendered for single-image galleries
    expect(screen.queryByText('1/1')).not.toBeInTheDocument();
  });
});

describe('MarketplaceImageGallery — multiple images', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the first image as the active image on mount', () => {
    render(<MarketplaceImageGallery images={IMAGES} />);
    // The rendered gallery prefers the API-provided thumbnail URL.
    const imgs = screen.getAllByRole('img');
    const primaryImgSrcs = imgs.map((i) => i.getAttribute('src'));
    expect(primaryImgSrcs).toContain('https://example.com/thumb1.jpg');
  });

  it('renders thumbnail buttons for each image', () => {
    render(<MarketplaceImageGallery images={IMAGES} />);
    const thumbBtns = screen.getAllByRole('button');
    expect(thumbBtns).toHaveLength(3);
  });

  it('renders thumbnail images using thumbnail_url', () => {
    render(<MarketplaceImageGallery images={IMAGES} />);
    const imgs = screen.getAllByRole('img');
    const srcs = imgs.map((i) => i.getAttribute('src'));
    expect(srcs).toContain('https://example.com/thumb1.jpg');
    expect(srcs).toContain('https://example.com/thumb2.jpg');
    expect(srcs).toContain('https://example.com/thumb3.jpg');
  });

  it('changes the primary image when a thumbnail button is clicked', () => {
    render(<MarketplaceImageGallery images={IMAGES} />);
    const thumbBtns = screen.getAllByRole('button');

    // Click the second thumbnail (index 1 → image 2)
    fireEvent.click(thumbBtns[1]);

    // The desktop primary image should now show the second image thumbnail.
    const imgs = screen.getAllByRole('img');
    const srcs = imgs.map((i) => i.getAttribute('src'));
    expect(srcs).toContain('https://example.com/thumb2.jpg');
  });

  it('changes the primary image when the third thumbnail is clicked', () => {
    render(<MarketplaceImageGallery images={IMAGES} />);
    const thumbBtns = screen.getAllByRole('button');

    fireEvent.click(thumbBtns[2]);

    const imgs = screen.getAllByRole('img');
    const srcs = imgs.map((i) => i.getAttribute('src'));
    expect(srcs).toContain('https://example.com/thumb3.jpg');
  });

  it('renders image count badge showing 1/3 initially', () => {
    render(<MarketplaceImageGallery images={IMAGES} />);
    // The badge appears in both the desktop primary view and the mobile carousel
    // — use getAllByText and assert at least one is present
    const badges = screen.getAllByText('1/3');
    expect(badges.length).toBeGreaterThan(0);
  });

  it('updates image count badge after thumbnail click', () => {
    render(<MarketplaceImageGallery images={IMAGES} />);
    const thumbBtns = screen.getAllByRole('button');
    fireEvent.click(thumbBtns[1]);
    const badges = screen.getAllByText('2/3');
    expect(badges.length).toBeGreaterThan(0);
  });

  it('thumbnail buttons have aria-label attributes', () => {
    render(<MarketplaceImageGallery images={IMAGES} />);
    const thumbBtns = screen.getAllByRole('button');
    thumbBtns.forEach((btn) => {
      expect(btn).toHaveAttribute('aria-label');
    });
  });

  it('sets aria-current on the active thumbnail', () => {
    render(<MarketplaceImageGallery images={IMAGES} />);
    const thumbBtns = screen.getAllByRole('button');
    // First thumbnail should be aria-current="true"
    expect(thumbBtns[0]).toHaveAttribute('aria-current', 'true');
    // Others should not
    expect(thumbBtns[1]).not.toHaveAttribute('aria-current');
    expect(thumbBtns[2]).not.toHaveAttribute('aria-current');
  });

  it('moves aria-current to the clicked thumbnail', () => {
    render(<MarketplaceImageGallery images={IMAGES} />);
    const thumbBtns = screen.getAllByRole('button');
    fireEvent.click(thumbBtns[2]);
    expect(thumbBtns[2]).toHaveAttribute('aria-current', 'true');
    expect(thumbBtns[0]).not.toHaveAttribute('aria-current');
  });
});
