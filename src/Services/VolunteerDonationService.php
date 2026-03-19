<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * VolunteerDonationService
 *
 * Manages monetary donations linked to volunteer opportunities and
 * giving-day fundraising campaigns. Supports cursor-based pagination,
 * giving-day progress tracking, and CSV export for admin reporting.
 *
 * Tables: vol_donations, vol_giving_days
 */
class VolunteerDonationService
{
    /** Default page size for paginated queries */
    private const DEFAULT_LIMIT = 20;

    /** Maximum page size */
    private const MAX_LIMIT = 100;

    /** Allowed donation statuses */
    private const VALID_STATUSES = ['pending', 'completed', 'refunded', 'failed'];

    // ========================================================================
    // Donations
    // ========================================================================

    /**
     * Get paginated donations for the current user.
     *
     * Uses cursor-based pagination (keyset on id DESC). Optionally filters
     * by opportunity_id and/or giving_day_id.
     *
     * @param array $filters Keys: opportunity_id, giving_day_id, cursor, limit
     * @return array{items: array, next_cursor: int|null}
     */
    public static function getDonations(array $filters = []): array
    {
        $tenantId = TenantContext::getId();
        $pdo = Database::getConnection();

        $limit  = max(1, min((int) ($filters['limit'] ?? self::DEFAULT_LIMIT), self::MAX_LIMIT));
        $cursor = isset($filters['cursor']) ? (int) $filters['cursor'] : null;

        $conditions = ['d.tenant_id = ?'];
        $params     = [$tenantId];

        if (!empty($filters['opportunity_id'])) {
            $conditions[] = 'd.opportunity_id = ?';
            $params[]     = (int) $filters['opportunity_id'];
        }

        if (!empty($filters['giving_day_id'])) {
            $conditions[] = 'd.giving_day_id = ?';
            $params[]     = (int) $filters['giving_day_id'];
        }

        if ($cursor !== null) {
            $conditions[] = 'd.id < ?';
            $params[]     = $cursor;
        }

        $where    = implode(' AND ', $conditions);
        $params[] = $limit + 1; // fetch one extra to detect next page

        $stmt = $pdo->prepare(
            "SELECT d.id, d.user_id, d.opportunity_id, d.giving_day_id,
                    d.amount, d.currency, d.payment_method, d.payment_reference,
                    d.message, d.is_anonymous, d.status, d.created_at
             FROM vol_donations d
             WHERE {$where}
             ORDER BY d.id DESC
             LIMIT ?"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $nextCursor = null;
        if (count($rows) > $limit) {
            array_pop($rows);
            $nextCursor = (int) end($rows)['id'];
        }

        return [
            'items'       => $rows,
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
     * @param array $data   Keys: amount, currency (default EUR), payment_method,
     *                      payment_reference, message, is_anonymous,
     *                      opportunity_id, giving_day_id
     * @return array The created donation record
     * @throws \InvalidArgumentException On validation failure
     */
    public static function createDonation(int $userId, array $data): array
    {
        $tenantId = TenantContext::getId();
        $pdo = Database::getConnection();

        $amount           = (float) ($data['amount'] ?? 0);
        $currency         = strtoupper(trim($data['currency'] ?? 'EUR'));
        $paymentMethod    = trim($data['payment_method'] ?? '');
        $paymentReference = trim($data['payment_reference'] ?? '');
        $message          = trim($data['message'] ?? '');
        $isAnonymous      = !empty($data['is_anonymous']) ? 1 : 0;
        $opportunityId    = isset($data['opportunity_id']) ? (int) $data['opportunity_id'] : null;
        $givingDayId      = isset($data['giving_day_id']) ? (int) $data['giving_day_id'] : null;
        $status           = in_array($data['status'] ?? '', ['pending', 'completed'], true)
            ? $data['status']
            : 'pending';

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Donation amount must be greater than zero.');
        }
        if (strlen($currency) !== 3) {
            throw new \InvalidArgumentException('Currency must be a 3-letter ISO code.');
        }
        if ($paymentMethod === '') {
            throw new \InvalidArgumentException('Payment method is required.');
        }

        $now = date('Y-m-d H:i:s');

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "INSERT INTO vol_donations
                 (tenant_id, user_id, opportunity_id, giving_day_id, amount, currency,
                  payment_method, payment_reference, message, is_anonymous, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $tenantId,
                $userId,
                $opportunityId,
                $givingDayId,
                $amount,
                $currency,
                $paymentMethod,
                $paymentReference,
                $message,
                $isAnonymous,
                $status,
                $now,
            ]);

            $donationId = (int) $pdo->lastInsertId();

            // Increment giving day raised_amount only for completed donations
            if ($givingDayId !== null && $status === 'completed') {
                $updateStmt = $pdo->prepare(
                    "UPDATE vol_giving_days
                     SET raised_amount = raised_amount + ?
                     WHERE id = ? AND tenant_id = ?"
                );
                $updateStmt->execute([$amount, $givingDayId, $tenantId]);
            }

            $pdo->commit();
        } catch (\PDOException $e) {
            $pdo->rollBack();
            throw new \RuntimeException('Failed to create donation: ' . $e->getMessage());
        }

        return [
            'id'                => $donationId,
            'tenant_id'         => $tenantId,
            'user_id'           => $userId,
            'opportunity_id'    => $opportunityId,
            'giving_day_id'     => $givingDayId,
            'amount'            => number_format($amount, 2, '.', ''),
            'currency'          => $currency,
            'payment_method'    => $paymentMethod,
            'payment_reference' => $paymentReference,
            'message'           => $message,
            'is_anonymous'      => $isAnonymous,
            'status'            => $status,
            'created_at'        => $now,
        ];
    }

    // ========================================================================
    // Giving Days
    // ========================================================================

    /**
     * List active giving days for the current tenant.
     *
     * @return array Active giving days ordered by start_date descending
     */
    public static function getGivingDays(): array
    {
        $tenantId = TenantContext::getId();
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare(
            "SELECT id, title, description, start_date, end_date,
                    goal_amount, raised_amount, is_active, created_at
             FROM vol_giving_days
             WHERE tenant_id = ? AND is_active = 1
             ORDER BY start_date DESC"
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get statistics for a giving day.
     *
     * Returns total raised, distinct donor count, goal amount, and
     * percentage progress toward the goal (capped at 100%).
     *
     * @param int $givingDayId Giving day ID (tenant-scoped)
     * @return array Keys: total_raised, donor_count, goal_amount, progress_percent
     * @throws \RuntimeException If the giving day is not found
     */
    public static function getGivingDayStats(int $givingDayId): array
    {
        $tenantId = TenantContext::getId();
        $pdo = Database::getConnection();

        // Fetch giving day record
        $stmt = $pdo->prepare(
            "SELECT goal_amount, raised_amount
             FROM vol_giving_days
             WHERE id = ? AND tenant_id = ?"
        );
        $stmt->execute([$givingDayId, $tenantId]);
        $givingDay = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$givingDay) {
            throw new \RuntimeException('Giving day not found.');
        }

        // Count distinct donors (exclude refunded donations)
        $countStmt = $pdo->prepare(
            "SELECT COUNT(DISTINCT user_id) AS donor_count
             FROM vol_donations
             WHERE giving_day_id = ? AND tenant_id = ? AND status != 'refunded'"
        );
        $countStmt->execute([$givingDayId, $tenantId]);
        $donorCount = (int) $countStmt->fetchColumn();

        $goalAmount  = (float) $givingDay['goal_amount'];
        $totalRaised = (float) $givingDay['raised_amount'];
        $progress    = $goalAmount > 0
            ? min(round(($totalRaised / $goalAmount) * 100, 2), 100.00)
            : 0.00;

        return [
            'total_raised'     => number_format($totalRaised, 2, '.', ''),
            'donor_count'      => $donorCount,
            'goal_amount'      => number_format($goalAmount, 2, '.', ''),
            'progress_percent' => $progress,
        ];
    }

    /**
     * List all giving days (active and inactive) for admin view.
     *
     * @return array All giving days ordered by created_at descending
     */
    public static function adminGetGivingDays(): array
    {
        $tenantId = TenantContext::getId();
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare(
            "SELECT id, title, description, start_date, end_date,
                    goal_amount, raised_amount, is_active, created_at
             FROM vol_giving_days
             WHERE tenant_id = ?
             ORDER BY created_at DESC"
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Create a new giving day (admin).
     *
     * @param int   $userId Admin user ID who is creating the giving day
     * @param array $data Keys: title, description, start_date, end_date, goal_amount
     * @return array The created giving day record
     * @throws \InvalidArgumentException On validation failure
     */
    public static function createGivingDay(int $userId, array $data): array
    {
        $tenantId = TenantContext::getId();
        $pdo = Database::getConnection();

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

        $stmt = $pdo->prepare(
            "INSERT INTO vol_giving_days
             (tenant_id, title, description, start_date, end_date,
              goal_amount, raised_amount, is_active, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 0.00, 1, ?, ?)"
        );
        $stmt->execute([$tenantId, $title, $description, $startDate, $endDate, $goalAmount, $userId, $now]);

        $id = (int) $pdo->lastInsertId();

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
     * Update a giving day (admin).
     *
     * Accepts any subset of: title, description, start_date, end_date,
     * goal_amount, is_active.
     *
     * @param int   $id   Giving day ID (tenant-scoped)
     * @param array $data Fields to update
     * @return bool True if a row was updated
     * @throws \InvalidArgumentException On invalid goal_amount
     */
    public static function updateGivingDay(int $id, array $data): bool
    {
        $tenantId = TenantContext::getId();
        $pdo = Database::getConnection();

        $fields = [];
        $params = [];

        if (isset($data['title'])) {
            $fields[] = 'title = ?';
            $params[] = trim($data['title']);
        }
        if (isset($data['description'])) {
            $fields[] = 'description = ?';
            $params[] = trim($data['description']);
        }
        if (isset($data['start_date'])) {
            $fields[] = 'start_date = ?';
            $params[] = trim($data['start_date']);
        }
        if (isset($data['end_date'])) {
            $fields[] = 'end_date = ?';
            $params[] = trim($data['end_date']);
        }
        if (isset($data['goal_amount'])) {
            $goalAmount = (float) $data['goal_amount'];
            if ($goalAmount <= 0) {
                throw new \InvalidArgumentException('Goal amount must be greater than zero.');
            }
            $fields[] = 'goal_amount = ?';
            $params[] = $goalAmount;
        }
        if (isset($data['is_active'])) {
            $fields[] = 'is_active = ?';
            $params[] = $data['is_active'] ? 1 : 0;
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $params[] = $tenantId;

        $sql = "UPDATE vol_giving_days SET " . implode(', ', $fields)
             . " WHERE id = ? AND tenant_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    /**
     * Export donations as a CSV string.
     *
     * Supports filtering by opportunity_id, giving_day_id, and status.
     *
     * @param array $filters Optional: opportunity_id, giving_day_id, status
     * @return string CSV content including header row
     */
    public static function exportDonations(array $filters = []): string
    {
        $tenantId = TenantContext::getId();
        $pdo = Database::getConnection();

        $conditions = ['d.tenant_id = ?'];
        $params     = [$tenantId];

        if (!empty($filters['opportunity_id'])) {
            $conditions[] = 'd.opportunity_id = ?';
            $params[]     = (int) $filters['opportunity_id'];
        }
        if (!empty($filters['giving_day_id'])) {
            $conditions[] = 'd.giving_day_id = ?';
            $params[]     = (int) $filters['giving_day_id'];
        }
        if (!empty($filters['status']) && in_array($filters['status'], self::VALID_STATUSES, true)) {
            $conditions[] = 'd.status = ?';
            $params[]     = $filters['status'];
        }

        $where = implode(' AND ', $conditions);

        $stmt = $pdo->prepare(
            "SELECT d.id, d.user_id, d.opportunity_id, d.giving_day_id,
                    d.amount, d.currency, d.payment_method, d.payment_reference,
                    d.message, d.is_anonymous, d.status, d.created_at
             FROM vol_donations d
             WHERE {$where}
             ORDER BY d.created_at DESC"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $output = fopen('php://temp', 'r+');

        // Header row
        fputcsv($output, [
            'ID', 'User ID', 'Opportunity ID', 'Giving Day ID',
            'Amount', 'Currency', 'Payment Method', 'Payment Reference',
            'Message', 'Anonymous', 'Status', 'Created At',
        ]);

        foreach ($rows as $row) {
            fputcsv($output, [
                $row['id'],
                $row['user_id'],
                $row['opportunity_id'] ?? '',
                $row['giving_day_id'] ?? '',
                $row['amount'],
                $row['currency'],
                $row['payment_method'],
                $row['payment_reference'],
                $row['message'],
                $row['is_anonymous'] ? 'Yes' : 'No',
                $row['status'],
                $row['created_at'],
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
