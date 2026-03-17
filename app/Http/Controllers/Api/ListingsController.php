<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\ListingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * ListingsController — Laravel reference implementation for listings CRUD.
 *
 * This is the first fully-converted Laravel API controller, intended as a
 * reference for migrating the remaining legacy controllers. It demonstrates:
 *
 *   - Constructor dependency injection (ListingService)
 *   - Returning JsonResponse from every action (no echo+exit)
 *   - Using BaseApiController helpers: respondWithData, respondWithCollection,
 *     respondWithError, noContent, input(), getUserId(), requireAuth(), etc.
 *   - Rate limiting via $this->rateLimit()
 *   - Proper HTTP status codes (200, 201, 204, 403, 404, 422)
 *
 * Endpoints (v2):
 *   GET    /api/v2/listings          → index()
 *   GET    /api/v2/listings/{id}     → show()
 *   POST   /api/v2/listings          → store()
 *   PUT    /api/v2/listings/{id}     → update()
 *   DELETE /api/v2/listings/{id}     → destroy()
 */
class ListingsController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly ListingService $listingService,
    ) {}

    // -----------------------------------------------------------------
    //  GET /api/v2/listings
    // -----------------------------------------------------------------

    /**
     * List active listings with optional filtering and cursor-based pagination.
     *
     * Query params: type, category_id, q, skills, featured_first, user_id,
     *               cursor, per_page (default 20, max 100).
     */
    public function index(): JsonResponse
    {
        $userId = $this->getOptionalUserId();

        $filters = [];

        // Type filter (supports comma-separated)
        $type = $this->query('type');
        if ($type) {
            $filters['type'] = str_contains($type, ',') ? explode(',', $type) : $type;
        }

        if ($this->query('category_id')) {
            $filters['category_id'] = $this->queryInt('category_id');
        }

        if ($this->query('q')) {
            $filters['search'] = $this->query('q');
        }

        if ($this->query('user_id')) {
            $filters['user_id'] = $this->queryInt('user_id');
        }

        if ($this->query('skills')) {
            $filters['skills'] = $this->query('skills');
        }

        if ($this->queryBool('featured_first')) {
            $filters['featured_first'] = true;
        }

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $filters['limit'] = $this->queryInt('per_page', 20, 1, 100);

        if ($userId !== null) {
            $filters['current_user_id'] = $userId;
        }

        $result = $this->listingService->getAll($filters);

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    // -----------------------------------------------------------------
    //  GET /api/v2/listings/{id}
    // -----------------------------------------------------------------

    /**
     * Get a single listing by ID.
     */
    public function show(int $id): JsonResponse
    {
        $userId = $this->getOptionalUserId();
        $listing = $this->listingService->getById($id, false, $userId);

        if ($listing === null) {
            return $this->respondWithError('NOT_FOUND', 'Listing not found', null, 404);
        }

        return $this->respondWithData($listing);
    }

    // -----------------------------------------------------------------
    //  POST /api/v2/listings
    // -----------------------------------------------------------------

    /**
     * Create a new listing.
     *
     * Requires authentication. Body: title (required), description (required),
     * type (offer|request), category_id, image_url, location, latitude,
     * longitude, federated_visibility, sdg_goals.
     */
    public function store(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('listing_create', 10, 60);

        $data = $this->getAllInput();

        try {
            $listing = $this->listingService->create($userId, $data);
        } catch (ValidationException $e) {
            $errors = collect($e->errors())->flatMap(function (array $messages, string $field) {
                return array_map(fn (string $msg) => [
                    'code'    => 'VALIDATION_ERROR',
                    'message' => $msg,
                    'field'   => $field,
                ], $messages);
            })->values()->all();

            return $this->respondWithErrors($errors, 422);
        }

        // Return the freshly-created listing via getById for full formatting
        $result = $this->listingService->getById($listing->id, false, $userId);

        return $this->respondWithData($result ?? $listing->toArray(), null, 201);
    }

    // -----------------------------------------------------------------
    //  PUT /api/v2/listings/{id}
    // -----------------------------------------------------------------

    /**
     * Update an existing listing. Only the listing owner may update.
     */
    public function update(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('listing_update', 20, 60);

        // Verify the listing exists and the user owns it
        $existing = $this->listingService->getById($id);

        if ($existing === null) {
            return $this->respondWithError('NOT_FOUND', 'Listing not found', null, 404);
        }

        if ((int) ($existing['user_id'] ?? 0) !== $userId) {
            return $this->respondWithError('FORBIDDEN', 'You do not own this listing', null, 403);
        }

        $data = $this->getAllInput();

        try {
            $listing = $this->listingService->update($id, $data);
        } catch (ValidationException $e) {
            $errors = collect($e->errors())->flatMap(function (array $messages, string $field) {
                return array_map(fn (string $msg) => [
                    'code'    => 'VALIDATION_ERROR',
                    'message' => $msg,
                    'field'   => $field,
                ], $messages);
            })->values()->all();

            return $this->respondWithErrors($errors, 422);
        }

        $result = $this->listingService->getById($listing->id, false, $userId);

        return $this->respondWithData($result ?? $listing->toArray());
    }

    // -----------------------------------------------------------------
    //  DELETE /api/v2/listings/{id}
    // -----------------------------------------------------------------

    /**
     * Soft-delete a listing. Only the listing owner may delete.
     */
    public function destroy(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('listing_delete', 10, 60);

        // Verify ownership
        $existing = $this->listingService->getById($id);

        if ($existing === null) {
            return $this->respondWithError('NOT_FOUND', 'Listing not found', null, 404);
        }

        if ((int) ($existing['user_id'] ?? 0) !== $userId) {
            return $this->respondWithError('FORBIDDEN', 'You do not own this listing', null, 403);
        }

        $this->listingService->delete($id);

        return $this->noContent();
    }

    // -----------------------------------------------------------------
    //  GET /api/v2/listings/nearby
    // -----------------------------------------------------------------

    /**
     * Get listings near a geographic point using Haversine formula.
     *
     * Query params: lat (required), lon (required), radius_km (default 25),
     *               type, category_id, per_page (default 20, max 100).
     */
    public function nearby(): JsonResponse
    {
        $lat = $this->query('lat');
        $lon = $this->query('lon');

        if ($lat === null || $lon === null) {
            return $this->respondWithError('VALIDATION_ERROR', 'Latitude and longitude are required', null, 400);
        }

        $lat = (float) $lat;
        $lon = (float) $lon;

        if ($lat < -90 || $lat > 90) {
            return $this->respondWithError('VALIDATION_ERROR', 'Latitude must be between -90 and 90', 'lat', 400);
        }
        if ($lon < -180 || $lon > 180) {
            return $this->respondWithError('VALIDATION_ERROR', 'Longitude must be between -180 and 180', 'lon', 400);
        }

        $filters = [
            'radius_km' => (float) $this->query('radius_km', '25'),
            'limit'     => $this->queryInt('per_page', 20, 1, 100),
        ];

        $type = $this->query('type');
        if ($type) {
            $filters['type'] = str_contains($type, ',') ? explode(',', $type) : $type;
        }

        if ($this->query('category_id')) {
            $filters['category_id'] = $this->queryInt('category_id');
        }

        $result = $this->listingService->getNearby($lat, $lon, $filters);

        return $this->respondWithData($result['items'], [
            'search' => [
                'type'      => 'nearby',
                'lat'       => $lat,
                'lon'       => $lon,
                'radius_km' => $filters['radius_km'],
            ],
            'per_page' => $filters['limit'],
            'has_more' => $result['has_more'],
        ]);
    }

    // -----------------------------------------------------------------
    //  GET /api/v2/listings/saved
    // -----------------------------------------------------------------

    /**
     * Get listing IDs saved by the authenticated user.
     */
    public function getSavedListings(): JsonResponse
    {
        $userId = $this->requireAuth();

        $savedIds = $this->listingService->getSavedListingIds($userId);

        return $this->respondWithData($savedIds);
    }

    // -----------------------------------------------------------------
    //  GET /api/v2/listings/featured
    // -----------------------------------------------------------------

    /**
     * Get currently featured listings.
     *
     * Query params: per_page (default 10, max 50).
     */
    public function featured(): JsonResponse
    {
        $limit = $this->queryInt('per_page', 10, 1, 50);

        $items = $this->listingService->getFeatured($limit);

        return $this->respondWithData($items);
    }

    // -----------------------------------------------------------------
    //  POST /api/v2/listings/{id}/save
    // -----------------------------------------------------------------

    /**
     * Save (favourite) a listing for the authenticated user.
     */
    public function saveListing(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $result = $this->listingService->saveListing($userId, $id);

        if (! $result) {
            return $this->respondWithError('NOT_FOUND', 'Listing not found', null, 404);
        }

        return $this->respondWithData(['saved' => true, 'listing_id' => $id]);
    }

    // -----------------------------------------------------------------
    //  DELETE /api/v2/listings/{id}/save
    // -----------------------------------------------------------------

    /**
     * Unsave (un-favourite) a listing for the authenticated user.
     */
    public function unsaveListing(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $this->listingService->unsaveListing($userId, $id);

        return $this->respondWithData(['saved' => false, 'listing_id' => $id]);
    }

    // -----------------------------------------------------------------
    //  Delegated endpoints (complex, kept on legacy for now)
    // -----------------------------------------------------------------

    /**
     * Delegate to legacy controller via output buffering.
     */
    private function delegate(string $legacyClass, string $method, array $params = []): JsonResponse
    {
        $controller = new $legacyClass();
        ob_start();
        $controller->$method(...$params);
        $output = ob_get_clean();
        $status = http_response_code();
        return response()->json(json_decode($output, true) ?: $output, $status ?: 200);
    }

    public function popularTags(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\ListingsApiController::class, 'popularTags');
    }

    public function autocompleteTags(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\ListingsApiController::class, 'autocompleteTags');
    }

    public function uploadImage($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\ListingsApiController::class, 'uploadImage', [$id]);
    }

    public function deleteImage($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\ListingsApiController::class, 'deleteImage', [$id]);
    }

    public function renew($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\ListingsApiController::class, 'renew', [$id]);
    }

    public function analytics($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\ListingsApiController::class, 'analytics', [$id]);
    }

    public function setSkillTags($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\ListingsApiController::class, 'setSkillTags', [$id]);
    }
}
