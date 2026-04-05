<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\MarketplaceCollection;
use App\Models\MarketplaceCollectionItem;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceSavedSearch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MarketplaceDiscoveryService — Saved searches and collections/wishlists.
 *
 * All methods are tenant-scoped via model global scopes (HasTenantScope).
 */
class MarketplaceDiscoveryService
{
    // ─────────────────────────────────────────────────────────────────
    //  Saved Searches
    // ─────────────────────────────────────────────────────────────────

    /**
     * Create a saved search for a user.
     */
    public static function createSavedSearch(int $userId, array $data): MarketplaceSavedSearch
    {
        return MarketplaceSavedSearch::create([
            'user_id' => $userId,
            'name' => $data['name'],
            'search_query' => $data['search_query'] ?? null,
            'filters' => $data['filters'] ?? null,
            'alert_frequency' => $data['alert_frequency'] ?? 'daily',
            'alert_channel' => $data['alert_channel'] ?? 'email',
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Get all saved searches for a user.
     *
     * @return MarketplaceSavedSearch[]
     */
    public static function getSavedSearches(int $userId): array
    {
        return MarketplaceSavedSearch::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->all();
    }

    /**
     * Delete a saved search (only if owned by the user).
     */
    public static function deleteSavedSearch(int $id, int $userId): void
    {
        $search = MarketplaceSavedSearch::where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if ($search) {
            $search->delete();
        }
    }

    /**
     * Toggle saved search active status.
     */
    public static function toggleSavedSearch(int $id, int $userId, bool $isActive): ?MarketplaceSavedSearch
    {
        $search = MarketplaceSavedSearch::where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$search) {
            return null;
        }

        $search->is_active = $isActive;
        $search->save();

        return $search;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Collections
    // ─────────────────────────────────────────────────────────────────

    /**
     * Create a collection for a user.
     */
    public static function createCollection(int $userId, array $data): MarketplaceCollection
    {
        return MarketplaceCollection::create([
            'user_id' => $userId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_public' => $data['is_public'] ?? false,
        ]);
    }

    /**
     * Get all collections for a user.
     *
     * @return MarketplaceCollection[]
     */
    public static function getCollections(int $userId): array
    {
        return MarketplaceCollection::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->all();
    }

    /**
     * Update a collection's metadata.
     */
    public static function updateCollection(MarketplaceCollection $collection, array $data): MarketplaceCollection
    {
        $fillable = ['name', 'description', 'is_public'];

        foreach ($fillable as $field) {
            if (array_key_exists($field, $data)) {
                $collection->{$field} = $data[$field];
            }
        }

        $collection->save();

        return $collection;
    }

    /**
     * Delete a collection (only if owned by the user).
     */
    public static function deleteCollection(int $id, int $userId): void
    {
        $collection = MarketplaceCollection::where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if ($collection) {
            // Items are cascade-deleted by FK
            $collection->delete();
        }
    }

    /**
     * Add a listing to a collection.
     */
    public static function addToCollection(int $collectionId, int $listingId, ?string $note = null): void
    {
        $exists = MarketplaceCollectionItem::where('collection_id', $collectionId)
            ->where('marketplace_listing_id', $listingId)
            ->exists();

        if ($exists) {
            return;
        }

        MarketplaceCollectionItem::create([
            'collection_id' => $collectionId,
            'marketplace_listing_id' => $listingId,
            'note' => $note,
        ]);

        MarketplaceCollection::where('id', $collectionId)->increment('item_count');
    }

    /**
     * Remove a listing from a collection.
     */
    public static function removeFromCollection(int $collectionId, int $listingId): void
    {
        $deleted = MarketplaceCollectionItem::where('collection_id', $collectionId)
            ->where('marketplace_listing_id', $listingId)
            ->delete();

        if ($deleted) {
            MarketplaceCollection::where('id', $collectionId)->decrement('item_count');
        }
    }

    /**
     * Get items in a collection with cursor pagination.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public static function getCollectionItems(int $collectionId, int $limit = 20, ?string $cursor = null): array
    {
        $query = MarketplaceCollectionItem::where('collection_id', $collectionId)
            ->with([
                'listing' => fn ($q) => $q->with([
                    'user:id,first_name,last_name,avatar_url',
                    'category:id,name,slug,icon',
                    'images' => fn ($iq) => $iq->where('is_primary', true)->limit(1),
                ]),
            ])
            ->orderBy('id', 'desc');

        if ($cursor) {
            $decodedCursor = (int) base64_decode($cursor, true);
            if ($decodedCursor > 0) {
                $query->where('id', '<', $decodedCursor);
            }
        }

        $results = $query->limit($limit + 1)->get();
        $hasMore = $results->count() > $limit;
        if ($hasMore) {
            $results->pop();
        }

        $items = $results->map(function (MarketplaceCollectionItem $item) {
            $listing = $item->listing;
            if (!$listing) {
                return null;
            }

            $primaryImage = $listing->relationLoaded('images') ? $listing->images->first() : null;

            return [
                'collection_item_id' => $item->id,
                'note' => $item->note,
                'added_at' => $item->created_at?->toISOString(),
                'listing' => [
                    'id' => $listing->id,
                    'title' => $listing->title,
                    'price' => $listing->price,
                    'price_currency' => $listing->price_currency,
                    'price_type' => $listing->price_type,
                    'condition' => $listing->condition,
                    'location' => $listing->location,
                    'status' => $listing->status,
                    'image' => $primaryImage ? [
                        'url' => $primaryImage->image_url,
                        'thumbnail_url' => $primaryImage->thumbnail_url,
                        'alt_text' => $primaryImage->alt_text,
                    ] : null,
                    'category' => $listing->category ? [
                        'id' => $listing->category->id,
                        'name' => $listing->category->name,
                        'slug' => $listing->category->slug,
                        'icon' => $listing->category->icon,
                    ] : null,
                    'user' => $listing->user ? [
                        'id' => $listing->user->id,
                        'name' => trim($listing->user->first_name . ' ' . $listing->user->last_name),
                        'avatar_url' => $listing->user->avatar_url,
                    ] : null,
                    'created_at' => $listing->created_at?->toISOString(),
                ],
            ];
        })->filter()->values()->all();

        $nextCursor = $hasMore && $results->isNotEmpty()
            ? base64_encode((string) $results->last()->id)
            : null;

        return [
            'items' => $items,
            'cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }
}
