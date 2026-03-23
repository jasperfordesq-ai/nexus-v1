// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for ImageLightbox — fullscreen image viewer overlay.
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

vi.mock('framer-motion', () => ({
  motion: {
    div: ({ children, onClick, role, ...props }: Record<string, unknown>) => {
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
        <div {...safe} role={role as string} onClick={onClick as React.MouseEventHandler}>
          {children as React.ReactNode}
        </div>
      );
    },
    img: ({ src, alt, ...props }: Record<string, unknown>) => {
      const safe: Record<string, unknown> = {};
      const skip = new Set([
        'variants', 'initial', 'animate', 'exit', 'transition',
        'custom', 'drag', 'dragConstraints', 'dragElastic', 'onDragEnd',
      ]);
      for (const [k, v] of Object.entries(props)) {
        if (!skip.has(k)) safe[k] = v;
      }
      return <img {...safe} src={src as string} alt={alt as string} />;
    },
  },
  AnimatePresence: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}));

import { ImageLightbox } from '../ImageLightbox';

// ── Fixtures ─────────────────────────────────────────────────────────────────

function makeMedia(count: number): PostMedia[] {
  return Array.from({ length: count }, (_, i) => ({
    id: i + 1,
    media_type: 'image' as const,
    file_url: `/uploads/img${i + 1}.jpg`,
    thumbnail_url: null,
    alt_text: i === 0 ? 'First image description' : null,
    width: 800,
    height: 600,
    file_size: 50000,
    display_order: i,
  }));
}

// ── Tests ────────────────────────────────────────────────────────────────────

describe('ImageLightbox', () => {
  const onClose = vi.fn();

  beforeEach(() => {
    vi.clearAllMocks();
    // Reset body overflow that may be set by the component
    document.body.style.overflow = '';
  });

  it('renders nothing when the current media item is invalid', () => {
    render(<ImageLightbox media={[]} onClose={onClose} />);
    // Returns null when current is falsy — no dialog rendered
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('renders the image in a dialog', () => {
    const media = makeMedia(1);
    render(<ImageLightbox media={media} onClose={onClose} />);

    const dialog = screen.getByRole('dialog');
    expect(dialog).toBeInTheDocument();
    expect(dialog).toHaveAttribute('aria-modal', 'true');

    const img = screen.getByRole('img');
    expect(img).toHaveAttribute('src', '/uploads/img1.jpg');
    expect(img).toHaveAttribute('alt', 'First image description');
  });

  it('renders a close button', () => {
    const media = makeMedia(1);
    render(<ImageLightbox media={media} onClose={onClose} />);

    const closeBtn = screen.getByLabelText('Close image viewer');
    expect(closeBtn).toBeInTheDocument();

    fireEvent.click(closeBtn);
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('does not show counter, arrows, or dots for a single image', () => {
    const media = makeMedia(1);
    render(<ImageLightbox media={media} onClose={onClose} />);

    expect(screen.queryByText(/of/)).not.toBeInTheDocument();
    expect(screen.queryByLabelText('Previous image')).not.toBeInTheDocument();
    expect(screen.queryByLabelText('Next image')).not.toBeInTheDocument();
  });

  it('shows counter, navigation arrows, and dot indicators for multiple images', () => {
    const media = makeMedia(3);
    render(<ImageLightbox media={media} onClose={onClose} />);

    // Counter "1 of 3"
    expect(screen.getByText('1 of 3')).toBeInTheDocument();

    // Next arrow (no previous on first image)
    expect(screen.queryByLabelText('Previous image')).not.toBeInTheDocument();
    expect(screen.getByLabelText('Next image')).toBeInTheDocument();

    // Dot indicators
    const dots = screen.getAllByLabelText(/Go to image/);
    expect(dots).toHaveLength(3);
  });

  it('navigates to next image via next arrow', () => {
    const media = makeMedia(3);
    render(<ImageLightbox media={media} onClose={onClose} />);

    fireEvent.click(screen.getByLabelText('Next image'));
    expect(screen.getByText('2 of 3')).toBeInTheDocument();
  });

  it('navigates to previous image via previous arrow', () => {
    const media = makeMedia(3);
    render(<ImageLightbox media={media} initialIndex={2} onClose={onClose} />);

    expect(screen.getByText('3 of 3')).toBeInTheDocument();
    fireEvent.click(screen.getByLabelText('Previous image'));
    expect(screen.getByText('2 of 3')).toBeInTheDocument();
  });

  it('navigates via dot indicators', () => {
    const media = makeMedia(3);
    render(<ImageLightbox media={media} onClose={onClose} />);

    fireEvent.click(screen.getByLabelText('Go to image 3'));
    expect(screen.getByText('3 of 3')).toBeInTheDocument();
  });

  it('closes on Escape key', () => {
    const media = makeMedia(1);
    render(<ImageLightbox media={media} onClose={onClose} />);

    fireEvent.keyDown(document, { key: 'Escape' });
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('navigates with ArrowRight and ArrowLeft keys', () => {
    const media = makeMedia(3);
    render(<ImageLightbox media={media} onClose={onClose} />);

    fireEvent.keyDown(document, { key: 'ArrowRight' });
    expect(screen.getByText('2 of 3')).toBeInTheDocument();

    fireEvent.keyDown(document, { key: 'ArrowLeft' });
    expect(screen.getByText('1 of 3')).toBeInTheDocument();
  });

  it('locks body scroll when open', () => {
    const media = makeMedia(1);
    render(<ImageLightbox media={media} onClose={onClose} />);
    expect(document.body.style.overflow).toBe('hidden');
  });

  it('respects initialIndex prop', () => {
    const media = makeMedia(5);
    render(<ImageLightbox media={media} initialIndex={3} onClose={onClose} />);

    expect(screen.getByText('4 of 5')).toBeInTheDocument();
    const img = screen.getByRole('img');
    expect(img).toHaveAttribute('src', '/uploads/img4.jpg');
  });

  it('renders alt text caption when available', () => {
    const media = makeMedia(2);
    render(<ImageLightbox media={media} onClose={onClose} />);

    expect(screen.getByText('First image description')).toBeInTheDocument();
  });

  it('marks the active dot with aria-current', () => {
    const media = makeMedia(3);
    render(<ImageLightbox media={media} initialIndex={1} onClose={onClose} />);

    const dots = screen.getAllByLabelText(/Go to image/);
    expect(dots[0]).not.toHaveAttribute('aria-current');
    expect(dots[1]).toHaveAttribute('aria-current', 'true');
    expect(dots[2]).not.toHaveAttribute('aria-current');
  });

  it('closes when clicking the backdrop', () => {
    const media = makeMedia(1);
    render(<ImageLightbox media={media} onClose={onClose} />);

    const dialog = screen.getByRole('dialog');
    fireEvent.click(dialog);
    expect(onClose).toHaveBeenCalledTimes(1);
  });
});
