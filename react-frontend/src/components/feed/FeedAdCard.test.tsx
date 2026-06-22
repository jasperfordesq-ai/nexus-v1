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

vi.mock('@/contexts', () => createMockContexts());

import { api } from '@/lib/api';
import { FeedAdCard } from './FeedAdCard';
import type { AdItem } from './FeedAdCard';

const AD: AdItem = {
  campaign_id: 1,
  creative_id: 10,
  advertiser_name: 'ACME Corp',
  title: 'Buy our product',
  body: 'This is the ad body text.',
  image_url: 'https://example.com/ad.jpg',
  cta_url: 'https://acme.example.com',
  cta_label: 'Shop now',
};

const AD_NO_IMAGE: AdItem = {
  ...AD,
  image_url: null,
};

const AD_NO_BODY: AdItem = {
  ...AD,
  body: null,
};

const AD_NO_LABEL: AdItem = {
  ...AD,
  cta_label: null,
};

describe('FeedAdCard', () => {
  let windowOpenSpy: ReturnType<typeof vi.spyOn>;

  beforeEach(() => {
    vi.clearAllMocks();
    windowOpenSpy = vi.spyOn(window, 'open').mockImplementation(() => null);

    // Default: impression succeeds
    vi.mocked(api.post).mockImplementation((url: string) => {
      if (url === '/v2/ads/impression') {
        return Promise.resolve({ success: true, data: { impression_id: 99 } });
      }
      return Promise.resolve({ success: true });
    });
  });

  afterEach(() => {
    windowOpenSpy.mockRestore();
  });

  it('renders as an article with accessible label', () => {
    render(<FeedAdCard ad={AD} />);
    expect(screen.getByRole('article')).toBeInTheDocument();
  });

  it('renders the advertiser name', () => {
    render(<FeedAdCard ad={AD} />);
    expect(screen.getByText('ACME Corp')).toBeInTheDocument();
  });

  it('renders the ad title', () => {
    render(<FeedAdCard ad={AD} />);
    expect(screen.getByText('Buy our product')).toBeInTheDocument();
  });

  it('renders the ad body text', () => {
    render(<FeedAdCard ad={AD} />);
    expect(screen.getByText('This is the ad body text.')).toBeInTheDocument();
  });

  it('renders the CTA button with custom label', () => {
    render(<FeedAdCard ad={AD} />);
    expect(screen.getByRole('button', { name: /shop now/i })).toBeInTheDocument();
  });

  it('renders a "Sponsored" chip', () => {
    render(<FeedAdCard ad={AD} />);
    expect(screen.getByText(/sponsored/i)).toBeInTheDocument();
  });

  it('renders the ad image when image_url is provided', () => {
    render(<FeedAdCard ad={AD} />);
    const img = screen.getByRole('img', { name: 'Buy our product' });
    expect(img).toHaveAttribute('src', 'https://example.com/ad.jpg');
  });

  it('does not render an image when image_url is null', () => {
    render(<FeedAdCard ad={AD_NO_IMAGE} />);
    expect(screen.queryByRole('img')).not.toBeInTheDocument();
  });

  it('does not render body section when body is null', () => {
    render(<FeedAdCard ad={AD_NO_BODY} />);
    expect(screen.queryByText('This is the ad body text.')).not.toBeInTheDocument();
  });

  it('fires an impression API call on mount', async () => {
    render(<FeedAdCard ad={AD} />);
    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith('/v2/ads/impression', {
        creative_id: 10,
        placement: 'feed',
      });
    });
  });

  it('opens the CTA URL in a new tab when the CTA button is clicked', async () => {
    render(<FeedAdCard ad={AD} />);
    // Wait for impression to resolve so impressionIdRef is populated
    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith('/v2/ads/impression', expect.anything());
    });

    fireEvent.click(screen.getByRole('button', { name: /shop now/i }));

    expect(windowOpenSpy).toHaveBeenCalledWith(
      'https://acme.example.com',
      '_blank',
      'noopener,noreferrer'
    );
  });

  it('records a click API call after the impression has been captured', async () => {
    render(<FeedAdCard ad={AD} />);
    // Wait until impression resolves
    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith('/v2/ads/impression', expect.anything());
    });

    fireEvent.click(screen.getByRole('button', { name: /shop now/i }));

    await waitFor(() => {
      expect(api.post).toHaveBeenCalledWith('/v2/ads/impression/99/click');
    });
  });

  it('does not call the click API when impression ID is not yet set', async () => {
    // Impression call that never resolves so impressionIdRef stays null
    vi.mocked(api.post).mockImplementation(() => new Promise(() => {}));

    render(<FeedAdCard ad={AD} />);
    fireEvent.click(screen.getByRole('button', { name: /shop now/i }));

    // Allow microtasks to flush
    await new Promise((r) => setTimeout(r, 50));

    // Should have opened the URL but NOT called the click endpoint
    expect(windowOpenSpy).toHaveBeenCalledTimes(1);
    const clickCalls = vi.mocked(api.post).mock.calls.filter((c) =>
      String(c[0]).includes('/click')
    );
    expect(clickCalls).toHaveLength(0);
  });

  it('renders a default CTA label when cta_label is null', () => {
    render(<FeedAdCard ad={AD_NO_LABEL} />);
    // The i18n key 'feed_ad.cta_default' resolves to the key in tests
    const btn = screen.getByRole('button');
    expect(btn).toBeInTheDocument();
  });

  it('does not surface ad impression failures in the UI', async () => {
    vi.mocked(api.post).mockRejectedValue(new Error('Ad server down'));
    render(<FeedAdCard ad={AD} />);
    // The article should still render
    await waitFor(() => {
      expect(screen.getByRole('article')).toBeInTheDocument();
    });
  });
});
