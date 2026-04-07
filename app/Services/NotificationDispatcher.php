<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\Mailer;
use App\Core\TenantContext;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * NotificationDispatcher — Native Laravel service for dispatching notifications.
 *
 * Handles in-app (bell), email, and web-push notifications with a
 * hierarchical frequency-setting system (Thread > Group > Global).
 *
 * DB facade / Eloquent instead of legacy Database class.
 */
class NotificationDispatcher
{
    public function __construct()
    {
    }

    // =========================================================================
    // PRIMARY DISPATCH
    // =========================================================================

    /**
     * Dispatch a notification (In-App + Email Traffic Cop).
     *
     * @param int         $userId      Target User ID
     * @param string      $contextType 'global', 'group', 'thread'
     * @param int|null    $contextId   Group ID or Discussion ID
     * @param string      $activityType 'new_topic', 'new_reply', 'mention'
     * @param string      $content     Plain text content for email preview
     * @param string      $link        Relative link to the item
     * @param string|null $htmlContent Full HTML content for Instant Emails
     * @param bool        $isOrganizer If true, applies strict Organizer Priority rules
     */
    public static function dispatch($userId, $contextType, $contextId, $activityType, $content, $link, $htmlContent, $isOrganizer = false): void
    {
        // 1. ALWAYS create In-App Notification (The "Bell")
        Notification::createNotification((int) $userId, $content, $link, $activityType);

        // 2. CHECK Notification Settings Hierarchy
        $frequency = self::getFrequencySetting($userId, $contextType, $contextId);

        // 3. APPLY "Organizer Rule" (Set in Stone)
        if ($isOrganizer && $activityType === 'new_topic') {
            if ($frequency === null) {
                $frequency = 'instant';
            }
        }

        // 4. Default for normal users if no setting found
        if ($frequency === null) {
            $frequency = 'daily';
        }

        // 5. Direct messages override: always instant unless user explicitly opted out
        if ($activityType === 'new_message' && $frequency !== 'off') {
            $frequency = 'instant';
        }

        // 6. TRAFFIC LIGHT LOGIC
        switch ($frequency) {
            case 'instant':
                self::queueNotification($userId, $activityType, $content, $link, 'instant', $htmlContent);
                // Also dispatch a real-time web push notification
                try {
                    $pushTitle = self::getPushTitle($activityType);
                    \App\Services\WebPushService::sendToUserStatic($userId, $pushTitle, $content, $link);
                } catch (\Throwable $e) {
                    Log::debug('[NotificationDispatcher] WebPush failed: ' . $e->getMessage());
                }
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

    // =========================================================================
    // FREQUENCY SETTINGS HIERARCHY
    // =========================================================================

    /**
     * Resolve the effective frequency setting based on hierarchy:
     * Thread > Group > Global
     */
    private static function getFrequencySetting($userId, $contextType, $contextId): ?string
    {
        $tenantId = TenantContext::getId();

        // Check exact match first
        // notification_settings may not have tenant_id column; user_id is
        // already tenant-scoped by the caller (dispatch verifies the user
        // belongs to TenantContext). Adding tenant_id here as defense-in-depth.
        $row = DB::table('notification_settings')
            ->where('user_id', $userId)
            ->where('context_type', $contextType)
            ->where('context_id', $contextId)
            ->value('frequency');

        if ($row !== null) {
            return $row;
        }

        // If 'thread', try 'group' parent
        if ($contextType === 'thread') {
            $groupId = DB::table('group_discussions')
                ->where('id', $contextId)
                ->where('tenant_id', $tenantId)
                ->value('group_id');

            if ($groupId) {
                return self::getFrequencySetting($userId, 'group', $groupId);
            }
        }

        // If 'group', try 'global'
        if ($contextType === 'group') {
            return self::getFrequencySetting($userId, 'global', 0);
        }

        // Global default (if not set in DB, return default from Config)
        if ($contextType === 'global') {
            $tenant = TenantContext::get();
            $config = json_decode($tenant['configuration'] ?? '{}', true);
            return $config['notifications']['default_frequency'] ?? 'daily';
        }

        return null;
    }

    // =========================================================================
    // PUSH & QUEUE HELPERS
    // =========================================================================

    /**
     * Map activity types to short push notification titles.
     */
    private static function getPushTitle(string $activityType): string
    {
        $key = 'notifications.push_' . $activityType;
        $translated = __($key);
        return $translated !== $key ? $translated : __('notifications.push_default');
    }

    private static function queueNotification($userId, $activityType, $content, $link, $frequency = 'daily', $emailBody = null): void
    {
        $snippet = substr($content, 0, 250);

        // Resolve tenant_id: use current context, or fall back to user's tenant
        $tenantId = TenantContext::getId();
        if (!$tenantId) {
            $user = \App\Models\User::findById($userId);
            $tenantId = $user['tenant_id'] ?? null;
        }

        DB::table('notification_queue')->insert([
            'user_id'        => $userId,
            'tenant_id'      => $tenantId,
            'activity_type'  => $activityType,
            'content_snippet' => $snippet,
            'link'           => $link,
            'frequency'      => $frequency,
            'email_body'     => $emailBody,
            'created_at'     => now(),
            'status'         => 'pending',
        ]);
    }

    // =========================================================================
    // HOT MATCH / MUTUAL MATCH / DIGEST
    // =========================================================================

    /**
     * Dispatch a HOT MATCH notification (85%+ match).
     */
    public static function dispatchHotMatch($userId, $match): void
    {
        $listingTitle = $match['title'] ?? 'New Listing';
        $matchScore = (int) ($match['match_score'] ?? 85);
        $userName = $match['user_name'] ?? 'Someone';
        $distance = $match['distance_km'] ?? null;
        $listingId = $match['id'] ?? 0;

        $distanceText = $distance !== null ? " ({$distance}km away)" : '';
        $content = __('notifications.hot_match_content', ['name' => $userName, 'title' => $listingTitle, 'score' => $matchScore, 'distance' => $distanceText]);
        $link = "/listings/{$listingId}";

        // Check user's match notification preferences
        $prefs = \App\Services\MatchingService::getPreferencesStatic($userId);
        if (empty($prefs['notify_hot_matches'])) {
            return;
        }

        $frequency = $prefs['notification_frequency'] ?? 'fortnightly';
        if ($frequency === 'never') {
            return;
        }

        // Normalize frequency: map 'fortnightly' to 'weekly' for queue ENUM compatibility
        $queueFrequency = $frequency === 'fortnightly' ? 'weekly' : $frequency;

        Notification::createNotification((int) $userId, $content, $link, 'hot_match');

        $htmlContent = self::buildHotMatchEmail($match, $matchScore);
        self::queueNotification($userId, 'hot_match', $content, $link, $queueFrequency, $htmlContent);
    }

    /**
     * Dispatch a MUTUAL MATCH notification.
     */
    public static function dispatchMutualMatch($userId, $match, $reciprocalInfo = []): void
    {
        $userName = $match['user_name'] ?? 'Someone';
        $theyOffer = $reciprocalInfo['they_offer'] ?? 'a skill you need';
        $youOffer = $reciprocalInfo['you_offer'] ?? 'something they need';
        $listingId = $match['id'] ?? 0;

        $content = __('notifications.mutual_match_content', ['name' => $userName, 'they_offer' => $theyOffer, 'you_offer' => $youOffer]);
        $link = "/listings/{$listingId}";

        $prefs = \App\Services\MatchingService::getPreferencesStatic($userId);
        if (empty($prefs['notify_mutual_matches'])) {
            return;
        }

        $frequency = $prefs['notification_frequency'] ?? 'fortnightly';
        if ($frequency === 'never') {
            return;
        }

        // Normalize frequency: map 'fortnightly' to 'weekly' for queue ENUM compatibility
        $queueFrequency = $frequency === 'fortnightly' ? 'weekly' : $frequency;

        Notification::createNotification((int) $userId, $content, $link, 'mutual_match');

        $htmlContent = self::buildMutualMatchEmail($match, $reciprocalInfo);
        self::queueNotification($userId, 'mutual_match', $content, $link, $queueFrequency, $htmlContent);
    }

    /**
     * Dispatch a NEW MATCHES DIGEST notification.
     */
    public static function dispatchMatchDigest($userId, $matches, $period = 'fortnightly'): void
    {
        if (empty($matches)) {
            return;
        }

        $count = count($matches);
        $hotCount = count(array_filter($matches, fn($m) => ($m['match_score'] ?? 0) >= 85));
        $mutualCount = count(array_filter($matches, fn($m) => ($m['match_type'] ?? '') === 'mutual'));

        $content = __('notifications.match_digest_content', ['period' => $period, 'count' => $count]);
        if ($hotCount > 0) {
            $content .= __('notifications.match_digest_hot', ['count' => $hotCount]);
        }
        if ($mutualCount > 0) {
            $content .= __('notifications.match_digest_mutual', ['count' => $mutualCount]);
        }

        $link = "/matches";

        Notification::createNotification((int) $userId, $content, $link, 'match_digest');

        $htmlContent = self::buildMatchDigestEmail($matches, $period, $hotCount, $mutualCount);
        self::queueNotification($userId, 'match_digest', $content, $link, 'instant', $htmlContent);
    }

    // =========================================================================
    // MATCH APPROVAL WORKFLOW
    // =========================================================================

    /**
     * Dispatch notification to brokers/admins when a match needs approval.
     */
    public static function dispatchMatchApprovalRequest($brokerId, $userName, $listingTitle, $requestId): void
    {
        $content = __('notifications.match_approval_request', ['name' => $userName, 'title' => $listingTitle]);
        $link = "/admin/match-approvals";

        Notification::createNotification((int) $brokerId, $content, $link, 'match_approval_request');

        $htmlContent = self::buildMatchApprovalRequestEmail($userName, $listingTitle, $requestId);
        self::queueNotification($brokerId, 'match_approval_request', $content, $link, 'instant', $htmlContent);
    }

    /**
     * Dispatch notification to user when their match is approved.
     */
    public static function dispatchMatchApproved($userId, $listingTitle, $listingId, $matchScore = 0): void
    {
        $content = __('notifications.match_approved', ['title' => $listingTitle]);
        $link = "/listings/{$listingId}";

        Notification::createNotification((int) $userId, $content, $link, 'match_approved');

        $htmlContent = self::buildMatchApprovedEmail($listingTitle, $listingId, $matchScore);
        self::queueNotification($userId, 'match_approved', $content, $link, 'instant', $htmlContent);
    }

    /**
     * Dispatch notification to user when their match is rejected.
     */
    public static function dispatchMatchRejected($userId, $listingTitle, $reason = ''): void
    {
        $content = __('notifications.match_rejected', ['title' => $listingTitle]);
        if (!empty($reason)) {
            $content .= __('notifications.match_rejected_reason', ['reason' => $reason]);
        }
        $link = "/matches";

        Notification::createNotification((int) $userId, $content, $link, 'match_rejected');

        $htmlContent = self::buildMatchRejectedEmail($listingTitle, $reason);
        self::queueNotification($userId, 'match_rejected', $content, $link, 'instant', $htmlContent);
    }

    // =========================================================================
    // SIMPLE NOTIFICATION (for broker control services)
    // =========================================================================

    /**
     * Send a simple notification to a user.
     */
    public static function send(int $userId, string $type, array $data = []): void
    {
        $content = self::buildNotificationContent($type, $data);
        $link = self::buildNotificationLink($type, $data);

        Notification::createNotification($userId, $content, $link, $type);

        // Send email immediately for exchange notifications
        self::sendExchangeEmailImmediately($userId, $type, $data, $content, $link);
    }

    /**
     * Notify all admins/brokers of the current tenant.
     */
    public static function notifyAdmins(string $type, array $data = [], string $message = ''): void
    {
        $tenantId = TenantContext::getId();

        $admins = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->whereIn('role', ['admin', 'broker', 'coordinator'])
            ->where('status', 'active')
            ->pluck('id');

        foreach ($admins as $adminId) {
            $content = $message ?: self::buildNotificationContent($type, $data);
            $link = self::buildNotificationLink($type, $data);

            Notification::createNotification((int) $adminId, $content, $link, $type);

            self::sendExchangeEmailImmediately((int) $adminId, $type, $data, $content, $link);
        }
    }

    /**
     * Send credit received email to a user.
     */
    public static function sendCreditEmail(int $recipientUserId, string $senderName, float $amount, string $description = ''): void
    {
        try {
            $tenantId = TenantContext::getId();

            $user = DB::table('users')
                ->where('id', $recipientUserId)
                ->where('tenant_id', $tenantId)
                ->select(['email', 'name', 'first_name'])
                ->first();

            if (!$user || empty($user->email)) {
                return;
            }

            $recipientName = $user->first_name ?? $user->name ?? 'there';
            $tenantName = TenantContext::getSetting('site_name', 'Project NEXUS');
            $baseUrl = TenantContext::getFrontendUrl();
            $basePath = TenantContext::getSlugPrefix();
            $walletUrl = $baseUrl . $basePath . '/wallet';

            $amountDisplay = $amount . ' hour' . ($amount != 1 ? 's' : '');
            $subject = __('emails.notification.credit_received_subject', ['sender' => htmlspecialchars($senderName), 'amount' => $amountDisplay, 'community' => $tenantName]);
            $emailBody = self::buildCreditReceivedEmail(
                htmlspecialchars($recipientName),
                htmlspecialchars($senderName),
                $amount,
                htmlspecialchars($description),
                $walletUrl,
                htmlspecialchars($tenantName)
            );

            $mailer = Mailer::forCurrentTenant();
            $mailer->send($user->email, $subject, $emailBody);
        } catch (\Exception $e) {
            Log::warning("NotificationDispatcher::sendCreditEmail failed: " . $e->getMessage());
        }
    }

    /**
     * Send review received email to a user.
     */
    public static function sendReviewEmail(int $receiverUserId, string $reviewerName, int $rating, ?string $comment = null, bool $isAnonymous = false): void
    {
        try {
            $tenantId = TenantContext::getId();

            $user = DB::table('users')
                ->where('id', $receiverUserId)
                ->where('tenant_id', $tenantId)
                ->select(['email', 'name', 'first_name'])
                ->first();

            if (!$user || empty($user->email)) {
                return;
            }

            $recipientName = $user->first_name ?? $user->name ?? 'there';
            $tenantName = TenantContext::getSetting('site_name', 'Project NEXUS');
            $baseUrl = TenantContext::getFrontendUrl();
            $basePath = TenantContext::getSlugPrefix();
            $profileUrl = $baseUrl . $basePath . '/profile/' . $receiverUserId;

            $displayName = $isAnonymous ? __('emails.notification.someone') : htmlspecialchars($reviewerName);
            $subject = __('emails.notification.review_subject', ['reviewer' => $displayName, 'rating' => $rating, 'community' => $tenantName]);

            $emailBody = self::buildReviewReceivedEmail(
                htmlspecialchars($recipientName),
                $displayName,
                $rating,
                $comment ? htmlspecialchars($comment) : null,
                $profileUrl,
                htmlspecialchars($tenantName)
            );

            $mailer = Mailer::forCurrentTenant();
            $mailer->send($user->email, $subject, $emailBody);
        } catch (\Exception $e) {
            Log::warning("NotificationDispatcher::sendReviewEmail failed: " . $e->getMessage());
        }
    }

    // =========================================================================
    // IDENTITY VERIFICATION NOTIFICATIONS
    // =========================================================================

    public static function dispatchVerificationPassed(int $userId): void
    {
        $content = __('notifications.verification_passed');
        $link = "/dashboard";

        Notification::createNotification($userId, $content, $link, 'verification_passed');
        $htmlContent = self::buildVerificationPassedEmail();
        self::queueNotification($userId, 'verification_passed', $content, $link, 'instant', $htmlContent);
    }

    public static function dispatchVerificationFailed(int $userId, string $reason = ''): void
    {
        $content = !empty($reason)
            ? __('notifications.verification_failed_reason', ['reason' => $reason])
            : __('notifications.verification_failed');
        $link = "/verify-identity";

        Notification::createNotification($userId, $content, $link, 'verification_failed');
        $htmlContent = self::buildVerificationFailedEmail($reason);
        self::queueNotification($userId, 'verification_failed', $content, $link, 'instant', $htmlContent);
    }

    public static function dispatchVerificationCompletedToAdmins(int $userId, string $status): void
    {
        $tenantId = TenantContext::getId();

        $user = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->select(['first_name', 'last_name', 'email'])
            ->first();

        if (!$user) {
            return;
        }

        $userName = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
        $email = $user->email ?? '';
        $isPassed = $status === 'passed';

        $content = $isPassed
            ? __('notifications.verification_passed_admin', ['name' => $userName, 'email' => $email])
            : __('notifications.verification_failed_admin', ['name' => $userName, 'email' => $email]);
        $link = "/admin/members";

        $admins = DB::table('users')
            ->where('tenant_id', $tenantId)
            ->whereIn('role', ['admin', 'super_admin'])
            ->where('status', 'active')
            ->pluck('id');

        foreach ($admins as $adminId) {
            Notification::createNotification((int) $adminId, $content, $link, 'admin_verification_update');
        }
    }

    public static function dispatchVerificationReminder(int $userId): void
    {
        $content = __('notifications.verification_reminder');
        $link = "/verify-identity";

        Notification::createNotification($userId, $content, $link, 'verification_reminder');

        $tenant = TenantContext::get();
        $tenantName = htmlspecialchars($tenant['name'] ?? 'Community', ENT_QUOTES, 'UTF-8');
        $basePath = TenantContext::getSlugPrefix();
        $frontendUrl = TenantContext::getFrontendUrl();

        $reminderHeading = __('notifications.verification_reminder_heading');
        $reminderBody = __('notifications.verification_reminder_body');
        $reminderCta = __('notifications.verification_reminder_cta');

        $htmlContent = <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #f59e0b, #d97706); padding: 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 24px;">{$reminderHeading}</h1>
        <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0;">{$tenantName}</p>
    </div>
    <div style="background: #f8fafc; padding: 24px; border-radius: 0 0 16px 16px; border: 1px solid #e2e8f0; border-top: none;">
        <p style="color: #1e293b; font-size: 16px; line-height: 1.6;">{$reminderBody}</p>
        <div style="text-align: center; margin-top: 24px;">
            <a href="{$frontendUrl}{$basePath}/verify-identity" style="display: inline-block; background: linear-gradient(135deg, #f59e0b, #d97706); color: white; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-weight: 600;">{$reminderCta}</a>
        </div>
    </div>
</div>
HTML;

        self::queueNotification($userId, 'verification_reminder', $content, $link, 'instant', $htmlContent);
    }

    // =========================================================================
    // NOTIFICATION CONTENT BUILDERS
    // =========================================================================

    private static function buildNotificationContent(string $type, array $data): string
    {
        switch ($type) {
            case 'exchange_request_received':
                return __('notifications.exchange_request_received');
            case 'exchange_request_declined':
                if (!empty($data['reason'])) {
                    return __('notifications.exchange_request_declined_reason', ['reason' => $data['reason']]);
                }
                return __('notifications.exchange_request_declined');
            case 'exchange_approved':
                return __('notifications.exchange_approved');
            case 'exchange_rejected':
                if (!empty($data['reason'])) {
                    return __('notifications.exchange_rejected_reason', ['reason' => $data['reason']]);
                }
                return __('notifications.exchange_rejected');
            case 'exchange_completed':
                $hours = $data['hours'] ?? 0;
                return __('notifications.exchange_completed', ['hours' => $hours]);
            case 'exchange_cancelled':
                return __('notifications.exchange_cancelled');
            case 'exchange_disputed':
                return __('notifications.exchange_disputed');
            case 'exchange_accepted':
                return __('notifications.exchange_accepted');
            case 'exchange_pending_broker':
                return __('notifications.exchange_pending_broker');
            case 'exchange_started':
                return __('notifications.exchange_started');
            case 'exchange_ready_confirmation':
                $hours = $data['proposed_hours'] ?? 0;
                return __('notifications.exchange_ready_confirmation', ['hours' => $hours]);
            case 'listing_risk_tagged':
                $level = $data['risk_level'] ?? 'unknown';
                $title = $data['listing_title'] ?? 'Listing';
                return __('notifications.listing_risk_tagged', ['title' => $title, 'level' => $level]);
            case 'credit_received':
                $senderName = $data['sender_name'] ?? 'Someone';
                $amount = $data['amount'] ?? 0;
                return __('notifications.credit_received', ['name' => $senderName, 'amount' => $amount]);
            default:
                return __('emails_notifications.default_notification', ['type' => $type]);
        }
    }

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
                return "/admin/broker-controls/exchanges/{$exchangeId}";
            case 'exchange_request_declined':
                return "/exchanges";
            case 'listing_risk_tagged':
                return "/admin/broker-controls/risk-tags";
            case 'credit_received':
                return "/wallet";
            default:
                return "/notifications";
        }
    }

    // =========================================================================
    // EXCHANGE EMAIL SENDER
    // =========================================================================

    private static function sendExchangeEmailImmediately(int $userId, string $type, array $data, string $content, string $link): void
    {
        // Only send for exchange and credit notifications
        if (strpos($type, 'exchange_') !== 0 && $type !== 'credit_received') {
            return;
        }

        try {
            $tenantId = TenantContext::getId();

            $user = DB::table('users')
                ->where('id', $userId)
                ->where('tenant_id', $tenantId)
                ->select(['email', 'name', 'first_name'])
                ->first();

            if (!$user || empty($user->email)) {
                return;
            }

            $baseUrl = TenantContext::getFrontendUrl();
            $slugPrefix = TenantContext::getSlugPrefix();
            $fullUrl = $baseUrl . $slugPrefix . $link;

            $mailer = Mailer::forCurrentTenant();

            if ($type === 'credit_received') {
                $senderName = htmlspecialchars($data['sender_name'] ?? 'A member');
                $amount = (float) ($data['amount'] ?? 0);
                $description = htmlspecialchars($data['description'] ?? '');
                $recipientName = htmlspecialchars($user->first_name ?? $user->name ?? 'there');
                $tenantName = htmlspecialchars(TenantContext::getSetting('site_name', 'Project NEXUS'));

                $amountDisplay = $amount . ' hour' . ($amount != 1 ? 's' : '');
                $subject = __('emails.notification.credit_received_subject', ['sender' => $senderName, 'amount' => $amountDisplay, 'community' => $tenantName]);
                $emailBody = self::buildCreditReceivedEmail($recipientName, $senderName, $amount, $description, $fullUrl, $tenantName);
                $mailer->send($user->email, $subject, $emailBody);
            } else {
                $exchangeDetails = self::getExchangeDetailsForEmail($data['exchange_id'] ?? 0);
                $userArr = ['email' => $user->email, 'name' => $user->name, 'first_name' => $user->first_name];
                $emailBody = self::buildRichExchangeEmail($type, $data, $userArr, $exchangeDetails, $fullUrl);
                $subject = self::getExchangeEmailSubject($type, $exchangeDetails);
                $mailer->send($user->email, $subject, $emailBody);
            }
        } catch (\Exception $e) {
            Log::warning("NotificationDispatcher: Failed to send exchange email - " . $e->getMessage());
        }
    }

    private static function getExchangeDetailsForEmail(int $exchangeId): array
    {
        if ($exchangeId <= 0) {
            return [];
        }

        try {
            $row = DB::table('exchange_requests as e')
                ->join('listings as l', 'e.listing_id', '=', 'l.id')
                ->join('users as req', 'e.requester_id', '=', 'req.id')
                ->join('users as prov', 'e.provider_id', '=', 'prov.id')
                ->where('e.id', $exchangeId)
                ->select([
                    'e.*',
                    'l.title as listing_title',
                    'l.type as listing_type',
                    'l.description as listing_description',
                    'req.name as requester_name',
                    'req.first_name as requester_first_name',
                    'req.avatar_url as requester_avatar',
                    'prov.name as provider_name',
                    'prov.first_name as provider_first_name',
                    'prov.avatar_url as provider_avatar',
                ])
                ->first();

            return $row ? (array) $row : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private static function getExchangeEmailSubject(string $type, array $details): string
    {
        $listingTitle = $details['listing_title'] ?? 'your listing';
        $shortTitle = strlen($listingTitle) > 30 ? substr($listingTitle, 0, 30) . '...' : $listingTitle;

        $key = 'notifications.exchange_email_' . str_replace('exchange_', '', $type);
        if ($type === 'exchange_request_received') {
            return __('notifications.exchange_email_request_received', ['title' => $shortTitle]);
        }
        $translated = __($key);
        return $translated !== $key ? $translated : __('notifications.exchange_email_default');
    }

    // =========================================================================
    // EMAIL TEMPLATE BUILDERS
    // =========================================================================

    private static function buildHotMatchEmail($match, $matchScore): string
    {
        $userId = $match['recipient_user_id'] ?? 0;
        $tenantId = TenantContext::getId();

        $user = null;
        if ($userId) {
            $user = DB::table('users')
                ->where('id', $userId)
                ->where('tenant_id', $tenantId)
                ->select(['name', 'first_name'])
                ->first();
        }
        $userName = $user->name ?? $user->first_name ?? 'there';

        $tenant = TenantContext::get();
        $tenantName = $tenant['name'] ?? 'Community';

        $templatePath = base_path('views/emails/match_hot.php');
        if (file_exists($templatePath)) {
            ob_start();
            include $templatePath;
            return ob_get_clean();
        }

        $title = htmlspecialchars($match['title'] ?? 'New Listing');
        $posterName = htmlspecialchars($match['user_name'] ?? 'Someone');
        $distance = $match['distance_km'] ?? null;
        $distanceLabel = $distance !== null ? __('emails_notifications.hot_match.km_away', ['distance' => $distance]) : '';
        $distanceHtml = $distance !== null ? "<p style='color: #10b981;'>📍 {$distanceLabel}</p>" : '';
        $hotHeading = __('emails_notifications.hot_match.heading');
        $hotSubheading = __('emails_notifications.hot_match.subheading', ['score' => $matchScore]);
        $postedByText = __('emails_notifications.hot_match.posted_by', ['name' => $posterName]);

        return <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #f97316, #ef4444); padding: 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 24px;">{$hotHeading}</h1>
        <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0;">{$hotSubheading}</p>
    </div>
    <div style="background: #f8fafc; padding: 24px; border-radius: 0 0 16px 16px; border: 1px solid #e2e8f0; border-top: none;">
        <h2 style="color: #1e293b; margin: 0 0 8px;">{$title}</h2>
        <p style="color: #6366f1; font-weight: 600; margin: 0 0 12px;">{$postedByText}</p>
        {$distanceHtml}
    </div>
</div>
HTML;
    }

    private static function buildMutualMatchEmail($match, $reciprocalInfo): string
    {
        $userId = $match['recipient_user_id'] ?? 0;
        $tenantId = TenantContext::getId();

        $user = null;
        if ($userId) {
            $user = DB::table('users')
                ->where('id', $userId)
                ->where('tenant_id', $tenantId)
                ->select(['name', 'first_name'])
                ->first();
        }
        $userName = $user->name ?? $user->first_name ?? 'there';

        $tenant = TenantContext::get();
        $tenantName = $tenant['name'] ?? 'Community';

        $templatePath = base_path('views/emails/match_mutual.php');
        if (file_exists($templatePath)) {
            ob_start();
            include $templatePath;
            return ob_get_clean();
        }

        $posterName = htmlspecialchars($match['user_name'] ?? 'Someone');
        $theyOffer = htmlspecialchars($reciprocalInfo['they_offer'] ?? 'a skill you need');
        $youOffer = htmlspecialchars($reciprocalInfo['you_offer'] ?? 'something they need');

        $mutualHeading = __('emails_notifications.mutual_match.heading');
        $mutualSubheading = __('emails_notifications.mutual_match.subheading');
        $exchangeWith = __('emails_notifications.mutual_match.exchange_with', ['name' => $posterName]);
        $theyHelpLabel = __('emails_notifications.mutual_match.they_help_you');
        $youHelpLabel = __('emails_notifications.mutual_match.you_help_them');

        return <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #10b981, #06b6d4); padding: 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 24px;">{$mutualHeading}</h1>
        <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0;">{$mutualSubheading}</p>
    </div>
    <div style="background: #f8fafc; padding: 24px; border-radius: 0 0 16px 16px; border: 1px solid #e2e8f0; border-top: none;">
        <h2 style="color: #1e293b; margin: 0 0 16px;">{$exchangeWith}</h2>
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin-bottom: 16px;">
            <p style="color: #64748b; margin: 0 0 8px; font-size: 14px;">{$theyHelpLabel}</p>
            <p style="color: #10b981; font-weight: 600; margin: 0; font-size: 16px;">{$theyOffer}</p>
        </div>
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px;">
            <p style="color: #64748b; margin: 0 0 8px; font-size: 14px;">{$youHelpLabel}</p>
            <p style="color: #6366f1; font-weight: 600; margin: 0; font-size: 16px;">{$youOffer}</p>
        </div>
    </div>
</div>
HTML;
    }

    private static function buildMatchDigestEmail($matches, $period, $hotCount, $mutualCount): string
    {
        $tenant = TenantContext::get();
        $tenantName = $tenant['name'] ?? 'Community';
        $userName = 'there';

        $templatePath = base_path('views/emails/match_digest.php');
        if (file_exists($templatePath)) {
            $stats = [
                'hotCount' => $hotCount,
                'mutualCount' => $mutualCount,
                'totalCount' => count($matches),
            ];
            ob_start();
            include $templatePath;
            return ob_get_clean();
        }

        $count = count($matches);
        $periodTitle = ucfirst($period);

        $matchListHtml = '';
        foreach (array_slice($matches, 0, 5) as $match) {
            $title = htmlspecialchars($match['title'] ?? 'Listing');
            $score = (int) ($match['match_score'] ?? 0);
            $matchUserName = !empty($match['user_name']) ? $match['user_name'] : trim(($match['first_name'] ?? '') . ' ' . ($match['last_name'] ?? ''));
            $posterName = htmlspecialchars($matchUserName ?: 'A member');
            $scoreColor = $score >= 85 ? '#ef4444' : ($score >= 70 ? '#6366f1' : '#64748b');
            $byPosterText = __('emails_notifications.match_digest.by_poster', ['name' => $posterName]);
            $matchListHtml .= <<<HTML
<div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; margin-bottom: 10px;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <p style="color: #1e293b; font-weight: 600; margin: 0;">{$title}</p>
            <p style="color: #64748b; font-size: 14px; margin: 4px 0 0;">{$byPosterText}</p>
        </div>
        <span style="background: {$scoreColor}; color: white; padding: 4px 10px; border-radius: 20px; font-weight: 700; font-size: 14px;">{$score}%</span>
    </div>
</div>
HTML;
        }

        $statsHtml = '';
        if ($hotCount > 0) {
            $hotBadge = __('emails_notifications.match_digest.hot_badge', ['count' => $hotCount]);
            $statsHtml .= "<span style='background: #fef2f2; color: #ef4444; padding: 6px 12px; border-radius: 20px; margin-right: 8px;'>{$hotBadge}</span>";
        }
        if ($mutualCount > 0) {
            $mutualBadge = __('emails_notifications.match_digest.mutual_badge', ['count' => $mutualCount]);
            $statsHtml .= "<span style='background: #ecfdf5; color: #10b981; padding: 6px 12px; border-radius: 20px;'>{$mutualBadge}</span>";
        }

        $digestHeading = __('emails_notifications.match_digest.heading', ['period' => $periodTitle]);
        $digestWaiting = __('emails_notifications.match_digest.matches_waiting', ['count' => $count]);
        $topMatchesLabel = __('emails_notifications.match_digest.top_matches');

        return <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #6366f1, #8b5cf6); padding: 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 24px;">{$digestHeading}</h1>
        <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0;">{$digestWaiting}</p>
    </div>
    <div style="background: #f8fafc; padding: 24px; border-radius: 0 0 16px 16px; border: 1px solid #e2e8f0; border-top: none;">
        <div style="margin-bottom: 20px; text-align: center;">{$statsHtml}</div>
        <h3 style="color: #1e293b; margin: 0 0 16px;">{$topMatchesLabel}</h3>
        {$matchListHtml}
    </div>
</div>
HTML;
    }

    private static function buildMatchApprovalRequestEmail($userName, $listingTitle, $requestId): string
    {
        $tenant = TenantContext::get();
        $tenantName = htmlspecialchars($tenant['name'] ?? 'Community', ENT_QUOTES, 'UTF-8');
        $basePath = TenantContext::getSlugPrefix();
        $frontendUrl = TenantContext::getFrontendUrl();
        $userName = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
        $listingTitle = htmlspecialchars($listingTitle, ENT_QUOTES, 'UTF-8');

        $approvalHeading = __('emails_notifications.match_approval.heading');
        $approvalBody = __('emails_notifications.match_approval.body');
        $labelMember = __('emails_notifications.match_approval.label_member');
        $labelMatched = __('emails_notifications.match_approval.label_matched_listing');
        $reviewNote = __('emails_notifications.match_approval.review_note');
        $btnReview = __('emails_notifications.match_approval.btn_review');

        return <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #f59e0b, #d97706); padding: 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 24px;">{$approvalHeading}</h1>
        <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0;">{$tenantName}</p>
    </div>
    <div style="background: #f8fafc; padding: 24px; border-radius: 0 0 16px 16px; border: 1px solid #e2e8f0; border-top: none;">
        <p style="color: #1e293b; font-size: 16px; line-height: 1.6;">{$approvalBody}</p>
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin: 16px 0;">
            <p style="color: #64748b; margin: 0 0 8px; font-size: 14px;">{$labelMember}</p>
            <p style="color: #1e293b; font-weight: 600; margin: 0 0 16px; font-size: 18px;">{$userName}</p>
            <p style="color: #64748b; margin: 0 0 8px; font-size: 14px;">{$labelMatched}</p>
            <p style="color: #6366f1; font-weight: 600; margin: 0; font-size: 18px;">{$listingTitle}</p>
        </div>
        <p style="color: #64748b; font-size: 14px; line-height: 1.6;">
            {$reviewNote}
        </p>
        <div style="text-align: center; margin-top: 24px;">
            <a href="{$frontendUrl}{$basePath}/admin/match-approvals" style="display: inline-block; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-weight: 600;">{$btnReview}</a>
        </div>
    </div>
</div>
HTML;
    }

    private static function buildMatchApprovedEmail($listingTitle, $listingId, $matchScore): string
    {
        $tenant = TenantContext::get();
        $tenantName = htmlspecialchars($tenant['name'] ?? 'Community', ENT_QUOTES, 'UTF-8');
        $basePath = TenantContext::getSlugPrefix();
        $frontendUrl = TenantContext::getFrontendUrl();
        $listingTitle = htmlspecialchars($listingTitle, ENT_QUOTES, 'UTF-8');
        $scoreText = $matchScore > 0 ? " " . __('emails_notifications.match_approved.score_text', ['score' => $matchScore]) : "";
        $matchedHeading = __('emails_notifications.match_approved.heading');
        $matchedBody = __('emails_notifications.match_approved.body');
        $matchedViewNote = __('emails_notifications.match_approved.view_note');
        $btnViewMatch = __('emails_notifications.match_approved.btn_view');

        return <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #22c55e, #16a34a); padding: 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 24px;">{$matchedHeading}</h1>
        <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0;">{$tenantName}</p>
    </div>
    <div style="background: #f8fafc; padding: 24px; border-radius: 0 0 16px 16px; border: 1px solid #e2e8f0; border-top: none;">
        <p style="color: #1e293b; font-size: 16px; line-height: 1.6;">{$matchedBody}</p>
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin: 16px 0; text-align: center;">
            <p style="color: #22c55e; font-weight: 600; margin: 0; font-size: 20px;">{$listingTitle}</p>
            <p style="color: #64748b; margin: 8px 0 0; font-size: 14px;">{$scoreText}</p>
        </div>
        <p style="color: #64748b; font-size: 14px; line-height: 1.6;">
            {$matchedViewNote}
        </p>
        <div style="text-align: center; margin-top: 24px;">
            <a href="{$frontendUrl}{$basePath}/listings/{$listingId}" style="display: inline-block; background: linear-gradient(135deg, #22c55e, #16a34a); color: white; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-weight: 600;">{$btnViewMatch}</a>
        </div>
    </div>
</div>
HTML;
    }

    private static function buildMatchRejectedEmail($listingTitle, $reason): string
    {
        $tenant = TenantContext::get();
        $tenantName = htmlspecialchars($tenant['name'] ?? 'Community', ENT_QUOTES, 'UTF-8');
        $basePath = TenantContext::getSlugPrefix();
        $frontendUrl = TenantContext::getFrontendUrl();
        $listingTitle = htmlspecialchars($listingTitle, ENT_QUOTES, 'UTF-8');
        $reason = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');

        $reasonLabel = __('emails_notifications.match_rejected.label_reason');
        $reasonHtml = '';
        if (!empty($reason)) {
            $reasonHtml = <<<HTML
<div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; padding: 16px; margin: 16px 0;">
    <p style="color: #dc2626; font-weight: 600; margin: 0 0 8px; font-size: 14px;">{$reasonLabel}</p>
    <p style="color: #7f1d1d; margin: 0; font-size: 14px; line-height: 1.5;">{$reason}</p>
</div>
HTML;
        }

        $rejectedHeading = __('emails_notifications.match_rejected.heading');
        $rejectedBody = __('emails_notifications.match_rejected.body');
        $rejectedEncouragement = __('emails_notifications.match_rejected.encouragement');
        $btnBrowse = __('emails_notifications.match_rejected.btn_browse');

        return <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #6366f1, #8b5cf6); padding: 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 24px;">{$rejectedHeading}</h1>
        <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0;">{$tenantName}</p>
    </div>
    <div style="background: #f8fafc; padding: 24px; border-radius: 0 0 16px 16px; border: 1px solid #e2e8f0; border-top: none;">
        <p style="color: #1e293b; font-size: 16px; line-height: 1.6;">{$rejectedBody}</p>
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin: 16px 0; text-align: center;">
            <p style="color: #64748b; font-weight: 600; margin: 0; font-size: 18px;">{$listingTitle}</p>
        </div>
        {$reasonHtml}
        <p style="color: #64748b; font-size: 14px; line-height: 1.6;">
            {$rejectedEncouragement}
        </p>
        <div style="text-align: center; margin-top: 24px;">
            <a href="{$frontendUrl}{$basePath}/matches" style="display: inline-block; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-weight: 600;">{$btnBrowse}</a>
        </div>
    </div>
</div>
HTML;
    }

    private static function buildCreditReceivedEmail(string $recipientName, string $senderName, float $amount, string $description, string $walletUrl, string $tenantName): string
    {
        $amountDisplay = $amount . ' hour' . ($amount != 1 ? 's' : '');
        $descriptionHtml = $description ? "<p style=\"margin:12px 0 0;padding:12px;background:#f0f0f0;border-radius:8px;font-style:italic;color:#555;\">\"{$description}\"</p>" : '';
        $creditTitle = __('emails.notification.credit_received_title');
        $creditGreeting = __('emails.common.greeting', ['name' => $recipientName]);
        $creditBody = __('emails.notification.credit_sent_body', ['sender' => $senderName, 'amount' => $amountDisplay, 'community' => $tenantName]);
        $viewWalletText = __('emails.notification.view_wallet');
        $creditFooterTagline = __('emails_notifications.credit_received.footer_tagline', ['community' => $tenantName]);

        return <<<HTML
<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f4f4f5;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
  <tr><td style="background:linear-gradient(135deg,#10b981,#059669);padding:32px;text-align:center;">
    <h1 style="margin:0;color:#fff;font-size:24px;">{$creditTitle}</h1>
  </td></tr>
  <tr><td style="padding:32px;">
    <p style="margin:0 0 16px;font-size:16px;color:#374151;">{$creditGreeting}</p>
    <p style="margin:0 0 16px;font-size:16px;color:#374151;">
      {$creditBody}
    </p>
    {$descriptionHtml}
    <div style="text-align:center;margin:28px 0;">
      <a href="{$walletUrl}" style="display:inline-block;padding:14px 32px;background:#10b981;color:#fff;text-decoration:none;border-radius:8px;font-weight:600;font-size:16px;">{$viewWalletText}</a>
    </div>
  </td></tr>
  <tr><td style="padding:16px 32px;background:#f9fafb;border-top:1px solid #e5e7eb;text-align:center;">
    <p style="margin:0;font-size:12px;color:#9ca3af;">{$creditFooterTagline}</p>
  </td></tr>
</table>
</td></tr></table>
</body></html>
HTML;
    }

    private static function buildReviewReceivedEmail(string $recipientName, string $reviewerName, int $rating, ?string $comment, string $profileUrl, string $tenantName): string
    {
        $stars = str_repeat('&#9733;', $rating) . str_repeat('&#9734;', 5 - $rating);
        $starColor = $rating >= 4 ? '#f59e0b' : ($rating >= 3 ? '#6b7280' : '#ef4444');
        $reviewBodyText = __('emails_notifications.review.review_body', ['reviewer' => "<strong>{$reviewerName}</strong>", 'community' => $tenantName]);
        $ratingText = __('emails_notifications.review.rating_text', ['rating' => $rating]);
        $whatTheySaidLabel = __('emails_notifications.review.what_they_said');

        $commentHtml = '';
        if ($comment) {
            $commentHtml = <<<COMMENT
                    <tr>
                        <td style="padding: 0 24px 24px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f9fafb; border-radius: 12px; border: 1px solid #e5e7eb;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <p style="margin: 0 0 8px; font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">{$whatTheySaidLabel}</p>
                                        <p style="margin: 0; font-size: 16px; color: #374151; font-style: italic; line-height: 1.6;">&ldquo;{$comment}&rdquo;</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
COMMENT;
        }

        $gradient = $rating >= 4
            ? 'linear-gradient(135deg, #f59e0b, #d97706)'
            : ($rating >= 3
                ? 'linear-gradient(135deg, #6366f1, #8b5cf6)'
                : 'linear-gradient(135deg, #6b7280, #4b5563)');

        $year = date('Y');
        $sentByText = __('emails.notification.sent_by', ['community' => $tenantName]);
        $allRightsReserved = __('emails.footer.all_rights_reserved');
        $viewProfileText = __('emails.notification.view_profile');
        $reviewGreeting = __('emails.common.greeting', ['name' => $recipientName]);
        $reviewTitle = __('emails.notification.review_title');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>{$reviewTitle}</title></head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f3f4f6;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f3f4f6; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width: 600px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                    <tr>
                        <td style="background: {$gradient}; padding: 32px 24px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 700;">{$reviewTitle}</h1>
                            <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0; font-size: 14px;">{$tenantName}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px 24px 16px;">
                            <p style="margin: 0; font-size: 18px; color: #111827;">{$reviewGreeting}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 24px 16px;">
                            <p style="margin: 0; font-size: 16px; color: #374151; line-height: 1.6;">
                                {$reviewBodyText}
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 24px 24px; text-align: center;">
                            <div style="display: inline-block; padding: 16px 32px; background-color: #fffbeb; border-radius: 12px; border: 1px solid #fde68a;">
                                <span style="font-size: 36px; color: {$starColor}; letter-spacing: 4px;">{$stars}</span>
                                <p style="margin: 8px 0 0; font-size: 14px; color: #92400e; font-weight: 500;">{$ratingText}</p>
                            </div>
                        </td>
                    </tr>
                    {$commentHtml}
                    <tr>
                        <td style="padding: 0 24px 32px; text-align: center;">
                            <a href="{$profileUrl}" style="display: inline-block; background: {$gradient}; color: #ffffff; padding: 14px 32px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 16px;">{$viewProfileText}</a>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #f9fafb; padding: 24px; border-top: 1px solid #e5e7eb;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="text-align: center;">
                                        <p style="margin: 0 0 8px; font-size: 14px; color: #6b7280;">{$sentByText}</p>
                                        <p style="margin: 0; font-size: 12px; color: #9ca3af;">&copy; {$year} {$tenantName}. {$allRightsReserved}</p>
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

    private static function buildRichExchangeEmail(string $type, array $data, array $user, array $details, string $actionUrl): string
    {
        $tenant = TenantContext::get();
        $tenantName = htmlspecialchars($tenant['name'] ?? 'Community', ENT_QUOTES, 'UTF-8');

        $userName = htmlspecialchars($user['first_name'] ?? $user['name'] ?? 'there', ENT_QUOTES, 'UTF-8');
        $listingTitle = htmlspecialchars($details['listing_title'] ?? 'Service Exchange', ENT_QUOTES, 'UTF-8');
        $listingType = $details['listing_type'] ?? 'offer';
        $proposedHours = $details['proposed_hours'] ?? $data['hours'] ?? 0;
        $requesterName = htmlspecialchars($details['requester_first_name'] ?? $details['requester_name'] ?? 'A member', ENT_QUOTES, 'UTF-8');
        $providerName = htmlspecialchars($details['provider_first_name'] ?? $details['provider_name'] ?? 'Provider', ENT_QUOTES, 'UTF-8');

        $emailConfig = self::getExchangeEmailConfig($type, $data, $details);

        $year = date('Y');
        $greeting = __('emails.common.greeting', ['name' => $userName]);
        $sentByText = __('emails.notification.sent_by', ['community' => $tenantName]);
        $allRightsReserved = __('emails.footer.all_rights_reserved');
        $labelService = __('emails_notifications.exchange.label_service');
        $labelRequester = __('emails_notifications.exchange.label_requester');
        $labelProvider = __('emails_notifications.exchange.label_provider');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>{$emailConfig['title']}</title></head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f3f4f6;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f3f4f6; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width: 600px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                    <tr>
                        <td style="background: {$emailConfig['gradient']}; padding: 32px 24px; text-align: center;">
                            <div style="font-size: 48px; margin-bottom: 12px;">{$emailConfig['icon']}</div>
                            <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 700;">{$emailConfig['title']}</h1>
                            <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0; font-size: 14px;">{$tenantName}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px 24px 16px;">
                            <p style="margin: 0; font-size: 18px; color: #111827;">{$greeting}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 24px 24px;">
                            <p style="margin: 0; font-size: 16px; color: #374151; line-height: 1.6;">{$emailConfig['message']}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 24px 24px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f9fafb; border-radius: 12px; border: 1px solid #e5e7eb;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td style="padding-bottom: 16px; border-bottom: 1px solid #e5e7eb;">
                                                    <p style="margin: 0 0 4px; font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">{$labelService}</p>
                                                    <p style="margin: 0; font-size: 18px; color: #111827; font-weight: 600;">{$listingTitle}</p>
                                                    <span style="display: inline-block; margin-top: 8px; padding: 4px 12px; font-size: 12px; font-weight: 500; border-radius: 999px; background-color: {$emailConfig['typeColor']}; color: {$emailConfig['typeText']};">{$emailConfig['typeBadge']}</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding-top: 16px;">
                                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                                        <tr>
                                                            <td width="50%" style="vertical-align: top;">
                                                                <p style="margin: 0 0 4px; font-size: 12px; color: #6b7280;">{$labelRequester}</p>
                                                                <p style="margin: 0; font-size: 14px; color: #111827; font-weight: 500;">{$requesterName}</p>
                                                            </td>
                                                            <td width="50%" style="vertical-align: top;">
                                                                <p style="margin: 0 0 4px; font-size: 12px; color: #6b7280;">{$labelProvider}</p>
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
                    <tr>
                        <td style="padding: 0 24px 32px; text-align: center;">
                            <a href="{$actionUrl}" style="display: inline-block; background: {$emailConfig['gradient']}; color: #ffffff; padding: 14px 32px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 16px;">{$emailConfig['buttonText']}</a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 24px 24px;">
                            <p style="margin: 0; font-size: 14px; color: #6b7280; text-align: center;">{$emailConfig['helpText']}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #f9fafb; padding: 24px; border-top: 1px solid #e5e7eb;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="text-align: center;">
                                        <p style="margin: 0 0 8px; font-size: 14px; color: #6b7280;">{$sentByText}</p>
                                        <p style="margin: 0; font-size: 12px; color: #9ca3af;">&copy; {$year} {$tenantName}. {$allRightsReserved}</p>
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

    private static function getExchangeEmailConfig(string $type, array $data, array $details): array
    {
        $hours = $details['proposed_hours'] ?? $data['hours'] ?? 0;
        $finalHours = $details['final_hours'] ?? $data['hours'] ?? $hours;
        $reason = $data['reason'] ?? '';
        $listingType = $details['listing_type'] ?? 'offer';
        $requesterName = $details['requester_first_name'] ?? $details['requester_name'] ?? 'A member';
        $providerName = $details['provider_first_name'] ?? $details['provider_name'] ?? 'the provider';

        $typeColor = $listingType === 'offer' ? '#dcfce7' : '#fef3c7';
        $typeText = $listingType === 'offer' ? '#166534' : '#92400e';
        $typeBadge = $listingType === 'offer' ? __('notifications.exchange_type_offering') : __('notifications.exchange_type_requesting');

        $labelProposedHours = __('emails_notifications.exchange.label_proposed_hours');
        $labelApprovedHours = __('emails_notifications.exchange.label_approved_hours');
        $labelHoursTransferred = __('emails_notifications.exchange.label_hours_transferred');
        $labelAgreedHours = __('emails_notifications.exchange.label_agreed_hours');
        $labelExpectedHours = __('emails_notifications.exchange.label_expected_hours');
        $labelRequesterConfirmed = __('emails_notifications.exchange.label_requester_confirmed');
        $labelProviderConfirmed = __('emails_notifications.exchange.label_provider_confirmed');
        $labelReasonProvided = __('emails_notifications.exchange.label_reason_provided');
        $labelCoordinatorsNote = __('emails_notifications.exchange.label_coordinators_note');
        $labelNextStep = __('emails_notifications.exchange.label_next_step');
        $labelWellDone = __('emails_notifications.exchange.label_well_done');
        $labelUnderReview = __('emails_notifications.exchange.label_under_review');
        $labelRemember = __('emails_notifications.exchange.label_remember');
        $labelActionNeeded = __('emails_notifications.exchange.label_action_needed');
        $labelWhyApproval = __('emails_notifications.exchange.label_why_approval');

        $configs = [
            'exchange_request_received' => [
                'gradient' => 'linear-gradient(135deg, #6366f1, #8b5cf6)',
                'icon' => '📥',
                'title' => __('notifications.exchange_title_request_received'),
                'message' => __('emails_notifications.exchange.request_received_msg', ['name' => "<strong>{$requesterName}</strong>", 'hours' => "<strong>{$hours} hour(s)</strong>"]),
                'buttonText' => __('notifications.exchange_btn_review'),
                'helpText' => __('notifications.exchange_help_review'),
                'typeColor' => $typeColor,
                'typeText' => $typeText,
                'typeBadge' => $typeBadge,
                'extraDetails' => $hours > 0 ? "
                    <tr>
                        <td style=\"padding-top: 16px; border-top: 1px solid #e5e7eb; margin-top: 16px;\">
                            <p style=\"margin: 0 0 4px; font-size: 12px; color: #6b7280;\">{$labelProposedHours}</p>
                            <p style=\"margin: 0; font-size: 24px; color: #6366f1; font-weight: 700;\">{$hours}h</p>
                        </td>
                    </tr>
                " : '',
                'alertBox' => '',
            ],
            'exchange_request_declined' => [
                'gradient' => 'linear-gradient(135deg, #ef4444, #dc2626)',
                'icon' => '❌',
                'title' => __('notifications.exchange_title_request_declined'),
                'message' => __('emails_notifications.exchange.request_declined_msg', ['name' => "<strong>{$providerName}</strong>"]) . ($reason ? __('emails_notifications.exchange.request_declined_reason', ['reason' => $reason]) : ''),
                'buttonText' => __('notifications.exchange_btn_browse'),
                'helpText' => __('notifications.exchange_help_declined'),
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
                                        <p style=\"margin: 0 0 4px; font-size: 12px; color: #991b1b; font-weight: 600;\">{$labelReasonProvided}</p>
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
                'title' => __('notifications.exchange_title_approved'),
                'message' => __('emails_notifications.exchange.approved_msg'),
                'buttonText' => __('notifications.exchange_btn_start'),
                'helpText' => __('notifications.exchange_help_approved'),
                'typeColor' => $typeColor,
                'typeText' => $typeText,
                'typeBadge' => $typeBadge,
                'extraDetails' => "
                    <tr>
                        <td style=\"padding-top: 16px; border-top: 1px solid #e5e7eb; margin-top: 16px;\">
                            <p style=\"margin: 0 0 4px; font-size: 12px; color: #6b7280;\">{$labelApprovedHours}</p>
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
                                            <strong>{$labelNextStep}</strong> " . __('emails_notifications.exchange.approved_next_step') . "
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
                'title' => __('notifications.exchange_title_rejected'),
                'message' => __('emails_notifications.exchange.rejected_msg') . ($reason ? __('emails_notifications.exchange.rejected_reason', ['reason' => $reason]) : ''),
                'buttonText' => __('notifications.exchange_btn_view'),
                'helpText' => __('notifications.exchange_help_rejected'),
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
                                        <p style=\"margin: 0 0 4px; font-size: 12px; color: #991b1b; font-weight: 600;\">{$labelCoordinatorsNote}</p>
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
                'title' => __('notifications.exchange_title_completed'),
                'message' => __('emails_notifications.exchange.completed_msg', ['hours' => "<strong>{$finalHours} hour(s)</strong>"]),
                'buttonText' => __('notifications.exchange_btn_view_wallet'),
                'helpText' => __('notifications.exchange_help_completed'),
                'typeColor' => $typeColor,
                'typeText' => $typeText,
                'typeBadge' => $typeBadge,
                'extraDetails' => "
                    <tr>
                        <td style=\"padding-top: 16px; border-top: 1px solid #e5e7eb; margin-top: 16px;\">
                            <p style=\"margin: 0 0 4px; font-size: 12px; color: #6b7280;\">{$labelHoursTransferred}</p>
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
                                            <strong>{$labelWellDone}</strong> " . __('emails_notifications.exchange.completed_well_done') . "
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
                'title' => __('notifications.exchange_title_cancelled'),
                'message' => __('emails_notifications.exchange.cancelled_msg') . ($reason ? __('emails_notifications.exchange.cancelled_reason', ['reason' => $reason]) : ''),
                'buttonText' => __('notifications.exchange_btn_view'),
                'helpText' => __('notifications.exchange_help_cancelled'),
                'typeColor' => $typeColor,
                'typeText' => $typeText,
                'typeBadge' => $typeBadge,
                'extraDetails' => '',
                'alertBox' => '',
            ],
            'exchange_disputed' => [
                'gradient' => 'linear-gradient(135deg, #ef4444, #dc2626)',
                'icon' => '⚠️',
                'title' => __('notifications.exchange_title_disputed'),
                'message' => __('emails_notifications.exchange.disputed_msg'),
                'buttonText' => __('notifications.exchange_btn_view_exchange'),
                'helpText' => __('notifications.exchange_help_disputed'),
                'typeColor' => $typeColor,
                'typeText' => $typeText,
                'typeBadge' => $typeBadge,
                'extraDetails' => !empty($data['requester_hours']) && !empty($data['provider_hours']) ? "
                    <tr>
                        <td style=\"padding-top: 16px; border-top: 1px solid #e5e7eb; margin-top: 16px;\">
                            <table role=\"presentation\" width=\"100%\" cellspacing=\"0\" cellpadding=\"0\">
                                <tr>
                                    <td width=\"50%\" style=\"text-align: center; padding: 8px;\">
                                        <p style=\"margin: 0 0 4px; font-size: 12px; color: #6b7280;\">{$labelRequesterConfirmed}</p>
                                        <p style=\"margin: 0; font-size: 20px; color: #ef4444; font-weight: 700;\">{$data['requester_hours']}h</p>
                                    </td>
                                    <td width=\"50%\" style=\"text-align: center; padding: 8px;\">
                                        <p style=\"margin: 0 0 4px; font-size: 12px; color: #6b7280;\">{$labelProviderConfirmed}</p>
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
                                            <strong>{$labelUnderReview}</strong> " . __('emails_notifications.exchange.disputed_under_review') . "
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                ",
            ],
            'exchange_accepted' => [
                'gradient' => 'linear-gradient(135deg, #10b981, #059669)',
                'icon' => '✅',
                'title' => __('notifications.exchange_title_accepted'),
                'message' => __('emails_notifications.exchange.accepted_msg', ['name' => "<strong>{$providerName}</strong>"]),
                'buttonText' => __('notifications.exchange_btn_view_exchange'),
                'helpText' => __('notifications.exchange_help_accepted'),
                'typeColor' => $typeColor,
                'typeText' => $typeText,
                'typeBadge' => $typeBadge,
                'extraDetails' => "
                    <tr>
                        <td style=\"padding-top: 16px; border-top: 1px solid #e5e7eb; margin-top: 16px;\">
                            <p style=\"margin: 0 0 4px; font-size: 12px; color: #6b7280;\">{$labelAgreedHours}</p>
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
                                            <strong>{$labelNextStep}</strong> " . __('emails_notifications.exchange.accepted_next_step', ['name' => $providerName]) . "
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                ",
            ],
            'exchange_pending_broker' => [
                'gradient' => 'linear-gradient(135deg, #f59e0b, #d97706)',
                'icon' => '⏳',
                'title' => __('notifications.exchange_title_pending_broker'),
                'message' => __('emails_notifications.exchange.pending_broker_msg'),
                'buttonText' => __('notifications.exchange_btn_view_exchange'),
                'helpText' => __('notifications.exchange_help_pending_broker'),
                'typeColor' => $typeColor,
                'typeText' => $typeText,
                'typeBadge' => $typeBadge,
                'extraDetails' => "
                    <tr>
                        <td style=\"padding-top: 16px; border-top: 1px solid #e5e7eb; margin-top: 16px;\">
                            <p style=\"margin: 0 0 4px; font-size: 12px; color: #6b7280;\">{$labelProposedHours}</p>
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
                                            <strong>{$labelWhyApproval}</strong> " . __('emails_notifications.exchange.pending_broker_why') . "
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                ",
            ],
            'exchange_started' => [
                'gradient' => 'linear-gradient(135deg, #3b82f6, #1d4ed8)',
                'icon' => '🚀',
                'title' => __('notifications.exchange_title_started'),
                'message' => __('emails_notifications.exchange.started_msg'),
                'buttonText' => __('notifications.exchange_btn_view_exchange'),
                'helpText' => __('notifications.exchange_help_started'),
                'typeColor' => $typeColor,
                'typeText' => $typeText,
                'typeBadge' => $typeBadge,
                'extraDetails' => "
                    <tr>
                        <td style=\"padding-top: 16px; border-top: 1px solid #e5e7eb; margin-top: 16px;\">
                            <p style=\"margin: 0 0 4px; font-size: 12px; color: #6b7280;\">{$labelExpectedHours}</p>
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
                                            <strong>{$labelRemember}</strong> " . __('emails_notifications.exchange.started_remember') . "
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                ",
            ],
            'exchange_ready_confirmation' => [
                'gradient' => 'linear-gradient(135deg, #8b5cf6, #7c3aed)',
                'icon' => '✋',
                'title' => __('notifications.exchange_title_ready_confirmation'),
                'message' => __('emails_notifications.exchange.ready_confirmation_msg'),
                'buttonText' => __('notifications.exchange_btn_confirm'),
                'helpText' => __('notifications.exchange_help_ready_confirmation'),
                'typeColor' => $typeColor,
                'typeText' => $typeText,
                'typeBadge' => $typeBadge,
                'extraDetails' => "
                    <tr>
                        <td style=\"padding-top: 16px; border-top: 1px solid #e5e7eb; margin-top: 16px;\">
                            <p style=\"margin: 0 0 4px; font-size: 12px; color: #6b7280;\">{$labelProposedHours}</p>
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
                                            <strong>{$labelActionNeeded}</strong> " . __('emails_notifications.exchange.ready_confirmation_action') . "
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
            'title' => __('notifications.exchange_title_default'),
            'message' => __('notifications.exchange_msg_default'),
            'buttonText' => __('notifications.exchange_btn_view_exchange'),
            'helpText' => __('notifications.exchange_help_default'),
            'typeColor' => $typeColor,
            'typeText' => $typeText,
            'typeBadge' => $typeBadge,
            'extraDetails' => '',
            'alertBox' => '',
        ];
    }

    private static function buildVerificationPassedEmail(): string
    {
        $tenant = TenantContext::get();
        $tenantName = htmlspecialchars($tenant['name'] ?? 'Community', ENT_QUOTES, 'UTF-8');
        $basePath = TenantContext::getSlugPrefix();
        $frontendUrl = TenantContext::getFrontendUrl();

        $heading = __('notifications.verification_passed_heading');
        $body = __('notifications.verification_passed_body');
        $cta = __('notifications.verification_passed_cta');

        return <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #22c55e, #16a34a); padding: 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 24px;">{$heading}</h1>
        <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0;">{$tenantName}</p>
    </div>
    <div style="background: #f8fafc; padding: 24px; border-radius: 0 0 16px 16px; border: 1px solid #e2e8f0; border-top: none;">
        <p style="color: #1e293b; font-size: 16px; line-height: 1.6;">{$body}</p>
        <div style="text-align: center; margin-top: 24px;">
            <a href="{$frontendUrl}{$basePath}/dashboard" style="display: inline-block; background: linear-gradient(135deg, #22c55e, #16a34a); color: white; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-weight: 600;">{$cta}</a>
        </div>
    </div>
</div>
HTML;
    }

    private static function buildVerificationFailedEmail(string $reason): string
    {
        $tenant = TenantContext::get();
        $tenantName = htmlspecialchars($tenant['name'] ?? 'Community', ENT_QUOTES, 'UTF-8');
        $basePath = TenantContext::getSlugPrefix();
        $frontendUrl = TenantContext::getFrontendUrl();
        $reasonHtml = !empty($reason) ? "<p style=\"color: #dc2626; font-weight: 500; margin: 16px 0;\">" . __('notifications.label_reason') . " " . htmlspecialchars($reason) . "</p>" : '';

        $heading = __('notifications.verification_failed_heading');
        $body = __('notifications.verification_failed_body');
        $cta = __('notifications.verification_failed_cta');

        return <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #dc2626, #991b1b); padding: 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 24px;">{$heading}</h1>
        <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0;">{$tenantName}</p>
    </div>
    <div style="background: #f8fafc; padding: 24px; border-radius: 0 0 16px 16px; border: 1px solid #e2e8f0; border-top: none;">
        <p style="color: #1e293b; font-size: 16px; line-height: 1.6;">{$body}</p>
        {$reasonHtml}
        <div style="text-align: center; margin-top: 24px;">
            <a href="{$frontendUrl}{$basePath}/verify-identity" style="display: inline-block; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-weight: 600;">{$cta}</a>
        </div>
    </div>
</div>
HTML;
    }

    // =========================================================================
    // VOLUNTEERING EMAIL BUILDERS
    // =========================================================================

    /**
     * Build HTML email for volunteer application approval.
     */
    public static function buildVolApplicationApprovedEmail(string $oppTitle, int $oppId): string
    {
        $tenant = TenantContext::get();
        $tenantName = htmlspecialchars($tenant['name'] ?? 'Community', ENT_QUOTES, 'UTF-8');
        $basePath = TenantContext::getSlugPrefix();
        $frontendUrl = TenantContext::getFrontendUrl();
        $oppTitleHtml = htmlspecialchars($oppTitle, ENT_QUOTES, 'UTF-8');

        $volAcceptedHeading = __('emails_notifications.volunteering.heading_accepted');
        $volTenantLabel = __('emails_notifications.volunteering.tenant_volunteering', ['community' => $tenantName]);
        $volApprovedBody = __('emails_notifications.volunteering.approved_body');
        $volLabelOpp = __('emails_notifications.volunteering.label_opportunity');
        $volApprovedNext = __('emails_notifications.volunteering.approved_next');
        $volBtnViewOpp = __('emails_notifications.volunteering.btn_view_opportunity');

        return <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #22c55e, #059669); padding: 32px 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <div style="font-size: 48px; margin-bottom: 12px;">🎉</div>
        <h1 style="color: white; margin: 0; font-size: 24px;">{$volAcceptedHeading}</h1>
        <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0; font-size: 14px;">{$volTenantLabel}</p>
    </div>
    <div style="background: #f8fafc; padding: 32px 24px; border-radius: 0 0 16px 16px; border: 1px solid #e2e8f0; border-top: none;">
        <p style="color: #1e293b; font-size: 16px; line-height: 1.6; margin: 0 0 16px;">
            {$volApprovedBody}
        </p>
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin: 16px 0;">
            <p style="color: #64748b; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 4px;">{$volLabelOpp}</p>
            <p style="color: #1e293b; font-size: 18px; font-weight: 600; margin: 0;">{$oppTitleHtml}</p>
        </div>
        <p style="color: #475569; font-size: 14px; line-height: 1.6;">
            {$volApprovedNext}
        </p>
        <div style="text-align: center; margin-top: 24px;">
            <a href="{$frontendUrl}{$basePath}/volunteering/opportunities/{$oppId}" style="display: inline-block; background: linear-gradient(135deg, #22c55e, #059669); color: white; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-weight: 600; font-size: 16px;">{$volBtnViewOpp}</a>
        </div>
    </div>
</div>
HTML;
    }

    /**
     * Build HTML email for volunteer application decline.
     */
    public static function buildVolApplicationDeclinedEmail(string $oppTitle): string
    {
        $tenant = TenantContext::get();
        $tenantName = htmlspecialchars($tenant['name'] ?? 'Community', ENT_QUOTES, 'UTF-8');
        $basePath = TenantContext::getSlugPrefix();
        $frontendUrl = TenantContext::getFrontendUrl();
        $oppTitleHtml = htmlspecialchars($oppTitle, ENT_QUOTES, 'UTF-8');

        $volDeclinedHeading = __('emails_notifications.volunteering.heading_declined');
        $volTenantLabel = __('emails_notifications.volunteering.tenant_volunteering', ['community' => $tenantName]);
        $volDeclinedBody = __('emails_notifications.volunteering.declined_body');
        $volLabelOpp = __('emails_notifications.volunteering.label_opportunity');
        $volDeclinedEncouragement = __('emails_notifications.volunteering.declined_encouragement');
        $volBtnBrowse = __('emails_notifications.volunteering.btn_browse_opportunities');

        return <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #6366f1, #8b5cf6); padding: 32px 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 24px;">{$volDeclinedHeading}</h1>
        <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0; font-size: 14px;">{$volTenantLabel}</p>
    </div>
    <div style="background: #f8fafc; padding: 32px 24px; border-radius: 0 0 16px 16px; border: 1px solid #e2e8f0; border-top: none;">
        <p style="color: #1e293b; font-size: 16px; line-height: 1.6; margin: 0 0 16px;">
            {$volDeclinedBody}
        </p>
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin: 16px 0;">
            <p style="color: #64748b; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 4px;">{$volLabelOpp}</p>
            <p style="color: #1e293b; font-size: 18px; font-weight: 600; margin: 0;">{$oppTitleHtml}</p>
        </div>
        <p style="color: #475569; font-size: 14px; line-height: 1.6;">
            {$volDeclinedEncouragement}
        </p>
        <div style="text-align: center; margin-top: 24px;">
            <a href="{$frontendUrl}{$basePath}/volunteering" style="display: inline-block; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-weight: 600; font-size: 16px;">{$volBtnBrowse}</a>
        </div>
    </div>
</div>
HTML;
    }

    /**
     * Build HTML email for volunteer hours approved with payment.
     */
    public static function buildVolHoursApprovedPaidEmail(float $hours, string $orgName): string
    {
        $tenant = TenantContext::get();
        $tenantName = htmlspecialchars($tenant['name'] ?? 'Community', ENT_QUOTES, 'UTF-8');
        $basePath = TenantContext::getSlugPrefix();
        $frontendUrl = TenantContext::getFrontendUrl();
        $orgNameHtml = htmlspecialchars($orgName, ENT_QUOTES, 'UTF-8');

        $volHoursPaidHeading = __('emails_notifications.volunteering.heading_hours_paid');
        $volTenantLabel = __('emails_notifications.volunteering.tenant_volunteering', ['community' => $tenantName]);
        $volHoursPaidBody = __('emails_notifications.volunteering.hours_paid_body');
        $volLabelHoursApproved = __('emails_notifications.volunteering.label_hours_approved');
        $volLabelCreditsEarned = __('emails_notifications.volunteering.label_credits_earned');
        $volHoursPaidDetail = __('emails_notifications.volunteering.hours_paid_detail', ['org' => "<strong>{$orgNameHtml}</strong>", 'hours' => "<strong>{$hours} time credits</strong>"]);
        $volBtnViewWallet = __('emails_notifications.volunteering.btn_view_wallet');

        return <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #22c55e, #059669); padding: 32px 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <div style="font-size: 48px; margin-bottom: 12px;">⏰💰</div>
        <h1 style="color: white; margin: 0; font-size: 24px;">{$volHoursPaidHeading}</h1>
        <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0; font-size: 14px;">{$volTenantLabel}</p>
    </div>
    <div style="background: #f8fafc; padding: 32px 24px; border-radius: 0 0 16px 16px; border: 1px solid #e2e8f0; border-top: none;">
        <p style="color: #1e293b; font-size: 16px; line-height: 1.6; margin: 0 0 16px;">
            {$volHoursPaidBody}
        </p>
        <div style="display: flex; gap: 16px; margin: 20px 0;">
            <div style="flex: 1; background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; text-align: center;">
                <p style="color: #64748b; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 4px;">{$volLabelHoursApproved}</p>
                <p style="color: #22c55e; font-size: 28px; font-weight: 700; margin: 0;">{$hours}h</p>
            </div>
            <div style="flex: 1; background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; text-align: center;">
                <p style="color: #64748b; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 4px;">{$volLabelCreditsEarned}</p>
                <p style="color: #22c55e; font-size: 28px; font-weight: 700; margin: 0;">{$hours}</p>
            </div>
        </div>
        <p style="color: #475569; font-size: 14px; line-height: 1.6;">
            {$volHoursPaidDetail}
        </p>
        <div style="text-align: center; margin-top: 24px;">
            <a href="{$frontendUrl}{$basePath}/wallet" style="display: inline-block; background: linear-gradient(135deg, #22c55e, #059669); color: white; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-weight: 600; font-size: 16px;">{$volBtnViewWallet}</a>
        </div>
    </div>
</div>
HTML;
    }

    /**
     * Build HTML email for volunteer hours approved (no payment).
     */
    public static function buildVolHoursApprovedEmail(float $hours, string $orgName): string
    {
        $tenant = TenantContext::get();
        $tenantName = htmlspecialchars($tenant['name'] ?? 'Community', ENT_QUOTES, 'UTF-8');
        $basePath = TenantContext::getSlugPrefix();
        $frontendUrl = TenantContext::getFrontendUrl();
        $orgNameHtml = htmlspecialchars($orgName, ENT_QUOTES, 'UTF-8');

        $volHoursApprovedHeading = __('emails_notifications.volunteering.heading_hours_approved');
        $volTenantLabel = __('emails_notifications.volunteering.tenant_volunteering', ['community' => $tenantName]);
        $volHoursApprovedBody = __('emails_notifications.volunteering.hours_approved_body', ['org' => "<strong>{$orgNameHtml}</strong>"]);
        $volLabelHoursApproved = __('emails_notifications.volunteering.label_hours_approved');
        $volHoursApprovedThanks = __('emails_notifications.volunteering.hours_approved_thanks');
        $volBtnViewHours = __('emails_notifications.volunteering.btn_view_hours');

        return <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #22c55e, #059669); padding: 32px 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <div style="font-size: 48px; margin-bottom: 12px;">✅</div>
        <h1 style="color: white; margin: 0; font-size: 24px;">{$volHoursApprovedHeading}</h1>
        <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0; font-size: 14px;">{$volTenantLabel}</p>
    </div>
    <div style="background: #f8fafc; padding: 32px 24px; border-radius: 0 0 16px 16px; border: 1px solid #e2e8f0; border-top: none;">
        <p style="color: #1e293b; font-size: 16px; line-height: 1.6; margin: 0 0 16px;">
            {$volHoursApprovedBody}
        </p>
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; text-align: center; margin: 20px 0;">
            <p style="color: #64748b; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 4px;">{$volLabelHoursApproved}</p>
            <p style="color: #22c55e; font-size: 28px; font-weight: 700; margin: 0;">{$hours}h</p>
        </div>
        <p style="color: #475569; font-size: 14px; line-height: 1.6;">
            {$volHoursApprovedThanks}
        </p>
        <div style="text-align: center; margin-top: 24px;">
            <a href="{$frontendUrl}{$basePath}/volunteering?tab=hours" style="display: inline-block; background: linear-gradient(135deg, #22c55e, #059669); color: white; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-weight: 600; font-size: 16px;">{$volBtnViewHours}</a>
        </div>
    </div>
</div>
HTML;
    }

    /**
     * Build HTML email for volunteer hours declined.
     */
    public static function buildVolHoursDeclinedEmail(float $hours, string $orgName): string
    {
        $tenant = TenantContext::get();
        $tenantName = htmlspecialchars($tenant['name'] ?? 'Community', ENT_QUOTES, 'UTF-8');
        $basePath = TenantContext::getSlugPrefix();
        $frontendUrl = TenantContext::getFrontendUrl();
        $orgNameHtml = htmlspecialchars($orgName, ENT_QUOTES, 'UTF-8');

        $volHoursDeclinedHeading = __('emails_notifications.volunteering.heading_hours_declined');
        $volTenantLabel = __('emails_notifications.volunteering.tenant_volunteering', ['community' => $tenantName]);
        $volHoursDeclinedBody = __('emails_notifications.volunteering.hours_declined_body', ['hours' => "<strong>{$hours}h</strong>", 'org' => "<strong>{$orgNameHtml}</strong>"]);
        $volHoursDeclinedNote = __('emails_notifications.volunteering.hours_declined_note');
        $volBtnViewHours = __('emails_notifications.volunteering.btn_view_hours');

        return <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #6366f1, #8b5cf6); padding: 32px 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 24px;">{$volHoursDeclinedHeading}</h1>
        <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0; font-size: 14px;">{$volTenantLabel}</p>
    </div>
    <div style="background: #f8fafc; padding: 32px 24px; border-radius: 0 0 16px 16px; border: 1px solid #e2e8f0; border-top: none;">
        <p style="color: #1e293b; font-size: 16px; line-height: 1.6; margin: 0 0 16px;">
            {$volHoursDeclinedBody}
        </p>
        <p style="color: #475569; font-size: 14px; line-height: 1.6;">
            {$volHoursDeclinedNote}
        </p>
        <div style="text-align: center; margin-top: 24px;">
            <a href="{$frontendUrl}{$basePath}/volunteering?tab=hours" style="display: inline-block; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-weight: 600; font-size: 16px;">{$volBtnViewHours}</a>
        </div>
    </div>
</div>
HTML;
    }

    /**
     * Build HTML email for new volunteer application received (sent to org owner).
     */
    public static function buildVolApplicationReceivedEmail(string $volunteerName, string $oppTitle, int $orgId): string
    {
        $tenant = TenantContext::get();
        $tenantName = htmlspecialchars($tenant['name'] ?? 'Community', ENT_QUOTES, 'UTF-8');
        $basePath = TenantContext::getSlugPrefix();
        $frontendUrl = TenantContext::getFrontendUrl();
        $volunteerHtml = htmlspecialchars($volunteerName, ENT_QUOTES, 'UTF-8');
        $oppTitleHtml = htmlspecialchars($oppTitle, ENT_QUOTES, 'UTF-8');

        $volAppReceivedHeading = __('emails_notifications.volunteering.heading_application_received');
        $volTenantLabel = __('emails_notifications.volunteering.tenant_volunteering', ['community' => $tenantName]);
        $volAppReceivedBody = __('emails_notifications.volunteering.application_received_body', ['name' => "<strong>{$volunteerHtml}</strong>"]);
        $volLabelOpp = __('emails_notifications.volunteering.label_opportunity');
        $volAppReceivedNote = __('emails_notifications.volunteering.application_received_note');
        $volBtnReviewApp = __('emails_notifications.volunteering.btn_review_application');

        return <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #f43f5e, #e11d48); padding: 32px 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <div style="font-size: 48px; margin-bottom: 12px;">🙋</div>
        <h1 style="color: white; margin: 0; font-size: 24px;">{$volAppReceivedHeading}</h1>
        <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0; font-size: 14px;">{$volTenantLabel}</p>
    </div>
    <div style="background: #f8fafc; padding: 32px 24px; border-radius: 0 0 16px 16px; border: 1px solid #e2e8f0; border-top: none;">
        <p style="color: #1e293b; font-size: 16px; line-height: 1.6; margin: 0 0 16px;">
            {$volAppReceivedBody}
        </p>
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin: 16px 0;">
            <p style="color: #64748b; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 4px;">{$volLabelOpp}</p>
            <p style="color: #1e293b; font-size: 18px; font-weight: 600; margin: 0;">{$oppTitleHtml}</p>
        </div>
        <p style="color: #475569; font-size: 14px; line-height: 1.6;">
            {$volAppReceivedNote}
        </p>
        <div style="text-align: center; margin-top: 24px;">
            <a href="{$frontendUrl}{$basePath}/volunteering/org/{$orgId}/dashboard?tab=applications" style="display: inline-block; background: linear-gradient(135deg, #f43f5e, #e11d48); color: white; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-weight: 600; font-size: 16px;">{$volBtnReviewApp}</a>
        </div>
    </div>
</div>
HTML;
    }
}
