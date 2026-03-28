// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * GIPHY API client for GIF search and trending GIFs.
 * (Replaces former Tenor integration.)
 */

export interface TenorGif {
  id: string;
  url: string;
  preview_url: string;
  width: number;
  height: number;
}

interface GiphyImage {
  url: string;
  width: string;
  height: string;
}

interface GiphyResult {
  id: string;
  images: {
    original: GiphyImage;
    fixed_width_small: GiphyImage;
  };
}

interface GiphyResponse {
  data: GiphyResult[];
}

const GIPHY_API_BASE = 'https://api.giphy.com/v1/gifs';

function getApiKey(): string {
  return import.meta.env.VITE_GIPHY_API_KEY || '';
}

function mapResult(result: GiphyResult): TenorGif {
  const original = result.images.original;
  const preview = result.images.fixed_width_small;
  return {
    id: result.id,
    url: original.url,
    preview_url: preview.url,
    width: parseInt(original.width, 10),
    height: parseInt(original.height, 10),
  };
}

/**
 * Search for GIFs by query string.
 */
export async function searchGifs(query: string, limit = 20): Promise<TenorGif[]> {
  const key = getApiKey();
  if (!key) {
    console.error('[GIPHY] VITE_GIPHY_API_KEY is not set');
    return [];
  }

  try {
    const params = new URLSearchParams({
      q: query,
      api_key: key,
      limit: String(limit),
      rating: 'g',
    });

    const response = await fetch(`${GIPHY_API_BASE}/search?${params.toString()}`);
    if (!response.ok) {
      console.error(`[GIPHY] Search failed: ${response.status} ${response.statusText}`);
      return [];
    }

    const data: GiphyResponse = await response.json();
    return data.data.map(mapResult);
  } catch (err) {
    console.error('[GIPHY] Search error:', err);
    return [];
  }
}

/**
 * Fetch trending GIFs.
 */
export async function featured(limit = 20): Promise<TenorGif[]> {
  const key = getApiKey();
  if (!key) {
    console.error('[GIPHY] VITE_GIPHY_API_KEY is not set');
    return [];
  }

  try {
    const params = new URLSearchParams({
      api_key: key,
      limit: String(limit),
      rating: 'g',
    });

    const response = await fetch(`${GIPHY_API_BASE}/trending?${params.toString()}`);
    if (!response.ok) {
      console.error(`[GIPHY] Trending failed: ${response.status} ${response.statusText}`);
      return [];
    }

    const data: GiphyResponse = await response.json();
    return data.data.map(mapResult);
  } catch (err) {
    console.error('[GIPHY] Trending error:', err);
    return [];
  }
}
