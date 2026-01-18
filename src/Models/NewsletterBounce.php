<?php

namespace Nexus\Models;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

class NewsletterBounce
{
    const BOUNCE_HARD = 'hard';
    const BOUNCE_SOFT = 'soft';
    const BOUNCE_COMPLAINT = 'complaint';

    const MAX_SOFT_BOUNCES = 3; // Auto-suppress after this many soft bounces

    /**
     * Record a bounce
     */
    public static function record($email, $type, $newsletterId = null, $queueId = null, $reason = null, $code = null)
    {
        $tenantId = TenantContext::getId();

        // Record the bounce
        $sql = "INSERT INTO newsletter_bounces
                (tenant_id, email, newsletter_id, queue_id, bounce_type, bounce_reason, bounce_code)
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        Database::query($sql, [
            $tenantId,
            $email,
            $newsletterId,
            $queueId,
            $type,
            $reason,
            $code
        ]);

        // Handle suppression based on bounce type
        if ($type === self::BOUNCE_HARD) {
            self::addToSuppressionList($email, 'hard_bounce');
        } elseif ($type === self::BOUNCE_COMPLAINT) {
            self::addToSuppressionList($email, 'complaint');
        } elseif ($type === self::BOUNCE_SOFT) {
            // Check if we've hit the soft bounce threshold
            $softBounceCount = self::getSoftBounceCount($email);
            if ($softBounceCount >= self::MAX_SOFT_BOUNCES) {
                self::addToSuppressionList($email, 'repeated_soft_bounce', $softBounceCount);
            }
        }

        return true;
    }

    /**
     * Get soft bounce count for an email
     */
    public static function getSoftBounceCount($email)
    {
        $tenantId = TenantContext::getId();

        // Count soft bounces in the last 30 days
        $sql = "SELECT COUNT(*) as count FROM newsletter_bounces
                WHERE tenant_id = ? AND email = ? AND bounce_type = 'soft'
                AND bounced_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";

        $result = Database::query($sql, [$tenantId, $email])->fetch();
        return (int) $result['count'];
    }

    /**
     * Add email to suppression list
     */
    public static function addToSuppressionList($email, $reason, $bounceCount = 1, $expiresAt = null)
    {
        $tenantId = TenantContext::getId();

        // Use INSERT ... ON DUPLICATE KEY UPDATE
        $sql = "INSERT INTO newsletter_suppression_list (tenant_id, email, reason, bounce_count, expires_at)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                reason = VALUES(reason),
                bounce_count = VALUES(bounce_count),
                suppressed_at = CURRENT_TIMESTAMP,
                expires_at = VALUES(expires_at)";

        return Database::query($sql, [$tenantId, $email, $reason, $bounceCount, $expiresAt]);
    }

    /**
     * Check if email is suppressed
     */
    public static function isSuppressed($email)
    {
        $tenantId = TenantContext::getId();

        $sql = "SELECT id FROM newsletter_suppression_list
                WHERE tenant_id = ? AND email = ?
                AND (expires_at IS NULL OR expires_at > NOW())";

        $result = Database::query($sql, [$tenantId, $email])->fetch();
        return !empty($result);
    }

    /**
     * Remove from suppression list
     */
    public static function removeFromSuppressionList($email)
    {
        $tenantId = TenantContext::getId();

        $sql = "DELETE FROM newsletter_suppression_list WHERE tenant_id = ? AND email = ?";
        return Database::query($sql, [$tenantId, $email]);
    }

    /**
     * Get suppression list
     */
    public static function getSuppressionList($limit = 100, $offset = 0)
    {
        $tenantId = TenantContext::getId();
        $limit = (int) $limit;
        $offset = (int) $offset;

        $sql = "SELECT * FROM newsletter_suppression_list
                WHERE tenant_id = ? AND (expires_at IS NULL OR expires_at > NOW())
                ORDER BY suppressed_at DESC
                LIMIT $limit OFFSET $offset";

        return Database::query($sql, [$tenantId])->fetchAll();
    }

    /**
     * Get suppression list count
     */
    public static function getSuppressionCount()
    {
        $tenantId = TenantContext::getId();

        $sql = "SELECT COUNT(*) as total FROM newsletter_suppression_list
                WHERE tenant_id = ? AND (expires_at IS NULL OR expires_at > NOW())";

        return Database::query($sql, [$tenantId])->fetch()['total'];
    }

    /**
     * Get bounce statistics
     */
    public static function getStats($newsletterId = null)
    {
        $tenantId = TenantContext::getId();

        if ($newsletterId) {
            $sql = "SELECT
                        bounce_type,
                        COUNT(*) as count
                    FROM newsletter_bounces
                    WHERE tenant_id = ? AND newsletter_id = ?
                    GROUP BY bounce_type";
            $results = Database::query($sql, [$tenantId, $newsletterId])->fetchAll();
        } else {
            $sql = "SELECT
                        bounce_type,
                        COUNT(*) as count
                    FROM newsletter_bounces
                    WHERE tenant_id = ?
                    GROUP BY bounce_type";
            $results = Database::query($sql, [$tenantId])->fetchAll();
        }

        $stats = [
            'hard' => 0,
            'soft' => 0,
            'complaint' => 0,
            'total' => 0
        ];

        foreach ($results as $row) {
            $stats[$row['bounce_type']] = (int) $row['count'];
            $stats['total'] += (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Get recent bounces
     */
    public static function getRecent($limit = 50)
    {
        $tenantId = TenantContext::getId();
        $limit = (int) $limit;

        $sql = "SELECT b.*, n.subject as newsletter_subject
                FROM newsletter_bounces b
                LEFT JOIN newsletters n ON b.newsletter_id = n.id
                WHERE b.tenant_id = ?
                ORDER BY b.bounced_at DESC
                LIMIT $limit";

        return Database::query($sql, [$tenantId])->fetchAll();
    }

    /**
     * Get bounces for a specific newsletter
     */
    public static function getForNewsletter($newsletterId)
    {
        $tenantId = TenantContext::getId();

        $sql = "SELECT * FROM newsletter_bounces
                WHERE tenant_id = ? AND newsletter_id = ?
                ORDER BY bounced_at DESC";

        return Database::query($sql, [$tenantId, $newsletterId])->fetchAll();
    }

    /**
     * Filter out suppressed emails from a list
     */
    public static function filterSuppressed($emails)
    {
        if (empty($emails)) {
            return $emails;
        }

        $tenantId = TenantContext::getId();

        // Get all suppressed emails for this tenant
        $placeholders = implode(',', array_fill(0, count($emails), '?'));
        $params = array_merge([$tenantId], $emails);

        $sql = "SELECT email FROM newsletter_suppression_list
                WHERE tenant_id = ? AND email IN ($placeholders)
                AND (expires_at IS NULL OR expires_at > NOW())";

        $suppressedRows = Database::query($sql, $params)->fetchAll();
        $suppressedEmails = array_column($suppressedRows, 'email');

        // Return only non-suppressed emails
        return array_filter($emails, function($email) use ($suppressedEmails) {
            return !in_array(strtolower($email), array_map('strtolower', $suppressedEmails));
        });
    }

    /**
     * Record unsubscribe with details
     */
    public static function recordUnsubscribe($email, $newsletterId = null, $reason = null, $feedback = null)
    {
        $tenantId = TenantContext::getId();

        // Update subscriber record if exists
        $sql = "UPDATE newsletter_subscribers
                SET status = 'unsubscribed',
                    unsubscribed_at = NOW(),
                    unsubscribe_reason = ?,
                    unsubscribe_newsletter_id = ?,
                    unsubscribe_feedback = ?
                WHERE tenant_id = ? AND email = ?";

        Database::query($sql, [$reason, $newsletterId, $feedback, $tenantId, $email]);

        // Also add to suppression list
        self::addToSuppressionList($email, 'unsubscribe');

        return true;
    }
}
