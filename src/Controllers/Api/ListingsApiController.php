<?php

namespace Nexus\Controllers\Api;

use Nexus\Services\ListingService;
use Nexus\Core\TenantContext;
use Nexus\Core\ImageUploader;
use Nexus\Helpers\UrlHelper;

/**
 * ListingsApiController - RESTful API for listings
 *
 * Provides full CRUD operations for listings with standardized response format.
 *
 * Endpoints:
 * - GET    /api/v2/listings           - List all listings (paginated)
 * - POST   /api/v2/listings           - Create a new listing
 * - GET    /api/v2/listings/{id}      - Get a single listing
 * - PUT    /api/v2/listings/{id}      - Update a listing
 * - DELETE /api/v2/listings/{id}      - Delete a listing
 * - GET    /api/v2/listings/nearby    - Get nearby listings (geospatial)
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

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $filters['limit'] = $this->queryInt('per_page', 20, 1, 100);

        // Get listings
        $result = ListingService::getAll($filters);

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
        }

        $lat = (float)$lat;
        $lon = (float)$lon;

        // Validate coordinates
        if ($lat < -90 || $lat > 90) {
            $this->respondWithError('VALIDATION_ERROR', 'Latitude must be between -90 and 90', 'lat', 400);
        }
        if ($lon < -180 || $lon > 180) {
            $this->respondWithError('VALIDATION_ERROR', 'Longitude must be between -180 and 180', 'lon', 400);
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
     * GET /api/v2/listings/{id}
     *
     * Get a single listing by ID.
     *
     * Response: 200 OK with listing data, or 404 if not found
     */
    public function show(int $id): void
    {
        $listing = ListingService::getById($id);

        if (!$listing) {
            $this->respondWithError('NOT_FOUND', 'Listing not found', null, 404);
        }

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
        }

        if (!ListingService::canModify($listing, $userId)) {
            $this->respondWithError('FORBIDDEN', 'You do not have permission to modify this listing', null, 403);
        }

        // Check for uploaded file
        if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $this->respondWithError('VALIDATION_ERROR', 'No image file uploaded or upload error', 'image', 400);
        }

        try {
            $imageUrl = ImageUploader::upload($_FILES['image']);

            // Update listing with new image
            ListingService::update($id, $userId, ['image_url' => $imageUrl]);

            $this->respondWithData(['image_url' => $imageUrl]);
        } catch (\Exception $e) {
            $this->respondWithError('UPLOAD_FAILED', 'Failed to upload image: ' . $e->getMessage(), 'image', 400);
        }
    }
}
