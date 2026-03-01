<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\Mailer;

/**
 * MatchDigestService - Weekly email digest of best matches
 *
 * Generates and sends weekly match digests for each user:
 * - Top 5 matches across listings, jobs, and volunteering
 * - Includes match percentage and direct links
 * - Tracks sent digests to avoid duplicates
 *
 * Usage:
 *   MatchDigestService::generateDigest($userId) — single user
 *   MatchDigestService::sendAllDigests($tenantId) — cron job for all active users
 */
class MatchDigestService
{
    /**
     * Generate a match digest for a single user
     *
     * @param int $userId
     * @return array Digest data with top matches
     */
    public static function generateDigest(int $userId): array
    {
        $tenantId = TenantContext::getId();

        // Use CrossModuleMatchingService to get matches
        $allMatches = CrossModuleMatchingService::getAllMatches($userId, [
            'limit' => 5,
            'min_score' => 40,
            'modules' => ['listings', 'jobs', 'volunteering'],
        ]);

        $matches = $allMatches['items'] ?? [];

        // Get user info
        $user = Database::query(
            "SELECT id, name, first_name, email FROM users WHERE id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$user) {
            return ['success' => false, 'error' => 'User not found'];
        }

        return [
            'user_id' => $userId,
            'user_name' => $user['first_name'] ?? $user['name'] ?? 'Member',
            'user_email' => $user['email'],
            'matches' => $matches,
            'total_matches' => count($matches),
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Send match digests to all active users in a tenant
     *
     * Intended for weekly cron job.
     *
     * @param int $tenantId
     * @return array Summary of sent digests
     */
    public static function sendAllDigests(int $tenantId): array
    {
        $sent = 0;
        $skipped = 0;
        $failed = 0;

        try {
            // Get all active users who haven't received a digest this week
            $users = Database::query(
                "SELECT u.id, u.email, u.first_name, u.name
                 FROM users u
                 WHERE u.tenant_id = ? AND u.status = 'active'
                   AND u.email IS NOT NULL AND u.email != ''
                   AND u.id NOT IN (
                       SELECT user_id FROM match_digest_log
                       WHERE tenant_id = ? AND sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                   )
                 ORDER BY u.id ASC",
                [$tenantId, $tenantId]
            )->fetchAll(\PDO::FETCH_ASSOC);

            // Temporarily set tenant context for matching
            $originalTenantId = null;
            try {
                $originalTenantId = TenantContext::getId();
            } catch (\Exception $e) {
                // Not set yet
            }

            foreach ($users as $user) {
                try {
                    $digest = self::generateDigest((int)$user['id']);

                    // Skip if no matches found
                    if (empty($digest['matches'])) {
                        $skipped++;
                        continue;
                    }

                    // Send email
                    $emailSent = self::sendDigestEmail($digest, $tenantId);

                    if ($emailSent) {
                        // Log the digest
                        self::logDigest($tenantId, (int)$user['id'], $digest);
                        $sent++;
                    } else {
                        $failed++;
                    }
                } catch (\Exception $e) {
                    error_log("MatchDigestService: Failed for user {$user['id']}: " . $e->getMessage());
                    $failed++;
                }
            }
        } catch (\Exception $e) {
            error_log("MatchDigestService::sendAllDigests error: " . $e->getMessage());
        }

        return [
            'sent' => $sent,
            'skipped' => $skipped,
            'failed' => $failed,
            'total_users' => $sent + $skipped + $failed,
        ];
    }

    /**
     * Send the digest email to a user
     */
    private static function sendDigestEmail(array $digest, int $tenantId): bool
    {
        $email = $digest['user_email'] ?? null;
        if (empty($email)) {
            return false;
        }

        $userName = $digest['user_name'];
        $matches = $digest['matches'];

        // Get tenant info for branding
        $tenant = Database::query("SELECT name FROM tenants WHERE id = ?", [$tenantId])->fetch();
        $tenantName = $tenant['name'] ?? 'Project NEXUS';
        $baseUrl = TenantContext::getSetting('site_url', 'https://app.project-nexus.ie');

        // Build match cards HTML
        $matchCardsHtml = '';
        foreach ($matches as $i => $match) {
            $score = $match['score'];
            $title = htmlspecialchars($match['title']);
            $source = ucfirst($match['source']);
            $description = htmlspecialchars(mb_substr($match['description'] ?? '', 0, 120));
            $reasons = !empty($match['match_reasons']) ? htmlspecialchars(implode(' · ', $match['match_reasons'])) : '';
            $link = $baseUrl . '/' . $match['source'] . 's/' . $match['source_id'];

            $scoreColor = $score >= 80 ? '#22c55e' : ($score >= 60 ? '#f59e0b' : '#6366f1');

            $matchCardsHtml .= <<<HTML
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin: 12px 0;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-weight: 600; color: #1e293b;">{$title}</span>
                    <span style="background: {$scoreColor}; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: 600;">{$score}% match</span>
                </div>
                <p style="color: #64748b; font-size: 13px; margin: 4px 0;">{$source}</p>
                <p style="color: #475569; font-size: 14px; margin: 8px 0;">{$description}</p>
                <p style="color: #6366f1; font-size: 12px; margin: 4px 0;">{$reasons}</p>
                <a href="{$link}" style="display: inline-block; margin-top: 8px; color: #6366f1; font-weight: 500; text-decoration: none; font-size: 14px;">View details &rarr;</a>
            </div>
HTML;
        }

        $matchCount = count($matches);
        $htmlBody = <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #6366f1, #8b5cf6); padding: 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 24px;">Your Weekly Matches</h1>
        <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0;">We found {$matchCount} great match(es) for you!</p>
    </div>
    <div style="background: #f8fafc; padding: 24px; border-radius: 0 0 16px 16px; border: 1px solid #e2e8f0; border-top: none;">
        <p style="color: #64748b; margin: 0 0 16px;">Hi {$userName},</p>
        <p style="color: #475569; margin: 0 0 16px;">Here are your top matches this week across listings, jobs, and volunteering opportunities:</p>
        {$matchCardsHtml}
        <div style="text-align: center; margin-top: 24px;">
            <a href="{$baseUrl}/matches" style="display: inline-block; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; padding: 12px 32px; border-radius: 8px; text-decoration: none; font-weight: 600;">
                View All Matches
            </a>
        </div>
    </div>
    <div style="text-align: center; padding: 16px; color: #94a3b8; font-size: 12px;">
        <p>You received this from {$tenantName}.</p>
        <p><a href="{$baseUrl}/settings?tab=notifications" style="color: #6366f1;">Manage notification preferences</a></p>
    </div>
</div>
HTML;

        try {
            $mailer = new Mailer();
            return $mailer->send($email, "Your Weekly Match Digest — {$matchCount} new matches!", $htmlBody);
        } catch (\Exception $e) {
            error_log("MatchDigestService::sendDigestEmail error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log a sent digest
     */
    private static function logDigest(int $tenantId, int $userId, array $digest): void
    {
        try {
            Database::query(
                "INSERT INTO match_digest_log (tenant_id, user_id, matches_count, sent_at, digest_data)
                 VALUES (?, ?, ?, NOW(), ?)",
                [$tenantId, $userId, count($digest['matches']), json_encode(array_map(function ($m) {
                    return ['source' => $m['source'], 'source_id' => $m['source_id'], 'score' => $m['score']];
                }, $digest['matches']))]
            );
        } catch (\Exception $e) {
            error_log("MatchDigestService::logDigest error: " . $e->getMessage());
        }
    }
}
