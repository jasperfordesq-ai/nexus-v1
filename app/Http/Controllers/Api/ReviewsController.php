<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;

/**
 * ReviewsController - User reviews for completed exchanges.
 *
 * Endpoints (v2):
 *   GET    /api/v2/reviews/user/{userId}  userReviews()
 *   POST   /api/v2/reviews                store()
 *   DELETE /api/v2/reviews/{id}           destroy()
 */
class ReviewsController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly ReviewService $reviewService,
    ) {}

    /**
     * List reviews for a specific user.
     */
    public function userReviews(int $userId): JsonResponse
    {
        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];
        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = $this->reviewService->getForUser($userId, $filters);

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'] ?? null,
            $filters['limit'],
            $result['has_more'] ?? false
        );
    }

    /**
     * Create a review. Requires authentication.
     */
    public function store(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('review_create', 10, 60);

        $review = $this->reviewService->create($userId, $this->getAllInput());

        return $this->respondWithData($review, null, 201);
    }

    /**
     * Delete a review. Only the review author may delete.
     */
    public function destroy(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $existing = $this->reviewService->getById($id);

        if ($existing === null) {
            return $this->respondWithError('NOT_FOUND', 'Review not found', null, 404);
        }
        if ((int) ($existing['reviewer_id'] ?? 0) !== $userId) {
            return $this->respondWithError('FORBIDDEN', 'You did not author this review', null, 403);
        }

        $this->reviewService->delete($id);

        return $this->noContent();
    }

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


    public function pending(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\ReviewsApiController::class, 'pending');
    }


    public function userStats($userId): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\ReviewsApiController::class, 'userStats', [$userId]);
    }


    public function userTrust($userId): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\ReviewsApiController::class, 'userTrust', [$userId]);
    }


    public function show($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\ReviewsApiController::class, 'show', [$id]);
    }

}
