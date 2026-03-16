<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Models\Review;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * ReviewService — Laravel DI-based service for review operations.
 *
 * Eloquent/DI counterpart to the legacy static \Nexus\Services\ReviewService.
 * All queries are tenant-scoped automatically via the HasTenantScope trait.
 */
class ReviewService
{
    public function __construct(
        private readonly Review $review,
    ) {}

    /**
     * Get reviews for a specific user (as receiver).
     *
     * @return array{items: array, average_rating: float|null, total: int}
     */
    public function getForUser(int $userId, int $limit = 20): array
    {
        $query = $this->review->newQuery()
            ->with(['reviewer:id,first_name,last_name,avatar_url,organization_name,profile_type'])
            ->where('receiver_id', $userId)
            ->orderByDesc('id');

        $reviews = $query->limit($limit)->get();

        $avgRating = $this->review->newQuery()
            ->where('receiver_id', $userId)
            ->avg('rating');

        $total = $this->review->newQuery()
            ->where('receiver_id', $userId)
            ->count();

        return [
            'items'          => $reviews->toArray(),
            'average_rating' => $avgRating !== null ? round((float) $avgRating, 2) : null,
            'total'          => $total,
        ];
    }

    /**
     * Create a new review.
     *
     * @throws ValidationException
     * @throws \RuntimeException
     */
    public function create(int $reviewerId, int $receiverId, array $data): Review
    {
        validator($data, [
            'rating'         => 'required|integer|min:1|max:5',
            'comment'        => 'nullable|string|max:2000',
            'transaction_id' => 'nullable|integer|exists:transactions,id',
        ])->validate();

        // Prevent duplicate reviews for same transaction
        if (! empty($data['transaction_id'])) {
            $exists = $this->review->newQuery()
                ->where('reviewer_id', $reviewerId)
                ->where('transaction_id', $data['transaction_id'])
                ->exists();

            if ($exists) {
                throw new \RuntimeException('You have already reviewed this exchange');
            }
        }

        // Prevent self-review
        if ($reviewerId === $receiverId) {
            throw new \RuntimeException('You cannot review yourself');
        }

        $review = $this->review->newInstance([
            'reviewer_id'    => $reviewerId,
            'receiver_id'    => $receiverId,
            'transaction_id' => $data['transaction_id'] ?? null,
            'rating'         => (int) $data['rating'],
            'comment'        => $data['comment'] ?? null,
            'status'         => 'active',
        ]);

        $review->save();

        return $review->fresh(['reviewer', 'receiver']);
    }

    /**
     * Delete a review (only by the reviewer).
     */
    public function delete(int $reviewId, int $userId): bool
    {
        /** @var Review|null $review */
        $review = $this->review->newQuery()
            ->where('id', $reviewId)
            ->where('reviewer_id', $userId)
            ->first();

        if (! $review) {
            return false;
        }

        $review->delete();

        return true;
    }
}
