<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * VolunteerDonationService — Laravel DI wrapper for legacy \Nexus\Services\VolunteerDonationService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class VolunteerDonationService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy VolunteerDonationService::getDonations().
     */
    public function getDonations(array $filters = []): array
    {
        return \Nexus\Services\VolunteerDonationService::getDonations($filters);
    }

    /**
     * Delegates to legacy VolunteerDonationService::createDonation().
     */
    public function createDonation(int $userId, array $data): array
    {
        return \Nexus\Services\VolunteerDonationService::createDonation($userId, $data);
    }

    /**
     * Delegates to legacy VolunteerDonationService::getGivingDays().
     */
    public function getGivingDays(): array
    {
        return \Nexus\Services\VolunteerDonationService::getGivingDays();
    }

    /**
     * Delegates to legacy VolunteerDonationService::getGivingDayStats().
     */
    public function getGivingDayStats(int $givingDayId): array
    {
        return \Nexus\Services\VolunteerDonationService::getGivingDayStats($givingDayId);
    }

    /**
     * Delegates to legacy VolunteerDonationService::adminGetGivingDays().
     */
    public function adminGetGivingDays(): array
    {
        return \Nexus\Services\VolunteerDonationService::adminGetGivingDays();
    }

    /**
     * Valid donation statuses for filtering.
     */
    private const VALID_STATUSES = ['pending', 'completed', 'refunded', 'failed'];

    /**
     * Create a new giving day.
     *
     * @param array $data Must include: title, start_date, end_date, goal_amount. Optional: description.
     * @param int $tenantId
     * @return array|false The created giving day record, or false on failure
     */
    public function createGivingDay(array $data, int $tenantId): array|false
    {
        $title       = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');
        $startDate   = trim($data['start_date'] ?? '');
        $endDate     = trim($data['end_date'] ?? '');
        $goalAmount  = (float) ($data['goal_amount'] ?? 0);

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

        $now = date('Y-m-d H:i:s');
        $createdBy = $data['created_by'] ?? null;

        $id = DB::table('vol_giving_days')->insertGetId([
            'tenant_id'     => $tenantId,
            'title'         => $title,
            'description'   => $description,
            'start_date'    => $startDate,
            'end_date'      => $endDate,
            'goal_amount'   => $goalAmount,
            'raised_amount' => 0.00,
            'is_active'     => 1,
            'created_by'    => $createdBy,
            'created_at'    => $now,
        ]);

        return [
            'id'            => $id,
            'title'         => $title,
            'description'   => $description,
            'start_date'    => $startDate,
            'end_date'      => $endDate,
            'goal_amount'   => number_format($goalAmount, 2, '.', ''),
            'raised_amount' => '0.00',
            'is_active'     => 1,
            'created_at'    => $now,
        ];
    }

    /**
     * Export donations as an array of rows for CSV generation.
     *
     * @param int $tenantId
     * @param array|null $filters Optional: opportunity_id, giving_day_id, status
     * @return array Array of associative arrays (one per donation row)
     */
    public function exportDonations(int $tenantId, ?array $filters): array
    {
        $sql = "
            SELECT d.id, d.user_id, d.opportunity_id, d.giving_day_id,
                   d.amount, d.currency, d.payment_method, d.payment_reference,
                   d.message, d.is_anonymous, d.status, d.created_at
            FROM vol_donations d
            WHERE d.tenant_id = ?
        ";
        $params = [$tenantId];

        if (!empty($filters['opportunity_id'])) {
            $sql .= " AND d.opportunity_id = ?";
            $params[] = (int) $filters['opportunity_id'];
        }
        if (!empty($filters['giving_day_id'])) {
            $sql .= " AND d.giving_day_id = ?";
            $params[] = (int) $filters['giving_day_id'];
        }
        if (!empty($filters['status']) && in_array($filters['status'], self::VALID_STATUSES, true)) {
            $sql .= " AND d.status = ?";
            $params[] = $filters['status'];
        }

        $sql .= " ORDER BY d.created_at DESC";

        $results = DB::select($sql, $params);

        return array_map(fn ($row) => (array) $row, $results);
    }

    /**
     * Update a giving day by ID.
     *
     * @param int $givingDayId
     * @param array $data Fields to update: title, description, start_date, end_date, goal_amount, is_active
     * @param int $tenantId
     * @return bool True if a row was updated
     */
    public function updateGivingDay(int $givingDayId, array $data, int $tenantId): bool
    {
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

        $affected = DB::table('vol_giving_days')
            ->where('id', $givingDayId)
            ->where('tenant_id', $tenantId)
            ->update($updates);

        return $affected > 0;
    }
}
