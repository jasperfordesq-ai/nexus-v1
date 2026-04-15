<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\EmailTemplateBuilder;
use App\Core\Mailer;
use App\Core\TenantContext;
use App\Events\ReviewCreated;
use App\Models\Review;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * ReviewService — Laravel DI-based service for review operations.
 *
 * All queries are tenant-scoped automatically via the HasTenantScope trait.
 */
class ReviewService
{
    public function __construct(
        private readonly Review $review,
    ) {}

    /**
     * Get reviews for a specific user (as receiver) with cursor pagination.
     *
     * @param int   $userId  The user whose reviews to fetch
     * @param array $filters Optional: limit, cursor
     * @return array{items: array, cursor: string|null, has_more: bool, average_rating: float|null, total: int}
     */
    public function getForUser(int $userId, array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $cursor = $filters['cursor'] ?? null;

        $query = $this->review->newQuery()
            ->withFederated()
            ->with(['reviewer:id,first_name,last_name,avatar_url,organization_name,profile_type'])
            ->where('receiver_id', $userId)
            ->where(function (Builder $q) {
                $q->whereNull('status')
                   ->orWhereIn('status', ['active', 'approved']);
            })
            ->orderByDesc('id');

        if ($cursor !== null) {
            $cursorId = base64_decode($cursor, true);
            if ($cursorId !== false) {
                $query->where('id', '<', (int) $cursorId);
            }
        }

        $items = $query->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items->pop();
        }

        $nextCursor = $hasMore && $items->isNotEmpty()
            ? base64_encode((string) $items->last()->id)
            : null;

        // Format reviews to match the React contract
        $formatted = $items->map(function (Review $r) {
            $reviewer = $r->reviewer;
            $reviewerName = ($reviewer && $reviewer->profile_type === 'organisation' && $reviewer->organization_name)
                ? $reviewer->organization_name
                : trim(($reviewer->first_name ?? '') . ' ' . ($reviewer->last_name ?? ''));

            return [
                'id'           => $r->id,
                'rating'       => $r->rating,
                'comment'      => $r->comment,
                'review_type'  => $r->review_type ?? 'local',
                'is_anonymous' => (bool) ($r->is_anonymous ?? false),
                'reviewer'     => [
                    'id'         => $reviewer?->id,
                    'name'       => ($r->is_anonymous ?? false) ? 'Anonymous' : $reviewerName,
                    'first_name' => ($r->is_anonymous ?? false) ? null : $reviewer?->first_name,
                    'last_name'  => ($r->is_anonymous ?? false) ? null : $reviewer?->last_name,
                    'avatar'     => ($r->is_anonymous ?? false) ? null : $reviewer?->avatar_url,
                    'avatar_url' => ($r->is_anonymous ?? false) ? null : $reviewer?->avatar_url,
                ],
                'created_at' => $r->created_at?->toIso8601String(),
            ];
        })->all();

        // Aggregate stats — include federated reviews so reputation follows the user
        $avgRating = $this->review->newQuery()
            ->withFederated()
            ->where('receiver_id', $userId)
            ->where(function (Builder $q) {
                $q->whereNull('status')->orWhereIn('status', ['active', 'approved']);
            })
            ->avg('rating');

        $total = $this->review->newQuery()
            ->withFederated()
            ->where('receiver_id', $userId)
            ->where(function (Builder $q) {
                $q->whereNull('status')->orWhereIn('status', ['active', 'approved']);
            })
            ->count();

        return [
            'items'          => array_values($formatted),
            'cursor'         => $nextCursor,
            'has_more'       => $hasMore,
            'average_rating' => $avgRating !== null ? round((float) $avgRating, 2) : null,
            'total'          => $total,
        ];
    }

    /**
     * Get review stats (average, total, distribution) for a user.
     *
     * @return array{total: int, average: float, distribution: array}
     */
    public function getStats(int $userId): array
    {
        $baseQuery = fn () => $this->review->newQuery()
            ->withFederated()
            ->where('receiver_id', $userId)
            ->where(function (Builder $q) {
                $q->whereNull('status')->orWhereIn('status', ['active', 'approved']);
            });

        $total = $baseQuery()->count();
        $average = $total > 0 ? round((float) $baseQuery()->avg('rating'), 2) : 0;

        // Distribution
        $distribution = [];
        for ($i = 5; $i >= 1; $i--) {
            $distribution[$i] = $baseQuery()->where('rating', $i)->count();
        }

        $positive = ($distribution[5] ?? 0) + ($distribution[4] ?? 0);
        $negative = ($distribution[2] ?? 0) + ($distribution[1] ?? 0);

        return [
            'total'        => $total,
            'average'      => $average,
            'positive'     => $positive,
            'negative'     => $negative,
            'distribution' => $distribution,
        ];
    }

    /**
     * Get a single review by ID.
     */
    public function getById(int $reviewId): ?array
    {
        /** @var Review|null $review */
        $review = $this->review->newQuery()
            ->withFederated()
            ->with([
                'reviewer:id,first_name,last_name,avatar_url,organization_name,profile_type',
                'receiver:id,first_name,last_name,avatar_url,organization_name,profile_type',
            ])
            ->find($reviewId);

        if (! $review) {
            return null;
        }

        return $review->toArray();
    }

    /**
     * Create a new review.
     *
     * @param int   $reviewerId The user creating the review
     * @param array $data       Review data: receiver_id, rating, comment, transaction_id
     * @return array Created review data
     *
     * @throws ValidationException
     * @throws \RuntimeException
     */
    public function create(int $reviewerId, array $data): array
    {
        $receiverId = (int) ($data['receiver_id'] ?? 0);

        // Prevent self-review (check before validation to avoid unnecessary DB queries)
        if ($receiverId > 0 && $reviewerId === $receiverId) {
            throw new \RuntimeException('You cannot review yourself');
        }

        validator($data, [
            'receiver_id'    => 'required|integer|exists:users,id',
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
        } else {
            // Without a transaction_id, prevent multiple reviews for the same receiver
            // within a 24-hour window to avoid spam
            $recentExists = $this->review->newQuery()
                ->where('reviewer_id', $reviewerId)
                ->where('receiver_id', $receiverId)
                ->whereNull('transaction_id')
                ->where('created_at', '>=', now()->subDay())
                ->exists();

            if ($recentExists) {
                throw new \RuntimeException('You have already reviewed this member recently');
            }
        }

        $review = $this->review->newInstance([
            'reviewer_id'    => $reviewerId,
            'receiver_id'    => $receiverId,
            'transaction_id' => $data['transaction_id'] ?? null,
            'rating'         => (int) $data['rating'],
            'comment'        => $data['comment'] ?? null,
            'status'         => 'approved',
        ]);

        $review->save();

        $review = $review->fresh(['reviewer', 'receiver']);

        // Fire ReviewCreated so federation listeners can push the review to
        // the receiver's home partner (reputation portability).
        try {
            ReviewCreated::dispatch($review, (int) TenantContext::getId());
        } catch (\Throwable $e) {
            Log::warning('ReviewCreated dispatch failed', [
                'review_id' => $review->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Notify receiver they got a new review (skip anonymous reviews)
        if (empty($review->is_anonymous)) {
            try {
                $tenantId = TenantContext::getId();
                $receiver = DB::table('users')->where('id', $receiverId)->where('tenant_id', $tenantId)->select(['email', 'first_name', 'name'])->first();
                if ($receiver && !empty($receiver->email)) {
                    $firstName = $receiver->first_name ?? $receiver->name ?? 'there';
                    $fullUrl   = TenantContext::getFrontendUrl() . TenantContext::getSlugPrefix() . '/profile/' . $receiverId . '/reviews';
                    $html = EmailTemplateBuilder::make()
                        ->title(__('emails_misc.review.received_title'))
                        ->greeting($firstName)
                        ->paragraph(__('emails_misc.review.received_body', ['rating' => (int) $review->rating]))
                        ->button(__('emails_misc.review.received_cta'), $fullUrl)
                        ->render();
                    if (!Mailer::forCurrentTenant()->send($receiver->email, __('emails_misc.review.received_subject'), $html)) {
                        Log::warning('[ReviewService] create email failed', ['receiver_id' => $receiverId]);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[ReviewService] create email error: ' . $e->getMessage());
            }
        }

        return [
            'id'          => $review->id,
            'rating'      => $review->rating,
            'comment'     => $review->comment,
            'receiver_id' => $review->receiver_id,
            'message'     => __('svc_notifications_2.review.submitted_successfully'),
        ];
    }

    /**
     * Create a review (static entry point).
     *
     * @param int $reviewerId Reviewer user ID
     * @param int $receiverId Receiver user ID
     * @param int $rating Rating (1-5)
     * @param string|null $comment Optional comment
     * @return array|null Created review data or null on failure
     */
    public static function createReview(int $reviewerId, int $receiverId, int $rating, ?string $comment = null): ?array
    {
        try {
            $service = app(self::class);
            return $service->create($reviewerId, [
                'receiver_id' => $receiverId,
                'rating' => $rating,
                'comment' => $comment,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('ReviewService::createReview error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Delete (soft-hide) a review. Only the reviewer may delete.
     */
    public function delete(int $reviewId): bool
    {
        /** @var Review|null $review */
        $review = $this->review->newQuery()->find($reviewId);

        if (! $review) {
            return false;
        }

        $review->status = 'hidden';
        $review->save();

        return true;
    }
}
