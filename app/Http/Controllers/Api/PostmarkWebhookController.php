<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\EmailMonitorService;
use App\Core\TenantContext;
use App\Models\NewsletterBounce;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * PostmarkWebhookController — Postmark event webhook handler.
 *
 * Receives event notifications from Postmark (Delivery, Bounce, SpamComplaint,
 * Open, SubscriptionChange) and feeds them into the same bounce/suppression/
 * monitoring pipeline used for SendGrid, so the deliverability dashboard and
 * the Mailer's pre-send suppression check work identically under either
 * provider.
 *
 * Unauthenticated route (Postmark cannot present a Sanctum token); the request
 * is authenticated here via HTTP Basic auth (or an X-Postmark-Webhook-Secret
 * header) against POSTMARK_WEBHOOK_SECRET, delivered over HTTPS. Postmark has no
 * request-signing scheme, so a shared secret is the supported mechanism.
 */
class PostmarkWebhookController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly EmailMonitorService $emailMonitorService,
    ) {}

    /**
     * POST /api/v2/webhooks/postmark
     *
     * Postmark posts one event object per request (keyed by RecordType); a
     * batch array is tolerated defensively.
     */
    public function events(): JsonResponse
    {
        $secret = (string) (config('mail.postmark.webhook_secret') ?? '');
        if ($secret === '') {
            Log::error('Postmark webhook: POSTMARK_WEBHOOK_SECRET is not configured — refusing to accept unauthenticated webhook traffic.');
            return $this->respondWithError('CONFIGURATION_ERROR', __('api.webhook_auth_not_configured'), null, 500);
        }

        $providedPassword = (string) (request()->getPassword() ?? '');
        $providedHeader   = (string) request()->header('X-Postmark-Webhook-Secret', '');
        if (!hash_equals($secret, $providedPassword) && !hash_equals($secret, $providedHeader)) {
            return $this->respondWithError('UNAUTHORIZED', __('api.invalid_webhook_signature'), null, 401);
        }

        $payload = request()->getContent();
        if (empty($payload)) {
            return $this->respondWithError('INVALID_PAYLOAD', __('api.empty_payload'), null, 400);
        }

        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            return $this->respondWithError('INVALID_PAYLOAD', __('api.invalid_json_payload'), null, 400);
        }

        // Single event object -> wrap; already-an-array batch -> use as-is.
        $events = array_key_exists('RecordType', $decoded) ? [$decoded] : $decoded;

        $processed = 0;

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $type  = (string) ($event['RecordType'] ?? '');
            $email = (string) ($event['Email'] ?? $event['Recipient'] ?? '');

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $metadata = is_array($event['Metadata'] ?? null) ? $event['Metadata'] : [];
            $tenantId = (int) ($metadata['tenant_id'] ?? 0);

            $previousTenantId = TenantContext::currentId();

            if ($tenantId > 0) {
                $tenant = DB::selectOne("SELECT id, is_active FROM tenants WHERE id = ?", [$tenantId]);
                if (!$tenant || empty($tenant->is_active)) {
                    continue;
                }
                if (!TenantContext::setById($tenantId)) {
                    continue;
                }
            }

            try {
                $this->updateEmailLogAndSuppression($event, $type, $tenantId);

                switch ($type) {
                    case 'Bounce':
                        $this->handleBounce($event, $tenantId);
                        $processed++;
                        break;

                    case 'SpamComplaint':
                        $this->handleComplaint($event, $tenantId);
                        $processed++;
                        break;

                    case 'Delivery':
                        $this->emailMonitorService->recordEmailSend('postmark', true, $tenantId ?: null);
                        $processed++;
                        break;

                    case 'Open':
                    case 'Click':
                    case 'SubscriptionChange':
                        // Persisted in email_log / email_suppression above.
                        $processed++;
                        break;

                    default:
                        break;
                }
            } catch (\Throwable $e) {
                // One bad event must not fail the batch — Postmark would retry
                // the whole delivery and already-committed rows would re-write.
                Log::error('[PostmarkWebhook] Event processing failed — skipping event', [
                    'type'  => $type,
                    'error' => $e->getMessage(),
                ]);
            } finally {
                if ($previousTenantId !== null) {
                    TenantContext::setById($previousTenantId);
                } else {
                    TenantContext::reset();
                }
            }
        }

        return response()->json([
            'received'  => count($events),
            'processed' => $processed,
        ]);
    }

    /**
     * Update email_log (delivered_at / bounced_at / opened_at / status) and
     * email_suppression from a Postmark event. Postmark's MessageID matches the
     * provider_message_id captured at send time exactly (unlike SendGrid's
     * dotted id). Never regresses a terminal state. Best-effort — no-ops if the
     * tables are absent on this deployment.
     */
    private function updateEmailLogAndSuppression(array $event, string $type, int $tenantId = 0): void
    {
        try {
            if (!Schema::hasTable('email_log')) {
                return;
            }

            $email     = (string) ($event['Email'] ?? $event['Recipient'] ?? '');
            $messageId = (string) ($event['MessageID'] ?? '');
            if ($email === '' || $type === '') {
                return;
            }

            $tsRaw = $event['DeliveredAt'] ?? $event['BouncedAt'] ?? $event['ReceivedAt'] ?? $event['ChangedAt'] ?? null;
            $ts    = $tsRaw !== null ? strtotime((string) $tsRaw) : false;
            $when  = date('Y-m-d H:i:s', $ts !== false ? $ts : time());

            $update    = ['updated_at' => now()];
            $reason    = null;
            $suppress  = false;
            $logUpdate = true;

            switch ($type) {
                case 'Delivery':
                    $update['delivered_at'] = $when;
                    $update['status']       = 'delivered';
                    break;
                case 'Open':
                    $update['opened_at'] = $when;
                    break;
                case 'Bounce':
                    $update['bounced_at'] = $when;
                    $update['status']     = 'bounced';
                    $update['error']      = mb_substr((string) ($event['Description'] ?? $event['Details'] ?? 'bounce'), 0, 500);
                    $reason   = (string) ($event['Description'] ?? $event['Details'] ?? '');
                    // Permanent failures suppress the address; transient ones don't.
                    $permanent = in_array((string) ($event['Type'] ?? ''), [
                        'HardBounce', 'BadEmailAddress', 'ManuallyDeactivated',
                        'Unsubscribe', 'BlockedRecipient', 'AddressChange',
                    ], true);
                    $suppress = $permanent ? 'bounce' : 'block';
                    break;
                case 'SpamComplaint':
                    $update['status'] = 'failed';
                    $update['error']  = 'recipient marked as spam';
                    $suppress = 'spam_report';
                    break;
                case 'SubscriptionChange':
                    $logUpdate = false;
                    if (!empty($event['SuppressSending'])) {
                        $suppress = 'unsubscribe';
                    } else {
                        return; // re-subscribe — nothing to suppress
                    }
                    break;
                case 'Click':
                default:
                    return; // nothing to persist
            }

            if ($logUpdate) {
                if ($tenantId <= 0 || $messageId === '') {
                    Log::warning('Postmark webhook skipped email_log update without tenant/message id', [
                        'tenant_id'      => $tenantId > 0 ? $tenantId : null,
                        'has_message_id' => $messageId !== '',
                        'event'          => $type,
                    ]);
                } else {
                    $row = DB::table('email_log')
                        ->where('recipient_email', $email)
                        ->where('tenant_id', $tenantId)
                        ->where('provider', 'postmark')
                        ->where('provider_message_id', $messageId)
                        ->orderByDesc('id')
                        ->first();
                    if ($row) {
                        if (isset($update['status']) && in_array($row->status, ['bounced', 'failed'], true)
                            && $update['status'] === 'delivered') {
                            unset($update['status']);
                        }
                        DB::table('email_log')->where('id', $row->id)->update($update);
                    }
                }
            }

            if ($suppress !== false && Schema::hasTable('email_suppression')) {
                DB::table('email_suppression')->updateOrInsert(
                    ['email' => $email, 'reason' => $suppress],
                    [
                        'detail'        => $reason !== null && $reason !== '' ? mb_substr($reason, 0, 500) : null,
                        'suppressed_at' => $when,
                        'updated_at'    => now(),
                        'created_at'    => now(),
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::debug('PostmarkWebhook::updateEmailLogAndSuppression failed: ' . $e->getMessage());
        }
    }

    /**
     * Handle Bounce events → NewsletterBounce (hard vs soft) + monitor.
     */
    private function handleBounce(array $event, int $tenantId): void
    {
        $email  = (string) ($event['Email'] ?? '');
        $pmType = (string) ($event['Type'] ?? '');
        $reason = (string) ($event['Description'] ?? $event['Details'] ?? '');

        $soft = in_array($pmType, ['SoftBounce', 'Transient', 'DnsError', 'Blocked', 'SpamNotification'], true);
        $bounceType = $soft ? NewsletterBounce::BOUNCE_SOFT : NewsletterBounce::BOUNCE_HARD;

        if ($tenantId <= 0) {
            $this->emailMonitorService->recordEmailSend('postmark', false, null);
            return;
        }

        NewsletterBounce::record(
            $tenantId,
            $email,
            null, // newsletter_id — not available for transactional mail
            null, // queue_id
            $bounceType,
            $reason,
            $pmType
        );

        $this->emailMonitorService->recordEmailSend('postmark', false, $tenantId ?: null);
    }

    /**
     * Handle SpamComplaint events → NewsletterBounce complaint + monitor.
     */
    private function handleComplaint(array $event, int $tenantId): void
    {
        $email = (string) ($event['Email'] ?? '');

        if ($tenantId <= 0) {
            $this->emailMonitorService->recordEmailSend('postmark', false, null);
            return;
        }

        NewsletterBounce::record(
            $tenantId,
            $email,
            null,
            null,
            NewsletterBounce::BOUNCE_COMPLAINT,
            'Postmark spam complaint',
            ''
        );

        $this->emailMonitorService->recordEmailSend('postmark', false, $tenantId ?: null);
    }
}
