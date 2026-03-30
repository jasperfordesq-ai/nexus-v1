<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\Newsletter;
use App\Models\NewsletterSegment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Define rate limit constant globally for cron/test access
if (!defined('NEWSLETTER_EMAIL_DELAY_MICROSECONDS')) {
    define('NEWSLETTER_EMAIL_DELAY_MICROSECONDS', 250000);
}

/**
 * NewsletterService — Laravel DI-based service for newsletter operations.
 *
 * All queries are tenant-scoped automatically via the HasTenantScope trait.
 *
 * sendNow, renderEmail, getSegmentRecipientCount, and getRecipientCount are
 * now fully converted to use DB::table() and the App\Core\Mailer.
 */
class NewsletterService
{
    /** Rate limiting: microseconds between each email (250ms = max 4 emails/sec) */
    private const EMAIL_DELAY_MICROSECONDS = NEWSLETTER_EMAIL_DELAY_MICROSECONDS;

    public function __construct(
        private readonly Newsletter $newsletter,
    ) {}

    /**
     * Get newsletters with cursor pagination.
     *
     * @return array{items: array, cursor: string|null, has_more: bool}
     */
    public function getAll(array $filters = []): array
    {
        $limit = min((int) ($filters['limit'] ?? 20), 100);
        $cursor = $filters['cursor'] ?? null;

        $query = $this->newsletter->newQuery()
            ->with(['creator:id,first_name,last_name']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if ($cursor !== null && ($cid = base64_decode($cursor, true)) !== false) {
            $query->where('id', '<', (int) $cid);
        }

        $query->orderByDesc('id');
        $items = $query->limit($limit + 1)->get();
        $hasMore = $items->count() > $limit;
        if ($hasMore) {
            $items->pop();
        }

        return [
            'items' => $items->toArray(),
            'cursor' => $hasMore && $items->isNotEmpty() ? base64_encode((string) $items->last()->id) : null,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get a single newsletter by ID.
     */
    public function getById(int $id): ?Newsletter
    {
        return $this->newsletter->newQuery()
            ->with(['creator'])
            ->find($id);
    }

    /**
     * Create a new newsletter draft.
     */
    public function create(int $createdBy, array $data): Newsletter
    {
        $newsletter = $this->newsletter->newInstance([
            'created_by' => $createdBy,
            'subject' => trim($data['subject']),
            'preview_text' => trim($data['preview_text'] ?? ''),
            'content' => $data['content'] ?? '',
            'status' => 'draft',
            'target_audience' => $data['target_audience'] ?? 'all_members',
            'segment_id' => $data['segment_id'] ?? null,
        ]);

        $newsletter->save();

        return $newsletter->fresh(['creator']);
    }

    /**
     * Mark a newsletter as queued for sending.
     */
    public function send(int $id): Newsletter
    {
        $newsletter = $this->newsletter->newQuery()->findOrFail($id);

        if ($newsletter->status === 'sent') {
            throw new \RuntimeException('Newsletter has already been sent.');
        }

        $newsletter->status = 'sending';
        $newsletter->save();

        return $newsletter;
    }

    // =========================================================================
    // SENDING
    // =========================================================================

    /**
     * Send a newsletter immediately to all eligible recipients.
     *
     * Resolves recipients from target audience or segment, queues them, and
     * processes the queue via App\Core\Mailer.
     *
     * @param int $newsletterId Newsletter ID
     * @param string $targetAudience 'all_members', 'subscribers_only', or 'both'
     * @param int|null $segmentId Optional segment for filtered targeting
     * @return int Number of recipients queued
     * @throws \Exception
     */
    public static function sendNow(int $newsletterId, string $targetAudience = 'all_members', ?int $segmentId = null): int
    {
        $newsletter = Newsletter::find($newsletterId);

        if (!$newsletter) {
            throw new \Exception('Newsletter not found');
        }

        if ($newsletter->status === 'sent') {
            throw new \Exception('Newsletter already sent');
        }

        // Use segment_id from newsletter if not passed
        if (!$segmentId && !empty($newsletter->segment_id)) {
            $segmentId = (int) $newsletter->segment_id;
        }

        // Resolve recipients
        if ($segmentId) {
            $recipients = self::getSegmentRecipients($segmentId);
        } else {
            $recipients = self::getRecipientsList($targetAudience);
        }

        if (empty($recipients)) {
            throw new \Exception('No eligible recipients found');
        }

        // Clear old queue and queue new recipients
        DB::table('newsletter_queue')->where('newsletter_id', $newsletterId)->delete();

        $queued = self::queueRecipientsWithTokens($newsletterId, $recipients);

        // Update newsletter status
        $newsletter->update([
            'status' => 'sending',
            'total_recipients' => $queued,
            'target_audience' => $targetAudience,
            'segment_id' => $segmentId,
        ]);

        // Process queue
        self::processQueue($newsletterId);

        return $queued;
    }

    /**
     * Process the send queue for a newsletter.
     *
     * Sends emails in batches using App\Core\Mailer with rate limiting.
     */
    public static function processQueue(int $newsletterId, int $batchSize = 50): array
    {
        $newsletter = Newsletter::find($newsletterId);
        if (!$newsletter) {
            return ['sent' => 0, 'failed' => 0];
        }

        $tenantId = TenantContext::getId();
        $tenantName = DB::table('tenants')
            ->where('id', $tenantId)
            ->value('name') ?? 'Community';

        $mailer = new \App\Core\Mailer($tenantId);

        $sent = 0;
        $failed = 0;

        // Process all pending items in batches
        do {
            $pending = DB::table('newsletter_queue')
                ->where('newsletter_id', $newsletterId)
                ->where('status', 'pending')
                ->limit($batchSize)
                ->get();

            if ($pending->isEmpty()) {
                break;
            }

            foreach ($pending as $item) {
                try {
                    $recipientData = [
                        'email' => $item->email,
                        'first_name' => $item->first_name ?? '',
                        'last_name' => $item->last_name ?? '',
                        'name' => $item->name ?? '',
                    ];

                    $unsubscribeToken = $item->unsubscribe_token ?? null;
                    $trackingToken = $item->tracking_token ?? null;
                    $emailHtml = self::renderEmail(
                        (array) $newsletter->getAttributes(),
                        $tenantName,
                        $unsubscribeToken,
                        $recipientData,
                        $trackingToken
                    );

                    $subject = $newsletter->subject;

                    $apiUrl = rtrim(config('app.url', ''), '/');
                    $unsubscribeUrl = $unsubscribeToken
                        ? $apiUrl . '/newsletter/unsubscribe?token=' . $unsubscribeToken
                        : null;

                    $success = $mailer->send($item->email, $subject, $emailHtml, null, null, $unsubscribeUrl);

                    if ($success) {
                        DB::table('newsletter_queue')
                            ->where('id', $item->id)
                            ->update(['status' => 'sent', 'sent_at' => now()]);
                        $sent++;
                    } else {
                        DB::table('newsletter_queue')
                            ->where('id', $item->id)
                            ->update(['status' => 'failed', 'error_message' => 'Email send failed']);
                        $failed++;
                    }

                    usleep(self::EMAIL_DELAY_MICROSECONDS);
                } catch (\Exception $e) {
                    DB::table('newsletter_queue')
                        ->where('id', $item->id)
                        ->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
                    $failed++;
                    Log::error("Newsletter send error for {$item->email}: " . $e->getMessage());
                }
            }
        } while (true);

        // Update newsletter stats
        $stats = DB::table('newsletter_queue')
            ->where('newsletter_id', $newsletterId)
            ->selectRaw("
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
            ")
            ->first();

        $newsletter->update([
            'total_sent' => (int) ($stats->sent ?? 0),
            'total_failed' => (int) ($stats->failed ?? 0),
        ]);

        // If queue is complete, mark newsletter as sent (or failed if nothing went out)
        if (((int) ($stats->pending ?? 0)) === 0) {
            $totalSent   = (int) ($stats->sent ?? 0);
            $totalFailed = (int) ($stats->failed ?? 0);
            $finalStatus = ($totalSent === 0 && $totalFailed > 0) ? 'failed' : 'sent';
            $newsletter->update([
                'status'  => $finalStatus,
                'sent_at' => now(),
            ]);
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Queue recipients with unsubscribe tokens.
     *
     * @return int Number of recipients queued
     */
    private static function queueRecipientsWithTokens(int $newsletterId, array $recipients): int
    {
        $queued = 0;

        foreach ($recipients as $recipient) {
            $token = $recipient['unsubscribe_token'] ?? bin2hex(random_bytes(32));

            DB::table('newsletter_queue')->insert([
                'newsletter_id' => $newsletterId,
                'email' => $recipient['email'],
                'user_id' => $recipient['user_id'] ?? null,
                'name' => $recipient['name'] ?? '',
                'first_name' => $recipient['first_name'] ?? '',
                'last_name' => $recipient['last_name'] ?? '',
                'unsubscribe_token' => $token,
                'tracking_token' => bin2hex(random_bytes(32)),
                'status' => 'pending',
                'created_at' => now(),
            ]);
            $queued++;
        }

        return $queued;
    }

    // =========================================================================
    // EMAIL RENDERING
    // =========================================================================

    /**
     * Render the email HTML for a newsletter.
     *
     * Produces a professional, responsive HTML email with unsubscribe links.
     * Used for both preview (sendTest) and actual sending.
     *
     * @param array $newsletter Newsletter data as associative array
     * @param string $tenantName Tenant/community name for branding
     * @param string|null $unsubscribeToken Token for one-click unsubscribe
     * @param array|null $recipient Recipient data for personalization
     * @param string|null $trackingToken Unique per-queue-entry token for open tracking pixel
     * @return string Full HTML email
     */
    public static function renderEmail(array $newsletter, string $tenantName, ?string $unsubscribeToken = null, ?array $recipient = null, ?string $trackingToken = null): string
    {
        $content = $newsletter['content'] ?? '';
        $subject = $newsletter['subject'] ?? '';
        $previewText = $newsletter['preview_text'] ?? '';
        $year = date('Y');

        // Brand colors
        $color = '#6366f1';
        $colorDark = '#4f46e5';

        // URLs
        $frontendUrl = rtrim(config('app.frontend_url', config('app.url', '')), '/');
        $apiUrl = rtrim(config('app.url', ''), '/');

        // Personalize content if recipient data available
        if ($recipient) {
            $content = self::personalizeContent($content, $recipient);
        }

        // Replace global tokens that are the same for every recipient in this send
        $unsubscribeLink = $unsubscribeToken
            ? '<a href="' . $apiUrl . '/newsletter/unsubscribe?token=' . $unsubscribeToken . '" style="color:#6366f1;">Unsubscribe</a>'
            : '';
        $content = str_replace(
            ['{{tenant_name}}', '{{unsubscribe_link}}'],
            [$tenantName, $unsubscribeLink],
            $content
        );

        // Build unsubscribe URL
        if ($unsubscribeToken) {
            $unsubscribeUrl = $apiUrl . '/newsletter/unsubscribe?token=' . $unsubscribeToken;
            $unsubscribeLinks = '<a href="' . $unsubscribeUrl . '" style="color: #6b7280; text-decoration: underline;">Unsubscribe</a>'
                . ' <span style="color: #d1d5db; margin: 0 8px;">|</span> '
                . '<a href="' . $frontendUrl . '/settings" style="color: #6b7280; text-decoration: underline;">Manage Preferences</a>';
        } else {
            $unsubscribeUrl = $frontendUrl . '/settings';
            $unsubscribeLinks = '<a href="' . $unsubscribeUrl . '" style="color: #6b7280; text-decoration: underline;">Manage Email Preferences</a>';
        }

        // Tracking pixel (1×1 transparent GIF) — uses unique tracking_token per queue entry
        $pixelHtml = '';
        $pixelToken = $trackingToken ?? $unsubscribeToken;
        if ($pixelToken) {
            $pixelUrl = $apiUrl . '/v2/newsletter/pixel/' . rawurlencode($pixelToken);
            $pixelHtml = '<img src="' . $pixelUrl . '" width="1" height="1" border="0" alt=""'
                . ' style="height:1px!important;width:1px!important;border-width:0!important;'
                . 'margin-top:0!important;margin-bottom:0!important;'
                . 'margin-right:0!important;margin-left:0!important;'
                . 'padding-top:0!important;padding-bottom:0!important;'
                . 'padding-right:0!important;padding-left:0!important;display:block;" />';
        }

        return <<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="format-detection" content="telephone=no,address=no,email=no,date=no,url=no">
    <title>{$subject}</title>
    <style type="text/css">
        body, table, td, p, a, li, blockquote { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; background-color: #f3f4f6; }
        body, table, td, a { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }
        a { color: {$color}; }
        a:hover { color: {$colorDark}; }
        .content h1 { color: #1f2937; font-size: 28px; font-weight: 800; margin: 0 0 20px; line-height: 1.3; }
        .content h2 { color: #1f2937; font-size: 22px; font-weight: 700; margin: 25px 0 15px; line-height: 1.3; }
        .content h3 { color: #1f2937; font-size: 18px; font-weight: 700; margin: 20px 0 12px; line-height: 1.3; }
        .content p { color: #374151; font-size: 16px; line-height: 1.8; margin: 0 0 16px; }
        .content ul, .content ol { color: #374151; font-size: 16px; line-height: 1.8; margin: 0 0 16px; padding-left: 24px; }
        .content li { margin-bottom: 8px; }
        .content img { max-width: 100%; height: auto; border-radius: 12px; margin: 20px 0; display: block; }
        .content blockquote { margin: 20px 0; padding: 20px 25px; background: linear-gradient(135deg, #f5f3ff 0%, #ede9fe 100%); border-left: 4px solid {$color}; border-radius: 0 12px 12px 0; font-style: italic; color: #4b5563; }
        @media screen and (max-width: 600px) {
            .email-container { width: 100% !important; max-width: 100% !important; }
            .content { padding: 30px 20px !important; }
            .header { padding: 25px 20px !important; }
            .footer { padding: 25px 20px !important; }
        }
        @media (prefers-color-scheme: dark) {
            .email-bg { background-color: #1f2937 !important; }
            .email-container-inner { background-color: #374151 !important; }
            .content h1, .content h2, .content h3 { color: #f3f4f6 !important; }
            .content p, .content li { color: #d1d5db !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f4f6;">
    <div style="display: none; max-height: 0; overflow: hidden; mso-hide: all;">
        {$previewText}
        &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847; &#847;
    </div>
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f3f4f6;" class="email-bg">
        <tr>
            <td style="padding: 40px 10px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" align="center" class="email-container" style="margin: auto;">
                    <tr>
                        <td class="header" style="padding: 35px 40px; text-align: center; background: linear-gradient(135deg, {$color} 0%, {$colorDark} 100%); border-radius: 16px 16px 0 0;">
                            <h1 style="margin: 0; font-size: 26px; font-weight: 700; color: #ffffff; letter-spacing: -0.5px;">{$tenantName}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td class="content email-container-inner" style="background-color: #ffffff; padding: 40px;">
                            {$content}
                        </td>
                    </tr>
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
    {$pixelHtml}
</body>
</html>
HTML;
    }

    /**
     * Personalize email content by replacing {{variable}} placeholders.
     */
    private static function personalizeContent(string $content, array $recipient): string
    {
        // Escape user-provided values to prevent stored XSS in newsletter emails
        $replacements = [
            '{{first_name}}' => htmlspecialchars($recipient['first_name'] ?? '', ENT_QUOTES, 'UTF-8'),
            '{{last_name}}' => htmlspecialchars($recipient['last_name'] ?? '', ENT_QUOTES, 'UTF-8'),
            '{{name}}' => htmlspecialchars($recipient['name'] ?? '', ENT_QUOTES, 'UTF-8'),
            '{{email}}' => htmlspecialchars($recipient['email'] ?? '', ENT_QUOTES, 'UTF-8'),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    // =========================================================================
    // RECIPIENT RESOLUTION
    // =========================================================================

    /**
     * Get the count of recipients matching a segment.
     *
     * Evaluates the segment's rules against the users table using
     * dynamic condition building.
     */
    public static function getSegmentRecipientCount(int $segmentId): int
    {
        $segment = NewsletterSegment::find($segmentId);

        if (!$segment || empty($segment->rules)) {
            return 0;
        }

        $rules = self::normalizeSegmentRules($segment->rules, $segment->match_type ?? 'all');

        return self::countUsersByRules($rules);
    }

    /**
     * Get the total recipient count for a target audience.
     *
     * @param string $targetAudience 'all_members', 'subscribers_only', or 'both'
     * @return int
     */
    public static function getRecipientCount(string $targetAudience = 'all_members'): int
    {
        return count(self::getRecipientsList($targetAudience));
    }

    /**
     * Get recipients list based on target audience.
     *
     * @return array Array of recipient arrays with email, user_id, name, etc.
     */
    private static function getRecipientsList(string $targetAudience = 'all_members'): array
    {
        $tenantId = TenantContext::getId();
        $recipients = [];

        switch ($targetAudience) {
            case 'subscribers_only':
                $subscribers = DB::table('newsletter_subscribers')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active')
                    ->get();

                foreach ($subscribers as $sub) {
                    $recipients[] = [
                        'email' => $sub->email,
                        'user_id' => $sub->user_id,
                        'unsubscribe_token' => $sub->unsubscribe_token,
                        'name' => trim(($sub->first_name ?? '') . ' ' . ($sub->last_name ?? '')),
                        'first_name' => $sub->first_name ?? '',
                        'last_name' => $sub->last_name ?? '',
                    ];
                }
                break;

            case 'both':
                $seen = [];

                // Add active subscribers first
                $subscribers = DB::table('newsletter_subscribers')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active')
                    ->get();

                // Also collect unsubscribed emails to exclude members who opted out
                $unsubscribedEmails = DB::table('newsletter_subscribers')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'unsubscribed')
                    ->pluck('email')
                    ->map(fn($e) => strtolower($e))
                    ->flip()
                    ->all();

                foreach ($subscribers as $sub) {
                    $email = strtolower($sub->email);
                    $seen[$email] = true;
                    $recipients[] = [
                        'email' => $sub->email,
                        'user_id' => $sub->user_id,
                        'unsubscribe_token' => $sub->unsubscribe_token,
                        'name' => trim(($sub->first_name ?? '') . ' ' . ($sub->last_name ?? '')),
                        'first_name' => $sub->first_name ?? '',
                        'last_name' => $sub->last_name ?? '',
                    ];
                }

                // Add members not already in subscribers and not unsubscribed
                $users = DB::table('users')
                    ->where('tenant_id', $tenantId)
                    ->where('is_approved', 1)
                    ->whereNotNull('email')
                    ->where('email', '!=', '')
                    ->get(['id', 'email', 'name', 'first_name', 'last_name']);

                foreach ($users as $user) {
                    $email = strtolower($user->email);
                    if (isset($seen[$email]) || isset($unsubscribedEmails[$email])) {
                        continue;
                    }

                    $recipients[] = [
                        'email' => $user->email,
                        'user_id' => $user->id,
                        'unsubscribe_token' => null,
                        'name' => $user->name ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                        'first_name' => $user->first_name ?? '',
                        'last_name' => $user->last_name ?? '',
                    ];
                }
                break;

            case 'all_members':
            default:
                // Collect emails that have explicitly unsubscribed so we can exclude them
                $unsubscribedEmails = DB::table('newsletter_subscribers')
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'unsubscribed')
                    ->pluck('email')
                    ->map(fn($e) => strtolower($e))
                    ->flip()
                    ->all();

                $users = DB::table('users')
                    ->where('tenant_id', $tenantId)
                    ->where('is_approved', 1)
                    ->whereNotNull('email')
                    ->where('email', '!=', '')
                    ->get(['id', 'email', 'name', 'first_name', 'last_name']);

                foreach ($users as $user) {
                    // Skip users who have unsubscribed from newsletters
                    if (isset($unsubscribedEmails[strtolower($user->email)])) {
                        continue;
                    }

                    // Look up subscriber record for unsubscribe token
                    $subscriber = DB::table('newsletter_subscribers')
                        ->where('tenant_id', $tenantId)
                        ->where('email', $user->email)
                        ->first(['unsubscribe_token']);

                    $recipients[] = [
                        'email' => $user->email,
                        'user_id' => $user->id,
                        'unsubscribe_token' => $subscriber->unsubscribe_token ?? null,
                        'name' => $user->name ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                        'first_name' => $user->first_name ?? '',
                        'last_name' => $user->last_name ?? '',
                    ];
                }
                break;
        }

        return $recipients;
    }

    /**
     * Get recipients from a newsletter segment by evaluating its rules.
     *
     * @return array Array of recipient arrays
     */
    public static function getSegmentRecipients(int $segmentId): array
    {
        $segment = NewsletterSegment::find($segmentId);

        if (!$segment || empty($segment->rules)) {
            return [];
        }

        $rules = self::normalizeSegmentRules($segment->rules, $segment->match_type ?? 'all');
        $users = self::queryUsersByRules($rules);

        $tenantId = TenantContext::getId();
        $recipients = [];

        // Collect unsubscribed emails to exclude from segment sends
        $unsubscribedEmails = DB::table('newsletter_subscribers')
            ->where('tenant_id', $tenantId)
            ->where('status', 'unsubscribed')
            ->pluck('email')
            ->map(fn($e) => strtolower($e))
            ->flip()
            ->all();

        foreach ($users as $user) {
            if (empty($user->email)) {
                continue;
            }

            // Skip users who have unsubscribed from newsletters
            if (isset($unsubscribedEmails[strtolower($user->email)])) {
                continue;
            }

            $subscriber = DB::table('newsletter_subscribers')
                ->where('tenant_id', $tenantId)
                ->where('email', $user->email)
                ->first(['unsubscribe_token']);

            $recipients[] = [
                'email' => $user->email,
                'user_id' => $user->id,
                'unsubscribe_token' => $subscriber->unsubscribe_token ?? null,
                'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                'first_name' => $user->first_name ?? '',
                'last_name' => $user->last_name ?? '',
            ];
        }

        return $recipients;
    }

    // =========================================================================
    // SEGMENT RULE EVALUATION
    // =========================================================================

    /**
     * Normalize segment rules to the {match, conditions} format expected by
     * queryUsersByRules() / countUsersByRules().
     *
     * React saves rules as a flat array [{field, operator, value}, ...] alongside
     * a separate match_type column. The service query methods expect the nested
     * format {match: 'all'|'any', conditions: [...]}.
     */
    private static function normalizeSegmentRules(mixed $rules, string $matchType = 'all'): array
    {
        if (!is_array($rules)) {
            return ['match' => $matchType, 'conditions' => []];
        }

        // Already in nested format: {match: ..., conditions: [...]}
        if (isset($rules['conditions'])) {
            // Ensure match key reflects the segment's match_type column
            $rules['match'] = $rules['match'] ?? $matchType;
            return $rules;
        }

        // Flat array format [{field, operator, value}, ...] — wrap it
        return [
            'match' => $matchType,
            'conditions' => array_values($rules),
        ];
    }

    /**
     * Query users matching segment rules.
     *
     * @return \Illuminate\Support\Collection
     */
    private static function queryUsersByRules(array $rules): \Illuminate\Support\Collection
    {
        $tenantId = TenantContext::getId();
        $conditions = [];
        $params = [$tenantId];
        $matchType = $rules['match'] ?? 'all';

        foreach ($rules['conditions'] ?? [] as $condition) {
            $clause = self::buildConditionClause($condition, $params);
            if ($clause) {
                $conditions[] = $clause;
            }
        }

        $baseWhere = "tenant_id = ? AND is_approved = 1";

        if (!empty($conditions)) {
            $operator = ($matchType === 'all') ? ' AND ' : ' OR ';
            $baseWhere .= ' AND (' . implode($operator, $conditions) . ')';
        }

        $sql = "SELECT id, email, first_name, last_name FROM users WHERE {$baseWhere} ORDER BY email";

        return collect(DB::select($sql, $params));
    }

    /**
     * Count users matching segment rules.
     */
    private static function countUsersByRules(array $rules): int
    {
        $tenantId = TenantContext::getId();
        $conditions = [];
        $params = [$tenantId];
        $matchType = $rules['match'] ?? 'all';

        foreach ($rules['conditions'] ?? [] as $condition) {
            $clause = self::buildConditionClause($condition, $params);
            if ($clause) {
                $conditions[] = $clause;
            }
        }

        $baseWhere = "tenant_id = ? AND is_approved = 1";

        if (!empty($conditions)) {
            $operator = ($matchType === 'all') ? ' AND ' : ' OR ';
            $baseWhere .= ' AND (' . implode($operator, $conditions) . ')';
        }

        $sql = "SELECT COUNT(*) as total FROM users WHERE {$baseWhere}";
        $result = DB::selectOne($sql, $params);

        return (int) ($result->total ?? 0);
    }

    /**
     * Build a single SQL condition clause from a segment rule condition.
     *
     *
     * @param array $condition ['field' => string, 'operator' => string, 'value' => mixed]
     * @param array &$params Bind parameters (appended to)
     * @return string|null SQL clause or null if unsupported
     */
    private static function buildConditionClause(array $condition, array &$params): ?string
    {
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? '';
        $value = $condition['value'] ?? '';

        switch ($field) {
            case 'role':
                return self::buildStringCondition('role', $operator, $value, $params);

            case 'profile_type':
                return self::buildStringCondition('profile_type', $operator, $value, $params);

            case 'location':
                return self::buildStringCondition('location', $operator, $value, $params);

            case 'created_at':
                return self::buildDateCondition('created_at', $operator, $value, $params);

            case 'has_listings':
                if ($value == '1' || $value === true || $value === 'yes') {
                    return "id IN (SELECT DISTINCT user_id FROM listings WHERE status = 'active')";
                }
                return "id NOT IN (SELECT DISTINCT user_id FROM listings WHERE status = 'active')";

            case 'listing_count':
                return self::buildNumericSubqueryCondition(
                    "(SELECT COUNT(*) FROM listings WHERE listings.user_id = users.id AND listings.status = 'active')",
                    $operator, $value, $params
                );

            case 'geo_radius':
                return self::buildGeoRadiusCondition($value, $params);

            case 'county':
                return self::buildLocationLikeCondition($operator, $value, $params);

            case 'town':
                return self::buildLocationLikeCondition($operator, $value, $params);

            case 'group_membership':
                return self::buildGroupMembershipCondition($operator, $value, $params);

            case 'activity_score':
                return self::buildNumericSubqueryCondition(
                    "(SELECT COALESCE(activity_score, 0) FROM user_metrics WHERE user_metrics.user_id = users.id LIMIT 1)",
                    $operator, $value, $params
                );

            case 'login_recency':
                return self::buildLoginRecencyCondition($operator, $value);

            case 'bio':
                return self::buildStringCondition('bio', $operator, $value, $params);

            case 'avatar':
                return self::buildStringCondition('avatar_url', $operator, $value, $params);

            default:
                return null;
        }
    }

    /**
     * Build a string comparison condition.
     */
    private static function buildStringCondition(string $field, string $operator, string $value, array &$params): ?string
    {
        switch ($operator) {
            case 'equals':
                $params[] = $value;
                return "{$field} = ?";

            case 'not_equals':
                $params[] = $value;
                return "{$field} != ?";

            case 'contains':
                $params[] = '%' . $value . '%';
                return "{$field} LIKE ?";

            case 'starts_with':
                $params[] = $value . '%';
                return "{$field} LIKE ?";

            case 'is_empty':
                return "({$field} IS NULL OR {$field} = '')";

            case 'is_not_empty':
                return "({$field} IS NOT NULL AND {$field} != '')";

            default:
                return null;
        }
    }

    /**
     * Build a date comparison condition.
     */
    private static function buildDateCondition(string $field, string $operator, mixed $value, array &$params): ?string
    {
        switch ($operator) {
            case 'older_than_days':
                $params[] = (int) $value;
                return "{$field} < DATE_SUB(NOW(), INTERVAL ? DAY)";

            case 'newer_than_days':
                $params[] = (int) $value;
                return "{$field} > DATE_SUB(NOW(), INTERVAL ? DAY)";

            case 'before':
                $params[] = $value;
                return "{$field} < ?";

            case 'after':
                $params[] = $value;
                return "{$field} > ?";

            case 'between':
                if (is_array($value) && count($value) >= 2) {
                    $params[] = $value[0];
                    $params[] = $value[1];
                    return "{$field} BETWEEN ? AND ?";
                }
                return null;

            default:
                return null;
        }
    }

    /**
     * Build a numeric comparison against a subquery expression.
     */
    private static function buildNumericSubqueryCondition(string $subquery, string $operator, mixed $value, array &$params): ?string
    {
        switch ($operator) {
            case 'equals':
                $params[] = (int) $value;
                return "{$subquery} = ?";
            case 'greater_than':
                $params[] = (int) $value;
                return "{$subquery} > ?";
            case 'less_than':
                $params[] = (int) $value;
                return "{$subquery} < ?";
            case 'at_least':
                $params[] = (int) $value;
                return "{$subquery} >= ?";
            case 'at_most':
                $params[] = (int) $value;
                return "{$subquery} <= ?";
            default:
                return null;
        }
    }

    /**
     * Build a geographic radius condition using Haversine formula.
     */
    private static function buildGeoRadiusCondition(mixed $value, array &$params): ?string
    {
        if (!is_array($value) || !isset($value['lat'], $value['lng'], $value['radius_km'])) {
            return null;
        }

        $params[] = (float) $value['lat'];
        $params[] = (float) $value['lng'];
        $params[] = (float) $value['lat'];
        $params[] = (float) $value['radius_km'];

        return "(
            latitude IS NOT NULL
            AND longitude IS NOT NULL
            AND (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) <= ?
        )";
    }

    /**
     * Build a LIKE condition for location-based fields (county, town).
     */
    private static function buildLocationLikeCondition(string $operator, mixed $value, array &$params): ?string
    {
        $locations = is_array($value) ? $value : array_map('trim', explode(',', (string) $value));
        $locations = array_filter($locations);

        if (empty($locations)) {
            return null;
        }

        $conditions = [];
        foreach ($locations as $loc) {
            $params[] = '%' . trim($loc) . '%';
            $conditions[] = "location LIKE ?";
        }

        if ($operator === 'not_in') {
            return "NOT (" . implode(' OR ', $conditions) . ")";
        }

        return "(" . implode(' OR ', $conditions) . ")";
    }

    /**
     * Build a group membership condition.
     */
    private static function buildGroupMembershipCondition(string $operator, mixed $value, array &$params): ?string
    {
        $groupIds = is_array($value) ? $value : [$value];
        $groupIds = array_filter(array_map('intval', $groupIds));

        if (empty($groupIds)) {
            return null;
        }

        $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
        foreach ($groupIds as $gid) {
            $params[] = $gid;
        }

        $subquery = "id IN (SELECT user_id FROM group_members WHERE group_id IN ({$placeholders}) AND status = 'active')";

        if ($operator === 'not_member_of') {
            return "NOT ({$subquery})";
        }

        return $subquery;
    }

    /**
     * Build a login recency condition.
     */
    private static function buildLoginRecencyCondition(string $operator, mixed $value): ?string
    {
        if ($value === 'never') {
            return "(last_login_at IS NULL)";
        }

        if ($operator === 'older_than_days') {
            return "(last_login_at IS NOT NULL AND last_login_at < DATE_SUB(NOW(), INTERVAL " . (int) $value . " DAY))";
        }

        if ($operator === 'newer_than_days') {
            return "(last_login_at IS NOT NULL AND last_login_at > DATE_SUB(NOW(), INTERVAL " . (int) $value . " DAY))";
        }

        return null;
    }

    // =========================================================================
    // CRON ENTRY POINTS
    // =========================================================================

    /**
     * Process scheduled newsletters that are ready to send.
     *
     * @return int Number of newsletters processed
     */
    public static function processScheduled(): int
    {
        $tenantId = TenantContext::getId();
        $processed = 0;

        try {
            $newsletters = DB::table('newsletters')
                ->where('tenant_id', $tenantId)
                ->where('status', 'scheduled')
                ->where('scheduled_at', '<=', now())
                ->get();

            foreach ($newsletters as $newsletter) {
                // Atomically claim by flipping status from 'scheduled' → 'sending'.
                // If another cron process already claimed this newsletter the UPDATE
                // will match 0 rows and we skip it, preventing duplicate sends.
                $claimed = DB::table('newsletters')
                    ->where('id', $newsletter->id)
                    ->where('status', 'scheduled')
                    ->update(['status' => 'sending', 'updated_at' => now()]);

                if (!$claimed) {
                    continue;
                }

                try {
                    $service = app(self::class);
                    $service->sendNow(
                        (int) $newsletter->id,
                        $newsletter->target_audience ?? 'all_members',
                        $newsletter->segment_id ? (int) $newsletter->segment_id : null
                    );
                    $processed++;
                } catch (\Exception $e) {
                    Log::error("Failed to process scheduled newsletter {$newsletter->id}: " . $e->getMessage());
                    // Revert status so it can be retried
                    DB::table('newsletters')
                        ->where('id', $newsletter->id)
                        ->where('status', 'sending')
                        ->update(['status' => 'scheduled', 'updated_at' => now()]);
                }
            }
        } catch (\Exception $e) {
            Log::error('processScheduled error: ' . $e->getMessage());
        }

        return $processed;
    }

    /**
     * Process recurring newsletters.
     *
     * @return int Number of newsletters processed
     */
    public static function processRecurring(): int
    {
        $tenantId = TenantContext::getId();
        $processed = 0;

        try {
            $newsletters = DB::table('newsletters')
                ->where('tenant_id', $tenantId)
                ->where('is_recurring', true)
                ->where('status', 'active')
                ->get();

            foreach ($newsletters as $newsletter) {
                // Check if enough time has passed since last send
                $lastSent = $newsletter->last_sent_at ?? null;
                $interval = $newsletter->recurring_interval ?? 'weekly';

                $shouldSend = !$lastSent;
                if ($lastSent) {
                    $daysSince = (int) ((time() - strtotime($lastSent)) / 86400);
                    $shouldSend = match ($interval) {
                        'daily' => $daysSince >= 1,
                        'weekly' => $daysSince >= 7,
                        'monthly' => $daysSince >= 30,
                        default => false,
                    };
                }

                if ($shouldSend) {
                    try {
                        $service = app(self::class);
                        $service->sendNow((int) $newsletter->id);
                        $processed++;
                    } catch (\Exception $e) {
                        Log::error("Failed to process recurring newsletter {$newsletter->id}: " . $e->getMessage());
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('processRecurring error: ' . $e->getMessage());
        }

        return $processed;
    }

    /**
     * Schedule a newsletter for future sending.
     *
     * @param int $newsletterId Newsletter ID
     * @param string $scheduledAt DateTime string for scheduled send
     * @return bool
     */
    public static function schedule(int $newsletterId, string $scheduledAt): bool
    {
        $tenantId = TenantContext::getId();

        try {
            $affected = DB::table('newsletters')
                ->where('id', $newsletterId)
                ->where('tenant_id', $tenantId)
                ->update([
                    'status' => 'scheduled',
                    'scheduled_at' => $scheduledAt,
                    'updated_at' => now(),
                ]);

            return $affected > 0;
        } catch (\Exception $e) {
            Log::error('schedule error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get recipients based on target audience.
     *
     * @param string $targetAudience 'all_members', 'subscribers_only', 'both'
     * @return array
     */
    public static function getRecipients(string $targetAudience = 'all_members'): array
    {
        return self::getRecipientsList($targetAudience);
    }

    /**
     * Get statistics for a newsletter.
     *
     * @param int $newsletterId Newsletter ID
     * @return array
     */
    public static function getStats(int $newsletterId): array
    {
        $tenantId = TenantContext::getId();

        try {
            $newsletter = DB::table('newsletters')
                ->where('id', $newsletterId)
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$newsletter) {
                return [];
            }

            $stats = DB::table('newsletter_queue')
                ->where('newsletter_id', $newsletterId)
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened,
                    SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked
                ")
                ->first();

            return [
                'total' => (int) ($stats->total ?? 0),
                'sent' => (int) ($stats->sent ?? 0),
                'failed' => (int) ($stats->failed ?? 0),
                'pending' => (int) ($stats->pending ?? 0),
                'opened' => (int) ($stats->opened ?? 0),
                'clicked' => (int) ($stats->clicked ?? 0),
                'open_rate' => ($stats->sent ?? 0) > 0 ? round(($stats->opened ?? 0) / $stats->sent * 100, 1) : 0,
                'click_rate' => ($stats->sent ?? 0) > 0 ? round(($stats->clicked ?? 0) / $stats->sent * 100, 1) : 0,
            ];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get filtered recipients based on criteria.
     *
     * @param array $filters Filter criteria (location, group, etc.)
     * @return array
     */
    public static function getFilteredRecipients(array $filters = []): array
    {
        $tenantId = TenantContext::getId();

        try {
            $query = DB::table('users')
                ->where('tenant_id', $tenantId)
                ->where('is_approved', 1)
                ->whereNotNull('email')
                ->where('email', '!=', '');

            if (!empty($filters['location'])) {
                $query->where('location', 'LIKE', '%' . $filters['location'] . '%');
            }

            if (!empty($filters['group_id'])) {
                $query->whereIn('id', function ($q) use ($filters) {
                    $q->select('user_id')
                        ->from('group_members')
                        ->where('group_id', $filters['group_id'])
                        ->where('status', 'active');
                });
            }

            return $query->get(['id', 'email', 'name', 'first_name', 'last_name'])
                ->map(fn ($u) => (array) $u)
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    // =========================================================================
    // A/B TESTING
    // =========================================================================

    /**
     * Initialize A/B test statistics for a newsletter.
     *
     * @param int $newsletterId Newsletter ID
     * @return bool
     */
    public static function initializeABStats(int $newsletterId): bool
    {
        try {
            DB::table('newsletter_ab_stats')->updateOrInsert(
                ['newsletter_id' => $newsletterId],
                [
                    'variant_a_sent' => 0,
                    'variant_b_sent' => 0,
                    'variant_a_opened' => 0,
                    'variant_b_opened' => 0,
                    'variant_a_clicked' => 0,
                    'variant_b_clicked' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            return true;
        } catch (\Exception $e) {
            Log::error('initializeABStats error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get A/B test results for a newsletter.
     *
     * @param int $newsletterId Newsletter ID
     * @return array|null
     */
    public static function getABTestResults(int $newsletterId): ?array
    {
        try {
            $stats = DB::table('newsletter_ab_stats')
                ->where('newsletter_id', $newsletterId)
                ->first();

            if (!$stats) {
                return null;
            }

            return (array) $stats;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Select the winning variant from an A/B test.
     *
     * @param int $newsletterId Newsletter ID
     * @param string $winner 'a' or 'b'
     * @return bool
     */
    public static function selectABWinner(int $newsletterId, string $winner = 'a'): bool
    {
        try {
            DB::table('newsletters')
                ->where('id', $newsletterId)
                ->update([
                    'ab_winner' => $winner,
                    'updated_at' => now(),
                ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    // =========================================================================
    // RESEND TO NON-OPENERS
    // =========================================================================

    /**
     * Resend a newsletter to recipients who haven't opened it.
     *
     * @param int $newsletterId Newsletter ID
     * @param string|null $newSubject Optional new subject line
     * @param int $waitDays Minimum days to wait before resending
     * @return int Number of recipients resent to
     */
    public static function resendToNonOpeners(int $newsletterId, ?string $newSubject = null, int $waitDays = 3): int
    {
        try {
            $newsletter = DB::table('newsletters')->find($newsletterId);
            if (!$newsletter || $newsletter->status !== 'sent') {
                return 0;
            }

            // Check if enough time has passed
            if ($newsletter->sent_at) {
                $daysSince = (int) ((time() - strtotime($newsletter->sent_at)) / 86400);
                if ($daysSince < $waitDays) {
                    return 0;
                }
            }

            $nonOpeners = DB::table('newsletter_queue')
                ->where('newsletter_id', $newsletterId)
                ->where('status', 'sent')
                ->whereNull('opened_at')
                ->get();

            // Queue them for re-send
            foreach ($nonOpeners as $item) {
                DB::table('newsletter_queue')
                    ->where('id', $item->id)
                    ->update(['status' => 'pending']);
            }

            return count($nonOpeners);
        } catch (\Exception $e) {
            Log::error('resendToNonOpeners error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get resend eligibility info for a newsletter.
     *
     * @param int $newsletterId Newsletter ID
     * @return array
     */
    public static function getResendInfo(int $newsletterId): array
    {
        try {
            $newsletter = DB::table('newsletters')->find($newsletterId);
            if (!$newsletter) {
                return ['eligible' => false, 'reason' => 'Newsletter not found'];
            }

            $nonOpenerCount = DB::table('newsletter_queue')
                ->where('newsletter_id', $newsletterId)
                ->where('status', 'sent')
                ->whereNull('opened_at')
                ->count();

            return [
                'eligible' => $newsletter->status === 'sent' && $nonOpenerCount > 0,
                'non_opener_count' => $nonOpenerCount,
                'sent_at' => $newsletter->sent_at,
            ];
        } catch (\Exception $e) {
            return ['eligible' => false, 'reason' => 'Error checking eligibility'];
        }
    }

    /**
     * Get the current email sending method.
     *
     * @return string 'Gmail API' or 'SMTP'
     */
    public static function getSendingMethod(): string
    {
        if (config('mail.default') === 'gmail' || !empty(config('services.gmail.client_id'))) {
            return 'Gmail API';
        }
        return 'SMTP';
    }

    /**
     * Process template variables in newsletter content.
     *
     * @param string $content Raw content with placeholders
     * @param array $variables Variable name => value mapping
     * @return string Processed content
     */
    public static function processTemplateVariables(string $content, array $variables = []): string
    {
        foreach ($variables as $key => $value) {
            $content = str_replace('{{' . $key . '}}', (string) $value, $content);
        }
        return $content;
    }
}
