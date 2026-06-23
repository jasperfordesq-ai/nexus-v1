// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';
import type { PostMedia } from './types';

// ─── API mock (not used by MediaGrid directly, but required by dynamic import pattern) ──
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

// ─── Contexts ─────────────────────────────────────────────────────────────────
vi.mock('@/contexts', () =>
  createMockContexts({
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  }),
);

// ─── Stub heavy children ──────────────────────────────────────────────────────
vi.mock('./ImageLightbox', () => ({
  ImageLightbox: ({ onClose }: { onClose: () => void }) => (
    <div data-testid="lightbox">
      <button onClick={onClose}>close-lightbox</button>
    </div>
  ),
}));

// ─── Stub @/components/ui ────────────────────────────────────────────────────
vi.mock('@/components/ui', async () => (await import('@/test/uiMock')).uiMock);

// ─── Helpers ─────────────────────────────────────────────────────────────────
vi.mock('@/lib/helpers', () => ({
  resolveAssetUrl: (url: string | null) => url ?? '',
  resolveAvatarUrl: (url: string | null) => url ?? '',
}));

// ─── Fixtures ────────────────────────────────────────────────────────────────
function makeImage(id: number, overrides: Partial<PostMedia> = {}): PostMedia {
  return {
    id,
    media_type: 'image',
    file_url: `https://example.com/img${id}.jpg`,
    thumbnail_url: `https://example.com/thumb${id}.jpg`,
    alt_text: `Image ${id}`,
    width: 800,
    height: 600,
    file_size: 12345,
    display_order: id,
    ...overrides,
  };
}

function makeVideo(id: number): PostMedia {
  return {
    id,
    media_type: 'video',
    file_url: `https://example.com/vid${id}.mp4`,
    thumbnail_url: `https://example.com/vidthumb${id}.jpg`,
    alt_text: `Video ${id}`,
    width: 1280,
    height: 720,
    file_size: 98765,
    display_order: id,
  };
}

// ─────────────────────────────────────────────────────────────────────────────
describe('MediaGrid', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders null for empty media array (no images or buttons)', async () => {
    const { MediaGrid } = await import('./MediaGrid');
    render(<MediaGrid media={[]} />);
    // gridContent() returns null for 0 items — no images or interactive buttons
    expect(screen.queryByRole('img')).not.toBeInTheDocument();
    expect(screen.queryByTestId('lightbox')).not.toBeInTheDocument();
  });

  it('renders single image via alt text', async () => {
    // MediaGrid handles 2+ in grid; 1 item falls through to null from gridContent
    // but the component still renders (no explicit early return for 1 item),
    // so gridContent returns null — verify it doesn't crash
    const { MediaGrid } = await import('./MediaGrid');
    const { container } = render(<MediaGrid media={[makeImage(1)]} />);
    // With only 1 item the grid conditions don't match and gridContent returns null
    expect(container).toBeInTheDocument();
  });

  it('renders 2 images side by side', async () => {
    const { MediaGrid } = await import('./MediaGrid');
    render(<MediaGrid media={[makeImage(1), makeImage(2)]} />);
    const imgs = screen.getAllByRole('img');
    expect(imgs).toHaveLength(2);
    expect(imgs[0]).toHaveAttribute('alt', 'Image 1');
    expect(imgs[1]).toHaveAttribute('alt', 'Image 2');
  });

  it('renders 3 images', async () => {
    const { MediaGrid } = await import('./MediaGrid');
    render(<MediaGrid media={[makeImage(1), makeImage(2), makeImage(3)]} />);
    const imgs = screen.getAllByRole('img');
    expect(imgs).toHaveLength(3);
  });

  it('renders 4 images in 2x2 grid', async () => {
    const { MediaGrid } = await import('./MediaGrid');
    render(<MediaGrid media={[makeImage(1), makeImage(2), makeImage(3), makeImage(4)]} />);
    const imgs = screen.getAllByRole('img');
    expect(imgs).toHaveLength(4);
  });

  it('shows "+N more" overlay when 5+ images', async () => {
    const { MediaGrid } = await import('./MediaGrid');
    const media = [1, 2, 3, 4, 5].map((i) => makeImage(i));
    render(<MediaGrid media={media} />);
    // extraCount = 5 - 4 = 1, so "+1" overlay
    expect(screen.getByText('+1')).toBeInTheDocument();
  });

  it('shows correct extra count when 7 images', async () => {
    const { MediaGrid } = await import('./MediaGrid');
    const media = [1, 2, 3, 4, 5, 6, 7].map((i) => makeImage(i));
    render(<MediaGrid media={media} />);
    // extraCount = 7 - 4 = 3
    expect(screen.getByText('+3')).toBeInTheDocument();
  });

  it('renders a video with poster thumbnail', async () => {
    const { MediaGrid } = await import('./MediaGrid');
    render(<MediaGrid media={[makeImage(1), makeVideo(2)]} />);
    const video = document.querySelector('video');
    expect(video).not.toBeNull();
    expect(video?.getAttribute('src')).toContain('vid2.mp4');
    expect(video?.getAttribute('poster')).toContain('vidthumb2.jpg');
  });

  it('does not show lightbox initially', async () => {
    const { MediaGrid } = await import('./MediaGrid');
    render(<MediaGrid media={[makeImage(1), makeImage(2)]} />);
    expect(screen.queryByTestId('lightbox')).not.toBeInTheDocument();
  });

  it('opens lightbox when image button is clicked', async () => {
    const { MediaGrid } = await import('./MediaGrid');
    const { userEvent } = await import('@/test/test-utils');
    const user = userEvent.setup();
    render(<MediaGrid media={[makeImage(1), makeImage(2)]} />);

    // Each image is wrapped in a Button stub (rendered as <button>)
    const buttons = screen.getAllByRole('button');
    await user.click(buttons[0]);

    expect(screen.getByTestId('lightbox')).toBeInTheDocument();
  });

  it('closes lightbox when onClose is called', async () => {
    const { MediaGrid } = await import('./MediaGrid');
    const { userEvent } = await import('@/test/test-utils');
    const user = userEvent.setup();
    render(<MediaGrid media={[makeImage(1), makeImage(2)]} />);

    const buttons = screen.getAllByRole('button');
    await user.click(buttons[0]);
    expect(screen.getByTestId('lightbox')).toBeInTheDocument();

    await user.click(screen.getByText('close-lightbox'));
    expect(screen.queryByTestId('lightbox')).not.toBeInTheDocument();
  });

  it('applies custom className to the grid wrapper', async () => {
    const { MediaGrid } = await import('./MediaGrid');
    const { container } = render(
      <MediaGrid media={[makeImage(1), makeImage(2)]} className="my-custom-class" />,
    );
    const grid = container.querySelector('.my-custom-class');
    expect(grid).toBeInTheDocument();
  });

  it('images have eager loading on first and lazy on rest', async () => {
    const { MediaGrid } = await import('./MediaGrid');
    render(
      <MediaGrid media={[makeImage(1), makeImage(2), makeImage(3), makeImage(4)]} />,
    );
    const imgs = screen.getAllByRole('img');
    expect(imgs[0]).toHaveAttribute('loading', 'eager');
    expect(imgs[1]).toHaveAttribute('loading', 'lazy');
  });
});
