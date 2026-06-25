// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── Mock api ────────────────────────────────────────────────────────────────
const { mockApi } = vi.hoisted(() => ({
  mockApi: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    download: vi.fn(),
    upload: vi.fn(),
  },
}));

vi.mock('@/lib/api', () => ({ api: mockApi, default: mockApi }));
vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// ─── Contexts ────────────────────────────────────────────────────────────────
const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
  showToast: vi.fn(),
};

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
  })
);

// ─── Stub UI and motion ──────────────────────────────────────────────────────
vi.mock('@/components/ui', async () => (await import('@/test/uiMock')).uiMock);

vi.mock('@/lib/motion', async () => {
  const { default: React } = await import('react');
  return {
    motion: {
      div: (
        { ref, ...props }: Record<string, unknown> & { children?: React.ReactNode; ref?: React.Ref<HTMLDivElement> }
      ) => {
        // Strip non-DOM props before forwarding
        const { drag: _d, dragConstraints: _dc, dragElastic: _de, onDragEnd: _ode, custom: _c, variants: _v, initial: _i, animate: _a, exit: _e, transition: _t, children, ...domProps } = props;
        return React.createElement('div', { ...domProps, ref }, children as React.ReactNode);
      },
    },
    AnimatePresence: ({ children }: { children: React.ReactNode }) => React.createElement(React.Fragment, null, children),
  };
});

vi.mock('@/lib/helpers', () => ({
  resolveAssetUrl: (url: string) => url ?? '',
}));

// ─── Fixtures ────────────────────────────────────────────────────────────────
import type { PostMedia } from './types';

const makeImage = (overrides: Partial<PostMedia> = {}): PostMedia => ({
  id: 1,
  media_type: 'image',
  file_url: '/images/photo1.jpg',
  thumbnail_url: null,
  alt_text: null,
  width: 800,
  height: 600,
  file_size: 102400,
  display_order: 0,
  ...overrides,
});

const makeVideo = (overrides: Partial<PostMedia> = {}): PostMedia => ({
  id: 2,
  media_type: 'video',
  file_url: '/videos/clip.mp4',
  thumbnail_url: '/images/thumb.jpg',
  alt_text: 'A nice video',
  width: 1280,
  height: 720,
  file_size: 5242880,
  display_order: 1,
  ...overrides,
});

// ─────────────────────────────────────────────────────────────────────────────
describe('ImageLightbox', () => {
  const onClose = vi.fn();

  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders an image element for image media', async () => {
    const { ImageLightbox } = await import('./ImageLightbox');
    render(<ImageLightbox media={[makeImage()]} onClose={onClose} />);

    await waitFor(() => {
      const img = document.querySelector('img');
      expect(img).toBeInTheDocument();
      expect(img?.getAttribute('src')).toContain('photo1.jpg');
    });
  });

  it('renders a video element for video media', async () => {
    const { ImageLightbox } = await import('./ImageLightbox');
    render(<ImageLightbox media={[makeVideo()]} onClose={onClose} />);

    await waitFor(() => {
      const video = document.querySelector('video');
      expect(video).toBeInTheDocument();
    });
  });

  it('calls onClose when ESC key is pressed', async () => {
    const { ImageLightbox } = await import('./ImageLightbox');
    render(<ImageLightbox media={[makeImage()]} onClose={onClose} />);

    fireEvent.keyDown(document, { key: 'Escape' });
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('navigates to next image on ArrowRight key', async () => {
    const img1 = makeImage({ id: 1, file_url: '/images/photo1.jpg' });
    const img2 = makeImage({ id: 2, file_url: '/images/photo2.jpg' });
    const { ImageLightbox } = await import('./ImageLightbox');
    render(<ImageLightbox media={[img1, img2]} initialIndex={0} onClose={onClose} />);

    // Initially shows photo1
    await waitFor(() => {
      expect(document.querySelector('img')?.getAttribute('src')).toContain('photo1.jpg');
    });

    fireEvent.keyDown(document, { key: 'ArrowRight' });

    await waitFor(() => {
      expect(document.querySelector('img')?.getAttribute('src')).toContain('photo2.jpg');
    });
  });

  it('navigates to previous image on ArrowLeft key', async () => {
    const img1 = makeImage({ id: 1, file_url: '/images/photo1.jpg' });
    const img2 = makeImage({ id: 2, file_url: '/images/photo2.jpg' });
    const { ImageLightbox } = await import('./ImageLightbox');
    render(<ImageLightbox media={[img1, img2]} initialIndex={1} onClose={onClose} />);

    // Initially shows photo2
    await waitFor(() => {
      expect(document.querySelector('img')?.getAttribute('src')).toContain('photo2.jpg');
    });

    fireEvent.keyDown(document, { key: 'ArrowLeft' });

    await waitFor(() => {
      expect(document.querySelector('img')?.getAttribute('src')).toContain('photo1.jpg');
    });
  });

  it('does not navigate left when already at first image', async () => {
    const { ImageLightbox } = await import('./ImageLightbox');
    render(<ImageLightbox media={[makeImage({ file_url: '/images/only.jpg' })]} initialIndex={0} onClose={onClose} />);

    fireEvent.keyDown(document, { key: 'ArrowLeft' });

    await waitFor(() => {
      expect(document.querySelector('img')?.getAttribute('src')).toContain('only.jpg');
    });
  });

  it('does not navigate right when already at last image', async () => {
    const { ImageLightbox } = await import('./ImageLightbox');
    render(<ImageLightbox media={[makeImage({ file_url: '/images/only.jpg' })]} initialIndex={0} onClose={onClose} />);

    fireEvent.keyDown(document, { key: 'ArrowRight' });

    // Still on same image — no crash and image unchanged
    await waitFor(() => {
      expect(document.querySelector('img')?.getAttribute('src')).toContain('only.jpg');
    });
  });

  it('renders image counter for multiple images', async () => {
    const imgs = [makeImage({ id: 1 }), makeImage({ id: 2 }), makeImage({ id: 3 })];
    const { ImageLightbox } = await import('./ImageLightbox');
    render(<ImageLightbox media={imgs} initialIndex={0} onClose={onClose} />);

    await waitFor(() => {
      // Counter text "1 of 3" via i18n key lightbox.counter — live region
      const liveRegion = document.querySelector('[aria-live="polite"]');
      expect(liveRegion).toBeInTheDocument();
    });
  });

  it('shows alt text when present', async () => {
    const { ImageLightbox } = await import('./ImageLightbox');
    render(
      <ImageLightbox
        media={[makeImage({ alt_text: 'A beautiful sunset' })]}
        onClose={onClose}
      />
    );

    await waitFor(() => {
      expect(screen.getByText('A beautiful sunset')).toBeInTheDocument();
    });
  });

  it('renders close button with accessible label', async () => {
    const { ImageLightbox } = await import('./ImageLightbox');
    render(<ImageLightbox media={[makeImage()]} onClose={onClose} />);

    await waitFor(() => {
      const btns = screen.getAllByRole('button');
      const closeBtn = btns.find((b) =>
        b.getAttribute('aria-label')?.toLowerCase().includes('close')
      );
      expect(closeBtn).toBeDefined();
    });
  });

  it('renders a download link for the current image', async () => {
    const { ImageLightbox } = await import('./ImageLightbox');
    render(<ImageLightbox media={[makeImage({ file_url: '/images/photo1.jpg' })]} onClose={onClose} />);

    await waitFor(() => {
      const link = document.querySelector('a[download]');
      expect(link).toBeInTheDocument();
      expect(link?.getAttribute('href')).toContain('photo1.jpg');
    });
  });

  it('renders navigation arrows when more than one image', async () => {
    const { ImageLightbox } = await import('./ImageLightbox');
    render(
      <ImageLightbox
        media={[makeImage({ id: 1 }), makeImage({ id: 2, file_url: '/images/b.jpg' })]}
        initialIndex={0}
        onClose={onClose}
      />
    );

    await waitFor(() => {
      const btns = screen.getAllByRole('button');
      const nextBtn = btns.find((b) =>
        b.getAttribute('aria-label')?.toLowerCase().includes('next')
      );
      expect(nextBtn).toBeDefined();
    });
  });
});
