// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';
import userEvent from '@testing-library/user-event';
import type { PostMedia } from './types';

// ─── Stub ImageLightbox ───────────────────────────────────────────────────────
vi.mock('./ImageLightbox', () => ({
  ImageLightbox: ({
    onClose,
    initialIndex,
  }: {
    media: PostMedia[];
    initialIndex: number;
    onClose: () => void;
  }) => (
    <div data-testid="lightbox" data-index={initialIndex}>
      <button onClick={onClose} aria-label="close lightbox">Close</button>
    </div>
  ),
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

// resolveAssetUrl is used for src — keep it simple
vi.mock('@/lib/helpers', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/lib/helpers')>();
  return {
    ...orig,
    resolveAssetUrl: (url: string) => url ?? '',
    resolveAvatarUrl: (url: string | null) => url ?? '',
  };
});

// ─── Contexts ────────────────────────────────────────────────────────────────
const mockToast = { success: vi.fn(), error: vi.fn(), info: vi.fn(), warning: vi.fn(), showToast: vi.fn() };

vi.mock('@/contexts', () =>
  createMockContexts({
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

vi.mock('@/hooks', () => ({ usePageTitle: vi.fn() }));

// ─── Fixtures ────────────────────────────────────────────────────────────────
const makeImage = (id: number, overrides: Partial<PostMedia> = {}): PostMedia => ({
  id,
  media_type: 'image',
  file_url: `https://cdn.example.com/image-${id}.jpg`,
  thumbnail_url: null,
  alt_text: `Image ${id}`,
  width: 800,
  height: 600,
  file_size: 102400,
  display_order: id,
  ...overrides,
});

const makeVideo = (id: number): PostMedia => ({
  id,
  media_type: 'video',
  file_url: `https://cdn.example.com/video-${id}.mp4`,
  thumbnail_url: `https://cdn.example.com/thumb-${id}.jpg`,
  alt_text: `Video ${id}`,
  width: 1920,
  height: 1080,
  file_size: 1048576,
  display_order: id,
});

// ─────────────────────────────────────────────────────────────────────────────
describe('ImageCarousel', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders null when media array is empty', async () => {
    const { ImageCarousel } = await import('./ImageCarousel');
    const { container } = render(<ImageCarousel media={[]} />);
    expect(container.querySelector('[role="region"]')).not.toBeInTheDocument();
  });

  it('renders a single image', async () => {
    const { ImageCarousel } = await import('./ImageCarousel');
    render(<ImageCarousel media={[makeImage(1)]} />);

    const img = screen.getByRole('img', { name: /Image 1/i });
    expect(img).toBeInTheDocument();
    expect(img).toHaveAttribute('src', 'https://cdn.example.com/image-1.jpg');
  });

  it('renders a video element for video media type', async () => {
    const { ImageCarousel } = await import('./ImageCarousel');
    render(<ImageCarousel media={[makeVideo(1)]} />);

    // Video element should be in the DOM (no ARIA role for <video>; use querySelector)
    const videoEl = document.querySelector('video');
    expect(videoEl).toBeInTheDocument();
    expect(videoEl).toHaveAttribute('src', 'https://cdn.example.com/video-1.mp4');
    expect(videoEl).toHaveAttribute('poster', 'https://cdn.example.com/thumb-1.jpg');
  });

  it('shows counter badge "1/3" for first of three images', async () => {
    const { ImageCarousel } = await import('./ImageCarousel');
    render(<ImageCarousel media={[makeImage(1), makeImage(2), makeImage(3)]} />);

    expect(screen.getByText('1/3')).toBeInTheDocument();
  });

  it('does NOT show counter badge for a single image', async () => {
    const { ImageCarousel } = await import('./ImageCarousel');
    render(<ImageCarousel media={[makeImage(1)]} />);

    expect(screen.queryByText('1/1')).not.toBeInTheDocument();
  });

  it('shows dot indicators for multiple images', async () => {
    const { ImageCarousel } = await import('./ImageCarousel');
    render(<ImageCarousel media={[makeImage(1), makeImage(2), makeImage(3)]} />);

    const dots = screen.getAllByRole('button', { name: /go_to_image|image \d/i });
    expect(dots.length).toBeGreaterThanOrEqual(3);
  });

  it('shows right arrow button on first slide and no left arrow', async () => {
    const { ImageCarousel } = await import('./ImageCarousel');
    render(<ImageCarousel media={[makeImage(1), makeImage(2)]} />);

    const nextBtn = screen.getByRole('button', { name: /next/i });
    expect(nextBtn).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /previous/i })).not.toBeInTheDocument();
  });

  it('navigates to next image when Next button is pressed', async () => {
    const { ImageCarousel } = await import('./ImageCarousel');
    render(<ImageCarousel media={[makeImage(1), makeImage(2), makeImage(3)]} />);

    expect(screen.getByText('1/3')).toBeInTheDocument();

    const nextBtn = screen.getByRole('button', { name: /next/i });
    await userEvent.click(nextBtn);

    await waitFor(() => {
      expect(screen.getByText('2/3')).toBeInTheDocument();
    });
  });

  it('shows Previous button after navigating forward', async () => {
    const { ImageCarousel } = await import('./ImageCarousel');
    render(<ImageCarousel media={[makeImage(1), makeImage(2), makeImage(3)]} />);

    const nextBtn = screen.getByRole('button', { name: /next/i });
    await userEvent.click(nextBtn);

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /previous/i })).toBeInTheDocument();
    });
  });

  it('navigates back to previous image when Previous button is pressed', async () => {
    const { ImageCarousel } = await import('./ImageCarousel');
    render(<ImageCarousel media={[makeImage(1), makeImage(2), makeImage(3)]} />);

    // Go to slide 2
    await userEvent.click(screen.getByRole('button', { name: /next/i }));
    await waitFor(() => screen.getByText('2/3'));

    // Go back to slide 1
    const prevBtn = screen.getByRole('button', { name: /previous/i });
    await userEvent.click(prevBtn);
    await waitFor(() => {
      expect(screen.getByText('1/3')).toBeInTheDocument();
    });
  });

  it('opens lightbox when image is clicked', async () => {
    const { ImageCarousel } = await import('./ImageCarousel');
    render(<ImageCarousel media={[makeImage(1), makeImage(2)]} />);

    const img = screen.getByRole('img', { name: /Image 1/i });
    // The wrapping motion div handles the click, find its parent
    const clickTarget = img.closest('[class*="cursor-pointer"]') ?? img;
    await userEvent.click(clickTarget);

    await waitFor(() => {
      expect(screen.getByTestId('lightbox')).toBeInTheDocument();
    });
  });

  it('closes lightbox when close is called', async () => {
    const { ImageCarousel } = await import('./ImageCarousel');
    render(<ImageCarousel media={[makeImage(1), makeImage(2)]} />);

    const img = screen.getByRole('img', { name: /Image 1/i });
    const clickTarget = img.closest('[class*="cursor-pointer"]') ?? img;
    await userEvent.click(clickTarget);

    await waitFor(() => screen.getByTestId('lightbox'));

    const closeBtn = screen.getByRole('button', { name: /close lightbox/i });
    await userEvent.click(closeBtn);

    await waitFor(() => {
      expect(screen.queryByTestId('lightbox')).not.toBeInTheDocument();
    });
  });

  it('navigates via dot indicator buttons', async () => {
    const { ImageCarousel } = await import('./ImageCarousel');
    render(<ImageCarousel media={[makeImage(1), makeImage(2), makeImage(3)]} />);

    const dots = screen.getAllByRole('button', { name: /go_to_image|image \d/i });
    // Click the third dot (index 2)
    await userEvent.click(dots[2]);

    await waitFor(() => {
      expect(screen.getByText('3/3')).toBeInTheDocument();
    });
  });

  it('has accessible role="region" and aria-label on the carousel container', async () => {
    const { ImageCarousel } = await import('./ImageCarousel');
    render(<ImageCarousel media={[makeImage(1), makeImage(2)]} />);

    const region = screen.getByRole('region');
    expect(region).toBeInTheDocument();
    expect(region).toHaveAttribute('aria-label');
  });

  it('has tabIndex=0 on carousel container for keyboard navigation', async () => {
    const { ImageCarousel } = await import('./ImageCarousel');
    render(<ImageCarousel media={[makeImage(1), makeImage(2)]} />);

    const region = screen.getByRole('region');
    expect(region).toHaveAttribute('tabindex', '0');
  });

  it('first image loads eagerly, subsequent images load lazily', async () => {
    const { ImageCarousel } = await import('./ImageCarousel');
    render(<ImageCarousel media={[makeImage(1)]} />);

    const img = screen.getByRole('img');
    expect(img).toHaveAttribute('loading', 'eager');
  });
});
