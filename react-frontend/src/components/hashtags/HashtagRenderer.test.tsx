// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';
import React from 'react';

// ─── Mock api (not used by this component but required by vi.mock chain) ─────
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

// ─── Contexts ─────────────────────────────────────────────────────────────────
const mockToast = {
  success: vi.fn(),
  error: vi.fn(),
  info: vi.fn(),
  warning: vi.fn(),
  showToast: vi.fn(),
};

vi.mock('@/contexts', () =>
  createMockContexts({
    useAuth: () => ({
      user: { id: 1, name: 'Test User' },
      isAuthenticated: true,
      login: vi.fn(),
      logout: vi.fn(),
      register: vi.fn(),
      updateUser: vi.fn(),
      refreshUser: vi.fn(),
      status: 'idle' as const,
      error: null,
    }),
    useToast: () => mockToast,
    useTenant: () => ({
      tenant: { id: 2, name: 'Test', slug: 'test' },
      tenantPath: (p: string) => `/test${p}`,
      hasFeature: vi.fn(() => true),
      hasModule: vi.fn(() => true),
    }),
  })
);

// ─── Tests ───────────────────────────────────────────────────────────────────
describe('HashtagRenderer', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('renders plain text unchanged when no hashtags present', async () => {
    const { HashtagRenderer } = await import('./HashtagRenderer');
    render(<HashtagRenderer content="Hello world, no tags here." />);
    expect(screen.getByText('Hello world, no tags here.')).toBeInTheDocument();
  });

  it('renders a hashtag as a clickable link', async () => {
    const { HashtagRenderer } = await import('./HashtagRenderer');
    render(<HashtagRenderer content="I love #gardening" />);
    const link = screen.getByRole('link', { name: '#gardening' });
    expect(link).toBeInTheDocument();
  });

  it('hashtag link points to the feed hashtag page', async () => {
    const { HashtagRenderer } = await import('./HashtagRenderer');
    render(<HashtagRenderer content="Check out #timebanking" />);
    const link = screen.getByRole('link', { name: '#timebanking' });
    expect(link).toHaveAttribute('href', '/test/feed/hashtag/timebanking');
  });

  it('renders multiple hashtags as individual links', async () => {
    const { HashtagRenderer } = await import('./HashtagRenderer');
    render(<HashtagRenderer content="Enjoy #cooking and #music today" />);
    const cookingLink = screen.getByRole('link', { name: '#cooking' });
    const musicLink = screen.getByRole('link', { name: '#music' });
    expect(cookingLink).toBeInTheDocument();
    expect(musicLink).toBeInTheDocument();
  });

  it('preserves surrounding plain text around hashtags', async () => {
    const { HashtagRenderer } = await import('./HashtagRenderer');
    render(<HashtagRenderer content="Before #tag after" />);
    // plain text parts should still appear
    expect(screen.getByText(/Before/)).toBeInTheDocument();
    expect(screen.getByText(/after/)).toBeInTheDocument();
  });

  it('does not linkify a single-character hashtag (#a)', async () => {
    // Regex requires \w{2,} so single chars are left as plain text
    const { HashtagRenderer } = await import('./HashtagRenderer');
    render(<HashtagRenderer content="Use #a to mark items" />);
    // No links should be rendered for #a
    const links = screen.queryAllByRole('link');
    expect(links).toHaveLength(0);
  });

  it('renders two-character hashtags as links', async () => {
    const { HashtagRenderer } = await import('./HashtagRenderer');
    render(<HashtagRenderer content="Tag #go is valid" />);
    const link = screen.getByRole('link', { name: '#go' });
    expect(link).toBeInTheDocument();
  });

  it('applies the className prop to the outer span', async () => {
    const { HashtagRenderer } = await import('./HashtagRenderer');
    const { container } = render(
      <HashtagRenderer content="Hello #world" className="text-sm text-gray-700" />
    );
    const span = container.querySelector('span');
    expect(span).toHaveClass('text-sm');
    expect(span).toHaveClass('text-gray-700');
  });

  it('renders content with no hashtags inside a span', async () => {
    const { HashtagRenderer } = await import('./HashtagRenderer');
    const { container } = render(<HashtagRenderer content="Just text" className="my-class" />);
    const span = container.querySelector('span.my-class');
    expect(span).toBeInTheDocument();
    expect(span).toHaveTextContent('Just text');
  });

  it('handles hashtag at the very start of content', async () => {
    const { HashtagRenderer } = await import('./HashtagRenderer');
    render(<HashtagRenderer content="#welcome to the platform" />);
    const link = screen.getByRole('link', { name: '#welcome' });
    expect(link).toHaveAttribute('href', '/test/feed/hashtag/welcome');
  });

  it('handles hashtag at the very end of content', async () => {
    const { HashtagRenderer } = await import('./HashtagRenderer');
    render(<HashtagRenderer content="Great day for #volunteering" />);
    const link = screen.getByRole('link', { name: '#volunteering' });
    expect(link).toBeInTheDocument();
  });

  it('handles content with only a hashtag', async () => {
    const { HashtagRenderer } = await import('./HashtagRenderer');
    render(<HashtagRenderer content="#community" />);
    const link = screen.getByRole('link', { name: '#community' });
    expect(link).toHaveAttribute('href', '/test/feed/hashtag/community');
  });

  it('handles underscored hashtags like #time_banking', async () => {
    const { HashtagRenderer } = await import('./HashtagRenderer');
    render(<HashtagRenderer content="Join us for #time_banking" />);
    const link = screen.getByRole('link', { name: '#time_banking' });
    expect(link).toHaveAttribute('href', '/test/feed/hashtag/time_banking');
  });

  it('renders empty string content without crashing', async () => {
    const { HashtagRenderer } = await import('./HashtagRenderer');
    const { container } = render(<HashtagRenderer content="" />);
    expect(container.querySelector('span')).toBeInTheDocument();
  });
});
