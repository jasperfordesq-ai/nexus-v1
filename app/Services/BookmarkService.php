<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\Bookmark;
use App\Models\BookmarkCollection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BookmarkService
{
    private const VALID_TYPES = ['post', 'listing', 'event', 'job', 'blog', 'discussion'];

    /**
     * Toggle a bookmark: add if not bookmarked, remove if already bookmarked.
     *
     * @return array{bookmarked: bool, count: int}
     */
    public function toggle(int $userId, string $type, int $id, ?int $collectionId = null): array
    {
        $this->validateType($type);
        $tenantId = TenantContext::getId();

        $existing = Bookmark::where('user_id', $userId)
            ->where('bookmarkable_type', $type)
            ->where('bookmarkable_id', $id)
            ->first();

        if ($existing) {
            $existing->delete();
            $count = $this->getBookmarkCount($type, $id);
            return ['bookmarked' => false, 'count' => $count];
        }

        // Validate collection belongs to user if provided
        if ($collectionId !== null) {
            $collection = BookmarkCollection::where('id', $collectionId)
                ->where('user_id', $userId)
                ->first();
            if (!$collection) {
                $collectionId = null;
            }
        }

        Bookmark::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'bookmarkable_type' => $type,
            'bookmarkable_id' => $id,
            'collection_id' => $collectionId,
            'created_at' => now(),
        ]);

        $count = $this->getBookmarkCount($type, $id);
        return ['bookmarked' => true, 'count' => $count];
    }

    /**
     * Get paginated bookmarks for a user, optionally filtered by type and/or collection.
     */
    public function getUserBookmarks(int $userId, ?string $type = null, ?int $collectionId = null, int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        $query = Bookmark::where('user_id', $userId)
            ->orderBy('created_at', 'desc');

        if ($type !== null) {
            $this->validateType($type);
            $query->where('bookmarkable_type', $type);
        }

        if ($collectionId !== null) {
            $query->where('collection_id', $collectionId);
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Get all collections for a user, with bookmark counts.
     */
    public function getCollections(int $userId): Collection
    {
        return BookmarkCollection::where('user_id', $userId)
            ->withCount('bookmarks')
            ->orderBy('is_default', 'desc')
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * Create a new bookmark collection.
     */
    public function createCollection(int $userId, string $name, ?string $description = null): BookmarkCollection
    {
        return BookmarkCollection::create([
            'tenant_id' => TenantContext::getId(),
            'user_id' => $userId,
            'name' => $name,
            'description' => $description,
            'is_default' => false,
        ]);
    }

    /**
     * Update a bookmark collection.
     */
    public function updateCollection(int $collectionId, int $userId, array $data): BookmarkCollection
    {
        $collection = BookmarkCollection::where('id', $collectionId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $collection->update(array_filter([
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
        ], fn($v) => $v !== null));

        return $collection->fresh();
    }

    /**
     * Delete a bookmark collection. Bookmarks in it get their collection_id set to null.
     */
    public function deleteCollection(int $collectionId, int $userId): void
    {
        $collection = BookmarkCollection::where('id', $collectionId)
            ->where('user_id', $userId)
            ->firstOrFail();

        // Unlink bookmarks from this collection (FK has ON DELETE SET NULL, but be explicit)
        Bookmark::where('collection_id', $collectionId)
            ->where('user_id', $userId)
            ->update(['collection_id' => null]);

        $collection->delete();
    }

    /**
     * Move a bookmark to a different collection (or remove from collection with null).
     */
    public function moveToCollection(int $bookmarkId, int $userId, ?int $collectionId): void
    {
        $bookmark = Bookmark::where('id', $bookmarkId)
            ->where('user_id', $userId)
            ->firstOrFail();

        // Validate collection ownership if provided
        if ($collectionId !== null) {
            BookmarkCollection::where('id', $collectionId)
                ->where('user_id', $userId)
                ->firstOrFail();
        }

        $bookmark->update(['collection_id' => $collectionId]);
    }

    /**
     * Check if a user has bookmarked a specific item.
     */
    public function isBookmarked(int $userId, string $type, int $id): bool
    {
        return Bookmark::where('user_id', $userId)
            ->where('bookmarkable_type', $type)
            ->where('bookmarkable_id', $id)
            ->exists();
    }

    /**
     * Get the total bookmark count for an item across all users.
     */
    public function getBookmarkCount(string $type, int $id): int
    {
        return Bookmark::where('bookmarkable_type', $type)
            ->where('bookmarkable_id', $id)
            ->count();
    }

    private function validateType(string $type): void
    {
        if (!in_array($type, self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException(__('api.invalid_bookmarkable_type'));
        }
    }
}
