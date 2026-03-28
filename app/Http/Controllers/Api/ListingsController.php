<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\ListingService;
use App\Services\ListingAnalyticsService;
use App\Services\ListingExpiryService;
use App\Services\ListingRankingService;
use App\Services\ListingSkillTagService;
use App\Services\ListingFeaturedService;

/**
 * ListingsController - Listings CRUD, search, favorites, tags, analytics, renewal.
 *
 * Converted from delegation to direct static service calls.
 * All methods are now native Laravel — no legacy delegation remains.
 */
class ListingsController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly ListingAnalyticsService $listingAnalyticsService,
        private readonly ListingExpiryService $listingExpiryService,
        private readonly ListingFeaturedService $listingFeaturedService,
        private readonly ListingRankingService $listingRankingService,
        private readonly ListingService $listingService,
        private readonly ListingSkillTagService $listingSkillTagService,
    ) {}

    // -----------------------------------------------------------------
    //  GET /api/v2/listings
    // -----------------------------------------------------------------

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
        } elseif ($this->query('category')) {
            $filters['category_slug'] = $this->query('category');
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

        // Count total matching listings (without cursor/limit)
        $totalCount = $this->listingService->countAll($filters);

        // Apply MatchRank if enabled; skip when featured_first is set
        if ($this->listingRankingService->isEnabled() && !empty($result['items']) && empty($filters['featured_first'])) {
            $result['items'] = $this->listingRankingService->rankListings(
                $result['items'],
                $userId,
                ['search' => $filters['search'] ?? null]
            );
            $result['items'] = array_map(static function (array $item): array {
                unset($item['_match_rank'], $item['_score_breakdown']);
                return $item;
            }, $result['items']);
        }

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more'],
            ['total_items' => $totalCount]
        );
    }

    // -----------------------------------------------------------------
    //  GET /api/v2/listings/{id}
    // -----------------------------------------------------------------

    public function show(int $id): JsonResponse
    {
        $userId = $this->getOptionalUserId();
        $listing = $this->listingService->getById($id, false, $userId);

        if (!$listing) {
            return $this->respondWithError('NOT_FOUND', 'Listing not found', null, 404);
        }

        // Track view (skip if viewer is the listing owner)
        if ($userId === null || $userId !== (int) ($listing['user_id'] ?? 0)) {
            try {
                $ip = \App\Core\ClientIp::get();
                $this->listingAnalyticsService->recordView($id, $userId, $ip);
            } catch (\Throwable $e) {
                // Non-critical
            }
        }

        // Attach skill tags
        try {
            $listing['skill_tags'] = $this->listingSkillTagService->getTags($id);
        } catch (\Throwable $e) {
            $listing['skill_tags'] = [];
        }

        $listing['is_featured'] = (bool) ($listing['is_featured'] ?? false);

        return $this->respondWithData($listing);
    }

    // -----------------------------------------------------------------
    //  POST /api/v2/listings
    // -----------------------------------------------------------------

    public function store(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('listing_create', 10, 60);

        $data = $this->getAllInput();
        // image_url must go through the dedicated uploadImage endpoint — strip from user input
        unset($data['image_url']);

        try {
            $listing = $this->listingService->create($userId, $data);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = [];
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $errors[] = ['code' => 'VALIDATION_ERROR', 'message' => $message, 'field' => $field];
                }
            }
            return $this->respondWithErrors($errors, 422);
        }

        $result = $this->listingService->getById($listing->id);

        // Award XP for creating a listing
        try {
            \App\Services\GamificationService::awardXP($userId, \App\Services\GamificationService::XP_VALUES['create_listing'], 'create_listing', 'Created a listing');
            \App\Services\GamificationService::runAllBadgeChecks($userId);
        } catch (\Throwable $e) {
            \Log::warning('Gamification XP award failed', ['action' => 'create_listing', 'user' => $userId, 'error' => $e->getMessage()]);
        }

        // Record feed activity
        try {
            app(\App\Services\FeedActivityService::class)->recordActivity(
                \App\Core\TenantContext::getId(),
                $userId,
                'listing',
                $listing->id,
                [
                    'title'     => $data['title'] ?? null,
                    'content'   => $data['description'] ?? null,
                    'image_url' => $result['image_url'] ?? null,
                ]
            );
        } catch (\Throwable $e) {
            \Log::warning('Feed activity recording failed', ['type' => 'listing', 'id' => $listing->id, 'error' => $e->getMessage()]);
        }

        return $this->respondWithData($result, null, 201);
    }

    // -----------------------------------------------------------------
    //  PUT /api/v2/listings/{id}
    // -----------------------------------------------------------------

    public function update(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('listing_update', 20, 60);

        // Verify ownership or admin
        $existing = $this->listingService->getById($id, false, $userId);
        if (!$existing) {
            return $this->respondWithError('NOT_FOUND', 'Listing not found', null, 404);
        }
        if (!$this->listingService->canModify($existing, $userId)) {
            return $this->respondWithError('FORBIDDEN', 'You can only edit your own listings', null, 403);
        }

        $data = $this->getAllInput();
        // image_url must go through the dedicated uploadImage endpoint — strip from user input
        unset($data['image_url']);

        try {
            $this->listingService->update($id, $data);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->respondWithError('NOT_FOUND', 'Listing not found', null, 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = [];
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $errors[] = ['code' => 'VALIDATION_ERROR', 'message' => $message, 'field' => $field];
                }
            }
            return $this->respondWithErrors($errors, 422);
        }

        $listing = $this->listingService->getById($id);

        return $this->respondWithData($listing);
    }

    // -----------------------------------------------------------------
    //  DELETE /api/v2/listings/{id}
    // -----------------------------------------------------------------

    public function destroy(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('listing_delete', 10, 60);

        // Verify ownership or admin
        $existing = $this->listingService->getById($id, false, $userId);
        if (!$existing) {
            return $this->respondWithError('NOT_FOUND', 'Listing not found', null, 404);
        }
        if (!$this->listingService->canModify($existing, $userId)) {
            return $this->respondWithError('FORBIDDEN', 'You can only delete your own listings', null, 403);
        }

        $success = $this->listingService->delete($id);

        if (!$success) {
            return $this->respondWithError('DELETE_FAILED', 'Failed to delete listing', null, 400);
        }

        // Clean up feed entry for deleted listing
        try {
            app(\App\Services\FeedActivityService::class)->removeActivity('listing', $id);
        } catch (\Throwable $e) {
            \Log::warning('Feed cleanup failed for deleted listing', ['listing_id' => $id, 'error' => $e->getMessage()]);
        }

        return $this->noContent();
    }

    // -----------------------------------------------------------------
    //  GET /api/v2/listings/nearby
    // -----------------------------------------------------------------

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

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = $this->listingService->getNearby($lat, $lon, $filters);

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'] ?? null,
            $filters['limit'],
            $result['has_more'] ?? false,
            [
                'search' => [
                    'type'      => 'nearby',
                    'lat'       => $lat,
                    'lon'       => $lon,
                    'radius_km' => $filters['radius_km'],
                ],
            ]
        );
    }

    // -----------------------------------------------------------------
    //  GET /api/v2/listings/saved
    // -----------------------------------------------------------------

    public function getSavedListings(): JsonResponse
    {
        $userId = $this->requireAuth();

        $savedIds = $this->listingService->getSavedListingIds($userId);

        return $this->respondWithData($savedIds);
    }

    // -----------------------------------------------------------------
    //  GET /api/v2/listings/featured
    // -----------------------------------------------------------------

    public function featured(): JsonResponse
    {
        $limit = $this->queryInt('per_page', 10, 1, 50);

        $items = $this->listingFeaturedService->getFeaturedListings($limit);

        return $this->respondWithData($items);
    }

    // -----------------------------------------------------------------
    //  POST /api/v2/listings/{id}/save
    // -----------------------------------------------------------------

    public function saveListing(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $result = $this->listingService->saveListing($userId, $id);

        if (!$result) {
            return $this->respondWithError('NOT_FOUND', 'Listing not found', null, 404);
        }

        try {
            $this->listingAnalyticsService->updateSaveCount($id, true);
        } catch (\Exception $e) {
            // Non-critical
        }

        return $this->respondWithData(['saved' => true, 'listing_id' => $id]);
    }

    // -----------------------------------------------------------------
    //  DELETE /api/v2/listings/{id}/save
    // -----------------------------------------------------------------

    public function unsaveListing(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $this->listingService->unsaveListing($userId, $id);

        try {
            $this->listingAnalyticsService->updateSaveCount($id, false);
        } catch (\Exception $e) {
            // Non-critical
        }

        return $this->respondWithData(['saved' => false, 'listing_id' => $id]);
    }

    // -----------------------------------------------------------------
    //  GET /api/v2/listings/tags/popular
    // -----------------------------------------------------------------

    public function popularTags(): JsonResponse
    {
        $limit = $this->queryInt('limit', 20, 1, 50);

        $tags = $this->listingSkillTagService->getPopularTags($limit);

        return $this->respondWithData($tags);
    }

    // -----------------------------------------------------------------
    //  GET /api/v2/listings/tags/autocomplete
    // -----------------------------------------------------------------

    public function autocompleteTags(): JsonResponse
    {
        $prefix = trim($this->query('q', ''));
        if (strlen($prefix) < 2) {
            return $this->respondWithData([]);
        }

        $limit = $this->queryInt('limit', 10, 1, 20);
        $tags = $this->listingSkillTagService->autocompleteTags($prefix, $limit);

        return $this->respondWithData($tags);
    }

    // -----------------------------------------------------------------
    //  DELETE /api/v2/listings/{id}/image
    // -----------------------------------------------------------------

    public function deleteImage($id): JsonResponse
    {
        $id = (int) $id;
        $userId = $this->requireAuth();

        $listing = $this->listingService->getById($id);

        if (!$listing) {
            return $this->respondWithError('NOT_FOUND', 'Listing not found', null, 404);
        }

        if (!$this->listingService->canModify($listing, $userId)) {
            return $this->respondWithError('FORBIDDEN', 'You do not have permission to modify this listing', null, 403);
        }

        $this->listingService->update($id, ['image_url' => null]);

        return $this->respondWithData(['image_url' => null]);
    }

    // -----------------------------------------------------------------
    //  POST /api/v2/listings/{id}/renew
    // -----------------------------------------------------------------

    public function renew($id): JsonResponse
    {
        $id = (int) $id;
        $userId = $this->requireAuth();
        $this->rateLimit('listing_renew', 10, 60);

        $result = $this->listingExpiryService->renewListing($id, $userId);

        if (!$result['success']) {
            $status = $result['error'] === 'Listing not found' ? 404
                : ($result['error'] === 'You do not have permission to renew this listing' ? 403 : 422);
            return $this->respondWithError('RENEWAL_FAILED', $result['error'], null, $status);
        }

        return $this->respondWithData([
            'renewed'        => true,
            'listing_id'     => $id,
            'new_expires_at' => $result['new_expires_at'],
        ]);
    }

    // -----------------------------------------------------------------
    //  GET /api/v2/listings/{id}/analytics
    // -----------------------------------------------------------------

    public function analytics($id): JsonResponse
    {
        $id = (int) $id;
        $userId = $this->requireAuth();
        $this->rateLimit('listing_analytics', 60, 60);

        $listing = $this->listingService->getById($id);
        if (!$listing) {
            return $this->respondWithError('NOT_FOUND', 'Listing not found', null, 404);
        }

        if (!$this->listingService->canModify($listing, $userId)) {
            return $this->respondWithError('FORBIDDEN', 'You do not have permission to view analytics', null, 403);
        }

        $days = $this->queryInt('days', 30, 1, 90);
        $analytics = $this->listingAnalyticsService->getAnalytics($id, $days);

        return $this->respondWithData($analytics);
    }

    // -----------------------------------------------------------------
    //  PUT /api/v2/listings/{id}/tags
    // -----------------------------------------------------------------

    public function setSkillTags($id): JsonResponse
    {
        $id = (int) $id;
        $userId = $this->requireAuth();
        $this->rateLimit('listing_tags', 30, 60);

        $listing = $this->listingService->getById($id);
        if (!$listing) {
            return $this->respondWithError('NOT_FOUND', 'Listing not found', null, 404);
        }

        if (!$this->listingService->canModify($listing, $userId)) {
            return $this->respondWithError('FORBIDDEN', 'You do not have permission to modify this listing', null, 403);
        }

        $data = $this->getAllInput();
        $tags = $data['tags'] ?? [];

        if (!is_array($tags)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Tags must be an array of strings', 'tags', 400);
        }

        $success = $this->listingSkillTagService->setTags($id, $tags);

        if (!$success) {
            return $this->respondWithError('NOT_FOUND', 'Listing not found', null, 404);
        }

        $updatedTags = $this->listingSkillTagService->getTags($id);

        return $this->respondWithData(['listing_id' => $id, 'tags' => $updatedTags]);
    }

    // -----------------------------------------------------------------
    //  IMAGE UPLOAD
    // -----------------------------------------------------------------

    /**
     * POST /api/v2/listings/{id}/image
     *
     * Upload an image for a listing. Uses request()->file() (Laravel native).
     * Field name: 'image'
     */
    public function uploadImage($id): JsonResponse
    {
        $id = (int) $id;
        $userId = $this->requireAuth();
        $this->rateLimit('listing_image_upload', 10, 60);

        // Verify listing exists and user can modify it
        $listing = $this->listingService->getById($id);
        if (!$listing) {
            return $this->respondWithError('NOT_FOUND', 'Listing not found', null, 404);
        }

        if (!$this->listingService->canModify($listing, $userId)) {
            return $this->respondWithError('FORBIDDEN', 'You do not have permission to modify this listing', null, 403);
        }

        $file = request()->file('image');
        if (!$file || !$file->isValid()) {
            return $this->respondWithError('VALIDATION_ERROR', 'No image file uploaded or upload error', 'image', 400);
        }

        try {
            // Build a $_FILES-compatible array for ImageUploader::upload()
            $fileArray = [
                'name'     => $file->getClientOriginalName(),
                'type'     => $file->getMimeType(),
                'tmp_name' => $file->getRealPath(),
                'error'    => UPLOAD_ERR_OK,
                'size'     => $file->getSize(),
            ];

            $imageUrl = \App\Core\ImageUploader::upload($fileArray);

            // Update listing with new image
            $this->listingService->update($id, ['image_url' => $imageUrl]);

            return $this->respondWithData(['image_url' => $imageUrl]);
        } catch (\Exception $e) {
            \Log::error('Listing image upload failed', ['error' => $e->getMessage()]);
            return $this->respondWithError('UPLOAD_FAILED', 'Failed to upload image', 'image', 500);
        }
    }
}
