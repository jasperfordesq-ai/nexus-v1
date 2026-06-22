// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { featured, searchGifs } from './tenor';

function giphyPayload() {
  return {
    data: [
      {
        id: 'abc',
        images: {
          original: { url: 'https://giphy/orig.gif', width: '480', height: '270' },
          fixed_width_small: { url: 'https://giphy/small.gif', width: '100', height: '56' },
        },
      },
    ],
  };
}

describe('tenor (GIPHY) client', () => {
  beforeEach(() => {
    vi.stubGlobal('fetch', vi.fn());
    vi.spyOn(console, 'error').mockImplementation(() => {});
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.unstubAllEnvs();
    vi.restoreAllMocks();
  });

  describe('without an API key', () => {
    beforeEach(() => vi.stubEnv('VITE_GIPHY_API_KEY', ''));

    it('searchGifs returns [] and logs an error', async () => {
      expect(await searchGifs('cats')).toEqual([]);
      expect(console.error).toHaveBeenCalled();
      expect(fetch).not.toHaveBeenCalled();
    });

    it('featured returns [] and logs an error', async () => {
      expect(await featured()).toEqual([]);
      expect(console.error).toHaveBeenCalled();
      expect(fetch).not.toHaveBeenCalled();
    });
  });

  describe('with an API key', () => {
    beforeEach(() => vi.stubEnv('VITE_GIPHY_API_KEY', 'test-key'));

    it('searchGifs maps the GIPHY response and builds a search URL', async () => {
      vi.mocked(fetch).mockResolvedValueOnce({
        ok: true,
        json: async () => giphyPayload(),
      } as Response);

      const out = await searchGifs('happy', 5);

      expect(out).toEqual([
        {
          id: 'abc',
          url: 'https://giphy/orig.gif',
          preview_url: 'https://giphy/small.gif',
          width: 480,
          height: 270,
        },
      ]);
      const calledUrl = vi.mocked(fetch).mock.calls[0][0] as string;
      expect(calledUrl).toContain('/search?');
      expect(calledUrl).toContain('q=happy');
      expect(calledUrl).toContain('api_key=test-key');
      expect(calledUrl).toContain('limit=5');
      expect(calledUrl).toContain('rating=g');
    });

    it('featured maps the GIPHY response and builds a trending URL', async () => {
      vi.mocked(fetch).mockResolvedValueOnce({
        ok: true,
        json: async () => giphyPayload(),
      } as Response);

      const out = await featured(3);

      expect(out).toHaveLength(1);
      expect(out[0].id).toBe('abc');
      const calledUrl = vi.mocked(fetch).mock.calls[0][0] as string;
      expect(calledUrl).toContain('/trending?');
      expect(calledUrl).toContain('limit=3');
    });

    it('searchGifs returns [] on a non-ok response', async () => {
      vi.mocked(fetch).mockResolvedValueOnce({
        ok: false,
        status: 429,
        statusText: 'Too Many Requests',
      } as Response);
      expect(await searchGifs('x')).toEqual([]);
    });

    it('searchGifs returns [] when fetch rejects', async () => {
      vi.mocked(fetch).mockRejectedValueOnce(new Error('network'));
      expect(await searchGifs('x')).toEqual([]);
    });

    it('featured returns [] on a non-ok response', async () => {
      vi.mocked(fetch).mockResolvedValueOnce({
        ok: false,
        status: 500,
        statusText: 'Server Error',
      } as Response);
      expect(await featured()).toEqual([]);
    });
  });
});
