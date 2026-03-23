// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for ImageCarousel — swipeable image carousel for post images.
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
  ImageLightbox: ({ onClose }: { onClose: () => void }) => (
    <div data-testid="image-lightbox">
      <button onClick={onClose}>Close</button>
    </div>
  ),
}));

vi.mock('framer-motion', () => ({
  motion: {
    div: ({ children, onClick, ...props }: Record<string, unknown>) => {
      const safe: Record<string, unknown> = {};
      const skip = new Set([
        'variants', 'initial', 'animate', 'exit', 'transition',
        'custom', 'drag', 'dragConstraints', 'dragElastic', 'onDragEnd',
        'whileHover', 'whileTap', 'whileInView', 'viewport',
      ]);
      for (const [k, v] of Object.entries(props)) {
        if (!skip.has(k)) safe[k] = v;
      }
      return (
        <div {...safe} onClick={onClick as React.MouseEventHandler}>
          {children as React.ReactNode}
        </div>
      );
    },
  },
  AnimatePresence: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

import { ImageCarousel } from '../ImageCarousel';

// ── Fixtures ─────────────────────────────────────────────────────────────────

function makeMedia(count: number): PostMedia[] {
  return Array.from({ length: count }, (_, i) => ({
    id: i + 1,
    media_type: 'image' as const,
    file_url: `/uploads/img${i + 1}.jpg`,
    thumbnail_url: null,
    alt_text: i === 0 ? 'First image alt' : null,
    width: 800,
    height: 600,
    file_size: 50000,
    display_order: i,
  }));
}

// ── Tests ────────────────────────────────────────────────────────────────────

describe('ImageCarousel', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders nothing when media array is empty', () => {
    render(<ImageCarousel media={[]} />);
    // No carousel region — the Notifications region from ToastProvider still exists
    expect(screen.queryByRole('region', { name: /carousel/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('img')).not.toBeInTheDocument();
  });

  it('renders a single image without navigation controls', () => {
    const media = makeMedia(1);
    render(<ImageCarousel media={media} />);

    const img = screen.getByRole('img');
    expect(img).toBeInTheDocument();
    expect(img).toHaveAttribute('src', '/uploads/img1.jpg');
    expect(img).toHaveAttribute('alt', 'First image alt');

    // No arrows or dots for single image
    expect(screen.queryByLabelText('Previous image')).not.toBeInTheDocument();
    expect(screen.queryByLabelText('Next image')).not.toBeInTheDocument();
    // No counter badge
    expect(screen.queryByText('1/1')).not.toBeInTheDocument();
  });

  it('renders counter badge and dot indicators for multiple images', () => {
    const media = makeMedia(3);
    render(<ImageCarousel media={media} />);

    // Counter badge "1/3"
    expect(screen.getByText('1/3')).toBeInTheDocument();

    // Dot indicators — one per image
    const dots = screen.getAllByLabelText(/Go to image/);
    expect(dots).toHaveLength(3);
  });

  it('shows next arrow but not previous arrow on first image', () => {
    const media = makeMedia(3);
    render(<ImageCarousel media={media} />);

    expect(screen.queryByLabelText('Previous image')).not.toBeInTheDocument();
    expect(screen.getByLabelText('Next image')).toBeInTheDocument();
  });

  it('navigates to next image when next arrow is clicked', () => {
    const media = makeMedia(3);
    render(<ImageCarousel media={media} />);

    fireEvent.click(screen.getByLabelText('Next image'));

    // Counter should update to 2/3
    expect(screen.getByText('2/3')).toBeInTheDocument();
    // Previous arrow now visible
    expect(screen.getByLabelText('Previous image')).toBeInTheDocument();
  });

  it('navigates to previous image when previous arrow is clicked', () => {
    const media = makeMedia(3);
    render(<ImageCarousel media={media} />);

    // Navigate to second image
    fireEvent.click(screen.getByLabelText('Next image'));
    expect(screen.getByText('2/3')).toBeInTheDocument();

    // Navigate back
    fireEvent.click(screen.getByLabelText('Previous image'));
    expect(screen.getByText('1/3')).toBeInTheDocument();
  });

  it('navigates when dot indicator is clicked', () => {
    const media = makeMedia(3);
    render(<ImageCarousel media={media} />);

    // Click dot for image 3
    fireEvent.click(screen.getByLabelText('Go to image 3'));
    expect(screen.getByText('3/3')).toBeInTheDocument();
  });

  it('does not show next arrow on the last image', () => {
    const media = makeMedia(2);
    render(<ImageCarousel media={media} />);

    // Go to last image
    fireEvent.click(screen.getByLabelText('Next image'));
    expect(screen.getByText('2/2')).toBeInTheDocument();

    expect(screen.queryByLabelText('Next image')).not.toBeInTheDocument();
    expect(screen.getByLabelText('Previous image')).toBeInTheDocument();
  });

  it('supports keyboard navigation with ArrowRight and ArrowLeft', () => {
    const media = makeMedia(3);
    render(<ImageCarousel media={media} />);

    const carousel = screen.getByRole('region', { name: /carousel/i });
    fireEvent.keyDown(carousel, { key: 'ArrowRight' });
    expect(screen.getByText('2/3')).toBeInTheDocument();

    fireEvent.keyDown(carousel, { key: 'ArrowLeft' });
    expect(screen.getByText('1/3')).toBeInTheDocument();
  });

  it('opens lightbox when image is clicked', () => {
    const media = makeMedia(2);
    render(<ImageCarousel media={media} />);

    expect(screen.queryByTestId('image-lightbox')).not.toBeInTheDocument();

    // Click the motion.div wrapper (which contains the image)
    const img = screen.getByRole('img');
    fireEvent.click(img.parentElement!);

    expect(screen.getByTestId('image-lightbox')).toBeInTheDocument();
  });

  it('closes lightbox when onClose is called', () => {
    const media = makeMedia(2);
    render(<ImageCarousel media={media} />);

    // Open lightbox
    const img = screen.getByRole('img');
    fireEvent.click(img.parentElement!);
    expect(screen.getByTestId('image-lightbox')).toBeInTheDocument();

    // Close
    fireEvent.click(screen.getByText('Close'));
    expect(screen.queryByTestId('image-lightbox')).not.toBeInTheDocument();
  });

  it('has proper aria attributes on the carousel region', () => {
    const media = makeMedia(3);
    render(<ImageCarousel media={media} />);

    const region = screen.getByRole('region', { name: /carousel/i });
    expect(region).toHaveAttribute('aria-roledescription', 'carousel');
    expect(region).toHaveAttribute('tabindex', '0');
  });

  it('applies custom className', () => {
    const media = makeMedia(1);
    render(<ImageCarousel media={media} className="my-custom-class" />);

    const region = screen.getByRole('region', { name: /carousel/i });
    expect(region.className).toContain('my-custom-class');
  });

  it('marks the active dot with aria-current', () => {
    const media = makeMedia(3);
    render(<ImageCarousel media={media} />);

    const dots = screen.getAllByLabelText(/Go to image/);
    expect(dots[0]).toHaveAttribute('aria-current', 'true');
    expect(dots[1]).not.toHaveAttribute('aria-current');
    expect(dots[2]).not.toHaveAttribute('aria-current');
  });
});
