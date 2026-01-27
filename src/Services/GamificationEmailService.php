<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\Mailer;

/**
 * Gamification Email Service
 * Sends weekly progress digests and achievement notifications
 * Following full theme standards from EmailTemplateBuilder
 */
class GamificationEmailService
{
    // Theme colors
    private const BRAND_COLOR = '#6366f1';
    private const BRAND_COLOR_DARK = '#4f46e5';
    private const ACCENT_COLOR = '#f59e0b';
    private const SUCCESS_COLOR = '#10b981';
    private const TEXT_COLOR = '#374151';
    private const MUTED_COLOR = '#6b7280';
    private const BG_COLOR = '#f3f4f6';

    /**
     * Send weekly progress digest to users who actually have activity
     */
    public static function sendWeeklyDigests(): array
    {
        $tenantId = TenantContext::getId();
        $results = ['sent' => 0, 'skipped' => 0, 'failed' => 0];
        $weekAgo = date('Y-m-d H:i:s', strtotime('-7 days'));

        // OPTIMIZATION: Only get users who actually earned XP (>0) or badges this week
        // This prevents processing thousands of inactive users
        // Note: Preference check moved to PHP loop for cleaner migration handling
        $users = Database::query(
            "SELECT DISTINCT u.id, u.email, u.first_name, u.last_name, u.xp, u.level
             FROM users u
             WHERE u.tenant_id = ?
             AND u.is_approved = 1
             AND u.email IS NOT NULL
             AND (
                 EXISTS (SELECT 1 FROM xp_history xh WHERE xh.user_id = u.id AND xh.created_at >= ? AND xh.xp_amount > 0)
                 OR EXISTS (SELECT 1 FROM user_badges ub WHERE ub.user_id = u.id AND ub.awarded_at >= ?)
             )
             LIMIT 500",
            [$tenantId, $weekAgo, $weekAgo]
        )->fetchAll();

        foreach ($users as $user) {
            try {
                // Check if user has digest emails enabled (handles legacy migration)
                if (!\Nexus\Models\User::isGamificationEmailEnabled($user['id'], 'digest')) {
                    $results['skipped']++;
                    continue;
                }

                $digest = self::generateUserDigest($user['id']);

                // Skip if somehow no activity (edge case)
                if ($digest['total_xp_earned'] == 0 && empty($digest['badges_earned'])) {
                    $results['skipped']++;
                    continue;
                }

                $sent = self::sendDigestEmail($user, $digest);
                if ($sent) {
                    $results['sent']++;
                } else {
                    $results['failed']++;
                }
            } catch (\Throwable $e) {
                error_log("Weekly digest error for user {$user['id']}: " . $e->getMessage());
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Generate weekly digest data for a user
     */
    public static function generateUserDigest(int $userId): array
    {
        $tenantId = TenantContext::getId();
        $weekAgo = date('Y-m-d H:i:s', strtotime('-7 days'));

        // XP earned this week
        $xpData = Database::query(
            "SELECT SUM(xp_amount) as total_xp, COUNT(*) as xp_events
             FROM xp_history
             WHERE user_id = ? AND created_at >= ?",
            [$userId, $weekAgo]
        )->fetch();

        // Badges earned this week
        $badges = Database::query(
            "SELECT badge_key, name, icon, awarded_at
             FROM user_badges
             WHERE user_id = ? AND awarded_at >= ?
             ORDER BY awarded_at DESC",
            [$userId, $weekAgo]
        )->fetchAll();

        // Challenges completed this week
        $challenges = Database::query(
            "SELECT c.title, c.xp_reward, ucp.completed_at
             FROM user_challenge_progress ucp
             JOIN challenges c ON ucp.challenge_id = c.id
             WHERE ucp.user_id = ? AND ucp.completed_at >= ?
             ORDER BY ucp.completed_at DESC",
            [$userId, $weekAgo]
        )->fetchAll();

        // Current user stats
        $userStats = Database::query(
            "SELECT xp, level FROM users WHERE id = ?",
            [$userId]
        )->fetch();

        // Leaderboard position
        $rank = Database::query(
            "SELECT COUNT(*) + 1 as rank
             FROM users
             WHERE tenant_id = ? AND is_approved = 1 AND xp > (SELECT xp FROM users WHERE id = ?)",
            [$tenantId, $userId]
        )->fetch()['rank'] ?? 0;

        // Weekly rank change
        $lastWeekRank = Database::query(
            "SELECT rank_position FROM weekly_rank_snapshots
             WHERE user_id = ? ORDER BY snapshot_date DESC LIMIT 1",
            [$userId]
        )->fetch()['rank_position'] ?? $rank;

        $rankChange = $lastWeekRank - $rank;

        // XP to next level
        $nextLevelXp = GamificationService::calculateXPForLevel($userStats['level'] + 1);
        $currentLevelXp = GamificationService::calculateXPForLevel($userStats['level']);
        $xpToNextLevel = $nextLevelXp - $userStats['xp'];
        $levelProgress = round((($userStats['xp'] - $currentLevelXp) / ($nextLevelXp - $currentLevelXp)) * 100);

        // Upcoming challenges
        $upcomingChallenges = Database::query(
            "SELECT c.id, c.title, c.description, c.xp_reward, c.target_count,
                    COALESCE(ucp.current_count, 0) as current_count
             FROM challenges c
             LEFT JOIN user_challenge_progress ucp ON c.id = ucp.challenge_id AND ucp.user_id = ?
             WHERE c.tenant_id = ? AND c.is_active = 1 AND c.end_date >= CURDATE()
             AND (ucp.completed_at IS NULL OR ucp.completed_at IS NULL)
             ORDER BY c.end_date ASC
             LIMIT 3",
            [$userId, $tenantId]
        )->fetchAll();

        // Near-complete badges (progress >= 50%)
        $nearCompleteBadges = self::getNearCompleteBadges($userId);

        return [
            'total_xp_earned' => (int)($xpData['total_xp'] ?? 0),
            'xp_events' => (int)($xpData['xp_events'] ?? 0),
            'badges_earned' => $badges,
            'challenges_completed' => $challenges,
            'current_xp' => $userStats['xp'],
            'current_level' => $userStats['level'],
            'rank' => $rank,
            'rank_change' => $rankChange,
            'xp_to_next_level' => $xpToNextLevel,
            'level_progress' => $levelProgress,
            'upcoming_challenges' => $upcomingChallenges,
            'near_complete_badges' => $nearCompleteBadges,
        ];
    }

    /**
     * Get badges that user is close to earning
     */
    private static function getNearCompleteBadges(int $userId): array
    {
        // getBadgeProgress returns all badge progress as an array
        $allProgress = GamificationService::getBadgeProgress($userId);

        $nearComplete = [];

        foreach ($allProgress as $progress) {
            // Skip if no percent key or not in the 50-99% range
            if (!isset($progress['percent']) || $progress['percent'] < 50 || $progress['percent'] >= 100) {
                continue;
            }

            $badge = $progress['badge'] ?? null;
            if (!$badge) {
                continue;
            }

            $nearComplete[] = [
                'key' => $badge['key'] ?? '',
                'name' => $badge['name'] ?? 'Unknown',
                'icon' => $badge['icon'] ?? '🏆',
                'progress' => $progress['percent'],
                'current' => $progress['current'] ?? 0,
                'target' => $progress['target'] ?? 0,
            ];
        }

        usort($nearComplete, fn($a, $b) => $b['progress'] - $a['progress']);

        return array_slice($nearComplete, 0, 3);
    }

    /**
     * Send digest email to user
     */
    private static function sendDigestEmail(array $user, array $digest): bool
    {
        $basePath = TenantContext::getBasePath();
        $siteName = TenantContext::getSetting('site_name', 'Community');
        $siteUrl = TenantContext::getSetting('site_url', '');

        $subject = "Your Weekly Progress - {$siteName}";

        $html = self::generateDigestHtml($user, $digest, $basePath, $siteName, $siteUrl);

        try {
            $mailer = new Mailer();
            return $mailer->send($user['email'], $subject, $html);
        } catch (\Throwable $e) {
            error_log("Failed to send digest to user {$user['id']}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the base email wrapper HTML
     */
    private static function getEmailWrapper(string $content, string $siteName, string $siteUrl, string $basePath, string $previewText = ''): string
    {
        $brandColor = self::BRAND_COLOR;
        $brandColorDark = self::BRAND_COLOR_DARK;
        $bgColor = self::BG_COLOR;
        $year = date('Y');
        $settingsUrl = $siteUrl . $basePath . '/profile/settings';

        return <<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="format-detection" content="telephone=no,address=no,email=no,date=no,url=no">
    <title>{$siteName}</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style type="text/css">
        /* Reset styles */
        body, table, td, p, a, li, blockquote { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; background-color: {$bgColor}; }

        /* Typography */
        body, table, td, a { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }

        /* Link styles */
        a { color: {$brandColor}; text-decoration: underline; }
        a:hover { color: {$brandColorDark}; }

        /* Button hover */
        .button-primary:hover { background-color: {$brandColorDark} !important; }

        /* Responsive */
        @media screen and (max-width: 600px) {
            .email-container { width: 100% !important; max-width: 100% !important; }
            .fluid { width: 100% !important; max-width: 100% !important; height: auto !important; }
            .stack-column { display: block !important; width: 100% !important; max-width: 100% !important; }
            .center-on-narrow { text-align: center !important; display: block !important; margin-left: auto !important; margin-right: auto !important; float: none !important; }
            table.center-on-narrow { display: inline-block !important; }
            .hide-mobile { display: none !important; }
            .mobile-padding { padding-left: 20px !important; padding-right: 20px !important; }
            .stats-cell { display: block !important; width: 100% !important; padding: 15px 0 !important; border-left: none !important; border-right: none !important; border-bottom: 1px solid #e5e7eb !important; }
            .stats-cell:last-child { border-bottom: none !important; }
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .email-bg { background-color: #1f2937 !important; }
            .email-container-inner { background-color: #374151 !important; }
            .text-dark { color: #f3f4f6 !important; }
            .text-muted { color: #9ca3af !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: {$bgColor};">

    <!-- Preview text -->
    <div style="display: none; max-height: 0; overflow: hidden; mso-hide: all;">
        {$previewText}
        &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847;
    </div>

    <!-- Email wrapper -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: {$bgColor};" class="email-bg">
        <tr>
            <td style="padding: 40px 10px;">

                <!-- Email container -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" align="center" class="email-container" style="margin: auto;">

                    {$content}

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 30px 40px; border-radius: 0 0 16px 16px; border-top: 1px solid #e5e7eb;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="text-align: center; padding-bottom: 15px;">
                                        <p style="margin: 0; font-size: 14px; color: #6b7280;">
                                            &copy; {$year} {$siteName}. All rights reserved.
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: center;">
                                        <a href="{$settingsUrl}" style="color: #6b7280; text-decoration: underline; font-size: 13px;">Manage email preferences</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>
HTML;
    }

    /**
     * Generate HTML for digest email
     */
    private static function generateDigestHtml(array $user, array $digest, string $basePath, string $siteName, string $siteUrl): string
    {
        $firstName = htmlspecialchars($user['first_name']);
        $brandColor = self::BRAND_COLOR;
        $brandColorDark = self::BRAND_COLOR_DARK;
        $accentColor = self::ACCENT_COLOR;
        $successColor = self::SUCCESS_COLOR;
        $textColor = self::TEXT_COLOR;
        $mutedColor = self::MUTED_COLOR;

        $nextLevel = $digest['current_level'] + 1;
        $rankArrow = $digest['rank_change'] > 0 ? '&#8593;' : ($digest['rank_change'] < 0 ? '&#8595;' : '&#8594;');
        $rankColor = $digest['rank_change'] > 0 ? $successColor : ($digest['rank_change'] < 0 ? '#ef4444' : $mutedColor);
        $rankText = $digest['rank_change'] != 0 ? abs($digest['rank_change']) . ' spots' : 'No change';

        // Build badges HTML with improved card layout
        $badgesHtml = '';
        if (!empty($digest['badges_earned'])) {
            $badgeCount = count($digest['badges_earned']);
            $badgeItems = '';

            // Use responsive grid layout for badges (2 per row)
            for ($i = 0; $i < $badgeCount; $i += 2) {
                $badge1 = $digest['badges_earned'][$i];
                $badge2 = isset($digest['badges_earned'][$i + 1]) ? $digest['badges_earned'][$i + 1] : null;

                $badgeItems .= '<tr>';

                // First badge
                $badgeItems .= sprintf(
                    '<td style="padding: 10px; width: 50%%; vertical-align: top;" class="stack-column">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%%" style="background: linear-gradient(135deg, #fef3c7 0%%, #fde68a 100%%); border-radius: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                            <tr>
                                <td style="padding: 24px 20px; text-align: center;">
                                    <div style="font-size: 48px; margin-bottom: 12px; line-height: 1;">%s</div>
                                    <div style="font-size: 15px; font-weight: 700; color: %s; line-height: 1.4;">%s</div>
                                </td>
                            </tr>
                        </table>
                    </td>',
                    $badge1['icon'],
                    $textColor,
                    htmlspecialchars($badge1['name'])
                );

                // Second badge or empty cell
                if ($badge2) {
                    $badgeItems .= sprintf(
                        '<td style="padding: 10px; width: 50%%; vertical-align: top;" class="stack-column">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%%" style="background: linear-gradient(135deg, #fef3c7 0%%, #fde68a 100%%); border-radius: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                                <tr>
                                    <td style="padding: 24px 20px; text-align: center;">
                                        <div style="font-size: 48px; margin-bottom: 12px; line-height: 1;">%s</div>
                                        <div style="font-size: 15px; font-weight: 700; color: %s; line-height: 1.4;">%s</div>
                                    </td>
                                </tr>
                            </table>
                        </td>',
                        $badge2['icon'],
                        $textColor,
                        htmlspecialchars($badge2['name'])
                    );
                } else {
                    // Empty cell for odd number of badges
                    $badgeItems .= '<td style="padding: 10px; width: 50%%;" class="stack-column">&nbsp;</td>';
                }

                $badgeItems .= '</tr>';
            }

            $badgesHtml = <<<HTML
                            <!-- Badges Earned Section -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="padding: 32px 40px 24px;" class="mobile-padding">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <tr>
                                                <td>
                                                    <h3 style="margin: 0 0 4px; font-size: 22px; font-weight: 800; color: {$textColor}; letter-spacing: -0.5px;" class="text-dark">&#127942; Badges Earned</h3>
                                                    <p style="margin: 0 0 20px; font-size: 14px; color: {$mutedColor};" class="text-muted">Outstanding achievements this week!</p>
                                                </td>
                                            </tr>
                                        </table>
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            {$badgeItems}
                                        </table>
                                    </td>
                                </tr>
                            </table>
HTML;
        }

        // Build challenges HTML with improved card design
        $challengesHtml = '';
        if (!empty($digest['challenges_completed'])) {
            $challengeItems = '';
            foreach ($digest['challenges_completed'] as $challenge) {
                $challengeItems .= sprintf(
                    '<tr>
                        <td style="padding: 8px 0;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%%" style="background: linear-gradient(135deg, #f0fdf4 0%%, #dcfce7 100%%); border-radius: 12px; border-left: 4px solid %s;">
                                <tr>
                                    <td style="padding: 18px 20px;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%%">
                                            <tr>
                                                <td width="40" style="vertical-align: middle;">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="36" height="36" style="background: %s; border-radius: 50%%;">
                                                        <tr>
                                                            <td style="text-align: center; vertical-align: middle;">
                                                                <span style="color: white; font-size: 18px; font-weight: bold;">&#10003;</span>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                                <td style="vertical-align: middle; padding-left: 16px;">
                                                    <p style="margin: 0 0 4px; font-size: 16px; font-weight: 700; color: %s;" class="text-dark">%s</p>
                                                    <p style="margin: 0; font-size: 13px; color: #047857; font-weight: 600;">Completed &#127881;</p>
                                                </td>
                                                <td style="vertical-align: middle; text-align: right; white-space: nowrap; padding-left: 12px;">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="background: %s; border-radius: 20px; display: inline-block;">
                                                        <tr>
                                                            <td style="padding: 8px 16px;">
                                                                <span style="color: white; font-size: 15px; font-weight: 700; white-space: nowrap;">+%d XP</span>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>',
                    $successColor,
                    $successColor,
                    $textColor,
                    htmlspecialchars($challenge['title']),
                    $accentColor,
                    $challenge['xp_reward']
                );
            }
            $challengesHtml = <<<HTML
                            <!-- Challenges Completed Section -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="padding: 32px 40px 24px;" class="mobile-padding">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <tr>
                                                <td>
                                                    <h3 style="margin: 0 0 4px; font-size: 22px; font-weight: 800; color: {$textColor}; letter-spacing: -0.5px;" class="text-dark">&#127942; Challenges Completed</h3>
                                                    <p style="margin: 0 0 20px; font-size: 14px; color: {$mutedColor};" class="text-muted">You crushed these challenges!</p>
                                                </td>
                                            </tr>
                                        </table>
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            {$challengeItems}
                                        </table>
                                    </td>
                                </tr>
                            </table>
HTML;
        }

        // Build upcoming challenges HTML with modern progress cards
        $upcomingHtml = '';
        if (!empty($digest['upcoming_challenges'])) {
            $upcomingItems = '';
            foreach ($digest['upcoming_challenges'] as $challenge) {
                $progress = $challenge['target_count'] > 0
                    ? round(($challenge['current_count'] / $challenge['target_count']) * 100)
                    : 0;
                $upcomingItems .= sprintf(
                    '<tr>
                        <td style="padding: 8px 0;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%%" style="background: linear-gradient(135deg, #eff6ff 0%%, #dbeafe 100%%); border-radius: 12px; border-left: 4px solid %s;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%%">
                                            <tr>
                                                <td>
                                                    <p style="margin: 0 0 8px; font-size: 16px; font-weight: 700; color: %s;" class="text-dark">%s</p>
                                                </td>
                                                <td style="text-align: right;">
                                                    <span style="background: %s; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 700; white-space: nowrap;">+%d XP</span>
                                                </td>
                                            </tr>
                                        </table>
                                        <p style="margin: 0 0 12px; font-size: 13px; color: %s; font-weight: 500;" class="text-muted">%d of %d completed &#8226; %d%% done</p>
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%%">
                                            <tr>
                                                <td style="background: #e5e7eb; height: 10px; border-radius: 6px; position: relative; overflow: hidden;">
                                                    <div style="background: linear-gradient(90deg, %s 0%%, %s 100%%); height: 10px; border-radius: 6px; width: %d%%; box-shadow: 0 2px 4px rgba(99, 102, 241, 0.3);"></div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>',
                    $brandColor,
                    $textColor,
                    htmlspecialchars($challenge['title']),
                    $brandColor,
                    $challenge['xp_reward'],
                    $mutedColor,
                    $challenge['current_count'],
                    $challenge['target_count'],
                    $progress,
                    $brandColor,
                    $brandColorDark,
                    $progress
                );
            }
            $upcomingHtml = <<<HTML
                            <!-- Active Challenges Section -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="padding: 32px 40px 24px;" class="mobile-padding">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <tr>
                                                <td>
                                                    <h3 style="margin: 0 0 4px; font-size: 22px; font-weight: 800; color: {$textColor}; letter-spacing: -0.5px;" class="text-dark">&#127919; Active Challenges</h3>
                                                    <p style="margin: 0 0 20px; font-size: 14px; color: {$mutedColor};" class="text-muted">Keep going to earn more rewards!</p>
                                                </td>
                                            </tr>
                                        </table>
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            {$upcomingItems}
                                        </table>
                                    </td>
                                </tr>
                            </table>
HTML;
        }

        // Build near complete badges HTML with exciting design
        $nearCompleteHtml = '';
        if (!empty($digest['near_complete_badges'])) {
            $nearItems = '';
            foreach ($digest['near_complete_badges'] as $badge) {
                $nearItems .= sprintf(
                    '<tr>
                        <td style="padding: 8px 0;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%%" style="background: linear-gradient(135deg, #fef3c7 0%%, #fde68a 100%%); border-radius: 12px; border-left: 4px solid %s;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%%">
                                            <tr>
                                                <td width="60" style="vertical-align: middle; text-align: center;">
                                                    <span style="font-size: 40px; color: #d97706;">%s</span>
                                                </td>
                                                <td style="vertical-align: middle; padding-left: 12px;">
                                                    <p style="margin: 0 0 6px; font-size: 16px; font-weight: 700; color: %s;" class="text-dark">%s</p>
                                                    <p style="margin: 0 0 10px; font-size: 13px; color: #92400e; font-weight: 600;">%d%% complete &#8226; %d of %d</p>
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%%">
                                                        <tr>
                                                            <td style="background: rgba(0,0,0,0.1); height: 8px; border-radius: 6px;">
                                                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="%d%%" style="background: linear-gradient(90deg, %s 0%%, #f59e0b 100%%); height: 8px; border-radius: 6px; box-shadow: 0 2px 4px rgba(245, 158, 11, 0.4);">
                                                                    <tr><td style="font-size: 0; line-height: 0;">&nbsp;</td></tr>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>',
                    $accentColor,
                    $badge['icon'],
                    $textColor,
                    htmlspecialchars($badge['name']),
                    $badge['progress'],
                    $badge['current'],
                    $badge['target'],
                    $badge['progress'],
                    $accentColor
                );
            }
            $nearCompleteHtml = <<<HTML
                            <!-- Almost There Section -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="padding: 32px 40px 24px;" class="mobile-padding">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <tr>
                                                <td>
                                                    <h3 style="margin: 0 0 4px; font-size: 22px; font-weight: 800; color: {$textColor}; letter-spacing: -0.5px;" class="text-dark">&#128293; Almost There!</h3>
                                                    <p style="margin: 0 0 20px; font-size: 14px; color: {$mutedColor};" class="text-muted">You're so close to unlocking these badges!</p>
                                                </td>
                                            </tr>
                                        </table>
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            {$nearItems}
                                        </table>
                                    </td>
                                </tr>
                            </table>
HTML;
        }

        $content = <<<HTML
                    <!-- Enhanced Header with gradient and wave pattern -->
                    <tr>
                        <td style="padding: 0; background: linear-gradient(135deg, {$brandColor} 0%, #7c3aed 100%); border-radius: 16px 16px 0 0; position: relative;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="padding: 40px 40px 30px; text-align: center;">
                                        <div style="font-size: 56px; margin-bottom: 16px; line-height: 1;">&#127881;</div>
                                        <h1 style="margin: 0 0 12px; font-size: 32px; font-weight: 800; color: #ffffff; letter-spacing: -0.8px; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">Your Weekly Wins</h1>
                                        <p style="margin: 0; font-size: 18px; color: rgba(255,255,255,0.95); font-weight: 500;">Hey {$firstName}, you've been amazing this week!</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Main content container -->
                    <tr>
                        <td style="background-color: #ffffff; padding: 0;" class="email-container-inner">

                            <!-- Hero Stats Grid with Cards -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="padding: 32px 30px; background: linear-gradient(180deg, #fafafb 0%, #ffffff 100%);" class="mobile-padding">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <tr>
                                                <!-- XP Earned Card -->
                                                <td width="33%" style="padding: 0 10px;" class="stats-cell">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%); border-radius: 16px; box-shadow: 0 2px 8px rgba(245, 158, 11, 0.15); border: 2px solid #fed7aa;">
                                                        <tr>
                                                            <td style="padding: 24px 20px; text-align: center;">
                                                                <div style="font-size: 14px; font-weight: 700; color: #92400e; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">XP Earned</div>
                                                                <div style="font-size: 42px; font-weight: 900; color: {$accentColor}; line-height: 1; margin-bottom: 4px;">+{$digest['total_xp_earned']}</div>
                                                                <div style="font-size: 13px; color: #b45309; font-weight: 600;">this week &#11088;</div>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>

                                                <!-- Current Level Card -->
                                                <td width="33%" style="padding: 0 10px;" class="stats-cell">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-radius: 16px; box-shadow: 0 2px 8px rgba(99, 102, 241, 0.15); border: 2px solid #bfdbfe;">
                                                        <tr>
                                                            <td style="padding: 24px 20px; text-align: center;">
                                                                <div style="font-size: 14px; font-weight: 700; color: #1e3a8a; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Level</div>
                                                                <div style="font-size: 42px; font-weight: 900; color: {$brandColor}; line-height: 1; margin-bottom: 4px;">{$digest['current_level']}</div>
                                                                <div style="font-size: 13px; color: #1e40af; font-weight: 600;">{$digest['level_progress']}% to next</div>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>

                                                <!-- Leaderboard Rank Card -->
                                                <td width="33%" style="padding: 0 10px;" class="stats-cell">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border-radius: 16px; box-shadow: 0 2px 8px rgba(16, 185, 129, 0.15); border: 2px solid #bbf7d0;">
                                                        <tr>
                                                            <td style="padding: 24px 20px; text-align: center;">
                                                                <div style="font-size: 14px; font-weight: 700; color: #065f46; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Rank</div>
                                                                <div style="font-size: 42px; font-weight: 900; color: {$rankColor}; line-height: 1; margin-bottom: 4px;">#{$digest['rank']}</div>
                                                                <div style="font-size: 13px; color: #047857; font-weight: 600;">{$rankArrow} {$rankText}</div>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Level Progress Bar Section -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="padding: 0 40px 32px;" class="mobile-padding">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%); border-radius: 16px; padding: 24px; border: 2px solid #e9d5ff;">
                                            <tr>
                                                <td>
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                        <tr>
                                                            <td>
                                                                <p style="margin: 0 0 4px; font-size: 13px; font-weight: 700; color: #6b21a8; text-transform: uppercase; letter-spacing: 0.5px;">Next Level</p>
                                                                <p style="margin: 0 0 12px; font-size: 18px; font-weight: 800; color: {$textColor};" class="text-dark">Level {$digest['current_level']} &#8594; Level {$nextLevel}</p>
                                                            </td>
                                                            <td style="text-align: right;">
                                                                <p style="margin: 0; font-size: 16px; font-weight: 700; color: #7c3aed;">{$digest['xp_to_next_level']} XP to go</p>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-top: 12px;">
                                                        <tr>
                                                            <td style="background: rgba(124, 58, 237, 0.2); height: 12px; border-radius: 8px;">
                                                                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="{$digest['level_progress']}%" style="background: linear-gradient(90deg, {$brandColor} 0%, #7c3aed 100%); height: 12px; border-radius: 8px; box-shadow: 0 2px 8px rgba(124, 58, 237, 0.4);">
                                                                    <tr>
                                                                        <td style="text-align: right; padding-right: 8px;">
                                                                            <span style="font-size: 10px; font-weight: 800; color: white; line-height: 12px;">{$digest['level_progress']}%</span>
                                                                        </td>
                                                                    </tr>
                                                                </table>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

{$badgesHtml}
{$challengesHtml}
{$upcomingHtml}
{$nearCompleteHtml}

                            <!-- Enhanced CTA Section -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="padding: 40px; text-align: center; background: linear-gradient(180deg, #ffffff 0%, #f9fafb 100%);" class="mobile-padding">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, {$brandColor} 0%, #7c3aed 100%); border-radius: 16px; padding: 32px; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);">
                                            <tr>
                                                <td style="text-align: center;">
                                                    <h3 style="margin: 0 0 12px; font-size: 24px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px;">Ready for More?</h3>
                                                    <p style="margin: 0 0 24px; font-size: 16px; color: rgba(255,255,255,0.9); line-height: 1.5;">
                                                        Keep the momentum going! Every action brings you closer to your next big win.
                                                    </p>
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
                                                        <tr>
                                                            <td style="border-radius: 12px; background: #ffffff; box-shadow: 0 4px 12px rgba(0,0,0,0.15);" class="button-primary">
                                                                <a href="{$siteUrl}{$basePath}/achievements" style="display: inline-block; padding: 18px 40px; font-size: 17px; font-weight: 700; color: {$brandColor}; text-decoration: none; border-radius: 12px; letter-spacing: 0.3px;">
                                                                    &#128640; View Full Progress
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                        <p style="margin: 24px 0 0; font-size: 14px; color: {$mutedColor}; line-height: 1.6;" class="text-muted">
                                            You're doing amazing! Keep engaging with the community to unlock even more rewards and climb the leaderboard. &#127942;
                                        </p>
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>
HTML;

        return self::getEmailWrapper($content, $siteName, $siteUrl, $basePath, "Your weekly progress: +{$digest['total_xp_earned']} XP earned this week!");
    }

    /**
     * Send milestone notification email
     */
    public static function sendMilestoneEmail(int $userId, string $type, array $data): bool
    {
        $user = Database::query(
            "SELECT id, email, first_name, last_name FROM users WHERE id = ?",
            [$userId]
        )->fetch();

        if (!$user || empty($user['email'])) {
            return false;
        }

        // Check if user has milestone emails enabled
        if (!\Nexus\Models\User::isGamificationEmailEnabled($userId, 'milestones')) {
            return false;
        }

        $siteName = TenantContext::getSetting('site_name', 'Community');
        $basePath = TenantContext::getBasePath();
        $siteUrl = TenantContext::getSetting('site_url', '');

        switch ($type) {
            case 'level_up':
                $subject = "Congratulations! You've reached Level {$data['level']}!";
                $html = self::generateLevelUpEmail($user, $data, $basePath, $siteName, $siteUrl);
                break;

            case 'badge_earned':
                $subject = "You've earned a new badge: {$data['badge_name']}!";
                $html = self::generateBadgeEmail($user, $data, $basePath, $siteName, $siteUrl);
                break;

            case 'streak_milestone':
                $subject = "Amazing! {$data['streak_days']}-day streak achieved!";
                $html = self::generateStreakEmail($user, $data, $basePath, $siteName, $siteUrl);
                break;

            default:
                return false;
        }

        try {
            $mailer = new Mailer();
            return $mailer->send($user['email'], $subject, $html);
        } catch (\Throwable $e) {
            error_log("Failed to send milestone email: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate level-up email HTML
     */
    private static function generateLevelUpEmail(array $user, array $data, string $basePath, string $siteName, string $siteUrl): string
    {
        $firstName = htmlspecialchars($user['first_name']);
        $level = $data['level'];
        $bonusXp = $data['bonus_xp'] ?? 0;
        $brandColor = self::BRAND_COLOR;
        $brandColorDark = self::BRAND_COLOR_DARK;
        $accentColor = self::ACCENT_COLOR;
        $successColor = self::SUCCESS_COLOR;
        $textColor = self::TEXT_COLOR;
        $mutedColor = self::MUTED_COLOR;

        $bonusHtml = '';
        if ($bonusXp > 0) {
            $bonusHtml = "<p style=\"margin: 20px 0 0; font-size: 18px; font-weight: 600; color: {$successColor};\">Milestone Bonus: +{$bonusXp} XP!</p>";
        }

        $content = <<<HTML
                    <!-- Header with gradient -->
                    <tr>
                        <td style="padding: 40px; text-align: center; background: linear-gradient(135deg, {$accentColor} 0%, #f59e0b 100%); border-radius: 16px 16px 0 0;">
                            <div style="font-size: 80px; margin-bottom: 20px;">&#127881;</div>
                            <h1 style="margin: 0 0 10px; font-size: 32px; font-weight: 800; color: #1f2937;">Level Up!</h1>
                            <p style="margin: 0; font-size: 18px; color: #78350f;">Congratulations, {$firstName}!</p>
                        </td>
                    </tr>

                    <!-- Main content -->
                    <tr>
                        <td style="background-color: #ffffff; padding: 40px; text-align: center;" class="email-container-inner">
                            <div style="font-size: 120px; font-weight: 900; color: {$textColor}; line-height: 1;">{$level}</div>
                            <p style="margin: 15px 0 0; font-size: 18px; color: {$mutedColor};">You've reached Level {$level}</p>
                            {$bonusHtml}

                            <!-- CTA Button -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-top: 30px;">
                                <tr>
                                    <td style="text-align: center;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
                                            <tr>
                                                <td style="border-radius: 10px; background: linear-gradient(135deg, {$brandColor} 0%, {$brandColorDark} 100%);" class="button-primary">
                                                    <a href="{$siteUrl}{$basePath}/achievements" style="display: inline-block; padding: 16px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 10px;">View Your Achievements</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
HTML;

        return self::getEmailWrapper($content, $siteName, $siteUrl, $basePath, "You've reached Level {$level}!");
    }

    /**
     * Generate badge earned email HTML
     */
    private static function generateBadgeEmail(array $user, array $data, string $basePath, string $siteName, string $siteUrl): string
    {
        $firstName = htmlspecialchars($user['first_name']);
        $badgeName = htmlspecialchars($data['badge_name']);
        $badgeIcon = $data['badge_icon'] ?? '&#127942;';
        $achievementDesc = htmlspecialchars($data['badge_description'] ?? 'reaching a new milestone');
        $achievementDesc = ucfirst($achievementDesc);

        $brandColor = self::BRAND_COLOR;
        $brandColorDark = self::BRAND_COLOR_DARK;
        $textColor = self::TEXT_COLOR;
        $mutedColor = self::MUTED_COLOR;

        $content = <<<HTML
                    <!-- Header with gradient -->
                    <tr>
                        <td style="padding: 40px; text-align: center; background: linear-gradient(135deg, {$brandColor} 0%, #7c3aed 100%); border-radius: 16px 16px 0 0;">
                            <div style="font-size: 80px; margin-bottom: 20px;">{$badgeIcon}</div>
                            <h1 style="margin: 0 0 10px; font-size: 28px; font-weight: 700; color: #ffffff;">New Badge Earned!</h1>
                            <p style="margin: 0; font-size: 18px; color: rgba(255,255,255,0.9);">Congratulations, {$firstName}!</p>
                        </td>
                    </tr>

                    <!-- Main content -->
                    <tr>
                        <td style="background-color: #ffffff; padding: 40px;" class="email-container-inner">
                            <h2 style="margin: 0 0 20px; font-size: 24px; font-weight: 700; color: {$textColor}; text-align: center;" class="text-dark">{$badgeName}</h2>

                            <div style="background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%); border-radius: 12px; padding: 20px; margin-bottom: 24px;">
                                <p style="margin: 0 0 8px; font-size: 12px; font-weight: 700; color: {$mutedColor}; text-transform: uppercase; letter-spacing: 1px;">Achievement Unlocked For</p>
                                <p style="margin: 0; font-size: 18px; color: {$textColor}; font-weight: 500;" class="text-dark">{$achievementDesc}</p>
                            </div>

                            <p style="margin: 0 0 30px; font-size: 16px; color: {$mutedColor}; text-align: center; line-height: 1.6;" class="text-muted">
                                This badge has been added to your profile. Keep up the great work!
                            </p>

                            <!-- CTA Button -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="text-align: center;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
                                            <tr>
                                                <td style="border-radius: 10px; background: linear-gradient(135deg, {$brandColor} 0%, #7c3aed 100%);" class="button-primary">
                                                    <a href="{$siteUrl}{$basePath}/achievements/badges" style="display: inline-block; padding: 16px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 10px;">View All Your Badges</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
HTML;

        return self::getEmailWrapper($content, $siteName, $siteUrl, $basePath, "You've earned the {$badgeName} badge!");
    }

    /**
     * Generate streak milestone email HTML
     */
    private static function generateStreakEmail(array $user, array $data, string $basePath, string $siteName, string $siteUrl): string
    {
        $firstName = htmlspecialchars($user['first_name']);
        $streakDays = $data['streak_days'];
        $textColor = self::TEXT_COLOR;
        $mutedColor = self::MUTED_COLOR;

        $content = <<<HTML
                    <!-- Header with gradient -->
                    <tr>
                        <td style="padding: 40px; text-align: center; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); border-radius: 16px 16px 0 0;">
                            <div style="font-size: 80px; margin-bottom: 20px;">&#128293;</div>
                            <h1 style="margin: 0 0 10px; font-size: 32px; font-weight: 800; color: #ffffff;">{$streakDays}-Day Streak!</h1>
                            <p style="margin: 0; font-size: 18px; color: rgba(255,255,255,0.9);">Incredible dedication, {$firstName}!</p>
                        </td>
                    </tr>

                    <!-- Main content -->
                    <tr>
                        <td style="background-color: #ffffff; padding: 40px; text-align: center;" class="email-container-inner">
                            <p style="margin: 0 0 30px; font-size: 16px; color: {$mutedColor}; line-height: 1.8;" class="text-muted">
                                You've logged in for {$streakDays} days in a row! Keep the momentum going for even bigger rewards.
                            </p>

                            <!-- CTA Button -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="text-align: center;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 0 auto;">
                                            <tr>
                                                <td style="border-radius: 10px; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);" class="button-primary">
                                                    <a href="{$siteUrl}{$basePath}/achievements" style="display: inline-block; padding: 16px 32px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 10px;">Continue Your Streak</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
HTML;

        return self::getEmailWrapper($content, $siteName, $siteUrl, $basePath, "You're on a {$streakDays}-day streak!");
    }
}
