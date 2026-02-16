<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\Mailer;
use Nexus\Models\Notification;
use Nexus\Models\User;
use Nexus\Services\WebPushService;

class NotificationDispatcher
{
    /**
     * Dispatch a notification (In-App + Email Traffic Cop)
     *
     * @param int $userId Target User ID
     * @param string $contextType 'global', 'group', 'thread'
     * @param int|null $contextId Group ID or Discussion ID
     * @param string $activityType 'new_topic', 'new_reply', 'mention'
     * @param string $content Plain text content for email preview
     * @param string $link relative link to the item
     * @param string $htmlContent Full HTML content for Instant Emails
     * @param bool $isOrganizer If true, applies strict Organizer Priority rules
     */
    public static function dispatch($userId, $contextType, $contextId, $activityType, $content, $link, $htmlContent, $isOrganizer = false)
    {
        // 1. ALWAYS create In-App Notification (The "Bell")
        // We use a simplified message for the bell, e.g. "Jasper replied to your post".
        // The implementation assumes the caller constructs a friendly message or we derive it here.
        // For now, we reuse $content as the message.
        Notification::create($userId, $content, $link, $activityType);

        // 2. CHECK Notification Settings Hierarchy
        $frequency = self::getFrequencySetting($userId, $contextType, $contextId);

        // 3. APPLY "Organizer Rule" (Set in Stone)
        // If this is a New Topic ($activityType == 'new_topic') AND target is organizer,
        // they MUST get it instantly unless they explicitly turned it OFF.
        if ($isOrganizer && $activityType === 'new_topic') {
            if ($frequency === null) $frequency = 'instant'; // Default for Organizer
        }

        // 4. Default for normal users if no setting found
        if ($frequency === null) {
            $frequency = 'daily'; // Default safetynet
        }

        // 5. TRAFFIC LIGHT LOGIC
        switch ($frequency) {
            case 'instant':
                // Queue as 'instant' with full HTML body for rapid processing
                self::queueNotification($userId, $activityType, $content, $link, 'instant', $htmlContent);
                break;

            case 'daily':
            case 'weekly':
                self::queueNotification($userId, $activityType, $content, $link, $frequency);
                break;

            case 'off':
                // Do nothing
                break;
        }
    }

    /**
     * Resolve the effective frequency setting based on hierarchy:
     * Thread > Group > Global
     */
    private static function getFrequencySetting($userId, $contextType, $contextId)
    {
        $db = Database::getInstance();

        // 1. Thread Level (Specific Discussion)
        // Only if context is 'thread'
        if ($contextType === 'thread') {
            $stmt = $db->prepare("SELECT frequency FROM notification_settings WHERE user_id = ? AND context_type = 'thread' AND context_id = ?");
            $stmt->execute([$userId, $contextId]);
            $res = $stmt->fetch();
            if ($res) return $res['frequency'];

            // Fallback: Need to know the Group ID for this thread to check Group Level.
            // This is tricky without passing parent ID. 
            // For now, let's assume the caller passes 'group' context if 'thread' fails? 
            // Or we handle hierarchy externally?
            // BETTER APPROACH:
            // The 'context_type' passed to dispatch() describes the EVENT source.
            // But settings are hierarchical.
            // If Dispatch called with 'thread' (e.g. Reply), we check: Thread Setting -> Group Setting -> Global.
            // We need to fetch the Group ID of the thread if we are in 'thread' mode.
            // For simplicity in Phase 1, let's assume caller might pass GroupID separately or we look it up.
            // Let's look it up if needed.
            if ($contextId) {
                $thread = \Nexus\Models\GroupDiscussion::findById($contextId);
                if ($thread) {
                    // Check Group Level
                    $groupFreq = self::getFrequencySetting($userId, 'group', $thread['group_id']);
                    if ($groupFreq) return $groupFreq;
                }
            }
        }

        // 2. Group Level
        if ($contextType === 'group' || ($contextType === 'thread')) {
            // If we are here, we might need to check group settings directly.
            // If $contextType was group, $contextId is group_id.
            // If $contextType was thread, we already tried resolving above. 
            // Let's simplify: This method just checks ONE specific combination.
            // We need a separate method for "ResolveHierarchy".
        }

        // REFACTOR: Let's do a direct lookup approach.

        // Check exact match first
        $sql = "SELECT frequency FROM notification_settings WHERE user_id = ? AND context_type = ? AND context_id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$userId, $contextType, $contextId]);
        $row = $stmt->fetch();
        if ($row) return $row['frequency'];

        // If 'thread', try 'group' parent
        if ($contextType === 'thread') {
            // Fetch Group ID from Thread
            $stmt = $db->prepare("SELECT group_id FROM group_discussions WHERE id = ?");
            $stmt->execute([$contextId]);
            $thread = $stmt->fetch();
            if ($thread) {
                return self::getFrequencySetting($userId, 'group', $thread['group_id']);
            }
        }

        // If 'group', try 'global'
        if ($contextType === 'group') {
            return self::getFrequencySetting($userId, 'global', 0);
        }

        // Global default (if not set in DB, return default from Config)
        if ($contextType === 'global') {
            // Get Tenant Config for default
            $tenant = \Nexus\Core\TenantContext::get();
            $config = json_decode($tenant['configuration'] ?? '{}', true);
            return $config['notifications']['default_frequency'] ?? 'daily';
        }

        return null;
    }

    private static function queueNotification($userId, $activityType, $content, $link, $frequency = 'daily', $emailBody = null)
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO notification_queue (user_id, activity_type, content_snippet, link, frequency, email_body, created_at, status) VALUES (?, ?, ?, ?, ?, ?, NOW(), 'pending')");
        // Truncate content for snippet
        $snippet = substr($content, 0, 250);
        $stmt->execute([$userId, $activityType, $snippet, $link, $frequency, $emailBody]);
    }

    /**
     * Dispatch a HOT MATCH notification
     * Sent when a 85%+ match is found for the user
     *
     * @param int $userId The user receiving the notification
     * @param array $match The match data including listing info
     */
    public static function dispatchHotMatch($userId, $match)
    {
        $listingTitle = $match['title'] ?? 'New Listing';
        $matchScore = (int)($match['match_score'] ?? 85);
        $userName = $match['user_name'] ?? 'Someone';
        $distance = $match['distance_km'] ?? null;
        $listingId = $match['id'] ?? 0;

        $distanceText = $distance !== null ? " ({$distance}km away)" : '';
        $content = "🔥 Hot Match! {$userName} posted \"{$listingTitle}\" - {$matchScore}% match{$distanceText}";
        $link = "/listings/{$listingId}";

        // Check user's match notification preferences
        $prefs = MatchingService::getPreferences($userId);
        if (empty($prefs['notify_hot_matches'])) {
            return; // User disabled hot match notifications
        }

        $frequency = $prefs['notification_frequency'] ?? 'daily';
        if ($frequency === 'never') {
            return;
        }

        // Create in-app notification
        Notification::create($userId, $content, $link, 'hot_match');

        // Queue email based on frequency preference
        if ($frequency !== 'never') {
            $htmlContent = self::buildHotMatchEmail($match, $matchScore);
            self::queueNotification($userId, 'hot_match', $content, $link, $frequency, $htmlContent);
        }
    }

    /**
     * Dispatch a MUTUAL MATCH notification
     * Sent when a mutual exchange opportunity is detected
     *
     * @param int $userId The user receiving the notification
     * @param array $match The match data
     * @param array $reciprocalInfo Info about the mutual opportunity
     */
    public static function dispatchMutualMatch($userId, $match, $reciprocalInfo = [])
    {
        $userName = $match['user_name'] ?? 'Someone';
        $theyOffer = $reciprocalInfo['they_offer'] ?? 'a skill you need';
        $youOffer = $reciprocalInfo['you_offer'] ?? 'something they need';
        $listingId = $match['id'] ?? 0;

        $content = "🤝 Mutual Match! {$userName} can help you with {$theyOffer}, and you can help them with {$youOffer}";
        $link = "/listings/{$listingId}";

        // Check user's match notification preferences
        $prefs = MatchingService::getPreferences($userId);
        if (empty($prefs['notify_mutual_matches'])) {
            return; // User disabled mutual match notifications
        }

        $frequency = $prefs['notification_frequency'] ?? 'daily';
        if ($frequency === 'never') {
            return;
        }

        // Create in-app notification
        Notification::create($userId, $content, $link, 'mutual_match');

        // Queue email based on frequency preference
        if ($frequency !== 'never') {
            $htmlContent = self::buildMutualMatchEmail($match, $reciprocalInfo);
            self::queueNotification($userId, 'mutual_match', $content, $link, $frequency, $htmlContent);
        }
    }

    /**
     * Dispatch a NEW MATCHES DIGEST notification
     * Sent as a periodic digest of new matches
     *
     * @param int $userId The user receiving the notification
     * @param array $matches Array of new matches
     * @param string $period 'daily' or 'weekly'
     */
    public static function dispatchMatchDigest($userId, $matches, $period = 'daily')
    {
        if (empty($matches)) {
            return;
        }

        $count = count($matches);
        $hotCount = count(array_filter($matches, fn($m) => ($m['match_score'] ?? 0) >= 85));
        $mutualCount = count(array_filter($matches, fn($m) => ($m['match_type'] ?? '') === 'mutual'));

        $content = "📊 Your {$period} match digest: {$count} new matches";
        if ($hotCount > 0) $content .= ", {$hotCount} hot";
        if ($mutualCount > 0) $content .= ", {$mutualCount} mutual";

        $link = "/matches";

        // Create in-app notification
        Notification::create($userId, $content, $link, 'match_digest');

        // Queue email
        $htmlContent = self::buildMatchDigestEmail($matches, $period, $hotCount, $mutualCount);
        self::queueNotification($userId, 'match_digest', $content, $link, 'instant', $htmlContent);
    }

    /**
     * Build HTML email for hot match notification
     * Uses the standalone template file for better maintainability
     */
    private static function buildHotMatchEmail($match, $matchScore)
    {
        // Get user info for the template
        $userId = $match['recipient_user_id'] ?? 0;
        $user = $userId ? User::find($userId) : null;
        $userName = $user['name'] ?? $user['first_name'] ?? 'there';

        // Get tenant name
        $tenant = \Nexus\Core\TenantContext::get();
        $tenantName = $tenant['name'] ?? 'Community';

        // Try to use the template file
        $templatePath = dirname(__DIR__, 2) . '/views/emails/match_hot.php';
        if (file_exists($templatePath)) {
            ob_start();
            include $templatePath;
            return ob_get_clean();
        }

        // Fallback to inline HTML if template doesn't exist
        $title = htmlspecialchars($match['title'] ?? 'New Listing');
        $posterName = htmlspecialchars($match['user_name'] ?? 'Someone');
        $distance = $match['distance_km'] ?? null;

        $distanceHtml = $distance !== null ? "<p style='color: #10b981;'>📍 {$distance} km away</p>" : '';

        return <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #f97316, #ef4444); padding: 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 24px;">🔥 Hot Match Found!</h1>
        <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0;">A {$matchScore}% compatible listing just appeared</p>
    </div>
    <div style="background: #f8fafc; padding: 24px; border-radius: 0 0 16px 16px; border: 1px solid #e2e8f0; border-top: none;">
        <h2 style="color: #1e293b; margin: 0 0 8px;">{$title}</h2>
        <p style="color: #6366f1; font-weight: 600; margin: 0 0 12px;">Posted by {$posterName}</p>
        {$distanceHtml}
    </div>
</div>
HTML;
    }

    /**
     * Build HTML email for mutual match notification
     * Uses the standalone template file for better maintainability
     */
    private static function buildMutualMatchEmail($match, $reciprocalInfo)
    {
        // Get user info for the template
        $userId = $match['recipient_user_id'] ?? 0;
        $user = $userId ? User::find($userId) : null;
        $userName = $user['name'] ?? $user['first_name'] ?? 'there';

        // Get tenant name
        $tenant = \Nexus\Core\TenantContext::get();
        $tenantName = $tenant['name'] ?? 'Community';

        // Try to use the template file
        $templatePath = dirname(__DIR__, 2) . '/views/emails/match_mutual.php';
        if (file_exists($templatePath)) {
            ob_start();
            include $templatePath;
            return ob_get_clean();
        }

        // Fallback to inline HTML if template doesn't exist
        $posterName = htmlspecialchars($match['user_name'] ?? 'Someone');
        $theyOffer = htmlspecialchars($reciprocalInfo['they_offer'] ?? 'a skill you need');
        $youOffer = htmlspecialchars($reciprocalInfo['you_offer'] ?? 'something they need');

        return <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #10b981, #06b6d4); padding: 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 24px;">🤝 Mutual Match!</h1>
        <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0;">A perfect exchange opportunity</p>
    </div>
    <div style="background: #f8fafc; padding: 24px; border-radius: 0 0 16px 16px; border: 1px solid #e2e8f0; border-top: none;">
        <h2 style="color: #1e293b; margin: 0 0 16px;">Exchange with {$posterName}</h2>
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin-bottom: 16px;">
            <p style="color: #64748b; margin: 0 0 8px; font-size: 14px;">They can help you with:</p>
            <p style="color: #10b981; font-weight: 600; margin: 0; font-size: 16px;">{$theyOffer}</p>
        </div>
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px;">
            <p style="color: #64748b; margin: 0 0 8px; font-size: 14px;">You can help them with:</p>
            <p style="color: #6366f1; font-weight: 600; margin: 0; font-size: 16px;">{$youOffer}</p>
        </div>
    </div>
</div>
HTML;
    }

    /**
     * Build HTML email for match digest
     * Uses the standalone template file for better maintainability
     */
    private static function buildMatchDigestEmail($matches, $period, $hotCount, $mutualCount)
    {
        // Get tenant name
        $tenant = \Nexus\Core\TenantContext::get();
        $tenantName = $tenant['name'] ?? 'Community';

        // For template - we need userName which we'll get from the calling context
        $userName = 'there'; // Default fallback

        // Build stats array for template
        $stats = [
            'hotCount' => $hotCount,
            'mutualCount' => $mutualCount,
            'totalCount' => count($matches)
        ];

        // Try to use the template file
        $templatePath = dirname(__DIR__, 2) . '/views/emails/match_digest.php';
        if (file_exists($templatePath)) {
            ob_start();
            include $templatePath;
            return ob_get_clean();
        }

        // Fallback to inline HTML if template doesn't exist
        $count = count($matches);
        $periodTitle = ucfirst($period);

        $matchListHtml = '';
        foreach (array_slice($matches, 0, 5) as $match) {
            $title = htmlspecialchars($match['title'] ?? 'Listing');
            $score = (int)($match['match_score'] ?? 0);
            $posterName = htmlspecialchars($match['user_name'] ?? 'Unknown');
            $scoreColor = $score >= 85 ? '#ef4444' : ($score >= 70 ? '#6366f1' : '#64748b');
            $matchListHtml .= <<<HTML
<div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; margin-bottom: 10px;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <p style="color: #1e293b; font-weight: 600; margin: 0;">{$title}</p>
            <p style="color: #64748b; font-size: 14px; margin: 4px 0 0;">by {$posterName}</p>
        </div>
        <span style="background: {$scoreColor}; color: white; padding: 4px 10px; border-radius: 20px; font-weight: 700; font-size: 14px;">{$score}%</span>
    </div>
</div>
HTML;
        }

        $statsHtml = '';
        if ($hotCount > 0) {
            $statsHtml .= "<span style='background: #fef2f2; color: #ef4444; padding: 6px 12px; border-radius: 20px; margin-right: 8px;'>🔥 {$hotCount} Hot</span>";
        }
        if ($mutualCount > 0) {
            $statsHtml .= "<span style='background: #ecfdf5; color: #10b981; padding: 6px 12px; border-radius: 20px;'>🤝 {$mutualCount} Mutual</span>";
        }

        return <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #6366f1, #8b5cf6); padding: 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 24px;">📊 Your {$periodTitle} Match Digest</h1>
        <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0;">{$count} new matches waiting for you</p>
    </div>
    <div style="background: #f8fafc; padding: 24px; border-radius: 0 0 16px 16px; border: 1px solid #e2e8f0; border-top: none;">
        <div style="margin-bottom: 20px; text-align: center;">{$statsHtml}</div>
        <h3 style="color: #1e293b; margin: 0 0 16px;">Top Matches</h3>
        {$matchListHtml}
    </div>
</div>
HTML;
    }

    // =========================================================================
    // MATCH APPROVAL WORKFLOW NOTIFICATIONS
    // =========================================================================

    /**
     * Dispatch notification to brokers/admins when a match needs approval
     *
     * @param int $brokerId The broker/admin user ID
     * @param string $userName Name of user who would receive the match
     * @param string $listingTitle Title of the listing
     * @param int $requestId The approval request ID
     */
    public static function dispatchMatchApprovalRequest($brokerId, $userName, $listingTitle, $requestId)
    {
        $content = "📋 Match needs approval: {$userName} matched with \"{$listingTitle}\"";
        $link = "/admin-legacy/match-approvals";

        // Create in-app notification
        Notification::create($brokerId, $content, $link, 'match_approval_request');

        // Queue email (instant for admins)
        $htmlContent = self::buildMatchApprovalRequestEmail($userName, $listingTitle, $requestId);
        self::queueNotification($brokerId, 'match_approval_request', $content, $link, 'instant', $htmlContent);
    }

    /**
     * Dispatch notification to user when their match is approved
     *
     * @param int $userId The user receiving the notification
     * @param string $listingTitle Title of the listing
     * @param int $listingId The listing ID
     * @param float $matchScore The match score
     */
    public static function dispatchMatchApproved($userId, $listingTitle, $listingId, $matchScore = 0)
    {
        $content = "✅ Great news! You've been matched with \"{$listingTitle}\"";
        $link = "/listings/{$listingId}";

        // Create in-app notification
        Notification::create($userId, $content, $link, 'match_approved');

        // Queue email
        $htmlContent = self::buildMatchApprovedEmail($listingTitle, $listingId, $matchScore);
        self::queueNotification($userId, 'match_approved', $content, $link, 'instant', $htmlContent);
    }

    /**
     * Dispatch notification to user when their match is rejected
     *
     * @param int $userId The user receiving the notification
     * @param string $listingTitle Title of the listing
     * @param string $reason The rejection reason
     */
    public static function dispatchMatchRejected($userId, $listingTitle, $reason = '')
    {
        $content = "ℹ️ Match update: \"{$listingTitle}\" wasn't suitable at this time";
        if (!empty($reason)) {
            $content .= ". Reason: {$reason}";
        }
        $link = "/matches";

        // Create in-app notification
        Notification::create($userId, $content, $link, 'match_rejected');

        // Queue email
        $htmlContent = self::buildMatchRejectedEmail($listingTitle, $reason);
        self::queueNotification($userId, 'match_rejected', $content, $link, 'instant', $htmlContent);
    }

    /**
     * Build HTML email for match approval request (sent to brokers)
     */
    private static function buildMatchApprovalRequestEmail($userName, $listingTitle, $requestId)
    {
        $tenant = \Nexus\Core\TenantContext::get();
        $tenantName = $tenant['name'] ?? 'Community';
        $basePath = \Nexus\Core\TenantContext::getBasePath();

        return <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #f59e0b, #d97706); padding: 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 24px;">📋 Match Needs Approval</h1>
        <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0;">{$tenantName}</p>
    </div>
    <div style="background: #f8fafc; padding: 24px; border-radius: 0 0 16px 16px; border: 1px solid #e2e8f0; border-top: none;">
        <p style="color: #1e293b; font-size: 16px; line-height: 1.6;">A new match is waiting for your approval:</p>
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin: 16px 0;">
            <p style="color: #64748b; margin: 0 0 8px; font-size: 14px;">Member:</p>
            <p style="color: #1e293b; font-weight: 600; margin: 0 0 16px; font-size: 18px;">{$userName}</p>
            <p style="color: #64748b; margin: 0 0 8px; font-size: 14px;">Matched with listing:</p>
            <p style="color: #6366f1; font-weight: 600; margin: 0; font-size: 18px;">{$listingTitle}</p>
        </div>
        <p style="color: #64748b; font-size: 14px; line-height: 1.6;">
            Please review this match to ensure the member is suitable (mobility, health considerations) and the activity is within insurance coverage.
        </p>
        <div style="text-align: center; margin-top: 24px;">
            <a href="{$basePath}/admin-legacy/match-approvals" style="display: inline-block; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-weight: 600;">Review Match</a>
        </div>
    </div>
</div>
HTML;
    }

    /**
     * Build HTML email for match approved (sent to user)
     */
    private static function buildMatchApprovedEmail($listingTitle, $listingId, $matchScore)
    {
        $tenant = \Nexus\Core\TenantContext::get();
        $tenantName = $tenant['name'] ?? 'Community';
        $basePath = \Nexus\Core\TenantContext::getBasePath();
        $scoreText = $matchScore > 0 ? " ({$matchScore}% match)" : "";

        return <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #22c55e, #16a34a); padding: 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 24px;">✅ You've Been Matched!</h1>
        <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0;">{$tenantName}</p>
    </div>
    <div style="background: #f8fafc; padding: 24px; border-radius: 0 0 16px 16px; border: 1px solid #e2e8f0; border-top: none;">
        <p style="color: #1e293b; font-size: 16px; line-height: 1.6;">Great news! A coordinator has approved a match for you:</p>
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin: 16px 0; text-align: center;">
            <p style="color: #22c55e; font-weight: 600; margin: 0; font-size: 20px;">{$listingTitle}</p>
            <p style="color: #64748b; margin: 8px 0 0; font-size: 14px;">{$scoreText}</p>
        </div>
        <p style="color: #64748b; font-size: 14px; line-height: 1.6;">
            Click below to view the listing and get in touch with the member.
        </p>
        <div style="text-align: center; margin-top: 24px;">
            <a href="{$basePath}/listings/{$listingId}" style="display: inline-block; background: linear-gradient(135deg, #22c55e, #16a34a); color: white; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-weight: 600;">View Match</a>
        </div>
    </div>
</div>
HTML;
    }

    /**
     * Build HTML email for match rejected (sent to user)
     */
    private static function buildMatchRejectedEmail($listingTitle, $reason)
    {
        $tenant = \Nexus\Core\TenantContext::get();
        $tenantName = $tenant['name'] ?? 'Community';
        $basePath = \Nexus\Core\TenantContext::getBasePath();

        $reasonHtml = '';
        if (!empty($reason)) {
            $reasonHtml = <<<HTML
<div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; padding: 16px; margin: 16px 0;">
    <p style="color: #dc2626; font-weight: 600; margin: 0 0 8px; font-size: 14px;">Reason:</p>
    <p style="color: #7f1d1d; margin: 0; font-size: 14px; line-height: 1.5;">{$reason}</p>
</div>
HTML;
        }

        return <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #6366f1, #8b5cf6); padding: 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 24px;">Match Update</h1>
        <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0;">{$tenantName}</p>
    </div>
    <div style="background: #f8fafc; padding: 24px; border-radius: 0 0 16px 16px; border: 1px solid #e2e8f0; border-top: none;">
        <p style="color: #1e293b; font-size: 16px; line-height: 1.6;">Unfortunately, a coordinator has determined that the following match wasn't suitable at this time:</p>
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin: 16px 0; text-align: center;">
            <p style="color: #64748b; font-weight: 600; margin: 0; font-size: 18px;">{$listingTitle}</p>
        </div>
        {$reasonHtml}
        <p style="color: #64748b; font-size: 14px; line-height: 1.6;">
            Don't worry - there are plenty of other opportunities in your community! Browse more matches to find a good fit.
        </p>
        <div style="text-align: center; margin-top: 24px;">
            <a href="{$basePath}/matches" style="display: inline-block; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-weight: 600;">Browse Matches</a>
        </div>
    </div>
</div>
HTML;
    }

    // =========================================================================
    // SIMPLE NOTIFICATION METHODS (for broker control services)
    // =========================================================================

    /**
     * Send a simple notification to a user
     * Simplified interface for broker control services
     *
     * @param int $userId Target user ID
     * @param string $type Notification type (e.g., 'exchange_request_received')
     * @param array $data Notification data
     */
    /**
     * Send credit received email to a user (no in-app notification — that's handled by Transaction::create)
     */
    public static function sendCreditEmail(int $recipientUserId, string $senderName, float $amount, string $description = ''): void
    {
        try {
            $user = Database::query(
                "SELECT email, name, first_name FROM users WHERE id = ?",
                [$recipientUserId]
            )->fetch();

            if (!$user || empty($user['email'])) {
                return;
            }

            $recipientName = $user['first_name'] ?? $user['name'] ?? 'there';
            $tenantName = \Nexus\Core\TenantContext::getSetting('site_name', 'Project NEXUS');
            $baseUrl = \Nexus\Core\TenantContext::getSetting('site_url', 'https://app.project-nexus.ie');
            $basePath = \Nexus\Core\TenantContext::getBasePath();
            $walletUrl = $baseUrl . $basePath . '/wallet';

            $hourLabel = $amount == 1 ? 'hour' : 'hours';
            $subject = htmlspecialchars($senderName) . " sent you {$amount} {$hourLabel} on {$tenantName}";
            $emailBody = self::buildCreditReceivedEmail(
                htmlspecialchars($recipientName),
                htmlspecialchars($senderName),
                $amount,
                htmlspecialchars($description),
                $walletUrl,
                htmlspecialchars($tenantName)
            );

            $mailer = new \Nexus\Core\Mailer();
            $mailer->send($user['email'], $subject, $emailBody);
        } catch (\Exception $e) {
            error_log("NotificationDispatcher::sendCreditEmail failed: " . $e->getMessage());
        }
    }

    public static function send(int $userId, string $type, array $data = []): void
    {
        // Build content and link based on notification type
        $content = self::buildNotificationContent($type, $data);
        $link = self::buildNotificationLink($type, $data);

        // Create in-app notification
        Notification::create($userId, $content, $link, $type);

        // Send email immediately for exchange notifications (truly instant)
        self::sendExchangeEmailImmediately($userId, $type, $data, $content, $link);
    }

    /**
     * Notify all admins/brokers of the current tenant
     *
     * @param string $type Notification type
     * @param array $data Notification data
     * @param string $message Optional custom message
     */
    public static function notifyAdmins(string $type, array $data = [], string $message = ''): void
    {
        $tenantId = \Nexus\Core\TenantContext::getId();

        // Get all admin users for this tenant
        $stmt = Database::query(
            "SELECT id FROM users WHERE tenant_id = ? AND role IN ('admin', 'broker', 'coordinator') AND status = 'active'",
            [$tenantId]
        );

        while ($admin = $stmt->fetch()) {
            $content = $message ?: self::buildNotificationContent($type, $data);
            $link = self::buildNotificationLink($type, $data);

            Notification::create($admin['id'], $content, $link, $type);

            // Send email immediately for exchange notifications (truly instant)
            self::sendExchangeEmailImmediately($admin['id'], $type, $data, $content, $link);
        }
    }

    /**
     * Build notification content based on type
     */
    private static function buildNotificationContent(string $type, array $data): string
    {
        switch ($type) {
            case 'exchange_request_received':
                return "📥 New exchange request for your listing";
            case 'exchange_request_declined':
                $reason = !empty($data['reason']) ? ": {$data['reason']}" : '';
                return "❌ Your exchange request was declined{$reason}";
            case 'exchange_approved':
                return "✅ Your exchange has been approved! You can now begin.";
            case 'exchange_rejected':
                $reason = !empty($data['reason']) ? ": {$data['reason']}" : '';
                return "❌ Exchange was not approved{$reason}";
            case 'exchange_completed':
                $hours = $data['hours'] ?? 0;
                return "🎉 Exchange completed! {$hours} hours transferred.";
            case 'exchange_cancelled':
                return "⚠️ Exchange was cancelled";
            case 'exchange_disputed':
                return "⚠️ Exchange has conflicting hour confirmations - broker review needed";
            case 'exchange_accepted':
                return "✅ Your exchange request was accepted! You can now coordinate the service.";
            case 'exchange_pending_broker':
                return "⏳ Exchange accepted - awaiting coordinator approval";
            case 'exchange_started':
                return "🚀 Exchange has started! Service is now in progress.";
            case 'exchange_ready_confirmation':
                $hours = $data['proposed_hours'] ?? 0;
                return "✋ Exchange complete - please confirm {$hours} hours worked";
            case 'listing_risk_tagged':
                $level = $data['risk_level'] ?? 'unknown';
                $title = $data['listing_title'] ?? 'Listing';
                return "⚠️ Listing '{$title}' tagged as {$level} risk";
            case 'credit_received':
                $senderName = $data['sender_name'] ?? 'Someone';
                $amount = $data['amount'] ?? 0;
                return "💰 {$senderName} sent you {$amount} hour" . ($amount != 1 ? 's' : '');
            default:
                return "Notification: {$type}";
        }
    }

    /**
     * Build notification link based on type
     */
    private static function buildNotificationLink(string $type, array $data): string
    {
        switch ($type) {
            case 'exchange_request_received':
            case 'exchange_approved':
            case 'exchange_rejected':
            case 'exchange_completed':
            case 'exchange_cancelled':
            case 'exchange_disputed':
            case 'exchange_accepted':
            case 'exchange_started':
            case 'exchange_ready_confirmation':
                $exchangeId = $data['exchange_id'] ?? 0;
                return "/exchanges/{$exchangeId}";
            case 'exchange_pending_broker':
                $exchangeId = $data['exchange_id'] ?? 0;
                return "/admin-legacy/broker-controls/exchanges/{$exchangeId}";
            case 'exchange_request_declined':
                return "/exchanges";
            case 'listing_risk_tagged':
                return "/admin-legacy/broker-controls/risk-tags";
            case 'credit_received':
                return "/wallet";
            default:
                return "/notifications";
        }
    }

    /**
     * Send exchange email immediately (no cron delay)
     */
    private static function sendExchangeEmailImmediately(int $userId, string $type, array $data, string $content, string $link): void
    {
        // Only send for exchange and credit notifications
        if (strpos($type, 'exchange_') !== 0 && $type !== 'credit_received') {
            return;
        }

        try {
            // Get user email
            $user = Database::query(
                "SELECT email, name, first_name FROM users WHERE id = ?",
                [$userId]
            )->fetch();

            if (!$user || empty($user['email'])) {
                return;
            }

            // Build full URL
            $baseUrl = \Nexus\Core\TenantContext::getSetting('site_url', 'https://app.project-nexus.ie');
            $basePath = \Nexus\Core\TenantContext::getBasePath();
            $fullUrl = $baseUrl . $basePath . $link;

            $mailer = new \Nexus\Core\Mailer();

            if ($type === 'credit_received') {
                // Simple credit received email
                $senderName = htmlspecialchars($data['sender_name'] ?? 'A member');
                $amount = (float)($data['amount'] ?? 0);
                $description = htmlspecialchars($data['description'] ?? '');
                $recipientName = htmlspecialchars($user['first_name'] ?? $user['name'] ?? 'there');
                $tenantName = htmlspecialchars(\Nexus\Core\TenantContext::getSetting('site_name', 'Project NEXUS'));

                $subject = "{$senderName} sent you {$amount} hour" . ($amount != 1 ? 's' : '') . " on {$tenantName}";
                $emailBody = self::buildCreditReceivedEmail($recipientName, $senderName, $amount, $description, $fullUrl, $tenantName);
                $mailer->send($user['email'], $subject, $emailBody);
            } else {
                // Exchange notification email
                $exchangeDetails = self::getExchangeDetailsForEmail($data['exchange_id'] ?? 0);
                $emailBody = self::buildRichExchangeEmail($type, $data, $user, $exchangeDetails, $fullUrl);
                $subject = self::getExchangeEmailSubject($type, $exchangeDetails);
                $mailer->send($user['email'], $subject, $emailBody);
            }

        } catch (\Exception $e) {
            error_log("NotificationDispatcher: Failed to send exchange email - " . $e->getMessage());
        }
    }

    /**
     * Get exchange details for email content
     */
    private static function getExchangeDetailsForEmail(int $exchangeId): array
    {
        if ($exchangeId <= 0) {
            return [];
        }

        try {
            $stmt = Database::query(
                "SELECT e.*,
                        l.title as listing_title, l.type as listing_type, l.description as listing_description,
                        req.name as requester_name, req.first_name as requester_first_name, req.avatar_url as requester_avatar,
                        prov.name as provider_name, prov.first_name as provider_first_name, prov.avatar_url as provider_avatar
                 FROM exchange_requests e
                 JOIN listings l ON e.listing_id = l.id
                 JOIN users req ON e.requester_id = req.id
                 JOIN users prov ON e.provider_id = prov.id
                 WHERE e.id = ?",
                [$exchangeId]
            );
            return $stmt->fetch() ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get email subject for exchange notifications
     */
    /**
     * Build HTML email for credit received notification
     */
    private static function buildCreditReceivedEmail(string $recipientName, string $senderName, float $amount, string $description, string $walletUrl, string $tenantName): string
    {
        $amountDisplay = $amount . ' hour' . ($amount != 1 ? 's' : '');
        $descriptionHtml = $description ? "<p style=\"margin:12px 0 0;padding:12px;background:#f0f0f0;border-radius:8px;font-style:italic;color:#555;\">\"{$description}\"</p>" : '';

        return <<<HTML
<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f4f4f5;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
  <tr><td style="background:linear-gradient(135deg,#10b981,#059669);padding:32px;text-align:center;">
    <h1 style="margin:0;color:#fff;font-size:24px;">💰 You received time credits!</h1>
  </td></tr>
  <tr><td style="padding:32px;">
    <p style="margin:0 0 16px;font-size:16px;color:#374151;">Hi {$recipientName},</p>
    <p style="margin:0 0 16px;font-size:16px;color:#374151;">
      <strong>{$senderName}</strong> has sent you <strong>{$amountDisplay}</strong> on {$tenantName}.
    </p>
    {$descriptionHtml}
    <div style="text-align:center;margin:28px 0;">
      <a href="{$walletUrl}" style="display:inline-block;padding:14px 32px;background:#10b981;color:#fff;text-decoration:none;border-radius:8px;font-weight:600;font-size:16px;">View Your Wallet</a>
    </div>
  </td></tr>
  <tr><td style="padding:16px 32px;background:#f9fafb;border-top:1px solid #e5e7eb;text-align:center;">
    <p style="margin:0;font-size:12px;color:#9ca3af;">{$tenantName} — Time credits that strengthen communities</p>
  </td></tr>
</table>
</td></tr></table>
</body></html>
HTML;
    }

    private static function getExchangeEmailSubject(string $type, array $details): string
    {
        $listingTitle = $details['listing_title'] ?? 'your listing';
        $shortTitle = strlen($listingTitle) > 30 ? substr($listingTitle, 0, 30) . '...' : $listingTitle;

        $subjects = [
            'exchange_request_received' => "📥 New exchange request for \"{$shortTitle}\"",
            'exchange_request_declined' => "Exchange request declined",
            'exchange_approved' => "✅ Exchange approved by coordinator - Ready to begin!",
            'exchange_rejected' => "Exchange not approved",
            'exchange_completed' => "🎉 Exchange completed - Hours transferred!",
            'exchange_cancelled' => "Exchange cancelled",
            'exchange_disputed' => "⚠️ Exchange needs broker review",
            // New notification types
            'exchange_accepted' => "✅ Your exchange request was accepted!",
            'exchange_pending_broker' => "⏳ Exchange accepted - Awaiting coordinator approval",
            'exchange_started' => "🚀 Exchange started - Service in progress",
            'exchange_ready_confirmation' => "✋ Action needed: Confirm your exchange hours",
        ];

        return $subjects[$type] ?? "Exchange update";
    }

    /**
     * Build rich HTML email for exchange notifications
     */
    private static function buildRichExchangeEmail(string $type, array $data, array $user, array $details, string $actionUrl): string
    {
        $tenant = \Nexus\Core\TenantContext::get();
        $tenantName = $tenant['name'] ?? 'Community';
        $tenantColor = $tenant['primary_color'] ?? '#6366f1';

        $userName = $user['first_name'] ?? $user['name'] ?? 'there';
        $listingTitle = $details['listing_title'] ?? 'Service Exchange';
        $listingType = $details['listing_type'] ?? 'offer';
        $proposedHours = $details['proposed_hours'] ?? $data['hours'] ?? 0;
        $requesterName = $details['requester_first_name'] ?? $details['requester_name'] ?? 'A member';
        $providerName = $details['provider_first_name'] ?? $details['provider_name'] ?? 'Provider';

        // Get type-specific content
        $emailConfig = self::getExchangeEmailConfig($type, $data, $details);

        $year = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$emailConfig['title']}</title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f3f4f6;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f3f4f6; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width: 600px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background: {$emailConfig['gradient']}; padding: 32px 24px; text-align: center;">
                            <div style="font-size: 48px; margin-bottom: 12px;">{$emailConfig['icon']}</div>
                            <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 700;">{$emailConfig['title']}</h1>
                            <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0; font-size: 14px;">{$tenantName}</p>
                        </td>
                    </tr>

                    <!-- Greeting -->
                    <tr>
                        <td style="padding: 32px 24px 16px;">
                            <p style="margin: 0; font-size: 18px; color: #111827;">Hi {$userName},</p>
                        </td>
                    </tr>

                    <!-- Main Message -->
                    <tr>
                        <td style="padding: 0 24px 24px;">
                            <p style="margin: 0; font-size: 16px; color: #374151; line-height: 1.6;">
                                {$emailConfig['message']}
                            </p>
                        </td>
                    </tr>

                    <!-- Exchange Details Card -->
                    <tr>
                        <td style="padding: 0 24px 24px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f9fafb; border-radius: 12px; border: 1px solid #e5e7eb;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td style="padding-bottom: 16px; border-bottom: 1px solid #e5e7eb;">
                                                    <p style="margin: 0 0 4px; font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">Service</p>
                                                    <p style="margin: 0; font-size: 18px; color: #111827; font-weight: 600;">{$listingTitle}</p>
                                                    <span style="display: inline-block; margin-top: 8px; padding: 4px 12px; font-size: 12px; font-weight: 500; border-radius: 999px; background-color: {$emailConfig['typeColor']}; color: {$emailConfig['typeText']};">
                                                        {$emailConfig['typeBadge']}
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding-top: 16px;">
                                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                                        <tr>
                                                            <td width="50%" style="vertical-align: top;">
                                                                <p style="margin: 0 0 4px; font-size: 12px; color: #6b7280;">Requester</p>
                                                                <p style="margin: 0; font-size: 14px; color: #111827; font-weight: 500;">{$requesterName}</p>
                                                            </td>
                                                            <td width="50%" style="vertical-align: top;">
                                                                <p style="margin: 0 0 4px; font-size: 12px; color: #6b7280;">Provider</p>
                                                                <p style="margin: 0; font-size: 14px; color: #111827; font-weight: 500;">{$providerName}</p>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                            {$emailConfig['extraDetails']}
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {$emailConfig['alertBox']}

                    <!-- CTA Button -->
                    <tr>
                        <td style="padding: 0 24px 32px; text-align: center;">
                            <a href="{$actionUrl}" style="display: inline-block; background: {$emailConfig['gradient']}; color: #ffffff; padding: 14px 32px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 16px;">
                                {$emailConfig['buttonText']}
                            </a>
                        </td>
                    </tr>

                    <!-- Help Text -->
                    <tr>
                        <td style="padding: 0 24px 24px;">
                            <p style="margin: 0; font-size: 14px; color: #6b7280; text-align: center;">
                                {$emailConfig['helpText']}
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 24px; border-top: 1px solid #e5e7eb;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="text-align: center;">
                                        <p style="margin: 0 0 8px; font-size: 14px; color: #6b7280;">
                                            This email was sent by {$tenantName}
                                        </p>
                                        <p style="margin: 0; font-size: 12px; color: #9ca3af;">
                                            © {$year} Project NEXUS. All rights reserved.
                                        </p>
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
     * Get configuration for each exchange email type
     */
    private static function getExchangeEmailConfig(string $type, array $data, array $details): array
    {
        $hours = $details['proposed_hours'] ?? $data['hours'] ?? 0;
        $finalHours = $details['final_hours'] ?? $data['hours'] ?? $hours;
        $reason = $data['reason'] ?? '';
        $listingType = $details['listing_type'] ?? 'offer';
        $requesterName = $details['requester_first_name'] ?? $details['requester_name'] ?? 'A member';
        $providerName = $details['provider_first_name'] ?? $details['provider_name'] ?? 'the provider';

        // Type badge styling
        $typeColor = $listingType === 'offer' ? '#dcfce7' : '#fef3c7';
        $typeText = $listingType === 'offer' ? '#166534' : '#92400e';
        $typeBadge = $listingType === 'offer' ? 'Offering' : 'Requesting';

        $configs = [
            'exchange_request_received' => [
                'gradient' => 'linear-gradient(135deg, #6366f1, #8b5cf6)',
                'icon' => '📥',
                'title' => 'New Exchange Request',
                'message' => "<strong>{$requesterName}</strong> would like to exchange services with you! They've proposed <strong>{$hours} hour(s)</strong> for this exchange.",
                'buttonText' => 'Review Request',
                'helpText' => 'You can accept or decline this request from your exchanges dashboard.',
                'typeColor' => $typeColor,
                'typeText' => $typeText,
                'typeBadge' => $typeBadge,
                'extraDetails' => $hours > 0 ? "
                    <tr>
                        <td style=\"padding-top: 16px; border-top: 1px solid #e5e7eb; margin-top: 16px;\">
                            <p style=\"margin: 0 0 4px; font-size: 12px; color: #6b7280;\">Proposed Hours</p>
                            <p style=\"margin: 0; font-size: 24px; color: #6366f1; font-weight: 700;\">{$hours}h</p>
                        </td>
                    </tr>
                " : '',
                'alertBox' => '',
            ],
            'exchange_request_declined' => [
                'gradient' => 'linear-gradient(135deg, #ef4444, #dc2626)',
                'icon' => '❌',
                'title' => 'Request Declined',
                'message' => "Unfortunately, <strong>{$providerName}</strong> has declined your exchange request." . ($reason ? " They provided this reason: <em>\"{$reason}\"</em>" : ''),
                'buttonText' => 'Browse Other Listings',
                'helpText' => "Don't worry - there are plenty of other members who might be a great match!",
                'typeColor' => $typeColor,
                'typeText' => $typeText,
                'typeBadge' => $typeBadge,
                'extraDetails' => '',
                'alertBox' => $reason ? "
                    <tr>
                        <td style=\"padding: 0 24px 24px;\">
                            <table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"background-color: #fef2f2; border-radius: 8px; border-left: 4px solid #ef4444;\">
                                <tr>
                                    <td style=\"padding: 16px;\">
                                        <p style=\"margin: 0 0 4px; font-size: 12px; color: #991b1b; font-weight: 600;\">Reason provided:</p>
                                        <p style=\"margin: 0; font-size: 14px; color: #7f1d1d;\">{$reason}</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                " : '',
            ],
            'exchange_approved' => [
                'gradient' => 'linear-gradient(135deg, #10b981, #059669)',
                'icon' => '✅',
                'title' => 'Exchange Approved!',
                'message' => "Great news! Your exchange has been approved by a coordinator. You can now begin the service exchange.",
                'buttonText' => 'Start Exchange',
                'helpText' => 'Once you begin, remember to confirm the hours when the service is complete.',
                'typeColor' => $typeColor,
                'typeText' => $typeText,
                'typeBadge' => $typeBadge,
                'extraDetails' => "
                    <tr>
                        <td style=\"padding-top: 16px; border-top: 1px solid #e5e7eb; margin-top: 16px;\">
                            <p style=\"margin: 0 0 4px; font-size: 12px; color: #6b7280;\">Approved Hours</p>
                            <p style=\"margin: 0; font-size: 24px; color: #10b981; font-weight: 700;\">{$hours}h</p>
                        </td>
                    </tr>
                ",
                'alertBox' => "
                    <tr>
                        <td style=\"padding: 0 24px 24px;\">
                            <table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"background-color: #ecfdf5; border-radius: 8px; border-left: 4px solid #10b981;\">
                                <tr>
                                    <td style=\"padding: 16px;\">
                                        <p style=\"margin: 0; font-size: 14px; color: #065f46;\">
                                            💡 <strong>Next step:</strong> Contact the other party to arrange when and where the service will take place.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                ",
            ],
            'exchange_rejected' => [
                'gradient' => 'linear-gradient(135deg, #ef4444, #dc2626)',
                'icon' => '❌',
                'title' => 'Exchange Not Approved',
                'message' => "A coordinator has reviewed this exchange and was unable to approve it at this time." . ($reason ? " Reason: <em>\"{$reason}\"</em>" : ''),
                'buttonText' => 'View Details',
                'helpText' => 'If you have questions about this decision, please contact your community coordinator.',
                'typeColor' => $typeColor,
                'typeText' => $typeText,
                'typeBadge' => $typeBadge,
                'extraDetails' => '',
                'alertBox' => $reason ? "
                    <tr>
                        <td style=\"padding: 0 24px 24px;\">
                            <table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"background-color: #fef2f2; border-radius: 8px; border-left: 4px solid #ef4444;\">
                                <tr>
                                    <td style=\"padding: 16px;\">
                                        <p style=\"margin: 0 0 4px; font-size: 12px; color: #991b1b; font-weight: 600;\">Coordinator's note:</p>
                                        <p style=\"margin: 0; font-size: 14px; color: #7f1d1d;\">{$reason}</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                " : '',
            ],
            'exchange_completed' => [
                'gradient' => 'linear-gradient(135deg, #10b981, #059669)',
                'icon' => '🎉',
                'title' => 'Exchange Completed!',
                'message' => "Congratulations! Your exchange has been completed successfully. <strong>{$finalHours} hour(s)</strong> have been transferred.",
                'buttonText' => 'View in Wallet',
                'helpText' => 'Thank you for being an active member of our time-sharing community!',
                'typeColor' => $typeColor,
                'typeText' => $typeText,
                'typeBadge' => $typeBadge,
                'extraDetails' => "
                    <tr>
                        <td style=\"padding-top: 16px; border-top: 1px solid #e5e7eb; margin-top: 16px;\">
                            <p style=\"margin: 0 0 4px; font-size: 12px; color: #6b7280;\">Hours Transferred</p>
                            <p style=\"margin: 0; font-size: 32px; color: #10b981; font-weight: 700;\">{$finalHours}h</p>
                        </td>
                    </tr>
                ",
                'alertBox' => "
                    <tr>
                        <td style=\"padding: 0 24px 24px;\">
                            <table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"background-color: #ecfdf5; border-radius: 8px; border-left: 4px solid #10b981;\">
                                <tr>
                                    <td style=\"padding: 16px;\">
                                        <p style=\"margin: 0; font-size: 14px; color: #065f46;\">
                                            🌟 <strong>Well done!</strong> Your time credit balance has been updated. Consider leaving a review for the other member!
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                ",
            ],
            'exchange_cancelled' => [
                'gradient' => 'linear-gradient(135deg, #f59e0b, #d97706)',
                'icon' => '⚠️',
                'title' => 'Exchange Cancelled',
                'message' => "This exchange has been cancelled." . ($reason ? " Reason: <em>\"{$reason}\"</em>" : ''),
                'buttonText' => 'View Details',
                'helpText' => 'No time credits have been transferred.',
                'typeColor' => $typeColor,
                'typeText' => $typeText,
                'typeBadge' => $typeBadge,
                'extraDetails' => '',
                'alertBox' => '',
            ],
            'exchange_disputed' => [
                'gradient' => 'linear-gradient(135deg, #ef4444, #dc2626)',
                'icon' => '⚠️',
                'title' => 'Exchange Needs Review',
                'message' => "There's a discrepancy in the hours confirmed by both parties. A coordinator will review this exchange and help resolve the difference.",
                'buttonText' => 'View Exchange',
                'helpText' => 'A coordinator will be in touch to help resolve this. No action is needed from you right now.',
                'typeColor' => $typeColor,
                'typeText' => $typeText,
                'typeBadge' => $typeBadge,
                'extraDetails' => !empty($data['requester_hours']) && !empty($data['provider_hours']) ? "
                    <tr>
                        <td style=\"padding-top: 16px; border-top: 1px solid #e5e7eb; margin-top: 16px;\">
                            <table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\">
                                <tr>
                                    <td width=\"50%\" style=\"text-align: center; padding: 8px;\">
                                        <p style=\"margin: 0 0 4px; font-size: 12px; color: #6b7280;\">Requester confirmed</p>
                                        <p style=\"margin: 0; font-size: 20px; color: #ef4444; font-weight: 700;\">{$data['requester_hours']}h</p>
                                    </td>
                                    <td width=\"50%\" style=\"text-align: center; padding: 8px;\">
                                        <p style=\"margin: 0 0 4px; font-size: 12px; color: #6b7280;\">Provider confirmed</p>
                                        <p style=\"margin: 0; font-size: 20px; color: #ef4444; font-weight: 700;\">{$data['provider_hours']}h</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                " : '',
                'alertBox' => "
                    <tr>
                        <td style=\"padding: 0 24px 24px;\">
                            <table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"background-color: #fef2f2; border-radius: 8px; border-left: 4px solid #ef4444;\">
                                <tr>
                                    <td style=\"padding: 16px;\">
                                        <p style=\"margin: 0; font-size: 14px; color: #7f1d1d;\">
                                            ⏳ <strong>Under review:</strong> A coordinator will review the confirmed hours and make a fair decision.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                ",
            ],
            // NEW: Provider accepted (no broker needed)
            'exchange_accepted' => [
                'gradient' => 'linear-gradient(135deg, #10b981, #059669)',
                'icon' => '✅',
                'title' => 'Request Accepted!',
                'message' => "Great news! <strong>{$providerName}</strong> has accepted your exchange request. You can now coordinate when and where the service will take place.",
                'buttonText' => 'View Exchange',
                'helpText' => 'Contact the provider to arrange the details of your exchange.',
                'typeColor' => $typeColor,
                'typeText' => $typeText,
                'typeBadge' => $typeBadge,
                'extraDetails' => "
                    <tr>
                        <td style=\"padding-top: 16px; border-top: 1px solid #e5e7eb; margin-top: 16px;\">
                            <p style=\"margin: 0 0 4px; font-size: 12px; color: #6b7280;\">Agreed Hours</p>
                            <p style=\"margin: 0; font-size: 24px; color: #10b981; font-weight: 700;\">{$hours}h</p>
                        </td>
                    </tr>
                ",
                'alertBox' => "
                    <tr>
                        <td style=\"padding: 0 24px 24px;\">
                            <table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"background-color: #ecfdf5; border-radius: 8px; border-left: 4px solid #10b981;\">
                                <tr>
                                    <td style=\"padding: 16px;\">
                                        <p style=\"margin: 0; font-size: 14px; color: #065f46;\">
                                            💡 <strong>Next step:</strong> Message {$providerName} to arrange when and where the service will happen.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                ",
            ],
            // NEW: Pending broker approval
            'exchange_pending_broker' => [
                'gradient' => 'linear-gradient(135deg, #f59e0b, #d97706)',
                'icon' => '⏳',
                'title' => 'Awaiting Coordinator Approval',
                'message' => "The provider has accepted your request! Before you can begin, a community coordinator needs to review and approve this exchange.",
                'buttonText' => 'View Exchange',
                'helpText' => 'You\'ll receive another notification once the coordinator has reviewed your exchange.',
                'typeColor' => $typeColor,
                'typeText' => $typeText,
                'typeBadge' => $typeBadge,
                'extraDetails' => "
                    <tr>
                        <td style=\"padding-top: 16px; border-top: 1px solid #e5e7eb; margin-top: 16px;\">
                            <p style=\"margin: 0 0 4px; font-size: 12px; color: #6b7280;\">Proposed Hours</p>
                            <p style=\"margin: 0; font-size: 24px; color: #f59e0b; font-weight: 700;\">{$hours}h</p>
                        </td>
                    </tr>
                ",
                'alertBox' => "
                    <tr>
                        <td style=\"padding: 0 24px 24px;\">
                            <table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"background-color: #fffbeb; border-radius: 8px; border-left: 4px solid #f59e0b;\">
                                <tr>
                                    <td style=\"padding: 16px;\">
                                        <p style=\"margin: 0; font-size: 14px; color: #92400e;\">
                                            🔍 <strong>Why approval?</strong> Some exchanges require coordinator review to ensure safety and suitability for all members.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                ",
            ],
            // NEW: Work has started
            'exchange_started' => [
                'gradient' => 'linear-gradient(135deg, #3b82f6, #1d4ed8)',
                'icon' => '🚀',
                'title' => 'Exchange Started!',
                'message' => "The exchange is now in progress! The service is being provided.",
                'buttonText' => 'View Exchange',
                'helpText' => 'When the service is complete, both parties will need to confirm the hours worked.',
                'typeColor' => $typeColor,
                'typeText' => $typeText,
                'typeBadge' => $typeBadge,
                'extraDetails' => "
                    <tr>
                        <td style=\"padding-top: 16px; border-top: 1px solid #e5e7eb; margin-top: 16px;\">
                            <p style=\"margin: 0 0 4px; font-size: 12px; color: #6b7280;\">Expected Hours</p>
                            <p style=\"margin: 0; font-size: 24px; color: #3b82f6; font-weight: 700;\">{$hours}h</p>
                        </td>
                    </tr>
                ",
                'alertBox' => "
                    <tr>
                        <td style=\"padding: 0 24px 24px;\">
                            <table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"background-color: #eff6ff; border-radius: 8px; border-left: 4px solid #3b82f6;\">
                                <tr>
                                    <td style=\"padding: 16px;\">
                                        <p style=\"margin: 0; font-size: 14px; color: #1e40af;\">
                                            📝 <strong>Remember:</strong> When the service is complete, mark it as done and confirm the actual hours worked.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                ",
            ],
            // NEW: Ready for hour confirmation
            'exchange_ready_confirmation' => [
                'gradient' => 'linear-gradient(135deg, #8b5cf6, #7c3aed)',
                'icon' => '✋',
                'title' => 'Confirm Your Hours',
                'message' => "The exchange has been marked as complete! Please confirm the number of hours worked so the time credits can be transferred.",
                'buttonText' => 'Confirm Hours',
                'helpText' => 'Both parties need to confirm the hours before credits are transferred.',
                'typeColor' => $typeColor,
                'typeText' => $typeText,
                'typeBadge' => $typeBadge,
                'extraDetails' => "
                    <tr>
                        <td style=\"padding-top: 16px; border-top: 1px solid #e5e7eb; margin-top: 16px;\">
                            <p style=\"margin: 0 0 4px; font-size: 12px; color: #6b7280;\">Proposed Hours</p>
                            <p style=\"margin: 0; font-size: 24px; color: #8b5cf6; font-weight: 700;\">{$hours}h</p>
                        </td>
                    </tr>
                ",
                'alertBox' => "
                    <tr>
                        <td style=\"padding: 0 24px 24px;\">
                            <table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\" style=\"background-color: #f5f3ff; border-radius: 8px; border-left: 4px solid #8b5cf6;\">
                                <tr>
                                    <td style=\"padding: 16px;\">
                                        <p style=\"margin: 0; font-size: 14px; color: #5b21b6;\">
                                            ⏰ <strong>Action needed:</strong> Please confirm the hours as soon as possible so the other party receives their time credits.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                ",
            ],
        ];

        return $configs[$type] ?? [
            'gradient' => 'linear-gradient(135deg, #6366f1, #8b5cf6)',
            'icon' => '📋',
            'title' => 'Exchange Update',
            'message' => 'There has been an update to your exchange.',
            'buttonText' => 'View Exchange',
            'helpText' => 'Visit your dashboard to see the latest details.',
            'typeColor' => $typeColor,
            'typeText' => $typeText,
            'typeBadge' => $typeBadge,
            'extraDetails' => '',
            'alertBox' => '',
        ];
    }
}
