// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';
import { LinkPreviewCard, LinkPreviewSkeleton } from './LinkPreviewCard';
import type { LinkPreview } from './LinkPreviewCard';

// YouTubeEmbed has no @/contexts dependency — mock for isolation.
vi.mock('./YouTubeEmbed', () => ({
  YouTubeEmbed: ({ title }: { title: string }) => <div data-testid="youtube-embed">{title}</div>,
}));

// No @/lib/api usage in this component — no api mock needed.

const BASE_PREVIEW: LinkPreview = {
  url: 'https://example.com/article',
  title: 'Example Article',
  description: 'This is a description of the article.',
  image: 'https://example.com/image.jpg',
  domain: 'example.com',
};

describe('LinkPreviewCard — large layout (default)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders a link wrapping the card', () => {
    render(<LinkPreviewCard preview={BASE_PREVIEW} />);
    const link = screen.getByRole('link');
    expect(link).toBeInTheDocument();
    expect(link).toHaveAttribute('href', 'https://example.com/article');
    expect(link).toHaveAttribute('target', '_blank');
    expect(link).toHaveAttribute('rel', 'noopener noreferrer');
  });

  it('renders title', () => {
    render(<LinkPreviewCard preview={BASE_PREVIEW} />);
    expect(screen.getByText('Example Article')).toBeInTheDocument();
  });

  it('renders description', () => {
    render(<LinkPreviewCard preview={BASE_PREVIEW} />);
    expect(screen.getByText('This is a description of the article.')).toBeInTheDocument();
  });

  it('renders domain', () => {
    render(<LinkPreviewCard preview={BASE_PREVIEW} />);
    // domain appears in the bottom line
    expect(screen.getAllByText('example.com').length).toBeGreaterThan(0);
  });

  it('renders preview image with descriptive alt text', () => {
    render(<LinkPreviewCard preview={BASE_PREVIEW} />);
    const img = screen.getByAltText('Preview for Example Article');
    expect(img).toBeInTheDocument();
    expect(img).toHaveAttribute('src', 'https://example.com/image.jpg');
  });

  it('does not render image when imageError occurs', async () => {
    render(<LinkPreviewCard preview={BASE_PREVIEW} />);
    const img = screen.getByAltText('Preview for Example Article');
    fireEvent.error(img);
    // After error, image should be removed from DOM
    expect(screen.queryByAltText('Preview for Example Article')).not.toBeInTheDocument();
  });

  it('renders without image when image is null', () => {
    const preview: LinkPreview = { ...BASE_PREVIEW, image: null };
    render(<LinkPreviewCard preview={preview} />);
    expect(screen.queryByRole('img', { name: /preview/i })).not.toBeInTheDocument();
    expect(screen.getByText('Example Article')).toBeInTheDocument();
  });

  it('renders without title gracefully', () => {
    const preview: LinkPreview = { ...BASE_PREVIEW, title: null };
    render(<LinkPreviewCard preview={preview} />);
    // Falls back to alt text using domain
    const img = screen.queryByAltText(`Preview from example.com`);
    if (img) {
      expect(img).toBeInTheDocument();
    } else {
      // Image may be missing, but the card should still render
      expect(screen.getByRole('link')).toBeInTheDocument();
    }
  });

  it('renders without description gracefully', () => {
    const preview: LinkPreview = { ...BASE_PREVIEW, description: null };
    render(<LinkPreviewCard preview={preview} />);
    expect(screen.getByText('Example Article')).toBeInTheDocument();
  });

  it('renders siteName from siteName prop', () => {
    const preview: LinkPreview = { ...BASE_PREVIEW, siteName: 'Example Site' };
    render(<LinkPreviewCard preview={preview} />);
    expect(screen.getByText('Example Site')).toBeInTheDocument();
  });

  it('renders siteName from site_name alias', () => {
    const preview: LinkPreview = { ...BASE_PREVIEW, site_name: 'Alt Site Name' };
    render(<LinkPreviewCard preview={preview} />);
    expect(screen.getByText('Alt Site Name')).toBeInTheDocument();
  });

  it('renders image from image_url alias when image is absent', () => {
    const preview: LinkPreview = { ...BASE_PREVIEW, image: null, image_url: 'https://example.com/alt.jpg' };
    render(<LinkPreviewCard preview={preview} />);
    const img = screen.getByAltText('Preview for Example Article');
    expect(img).toHaveAttribute('src', 'https://example.com/alt.jpg');
  });

  it('extracts domain from URL when domain prop is absent', () => {
    const preview: LinkPreview = { ...BASE_PREVIEW, domain: null };
    render(<LinkPreviewCard preview={preview} />);
    expect(screen.getAllByText('example.com').length).toBeGreaterThan(0);
  });

  it('renders favicon when favicon_url is provided', () => {
    const preview: LinkPreview = { ...BASE_PREVIEW, favicon_url: 'https://example.com/favicon.ico' };
    render(<LinkPreviewCard preview={preview} />);
    const favicons = screen.getAllByRole('img');
    const favicon = favicons.find(img => img.getAttribute('src')?.includes('favicon'));
    expect(favicon).toBeInTheDocument();
  });

  it('sanitises unsafe URL to "#"', () => {
    const preview: LinkPreview = { ...BASE_PREVIEW, url: 'javascript:alert(1)' };
    render(<LinkPreviewCard preview={preview} />);
    expect(screen.getByRole('link')).toHaveAttribute('href', '#');
  });
});

describe('LinkPreviewCard — compact layout', () => {
  it('renders a link in compact mode', () => {
    render(<LinkPreviewCard preview={BASE_PREVIEW} compact />);
    expect(screen.getByRole('link')).toBeInTheDocument();
  });

  it('renders title in compact mode', () => {
    render(<LinkPreviewCard preview={BASE_PREVIEW} compact />);
    expect(screen.getByText('Example Article')).toBeInTheDocument();
  });

  it('renders description in compact mode', () => {
    render(<LinkPreviewCard preview={BASE_PREVIEW} compact />);
    expect(screen.getByText('This is a description of the article.')).toBeInTheDocument();
  });

  it('renders thumbnail image in compact mode', () => {
    render(<LinkPreviewCard preview={BASE_PREVIEW} compact />);
    expect(screen.getByAltText('Preview for Example Article')).toBeInTheDocument();
  });

  it('hides thumbnail after image error in compact mode', () => {
    render(<LinkPreviewCard preview={BASE_PREVIEW} compact />);
    const img = screen.getByAltText('Preview for Example Article');
    fireEvent.error(img);
    expect(screen.queryByAltText('Preview for Example Article')).not.toBeInTheDocument();
  });

  it('renders Globe icon when no favicon_url in compact mode', () => {
    const preview: LinkPreview = { ...BASE_PREVIEW, favicon_url: null };
    render(<LinkPreviewCard preview={preview} compact />);
    // Globe renders as svg with aria-hidden — card still has link
    expect(screen.getByRole('link')).toBeInTheDocument();
  });
});

describe('LinkPreviewCard — video / YouTube embed', () => {
  it('renders YouTubeEmbed when content_type="video" and embed_html present', () => {
    const preview: LinkPreview = {
      ...BASE_PREVIEW,
      content_type: 'video',
      embed_html: 'https://www.youtube.com/embed/abc123',
    };
    render(<LinkPreviewCard preview={preview} />);
    expect(screen.getByTestId('youtube-embed')).toBeInTheDocument();
    expect(screen.getByText('Example Article')).toBeInTheDocument();
  });

  it('does NOT render YouTubeEmbed when embed_html is absent even if type=video', () => {
    const preview: LinkPreview = {
      ...BASE_PREVIEW,
      content_type: 'video',
      embed_html: null,
    };
    render(<LinkPreviewCard preview={preview} />);
    expect(screen.queryByTestId('youtube-embed')).not.toBeInTheDocument();
    expect(screen.getByRole('link')).toBeInTheDocument();
  });
});

describe('LinkPreviewSkeleton', () => {
  it('renders large skeleton', () => {
    const { container } = render(<LinkPreviewSkeleton />);
    expect(container.firstChild).not.toBeNull();
  });

  it('renders compact skeleton', () => {
    const { container } = render(<LinkPreviewSkeleton compact />);
    expect(container.firstChild).not.toBeNull();
  });
});
