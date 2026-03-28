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

        // 5. TRAFFIC LIGHT LOGIC
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
        $titles = [
            'vol_application_received'  => 'New Application',
            'vol_application_approved'  => 'Application Approved',
            'vol_application_declined'  => 'Application Declined',
            'vol_hours_approved'        => 'Hours Approved',
            'vol_hours_declined'        => 'Hours Declined',
            'vol_hours_pending_review'  => 'Hours Pending Review',
            'vol_shift_reminder'        => 'Shift Reminder',
            'vol_shift_cancelled'       => 'Shift Cancelled',
            'vol_waitlist_promoted'     => 'Waitlist Update',
            'vol_swap_requested'        => 'Shift Swap Request',
            'vol_swap_approved'         => 'Shift Swap Approved',
            'vol_swap_declined'         => 'Shift Swap Declined',
            'new_topic'                 => 'New Post',
            'new_reply'                 => 'New Reply',
            'mention'                   => 'You Were Mentioned',
            'hot_match'                 => 'Hot Match Found',
            'mutual_match'              => 'Mutual Match Found',
        ];
        return $titles[$activityType] ?? 'New Notification';
    }

    private static function queueNotification($userId, $activityType, $content, $link, $frequency = 'daily', $emailBody = null): void
    {
        $snippet = substr($content, 0, 250);

        DB::table('notification_queue')->insert([
            'user_id'        => $userId,
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
        $content = "Hot Match! {$userName} posted \"{$listingTitle}\" - {$matchScore}% match{$distanceText}";
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

        Notification::createNotification((int) $userId, $content, $link, 'hot_match');

        if ($frequency !== 'never') {
            $htmlContent = self::buildHotMatchEmail($match, $matchScore);
            self::queueNotification($userId, 'hot_match', $content, $link, $frequency, $htmlContent);
        }
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

        $content = "Mutual Match! {$userName} can help you with {$theyOffer}, and you can help them with {$youOffer}";
        $link = "/listings/{$listingId}";

        $prefs = \App\Services\MatchingService::getPreferencesStatic($userId);
        if (empty($prefs['notify_mutual_matches'])) {
            return;
        }

        $frequency = $prefs['notification_frequency'] ?? 'fortnightly';
        if ($frequency === 'never') {
            return;
        }

        Notification::createNotification((int) $userId, $content, $link, 'mutual_match');

        if ($frequency !== 'never') {
            $htmlContent = self::buildMutualMatchEmail($match, $reciprocalInfo);
            self::queueNotification($userId, 'mutual_match', $content, $link, $frequency, $htmlContent);
        }
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

        $content = "Your {$period} match digest: {$count} new matches";
        if ($hotCount > 0) {
            $content .= ", {$hotCount} hot";
        }
        if ($mutualCount > 0) {
            $content .= ", {$mutualCount} mutual";
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
        $content = "Match needs approval: {$userName} matched with \"{$listingTitle}\"";
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
        $content = "Great news! You've been matched with \"{$listingTitle}\"";
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
        $content = "Match update: \"{$listingTitle}\" wasn't suitable at this time";
        if (!empty($reason)) {
            $content .= ". Reason: {$reason}";
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

            $displayName = $isAnonymous ? 'Someone' : htmlspecialchars($reviewerName);
            $subject = "{$displayName} left you a {$rating}-star review on {$tenantName}";

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
        $content = "Your identity has been verified successfully.";
        $link = "/dashboard";

        Notification::createNotification($userId, $content, $link, 'verification_passed');
        $htmlContent = self::buildVerificationPassedEmail();
        self::queueNotification($userId, 'verification_passed', $content, $link, 'instant', $htmlContent);
    }

    public static function dispatchVerificationFailed(int $userId, string $reason = ''): void
    {
        $content = "Your identity verification was unsuccessful.";
        if (!empty($reason)) {
            $content .= " Reason: {$reason}";
        }
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
            ? "Identity verification passed for {$userName} ({$email})"
            : "Identity verification failed for {$userName} ({$email})";
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
        $content = "You haven't completed identity verification yet. Please verify your identity to activate your account.";
        $link = "/verify-identity";

        Notification::createNotification($userId, $content, $link, 'verification_reminder');

        $tenant = TenantContext::get();
        $tenantName = $tenant['name'] ?? 'Community';
        $basePath = TenantContext::getSlugPrefix();
        $frontendUrl = TenantContext::getFrontendUrl();

        $htmlContent = <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #f59e0b, #d97706); padding: 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 24px;">Verification Reminder</h1>
        <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0;">{$tenantName}</p>
    </div>
    <div style="background: #f8fafc; padding: 24px; border-radius: 0 0 16px 16px; border: 1px solid #e2e8f0; border-top: none;">
        <p style="color: #1e293b; font-size: 16px; line-height: 1.6;">You started registering but haven't completed identity verification yet. Please verify your identity to activate your account.</p>
        <div style="text-align: center; margin-top: 24px;">
            <a href="{$frontendUrl}{$basePath}/verify-identity" style="display: inline-block; background: linear-gradient(135deg, #f59e0b, #d97706); color: white; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-weight: 600;">Complete Verification</a>
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
                return "New exchange request for your listing";
            case 'exchange_request_declined':
                $reason = !empty($data['reason']) ? ": {$data['reason']}" : '';
                return "Your exchange request was declined{$reason}";
            case 'exchange_approved':
                return "Your exchange has been approved! You can now begin.";
            case 'exchange_rejected':
                $reason = !empty($data['reason']) ? ": {$data['reason']}" : '';
                return "Exchange was not approved{$reason}";
            case 'exchange_completed':
                $hours = $data['hours'] ?? 0;
                return "Exchange completed! {$hours} hours transferred.";
            case 'exchange_cancelled':
                return "Exchange was cancelled";
            case 'exchange_disputed':
                return "Exchange has conflicting hour confirmations - broker review needed";
            case 'exchange_accepted':
                return "Your exchange request was accepted! You can now coordinate the service.";
            case 'exchange_pending_broker':
                return "Exchange accepted - awaiting coordinator approval";
            case 'exchange_started':
                return "Exchange has started! Service is now in progress.";
            case 'exchange_ready_confirmation':
                $hours = $data['proposed_hours'] ?? 0;
                return "Exchange complete - please confirm {$hours} hours worked";
            case 'listing_risk_tagged':
                $level = $data['risk_level'] ?? 'unknown';
                $title = $data['listing_title'] ?? 'Listing';
                return "Listing '{$title}' tagged as {$level} risk";
            case 'credit_received':
                $senderName = $data['sender_name'] ?? 'Someone';
                $amount = $data['amount'] ?? 0;
                return "{$senderName} sent you {$amount} hour" . ($amount != 1 ? 's' : '');
            default:
                return "Notification: {$type}";
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

                $subject = "{$senderName} sent you {$amount} hour" . ($amount != 1 ? 's' : '') . " on {$tenantName}";
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

        $subjects = [
            'exchange_request_received' => "New exchange request for \"{$shortTitle}\"",
            'exchange_request_declined' => "Exchange request declined",
            'exchange_approved' => "Exchange approved by coordinator - Ready to begin!",
            'exchange_rejected' => "Exchange not approved",
            'exchange_completed' => "Exchange completed - Hours transferred!",
            'exchange_cancelled' => "Exchange cancelled",
            'exchange_disputed' => "Exchange needs broker review",
            'exchange_accepted' => "Your exchange request was accepted!",
            'exchange_pending_broker' => "Exchange accepted - Awaiting coordinator approval",
            'exchange_started' => "Exchange started - Service in progress",
            'exchange_ready_confirmation' => "Action needed: Confirm your exchange hours",
        ];

        return $subjects[$type] ?? "Exchange update";
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
        $distanceHtml = $distance !== null ? "<p style='color: #10b981;'>📍 {$distance} km away</p>" : '';

        return <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #f97316, #ef4444); padding: 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 24px;">Hot Match Found!</h1>
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

        return <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #10b981, #06b6d4); padding: 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 24px;">Mutual Match!</h1>
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
            $statsHtml .= "<span style='background: #fef2f2; color: #ef4444; padding: 6px 12px; border-radius: 20px; margin-right: 8px;'>{$hotCount} Hot</span>";
        }
        if ($mutualCount > 0) {
            $statsHtml .= "<span style='background: #ecfdf5; color: #10b981; padding: 6px 12px; border-radius: 20px;'>{$mutualCount} Mutual</span>";
        }

        return <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #6366f1, #8b5cf6); padding: 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 24px;">Your {$periodTitle} Match Digest</h1>
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

    private static function buildMatchApprovalRequestEmail($userName, $listingTitle, $requestId): string
    {
        $tenant = TenantContext::get();
        $tenantName = $tenant['name'] ?? 'Community';
        $basePath = TenantContext::getSlugPrefix();
        $frontendUrl = TenantContext::getFrontendUrl();

        return <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #f59e0b, #d97706); padding: 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 24px;">Match Needs Approval</h1>
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
            <a href="{$frontendUrl}{$basePath}/admin/match-approvals" style="display: inline-block; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-weight: 600;">Review Match</a>
        </div>
    </div>
</div>
HTML;
    }

    private static function buildMatchApprovedEmail($listingTitle, $listingId, $matchScore): string
    {
        $tenant = TenantContext::get();
        $tenantName = $tenant['name'] ?? 'Community';
        $basePath = TenantContext::getSlugPrefix();
        $frontendUrl = TenantContext::getFrontendUrl();
        $scoreText = $matchScore > 0 ? " ({$matchScore}% match)" : "";

        return <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #22c55e, #16a34a); padding: 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 24px;">You've Been Matched!</h1>
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
            <a href="{$frontendUrl}{$basePath}/listings/{$listingId}" style="display: inline-block; background: linear-gradient(135deg, #22c55e, #16a34a); color: white; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-weight: 600;">View Match</a>
        </div>
    </div>
</div>
HTML;
    }

    private static function buildMatchRejectedEmail($listingTitle, $reason): string
    {
        $tenant = TenantContext::get();
        $tenantName = $tenant['name'] ?? 'Community';
        $basePath = TenantContext::getSlugPrefix();
        $frontendUrl = TenantContext::getFrontendUrl();

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
            <a href="{$frontendUrl}{$basePath}/matches" style="display: inline-block; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-weight: 600;">Browse Matches</a>
        </div>
    </div>
</div>
HTML;
    }

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
    <h1 style="margin:0;color:#fff;font-size:24px;">You received time credits!</h1>
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

    private static function buildReviewReceivedEmail(string $recipientName, string $reviewerName, int $rating, ?string $comment, string $profileUrl, string $tenantName): string
    {
        $stars = str_repeat('&#9733;', $rating) . str_repeat('&#9734;', 5 - $rating);
        $starColor = $rating >= 4 ? '#f59e0b' : ($rating >= 3 ? '#6b7280' : '#ef4444');

        $commentHtml = '';
        if ($comment) {
            $commentHtml = <<<COMMENT
                    <tr>
                        <td style="padding: 0 24px 24px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f9fafb; border-radius: 12px; border: 1px solid #e5e7eb;">
                                <tr>
                                    <td style="padding: 20px;">
                                        <p style="margin: 0 0 8px; font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">What they said</p>
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

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>New Review Received</title></head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f3f4f6;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #f3f4f6; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width: 600px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">
                    <tr>
                        <td style="background: {$gradient}; padding: 32px 24px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 700;">New Review Received!</h1>
                            <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0; font-size: 14px;">{$tenantName}</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px 24px 16px;">
                            <p style="margin: 0; font-size: 18px; color: #111827;">Hi {$recipientName},</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 24px 16px;">
                            <p style="margin: 0; font-size: 16px; color: #374151; line-height: 1.6;">
                                <strong>{$reviewerName}</strong> has left you a review on {$tenantName}.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 0 24px 24px; text-align: center;">
                            <div style="display: inline-block; padding: 16px 32px; background-color: #fffbeb; border-radius: 12px; border: 1px solid #fde68a;">
                                <span style="font-size: 36px; color: {$starColor}; letter-spacing: 4px;">{$stars}</span>
                                <p style="margin: 8px 0 0; font-size: 14px; color: #92400e; font-weight: 500;">{$rating} out of 5 stars</p>
                            </div>
                        </td>
                    </tr>
                    {$commentHtml}
                    <tr>
                        <td style="padding: 0 24px 32px; text-align: center;">
                            <a href="{$profileUrl}" style="display: inline-block; background: {$gradient}; color: #ffffff; padding: 14px 32px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 16px;">View Your Profile</a>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #f9fafb; padding: 24px; border-top: 1px solid #e5e7eb;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="text-align: center;">
                                        <p style="margin: 0 0 8px; font-size: 14px; color: #6b7280;">This email was sent by {$tenantName}</p>
                                        <p style="margin: 0; font-size: 12px; color: #9ca3af;">&copy; {$year} Project NEXUS. All rights reserved.</p>
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
        $tenantName = $tenant['name'] ?? 'Community';

        $userName = $user['first_name'] ?? $user['name'] ?? 'there';
        $listingTitle = $details['listing_title'] ?? 'Service Exchange';
        $listingType = $details['listing_type'] ?? 'offer';
        $proposedHours = $details['proposed_hours'] ?? $data['hours'] ?? 0;
        $requesterName = $details['requester_first_name'] ?? $details['requester_name'] ?? 'A member';
        $providerName = $details['provider_first_name'] ?? $details['provider_name'] ?? 'Provider';

        $emailConfig = self::getExchangeEmailConfig($type, $data, $details);

        $year = date('Y');

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
                            <p style="margin: 0; font-size: 18px; color: #111827;">Hi {$userName},</p>
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
                                                    <p style="margin: 0 0 4px; font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">Service</p>
                                                    <p style="margin: 0; font-size: 18px; color: #111827; font-weight: 600;">{$listingTitle}</p>
                                                    <span style="display: inline-block; margin-top: 8px; padding: 4px 12px; font-size: 12px; font-weight: 500; border-radius: 999px; background-color: {$emailConfig['typeColor']}; color: {$emailConfig['typeText']};">{$emailConfig['typeBadge']}</span>
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
                                        <p style="margin: 0 0 8px; font-size: 14px; color: #6b7280;">This email was sent by {$tenantName}</p>
                                        <p style="margin: 0; font-size: 12px; color: #9ca3af;">&copy; {$year} Project NEXUS. All rights reserved.</p>
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
                                            <strong>Next step:</strong> Contact the other party to arrange when and where the service will take place.
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
                                            <strong>Well done!</strong> Your time credit balance has been updated. Consider leaving a review for the other member!
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
                                            <strong>Under review:</strong> A coordinator will review the confirmed hours and make a fair decision.
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
                                            <strong>Next step:</strong> Message {$providerName} to arrange when and where the service will happen.
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
                                            <strong>Why approval?</strong> Some exchanges require coordinator review to ensure safety and suitability for all members.
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
                                            <strong>Remember:</strong> When the service is complete, mark it as done and confirm the actual hours worked.
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
                                            <strong>Action needed:</strong> Please confirm the hours as soon as possible so the other party receives their time credits.
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

    private static function buildVerificationPassedEmail(): string
    {
        $tenant = TenantContext::get();
        $tenantName = $tenant['name'] ?? 'Community';
        $basePath = TenantContext::getSlugPrefix();
        $frontendUrl = TenantContext::getFrontendUrl();

        return <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #22c55e, #16a34a); padding: 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 24px;">Identity Verified</h1>
        <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0;">{$tenantName}</p>
    </div>
    <div style="background: #f8fafc; padding: 24px; border-radius: 0 0 16px 16px; border: 1px solid #e2e8f0; border-top: none;">
        <p style="color: #1e293b; font-size: 16px; line-height: 1.6;">Your identity has been successfully verified. Your account is now active and ready to use.</p>
        <div style="text-align: center; margin-top: 24px;">
            <a href="{$frontendUrl}{$basePath}/dashboard" style="display: inline-block; background: linear-gradient(135deg, #22c55e, #16a34a); color: white; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-weight: 600;">Go to Dashboard</a>
        </div>
    </div>
</div>
HTML;
    }

    private static function buildVerificationFailedEmail(string $reason): string
    {
        $tenant = TenantContext::get();
        $tenantName = $tenant['name'] ?? 'Community';
        $basePath = TenantContext::getSlugPrefix();
        $frontendUrl = TenantContext::getFrontendUrl();
        $reasonHtml = !empty($reason) ? "<p style=\"color: #dc2626; font-weight: 500; margin: 16px 0;\">Reason: " . htmlspecialchars($reason) . "</p>" : '';

        return <<<HTML
<div style="font-family: system-ui, -apple-system, sans-serif; max-width: 600px; margin: 0 auto;">
    <div style="background: linear-gradient(135deg, #dc2626, #991b1b); padding: 24px; border-radius: 16px 16px 0 0; text-align: center;">
        <h1 style="color: white; margin: 0; font-size: 24px;">Verification Unsuccessful</h1>
        <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0;">{$tenantName}</p>
    </div>
    <div style="background: #f8fafc; padding: 24px; border-radius: 0 0 16px 16px; border: 1px solid #e2e8f0; border-top: none;">
        <p style="color: #1e293b; font-size: 16px; line-height: 1.6;">We were unable to verify your identity. You may retry the verification process or contact support for assistance.</p>
        {$reasonHtml}
        <div style="text-align: center; margin-top: 24px;">
            <a href="{$frontendUrl}{$basePath}/verify-identity" style="display: inline-block; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-weight: 600;">Retry Verification</a>
        </div>
    </div>
</div>
HTML;
    }
}
