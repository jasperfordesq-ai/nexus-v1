<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\VolDonation;
use App\Models\VolGivingDay;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * VolunteerDonationService — manages monetary donations linked to volunteer
 * opportunities and giving-day fundraising campaigns.
 *
 * Supports cursor-based pagination, giving-day progress tracking,
 * and CSV export for admin reporting.
 *
 * All queries are tenant-scoped automatically via the HasTenantScope trait on models.
 */
class VolunteerDonationService
{
    /** Default page size for paginated queries */
    private const DEFAULT_LIMIT = 20;

    /** Maximum page size */
    private const MAX_LIMIT = 100;

    /** Valid donation statuses for filtering */
    private const VALID_STATUSES = ['pending', 'completed', 'refunded', 'failed'];

    public function __construct()
    {
    }

    /**
     * Get paginated donations for the current tenant.
     *
     * Uses cursor-based pagination (keyset on id DESC). Optionally filters
     * by opportunity_id and/or giving_day_id.
     *
     * @param array $filters Keys: opportunity_id, giving_day_id, cursor, limit
     * @return array{items: array, next_cursor: int|null}
     */
    public static function getDonations(array $filters = []): array
    {
        $limit = max(1, min((int) ($filters['limit'] ?? self::DEFAULT_LIMIT), self::MAX_LIMIT));
        $cursor = isset($filters['cursor']) ? (int) $filters['cursor'] : null;

        $query = VolDonation::query()
            ->select([
                'id', 'user_id', 'opportunity_id', 'giving_day_id',
                'amount', 'currency', 'payment_method', 'payment_reference',
                'message', 'is_anonymous', 'status', 'created_at',
            ]);

        if (!empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (!empty($filters['opportunity_id'])) {
            $query->where('opportunity_id', (int) $filters['opportunity_id']);
        }

        if (!empty($filters['giving_day_id'])) {
            $query->where('giving_day_id', (int) $filters['giving_day_id']);
        }

        if ($cursor !== null) {
            $query->where('id', '<', $cursor);
        }

        $rows = $query->orderByDesc('id')
            ->limit($limit + 1)
            ->get();

        $nextCursor = null;
        if ($rows->count() > $limit) {
            $rows->pop();
            $nextCursor = $rows->last()->id;
        }

        return [
            'items' => $rows->map(fn ($row) => $row->toArray())->values()->toArray(),
            'next_cursor' => $nextCursor,
        ];
    }

    /**
     * Create a donation.
     *
     * If a giving_day_id is provided and valid, the giving day's
     * raised_amount is atomically incremented within the same transaction.
     *
     * @param int   $userId Donor user ID
     * @param array $data   Keys: amount, currency, payment_method, payment_reference,
     *                      message, is_anonymous, opportunity_id, giving_day_id
     * @return array The created donation record
     * @throws \InvalidArgumentException On validation failure
     */
    public static function createDonation(int $userId, array $data): array
    {
        $tenantId = TenantContext::getId();

        $amount = (float) ($data['amount'] ?? 0);
        $currency = strtoupper(trim($data['currency'] ?? 'EUR'));
        $paymentMethod = trim($data['payment_method'] ?? '');
        $paymentReference = trim($data['payment_reference'] ?? '');
        $message = trim($data['message'] ?? '');
        $isAnonymous = !empty($data['is_anonymous']) ? 1 : 0;
        $opportunityId = isset($data['opportunity_id']) ? (int) $data['opportunity_id'] : null;
        $givingDayId = isset($data['giving_day_id']) ? (int) $data['giving_day_id'] : null;
        // Donations always start as 'pending' — only payment webhooks or admin actions
        // should mark them 'completed'. Allowing caller-controlled status would let
        // users bypass payment verification and inflate giving-day totals.
        $status = 'pending';

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Donation amount must be greater than zero.');
        }
        if (strlen($currency) !== 3) {
            throw new \InvalidArgumentException('Currency must be a 3-letter ISO code.');
        }
        if ($paymentMethod === '') {
            throw new \InvalidArgumentException('Payment method is required.');
        }

        $now = now();

        $donation = DB::transaction(function () use (
            $tenantId, $userId, $opportunityId, $givingDayId, $amount,
            $currency, $paymentMethod, $paymentReference, $message,
            $isAnonymous, $status, $now
        ) {
            $donation = VolDonation::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'opportunity_id' => $opportunityId,
                'giving_day_id' => $givingDayId,
                'amount' => $amount,
                'currency' => $currency,
                'payment_method' => $paymentMethod,
                'payment_reference' => $paymentReference,
                'message' => $message,
                'is_anonymous' => $isAnonymous,
                'status' => $status,
                'created_at' => $now,
            ]);

            // Increment giving day raised_amount only for completed donations
            if ($givingDayId !== null && $status === 'completed') {
                VolGivingDay::where('id', $givingDayId)
                    ->increment('raised_amount', $amount);
            }

            return $donation;
        });

        return [
            'id' => $donation->id,
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'opportunity_id' => $opportunityId,
            'giving_day_id' => $givingDayId,
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => $currency,
            'payment_method' => $paymentMethod,
            'payment_reference' => $paymentReference,
            'message' => $message,
            'is_anonymous' => $isAnonymous,
            'status' => $status,
            'created_at' => $now->toDateTimeString(),
        ];
    }

    /**
     * List active giving days for the current tenant.
     */
    public static function getGivingDays(): array
    {
        return VolGivingDay::where('is_active', true)
            ->orderByDesc('start_date')
            ->get([
                'id', 'title', 'description', 'start_date', 'end_date',
                'goal_amount', 'raised_amount', 'is_active', 'created_at',
            ])
            ->toArray();
    }

    /**
     * Get statistics for a giving day.
     *
     * @param int $givingDayId Giving day ID (tenant-scoped)
     * @return array Keys: total_raised, donor_count, goal_amount, progress_percent
     * @throws \RuntimeException If the giving day is not found
     */
    public static function getGivingDayStats(int $givingDayId): array
    {
        $givingDay = VolGivingDay::find($givingDayId);

        if (!$givingDay) {
            throw new \RuntimeException('Giving day not found.');
        }

        $donorCount = VolDonation::where('giving_day_id', $givingDayId)
            ->where('status', '!=', 'refunded')
            ->distinct('user_id')
            ->count('user_id');

        $goalAmount = (float) $givingDay->goal_amount;
        $totalRaised = (float) $givingDay->raised_amount;
        $progress = $goalAmount > 0
            ? min(round(($totalRaised / $goalAmount) * 100, 2), 100.00)
            : 0.00;

        return [
            'total_raised' => number_format($totalRaised, 2, '.', ''),
            'donor_count' => $donorCount,
            'goal_amount' => number_format($goalAmount, 2, '.', ''),
            'progress_percent' => $progress,
        ];
    }

    /**
     * List all giving days (active and inactive) for admin view.
     */
    public static function adminGetGivingDays(): array
    {
        return VolGivingDay::orderByDesc('created_at')
            ->get([
                'id', 'title', 'description', 'start_date', 'end_date',
                'goal_amount', 'raised_amount', 'is_active', 'created_at',
            ])
            ->toArray();
    }

    /**
     * Create a new giving day.
     *
     * @param array $data Must include: title, start_date, end_date, goal_amount. Optional: description.
     * @param int $tenantId
     * @return array|false The created giving day record, or false on failure
     */
    public static function createGivingDay(array $data, int $tenantId): array|false
    {
        $title = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');
        $startDate = trim($data['start_date'] ?? '');
        $endDate = trim($data['end_date'] ?? '');
        $goalAmount = (float) ($data['goal_amount'] ?? 0);

        if ($title === '') {
            throw new \InvalidArgumentException('Title is required.');
        }
        if ($startDate === '' || $endDate === '') {
            throw new \InvalidArgumentException('Start date and end date are required.');
        }
        if (strtotime($endDate) <= strtotime($startDate)) {
            throw new \InvalidArgumentException('End date must be after start date.');
        }
        if ($goalAmount <= 0) {
            throw new \InvalidArgumentException('Goal amount must be greater than zero.');
        }

        $now = now();

        $givingDay = VolGivingDay::create([
            'tenant_id' => $tenantId,
            'title' => $title,
            'description' => $description,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'goal_amount' => $goalAmount,
            'raised_amount' => 0.00,
            'is_active' => 1,
            'created_by' => $data['created_by'] ?? null,
            'created_at' => $now,
        ]);

        return [
            'id' => $givingDay->id,
            'title' => $title,
            'description' => $description,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'goal_amount' => number_format($goalAmount, 2, '.', ''),
            'raised_amount' => '0.00',
            'is_active' => 1,
            'created_at' => $now->toDateTimeString(),
        ];
    }

    /**
     * Export donations as an array of rows for CSV generation.
     *
     * @param int $tenantId
     * @param array|null $filters Optional: opportunity_id, giving_day_id, status
     * @return array Array of associative arrays (one per donation row)
     */
    public static function exportDonations(int $tenantId, ?array $filters): array
    {
        $query = VolDonation::query()
            ->where('tenant_id', $tenantId)
            ->select([
                'id', 'user_id', 'opportunity_id', 'giving_day_id',
                'amount', 'currency', 'payment_method', 'payment_reference',
                'message', 'is_anonymous', 'status', 'created_at',
            ]);

        if (!empty($filters['opportunity_id'])) {
            $query->where('opportunity_id', (int) $filters['opportunity_id']);
        }
        if (!empty($filters['giving_day_id'])) {
            $query->where('giving_day_id', (int) $filters['giving_day_id']);
        }
        if (!empty($filters['status']) && in_array($filters['status'], self::VALID_STATUSES, true)) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('created_at')
            ->get()
            ->map(fn ($row) => $row->toArray())
            ->toArray();
    }

    /**
     * Update a giving day by ID.
     *
     * @param int $givingDayId
     * @param array $data Fields to update: title, description, start_date, end_date, goal_amount, is_active
     * @param int $tenantId
     * @return bool True if a row was updated
     */
    public static function updateGivingDay(int $givingDayId, array $data, int $tenantId): bool
    {
        $givingDay = VolGivingDay::find($givingDayId);

        if (!$givingDay) {
            return false;
        }

        $updates = [];

        if (isset($data['title'])) {
            $updates['title'] = trim($data['title']);
        }
        if (isset($data['description'])) {
            $updates['description'] = trim($data['description']);
        }
        if (isset($data['start_date'])) {
            $updates['start_date'] = trim($data['start_date']);
        }
        if (isset($data['end_date'])) {
            $updates['end_date'] = trim($data['end_date']);
        }
        if (isset($data['goal_amount'])) {
            $goalAmount = (float) $data['goal_amount'];
            if ($goalAmount <= 0) {
                throw new \InvalidArgumentException('Goal amount must be greater than zero.');
            }
            $updates['goal_amount'] = $goalAmount;
        }
        if (isset($data['is_active'])) {
            $updates['is_active'] = $data['is_active'] ? 1 : 0;
        }

        if (empty($updates)) {
            return false;
        }

        return $givingDay->update($updates);
    }
}
