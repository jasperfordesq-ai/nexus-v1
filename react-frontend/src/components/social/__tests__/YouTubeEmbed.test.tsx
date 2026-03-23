// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for YouTubeEmbed component.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { HeroUIProvider } from '@heroui/react';

// ─── Mocks ──────────────────────────────────────────────────────────────────

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string, fallback?: string) => fallback ?? key,
    i18n: { language: 'en', changeLanguage: vi.fn() },
  }),
  initReactI18next: { type: '3rdParty', init: () => {} },
}));

// ─── Imports ────────────────────────────────────────────────────────────────

import { YouTubeEmbed } from '../YouTubeEmbed';

// ─── Wrapper ────────────────────────────────────────────────────────────────

function W({ children }: { children: React.ReactNode }) {
  return <HeroUIProvider>{children}</HeroUIProvider>;
}

// ─── Test data ──────────────────────────────────────────────────────────────

const YOUTUBE_EMBED_URL = 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ';
const YOUTUBE_VIDEO_ID = 'dQw4w9WgXcQ';
const VIMEO_EMBED_URL = 'https://player.vimeo.com/video/123456';

// ─── Tests ──────────────────────────────────────────────────────────────────

describe('YouTubeEmbed', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders without crashing', () => {
    const { container } = render(
      <W>
        <YouTubeEmbed embedUrl={YOUTUBE_EMBED_URL} />
      </W>,
    );

    expect(container.firstChild).not.toBeNull();
  });

  it('renders play button initially (no iframe)', () => {
    render(
      <W>
        <YouTubeEmbed embedUrl={YOUTUBE_EMBED_URL} title="Test Video" />
      </W>,
    );

    expect(screen.getByLabelText('Play video: Test Video')).toBeInTheDocument();
    expect(screen.queryByTitle('Test Video')).not.toBeInTheDocument();
  });

  it('shows YouTube branding label', () => {
    render(
      <W>
        <YouTubeEmbed embedUrl={YOUTUBE_EMBED_URL} />
      </W>,
    );

    expect(screen.getByText('YouTube')).toBeInTheDocument();
  });

  it('derives thumbnail from YouTube video ID', () => {
    const { container } = render(
      <W>
        <YouTubeEmbed embedUrl={YOUTUBE_EMBED_URL} title="Test" />
      </W>,
    );

    const img = container.querySelector('img');
    expect(img).not.toBeNull();
    expect(img).toHaveAttribute(
      'src',
      `https://img.youtube.com/vi/${YOUTUBE_VIDEO_ID}/hqdefault.jpg`,
    );
  });

  it('uses provided thumbnailUrl over derived one', () => {
    const customThumb = 'https://example.com/thumb.jpg';

    const { container } = render(
      <W>
        <YouTubeEmbed
          embedUrl={YOUTUBE_EMBED_URL}
          thumbnailUrl={customThumb}
          title="Test"
        />
      </W>,
    );

    const img = container.querySelector('img');
    expect(img).not.toBeNull();
    expect(img).toHaveAttribute('src', customThumb);
  });

  it('uses default title "Video" when title is not provided', () => {
    render(
      <W>
        <YouTubeEmbed embedUrl={YOUTUBE_EMBED_URL} />
      </W>,
    );

    expect(screen.getByLabelText('Play video: Video')).toBeInTheDocument();
  });

  it('loads iframe with autoplay on play click', async () => {
    const user = userEvent.setup();

    render(
      <W>
        <YouTubeEmbed embedUrl={YOUTUBE_EMBED_URL} title="Test Video" />
      </W>,
    );

    // Click the play button
    await user.click(screen.getByLabelText('Play video: Test Video'));

    // iframe should now be present with autoplay=1
    const iframe = screen.getByTitle('Test Video');
    expect(iframe).toBeInTheDocument();
    expect(iframe).toHaveAttribute(
      'src',
      `${YOUTUBE_EMBED_URL}?autoplay=1&rel=0`,
    );
  });

  it('removes play button after clicking play', async () => {
    const user = userEvent.setup();

    render(
      <W>
        <YouTubeEmbed embedUrl={YOUTUBE_EMBED_URL} title="Test Video" />
      </W>,
    );

    await user.click(screen.getByLabelText('Play video: Test Video'));

    // Play button should be gone
    expect(screen.queryByLabelText('Play video: Test Video')).not.toBeInTheDocument();
  });

  it('sets iframe allowFullScreen attribute', async () => {
    const user = userEvent.setup();

    render(
      <W>
        <YouTubeEmbed embedUrl={YOUTUBE_EMBED_URL} title="Test Video" />
      </W>,
    );

    await user.click(screen.getByLabelText('Play video: Test Video'));

    const iframe = screen.getByTitle('Test Video');
    // allowFullScreen maps to the "allowfullscreen" attribute in the DOM
    expect(iframe).toHaveAttribute('allowfullscreen');
  });

  it('does not show thumbnail for non-YouTube URLs without thumbnailUrl', () => {
    const { container } = render(
      <W>
        <YouTubeEmbed embedUrl={VIMEO_EMBED_URL} title="Vimeo Video" />
      </W>,
    );

    // No img should be rendered since video ID can't be extracted and no thumbnailUrl
    expect(container.querySelector('img')).toBeNull();
  });

  it('shows thumbnail for non-YouTube URLs when thumbnailUrl is provided', () => {
    const thumb = 'https://example.com/vimeo-thumb.jpg';

    const { container } = render(
      <W>
        <YouTubeEmbed
          embedUrl={VIMEO_EMBED_URL}
          thumbnailUrl={thumb}
          title="Vimeo Video"
        />
      </W>,
    );

    const img = container.querySelector('img');
    expect(img).not.toBeNull();
    expect(img).toHaveAttribute('src', thumb);
  });

  it('maintains 16:9 aspect ratio container', () => {
    render(
      <W>
        <YouTubeEmbed embedUrl={YOUTUBE_EMBED_URL} />
      </W>,
    );

    const playButton = screen.getByLabelText('Play video: Video');
    const container = playButton.parentElement;
    expect(container).toHaveStyle({ aspectRatio: '16 / 9' });
  });

  it('handles youtube.com/embed/ URL format', () => {
    const regularEmbed = 'https://www.youtube.com/embed/abc12345678';

    const { container } = render(
      <W>
        <YouTubeEmbed embedUrl={regularEmbed} title="Regular YouTube" />
      </W>,
    );

    const img = container.querySelector('img');
    expect(img).not.toBeNull();
    expect(img).toHaveAttribute(
      'src',
      'https://img.youtube.com/vi/abc12345678/hqdefault.jpg',
    );
  });
});
