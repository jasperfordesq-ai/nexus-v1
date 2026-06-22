// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tests for MessageLinkPreview.
 *
 * MessageLinkPreview uses a module-level previewCache Map. Each test uses a
 * unique URL string to avoid cross-test cache pollution.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@/test/test-utils';
import { createMockContexts } from '@/test/mock-contexts';

vi.mock('@/contexts', () => createMockContexts());

vi.mock('@/lib/api', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}));

import { api } from '@/lib/api';
import { MessageLinkPreview } from './MessageLinkPreview';

describe('MessageLinkPreview', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  // ── No URL in text ─────────────────────────────────────────────────────

  it('makes no API call when text contains no URL', () => {
    render(<MessageLinkPreview text="Hello, no links here!" />);
    expect(api.get).not.toHaveBeenCalled();
  });

  it('makes no API call for empty text', () => {
    render(<MessageLinkPreview text="" />);
    expect(api.get).not.toHaveBeenCalled();
  });

  it('renders no link card when text has no URL', () => {
    render(<MessageLinkPreview text="Just plain text" />);
    expect(screen.queryByRole('link')).not.toBeInTheDocument();
  });

  // ── URL detected, API returns preview ─────────────────────────────────

  it('fetches the link-preview endpoint when a URL is present', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (typeof url === 'string' && url.includes('/v2/link-preview')) {
        return Promise.resolve({
          success: true,
          data: {
            url: 'https://unique-alpha-001.com',
            title: 'Alpha Article',
            description: 'Alpha description',
            domain: 'unique-alpha-001.com',
          },
        });
      }
      return Promise.resolve({ success: false });
    });

    render(<MessageLinkPreview text="Check https://unique-alpha-001.com today" />);

    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith(
        expect.stringContaining('/v2/link-preview'),
        expect.any(Object)
      );
    });
  });

  it('renders the card title when API succeeds', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (typeof url === 'string' && url.includes('/v2/link-preview')) {
        return Promise.resolve({
          success: true,
          data: {
            url: 'https://unique-beta-002.com',
            title: 'Beta Unique Title',
            domain: 'unique-beta-002.com',
          },
        });
      }
      return Promise.resolve({ success: false });
    });

    render(<MessageLinkPreview text="Read https://unique-beta-002.com here" />);

    await waitFor(() => {
      expect(screen.getByText('Beta Unique Title')).toBeInTheDocument();
    });
  });

  it('renders the card description when provided', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (typeof url === 'string' && url.includes('/v2/link-preview')) {
        return Promise.resolve({
          success: true,
          data: {
            url: 'https://unique-gamma-003.com',
            title: 'Gamma Title',
            description: 'Gamma Unique Description',
            domain: 'unique-gamma-003.com',
          },
        });
      }
      return Promise.resolve({ success: false });
    });

    render(<MessageLinkPreview text="See https://unique-gamma-003.com now" />);

    await waitFor(() => {
      expect(screen.getByText('Gamma Unique Description')).toBeInTheDocument();
    });
  });

  it('renders an anchor link to the URL', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (typeof url === 'string' && url.includes('/v2/link-preview')) {
        return Promise.resolve({
          success: true,
          data: {
            url: 'https://unique-delta-004.com',
            title: 'Delta Title',
            domain: 'unique-delta-004.com',
          },
        });
      }
      return Promise.resolve({ success: false });
    });

    render(<MessageLinkPreview text="Visit https://unique-delta-004.com today" />);

    await waitFor(() => {
      const link = screen.getByRole('link');
      expect(link).toHaveAttribute('href', 'https://unique-delta-004.com');
    });
  });

  // ── API returns success=false → no card ────────────────────────────────

  it('renders no card when API returns success=false', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({ success: false, data: null });

    render(<MessageLinkPreview text="See https://unique-epsilon-005.com" />);

    await waitFor(() => {
      expect(api.get).toHaveBeenCalled();
    });
    expect(screen.queryByRole('link')).not.toBeInTheDocument();
  });

  // ── API throws → no card ───────────────────────────────────────────────

  it('renders no card when the API call rejects', async () => {
    vi.mocked(api.get).mockRejectedValueOnce(new Error('Network error'));

    render(<MessageLinkPreview text="Check https://unique-zeta-006.com please" />);

    await waitFor(() => {
      expect(api.get).toHaveBeenCalled();
    });
    expect(screen.queryByRole('link')).not.toBeInTheDocument();
  });

  // ── Only the FIRST URL is fetched ──────────────────────────────────────

  it('fetches only the first URL when text contains multiple URLs', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: { url: 'https://first-eta-007.com', title: 'First', domain: 'first-eta-007.com' },
    });

    render(
      <MessageLinkPreview
        text="First https://first-eta-007.com and second https://second-eta-007.com"
      />
    );

    await waitFor(() => {
      expect(api.get).toHaveBeenCalledTimes(1);
    });
    expect(api.get).toHaveBeenCalledWith(
      expect.stringContaining(encodeURIComponent('https://first-eta-007.com')),
      expect.any(Object)
    );
    expect(api.get).not.toHaveBeenCalledWith(
      expect.stringContaining(encodeURIComponent('https://second-eta-007.com')),
      expect.any(Object)
    );
  });

  // ── Trailing punctuation stripped from URL ─────────────────────────────

  it('strips trailing period from the detected URL before fetching', async () => {
    vi.mocked(api.get).mockResolvedValueOnce({
      success: true,
      data: { url: 'https://strip-theta-008.com/path', title: 'Strip', domain: 'strip-theta-008.com' },
    });

    render(<MessageLinkPreview text="See https://strip-theta-008.com/path. Done!" />);

    await waitFor(() => {
      expect(api.get).toHaveBeenCalledWith(
        expect.stringContaining(encodeURIComponent('https://strip-theta-008.com/path')),
        expect.any(Object)
      );
    });
    expect(api.get).not.toHaveBeenCalledWith(
      expect.stringContaining(encodeURIComponent('https://strip-theta-008.com/path.')),
      expect.any(Object)
    );
  });

  // ── Missing optional fields → graceful render ──────────────────────────

  it('renders without crashing when title and description are absent', async () => {
    vi.mocked(api.get).mockImplementation((url: string) => {
      if (typeof url === 'string' && url.includes('/v2/link-preview')) {
        return Promise.resolve({
          success: true,
          data: { url: 'https://unique-iota-009.com', domain: 'unique-iota-009.com' },
        });
      }
      return Promise.resolve({ success: false });
    });

    render(<MessageLinkPreview text="Look at https://unique-iota-009.com here" />);

    await waitFor(() => {
      const link = screen.getByRole('link');
      expect(link).toHaveAttribute('href', 'https://unique-iota-009.com');
    });
  });
});
