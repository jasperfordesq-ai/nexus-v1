<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Receive SendGrid Event Webhook POSTs and update the local email_log /
 * email_suppression tables in real time.
 *
 *   POST /api/v2/webhooks/sendgrid/events
 *
 * SendGrid POSTs a JSON array of events. Each event has at minimum:
 *   - email           : recipient address
 *   - timestamp       : Unix seconds when the event fired
 *   - event           : processed | delivered | open | click |
 *                       bounce | dropped | spamreport | unsubscribe |
 *                       deferred | group_unsubscribe | group_resubscribe
 *   - sg_message_id   : matches email_log.provider_message_id from when
 *                       we sent (everything up to the first `.` is the
 *                       message id; the suffix is the recipient batch id)
 *   - reason / type   : extra context for bounce / dropped
 *
 * Authentication
 * --------------
 * SendGrid signs the request with an ECDSA P-256 key. Verify using:
 *   - X-Twilio-Email-Event-Webhook-Signature
 *   - X-Twilio-Email-Event-Webhook-Timestamp
 *   - SENDGRID_EVENT_WEBHOOK_PUBLIC_KEY env var (base64-encoded EC public key)
 *
 * If the public key is not configured the controller falls back to a
 * shared-secret check (X-Nexus-Webhook-Token vs config('mail.sendgrid.webhook_secret'))
 * so the endpoint is never wide open.
 *
 * Idempotency
 * -----------
 * SendGrid can retry an event delivery. Updates are idempotent: we only
 * advance email_log.status forward (queued → sent → delivered → bounced)
 * and never regress, and email_suppression has a UNIQUE (email, reason)
 * key so re-inserts are no-ops.
 */
class SendGridEventWebhookController extends BaseApiController
{
    protected bool $isV2Api = true;

    /** Max age for a signed timestamp (5 minutes — SendGrid default). */
    private const SIGNATURE_MAX_AGE_SECONDS = 600;

    public function ingest(Request $request): JsonResponse
    {
        // Auth check first. The raw body must be re-read for signature
        // verification because Laravel may have already consumed the input
        // stream by the time getContent() returns; we need the exact bytes.
        $rawBody = $request->getContent();

        if (!$this->isAuthenticated($request, $rawBody)) {
            Log::warning('SendGrid webhook: auth failed', [
                'ip' => $request->ip(),
            ]);
            return $this->respondWithError('UNAUTHORIZED', 'Invalid webhook signature.', null, 401);
        }

        $events = json_decode($rawBody, true);
        if (!is_array($events)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Body must be a JSON array of events.', null, 400);
        }

        if (!Schema::hasTable('email_log')) {
            // The migration hasn't run yet — accept the events to avoid
            // SendGrid spam-retrying, but log so operators know.
            Log::warning('SendGrid webhook: email_log table missing — ignoring batch');
            return $this->respondWithData(['received' => count($events), 'processed' => 0]);
        }

        $processed = 0;
        foreach ($events as $event) {
            try {
                if ($this->handleEvent((array) $event)) {
                    $processed++;
                }
            } catch (\Throwable $e) {
                Log::warning('SendGrid webhook event handler failed', [
                    'event' => $event['event'] ?? '?',
                    'email' => $event['email'] ?? '?',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->respondWithData([
            'received'  => count($events),
            'processed' => $processed,
        ]);
    }

    private function handleEvent(array $event): bool
    {
        $email     = (string) ($event['email'] ?? '');
        $type      = (string) ($event['event'] ?? '');
        $messageId = (string) ($event['sg_message_id'] ?? '');
        $timestamp = isset($event['timestamp']) ? (int) $event['timestamp'] : time();
        $when      = date('Y-m-d H:i:s', $timestamp);

        if ($email === '' || $type === '') {
            return false;
        }

        // SendGrid prefixes sg_message_id like `<id>.<batch>.<idx>`; the
        // X-Message-Id header we captured at send time is just the `<id>`
        // segment, so match on prefix.
        $baseId = strpos($messageId, '.') !== false
            ? substr($messageId, 0, (int) strpos($messageId, '.'))
            : $messageId;

        $now = now();
        $update = ['updated_at' => $now];

        switch ($type) {
            case 'delivered':
                $update['delivered_at'] = $when;
                $update['status']       = 'delivered';
                $this->advanceStatus($baseId, $email, $update);
                break;

            case 'open':
                $update['opened_at'] = $when;
                $this->advanceStatus($baseId, $email, $update);
                break;

            case 'click':
                // Useful telemetry but not currently a tracked column.
                // Could be added if/when operators want click rates.
                break;

            case 'bounce':
                $update['bounced_at'] = $when;
                $update['status']     = 'bounced';
                $update['error']      = mb_substr((string) ($event['reason'] ?? 'bounce'), 0, 500);
                $this->advanceStatus($baseId, $email, $update);
                $this->upsertSuppression($email, 'bounce', (string) ($event['reason'] ?? null), $when);
                break;

            case 'dropped':
                $update['status'] = 'failed';
                $update['error']  = mb_substr('dropped: ' . ($event['reason'] ?? 'unknown'), 0, 500);
                $this->advanceStatus($baseId, $email, $update);
                $this->upsertSuppression($email, 'block', (string) ($event['reason'] ?? null), $when);
                break;

            case 'spamreport':
                $update['status'] = 'failed';
                $update['error']  = 'recipient marked as spam';
                $this->advanceStatus($baseId, $email, $update);
                $this->upsertSuppression($email, 'spam_report', null, $when);
                break;

            case 'unsubscribe':
            case 'group_unsubscribe':
                $this->upsertSuppression($email, 'unsubscribe', (string) ($event['asm_group_id'] ?? null), $when);
                break;

            case 'deferred':
            case 'processed':
            case 'group_resubscribe':
            default:
                // No persistent state change — these are informational.
                return false;
        }

        return true;
    }

    /**
     * Update the matching email_log row IF it exists. We never advance the
     * status of a row that we already classified as `bounced` or `failed`,
     * so a stale `delivered` event from SendGrid can't overwrite an earlier
     * bounce.
     */
    private function advanceStatus(string $baseMessageId, string $email, array $update): void
    {
        // Find the most recent matching row. Match on (message id LIKE) +
        // recipient email so a degraded sg_message_id doesn't collide
        // across recipients.
        $row = DB::table('email_log')
            ->where('recipient_email', $email)
            ->when($baseMessageId !== '', function ($q) use ($baseMessageId) {
                $q->where('provider_message_id', 'like', $baseMessageId . '%');
            })
            ->orderByDesc('id')
            ->first();

        if (!$row) {
            // The send happened before email_log existed, or the message id
            // didn't make it into our row. Skip silently — the webhook is
            // best-effort.
            return;
        }

        // Don't regress status. Terminal states (bounced/failed) stay.
        if (isset($update['status']) && in_array($row->status, ['bounced', 'failed'], true)
            && $update['status'] === 'delivered') {
            unset($update['status']);
        }

        DB::table('email_log')->where('id', $row->id)->update($update);
    }

    private function upsertSuppression(string $email, string $reason, ?string $detail, string $when): void
    {
        if (!Schema::hasTable('email_suppression')) {
            return;
        }
        DB::table('email_suppression')->updateOrInsert(
            ['email' => $email, 'reason' => $reason],
            [
                'detail'        => $detail !== null ? mb_substr($detail, 0, 500) : null,
                'suppressed_at' => $when,
                'updated_at'    => now(),
                'created_at'    => now(),
            ]
        );
    }

    private function isAuthenticated(Request $request, string $rawBody): bool
    {
        // Option 1: SendGrid ECDSA signature (preferred).
        $publicKeyPem = (string) (config('mail.sendgrid.event_webhook_public_key')
            ?? env('SENDGRID_EVENT_WEBHOOK_PUBLIC_KEY', ''));
        $sig          = $request->header('X-Twilio-Email-Event-Webhook-Signature');
        $ts           = $request->header('X-Twilio-Email-Event-Webhook-Timestamp');

        if ($publicKeyPem !== '' && $sig !== null && $ts !== null) {
            return $this->verifySendGridSignature($publicKeyPem, $sig, (string) $ts, $rawBody);
        }

        // Option 2: shared-secret fallback. Configure SENDGRID_WEBHOOK_TOKEN
        // in the platform .env and add it as a custom header in the SendGrid
        // webhook configuration UI.
        $expected = (string) (config('mail.sendgrid.webhook_secret') ?? env('SENDGRID_WEBHOOK_TOKEN', ''));
        $supplied = (string) ($request->header('X-Nexus-Webhook-Token') ?? '');
        if ($expected !== '' && hash_equals($expected, $supplied)) {
            return true;
        }

        return false;
    }

    private function verifySendGridSignature(string $publicKeyPem, string $signatureB64, string $timestamp, string $body): bool
    {
        // Replay protection.
        $age = abs(time() - (int) $timestamp);
        if ($age > self::SIGNATURE_MAX_AGE_SECONDS) {
            return false;
        }

        // SendGrid signs: timestamp + body, with ECDSA on the P-256 curve,
        // returning a DER-encoded ASN.1 signature, base64-encoded in the
        // header. openssl_verify with the EC public key handles all of that.
        $signed    = $timestamp . $body;
        $signature = base64_decode($signatureB64, true);
        if ($signature === false) {
            return false;
        }
        $pkey = openssl_pkey_get_public($publicKeyPem);
        if ($pkey === false) {
            return false;
        }
        $result = openssl_verify($signed, $signature, $pkey, OPENSSL_ALGO_SHA256);

        return $result === 1;
    }
}
