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
        $link = "/admin/match-approvals";

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
            <a href="{$basePath}/admin/match-approvals" style="display: inline-block; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-weight: 600;">Review Match</a>
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
    public static function send(int $userId, string $type, array $data = []): void
    {
        // Build content and link based on notification type
        $content = self::buildNotificationContent($type, $data);
        $link = self::buildNotificationLink($type, $data);

        // Create in-app notification
        Notification::create($userId, $content, $link, $type);

        // Queue email notification
        self::queueNotification($userId, $type, $content, $link, 'instant');
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
            self::queueNotification($admin['id'], $type, $content, $link, 'instant');
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
            case 'listing_risk_tagged':
                $level = $data['risk_level'] ?? 'unknown';
                $title = $data['listing_title'] ?? 'Listing';
                return "⚠️ Listing '{$title}' tagged as {$level} risk";
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
                $exchangeId = $data['exchange_id'] ?? 0;
                return "/exchanges/{$exchangeId}";
            case 'exchange_request_declined':
                return "/exchanges";
            case 'listing_risk_tagged':
                return "/admin/broker-controls/risk-tags";
            default:
                return "/notifications";
        }
    }
}
