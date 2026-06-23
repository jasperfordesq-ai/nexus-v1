// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import userEvent from '@testing-library/user-event';
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
    useAuth: () => ({ user: null, isAuthenticated: false, login: vi.fn(), logout: vi.fn(), register: vi.fn(), updateUser: vi.fn(), refreshUser: vi.fn(), status: 'idle' as const, error: null }),
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

// ─── Stub @/components/ui to avoid HeroUI jsdom issues ───────────────────────
vi.mock('@/components/ui', async (importOriginal) => {
  const orig = await importOriginal<typeof import('@/components/ui')>();
  return {
    ...orig,
    // Card just renders children
    Card: ({ children, className }: { children: React.ReactNode; className?: string }) => (
      <div data-testid="card" className={className}>{children}</div>
    ),
    // Button renders as a button with onPress→onClick
    Button: ({ children, onPress, 'aria-label': ariaLabel, className, ...rest }: React.ButtonHTMLAttributes<HTMLButtonElement> & { onPress?: () => void; children?: React.ReactNode }) => (
      <button
        aria-label={ariaLabel}
        className={className}
        onClick={onPress}
        {...rest}
      >
        {children}
      </button>
    ),
  };
});

// ─────────────────────────────────────────────────────────────────────────────

const VALID_EMBED_URL = 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ';
const VALID_VIDEO_ID = 'dQw4w9WgXcQ';

describe('YouTubeEmbed', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders a play button before the user clicks', async () => {
    const { YouTubeEmbed } = await import('./YouTubeEmbed');
    render(<YouTubeEmbed embedUrl={VALID_EMBED_URL} />);

    // The play button (aria-label from i18n feed namespace)
    const playBtn = screen.getByRole('button');
    expect(playBtn).toBeInTheDocument();
  });

  it('shows no iframe before clicking play', async () => {
    const { YouTubeEmbed } = await import('./YouTubeEmbed');
    render(<YouTubeEmbed embedUrl={VALID_EMBED_URL} />);

    expect(document.querySelector('iframe')).toBeNull();
  });

  it('renders the thumbnail img when thumbnailUrl is provided', async () => {
    const { YouTubeEmbed } = await import('./YouTubeEmbed');
    const thumbUrl = 'https://example.com/thumb.jpg';
    render(<YouTubeEmbed embedUrl={VALID_EMBED_URL} thumbnailUrl={thumbUrl} />);

    const img = screen.getByRole('img');
    expect(img).toHaveAttribute('src', thumbUrl);
  });

  it('derives thumbnail from videoId when no thumbnailUrl given', async () => {
    const { YouTubeEmbed } = await import('./YouTubeEmbed');
    render(<YouTubeEmbed embedUrl={VALID_EMBED_URL} />);

    const img = screen.getByRole('img');
    expect(img).toHaveAttribute(
      'src',
      `https://img.youtube.com/vi/${VALID_VIDEO_ID}/hqdefault.jpg`
    );
  });

  it('loads the iframe after clicking play', async () => {
    const user = userEvent.setup();
    const { YouTubeEmbed } = await import('./YouTubeEmbed');
    render(<YouTubeEmbed embedUrl={VALID_EMBED_URL} />);

    const playBtn = screen.getByRole('button');
    await user.click(playBtn);

    const iframe = document.querySelector('iframe');
    expect(iframe).not.toBeNull();
    expect(iframe?.getAttribute('src')).toContain('autoplay=1');
  });

  it('iframe src includes the embed URL and cc_load_policy', async () => {
    const user = userEvent.setup();
    const { YouTubeEmbed } = await import('./YouTubeEmbed');
    render(<YouTubeEmbed embedUrl={VALID_EMBED_URL} />);

    await user.click(screen.getByRole('button'));

    const iframe = document.querySelector('iframe');
    expect(iframe?.getAttribute('src')).toContain('cc_load_policy=1');
    expect(iframe?.getAttribute('src')).toContain(VALID_EMBED_URL);
  });

  it('uses the provided title as the iframe title attribute', async () => {
    const user = userEvent.setup();
    const { YouTubeEmbed } = await import('./YouTubeEmbed');
    render(<YouTubeEmbed embedUrl={VALID_EMBED_URL} title="My Test Video" />);

    await user.click(screen.getByRole('button'));

    const iframe = document.querySelector('iframe');
    expect(iframe?.getAttribute('title')).toBe('My Test Video');
  });

  it('shows YouTube branding label for YouTube URLs', async () => {
    const { YouTubeEmbed } = await import('./YouTubeEmbed');
    render(<YouTubeEmbed embedUrl={VALID_EMBED_URL} />);

    expect(screen.getByText('YouTube')).toBeInTheDocument();
  });

  it('shows Vimeo branding for vimeo URLs', async () => {
    const { YouTubeEmbed } = await import('./YouTubeEmbed');
    render(<YouTubeEmbed embedUrl="https://player.vimeo.com/video/123456" />);

    expect(screen.getByText('Vimeo')).toBeInTheDocument();
  });

  it('does not render thumbnail when URL has no extractable videoId and no thumbnailUrl', async () => {
    const { YouTubeEmbed } = await import('./YouTubeEmbed');
    // A non-YouTube/vimeo URL with no extractable id
    render(<YouTubeEmbed embedUrl="https://example.com/video/embed" />);

    // img should not be present (no thumbnail derivable, no thumbnailUrl provided)
    expect(document.querySelector('img')).toBeNull();
  });

  it('hides the play button once playing (iframe takes over)', async () => {
    const user = userEvent.setup();
    const { YouTubeEmbed } = await import('./YouTubeEmbed');
    render(<YouTubeEmbed embedUrl={VALID_EMBED_URL} />);

    await user.click(screen.getByRole('button'));

    // After play, the button is replaced by iframe; no button in DOM
    expect(screen.queryByRole('button')).toBeNull();
  });

  it('pre-play src does not include autoplay', async () => {
    const { YouTubeEmbed } = await import('./YouTubeEmbed');
    render(<YouTubeEmbed embedUrl={VALID_EMBED_URL} />);

    // No iframe at this point, so verify there's nothing with autoplay
    expect(document.querySelector('iframe[src*="autoplay=1"]')).toBeNull();
  });
});
