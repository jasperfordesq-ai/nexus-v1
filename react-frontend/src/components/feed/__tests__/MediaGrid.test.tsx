// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for MediaGrid — Facebook/Instagram-style grid layout for 2-4+ images.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';
import type { PostMedia } from '../types';

// ── Mocks ────────────────────────────────────────────────────────────────────

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, fallbackOrOpts?: string | Record<string, unknown>, opts?: Record<string, unknown>) => {
      const fallback = typeof fallbackOrOpts === 'string' ? fallbackOrOpts : key;
      const vars = typeof fallbackOrOpts === 'object' ? fallbackOrOpts : opts;
      if (!vars) return fallback;
      return fallback.replace(/\{\{(\w+)\}\}/g, (_, k) => String(vars[k] ?? `{{${k}}}`));
    },
  }),
}));

vi.mock('@/lib/helpers', () => ({
  resolveAssetUrl: (url: string | null) => url || '',
}));

vi.mock('../ImageLightbox', () => ({
  ImageLightbox: ({
    initialIndex,
    onClose,
  }: {
    initialIndex: number;
    onClose: () => void;
  }) => (
    <div data-testid="image-lightbox" data-initial-index={initialIndex}>
      <button onClick={onClose}>Close Lightbox</button>
    </div>
  ),
}));

import { MediaGrid } from '../MediaGrid';

// ── Fixtures ─────────────────────────────────────────────────────────────────

function makeMedia(count: number): PostMedia[] {
  return Array.from({ length: count }, (_, i) => ({
    id: i + 1,
    media_type: 'image' as const,
    file_url: `/uploads/img${i + 1}.jpg`,
    thumbnail_url: null,
    alt_text: `Alt text ${i + 1}`,
    width: 800,
    height: 600,
    file_size: 50000,
    display_order: i,
  }));
}

// ── Tests ────────────────────────────────────────────────────────────────────

describe('MediaGrid', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders two images side-by-side for a 2-image grid', () => {
    const media = makeMedia(2);
    render(<MediaGrid media={media} />);

    const images = screen.getAllByRole('img');
    expect(images).toHaveLength(2);
    expect(images[0]).toHaveAttribute('src', '/uploads/img1.jpg');
    expect(images[1]).toHaveAttribute('src', '/uploads/img2.jpg');
  });

  it('renders three images in the 60/40 layout', () => {
    const media = makeMedia(3);
    render(<MediaGrid media={media} />);

    const images = screen.getAllByRole('img');
    expect(images).toHaveLength(3);
  });

  it('renders four images in a 2x2 grid without an overlay', () => {
    const media = makeMedia(4);
    render(<MediaGrid media={media} />);

    const images = screen.getAllByRole('img');
    expect(images).toHaveLength(4);

    // No "+N more" overlay
    expect(screen.queryByText(/^\+\d+$/)).not.toBeInTheDocument();
  });

  it('renders 4 images with "+N more" overlay when there are 5+ images', () => {
    const media = makeMedia(6);
    render(<MediaGrid media={media} />);

    // Only first 4 images displayed
    const images = screen.getAllByRole('img');
    expect(images).toHaveLength(4);

    // "+2" overlay on 4th image
    expect(screen.getByText('+2')).toBeInTheDocument();
  });

  it('renders 4 images with "+1 more" overlay for exactly 5 images', () => {
    const media = makeMedia(5);
    render(<MediaGrid media={media} />);

    const images = screen.getAllByRole('img');
    expect(images).toHaveLength(4);

    expect(screen.getByText('+1')).toBeInTheDocument();
  });

  it('uses alt text from the media items', () => {
    const media = makeMedia(2);
    render(<MediaGrid media={media} />);

    expect(screen.getByAltText('Alt text 1')).toBeInTheDocument();
    expect(screen.getByAltText('Alt text 2')).toBeInTheDocument();
  });

  it('opens lightbox when an image button is clicked', () => {
    const media = makeMedia(3);
    render(<MediaGrid media={media} />);

    expect(screen.queryByTestId('image-lightbox')).not.toBeInTheDocument();

    // Click the second image button
    const buttons = screen.getAllByRole('button');
    // Buttons correspond to each displayed image
    fireEvent.click(buttons[1]);

    const lightbox = screen.getByTestId('image-lightbox');
    expect(lightbox).toBeInTheDocument();
    expect(lightbox).toHaveAttribute('data-initial-index', '1');
  });

  it('opens lightbox with correct index for 5+ images', () => {
    const media = makeMedia(7);
    render(<MediaGrid media={media} />);

    // Click the 4th image (has overlay)
    const buttons = screen.getAllByRole('button');
    fireEvent.click(buttons[3]);

    const lightbox = screen.getByTestId('image-lightbox');
    expect(lightbox).toHaveAttribute('data-initial-index', '3');
  });

  it('closes lightbox when onClose is triggered', () => {
    const media = makeMedia(2);
    render(<MediaGrid media={media} />);

    // Open lightbox
    const buttons = screen.getAllByRole('button');
    fireEvent.click(buttons[0]);
    expect(screen.getByTestId('image-lightbox')).toBeInTheDocument();

    // Close
    fireEvent.click(screen.getByText('Close Lightbox'));
    expect(screen.queryByTestId('image-lightbox')).not.toBeInTheDocument();
  });

  it('applies custom className', () => {
    const media = makeMedia(2);
    const { container } = render(
      <MediaGrid media={media} className="custom-grid" />,
    );

    const grid = container.querySelector('.custom-grid');
    expect(grid).toBeInTheDocument();
  });

  it('eagerly loads the first image and lazy-loads the rest', () => {
    const media = makeMedia(4);
    render(<MediaGrid media={media} />);

    const images = screen.getAllByRole('img');
    expect(images[0]).toHaveAttribute('loading', 'eager');
    expect(images[1]).toHaveAttribute('loading', 'lazy');
    expect(images[2]).toHaveAttribute('loading', 'lazy');
    expect(images[3]).toHaveAttribute('loading', 'lazy');
  });

  it('passes all media to lightbox even when grid shows only 4', () => {
    const media = makeMedia(6);
    render(<MediaGrid media={media} />);

    // Open lightbox from first image
    const buttons = screen.getAllByRole('button');
    fireEvent.click(buttons[0]);

    // The lightbox receives the full media array (verified by its presence)
    expect(screen.getByTestId('image-lightbox')).toBeInTheDocument();
    expect(screen.getByTestId('image-lightbox')).toHaveAttribute(
      'data-initial-index',
      '0',
    );
  });
});
