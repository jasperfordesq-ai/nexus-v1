<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\User;

/**
 * AbuseDetectionService
 *
 * Detects suspicious patterns in timebanking activity.
 * Creates alerts for admin review when anomalies are found.
 */
class AbuseDetectionService
{
    /**
     * Configuration thresholds (can be overridden per tenant)
     */
    const LARGE_TRANSFER_THRESHOLD = 50;    // Credits
    const HIGH_VELOCITY_THRESHOLD = 10;     // Transactions per hour
    const CIRCULAR_WINDOW_HOURS = 24;       // Hours to check for circular transfers
    const INACTIVE_DAYS_THRESHOLD = 90;     // Days of inactivity
    const HIGH_BALANCE_THRESHOLD = 10;      // Credits for inactive high balance

    /**
     * Run all detection checks
     *
     * @return array Summary of alerts created
     */
    public static function runAllChecks()
    {
        $results = [
            'large_transfers' => self::checkLargeTransfers(),
            'high_velocity' => self::checkHighVelocity(),
            'circular_transfers' => self::checkCircularTransfers(),
            'inactive_high_balance' => self::checkInactiveHighBalances(),
        ];

        return $results;
    }

    /**
     * Check for large transfers (amount > threshold)
     *
     * @param float|null $threshold Override default threshold
     * @return int Number of alerts created
     */
    public static function checkLargeTransfers($threshold = null)
    {
        $tenantId = TenantContext::getId();
        $threshold = $threshold ?? self::LARGE_TRANSFER_THRESHOLD;

        // Find transactions larger than threshold in last 24 hours that haven't been flagged
        $largeTransactions = Database::query(
            "SELECT t.*, CONCAT(s.first_name, ' ', s.last_name) as sender_name,
                    CONCAT(r.first_name, ' ', r.last_name) as receiver_name
             FROM transactions t
             JOIN users s ON t.sender_id = s.id
             JOIN users r ON t.receiver_id = r.id
             WHERE t.tenant_id = ?
             AND t.amount >= ?
             AND t.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             AND t.id NOT IN (
                 SELECT JSON_UNQUOTE(JSON_EXTRACT(details, '$.transaction_id'))
                 FROM abuse_alerts WHERE tenant_id = ? AND alert_type = 'large_transfer'
             )",
            [$tenantId, $threshold, $tenantId]
        )->fetchAll();

        $alertsCreated = 0;
        foreach ($largeTransactions as $txn) {
            self::createAlert(
                'large_transfer',
                $txn['amount'] >= $threshold * 2 ? 'high' : 'medium',
                $txn['sender_id'],
                $txn['id'],
                [
                    'transaction_id' => $txn['id'],
                    'amount' => $txn['amount'],
                    'sender_name' => $txn['sender_name'],
                    'receiver_name' => $txn['receiver_name'],
                    'threshold' => $threshold,
                ]
            );
            $alertsCreated++;
        }

        return $alertsCreated;
    }

    /**
     * Check for high velocity trading (many transactions in short time)
     *
     * @param int|null $threshold Override default threshold
     * @return int Number of alerts created
     */
    public static function checkHighVelocity($threshold = null)
    {
        $tenantId = TenantContext::getId();
        $threshold = $threshold ?? self::HIGH_VELOCITY_THRESHOLD;

        // Find users with more than threshold transactions in the last hour
        $highVelocityUsers = Database::query(
            "SELECT user_id, transaction_count FROM (
                SELECT sender_id as user_id, COUNT(*) as transaction_count
                FROM transactions
                WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                GROUP BY sender_id
                HAVING transaction_count >= ?
                UNION
                SELECT receiver_id as user_id, COUNT(*) as transaction_count
                FROM transactions
                WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                GROUP BY receiver_id
                HAVING transaction_count >= ?
             ) as velocity
             WHERE user_id NOT IN (
                 SELECT JSON_UNQUOTE(JSON_EXTRACT(details, '$.user_id'))
                 FROM abuse_alerts
                 WHERE tenant_id = ? AND alert_type = 'high_velocity'
                 AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
             )",
            [$tenantId, $threshold, $tenantId, $threshold, $tenantId]
        )->fetchAll();

        $alertsCreated = 0;
        foreach ($highVelocityUsers as $user) {
            $userData = User::findById($user['user_id']);
            $userName = $userData ? "{$userData['first_name']} {$userData['last_name']}" : 'Unknown';

            self::createAlert(
                'high_velocity',
                $user['transaction_count'] >= $threshold * 2 ? 'high' : 'medium',
                $user['user_id'],
                null,
                [
                    'user_id' => $user['user_id'],
                    'user_name' => $userName,
                    'transaction_count' => $user['transaction_count'],
                    'threshold' => $threshold,
                    'time_window' => '1 hour',
                ]
            );
            $alertsCreated++;
        }

        return $alertsCreated;
    }

    /**
     * Check for circular transfers (A→B→A within window)
     *
     * @param int|null $windowHours Override default window
     * @return int Number of alerts created
     */
    public static function checkCircularTransfers($windowHours = null)
    {
        $tenantId = TenantContext::getId();
        $windowHours = $windowHours ?? self::CIRCULAR_WINDOW_HOURS;

        // Find pairs where A sent to B and B sent back to A within the window
        $circularTransfers = Database::query(
            "SELECT t1.sender_id as user_a, t1.receiver_id as user_b,
                    t1.id as first_txn_id, t2.id as return_txn_id,
                    t1.amount as first_amount, t2.amount as return_amount,
                    t1.created_at as first_time, t2.created_at as return_time
             FROM transactions t1
             JOIN transactions t2 ON t1.sender_id = t2.receiver_id
                                  AND t1.receiver_id = t2.sender_id
                                  AND t2.created_at > t1.created_at
             WHERE t1.tenant_id = ?
             AND t1.created_at >= DATE_SUB(NOW(), INTERVAL $windowHours HOUR)
             AND t2.created_at >= DATE_SUB(NOW(), INTERVAL $windowHours HOUR)
             AND CONCAT(LEAST(t1.id, t2.id), '-', GREATEST(t1.id, t2.id)) NOT IN (
                 SELECT JSON_UNQUOTE(JSON_EXTRACT(details, '$.txn_pair'))
                 FROM abuse_alerts WHERE tenant_id = ? AND alert_type = 'circular_transfer'
             )",
            [$tenantId, $tenantId]
        )->fetchAll();

        $alertsCreated = 0;
        foreach ($circularTransfers as $circular) {
            $userA = User::findById($circular['user_a']);
            $userB = User::findById($circular['user_b']);

            self::createAlert(
                'circular_transfer',
                'medium',
                $circular['user_a'],
                $circular['first_txn_id'],
                [
                    'user_a_id' => $circular['user_a'],
                    'user_a_name' => $userA ? "{$userA['first_name']} {$userA['last_name']}" : 'Unknown',
                    'user_b_id' => $circular['user_b'],
                    'user_b_name' => $userB ? "{$userB['first_name']} {$userB['last_name']}" : 'Unknown',
                    'first_amount' => $circular['first_amount'],
                    'return_amount' => $circular['return_amount'],
                    'first_txn_id' => $circular['first_txn_id'],
                    'return_txn_id' => $circular['return_txn_id'],
                    'txn_pair' => min($circular['first_txn_id'], $circular['return_txn_id']) . '-' .
                                  max($circular['first_txn_id'], $circular['return_txn_id']),
                    'window_hours' => $windowHours,
                ]
            );
            $alertsCreated++;
        }

        return $alertsCreated;
    }

    /**
     * Check for inactive users with high balances (potential credit hoarding)
     *
     * @return int Number of alerts created
     */
    public static function checkInactiveHighBalances()
    {
        $tenantId = TenantContext::getId();
        $inactiveDays = self::INACTIVE_DAYS_THRESHOLD;
        $balanceThreshold = self::HIGH_BALANCE_THRESHOLD;

        // Find users with high balance and no recent activity
        $inactiveUsers = Database::query(
            "SELECT u.id, u.first_name, u.last_name, u.balance,
                    MAX(t.created_at) as last_transaction
             FROM users u
             LEFT JOIN transactions t ON (t.sender_id = u.id OR t.receiver_id = u.id)
             WHERE u.tenant_id = ? AND u.balance >= ?
             AND u.id NOT IN (
                 SELECT JSON_UNQUOTE(JSON_EXTRACT(details, '$.user_id'))
                 FROM abuse_alerts WHERE tenant_id = ? AND alert_type = 'inactive_high_balance'
                 AND status IN ('new', 'reviewing')
             )
             GROUP BY u.id
             HAVING last_transaction IS NULL
                OR last_transaction < DATE_SUB(NOW(), INTERVAL $inactiveDays DAY)",
            [$tenantId, $balanceThreshold, $tenantId]
        )->fetchAll();

        $alertsCreated = 0;
        foreach ($inactiveUsers as $user) {
            self::createAlert(
                'inactive_high_balance',
                'low',
                $user['id'],
                null,
                [
                    'user_id' => $user['id'],
                    'user_name' => "{$user['first_name']} {$user['last_name']}",
                    'balance' => $user['balance'],
                    'last_transaction' => $user['last_transaction'],
                    'inactive_days' => $inactiveDays,
                ]
            );
            $alertsCreated++;
        }

        return $alertsCreated;
    }

    /**
     * Create an abuse alert
     *
     * @param string $alertType Type of alert
     * @param string $severity low, medium, high, critical
     * @param int|null $userId Related user
     * @param int|null $transactionId Related transaction
     * @param array $details Additional details as JSON
     * @return int Alert ID
     */
    public static function createAlert($alertType, $severity, $userId = null, $transactionId = null, $details = [])
    {
        $tenantId = TenantContext::getId();

        Database::query(
            "INSERT INTO abuse_alerts (tenant_id, alert_type, severity, user_id, transaction_id, details)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$tenantId, $alertType, $severity, $userId, $transactionId, json_encode($details)]
        );

        return Database::getInstance()->lastInsertId();
    }

    /**
     * Get alerts list
     *
     * @param string|null $status Filter by status
     * @param int $limit Number of alerts
     * @param int $offset Pagination offset
     * @return array
     */
    public static function getAlerts($status = null, $limit = 50, $offset = 0)
    {
        $tenantId = TenantContext::getId();
        $limit = (int) $limit;
        $offset = (int) $offset;

        $sql = "SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) as user_name
                FROM abuse_alerts a
                LEFT JOIN users u ON a.user_id = u.id
                WHERE a.tenant_id = ?";
        $params = [$tenantId];

        if ($status) {
            $sql .= " AND a.status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY
                    CASE a.severity
                        WHEN 'critical' THEN 1
                        WHEN 'high' THEN 2
                        WHEN 'medium' THEN 3
                        WHEN 'low' THEN 4
                    END,
                    a.created_at DESC
                  LIMIT $limit OFFSET $offset";

        return Database::query($sql, $params)->fetchAll();
    }

    /**
     * Get alert by ID
     *
     * @param int $alertId
     * @return array|false
     */
    public static function getAlert($alertId)
    {
        $tenantId = TenantContext::getId();

        return Database::query(
            "SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) as user_name,
                    CONCAT(r.first_name, ' ', r.last_name) as resolved_by_name
             FROM abuse_alerts a
             LEFT JOIN users u ON a.user_id = u.id
             LEFT JOIN users r ON a.resolved_by = r.id
             WHERE a.id = ? AND a.tenant_id = ?",
            [$alertId, $tenantId]
        )->fetch();
    }

    /**
     * Update alert status
     *
     * @param int $alertId
     * @param string $status new, reviewing, resolved, dismissed
     * @param int|null $resolvedBy User ID resolving
     * @param string|null $notes Resolution notes
     * @return bool
     */
    public static function updateAlertStatus($alertId, $status, $resolvedBy = null, $notes = null)
    {
        $tenantId = TenantContext::getId();

        $resolvedAt = in_array($status, ['resolved', 'dismissed']) ? 'NOW()' : 'NULL';

        Database::query(
            "UPDATE abuse_alerts
             SET status = ?, resolved_by = ?, resolved_at = $resolvedAt, resolution_notes = ?
             WHERE id = ? AND tenant_id = ?",
            [$status, $resolvedBy, $notes, $alertId, $tenantId]
        );

        return true;
    }

    /**
     * Get alert counts by status
     *
     * @return array
     */
    public static function getAlertCounts()
    {
        $tenantId = TenantContext::getId();

        $result = Database::query(
            "SELECT status, COUNT(*) as count FROM abuse_alerts
             WHERE tenant_id = ?
             GROUP BY status",
            [$tenantId]
        )->fetchAll();

        $counts = ['new' => 0, 'reviewing' => 0, 'resolved' => 0, 'dismissed' => 0];
        foreach ($result as $row) {
            $counts[$row['status']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Get alert counts by type
     *
     * @return array
     */
    public static function getAlertCountsByType()
    {
        $tenantId = TenantContext::getId();

        $result = Database::query(
            "SELECT alert_type, COUNT(*) as count FROM abuse_alerts
             WHERE tenant_id = ? AND status IN ('new', 'reviewing')
             GROUP BY alert_type",
            [$tenantId]
        )->fetchAll();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['alert_type']] = (int) $row['count'];
        }

        return $counts;
    }
}
