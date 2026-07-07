// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

vi.mock('@/lib/logger', () => ({ logError: vi.fn() }));

vi.mock('@/contexts', () =>
  createMockContexts({
    useTenant: () => ({
      tenant: { id: 2, name: 'Test Tenant', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

// Stub helpers to return predictable values
vi.mock('@/lib/helpers', () => ({
  resolveAvatarUrl: (url: string | null | undefined) => url ?? '',
  resolveAssetUrl: (url: string | null | undefined) => url ?? '',
  resolveThumbnailUrl: (url: string | null | undefined) => url ?? '',
  formatRelativeTime: () => '5m ago',
}));

import { QuotedPostEmbed } from './QuotedPostEmbed';
import type { QuotedPostData } from './QuotedPostEmbed';

const SHORT_POST: QuotedPostData = {
  id: 1,
  content: 'Short content',
  created_at: '2026-01-01T00:00:00Z',
  author: { id: 10, name: 'Bob', avatar_url: null },
};

const LONG_POST: QuotedPostData = {
  id: 2,
  content: 'x'.repeat(300),
  created_at: '2026-01-01T00:00:00Z',
  author: { id: 11, name: 'Carol', avatar_url: 'https://example.com/carol.jpg' },
};

const POST_WITH_IMAGE: QuotedPostData = {
  id: 3,
  content: 'Post with image',
  image_url: 'https://example.com/img.jpg',
  created_at: '2026-01-01T00:00:00Z',
  author: { id: 12, name: 'Dave', avatar_url: null },
};

const POST_WITH_MEDIA: QuotedPostData = {
  id: 4,
  content: 'Post with media',
  created_at: '2026-01-01T00:00:00Z',
  author: { id: 13, name: 'Eve', avatar_url: null },
  media: [
    {
      id: 100,
      media_type: 'image',
      file_url: 'https://example.com/media.jpg',
      thumbnail_url: 'https://example.com/thumb.jpg',
      alt_text: 'A media image',
    },
  ],
};

describe('QuotedPostEmbed', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the author name', () => {
    render(<QuotedPostEmbed post={SHORT_POST} />);
    expect(screen.getByText('Bob')).toBeInTheDocument();
  });

  it('renders the post content', () => {
    render(<QuotedPostEmbed post={SHORT_POST} />);
    expect(screen.getByText('Short content')).toBeInTheDocument();
  });

  it('renders the relative time', () => {
    render(<QuotedPostEmbed post={SHORT_POST} />);
    expect(screen.getByText('5m ago')).toBeInTheDocument();
  });

  it('wraps content in a link when not in preview mode', () => {
    const { container } = render(<QuotedPostEmbed post={SHORT_POST} isPreview={false} />);
    const link = container.querySelector('a');
    expect(link).toBeInTheDocument();
    expect(link).toHaveAttribute('href', '/test/feed?post=1');
  });

  it('does NOT render a link wrapper in preview mode', () => {
    const { container } = render(<QuotedPostEmbed post={SHORT_POST} isPreview={true} />);
    expect(container.querySelector('a')).not.toBeInTheDocument();
  });

  it('truncates long content and shows "Read more" button', () => {
    render(<QuotedPostEmbed post={LONG_POST} />);
    expect(screen.getByRole('button', { name: /read more/i })).toBeInTheDocument();
    // Content should be sliced at 280 chars
    expect(screen.queryByText(LONG_POST.content)).not.toBeInTheDocument();
  });

  it('expands content after pressing "Read more"', async () => {
    render(<QuotedPostEmbed post={LONG_POST} />);
    fireEvent.click(screen.getByRole('button', { name: /read more/i }));
    await waitFor(() => {
      expect(screen.queryByRole('button', { name: /read more/i })).not.toBeInTheDocument();
      // Full content should now be visible
      expect(screen.getByText(LONG_POST.content)).toBeInTheDocument();
    });
  });

  it('renders the post image from image_url', () => {
    render(<QuotedPostEmbed post={POST_WITH_IMAGE} />);
    const img = screen.getByRole('img');
    expect(img).toHaveAttribute('src', 'https://example.com/img.jpg');
  });

  it('renders thumbnail from media array when available', () => {
    render(<QuotedPostEmbed post={POST_WITH_MEDIA} />);
    const img = screen.getByRole('img');
    expect(img).toHaveAttribute('src', 'https://example.com/thumb.jpg');
  });

  it('does not render an image when there is no image_url or media', () => {
    render(<QuotedPostEmbed post={SHORT_POST} />);
    expect(screen.queryByRole('img')).not.toBeInTheDocument();
  });

  it('does not show "Read more" for short content', () => {
    render(<QuotedPostEmbed post={SHORT_POST} />);
    expect(screen.queryByRole('button', { name: /read more/i })).not.toBeInTheDocument();
  });

  it('renders content_truncated=true posts with "Read more"', () => {
    const truncatedPost: QuotedPostData = {
      ...SHORT_POST,
      content_truncated: true,
    };
    render(<QuotedPostEmbed post={truncatedPost} />);
    // shouldTruncate=true because content_truncated flag is set
    expect(screen.getByRole('button', { name: /read more/i })).toBeInTheDocument();
  });
});
