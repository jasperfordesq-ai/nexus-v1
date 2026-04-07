<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\BookmarkService;
use Illuminate\Http\JsonResponse;

/**
 * BookmarkController — Bookmarks / Save Collections.
 *
 * Endpoints (v2):
 *   POST   /api/v2/bookmarks            toggle()
 *   GET    /api/v2/bookmarks             index()
 *   GET    /api/v2/bookmarks/status      status()
 *   GET    /api/v2/bookmark-collections  collections()
 *   POST   /api/v2/bookmark-collections  createCollection()
 *   PATCH  /api/v2/bookmark-collections/{id}  updateCollection()
 *   DELETE /api/v2/bookmark-collections/{id}  deleteCollection()
 *   POST   /api/v2/bookmarks/{id}/move   move()
 */
class BookmarkController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly BookmarkService $bookmarkService,
    ) {}

    /**
     * POST /api/v2/bookmarks — Toggle bookmark on/off.
     * Body: { type: string, id: number, collection_id?: number }
     */
    public function toggle(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('bookmark_toggle', 60, 60);

        $type = $this->input('type');
        $id = (int) $this->input('id', 0);
        $collectionId = $this->inputInt('collection_id');

        if (!$type || $id <= 0) {
            return $this->respondWithError('INVALID_INPUT', 'Type and id are required.');
        }

        try {
            $result = $this->bookmarkService->toggle($userId, $type, $id, $collectionId);
            return $this->respondWithData($result);
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('INVALID_TYPE', $e->getMessage());
        }
    }

    /**
     * GET /api/v2/bookmarks — List user bookmarks.
     * Query: ?type=&collection_id=&page=&per_page=
     */
    public function index(): JsonResponse
    {
        $userId = $this->requireAuth();

        $type = $this->query('type');
        $collectionId = $this->queryInt('collection_id');
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);

        try {
            $paginator = $this->bookmarkService->getUserBookmarks($userId, $type, $collectionId, $page, $perPage);

            return $this->respondWithPaginatedCollection(
                $paginator->items(),
                $paginator->total(),
                $paginator->currentPage(),
                $paginator->perPage()
            );
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('INVALID_TYPE', $e->getMessage());
        }
    }

    /**
     * GET /api/v2/bookmarks/status — Check if an item is bookmarked.
     * Query: ?type=&id=
     */
    public function status(): JsonResponse
    {
        $userId = $this->requireAuth();

        $type = $this->query('type');
        $id = (int) $this->query('id', 0);

        if (!$type || $id <= 0) {
            return $this->respondWithError('INVALID_INPUT', 'Type and id are required.');
        }

        try {
            $bookmarked = $this->bookmarkService->isBookmarked($userId, $type, $id);
            $count = $this->bookmarkService->getBookmarkCount($type, $id);

            return $this->respondWithData([
                'bookmarked' => $bookmarked,
                'count' => $count,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('INVALID_TYPE', $e->getMessage());
        }
    }

    /**
     * GET /api/v2/bookmark-collections — List user collections.
     */
    public function collections(): JsonResponse
    {
        $userId = $this->requireAuth();

        $collections = $this->bookmarkService->getCollections($userId);

        return $this->respondWithData($collections->toArray());
    }

    /**
     * POST /api/v2/bookmark-collections — Create a collection.
     * Body: { name: string, description?: string }
     */
    public function createCollection(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('bookmark_collection', 20, 60);

        $name = trim((string) $this->input('name', ''));
        $description = $this->input('description');

        if ($name === '') {
            return $this->respondWithError('VALIDATION_ERROR', 'Collection name is required.', 'name');
        }

        if (mb_strlen($name) > 100) {
            return $this->respondWithError('VALIDATION_ERROR', 'Collection name must be 100 characters or less.', 'name');
        }

        try {
            $collection = $this->bookmarkService->createCollection($userId, $name, $description);
            return $this->respondWithData($collection->toArray(), null, 201);
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry') || str_contains($e->getMessage(), 'UNIQUE')) {
                return $this->respondWithError('DUPLICATE', 'A collection with that name already exists.', 'name', 409);
            }
            throw $e;
        }
    }

    /**
     * PATCH /api/v2/bookmark-collections/{id} — Update a collection.
     */
    public function updateCollection(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $data = $this->getAllInput();

        if (isset($data['name']) && mb_strlen(trim($data['name'])) === 0) {
            return $this->respondWithError('VALIDATION_ERROR', 'Collection name cannot be empty.', 'name');
        }

        try {
            $collection = $this->bookmarkService->updateCollection($id, $userId, $data);
            return $this->respondWithData($collection->toArray());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->respondWithError('NOT_FOUND', 'Collection not found.', null, 404);
        }
    }

    /**
     * DELETE /api/v2/bookmark-collections/{id} — Delete a collection.
     */
    public function deleteCollection(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        try {
            $this->bookmarkService->deleteCollection($id, $userId);
            return $this->respondWithData(['success' => true]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->respondWithError('NOT_FOUND', 'Collection not found.', null, 404);
        }
    }

    /**
     * POST /api/v2/bookmarks/{id}/move — Move bookmark to a collection.
     * Body: { collection_id: number|null }
     */
    public function move(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $collectionId = $this->inputInt('collection_id');

        try {
            $this->bookmarkService->moveToCollection($id, $userId, $collectionId);
            return $this->respondWithData(['success' => true]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->respondWithError('NOT_FOUND', 'Bookmark or collection not found.', null, 404);
        }
    }
}
