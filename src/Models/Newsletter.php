<?php

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class Newsletter
{
    /**
     * Create a new newsletter campaign
     */
    public static function create($data)
    {
        $tenantId = TenantContext::getId();
        $sql = "INSERT INTO newsletters (tenant_id, subject, preview_text, content, status, scheduled_at, created_by,
                is_recurring, recurring_frequency, recurring_day, recurring_day_of_month, recurring_time, recurring_end_date, template_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        Database::query($sql, [
            $tenantId,
            $data['subject'],
            $data['preview_text'] ?? null,
            $data['content'],
            $data['status'] ?? 'draft',
            $data['scheduled_at'] ?? null,
            $data['created_by'],
            $data['is_recurring'] ?? 0,
            $data['recurring_frequency'] ?? null,
            $data['recurring_day'] ?? null,
            $data['recurring_day_of_month'] ?? null,
            $data['recurring_time'] ?? null,
            $data['recurring_end_date'] ?? null,
            $data['template_id'] ?? null
        ]);

        return Database::getConnection()->lastInsertId();
    }

    /**
     * Find newsletter by ID (tenant-scoped)
     */
    public static function findById($id)
    {
        $tenantId = TenantContext::getId();
        $sql = "SELECT n.*, u.first_name, u.last_name,
                CONCAT(u.first_name, ' ', u.last_name) as author_name
                FROM newsletters n
                LEFT JOIN users u ON n.created_by = u.id
                WHERE n.id = ? AND n.tenant_id = ?";
        return Database::query($sql, [$id, $tenantId])->fetch();
    }

    /**
     * Get all newsletters for current tenant
     */
    public static function getAll($limit = 50, $offset = 0)
    {
        $tenantId = TenantContext::getId();
        $limit = (int)$limit;
        $offset = (int)$offset;

        $sql = "SELECT n.*, u.first_name, u.last_name,
                CONCAT(u.first_name, ' ', u.last_name) as author_name
                FROM newsletters n
                LEFT JOIN users u ON n.created_by = u.id
                WHERE n.tenant_id = ?
                ORDER BY n.created_at DESC
                LIMIT $limit OFFSET $offset";

        return Database::query($sql, [$tenantId])->fetchAll();
    }

    /**
     * Get all sent newsletters for analytics
     */
    public static function getAllSent()
    {
        $tenantId = TenantContext::getId();

        $sql = "SELECT n.*, u.first_name, u.last_name,
                CONCAT(u.first_name, ' ', u.last_name) as author_name
                FROM newsletters n
                LEFT JOIN users u ON n.created_by = u.id
                WHERE n.tenant_id = ? AND n.status = 'sent'
                ORDER BY n.sent_at DESC";

        return Database::query($sql, [$tenantId])->fetchAll();
    }

    /**
     * Count newsletters
     */
    public static function count($status = null)
    {
        $tenantId = TenantContext::getId();

        if ($status) {
            $sql = "SELECT COUNT(*) as total FROM newsletters WHERE tenant_id = ? AND status = ?";
            return Database::query($sql, [$tenantId, $status])->fetch()['total'];
        }

        $sql = "SELECT COUNT(*) as total FROM newsletters WHERE tenant_id = ?";
        return Database::query($sql, [$tenantId])->fetch()['total'];
    }

    /**
     * Update newsletter
     */
    public static function update($id, $data)
    {
        $tenantId = TenantContext::getId();
        $fields = [];
        $params = [];

        $allowedFields = ['subject', 'preview_text', 'content', 'status', 'scheduled_at',
                          'total_recipients', 'total_sent', 'total_failed', 'sent_at',
                          'target_audience', 'segment_id', 'ab_test_enabled', 'subject_b',
                          'ab_split_percentage', 'ab_winner', 'ab_winner_metric',
                          'ab_auto_select_winner', 'ab_auto_select_after_hours',
                          'target_counties', 'target_towns', 'target_groups',
                          'is_recurring', 'recurring_frequency', 'recurring_day',
                          'recurring_day_of_month', 'recurring_time', 'recurring_end_date',
                          'last_recurring_sent', 'template_id'];

        // Data Loss Prevention: Fields that should not be overwritten with empty values
        $preserveIfEmpty = ['subject', 'content'];

        foreach ($data as $key => $value) {
            if (!in_array($key, $allowedFields)) {
                continue;
            }

            // Don't overwrite critical fields with empty values
            if (in_array($key, $preserveIfEmpty) && ($value === '' || $value === null)) {
                continue;
            }

            $fields[] = "$key = ?";
            $params[] = $value;
        }

        if (empty($fields)) return false;

        $sql = "UPDATE newsletters SET " . implode(', ', $fields) . " WHERE id = ? AND tenant_id = ?";
        $params[] = $id;
        $params[] = $tenantId;

        Database::query($sql, $params);
        return true;
    }

    /**
     * Delete newsletter
     */
    public static function delete($id)
    {
        $tenantId = TenantContext::getId();
        $sql = "DELETE FROM newsletters WHERE id = ? AND tenant_id = ?";
        Database::query($sql, [$id, $tenantId]);
    }

    /**
     * Get scheduled newsletters ready to send
     */
    public static function getScheduledReady()
    {
        $sql = "SELECT * FROM newsletters
                WHERE status = 'scheduled'
                AND scheduled_at <= NOW()
                ORDER BY scheduled_at ASC";
        return Database::query($sql)->fetchAll();
    }

    /**
     * Get recurring newsletters that are due to be sent
     */
    public static function getRecurringReady()
    {
        $sql = "SELECT * FROM newsletters
                WHERE is_recurring = 1
                AND status IN ('draft', 'sent')
                AND (recurring_end_date IS NULL OR recurring_end_date >= CURDATE())
                ORDER BY id ASC";

        $newsletters = Database::query($sql)->fetchAll();
        $ready = [];

        foreach ($newsletters as $newsletter) {
            if (self::isRecurringDue($newsletter)) {
                $ready[] = $newsletter;
            }
        }

        return $ready;
    }

    /**
     * Check if a recurring newsletter is due to be sent
     */
    public static function isRecurringDue($newsletter)
    {
        if (empty($newsletter['is_recurring']) || empty($newsletter['recurring_frequency'])) {
            return false;
        }

        $now = new \DateTime();
        $recurringTime = $newsletter['recurring_time'] ?? '09:00:00';
        $lastSent = $newsletter['last_recurring_sent'] ? new \DateTime($newsletter['last_recurring_sent']) : null;

        // Parse preferred send time
        $sendHour = (int) substr($recurringTime, 0, 2);
        $sendMinute = (int) substr($recurringTime, 3, 2);

        // Check if we're past the send time today
        $currentHour = (int) $now->format('H');
        $currentMinute = (int) $now->format('i');
        $isPastSendTime = ($currentHour > $sendHour) || ($currentHour == $sendHour && $currentMinute >= $sendMinute);

        if (!$isPastSendTime) {
            return false; // Not yet time to send today
        }

        $frequency = $newsletter['recurring_frequency'];
        $today = $now->format('Y-m-d');

        // If already sent today, skip
        if ($lastSent && $lastSent->format('Y-m-d') === $today) {
            return false;
        }

        switch ($frequency) {
            case 'daily':
                return true;

            case 'weekly':
            case 'biweekly':
                $dayOfWeek = strtolower($now->format('D')); // mon, tue, etc.
                $targetDay = $newsletter['recurring_day'] ?? 'mon';

                if ($dayOfWeek !== $targetDay) {
                    return false;
                }

                if ($frequency === 'biweekly' && $lastSent) {
                    // Check if it's been at least 13 days since last send
                    $daysSinceLastSent = $lastSent->diff($now)->days;
                    if ($daysSinceLastSent < 13) {
                        return false;
                    }
                }
                return true;

            case 'monthly':
                $dayOfMonth = (int) $now->format('j');
                $targetDay = $newsletter['recurring_day_of_month'] ?? '1';

                if ($targetDay === 'last') {
                    // Last day of month
                    $lastDayOfMonth = (int) $now->format('t');
                    if ($dayOfMonth !== $lastDayOfMonth) {
                        return false;
                    }
                } else {
                    if ($dayOfMonth !== (int) $targetDay) {
                        return false;
                    }
                }

                // Check if already sent this month
                if ($lastSent && $lastSent->format('Y-m') === $now->format('Y-m')) {
                    return false;
                }
                return true;
        }

        return false;
    }

    /**
     * Mark a recurring newsletter as sent (update last_recurring_sent)
     */
    public static function markRecurringSent($id)
    {
        $sql = "UPDATE newsletters SET last_recurring_sent = NOW() WHERE id = ?";
        Database::query($sql, [$id]);
    }

    /**
     * Queue recipients for a newsletter (legacy - for backwards compatibility)
     */
    public static function queueRecipients($newsletterId, $users)
    {
        $sql = "INSERT INTO newsletter_queue (newsletter_id, user_id, email) VALUES (?, ?, ?)";
        $count = 0;

        foreach ($users as $user) {
            try {
                Database::query($sql, [$newsletterId, $user['id'], $user['email']]);
                $count++;
            } catch (\Exception $e) {
                // Likely duplicate, skip
                error_log("Queue insert error: " . $e->getMessage());
            }
        }

        return $count;
    }

    /**
     * Queue recipients with unsubscribe tokens
     */
    public static function queueRecipientsWithTokens($newsletterId, $recipients)
    {
        $sql = "INSERT INTO newsletter_queue (newsletter_id, user_id, email, unsubscribe_token) VALUES (?, ?, ?, ?)";
        $count = 0;

        foreach ($recipients as $recipient) {
            try {
                $token = $recipient['unsubscribe_token'] ?? bin2hex(random_bytes(32));
                Database::query($sql, [
                    $newsletterId,
                    $recipient['user_id'] ?? null,
                    $recipient['email'],
                    $token
                ]);
                $count++;
            } catch (\Exception $e) {
                // Likely duplicate, skip
                error_log("Queue insert error: " . $e->getMessage());
            }
        }

        return $count;
    }

    /**
     * Get pending queue items for a newsletter
     */
    public static function getQueuePending($newsletterId, $limit = 50)
    {
        $limit = (int)$limit;
        $sql = "SELECT * FROM newsletter_queue
                WHERE newsletter_id = ? AND status = 'pending'
                ORDER BY id ASC
                LIMIT $limit";
        return Database::query($sql, [$newsletterId])->fetchAll();
    }

    /**
     * Update queue item status
     */
    public static function updateQueueItem($queueId, $status, $errorMessage = null)
    {
        $sentAt = ($status === 'sent') ? date('Y-m-d H:i:s') : null;
        $sql = "UPDATE newsletter_queue SET status = ?, error_message = ?, sent_at = ? WHERE id = ?";
        Database::query($sql, [$status, $errorMessage, $sentAt, $queueId]);
    }

    /**
     * Update queue item tracking token
     */
    public static function updateQueueTrackingToken($queueId, $trackingToken)
    {
        $sql = "UPDATE newsletter_queue SET tracking_token = ? WHERE id = ?";
        Database::query($sql, [$trackingToken, $queueId]);
    }

    /**
     * Get queue statistics for a newsletter
     */
    public static function getQueueStats($newsletterId)
    {
        $sql = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                FROM newsletter_queue
                WHERE newsletter_id = ?";
        return Database::query($sql, [$newsletterId])->fetch();
    }

    /**
     * Clear queue for a newsletter
     */
    public static function clearQueue($newsletterId)
    {
        $sql = "DELETE FROM newsletter_queue WHERE newsletter_id = ?";
        Database::query($sql, [$newsletterId]);
    }

    /**
     * Queue recipients with A/B variant assignment
     */
    public static function queueRecipientsWithVariants($newsletterId, $recipients, $splitPercentage = 50)
    {
        $sql = "INSERT INTO newsletter_queue (newsletter_id, user_id, email, unsubscribe_token, ab_variant) VALUES (?, ?, ?, ?, ?)";
        $count = 0;
        $variantACount = 0;
        $variantBCount = 0;
        $total = count($recipients);

        // Calculate how many should be in variant A
        $targetVariantA = (int) round($total * ($splitPercentage / 100));

        foreach ($recipients as $recipient) {
            try {
                $token = $recipient['unsubscribe_token'] ?? bin2hex(random_bytes(32));

                // Assign variant: first N go to A, rest to B
                $variant = ($variantACount < $targetVariantA) ? 'A' : 'B';

                Database::query($sql, [
                    $newsletterId,
                    $recipient['user_id'] ?? null,
                    $recipient['email'],
                    $token,
                    $variant
                ]);

                if ($variant === 'A') {
                    $variantACount++;
                } else {
                    $variantBCount++;
                }
                $count++;
            } catch (\Exception $e) {
                error_log("Queue insert error: " . $e->getMessage());
            }
        }

        return [
            'total' => $count,
            'variant_a' => $variantACount,
            'variant_b' => $variantBCount
        ];
    }

    /**
     * Get queue stats by variant for A/B testing
     */
    public static function getQueueStatsByVariant($newsletterId)
    {
        $sql = "SELECT
                    ab_variant,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                FROM newsletter_queue
                WHERE newsletter_id = ?
                GROUP BY ab_variant";
        return Database::query($sql, [$newsletterId])->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get A/B test stats from aggregated table
     */
    public static function getABStats($newsletterId)
    {
        $sql = "SELECT * FROM newsletter_ab_stats WHERE newsletter_id = ? ORDER BY variant";
        return Database::query($sql, [$newsletterId])->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Initialize or update A/B stats for a variant
     */
    public static function updateABStats($newsletterId, $variant, $stats)
    {
        $tenantId = TenantContext::getId();
        $sql = "INSERT INTO newsletter_ab_stats
                (tenant_id, newsletter_id, variant, total_sent, total_opens, unique_opens, total_clicks, unique_clicks)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    total_sent = VALUES(total_sent),
                    total_opens = VALUES(total_opens),
                    unique_opens = VALUES(unique_opens),
                    total_clicks = VALUES(total_clicks),
                    unique_clicks = VALUES(unique_clicks)";

        Database::query($sql, [
            $tenantId,
            $newsletterId,
            $variant,
            $stats['total_sent'] ?? 0,
            $stats['total_opens'] ?? 0,
            $stats['unique_opens'] ?? 0,
            $stats['total_clicks'] ?? 0,
            $stats['unique_clicks'] ?? 0
        ]);
    }

    /**
     * Set the A/B test winner
     */
    public static function setABWinner($newsletterId, $winner)
    {
        $tenantId = TenantContext::getId();
        $sql = "UPDATE newsletters SET ab_winner = ? WHERE id = ? AND tenant_id = ?";
        Database::query($sql, [$winner, $newsletterId, $tenantId]);
    }

    /**
     * Get variant for a queue item by tracking token
     */
    public static function getVariantByTrackingToken($trackingToken)
    {
        $sql = "SELECT ab_variant FROM newsletter_queue WHERE tracking_token = ? LIMIT 1";
        $result = Database::query($sql, [$trackingToken])->fetch();
        return $result ? $result['ab_variant'] : null;
    }
}
