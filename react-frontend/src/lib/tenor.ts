// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tenor API v2 client for GIF search and featured/trending GIFs.
 */

export interface TenorGif {
  id: string;
  url: string;
  preview_url: string;
  width: number;
  height: number;
}

interface TenorMediaFormat {
  url: string;
  dims: [number, number];
  size: number;
}

interface TenorResult {
  id: string;
  media_formats: {
    gif: TenorMediaFormat;
    tinygif: TenorMediaFormat;
  };
}

interface TenorResponse {
  results: TenorResult[];
  next: string;
}

const TENOR_API_BASE = 'https://tenor.googleapis.com/v2';

function getApiKey(): string {
  return import.meta.env.VITE_TENOR_API_KEY || '';
}

function mapResult(result: TenorResult): TenorGif {
  const gif = result.media_formats.gif;
  const tinygif = result.media_formats.tinygif;
  return {
    id: result.id,
    url: gif.url,
    preview_url: tinygif.url,
    width: gif.dims[0],
    height: gif.dims[1],
  };
}

/**
 * Search for GIFs by query string.
 */
export async function searchGifs(query: string, limit = 20): Promise<TenorGif[]> {
  const key = getApiKey();
  if (!key) {
    console.error('[Tenor] VITE_TENOR_API_KEY is not set');
    return [];
  }

  try {
    const params = new URLSearchParams({
      q: query,
      key,
      client_key: 'project_nexus',
      limit: String(limit),
      media_filter: 'gif,tinygif',
    });

    const response = await fetch(`${TENOR_API_BASE}/search?${params.toString()}`);
    if (!response.ok) {
      console.error(`[Tenor] Search failed: ${response.status} ${response.statusText}`);
      return [];
    }

    const data: TenorResponse = await response.json();
    return data.results.map(mapResult);
  } catch (err) {
    console.error('[Tenor] Search error:', err);
    return [];
  }
}

/**
 * Fetch featured/trending GIFs.
 */
export async function featured(limit = 20): Promise<TenorGif[]> {
  const key = getApiKey();
  if (!key) {
    console.error('[Tenor] VITE_TENOR_API_KEY is not set');
    return [];
  }

  try {
    const params = new URLSearchParams({
      key,
      client_key: 'project_nexus',
      limit: String(limit),
      media_filter: 'gif,tinygif',
    });

    const response = await fetch(`${TENOR_API_BASE}/featured?${params.toString()}`);
    if (!response.ok) {
      console.error(`[Tenor] Featured failed: ${response.status} ${response.statusText}`);
      return [];
    }

    const data: TenorResponse = await response.json();
    return data.results.map(mapResult);
  } catch (err) {
    console.error('[Tenor] Featured error:', err);
    return [];
  }
}
