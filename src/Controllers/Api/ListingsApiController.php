<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Services\ListingService;
use Nexus\Services\ListingAnalyticsService;
use Nexus\Services\ListingExpiryService;
use Nexus\Services\ListingRankingService;
use Nexus\Services\ListingSkillTagService;
use Nexus\Services\ListingFeaturedService;
use Nexus\Core\ImageUploader;

/**
 * ListingsApiController - RESTful API for listings
 *
 * Provides full CRUD operations for listings with standardized response format.
 *
 * Endpoints:
 * - GET    /api/v2/listings                    - List all listings (paginated)
 * - POST   /api/v2/listings                    - Create a new listing
 * - GET    /api/v2/listings/featured           - Get featured listings
 * - GET    /api/v2/listings/nearby             - Get nearby listings (geospatial)
 * - GET    /api/v2/listings/tags/popular       - Get popular skill tags
 * - GET    /api/v2/listings/tags/autocomplete  - Autocomplete skill tags
 * - GET    /api/v2/listings/{id}               - Get a single listing
 * - PUT    /api/v2/listings/{id}               - Update a listing
 * - DELETE /api/v2/listings/{id}               - Delete a listing
 * - POST   /api/v2/listings/{id}/renew         - Renew an expired listing
 * - GET    /api/v2/listings/{id}/analytics     - Get listing analytics (owner only)
 * - PUT    /api/v2/listings/{id}/tags          - Set skill tags for a listing
 *
 * Response Format:
 * Success: { "data": {...}, "meta": {...} }
 * Error:   { "errors": [{ "code": "...", "message": "...", "field": "..." }] }
 */
class ListingsApiController extends BaseApiController
{
    /**
     * GET /api/v2/listings
     *
     * List listings with optional filtering and cursor-based pagination.
     *
     * Query Parameters:
     * - type: string ('offer' or 'request') or comma-separated for multiple
     * - category_id: int (filter by category ID)
     * - category: string (filter by category slug, alternative to category_id)
     * - q: string (search term)
     * - cursor: string (pagination cursor)
     * - per_page: int (default 20, max 100)
     * - user_id: int (filter by owner)
     *
     * Response: 200 OK with data array and pagination meta
     */
    public function index(): void
    {
        // Optional authentication - public endpoint
        $userId = $this->getOptionalUserId();

        // Build filters from query parameters
        $filters = [];

        $type = $this->query('type');
        if ($type) {
            // Support comma-separated types
            $filters['type'] = strpos($type, ',') !== false ? explode(',', $type) : $type;
        }

        if ($this->query('category_id')) {
            $filters['category_id'] = $this->queryInt('category_id');
        } elseif ($this->query('category')) {
            // Support category by slug - look up the ID
            $filters['category_slug'] = $this->query('category');
        }

        if ($this->query('q')) {
            $filters['search'] = $this->query('q');
        }

        if ($this->query('user_id')) {
            $filters['user_id'] = $this->queryInt('user_id');
        }

        // Skills filter: ?skills=cooking,gardening
        if ($this->query('skills')) {
            $filters['skills'] = $this->query('skills');
        }

        // Featured first: ?featured_first=1
        if ($this->query('featured_first')) {
            $filters['featured_first'] = true;
        }

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $filters['limit'] = $this->queryInt('per_page', 20, 1, 100);

        // Pass current user ID so getAll() can attach is_favorited
        if ($userId !== null) {
            $filters['current_user_id'] = $userId;
        }

        // Get listings
        $result = ListingService::getAll($filters);

        // Apply MatchRank if enabled; skip when featured_first is set (respect intentional curation)
        if (ListingRankingService::isEnabled() && !empty($result['items']) && empty($filters['featured_first'])) {
            $result['items'] = ListingRankingService::rankListings(
                $result['items'],
                $userId,
                ['search' => $filters['search'] ?? null]
            );
            // Strip internal scoring fields — not part of the public API contract
            $result['items'] = array_map(static function (array $item): array {
                unset($item['_match_rank'], $item['_score_breakdown']);
                return $item;
            }, $result['items']);
        }

        // Return with cursor-based pagination
        $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /**
     * GET /api/v2/listings/nearby
     *
     * Get listings near a geographic point.
     *
     * Query Parameters:
     * - lat: float (required) - Latitude
     * - lon: float (required) - Longitude
     * - radius_km: float (default 25) - Search radius in kilometers
     * - type: string ('offer' or 'request')
     * - category_id: int
     * - per_page: int (default 20, max 100)
     *
     * Response: 200 OK with data array and search meta
     */
    public function nearby(): void
    {
        $lat = $this->query('lat');
        $lon = $this->query('lon');

        if ($lat === null || $lon === null) {
            $this->respondWithError('VALIDATION_ERROR', 'Latitude and longitude are required', null, 400);
            return;
        }

        $lat = (float)$lat;
        $lon = (float)$lon;

        // Validate coordinates
        if ($lat < -90 || $lat > 90) {
            $this->respondWithError('VALIDATION_ERROR', 'Latitude must be between -90 and 90', 'lat', 400);
            return;
        }
        if ($lon < -180 || $lon > 180) {
            $this->respondWithError('VALIDATION_ERROR', 'Longitude must be between -180 and 180', 'lon', 400);
            return;
        }

        $filters = [
            'radius_km' => (float)$this->query('radius_km', '25'),
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];

        $type = $this->query('type');
        if ($type) {
            $filters['type'] = strpos($type, ',') !== false ? explode(',', $type) : $type;
        }

        if ($this->query('category_id')) {
            $filters['category_id'] = $this->queryInt('category_id');
        }

        $result = ListingService::getNearby($lat, $lon, $filters);

        $this->respondWithData($result['items'], [
            'search' => [
                'type' => 'nearby',
                'lat' => $lat,
                'lon' => $lon,
                'radius_km' => $filters['radius_km'],
            ],
            'per_page' => $filters['limit'],
            'has_more' => $result['has_more'],
        ]);
    }

    /**
     * GET /api/v2/listings/featured
     *
     * Get currently featured listings.
     *
     * Query Parameters:
     * - per_page: int (default 10, max 50)
     *
     * Response: 200 OK with featured listings
     */
    public function featured(): void
    {
        $limit = $this->queryInt('per_page', 10, 1, 50);
        $items = ListingFeaturedService::getFeaturedListings($limit);

        $this->respondWithData($items);
    }

    /**
     * GET /api/v2/listings/tags/popular
     *
     * Get popular skill tags for the current tenant.
     *
     * Query Parameters:
     * - limit: int (default 20, max 50)
     *
     * Response: 200 OK with [{ tag, count }, ...]
     */
    public function popularTags(): void
    {
        $limit = $this->queryInt('limit', 20, 1, 50);
        $tags = ListingSkillTagService::getPopularTags($limit);

        $this->respondWithData($tags);
    }

    /**
     * GET /api/v2/listings/tags/autocomplete
     *
     * Autocomplete skill tags based on a prefix.
     *
     * Query Parameters:
     * - q: string (required, min 2 chars)
     * - limit: int (default 10, max 20)
     *
     * Response: 200 OK with array of tag strings
     */
    public function autocompleteTags(): void
    {
        $prefix = trim($this->query('q', ''));
        if (strlen($prefix) < 2) {
            $this->respondWithData([]);
            return;
        }

        $limit = $this->queryInt('limit', 10, 1, 20);
        $tags = ListingSkillTagService::autocompleteTags($prefix, $limit);

        $this->respondWithData($tags);
    }

    /**
     * GET /api/v2/listings/{id}
     *
     * Get a single listing by ID.
     * Records a view for analytics if the viewer is not the owner.
     *
     * Response: 200 OK with listing data, or 404 if not found
     */
    public function show(int|string $id): void
    {
        // Guard against non-numeric IDs (e.g. "featured" leaking through route ordering)
        if (!is_numeric($id)) {
            $this->respondWithError('NOT_FOUND', 'Listing not found', null, 404);
            return;
        }
        $id = (int) $id;

        $userId  = $this->getOptionalUserId();
        $listing = ListingService::getById($id, false, $userId);

        if (!$listing) {
            $this->respondWithError('NOT_FOUND', 'Listing not found', null, 404);
            return;
        }

        // Track view (skip if viewer is the listing owner)
        if ($userId === null || $userId !== (int)($listing['user_id'] ?? 0)) {
            try {
                $ip = \Nexus\Core\ClientIp::get();
                ListingAnalyticsService::recordView($id, $userId, $ip);
            } catch (\Exception $e) {
                // Non-critical
            }
        }

        // Attach skill tags
        try {
            $listing['skill_tags'] = ListingSkillTagService::getTags($id);
        } catch (\Exception $e) {
            $listing['skill_tags'] = [];
        }

        // Attach featured status
        $listing['is_featured'] = (bool)($listing['is_featured'] ?? false);

        $this->respondWithData($listing);
    }

    /**
     * POST /api/v2/listings
     *
     * Create a new listing.
     *
     * Request Body (JSON):
     * {
     *   "title": "string (required)",
     *   "description": "string (required)",
     *   "type": "offer|request (default: offer)",
     *   "category_id": "int (optional)",
     *   "image_url": "string (optional)",
     *   "location": "string (optional - uses user's location if not provided)",
     *   "latitude": "float (optional)",
     *   "longitude": "float (optional)",
     *   "federated_visibility": "none|listed|bookable (default: none)",
     *   "attributes": {"attr_id": true, ...} (optional),
     *   "sdg_goals": {"goal_id": true, ...} (optional)
     * }
     *
     * Response: 201 Created with new listing data
     */
    public function store(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('listing_create', 10, 60);

        $data = $this->getAllInput();

        $listingId = ListingService::create($userId, $data);

        if ($listingId === null) {
            $errors = ListingService::getErrors();
            $this->respondWithErrors($errors, 422);
        }

        // Fetch the created listing
        $listing = ListingService::getById($listingId);

        $this->respondWithData($listing, null, 201);
    }

    /**
     * PUT /api/v2/listings/{id}
     *
     * Update an existing listing.
     *
     * Request Body (JSON): Same as store, all fields optional
     *
     * Response: 200 OK with updated listing data
     */
    public function update(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('listing_update', 20, 60);

        $data = $this->getAllInput();

        $success = ListingService::update($id, $userId, $data);

        if (!$success) {
            $errors = ListingService::getErrors();
            $status = 422;

            // Determine appropriate status code
            foreach ($errors as $error) {
                if ($error['code'] === 'NOT_FOUND') {
                    $status = 404;
                    break;
                }
                if ($error['code'] === 'FORBIDDEN') {
                    $status = 403;
                    break;
                }
            }

            $this->respondWithErrors($errors, $status);
        }

        // Fetch the updated listing
        $listing = ListingService::getById($id);

        $this->respondWithData($listing);
    }

    /**
     * DELETE /api/v2/listings/{id}
     *
     * Delete a listing (soft delete).
     *
     * Response: 204 No Content on success
     */
    public function destroy(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('listing_delete', 10, 60);

        $success = ListingService::delete($id, $userId);

        if (!$success) {
            $errors = ListingService::getErrors();
            $status = 400;

            foreach ($errors as $error) {
                if ($error['code'] === 'NOT_FOUND') {
                    $status = 404;
                    break;
                }
                if ($error['code'] === 'FORBIDDEN') {
                    $status = 403;
                    break;
                }
            }

            $this->respondWithErrors($errors, $status);
        }

        $this->noContent();
    }

    /**
     * POST /api/v2/listings/{id}/save
     *
     * Save (favourite) a listing for the authenticated user.
     *
     * Response: 200 OK with { saved: true, listing_id: int }
     */
    public function saveListing(int $id): void
    {
        $userId = $this->getUserId();

        $result = ListingService::saveListing($userId, $id);

        if (!$result) {
            $this->respondWithError('NOT_FOUND', 'Listing not found', null, 404);
            return;
        }

        // Update save count cache
        try {
            ListingAnalyticsService::updateSaveCount($id, true);
        } catch (\Exception $e) {
            // Non-critical
        }

        $this->respondWithData(['saved' => true, 'listing_id' => $id]);
    }

    /**
     * DELETE /api/v2/listings/{id}/save
     *
     * Unsave (un-favourite) a listing for the authenticated user.
     *
     * Response: 200 OK with { saved: false, listing_id: int }
     */
    public function unsaveListing(int $id): void
    {
        $userId = $this->getUserId();

        ListingService::unsaveListing($userId, $id);

        // Update save count cache
        try {
            ListingAnalyticsService::updateSaveCount($id, false);
        } catch (\Exception $e) {
            // Non-critical
        }

        $this->respondWithData(['saved' => false, 'listing_id' => $id]);
    }

    /**
     * GET /api/v2/listings/saved
     *
     * Get listing IDs saved by the authenticated user in the current tenant.
     *
     * Response: 200 OK with array of listing IDs
     */
    public function getSavedListings(): void
    {
        $userId = $this->getUserId();

        $savedIds = ListingService::getSavedListingIds($userId);

        $this->respondWithData($savedIds);
    }

    /**
     * POST /api/v2/listings/{id}/image
     *
     * Upload an image for a listing.
     *
     * Request: multipart/form-data with 'image' file
     *
     * Response: 200 OK with image URL
     */
    public function uploadImage(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('listing_image_upload', 10, 60);

        // Verify listing exists and user can modify it
        $listing = ListingService::getById($id);

        if (!$listing) {
            $this->respondWithError('NOT_FOUND', 'Listing not found', null, 404);
            return;
        }

        if (!ListingService::canModify($listing, $userId)) {
            $this->respondWithError('FORBIDDEN', 'You do not have permission to modify this listing', null, 403);
            return;
        }

        // Check for uploaded file
        if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $this->respondWithError('VALIDATION_ERROR', 'No image file uploaded or upload error', 'image', 400);
            return;
        }

        try {
            $imageUrl = ImageUploader::upload($_FILES['image']);

            // Update listing with new image
            ListingService::update($id, $userId, ['image_url' => $imageUrl]);

            $this->respondWithData(['image_url' => $imageUrl]);
        } catch (\Exception $e) {
            $this->respondWithError('UPLOAD_FAILED', 'Failed to upload image: ' . $e->getMessage(), 'image', 400);
            return;
        }
    }

    /**
     * DELETE /api/v2/listings/{id}/image
     *
     * Remove the image from a listing.
     *
     * Response: 200 OK with { image_url: null }
     */
    public function deleteImage(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();

        $listing = ListingService::getById($id);

        if (!$listing) {
            $this->respondWithError('NOT_FOUND', 'Listing not found', null, 404);
            return;
        }

        if (!ListingService::canModify($listing, $userId)) {
            $this->respondWithError('FORBIDDEN', 'You do not have permission to modify this listing', null, 403);
            return;
        }

        ListingService::update($id, $userId, ['image_url' => null]);

        $this->respondWithData(['image_url' => null]);
    }

    /**
     * POST /api/v2/listings/{id}/renew
     *
     * Renew a listing (extend its expiry by 30 days).
     * Owner or admin only.
     *
     * Response: 200 OK with { renewed: true, new_expires_at: string }
     */
    public function renew(int $id): void
    {
        $userId = $this->getUserId();
        $this->rateLimit('listing_renew', 10, 60);

        $result = ListingExpiryService::renewListing($id, $userId);

        if (!$result['success']) {
            $status = $result['error'] === 'Listing not found' ? 404
                : ($result['error'] === 'You do not have permission to renew this listing' ? 403 : 422);
            $this->respondWithError('RENEWAL_FAILED', $result['error'], null, $status);
            return;
        }

        $this->respondWithData([
            'renewed' => true,
            'listing_id' => $id,
            'new_expires_at' => $result['new_expires_at'],
        ]);
    }

    /**
     * GET /api/v2/listings/{id}/analytics
     *
     * Get analytics for a listing (owner or admin only).
     *
     * Query Parameters:
     * - days: int (default 30, max 90)
     *
     * Response: 200 OK with analytics data
     */
    public function analytics(int $id): void
    {
        $userId = $this->getUserId();

        // Verify ownership or admin
        $listing = ListingService::getById($id);
        if (!$listing) {
            $this->respondWithError('NOT_FOUND', 'Listing not found', null, 404);
            return;
        }

        if (!ListingService::canModify($listing, $userId)) {
            $this->respondWithError('FORBIDDEN', 'You do not have permission to view analytics', null, 403);
            return;
        }

        $days = $this->queryInt('days', 30, 1, 90);
        $analytics = ListingAnalyticsService::getAnalytics($id, $days);

        $this->respondWithData($analytics);
    }

    /**
     * PUT /api/v2/listings/{id}/tags
     *
     * Set skill tags for a listing (owner or admin only).
     *
     * Request Body (JSON):
     * { "tags": ["cooking", "gardening", "repair"] }
     *
     * Response: 200 OK with updated tags
     */
    public function setSkillTags(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();

        // Verify ownership or admin
        $listing = ListingService::getById($id);
        if (!$listing) {
            $this->respondWithError('NOT_FOUND', 'Listing not found', null, 404);
            return;
        }

        if (!ListingService::canModify($listing, $userId)) {
            $this->respondWithError('FORBIDDEN', 'You do not have permission to modify this listing', null, 403);
            return;
        }

        $data = $this->getAllInput();
        $tags = $data['tags'] ?? [];

        if (!is_array($tags)) {
            $this->respondWithError('VALIDATION_ERROR', 'Tags must be an array of strings', 'tags', 400);
            return;
        }

        $success = ListingSkillTagService::setTags($id, $tags);

        if (!$success) {
            $this->respondWithError('NOT_FOUND', 'Listing not found', null, 404);
            return;
        }

        $updatedTags = ListingSkillTagService::getTags($id);
        $this->respondWithData(['listing_id' => $id, 'tags' => $updatedTags]);
    }
}
