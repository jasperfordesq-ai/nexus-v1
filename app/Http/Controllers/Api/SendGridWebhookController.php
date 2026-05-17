<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\EmailMonitorService;
use Illuminate\Http\JsonResponse;
use App\Core\TenantContext;
use App\Models\NewsletterBounce;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SendGridWebhookController — Eloquent-powered SendGrid event webhook handler.
 *
 * Fully migrated from legacy delegation to native Laravel.
 * Receives event notifications from SendGrid (bounces, complaints, deliveries)
 * and feeds them into the existing bounce/suppression system.
 *
 * This is an unauthenticated endpoint — SendGrid sends events here.
 */
class SendGridWebhookController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly EmailMonitorService $emailMonitorService,
    ) {}

    /**
     * POST /api/v2/webhooks/sendgrid/events
     *
     * Process SendGrid event webhook payload.
     * Expects a JSON array of event objects from SendGrid.
     *
     * Authentication: ECDSA signature verification (preferred) with shared-secret fallback.
     * See: https://docs.sendgrid.com/for-developers/tracking-events/getting-started-event-webhook-security-features
     */
    public function events(): JsonResponse
    {
        // ECDSA signature verification (SendGrid official method).
        // SECURITY: Query-parameter / shared-secret fallbacks have been
        // removed — secrets must NEVER travel in URLs (logged by proxies,
        // browser history, referer headers). The only accepted auth is the
        // SendGrid ECDSA signature verified against the public key in
        // SENDGRID_WEBHOOK_VERIFICATION_KEY.
        $verificationKey = env('SENDGRID_WEBHOOK_VERIFICATION_KEY');

        if (empty($verificationKey)) {
            Log::error('SendGrid webhook: SENDGRID_WEBHOOK_VERIFICATION_KEY is not configured — refusing to accept unauthenticated webhook traffic.');
            return $this->respondWithError('CONFIGURATION_ERROR', __('api.webhook_auth_not_configured'), null, 500);
        }

        // Accept raw base64 (single-line, Docker-friendly) or full PEM format.
        // Docker env_file does not support multiline values, so the key is stored
        // as a plain base64 string and wrapped in PEM headers here.
        if (!str_starts_with(trim($verificationKey), '-----BEGIN')) {
            $verificationKey = "-----BEGIN PUBLIC KEY-----\n" . trim($verificationKey) . "\n-----END PUBLIC KEY-----\n";
        }

        $signature = request()->header('X-Twilio-Email-Event-Webhook-Signature');
        $timestamp = request()->header('X-Twilio-Email-Event-Webhook-Timestamp');

        if (empty($signature) || empty($timestamp)) {
            return $this->respondWithError('UNAUTHORIZED', __('api.missing_signature_headers'), null, 401);
        }

        $payload = request()->getContent();
        $timestampedPayload = $timestamp . $payload;
        $decodedSignature = base64_decode($signature);

        $publicKey = openssl_pkey_get_public($verificationKey);
        if (!$publicKey) {
            Log::error('SendGrid webhook: SENDGRID_WEBHOOK_VERIFICATION_KEY is set but is not a valid public key.');
            return $this->respondWithError('CONFIGURATION_ERROR', __('api.webhook_auth_not_configured'), null, 500);
        }

        $valid = openssl_verify($timestampedPayload, $decodedSignature, $publicKey, OPENSSL_ALGO_SHA256);
        if ($valid !== 1) {
            return $this->respondWithError('UNAUTHORIZED', __('api.invalid_webhook_signature'), null, 401);
        }

        $payload = request()->getContent();

        if (empty($payload)) {
            return $this->respondWithError('INVALID_PAYLOAD', __('api.empty_payload'), null, 400);
        }

        $events = json_decode($payload, true);

        if (!is_array($events)) {
            return $this->respondWithError('INVALID_PAYLOAD', __('api.invalid_json_payload'), null, 400);
        }

        $processed = 0;

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $type = $event['event'] ?? '';
            $email = $event['email'] ?? '';
            $tenantId = (int) ($event['tenant_id'] ?? 0);

            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            // Validate tenant exists AND is active before accepting the tenant_id from payload
            if ($tenantId > 0) {
                $tenant = DB::selectOne(
                    "SELECT id, is_active FROM tenants WHERE id = ?",
                    [$tenantId]
                );
                if (!$tenant || empty($tenant->is_active)) {
                    // Invalid or inactive tenant ID from payload — skip this event
                    continue;
                }
                if (!TenantContext::setById($tenantId)) {
                    continue;
                }
            }

            // Also update the new email_log + email_suppression tables so
            // the deliverability dashboard at /admin/email-deliverability
            // reflects real-time SendGrid status. These updates are
            // additive — the existing NewsletterBounce / EmailMonitorService
            // pipelines below continue to run unchanged.
            $this->updateEmailLogAndSuppression($event, $type);

            switch ($type) {
                case 'bounce':
                case 'dropped':
                    $this->handleBounce($event, $tenantId);
                    $processed++;
                    break;

                case 'spamreport':
                    $this->handleComplaint($event, $tenantId);
                    $processed++;
                    break;

                case 'delivered':
                    $this->emailMonitorService->recordEmailSend('sendgrid', true, $tenantId ?: null);
                    $processed++;
                    break;

                case 'open':
                case 'click':
                case 'unsubscribe':
                case 'group_unsubscribe':
                    // Recorded in email_log / email_suppression by the
                    // updateEmailLogAndSuppression() call above; no further
                    // legacy-pipeline action needed.
                    $processed++;
                    break;

                default:
                    break;
            }
        }

        return response()->json([
            'received' => count($events),
            'processed' => $processed,
        ]);
    }

    /**
     * Update email_log (delivered_at / bounced_at / opened_at / status) and
     * email_suppression to reflect a real-time event from SendGrid.
     *
     * Matches the email_log row by recipient + (sg_message_id prefix). Never
     * regresses a row that is already in a terminal state (bounced / failed
     * stays terminal even if a stale `delivered` event arrives later).
     *
     * Best-effort: silently no-ops if the new tables don't exist on this
     * deployment yet.
     */
    private function updateEmailLogAndSuppression(array $event, string $type): void
    {
        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('email_log')) {
                return;
            }

            $email     = (string) ($event['email'] ?? '');
            $messageId = (string) ($event['sg_message_id'] ?? '');
            $ts        = isset($event['timestamp']) ? (int) $event['timestamp'] : time();
            $when      = date('Y-m-d H:i:s', $ts);
            if ($email === '' || $type === '') {
                return;
            }

            // SendGrid's sg_message_id is `<id>.<batch>.<idx>`; the
            // X-Message-Id we captured at send time is just `<id>`.
            $baseId = strpos($messageId, '.') !== false
                ? substr($messageId, 0, (int) strpos($messageId, '.'))
                : $messageId;

            $update    = ['updated_at' => now()];
            $reason    = null;
            $suppress  = false;
            $logUpdate = true;

            switch ($type) {
                case 'delivered':
                    $update['delivered_at'] = $when;
                    $update['status']       = 'delivered';
                    break;
                case 'open':
                    $update['opened_at'] = $when;
                    break;
                case 'bounce':
                    $update['bounced_at'] = $when;
                    $update['status']     = 'bounced';
                    $update['error']      = mb_substr((string) ($event['reason'] ?? 'bounce'), 0, 500);
                    $reason    = (string) ($event['reason'] ?? null);
                    $suppress  = 'bounce';
                    break;
                case 'dropped':
                    $update['status'] = 'failed';
                    $update['error']  = mb_substr('dropped: ' . ($event['reason'] ?? 'unknown'), 0, 500);
                    $reason    = (string) ($event['reason'] ?? null);
                    $suppress  = 'block';
                    break;
                case 'spamreport':
                    $update['status'] = 'failed';
                    $update['error']  = 'recipient marked as spam';
                    $suppress = 'spam_report';
                    break;
                case 'unsubscribe':
                case 'group_unsubscribe':
                    $logUpdate = false;
                    $suppress  = 'unsubscribe';
                    $reason    = isset($event['asm_group_id']) ? (string) $event['asm_group_id'] : null;
                    break;
                case 'click':
                case 'processed':
                case 'deferred':
                case 'group_resubscribe':
                default:
                    return; // nothing to persist
            }

            if ($logUpdate) {
                $row = DB::table('email_log')
                    ->where('recipient_email', $email)
                    ->when($baseId !== '', fn ($q) => $q->where('provider_message_id', 'like', $baseId . '%'))
                    ->orderByDesc('id')
                    ->first();
                if ($row) {
                    // Don't regress terminal states.
                    if (isset($update['status']) && in_array($row->status, ['bounced', 'failed'], true)
                        && $update['status'] === 'delivered') {
                        unset($update['status']);
                    }
                    DB::table('email_log')->where('id', $row->id)->update($update);
                }
            }

            if ($suppress !== false && \Illuminate\Support\Facades\Schema::hasTable('email_suppression')) {
                DB::table('email_suppression')->updateOrInsert(
                    ['email' => $email, 'reason' => $suppress],
                    [
                        'detail'        => $reason !== null ? mb_substr($reason, 0, 500) : null,
                        'suppressed_at' => $when,
                        'updated_at'    => now(),
                        'created_at'    => now(),
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::debug('SendGridWebhook::updateEmailLogAndSuppression failed: ' . $e->getMessage());
        }
    }

    /**
     * Handle bounce/dropped events.
     */
    private function handleBounce(array $event, int $tenantId): void
    {
        $email = $event['email'] ?? '';
        $sgType = $event['type'] ?? '';
        $reason = $event['reason'] ?? null;
        $status = $event['status'] ?? null;

        // SendGrid bounce types: 'bounce' (hard) or 'blocked' (soft/temporary)
        $bounceType = ($sgType === 'blocked')
            ? NewsletterBounce::BOUNCE_SOFT
            : NewsletterBounce::BOUNCE_HARD;

        if ($tenantId <= 0) {
            $this->emailMonitorService->recordEmailSend('sendgrid', false, null);
            return;
        }

        NewsletterBounce::record(
            $tenantId,
            $email,
            null, // newsletter_id — not always available from transactional emails
            null, // queue_id
            $bounceType,
            (string) ($reason ?? ''),
            (string) ($status ?? '')
        );

        $this->emailMonitorService->recordEmailSend('sendgrid', false, $tenantId ?: null);
    }

    /**
     * Handle spam report/complaint events.
     */
    private function handleComplaint(array $event, int $tenantId): void
    {
        $email = $event['email'] ?? '';

        if ($tenantId <= 0) {
            $this->emailMonitorService->recordEmailSend('sendgrid', false, null);
            return;
        }

        NewsletterBounce::record(
            $tenantId,
            $email,
            null,
            null,
            NewsletterBounce::BOUNCE_COMPLAINT,
            'SendGrid spam report',
            ''
        );

        $this->emailMonitorService->recordEmailSend('sendgrid', false, $tenantId ?: null);
    }
}
