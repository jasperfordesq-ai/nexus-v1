<?php

namespace Nexus\Services;

use Nexus\Models\Newsletter;
use Nexus\Models\NewsletterSubscriber;
use Nexus\Models\NewsletterSegment;
use Nexus\Models\User;
use Nexus\Core\Mailer;
use Nexus\Core\TenantContext;
use Nexus\Core\Env;
use Nexus\Controllers\NewsletterTrackingController;

/**
 * ============================================================================
 * EMAIL CONFIGURATION
 * ============================================================================
 *
 * Email sending is now configured globally in Admin Settings > Email Provider.
 * The Mailer class automatically handles both SMTP and Gmail API based on
 * the USE_GMAIL_API setting in the .env file.
 *
 * To configure:
 * 1. Go to Admin > Settings > Global System Config (Super Admin)
 * 2. Select "Gmail API" or "SMTP" as the Email Sending Method
 * 3. Fill in the required credentials
 *
 * The same email configuration is used for all platform emails including
 * newsletters, notifications, and system emails.
 */

// Rate limiting - microseconds between each email (250000 = 250ms = max 4 emails/sec)
// This applies to newsletter bulk sending to avoid overwhelming the email provider
if (!defined('NEWSLETTER_EMAIL_DELAY_MICROSECONDS')) {
    define('NEWSLETTER_EMAIL_DELAY_MICROSECONDS', 250000);
}

class NewsletterService
{
    /**
     * Send a newsletter immediately to all eligible recipients
     */
    public static function sendNow($newsletterId, $targetAudience = 'all_members', $segmentId = null)
    {
        $newsletter = Newsletter::findById($newsletterId);
        if (!$newsletter) {
            throw new \Exception("Newsletter not found");
        }

        if ($newsletter['status'] === 'sent') {
            throw new \Exception("Newsletter already sent");
        }

        // Check for direct targeting from newsletter (counties, towns, groups)
        $hasDirectTargeting = !empty($newsletter['target_counties']) ||
                              !empty($newsletter['target_towns']) ||
                              !empty($newsletter['target_groups']);

        // Use segment_id from newsletter if not passed
        if (!$segmentId && !empty($newsletter['segment_id'])) {
            $segmentId = $newsletter['segment_id'];
        }

        // Get recipients based on target audience, segment, or direct targeting
        if ($segmentId) {
            $recipients = self::getSegmentRecipients($segmentId);
        } elseif ($hasDirectTargeting) {
            // Use direct targeting filters from the newsletter
            $recipients = self::getFilteredRecipients($newsletter, $targetAudience);
        } else {
            $recipients = self::getRecipients($targetAudience);
        }

        if (empty($recipients)) {
            throw new \Exception("No eligible recipients found");
        }

        // Queue the recipients
        Newsletter::clearQueue($newsletterId);

        // Check if A/B test is enabled
        $isABTest = !empty($newsletter['ab_test_enabled']) && !empty($newsletter['subject_b']);
        $splitPercentage = $newsletter['ab_split_percentage'] ?? 50;

        if ($isABTest) {
            $queueResult = Newsletter::queueRecipientsWithVariants($newsletterId, $recipients, $splitPercentage);
            $queued = $queueResult['total'];
        } else {
            $queued = Newsletter::queueRecipientsWithTokens($newsletterId, $recipients);
        }

        // Update newsletter status
        Newsletter::update($newsletterId, [
            'status' => 'sending',
            'total_recipients' => $queued,
            'target_audience' => $targetAudience,
            'segment_id' => $segmentId
        ]);

        // Process queue
        self::processQueue($newsletterId);

        return $queued;
    }

    /**
     * Get recipients based on target audience
     */
    public static function getRecipients($targetAudience = 'all_members')
    {
        $recipients = [];

        switch ($targetAudience) {
            case 'subscribers_only':
                // Only newsletter subscribers
                $subscribers = NewsletterSubscriber::getActive();
                foreach ($subscribers as $sub) {
                    $recipients[] = [
                        'email' => $sub['email'],
                        'user_id' => $sub['user_id'],
                        'unsubscribe_token' => $sub['unsubscribe_token'],
                        'name' => trim(($sub['first_name'] ?? '') . ' ' . ($sub['last_name'] ?? '')),
                        'first_name' => $sub['first_name'] ?? '',
                        'last_name' => $sub['last_name'] ?? ''
                    ];
                }
                break;

            case 'both':
                // Members + external subscribers (deduplicated)
                $seen = [];

                // Add subscribers first
                $subscribers = NewsletterSubscriber::getActive();
                foreach ($subscribers as $sub) {
                    $email = strtolower($sub['email']);
                    $seen[$email] = true;
                    $recipients[] = [
                        'email' => $sub['email'],
                        'user_id' => $sub['user_id'],
                        'unsubscribe_token' => $sub['unsubscribe_token'],
                        'name' => trim(($sub['first_name'] ?? '') . ' ' . ($sub['last_name'] ?? '')),
                        'first_name' => $sub['first_name'] ?? '',
                        'last_name' => $sub['last_name'] ?? ''
                    ];
                }

                // Add members not already in subscribers
                $users = User::getAll();
                foreach ($users as $user) {
                    if (empty($user['email']) || !($user['is_approved'] ?? 1)) continue;
                    $email = strtolower($user['email']);
                    if (isset($seen[$email])) continue;

                    $recipients[] = [
                        'email' => $user['email'],
                        'user_id' => $user['id'],
                        'unsubscribe_token' => null, // Will generate on queue
                        'name' => $user['name'] ?? trim($user['first_name'] . ' ' . $user['last_name']),
                        'first_name' => $user['first_name'] ?? '',
                        'last_name' => $user['last_name'] ?? ''
                    ];
                }
                break;

            case 'all_members':
            default:
                // All approved members (original behavior)
                $users = User::getAll();
                foreach ($users as $user) {
                    if (empty($user['email']) || !($user['is_approved'] ?? 1)) continue;

                    // Check if they have a subscriber record with unsubscribe token
                    $subscriber = NewsletterSubscriber::findByEmail($user['email']);

                    $recipients[] = [
                        'email' => $user['email'],
                        'user_id' => $user['id'],
                        'unsubscribe_token' => $subscriber['unsubscribe_token'] ?? null,
                        'name' => $user['name'] ?? trim($user['first_name'] . ' ' . $user['last_name']),
                        'first_name' => $user['first_name'] ?? '',
                        'last_name' => $user['last_name'] ?? ''
                    ];
                }
                break;
        }

        return $recipients;
    }

    /**
     * Get recipients filtered by newsletter's direct targeting options
     * (counties, towns, groups)
     */
    public static function getFilteredRecipients($newsletter, $targetAudience = 'all_members')
    {
        // Start with base audience
        $baseRecipients = self::getRecipients($targetAudience);

        if (empty($baseRecipients)) {
            return [];
        }

        // Parse targeting criteria from newsletter
        $targetCounties = !empty($newsletter['target_counties'])
            ? json_decode($newsletter['target_counties'], true) : [];
        $targetTowns = !empty($newsletter['target_towns'])
            ? json_decode($newsletter['target_towns'], true) : [];
        $targetGroups = !empty($newsletter['target_groups'])
            ? json_decode($newsletter['target_groups'], true) : [];

        // If no filters, return all base recipients
        if (empty($targetCounties) && empty($targetTowns) && empty($targetGroups)) {
            return $baseRecipients;
        }

        // Get user IDs that match the group filter
        $groupMemberIds = [];
        if (!empty($targetGroups)) {
            $groupMemberIds = self::getUserIdsByGroups($targetGroups);
        }

        // Filter recipients using OR logic:
        // Include user if they match ANY of the selected criteria
        $filteredRecipients = [];
        foreach ($baseRecipients as $recipient) {
            $userId = $recipient['user_id'] ?? null;

            // Skip if no user_id (external subscriber without account)
            if (!$userId) {
                // For external subscribers, we can't filter by location/group
                // Skip them when targeting is active
                continue;
            }

            // Get user details for location filtering
            $user = User::findById($userId);
            if (!$user) {
                continue;
            }

            // OR Logic: Include user if they match ANY filter
            $matchesAnyFilter = false;

            // Check group membership
            if (!empty($targetGroups) && in_array($userId, $groupMemberIds)) {
                $matchesAnyFilter = true;
            }

            // Check county
            if (!$matchesAnyFilter && !empty($targetCounties)) {
                $userCounty = $user['county'] ?? $user['state'] ?? '';
                if (self::matchesLocation($userCounty, $targetCounties)) {
                    $matchesAnyFilter = true;
                }
            }

            // Check town
            if (!$matchesAnyFilter && !empty($targetTowns)) {
                $userTown = $user['town'] ?? $user['city'] ?? '';
                if (self::matchesLocation($userTown, $targetTowns)) {
                    $matchesAnyFilter = true;
                }
            }

            // Skip user if they don't match any filter
            if (!$matchesAnyFilter) {
                continue;
            }

            // User matched at least one filter - include them
            $filteredRecipients[] = $recipient;
        }

        return $filteredRecipients;
    }

    /**
     * Check if a user's location matches any of the target locations
     */
    private static function matchesLocation($userLocation, $targetLocations)
    {
        if (empty($userLocation)) {
            return false;
        }

        $userLocation = strtolower(trim($userLocation));

        foreach ($targetLocations as $target) {
            if (strtolower(trim($target)) === $userLocation) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get user IDs that belong to any of the specified groups
     */
    private static function getUserIdsByGroups($groupIds)
    {
        if (empty($groupIds)) {
            return [];
        }

        $db = \Nexus\Core\Database::getInstance();
        $placeholders = implode(',', array_fill(0, count($groupIds), '?'));

        // Check if group_members table exists
        try {
            $stmt = $db->prepare("SELECT DISTINCT user_id FROM group_members WHERE group_id IN ($placeholders) AND status = 'active'");
            $stmt->execute(array_values($groupIds));
            return $stmt->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
            error_log("Error getting group members: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Schedule a newsletter for later
     */
    public static function schedule($newsletterId, $scheduledAt)
    {
        $newsletter = Newsletter::findById($newsletterId);
        if (!$newsletter) {
            throw new \Exception("Newsletter not found");
        }

        Newsletter::update($newsletterId, [
            'status' => 'scheduled',
            'scheduled_at' => $scheduledAt
        ]);

        return true;
    }

    /**
     * Process the send queue for a newsletter
     * Uses the unified Mailer class which handles both SMTP and Gmail API
     */
    public static function processQueue($newsletterId, $batchSize = 50)
    {
        $newsletter = Newsletter::findById($newsletterId);
        if (!$newsletter) {
            return false;
        }

        $tenant = TenantContext::get();
        $tenantName = $tenant['name'] ?? 'Project NEXUS';

        // Initialize the unified Mailer (handles both SMTP and Gmail API automatically)
        $mailer = new Mailer();

        $pending = Newsletter::getQueuePending($newsletterId, $batchSize);

        $sent = 0;
        $failed = 0;

        $basePath = TenantContext::getBasePath();

        // Check if this is an A/B test
        $isABTest = !empty($newsletter['ab_test_enabled']) && !empty($newsletter['subject_b']);

        foreach ($pending as $item) {
            try {
                // Build recipient data for personalization
                $recipientData = [
                    'email' => $item['email'],
                    'first_name' => $item['first_name'] ?? '',
                    'last_name' => $item['last_name'] ?? '',
                    'name' => $item['name'] ?? ''
                ];

                // Build personalized email HTML with unsubscribe link
                $unsubscribeToken = $item['unsubscribe_token'] ?? null;
                $emailHtml = self::renderEmail($newsletter, $tenantName, $unsubscribeToken, $recipientData);

                // Generate tracking token for this recipient
                $trackingToken = NewsletterTrackingController::generateTrackingToken($item['email']);

                // Update queue item with tracking token
                Newsletter::updateQueueTrackingToken($item['id'], $trackingToken);

                // Add open/click tracking to the email
                $emailHtml = NewsletterTrackingController::addTracking(
                    $emailHtml,
                    $newsletterId,
                    $trackingToken,
                    $basePath
                );

                // Determine subject line for A/B testing
                $subject = $newsletter['subject'];
                if ($isABTest && !empty($item['ab_variant'])) {
                    $subject = ($item['ab_variant'] === 'B') ? $newsletter['subject_b'] : $newsletter['subject'];
                }

                // Send email using the unified Mailer (SMTP or Gmail API based on config)
                $success = $mailer->send(
                    $item['email'],
                    $subject,
                    $emailHtml
                );

                if ($success) {
                    Newsletter::updateQueueItem($item['id'], 'sent');
                    $sent++;
                } else {
                    Newsletter::updateQueueItem($item['id'], 'failed', 'Email send failed');
                    $failed++;
                }

                // Rate limiting to avoid overwhelming the email provider
                usleep(NEWSLETTER_EMAIL_DELAY_MICROSECONDS);

            } catch (\Exception $e) {
                Newsletter::updateQueueItem($item['id'], 'failed', $e->getMessage());
                $failed++;
                error_log("Newsletter send error for {$item['email']}: " . $e->getMessage());
            }
        }

        // Update newsletter stats
        $stats = Newsletter::getQueueStats($newsletterId);
        Newsletter::update($newsletterId, [
            'total_sent' => $stats['sent'] ?? 0,
            'total_failed' => $stats['failed'] ?? 0
        ]);

        // If queue is complete, mark newsletter as sent
        if (($stats['pending'] ?? 0) == 0) {
            Newsletter::update($newsletterId, [
                'status' => 'sent',
                'sent_at' => date('Y-m-d H:i:s')
            ]);

            // Initialize A/B stats if this was an A/B test
            if ($isABTest) {
                self::initializeABStats($newsletterId);
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Process all scheduled newsletters that are ready
     */
    public static function processScheduled()
    {
        $newsletters = Newsletter::getScheduledReady();
        $processed = 0;

        foreach ($newsletters as $newsletter) {
            try {
                $targetAudience = $newsletter['target_audience'] ?? 'all_members';
                $recipients = self::getRecipients($targetAudience);

                if (empty($recipients)) {
                    Newsletter::update($newsletter['id'], [
                        'status' => 'failed',
                        'total_failed' => 0
                    ]);
                    continue;
                }

                // Queue and send
                Newsletter::clearQueue($newsletter['id']);
                $queued = Newsletter::queueRecipientsWithTokens($newsletter['id'], $recipients);

                Newsletter::update($newsletter['id'], [
                    'status' => 'sending',
                    'total_recipients' => $queued
                ]);

                self::processQueue($newsletter['id']);
                $processed++;
            } catch (\Exception $e) {
                error_log("Error processing scheduled newsletter {$newsletter['id']}: " . $e->getMessage());
                Newsletter::update($newsletter['id'], ['status' => 'failed']);
            }
        }

        return $processed;
    }

    /**
     * Process all recurring newsletters that are due
     */
    public static function processRecurring()
    {
        $newsletters = Newsletter::getRecurringReady();
        $processed = 0;

        foreach ($newsletters as $newsletter) {
            try {
                // Set the tenant context for this newsletter
                TenantContext::setById($newsletter['tenant_id']);

                $targetAudience = $newsletter['target_audience'] ?? 'all_members';
                $segmentId = $newsletter['segment_id'] ?? null;

                // Get recipients
                if ($segmentId) {
                    $recipients = self::getSegmentRecipients($segmentId);
                } else {
                    $recipients = self::getRecipients($targetAudience);
                }

                if (empty($recipients)) {
                    error_log("Recurring newsletter {$newsletter['id']}: No recipients found, skipping");
                    Newsletter::markRecurringSent($newsletter['id']);
                    continue;
                }

                // Queue and send
                Newsletter::clearQueue($newsletter['id']);
                $queued = Newsletter::queueRecipientsWithTokens($newsletter['id'], $recipients);

                Newsletter::update($newsletter['id'], [
                    'status' => 'sending',
                    'total_recipients' => $queued
                ]);

                self::processQueue($newsletter['id']);

                // Mark as sent (updates last_recurring_sent)
                Newsletter::markRecurringSent($newsletter['id']);

                // Reset status back to 'sent' (not 'sending') for recurring
                Newsletter::update($newsletter['id'], [
                    'status' => 'sent',
                    'sent_at' => date('Y-m-d H:i:s')
                ]);

                $processed++;
                error_log("Recurring newsletter {$newsletter['id']} sent successfully to $queued recipients");

            } catch (\Exception $e) {
                error_log("Error processing recurring newsletter {$newsletter['id']}: " . $e->getMessage());
            }
        }

        return $processed;
    }

    /**
     * Get eligible recipients count (for display purposes)
     */
    public static function getEligibleRecipients($targetAudience = 'all_members')
    {
        return self::getRecipients($targetAudience);
    }

    /**
     * Render newsletter content into full HTML email with unsubscribe link
     * Enhanced with professional email template design
     */
    public static function renderEmail($newsletter, $tenantName, $unsubscribeToken = null, $recipient = null)
    {
        $content = $newsletter['content'];
        $previewText = $newsletter['preview_text'] ?? '';
        $year = date('Y');

        // Brand Color - can be customized per tenant
        $color = '#6366f1';
        $colorDark = '#4f46e5';

        // App URL for links
        $appUrl = Env::get('APP_URL') ?? '';
        $basePath = TenantContext::getBasePath();

        // Process dynamic content blocks first
        $content = EmailTemplateBuilder::processDynamicBlocks($content);

        // Personalize content if recipient data available
        if ($recipient) {
            $content = EmailTemplateBuilder::personalizeContent($content, $recipient);
        }

        // Build unsubscribe URL
        if ($unsubscribeToken) {
            $unsubscribeUrl = rtrim($appUrl, '/') . $basePath . '/newsletter/unsubscribe?token=' . $unsubscribeToken;
            $unsubscribeLinks = '<a href="' . $unsubscribeUrl . '" style="color: #6b7280; text-decoration: underline;">Unsubscribe</a> <span style="color: #d1d5db; margin: 0 8px;">|</span> <a href="' . rtrim($appUrl, '/') . $basePath . '/settings" style="color: #6b7280; text-decoration: underline;">Manage Preferences</a>';
        } else {
            $unsubscribeUrl = rtrim($appUrl, '/') . $basePath . '/settings';
            $unsubscribeLinks = '<a href="' . $unsubscribeUrl . '" style="color: #6b7280; text-decoration: underline;">Manage Email Preferences</a>';
        }

        // Build the professional email HTML
        return <<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="format-detection" content="telephone=no,address=no,email=no,date=no,url=no">
    <title>{$newsletter['subject']}</title>
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
        /* Reset styles for email clients */
        body, table, td, p, a, li, blockquote { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; background-color: #f3f4f6; }

        /* Typography */
        body, table, td, a { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }

        /* Link styles */
        a { color: {$color}; }
        a:hover { color: {$colorDark}; }

        /* Content styles */
        .content h1 { color: #1f2937; font-size: 28px; font-weight: 800; margin: 0 0 20px; line-height: 1.3; }
        .content h2 { color: #1f2937; font-size: 22px; font-weight: 700; margin: 25px 0 15px; line-height: 1.3; }
        .content h3 { color: #1f2937; font-size: 18px; font-weight: 700; margin: 20px 0 12px; line-height: 1.3; }
        .content p { color: #374151; font-size: 16px; line-height: 1.8; margin: 0 0 16px; }
        .content ul, .content ol { color: #374151; font-size: 16px; line-height: 1.8; margin: 0 0 16px; padding-left: 24px; }
        .content li { margin-bottom: 8px; }
        .content img { max-width: 100%; height: auto; border-radius: 12px; margin: 20px 0; display: block; }
        .content blockquote { margin: 20px 0; padding: 20px 25px; background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%); border-left: 4px solid {$color}; border-radius: 0 12px 12px 0; font-style: italic; color: #4b5563; }

        /* Button styles */
        .btn-primary { display: inline-block; background: linear-gradient(135deg, {$color} 0%, {$colorDark} 100%); color: #ffffff !important; font-weight: 600; font-size: 16px; padding: 14px 28px; border-radius: 10px; text-decoration: none; }
        .btn-primary:hover { background: {$colorDark}; }

        /* Responsive */
        @media screen and (max-width: 600px) {
            .email-container { width: 100% !important; max-width: 100% !important; }
            .mobile-padding { padding-left: 20px !important; padding-right: 20px !important; }
            .content { padding: 30px 20px !important; }
            .header { padding: 25px 20px !important; }
            .footer { padding: 25px 20px !important; }
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .email-bg { background-color: #1f2937 !important; }
            .email-container-inner { background-color: #374151 !important; }
            .content h1, .content h2, .content h3 { color: #f3f4f6 !important; }
            .content p, .content li { color: #d1d5db !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f4f6;">

    <!-- Preview text (hidden, shows in email client preview) -->
    <div style="display: none; max-height: 0; overflow: hidden; mso-hide: all;">
        {$previewText}
        &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847;
    </div>

    <!-- Email wrapper -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f3f4f6;" class="email-bg">
        <tr>
            <td style="padding: 40px 10px;">

                <!-- Email container -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" align="center" class="email-container" style="margin: auto;">

                    <!-- Header with brand gradient -->
                    <tr>
                        <td class="header" style="padding: 35px 40px; text-align: center; background: linear-gradient(135deg, {$color} 0%, {$colorDark} 100%); border-radius: 16px 16px 0 0;">
                            <h1 style="margin: 0; font-size: 26px; font-weight: 700; color: #ffffff; letter-spacing: -0.5px;">{$tenantName}</h1>
                        </td>
                    </tr>

                    <!-- Main content -->
                    <tr>
                        <td class="content email-container-inner" style="background-color: #ffffff; padding: 40px;">
                            {$content}
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td class="footer" style="background-color: #f9fafb; padding: 30px 40px; border-radius: 0 0 16px 16px; border-top: 1px solid #e5e7eb;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="text-align: center; padding-bottom: 15px;">
                                        <p style="margin: 0; font-size: 14px; color: #6b7280;">
                                            &copy; {$year} {$tenantName}. All rights reserved.
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: center; padding-bottom: 15px;">
                                        <p style="margin: 0; font-size: 13px; color: #9ca3af; line-height: 1.6;">
                                            You received this email because you're subscribed to the {$tenantName} newsletter.
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: center;">
                                        {$unsubscribeLinks}
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
     * Get send statistics summary
     */
    public static function getStats($newsletterId)
    {
        $newsletter = Newsletter::findById($newsletterId);
        if (!$newsletter) {
            return null;
        }

        $queueStats = Newsletter::getQueueStats($newsletterId);

        return [
            'status' => $newsletter['status'],
            'total_recipients' => $newsletter['total_recipients'],
            'total_sent' => $newsletter['total_sent'],
            'total_failed' => $newsletter['total_failed'],
            'sent_at' => $newsletter['sent_at'],
            'target_audience' => $newsletter['target_audience'] ?? 'all_members',
            'queue_pending' => $queueStats['pending'] ?? 0,
            'queue_sent' => $queueStats['sent'] ?? 0,
            'queue_failed' => $queueStats['failed'] ?? 0
        ];
    }

    /**
     * Get recipient count by audience type
     */
    public static function getRecipientCount($targetAudience = 'all_members')
    {
        return count(self::getRecipients($targetAudience));
    }

    /**
     * Get recipients from a segment
     */
    public static function getSegmentRecipients($segmentId)
    {
        $users = NewsletterSegment::getMatchingUsers($segmentId);
        $recipients = [];

        foreach ($users as $user) {
            if (empty($user['email'])) continue;

            // Check if they have a subscriber record with unsubscribe token
            $subscriber = NewsletterSubscriber::findByEmail($user['email']);

            $recipients[] = [
                'email' => $user['email'],
                'user_id' => $user['id'],
                'unsubscribe_token' => $subscriber['unsubscribe_token'] ?? null,
                'name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
                'first_name' => $user['first_name'] ?? '',
                'last_name' => $user['last_name'] ?? ''
            ];
        }

        return $recipients;
    }

    /**
     * Get segment recipient count
     */
    public static function getSegmentRecipientCount($segmentId)
    {
        return NewsletterSegment::countMatchingUsers($segmentId);
    }

    /**
     * Initialize A/B test statistics after send completion
     */
    public static function initializeABStats($newsletterId)
    {
        $variantStats = Newsletter::getQueueStatsByVariant($newsletterId);

        foreach ($variantStats as $stat) {
            $variant = $stat['ab_variant'] ?? null;
            if (!$variant) continue;

            Newsletter::updateABStats($newsletterId, $variant, [
                'total_sent' => $stat['sent'] ?? 0,
                'total_opens' => 0,
                'unique_opens' => 0,
                'total_clicks' => 0,
                'unique_clicks' => 0
            ]);
        }
    }

    /**
     * Get A/B test results with calculated metrics
     */
    public static function getABTestResults($newsletterId)
    {
        $newsletter = Newsletter::findById($newsletterId);
        if (!$newsletter || empty($newsletter['ab_test_enabled'])) {
            return null;
        }

        $stats = Newsletter::getABStats($newsletterId);

        $results = [
            'newsletter_id' => $newsletterId,
            'subject_a' => $newsletter['subject'],
            'subject_b' => $newsletter['subject_b'],
            'split_percentage' => $newsletter['ab_split_percentage'],
            'winner' => $newsletter['ab_winner'],
            'winner_metric' => $newsletter['ab_winner_metric'],
            'variants' => []
        ];

        foreach ($stats as $stat) {
            $variant = $stat['variant'];
            $sent = max($stat['total_sent'], 1); // Avoid division by zero

            $results['variants'][$variant] = [
                'total_sent' => $stat['total_sent'],
                'total_opens' => $stat['total_opens'],
                'unique_opens' => $stat['unique_opens'],
                'total_clicks' => $stat['total_clicks'],
                'unique_clicks' => $stat['unique_clicks'],
                'open_rate' => round(($stat['unique_opens'] / $sent) * 100, 2),
                'click_rate' => round(($stat['unique_clicks'] / $sent) * 100, 2),
                'click_to_open' => $stat['unique_opens'] > 0
                    ? round(($stat['unique_clicks'] / $stat['unique_opens']) * 100, 2)
                    : 0
            ];
        }

        // Determine suggested winner based on metric
        if (count($results['variants']) === 2 && empty($newsletter['ab_winner'])) {
            $metric = $newsletter['ab_winner_metric'] ?? 'opens';
            $metricKey = ($metric === 'clicks') ? 'click_rate' : 'open_rate';

            $variantA = $results['variants']['A'] ?? null;
            $variantB = $results['variants']['B'] ?? null;

            if ($variantA && $variantB) {
                if ($variantA[$metricKey] > $variantB[$metricKey]) {
                    $results['suggested_winner'] = 'A';
                    $results['winning_margin'] = $variantA[$metricKey] - $variantB[$metricKey];
                } elseif ($variantB[$metricKey] > $variantA[$metricKey]) {
                    $results['suggested_winner'] = 'B';
                    $results['winning_margin'] = $variantB[$metricKey] - $variantA[$metricKey];
                } else {
                    $results['suggested_winner'] = 'tie';
                    $results['winning_margin'] = 0;
                }
            }
        }

        return $results;
    }

    /**
     * Select A/B test winner
     */
    public static function selectABWinner($newsletterId, $winner)
    {
        if (!in_array($winner, ['A', 'B'])) {
            throw new \Exception("Invalid winner. Must be 'A' or 'B'");
        }

        Newsletter::setABWinner($newsletterId, $winner);
        return true;
    }

    /**
     * Resend newsletter to non-openers
     * Creates a new newsletter based on the original and sends only to those who didn't open
     */
    public static function resendToNonOpeners($newsletterId, $newSubject = null, $waitDays = 3)
    {
        $newsletter = Newsletter::findById($newsletterId);
        if (!$newsletter) {
            throw new \Exception("Newsletter not found");
        }

        if ($newsletter['status'] !== 'sent') {
            throw new \Exception("Can only resend newsletters that have been sent");
        }

        // Check if enough time has passed
        $sentAt = new \DateTime($newsletter['sent_at']);
        $now = new \DateTime();
        $daysSinceSent = $sentAt->diff($now)->days;

        if ($daysSinceSent < $waitDays) {
            throw new \Exception("Please wait at least $waitDays days before resending. Only $daysSinceSent day(s) have passed.");
        }

        // Get non-openers
        $nonOpeners = \Nexus\Models\NewsletterAnalytics::getNonOpeners($newsletterId);

        if (empty($nonOpeners)) {
            throw new \Exception("No non-openers found for this newsletter");
        }

        // Create a new newsletter as a copy
        $newNewsletterData = [
            'subject' => $newSubject ?: "Reminder: " . $newsletter['subject'],
            'preview_text' => $newsletter['preview_text'],
            'content' => $newsletter['content'],
            'status' => 'draft',
            'created_by' => $_SESSION['user_id'] ?? $newsletter['created_by'],
            'template_id' => $newsletter['template_id'] ?? null
        ];

        $newId = Newsletter::create($newNewsletterData);

        // Queue only the non-openers
        $recipients = [];
        foreach ($nonOpeners as $nonOpener) {
            $recipients[] = [
                'id' => $nonOpener['user_id'],
                'email' => $nonOpener['email'],
                'first_name' => $nonOpener['first_name'] ?? '',
                'last_name' => $nonOpener['last_name'] ?? ''
            ];
        }

        Newsletter::clearQueue($newId);
        $queued = Newsletter::queueRecipientsWithTokens($newId, $recipients);

        // Update newsletter with recipient count
        Newsletter::update($newId, [
            'status' => 'sending',
            'total_recipients' => $queued,
            'target_audience' => 'resend_non_openers'
        ]);

        // Process the queue
        self::processQueue($newId);

        // Mark as sent
        Newsletter::update($newId, [
            'status' => 'sent',
            'sent_at' => date('Y-m-d H:i:s')
        ]);

        return [
            'newsletter_id' => $newId,
            'recipients' => $queued,
            'original_id' => $newsletterId
        ];
    }

    /**
     * Get resend eligibility info for a newsletter
     */
    public static function getResendInfo($newsletterId, $waitDays = 3)
    {
        $newsletter = Newsletter::findById($newsletterId);
        if (!$newsletter || $newsletter['status'] !== 'sent') {
            return null;
        }

        $nonOpenerCount = \Nexus\Models\NewsletterAnalytics::countNonOpeners($newsletterId);
        $totalSent = $newsletter['total_sent'] ?? 0;
        $sentAt = new \DateTime($newsletter['sent_at']);
        $now = new \DateTime();
        $daysSinceSent = $sentAt->diff($now)->days;

        return [
            'can_resend' => $daysSinceSent >= $waitDays && $nonOpenerCount > 0,
            'days_since_sent' => $daysSinceSent,
            'wait_days' => $waitDays,
            'days_remaining' => max(0, $waitDays - $daysSinceSent),
            'non_opener_count' => $nonOpenerCount,
            'total_sent' => $totalSent,
            'non_opener_percent' => $totalSent > 0 ? round(($nonOpenerCount / $totalSent) * 100, 1) : 0
        ];
    }

    /**
     * Process template variables for preview
     * Replaces placeholders with sample/actual data
     */
    public static function processTemplateVariables($content, $sampleData = [])
    {
        $tenant = TenantContext::get();
        $basePath = TenantContext::getBasePath();
        $appUrl = Env::get('APP_URL') ?? '';

        // Default sample data for preview
        $defaults = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'name' => 'John Doe',
            'tenant_name' => $tenant['name'] ?? 'Your Organization',
            'unsubscribe_link' => rtrim($appUrl, '/') . $basePath . '/newsletter/unsubscribe?token=SAMPLE_TOKEN',
            'view_in_browser' => rtrim($appUrl, '/') . $basePath . '/newsletter/view/preview',
            'current_date' => date('F j, Y'),
            'current_year' => date('Y'),
        ];

        // Merge with provided sample data
        $data = array_merge($defaults, $sampleData);

        // Replace all {{variable}} placeholders
        $processed = preg_replace_callback('/\{\{(\w+)\}\}/', function($matches) use ($data) {
            $key = $matches[1];
            return $data[$key] ?? $matches[0]; // Return original if not found
        }, $content);

        return $processed;
    }

    /**
     * Test Gmail API connection
     * Call this from admin panel to verify setup
     */
    public static function testGmailApiConnection()
    {
        $useGmailApi = strtolower(getenv('USE_GMAIL_API') ?: ($_ENV['USE_GMAIL_API'] ?? 'false')) === 'true';

        if (!$useGmailApi) {
            return ['success' => false, 'message' => 'Gmail API is not enabled. Set USE_GMAIL_API to true in .env file.'];
        }

        return GmailApiHelper::testConnection();
    }

    /**
     * Get current email sending method
     */
    public static function getSendingMethod()
    {
        $useGmailApi = strtolower(getenv('USE_GMAIL_API') ?: ($_ENV['USE_GMAIL_API'] ?? 'false')) === 'true';
        return $useGmailApi ? 'Gmail API' : 'SMTP';
    }
}
