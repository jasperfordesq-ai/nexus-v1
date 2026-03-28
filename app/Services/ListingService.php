<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Events\ListingCreated;
use App\Models\Listing;
use App\Models\User;
use App\Services\SearchService;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * ListingService — Laravel DI-based service for listing operations.
 *
 * This is the Eloquent/DI counterpart to the legacy static
 * class via constructor type-hinting; the legacy static class remains
 * available for existing code that has not yet been migrated.
 *
 * All queries are tenant-scoped automatically via the HasTenantScope
 * trait on the Listing model.
 */
class ListingService
{
    public function __construct(
        private readonly Listing $listing,
    ) {}

    // -----------------------------------------------------------------
    //  Read
    // -----------------------------------------------------------------

    /**
     * Get active listings with optional filtering and cursor pagination.
     *
     * @param array{
     *   type?: string|string[],
     *   category_id?: int,
     *   category_slug?: string,
     *   user_id?: int,
     *   search?: string,
     *   skills?: string|string[],
     *   featured_first?: bool,
     *   include_deleted?: bool,
     *   current_user_id?: int|null,
     *   limit?: int,
     *   cursor?: string|null,
     * } $filters
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public static function getAll(array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $cursor = $filters['cursor'] ?? null;
        $currentUserId = ! empty($filters['current_user_id'])
            ? (int) $filters['current_user_id']
            : null;

        // ── Meilisearch search path ──────────────────────────────────────────
        // When a search term is present and Meilisearch is available, use it for
        // typo-tolerant, relevance-ranked ID retrieval, then hydrate from SQL.
        // Falls through to the SQL path below if Meilisearch is unavailable.
        if (!empty($filters['search'])) {
            $tenantId = \App\Core\TenantContext::getId();

            // Decode Meilisearch offset cursor (format: "meili:<offset>")
            $meiliOffset = 0;
            if ($cursor !== null) {
                $decoded = base64_decode($cursor, true);
                if ($decoded !== false && str_starts_with($decoded, 'meili:')) {
                    $meiliOffset = (int) substr($decoded, 6);
                }
            }

            // Filters expressible in Meilisearch
            $meiliFilters = [];

            if (!empty($filters['category_id'])) {
                $meiliFilters[] = 'category_id = ' . (int) $filters['category_id'];
            } elseif (!empty($filters['category_slug'])) {
                // Resolve slug → id so Meilisearch can apply the category filter
                $catId = DB::table('categories')
                    ->where('slug', $filters['category_slug'])
                    ->where('type', 'listing')
                    ->where('tenant_id', $tenantId)
                    ->value('id');
                if ($catId) {
                    $meiliFilters[] = 'category_id = ' . $catId;
                }
            }

            if (!empty($filters['type']) && is_string($filters['type'])) {
                $meiliFilters[] = "type = '{$filters['type']}'";
            }

            if (!empty($filters['user_id'])) {
                $meiliFilters[] = 'user_id = ' . (int) $filters['user_id'];
            }

            if (!empty($filters['skills'])) {
                $skills = is_array($filters['skills'])
                    ? $filters['skills']
                    : explode(',', $filters['skills']);
                foreach (array_filter(array_map(fn($s) => strtolower(trim($s)), $skills)) as $skill) {
                    $meiliFilters[] = "skill_tags = '" . str_replace("'", "\\'", $skill) . "'";
                }
            }

            $meiliResult = SearchService::searchListingIds(
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

                $q = Listing::query()
                    ->with(['user:id,first_name,last_name,organization_name,profile_type,avatar_url,tagline',
                            'category:id,name,color,slug'])
                    ->whereIn('id', $ids);

                // Multi-type arrays can't be expressed in the Meilisearch filter above
                if (!empty($filters['type']) && is_array($filters['type'])) {
                    $q->whereIn('type', $filters['type']);
                }

                $listingsById = $q->get()->keyBy('id');

                $savedIds = [];
                if ($currentUserId && $listingsById->isNotEmpty()) {
                    $savedIds = DB::table('user_saved_listings')
                        ->where('user_id', $currentUserId)
                        ->whereIn('listing_id', $listingsById->keys())
                        ->pluck('listing_id')->flip()->all();
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

        $query = Listing::query()
            ->with(['user:id,first_name,last_name,organization_name,profile_type,avatar_url,tagline',
                     'category:id,name,color,slug']);

        // Status filter
        if (empty($filters['include_deleted'])) {
            $query->where(function (Builder $q) {
                $q->whereNull('status')->orWhere('status', 'active');
            });
        }

        // Type filter
        if (! empty($filters['type'])) {
            $type = $filters['type'];
            is_array($type)
                ? $query->whereIn('type', $type)
                : $query->where('type', $type);
        }

        // Category filter
        if (! empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        // Category slug filter (resolve slug → id, tenant-scoped)
        if (!empty($filters['category_slug'])) {
            $catId = DB::table('categories')
                ->where('slug', $filters['category_slug'])
                ->where('type', 'listing')
                ->where('tenant_id', \App\Core\TenantContext::getId())
                ->value('id');
            if ($catId) {
                $query->where('category_id', $catId);
            }
        }

        // User filter
        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        // Search (basic LIKE — Meilisearch integration can be layered on)
        if (! empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('title', 'LIKE', $term)
                  ->orWhere('description', 'LIKE', $term)
                  ->orWhere('location', 'LIKE', $term);
            });
        }

        // Skills filter
        if (! empty($filters['skills'])) {
            $skills = is_array($filters['skills'])
                ? $filters['skills']
                : explode(',', $filters['skills']);
            $skills = array_map(fn ($s) => strtolower(trim($s)), array_filter($skills));

            if (! empty($skills)) {
                $query->whereHas('skillTags', function (Builder $q) use ($skills) {
                    $q->whereIn('tag', $skills);
                });
            }
        }

        // Cursor pagination (ID-based, descending)
        if ($cursor !== null) {
            $cursorId = base64_decode($cursor, true);
            if ($cursorId !== false) {
                $query->where('id', '<', (int) $cursorId);
            }
        }

        // Ordering
        if (! empty($filters['featured_first'])) {
            $query->orderByDesc('is_featured');
        }
        $query->orderByDesc('id');

        // Fetch limit + 1 to determine has_more
        $items = $query->limit($limit + 1)->get();

        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items->pop();
        }

        $nextCursor = $hasMore && $items->isNotEmpty()
            ? base64_encode((string) $items->last()->id)
            : null;

        // If a current user is provided, eager-load the is_favorited flag
        $savedIds = [];
        if ($currentUserId && $items->isNotEmpty()) {
            $savedIds = DB::table('user_saved_listings')
                ->where('user_id', $currentUserId)
                ->whereIn('listing_id', $items->pluck('id'))
                ->pluck('listing_id')
                ->flip()
                ->all();
        }

        $result = $items->map(
            fn(Listing $listing) => self::formatListingItem($listing, $savedIds, $currentUserId)
        )->all();

        return [
            'items'    => array_values($result),
            'cursor'   => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Shape a Listing model into the array contract expected by the React frontend.
     */
    private static function formatListingItem(Listing $listing, array $savedIds, ?int $currentUserId): array
    {
        $data = $listing->toArray();

        $user = $listing->user;
        $data['user'] = $user ? [
            'id'         => $user->id,
            'name'       => ($user->profile_type === 'organisation' && $user->organization_name)
                                ? $user->organization_name
                                : trim($user->first_name . ' ' . $user->last_name),
            'avatar'     => $user->avatar_url,
            'avatar_url' => $user->avatar_url,
            'tagline'    => $user->tagline,
        ] : null;

        $cat = $listing->category;
        $data['category_name']  = $cat?->name;
        $data['category_color'] = $cat?->color;

        if ($currentUserId) {
            $data['is_favorited'] = isset($savedIds[$listing->id]);
        }

        return $data;
    }

    /**
     * Count total listings matching filters (without cursor/limit).
     *
     * Accepts the same filter array as getAll(), but ignores cursor and limit.
     */
    public static function countAll(array $filters = []): int
    {
        // Use Meilisearch's estimated total when a search term is present
        if (!empty($filters['search'])) {
            $tenantId     = \App\Core\TenantContext::getId();
            $meiliFilters = [];
            if (!empty($filters['category_id'])) {
                $meiliFilters[] = 'category_id = ' . (int) $filters['category_id'];
            } elseif (!empty($filters['category_slug'])) {
                $catId = DB::table('categories')
                    ->where('slug', $filters['category_slug'])
                    ->where('type', 'listing')
                    ->where('tenant_id', $tenantId)
                    ->value('id');
                if ($catId) {
                    $meiliFilters[] = 'category_id = ' . $catId;
                }
            }
            if (!empty($filters['type']) && is_string($filters['type'])) {
                $meiliFilters[] = "type = '{$filters['type']}'";
            }
            if (!empty($filters['user_id'])) {
                $meiliFilters[] = 'user_id = ' . (int) $filters['user_id'];
            }
            $meiliResult = SearchService::searchListingIds($filters['search'], $tenantId, $meiliFilters, 1, 0);
            if ($meiliResult !== null) {
                return $meiliResult['total'];
            }
        }

        $query = Listing::query();

        // Status filter
        if (empty($filters['include_deleted'])) {
            $query->where(function (Builder $q) {
                $q->whereNull('status')->orWhere('status', 'active');
            });
        }

        // Type filter
        if (! empty($filters['type'])) {
            $type = $filters['type'];
            is_array($type)
                ? $query->whereIn('type', $type)
                : $query->where('type', $type);
        }

        // Category filter
        if (! empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        // Category slug filter (resolve slug → id, tenant-scoped)
        if (!empty($filters['category_slug'])) {
            $catId = DB::table('categories')
                ->where('slug', $filters['category_slug'])
                ->where('type', 'listing')
                ->where('tenant_id', \App\Core\TenantContext::getId())
                ->value('id');
            if ($catId) {
                $query->where('category_id', $catId);
            }
        }

        // User filter
        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        // Search
        if (! empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('title', 'LIKE', $term)
                  ->orWhere('description', 'LIKE', $term)
                  ->orWhere('location', 'LIKE', $term);
            });
        }

        // Skills filter
        if (! empty($filters['skills'])) {
            $skills = is_array($filters['skills'])
                ? $filters['skills']
                : explode(',', $filters['skills']);
            $skills = array_map(fn ($s) => strtolower(trim($s)), array_filter($skills));

            if (! empty($skills)) {
                $query->whereHas('skillTags', function (Builder $q) use ($skills) {
                    $q->whereIn('tag', $skills);
                });
            }
        }

        return $query->count();
    }

    /**
     * Get a single listing by ID.
     */
    public static function getById(int $id, bool $includeDeleted = false, ?int $currentUserId = null): ?array
    {
        $query = Listing::query()
            ->with([
                'user:id,first_name,last_name,organization_name,profile_type,avatar_url,tagline',
                'category',
                'skillTags',
            ]);

        if (! $includeDeleted) {
            $query->where(function (Builder $q) {
                $q->whereNull('status')->orWhere('status', '!=', 'deleted');
            });
        }

        /** @var Listing|null $listing */
        $listing = $query->find($id);

        if (! $listing) {
            return null;
        }

        // Draft/pending/suspended listings are only visible to their owner
        $listingStatus = $listing->status ?? 'active';
        if (!in_array($listingStatus, ['active', null], true) && $listing->user_id !== $currentUserId) {
            return null;
        }

        $data = $listing->toArray();

        // Author info — replace the eager-loaded user relation with safe public fields only
        $user = $listing->user;
        if ($user) {
            $data['author_name'] = ($user->profile_type === 'organisation' && $user->organization_name)
                ? $user->organization_name
                : trim($user->first_name . ' ' . $user->last_name);
            $data['author_avatar'] = $user->avatar_url ?? null;
            $data['user'] = [
                'id'         => $user->id,
                'name'       => $data['author_name'],
                'avatar'     => $user->avatar_url,
                'avatar_url' => $user->avatar_url,
                'tagline'    => $user->tagline,
            ];
        } else {
            $data['author_name'] = 'Unknown User';
            $data['author_avatar'] = null;
            $data['user'] = null;
        }

        // Category
        $data['category_name'] = $listing->category?->name;
        $data['category_color'] = $listing->category?->color;

        // Engagement counts (wrapped in try/catch — tables may not exist during migration)
        $tenantId = \App\Core\TenantContext::getId();
        try {
            $data['likes_count'] = (int) DB::table('likes')
                ->where('tenant_id', $tenantId)
                ->where('target_type', 'listing')
                ->where('target_id', $id)
                ->count();
        } catch (\Throwable $e) {
            $data['likes_count'] = 0;
        }

        try {
            $data['comments_count'] = (int) DB::table('comments')
                ->where('tenant_id', $tenantId)
                ->where('target_type', 'listing')
                ->where('target_id', $id)
                ->count();
        } catch (\Throwable $e) {
            $data['comments_count'] = 0;
        }

        // Favorited / liked flags for the current user
        $data['is_favorited'] = false;
        $data['is_liked'] = false;

        if ($currentUserId) {
            try {
                $data['is_favorited'] = DB::table('user_saved_listings')
                    ->where('listing_id', $id)
                    ->where('user_id', $currentUserId)
                    ->exists();
            } catch (\Throwable $e) {
                // Table may not exist
            }

            try {
                $data['is_liked'] = DB::table('likes')
                    ->where('tenant_id', $tenantId)
                    ->where('target_type', 'listing')
                    ->where('target_id', $id)
                    ->where('user_id', $currentUserId)
                    ->exists();
            } catch (\Throwable $e) {
                // Table may not exist
            }
        }

        return $data;
    }

    // -----------------------------------------------------------------
    //  Nearby (Haversine geospatial query)
    // -----------------------------------------------------------------

    /**
     * Get listings near a geographic point using the Haversine formula.
     *
     * @param float $lat Latitude of search centre
     * @param float $lon Longitude of search centre
     * @param array{radius_km?: float, limit?: int, type?: string|string[], category_id?: int} $filters
     * @return array{items: array, has_more: bool}
     */
    public static function getNearby(float $lat, float $lon, array $filters = []): array
    {
        $radiusKm = (float) ($filters['radius_km'] ?? 25);
        $limit = min((int) ($filters['limit'] ?? 20), 100);

        // Decode offset cursor (format: "nearby:<offset>")
        $offset = 0;
        $cursor = $filters['cursor'] ?? null;
        if ($cursor !== null) {
            $decoded = base64_decode($cursor, true);
            if ($decoded !== false && str_starts_with($decoded, 'nearby:')) {
                $offset = (int) substr($decoded, 7);
            }
        }

        $haversine = '(6371 * acos(LEAST(1.0, GREATEST(-1.0, '
            . 'cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + '
            . 'sin(radians(?)) * sin(radians(latitude))'
            . '))))';

        $query = Listing::query()
            ->with([
                'user:id,first_name,last_name,organization_name,profile_type,avatar_url',
                'category:id,name,color',
            ])
            ->selectRaw("listings.*, {$haversine} AS distance_km", [$lat, $lon, $lat])
            ->where('status', 'active')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->having('distance_km', '<=', $radiusKm)
            ->orderBy('distance_km');

        // Type filter
        if (! empty($filters['type'])) {
            $type = $filters['type'];
            is_array($type)
                ? $query->whereIn('type', $type)
                : $query->where('type', $type);
        }

        // Category filter
        if (! empty($filters['category_id'])) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        $items = $query->offset($offset)->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items->pop();
        }

        $result = $items->map(function (Listing $listing) {
            $data = $listing->toArray();
            $user = $listing->user;
            $data['author_name'] = ($user && $user->profile_type === 'organisation' && $user->organization_name)
                ? $user->organization_name
                : trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            $data['author_avatar'] = $user->avatar_url ?? null;
            $data['category_name'] = $listing->category?->name;
            $data['category_color'] = $listing->category?->color;
            $data['distance_km'] = round((float) $listing->distance_km, 2);
            return $data;
        })->all();

        $nextCursor = $hasMore ? base64_encode('nearby:' . ($offset + $limit)) : null;

        return [
            'items'    => array_values($result),
            'cursor'   => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    // -----------------------------------------------------------------
    //  Featured listings
    // -----------------------------------------------------------------

    /**
     * Get currently featured listings.
     *
     * @param int $limit Max listings to return
     * @return array Featured listings
     */
    public static function getFeatured(int $limit = 10): array
    {
        return Listing::query()
            ->with([
                'user:id,first_name,last_name,organization_name,profile_type,avatar_url',
                'category:id,name,color',
            ])
            ->where('status', 'active')
            ->where('is_featured', true)
            ->where(function (Builder $q) {
                $q->whereNull('featured_until')
                   ->orWhere('featured_until', '>', now());
            })
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function (Listing $listing) {
                $data = $listing->toArray();
                $user = $listing->user;
                $data['author_name'] = ($user && $user->profile_type === 'organisation' && $user->organization_name)
                    ? $user->organization_name
                    : trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
                $data['author_avatar'] = $user->avatar_url ?? null;
                $data['category_name'] = $listing->category?->name;
                $data['category_color'] = $listing->category?->color;
                return $data;
            })
            ->all();
    }

    // -----------------------------------------------------------------
    //  Saved / Favourite Listings
    // -----------------------------------------------------------------

    /**
     * Get listing IDs saved by the user in the current tenant.
     *
     * @return int[]
     */
    public static function getSavedListingIds(int $userId): array
    {
        return DB::table('user_saved_listings')
            ->where('user_id', $userId)
            ->where('tenant_id', \App\Core\TenantContext::getId())
            ->pluck('listing_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Save (favourite) a listing. Idempotent via INSERT IGNORE.
     *
     * @return bool False if listing not found in tenant
     */
    public static function saveListing(int $userId, int $listingId): bool
    {
        // Verify listing exists in tenant
        $exists = Listing::query()->where('id', $listingId)->exists();

        if (! $exists) {
            return false;
        }

        DB::table('user_saved_listings')->insertOrIgnore([
            'user_id'    => $userId,
            'listing_id' => $listingId,
            'tenant_id'  => \App\Core\TenantContext::getId(),
        ]);

        return true;
    }

    /**
     * Unsave (un-favourite) a listing. Always succeeds (idempotent).
     */
    public static function unsaveListing(int $userId, int $listingId): void
    {
        DB::table('user_saved_listings')
            ->where('user_id', $userId)
            ->where('listing_id', $listingId)
            ->delete();
    }

    // -----------------------------------------------------------------
    //  Write
    // -----------------------------------------------------------------

    /**
     * Create a new listing.
     *
     * @param int   $userId Owner of the listing.
     * @param array $data   Listing attributes (title, description, type, etc.)
     * @return Listing
     *
     * @throws ValidationException
     */
    public static function create(int $userId, array $data): Listing
    {
        self::validateData($data);

        // Fall back to user's location when not provided
        if (empty($data['location']) || empty($data['latitude']) || empty($data['longitude'])) {
            $user = User::find($userId);
            if ($user) {
                $data['location']  = $data['location']  ?? $user->location;
                $data['latitude']  = $data['latitude']  ?? $user->latitude;
                $data['longitude'] = $data['longitude'] ?? $user->longitude;
            }
        }

        $listing = DB::transaction(function () use ($userId, $data) {
            $listing = new Listing([
                'user_id'               => $userId,
                'title'                 => trim($data['title']),
                'description'           => trim($data['description']),
                'type'                  => $data['type'] ?? 'offer',
                'category_id'           => $data['category_id'] ?? null,
                'image_url'             => $data['image_url'] ?? null,
                'location'              => $data['location'] ?? null,
                'latitude'              => $data['latitude'] ?? null,
                'longitude'             => $data['longitude'] ?? null,
                'hours_estimate'        => isset($data['hours_estimate']) ? (float) $data['hours_estimate'] : null,
                'federated_visibility'  => $data['federated_visibility'] ?? 'none',
                'status'                => 'active',
            ]);

            // tenant_id is set automatically by HasTenantScope
            $listing->save();

            // SDG goals
            if (! empty($data['sdg_goals'])) {
                $listing->sdg_goals = is_array($data['sdg_goals'])
                    ? $data['sdg_goals']
                    : json_decode($data['sdg_goals'], true);
                $listing->save();
            }

            return $listing->fresh(['user', 'category', 'skillTags']);
        });

        // Dispatch event after DB transaction commits — triggers feed activity,
        // WebSocket broadcast, and Meilisearch indexing.
        $user = User::find($userId);
        if ($user) {
            event(new ListingCreated($listing, $user, \App\Core\TenantContext::getId()));
        }

        return $listing;
    }

    /**
     * Update an existing listing.
     *
     * @param int   $id   Listing ID.
     * @param array $data Fields to update.
     * @return Listing
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     * @throws ValidationException
     */
    public static function update(int $id, array $data): Listing
    {
        self::validateData($data, isUpdate: true);

        /** @var Listing $listing */
        $listing = Listing::query()->findOrFail($id);

        $allowed = [
            'title', 'description', 'type', 'category_id', 'image_url',
            'location', 'latitude', 'longitude', 'hours_estimate',
            'federated_visibility', 'status', 'sdg_goals',
        ];

        $listing->fill(collect($data)->only($allowed)->all());
        $listing->save();

        return $listing->fresh(['user', 'category', 'skillTags']);
    }

    /**
     * Soft-delete a listing by setting status = 'deleted'.
     */
    public static function delete(int $id): bool
    {
        /** @var Listing|null $listing */
        $listing = Listing::query()->find($id);

        if (! $listing) {
            return false;
        }

        $listing->status = 'deleted';
        $listing->save();

        return true;
    }

    // -----------------------------------------------------------------
    //  Search (simple — wraps getAll for now)
    // -----------------------------------------------------------------

    /**
     * Search listings by term with optional type filter.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public static function search(string $term, ?string $type = null, int $limit = 20): array
    {
        $filters = ['search' => $term, 'limit' => $limit];

        if ($type !== null) {
            $filters['type'] = $type;
        }

        return self::getAll($filters);
    }

    // -----------------------------------------------------------------
    //  Permissions
    // -----------------------------------------------------------------

    /**
     * Check if a user can modify a listing (owner or admin).
     */
    public static function canModify(array $listing, int $userId): bool
    {
        // Owner can always modify
        if ((int) ($listing['user_id'] ?? 0) === $userId) {
            return true;
        }

        // Check if user is admin
        $user = User::find($userId);

        if ($user) {
            $role = $user->role ?? '';
            if (in_array($role, ['admin', 'tenant_admin']) || $user->is_super_admin || $user->is_tenant_super_admin) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validation errors from the last operation.
     *
     * @var array
     */
    private static array $errors = [];

    /**
     * Get validation errors from the last operation.
     */
    public static function getErrors(): array
    {
        return self::$errors;
    }

    /**
     * Validate listing data and return boolean (for test compatibility).
     *
     * @return bool True if valid, false if errors (check getErrors()).
     */
    public static function validate(array $data): bool
    {
        self::$errors = [];

        $title = $data['title'] ?? null;
        $type = $data['type'] ?? null;

        // title is required and max 255
        if ($title === null || $title === '') {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Title is required', 'field' => 'title'];
        } elseif (strlen($title) > 255) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Title must not exceed 255 characters', 'field' => 'title'];
        }

        // type must be offer or request
        if ($type !== null && !in_array($type, ['offer', 'request'], true)) {
            self::$errors[] = ['code' => 'VALIDATION_ERROR', 'message' => 'Type must be offer or request', 'field' => 'type'];
        }

        return empty(self::$errors);
    }

    // -----------------------------------------------------------------
    //  Validation
    // -----------------------------------------------------------------

    /**
     * Validate listing input data.
     *
     * @throws ValidationException
     */
    private static function validateData(array $data, bool $isUpdate = false): void
    {
        $rules = [];

        if (! $isUpdate) {
            $rules['title']       = 'required|string|max:255';
            $rules['description'] = 'required|string';
            $rules['type']        = 'required|in:offer,request';
        } else {
            $rules['title']       = 'sometimes|string|max:255';
            $rules['description'] = 'sometimes|string';
            $rules['type']        = 'sometimes|in:offer,request';
        }

        $rules['category_id']          = 'sometimes|nullable|integer|exists:categories,id';
        $rules['federated_visibility'] = 'sometimes|in:none,listed,bookable';
        $rules['hours_estimate']       = 'sometimes|nullable|numeric|min:0';

        $validator = validator($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
