// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Normalise a V2 collection payload into a plain item array.
 *
 * The api client already unwraps the `{ data, meta }` envelope, so for
 * `respondWithCollection` endpoints `response.data` IS the item array
 * (pagination lives on `response.meta`). Some endpoints instead return an
 * object payload of `{ items }` or `{ data: { items } }` — this accepts all
 * three shapes and returns `[]` for anything else.
 */
export function extractCollectionItems<T>(payload: unknown): T[] {
  if (Array.isArray(payload)) {
    return payload as T[];
  }

  if (payload && typeof payload === 'object') {
    const wrapped = payload as { items?: T[]; data?: { items?: T[] } };
    if (Array.isArray(wrapped.items)) {
      return wrapped.items;
    }
    if (Array.isArray(wrapped.data?.items)) {
      return wrapped.data.items;
    }
  }

  return [];
}
