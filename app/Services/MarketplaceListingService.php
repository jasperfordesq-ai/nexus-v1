<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceImage;
use App\Models\MarketplaceSavedListing;
use App\Services\MarketplaceConfigurationService;
use App\Services\SearchService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * MarketplaceListingService — Standalone service for marketplace listings.
 *
 * Completely separate from ListingService (timebanking). Uses its own
 * marketplace_listings table and marketplace_listings Meilisearch index.
 */
class MarketplaceListingService
{
    // -----------------------------------------------------------------
    //  Read
    // -----------------------------------------------------------------

    /**
     * Get active marketplace listings with filtering and cursor pagination.
     *
     * @param array{
     *   category_id?: int,
     *   category_slug?: string,
     *   search?: string,
     *   price_min?: float,
     *   price_max?: float,
     *   price_type?: string,
     *   condition?: string|string[],
     *   seller_type?: string,
     *   delivery_method?: string,
     *   posted_within?: int,
     *   user_id?: int,
     *   current_user_id?: int|null,
     *   featured_first?: bool,
     *   limit?: int,
     *   cursor?: string|null,
     *   sort?: string,
     * } $filters
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public static function getAll(array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $cursor = $filters['cursor'] ?? null;
        $currentUserId = !empty($filters['current_user_id'])
            ? (int) $filters['current_user_id']
            : null;

        // ── Meilisearch search path ──────────────────────────────────────────
        // When a search term is present and Meilisearch is available, use it for
        // typo-tolerant, relevance-ranked ID retrieval, then hydrate from SQL.
        // Falls through to the SQL path below if Meilisearch is unavailable.
        // Skip Meilisearch when browsing own listings (user_id filter) or when
        // price range / posted_within facets are active — they're applied
        // post-search in SQL which breaks pagination.
        $hasFacetedFilters = !empty($filters['price_min']) || !empty($filters['price_max'])
            || !empty($filters['posted_within']);
        if (!empty($filters['search']) && empty($filters['user_id']) && !$hasFacetedFilters) {
            $tenantId = TenantContext::getId();

            // Decode Meilisearch offset cursor (format: "meili:<offset>")
            $meiliOffset = 0;
            if ($cursor !== null) {
                $decoded = base64_decode($cursor, true);
                if ($decoded !== false && str_starts_with($decoded, 'meili:')) {
                    $meiliOffset = (int) substr($decoded, 6);
                }
            }

            // Build Meilisearch filter expressions from applicable filters
            $meiliFilters = [];

            if (!empty($filters['category_id'])) {
                $meiliFilters[] = 'category_id = ' . (int) $filters['category_id'];
            } elseif (!empty($filters['category_slug'])) {
                $catId = DB::table('marketplace_categories')
                    ->where('slug', $filters['category_slug'])
                    ->where(function ($q) {
                        $q->where('tenant_id', TenantContext::getId())
                          ->orWhereNull('tenant_id');
                    })
                    ->value('id');
                if ($catId) {
                    $meiliFilters[] = 'category_id = ' . $catId;
                }
            }

            if (!empty($filters['price_type'])) {
                $meiliFilters[] = "price_type = '{$filters['price_type']}'";
            }

            if (!empty($filters['condition'])) {
                $conditions = is_array($filters['condition'])
                    ? $filters['condition']
                    : explode(',', $filters['condition']);
                if (count($conditions) === 1) {
                    $meiliFilters[] = "condition = '" . str_replace("'", "\\'", $conditions[0]) . "'";
                } else {
                    $parts = array_map(fn($c) => "condition = '" . str_replace("'", "\\'", $c) . "'", $conditions);
                    $meiliFilters[] = '(' . implode(' OR ', $parts) . ')';
                }
            }

            if (!empty($filters['seller_type'])) {
                $meiliFilters[] = "seller_type = '{$filters['seller_type']}'";
            }

            if (!empty($filters['delivery_method'])) {
                $meiliFilters[] = "delivery_method = '{$filters['delivery_method']}'";
            }

            $meiliResult = SearchService::searchMarketplaceListingIds(
                $filters['search'], $tenantId, $meiliFilters, $limit + 1, $meiliOffset
            );

            if ($meiliResult !== null) {
                $ids     = $meiliResult['ids'];
                $hasMore = count($ids) > $limit;
                if ($hasMore) {
                    array_pop($ids);
                }

                if (empty($ids)) {
                    return ['items' => [], 'cursor' => null, 'has_more' => false];
                }

                $q = MarketplaceListing::query()
                    ->with([
                        'user:id,first_name,last_name,avatar_url,is_verified',
                        'category:id,name,slug,icon',
                        'images' => fn ($qq) => $qq->orderBy('sort_order')->limit(5),
                    ])
                    ->whereIn('id', $ids)
                    ->where('status', 'active')
                    ->where('moderation_status', 'approved');

                $listingsById = $q->get()->keyBy('id');

                // Saved listing IDs for current user
                $savedIds = [];
                if ($currentUserId && $listingsById->isNotEmpty()) {
                    $savedIds = DB::table('marketplace_saved_listings')
                        ->where('user_id', $currentUserId)
                        ->where('tenant_id', $tenantId)
                        ->whereIn('marketplace_listing_id', $listingsById->keys())
                        ->pluck('marketplace_listing_id')
                        ->flip()
                        ->all();
                }

                // Preserve Meilisearch relevance order
                $items = [];
                foreach ($ids as $id) {
                    $listing = $listingsById[$id] ?? null;
                    if ($listing) {
                        $items[] = self::formatListingItem($listing, $savedIds, $currentUserId);
                    }
                }

                return [
                    'items'    => $items,
                    'cursor'   => $hasMore ? base64_encode('meili:' . ($meiliOffset + $limit)) : null,
                    'has_more' => $hasMore,
                ];
            }
            // Meilisearch unavailable — fall through to SQL path
        }
        // ── End Meilisearch path ─────────────────────────────────────────────

        $query = MarketplaceListing::query()
            ->with([
                'user:id,first_name,last_name,avatar_url,is_verified',
                'category:id,name,slug,icon',
                'images' => fn ($q) => $q->orderBy('sort_order')->limit(5),
            ])
            ;

        // When browsing own listings, show all statuses; otherwise only active+approved
        if (!empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
            // Optionally filter by status if specified
            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }
        } else {
            $query->where('status', 'active')
                  ->where('moderation_status', 'approved');
        }

        // Category filter
        if (!empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        } elseif (!empty($filters['category_slug'])) {
            $catId = DB::table('marketplace_categories')
                ->where('slug', $filters['category_slug'])
                ->where(function ($q) {
                    $q->where('tenant_id', TenantContext::getId())
                      ->orWhereNull('tenant_id');
                })
                ->value('id');
            if ($catId) {
                $query->where('category_id', $catId);
            }
        }

        // Search (SQL fallback — used when Meilisearch is unavailable)
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->whereRaw('MATCH(title, description) AGAINST(? IN BOOLEAN MODE)', [$search])
                  ->orWhere('title', 'LIKE', "%{$search}%");
            });
        }

        // Price range
        if (!empty($filters['price_min'])) {
            $query->where('price', '>=', (float) $filters['price_min']);
        }
        if (!empty($filters['price_max'])) {
            $query->where('price', '<=', (float) $filters['price_max']);
        }

        // Price type (free items)
        if (!empty($filters['price_type'])) {
            $query->where('price_type', $filters['price_type']);
        }

        // Condition
        if (!empty($filters['condition'])) {
            $conditions = is_array($filters['condition'])
                ? $filters['condition']
                : explode(',', $filters['condition']);
            $query->whereIn('condition', $conditions);
        }

        // Seller type
        if (!empty($filters['seller_type'])) {
            $query->where('seller_type', $filters['seller_type']);
        }

        // Delivery method
        if (!empty($filters['delivery_method'])) {
            $query->where('delivery_method', $filters['delivery_method']);
        }

        // Posted within X days
        if (!empty($filters['posted_within'])) {
            $query->where('created_at', '>=', now()->subDays((int) $filters['posted_within']));
        }

        // Featured first (must be BEFORE the main sort so it takes priority)
        if (!empty($filters['featured_first'])) {
            $query->orderByRaw('promoted_until > NOW() DESC');
        }

        // Sorting
        $sort = $filters['sort'] ?? 'newest';
        match ($sort) {
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'popular' => $query->orderBy('views_count', 'desc'),
            default => $query->orderBy('id', 'desc'), // newest
        };

        // Cursor pagination
        if ($cursor) {
            $decodedCursor = (int) base64_decode($cursor, true);
            if ($decodedCursor > 0) {
                if ($sort === 'price_asc') {
                    $query->where('id', '>', $decodedCursor);
                } else {
                    $query->where('id', '<', $decodedCursor);
                }
            }
        }

        $listings = $query->limit($limit + 1)->get();
        $hasMore = $listings->count() > $limit;
        if ($hasMore) {
            $listings->pop();
        }

        // Saved listing IDs for current user
        $savedIds = [];
        if ($currentUserId && $listings->isNotEmpty()) {
            $savedIds = DB::table('marketplace_saved_listings')
                ->where('user_id', $currentUserId)
                ->where('tenant_id', TenantContext::getId())
                ->whereIn('marketplace_listing_id', $listings->pluck('id'))
                ->pluck('marketplace_listing_id')
                ->flip()
                ->all();
        }

        $items = $listings->map(fn ($l) => self::formatListingItem($l, $savedIds, $currentUserId))->values()->all();

        $nextCursor = $hasMore && $listings->isNotEmpty()
            ? base64_encode((string) $listings->last()->id)
            : null;

        return [
            'items' => $items,
            'cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get a single marketplace listing by ID.
     */
    public static function getById(int $id, ?int $currentUserId = null): ?array
    {
        $listing = MarketplaceListing::query()
            ->with([
                'user:id,first_name,last_name,avatar_url,is_verified,created_at',
                'category:id,name,slug,icon',
                'images' => fn ($q) => $q->orderBy('sort_order'),
            ])
            ->find($id);

        if (!$listing) {
            return null;
        }

        $isSaved = false;
        if ($currentUserId) {
            $isSaved = MarketplaceSavedListing::where('user_id', $currentUserId)
                ->where('marketplace_listing_id', $id)
                ->exists();
        }

        return self::formatListingDetail($listing, $isSaved, $currentUserId);
    }

    /**
     * Get nearby marketplace listings using haversine distance.
     */
    public static function getNearby(float $lat, float $lng, float $radiusKm = 25, int $limit = 20): array
    {
        $listings = MarketplaceListing::query()
            ->with([
                'user:id,first_name,last_name,avatar_url',
                'category:id,name,slug,icon',
                'images' => fn ($q) => $q->where('is_primary', true)->limit(1),
            ])
            ->where('status', 'active')
            ->where('moderation_status', 'approved')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->selectRaw('*, (
                6371 * acos(
                    cos(radians(?)) * cos(radians(latitude)) *
                    cos(radians(longitude) - radians(?)) +
                    sin(radians(?)) * sin(radians(latitude))
                )
            ) AS distance_km', [$lat, $lng, $lat])
            ->having('distance_km', '<=', $radiusKm)
            ->orderBy('distance_km')
            ->limit($limit)
            ->get();

        return $listings->map(fn ($l) => array_merge(
            self::formatListingItem($l, [], null),
            ['distance_km' => round($l->distance_km, 1)]
        ))->all();
    }

    // -----------------------------------------------------------------
    //  Write
    // -----------------------------------------------------------------

    /**
     * Create a new marketplace listing.
     */
    public static function create(int $userId, array $data): MarketplaceListing
    {
        // Enforce max active listings per seller
        $maxListings = MarketplaceConfigurationService::maxActiveListings();
        if ($maxListings > 0) {
            $activeCount = MarketplaceListing::where('user_id', $userId)
                ->where('status', 'active')
                ->count();
            if ($activeCount >= $maxListings) {
                throw new \InvalidArgumentException("Maximum of {$maxListings} active listings reached.");
            }
        }

        $listing = new MarketplaceListing();
        $listing->tenant_id = TenantContext::getId();
        $listing->user_id = $userId;
        $listing->title = $data['title'];
        $listing->description = $data['description'];
        $listing->tagline = $data['tagline'] ?? null;
        $listing->price = $data['price'] ?? null;
        $listing->price_currency = $data['price_currency'] ?? 'EUR';
        $listing->price_type = $data['price_type'] ?? 'fixed';
        $listing->time_credit_price = $data['time_credit_price'] ?? null;
        $listing->category_id = $data['category_id'] ?? null;
        $listing->condition = $data['condition'] ?? null;
        $listing->quantity = $data['quantity'] ?? 1;
        $listing->location = $data['location'] ?? null;
        $listing->latitude = $data['latitude'] ?? null;
        $listing->longitude = $data['longitude'] ?? null;
        $listing->shipping_available = $data['shipping_available'] ?? false;
        $listing->local_pickup = $data['local_pickup'] ?? true;
        $listing->delivery_method = $data['delivery_method'] ?? 'pickup';
        $listing->seller_type = $data['seller_type'] ?? 'private';
        $listing->status = $data['status'] ?? 'active';
        // Only require moderation if explicitly enabled for this tenant
        try {
            $moderationEnabled = MarketplaceConfigurationService::moderationEnabled();
        } catch (\Throwable $e) {
            $moderationEnabled = false; // Default to no moderation if config unavailable
        }
        $listing->moderation_status = $moderationEnabled ? 'pending' : 'approved';
        $listing->template_data = $data['template_data'] ?? null;
        $durationDays = (int) ($data['duration_days'] ?? MarketplaceConfigurationService::listingDurationDays());
        $listing->expires_at = now()->addDays($durationDays);
        $listing->save();

        // Geocode if location provided but no coordinates
        if ($listing->location && !$listing->latitude) {
            self::geocodeListing($listing);
        }

        // Sync to Meilisearch index (non-blocking, best-effort)
        SearchService::indexMarketplaceListing($listing);

        return $listing;
    }

    /**
     * Update an existing marketplace listing.
     */
    public static function update(MarketplaceListing $listing, array $data): MarketplaceListing
    {
        $fillable = [
            'title', 'description', 'tagline', 'price', 'price_currency',
            'price_type', 'time_credit_price', 'category_id', 'condition',
            'quantity', 'location', 'latitude', 'longitude',
            'shipping_available', 'local_pickup', 'delivery_method',
            'seller_type', 'status', 'template_data',
        ];

        foreach ($fillable as $field) {
            if (array_key_exists($field, $data)) {
                $listing->{$field} = $data[$field];
            }
        }

        $listing->save();

        // Re-geocode if location changed
        if (isset($data['location']) && !isset($data['latitude'])) {
            self::geocodeListing($listing);
        }

        // Sync updated listing to Meilisearch index (non-blocking, best-effort)
        SearchService::indexMarketplaceListing($listing);

        return $listing;
    }

    /**
     * Soft-remove a marketplace listing.
     */
    public static function remove(MarketplaceListing $listing): void
    {
        $listing->status = 'removed';
        $listing->save();

        // Remove from Meilisearch index (non-blocking, best-effort)
        SearchService::removeMarketplaceListing($listing->id);
    }

    /**
     * Renew an expired listing.
     */
    public static function renew(MarketplaceListing $listing, int $durationDays = 30): MarketplaceListing
    {
        $listing->status = 'active';
        $listing->expires_at = now()->addDays($durationDays);
        $listing->renewed_at = now();
        $listing->renewal_count = ($listing->renewal_count ?? 0) + 1;
        $listing->save();

        // Re-index renewed listing in Meilisearch
        SearchService::indexMarketplaceListing($listing);

        return $listing;
    }

    // -----------------------------------------------------------------
    //  Images
    // -----------------------------------------------------------------

    /**
     * Add images to a marketplace listing.
     *
     * @param MarketplaceListing $listing
     * @param array<array{url: string, thumbnail_url?: string, alt_text?: string}> $images
     */
    public static function addImages(MarketplaceListing $listing, array $images): void
    {
        $maxSort = MarketplaceImage::where('marketplace_listing_id', $listing->id)->max('sort_order') ?? -1;

        foreach ($images as $i => $img) {
            MarketplaceImage::create([
                'tenant_id' => $listing->tenant_id,
                'marketplace_listing_id' => $listing->id,
                'image_url' => $img['url'],
                'thumbnail_url' => $img['thumbnail_url'] ?? null,
                'alt_text' => $img['alt_text'] ?? null,
                'sort_order' => $maxSort + $i + 1,
                'is_primary' => $maxSort < 0 && $i === 0,
            ]);
        }
    }

    /**
     * Reorder images for a listing.
     *
     * @param MarketplaceListing $listing
     * @param int[] $imageIds Ordered array of image IDs
     */
    public static function reorderImages(MarketplaceListing $listing, array $imageIds): void
    {
        foreach ($imageIds as $order => $imageId) {
            MarketplaceImage::where('id', $imageId)
                ->where('marketplace_listing_id', $listing->id)
                ->update([
                    'sort_order' => $order,
                    'is_primary' => $order === 0,
                ]);
        }
    }

    /**
     * Delete an image from a listing.
     */
    public static function deleteImage(MarketplaceListing $listing, int $imageId): bool
    {
        return MarketplaceImage::where('id', $imageId)
            ->where('marketplace_listing_id', $listing->id)
            ->delete() > 0;
    }

    // -----------------------------------------------------------------
    //  Saved / Favorites
    // -----------------------------------------------------------------

    /**
     * Save/bookmark a listing for a user.
     */
    public static function saveListing(int $userId, int $listingId): void
    {
        MarketplaceSavedListing::firstOrCreate([
            'tenant_id' => TenantContext::getId(),
            'user_id' => $userId,
            'marketplace_listing_id' => $listingId,
        ]);

        MarketplaceListing::where('id', $listingId)->increment('saves_count');
    }

    /**
     * Unsave/unbookmark a listing.
     */
    public static function unsaveListing(int $userId, int $listingId): void
    {
        $deleted = MarketplaceSavedListing::where('user_id', $userId)
            ->where('marketplace_listing_id', $listingId)
            ->delete();

        if ($deleted) {
            MarketplaceListing::where('id', $listingId)->decrement('saves_count');
        }
    }

    /**
     * Get saved marketplace listings for a user.
     */
    public static function getSavedListings(int $userId, int $limit = 20, ?string $cursor = null): array
    {
        $query = MarketplaceSavedListing::query()
            ->where('user_id', $userId)
            ->orderBy('id', 'desc');

        if ($cursor) {
            $query->where('id', '<', (int) base64_decode($cursor, true));
        }

        $saved = $query->limit($limit + 1)->get();
        $hasMore = $saved->count() > $limit;
        if ($hasMore) {
            $saved->pop();
        }

        $listingIds = $saved->pluck('marketplace_listing_id');
        $listings = MarketplaceListing::query()
            ->with([
                'user:id,first_name,last_name,avatar_url',
                'category:id,name,slug,icon',
                'images' => fn ($q) => $q->where('is_primary', true)->limit(1),
            ])
            ->whereIn('id', $listingIds)
            ->get()
            ->keyBy('id');

        $items = $saved->map(function ($s) use ($listings) {
            $listing = $listings[$s->marketplace_listing_id] ?? null;
            if (!$listing) {
                return null;
            }
            return self::formatListingItem($listing, [$listing->id => true], null);
        })->filter()->values()->all();

        return [
            'items' => $items,
            'cursor' => $hasMore && $saved->isNotEmpty() ? base64_encode((string) $saved->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    // -----------------------------------------------------------------
    //  Analytics
    // -----------------------------------------------------------------

    /**
     * Increment view count for a listing.
     */
    public static function recordView(int $listingId): void
    {
        MarketplaceListing::where('id', $listingId)->increment('views_count');
    }

    /**
     * Get analytics for a listing (owner view).
     */
    public static function getAnalytics(MarketplaceListing $listing): array
    {
        $offerCount = DB::table('marketplace_offers')
            ->where('marketplace_listing_id', $listing->id)
            ->where('tenant_id', $listing->tenant_id)
            ->count();

        return [
            'views' => $listing->views_count,
            'saves' => $listing->saves_count,
            'contacts' => $listing->contacts_count,
            'offers' => $offerCount,
            'created_at' => $listing->created_at?->toISOString(),
            'expires_at' => $listing->expires_at?->toISOString(),
            'days_active' => $listing->created_at?->diffInDays(now()),
        ];
    }

    // -----------------------------------------------------------------
    //  Categories
    // -----------------------------------------------------------------

    /**
     * Get marketplace categories with listing counts.
     */
    public static function getCategories(): array
    {
        $tenantId = TenantContext::getId();

        $categories = DB::table('marketplace_categories')
            ->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            })
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        // Get listing counts per category
        $counts = DB::table('marketplace_listings')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('moderation_status', 'approved')
            ->groupBy('category_id')
            ->selectRaw('category_id, COUNT(*) as count')
            ->pluck('count', 'category_id');

        return $categories->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'slug' => $c->slug,
            'description' => $c->description,
            'icon' => $c->icon,
            'parent_id' => $c->parent_id,
            'listing_count' => $counts[$c->id] ?? 0,
        ])->all();
    }

    // -----------------------------------------------------------------
    //  Formatting helpers
    // -----------------------------------------------------------------

    private static function formatListingItem(MarketplaceListing $listing, array $savedIds, ?int $currentUserId): array
    {
        $primaryImage = $listing->relationLoaded('images')
            ? $listing->images->first()
            : null;

        return [
            'id' => $listing->id,
            'title' => $listing->title,
            'tagline' => $listing->tagline,
            'price' => $listing->price,
            'price_currency' => $listing->price_currency,
            'price_type' => $listing->price_type,
            'time_credit_price' => $listing->time_credit_price,
            'condition' => $listing->condition,
            'location' => $listing->location,
            'delivery_method' => $listing->delivery_method,
            'seller_type' => $listing->seller_type,
            'status' => $listing->status,
            'image' => $primaryImage ? [
                'url' => $primaryImage->image_url,
                'thumbnail_url' => $primaryImage->thumbnail_url,
                'alt_text' => $primaryImage->alt_text,
            ] : null,
            'image_count' => $listing->images_count ?? $listing->images->count(),
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
                'is_verified' => $listing->user->is_verified ?? false,
            ] : null,
            'is_saved' => isset($savedIds[$listing->id]),
            'is_own' => $currentUserId && $listing->user_id === $currentUserId,
            'is_promoted' => $listing->promoted_until && $listing->promoted_until > now(),
            'views_count' => $listing->views_count,
            'created_at' => $listing->created_at?->toISOString(),
        ];
    }

    private static function formatListingDetail(MarketplaceListing $listing, bool $isSaved, ?int $currentUserId): array
    {
        $images = $listing->relationLoaded('images')
            ? $listing->images->map(fn ($img) => [
                'id' => $img->id,
                'url' => $img->image_url,
                'thumbnail_url' => $img->thumbnail_url,
                'alt_text' => $img->alt_text,
                'is_primary' => $img->is_primary,
            ])->all()
            : [];

        return [
            'id' => $listing->id,
            'title' => $listing->title,
            'description' => $listing->description,
            'tagline' => $listing->tagline,
            'price' => $listing->price,
            'price_currency' => $listing->price_currency,
            'price_type' => $listing->price_type,
            'time_credit_price' => $listing->time_credit_price,
            'category' => $listing->category ? [
                'id' => $listing->category->id,
                'name' => $listing->category->name,
                'slug' => $listing->category->slug,
                'icon' => $listing->category->icon,
            ] : null,
            'condition' => $listing->condition,
            'quantity' => $listing->quantity,
            'location' => $listing->location,
            'latitude' => $listing->latitude,
            'longitude' => $listing->longitude,
            'shipping_available' => $listing->shipping_available,
            'local_pickup' => $listing->local_pickup,
            'delivery_method' => $listing->delivery_method,
            'seller_type' => $listing->seller_type,
            'status' => $listing->status,
            'template_data' => $listing->template_data,
            'video_url' => $listing->video_url,
            'images' => $images,
            'user' => $listing->user ? [
                'id' => $listing->user->id,
                'name' => trim($listing->user->first_name . ' ' . $listing->user->last_name),
                'avatar_url' => $listing->user->avatar_url,
                'is_verified' => $listing->user->is_verified ?? false,
                'member_since' => $listing->user->created_at?->toISOString(),
            ] : null,
            'is_saved' => $isSaved,
            'is_own' => $currentUserId && $listing->user_id === $currentUserId,
            'is_promoted' => $listing->promoted_until && $listing->promoted_until > now(),
            'views_count' => $listing->views_count,
            'saves_count' => $listing->saves_count,
            'expires_at' => $listing->expires_at?->toISOString(),
            'created_at' => $listing->created_at?->toISOString(),
            'updated_at' => $listing->updated_at?->toISOString(),
        ];
    }

    // -----------------------------------------------------------------
    //  Helpers
    // -----------------------------------------------------------------

    /**
     * Geocode a listing's location to lat/lng coordinates.
     */
    private static function geocodeListing(MarketplaceListing $listing): void
    {
        if (!$listing->location) {
            return;
        }

        try {
            $coords = GeocodingService::geocode($listing->location);
            if ($coords) {
                $listing->latitude = $coords['latitude'];
                $listing->longitude = $coords['longitude'];
                $listing->save();
            }
        } catch (\Throwable $e) {
            // Geocoding is best-effort — don't fail the listing operation
            \Illuminate\Support\Facades\Log::warning('Marketplace geocoding failed', [
                'listing_id' => $listing->id,
                'location' => $listing->location,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
