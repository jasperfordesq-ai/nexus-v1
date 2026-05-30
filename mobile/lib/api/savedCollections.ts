// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { api } from '@/lib/api/client';
import { API_V2 } from '@/lib/constants';

export interface SavedCollection {
  id: number;
  name: string;
  description: string | null;
  color: string | null;
  icon?: string | null;
  items_count: number;
  is_public: boolean;
}

export interface SavedItem {
  id: number;
  item_type: string;
  item_id: number;
  note: string | null;
  saved_at: string;
  preview?: { title?: string | null } | null;
}

export interface SavedCollectionsResponse {
  data: SavedCollection[];
}

export interface SavedCollectionItemsResponse {
  data: {
    items: SavedItem[];
    collection: SavedCollection;
  };
  meta?: {
    current_page?: number;
    last_page?: number;
    total_pages?: number;
    per_page?: number;
    total?: number;
  };
}

export interface CreateSavedCollectionPayload {
  name: string;
  description?: string | null;
  is_public?: boolean;
  color?: string;
  icon?: string;
}

export function getMySavedCollections(): Promise<SavedCollectionsResponse> {
  return api.get<SavedCollectionsResponse>(`${API_V2}/me/collections`);
}

export function getPublicSavedCollections(userId: number | string): Promise<SavedCollectionsResponse> {
  return api.get<SavedCollectionsResponse>(`${API_V2}/users/${userId}/public-collections`);
}

export function createSavedCollection(payload: CreateSavedCollectionPayload): Promise<{ data: SavedCollection }> {
  return api.post<{ data: SavedCollection }>(`${API_V2}/me/collections`, payload);
}

export function getSavedCollectionItems(collectionId: number | string, page = 1, perPage = 20): Promise<SavedCollectionItemsResponse> {
  return api.get<SavedCollectionItemsResponse>(`${API_V2}/me/collections/${collectionId}/items`, {
    page: String(page),
    per_page: String(perPage),
  });
}

export function removeSavedItem(savedItemId: number | string): Promise<void> {
  return api.delete<void>(`${API_V2}/me/saved-items/${savedItemId}`);
}
