<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford (via Claude Code)
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Services\FederatedMessageService;
use App\Services\FederationExternalPartnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * FederationExternalWebhookController — Receives webhook events from
 * external federation partners (e.g., TimeOverflow).
 *
 * Public endpoint (no Sanctum auth). Authentication is via HMAC-SHA256
 * signature verification using the partner's signing_secret.
 *
 * POST /api/v2/federation/external/webhooks/receive
 *
 * Expected payload:
 *   {
 *     "event": "message.sent",
 *     "partner_id": 1,          // The SENDING partner's ID on their side (informational)
 *     "timestamp": "...",
 *     "data": { ... event-specific payload ... }
 *   }
 *
 * Headers:
 *   X-Webhook-Signature: HMAC-SHA256 hex digest of the raw body
 *   X-Webhook-Timestamp: Unix timestamp (for replay protection)
 *   X-Federation-Signature: Alternative header (Nexus format)
 *   X-Federation-Timestamp: Alternative header (Nexus format)
 */
class FederationExternalWebhookController extends BaseApiController
{
    protected bool $isV2Api = true;

    /** Maximum age of a webhook timestamp before rejection (seconds) */
    private const TIMESTAMP_TOLERANCE = 300; // 5 minutes

    /** Rate limit: max webhooks per minute per IP */
    private const RATE_LIMIT_PER_MINUTE = 200;

    /**
     * POST /api/v2/federation/external/webhooks/receive
     */
    public function receive(Request $request): JsonResponse
    {
        // ---- Rate limit by IP ----
        $ip = $request->ip();
        $rateLimitKey = "federation_ext_webhook:{$ip}";
        if (RateLimiter::tooManyAttempts($rateLimitKey, self::RATE_LIMIT_PER_MINUTE)) {
            return response()->json([
                'errors' => [['code' => 'RATE_LIMITED', 'message' => 'Too many requests']],
            ], 429);
        }
        RateLimiter::hit($rateLimitKey, 60);

        // ---- Parse body ----
        $rawBody = $request->getContent();
        if (empty($rawBody)) {
            return $this->respondWithError('INVALID_REQUEST', 'Empty request body', null, 400);
        }

        $payload = json_decode($rawBody, true, 10);
        if (!is_array($payload)) {
            return $this->respondWithError('INVALID_REQUEST', 'Invalid JSON', null, 400);
        }

        $event = $payload['event'] ?? null;
        $data = $payload['data'] ?? [];

        if (empty($event)) {
            return $this->respondWithError('INVALID_REQUEST', 'Missing event type', null, 400);
        }

        // ---- Identify the external partner ----
        // The partner is identified by matching the signing_secret used to generate
        // the HMAC signature. We look up partners that have a signing_secret configured.
        $partner = $this->identifyAndVerifyPartner($request, $rawBody);
        if (!$partner) {
            return $this->respondWithError('INVALID_SIGNATURE', 'Invalid or missing webhook signature', null, 401);
        }

        if ($partner->status !== 'active') {
            return $this->respondWithError('PARTNER_INACTIVE', 'Partner is not active', null, 403);
        }

        // ---- Set tenant context from partner ----
        TenantContext::setById($partner->tenant_id);

        // ---- Log the webhook ----
        $logId = $this->logWebhook($partner, $event, $payload);

        // ---- Dispatch event ----
        try {
            $result = $this->handleEvent($event, $data, $partner);

            DB::table('federation_external_partner_logs')
                ->where('id', $logId)
                ->update(['response_code' => 200, 'success' => true]);

            return $this->respondWithData([
                'received' => true,
                'event' => $event,
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            Log::error("[FederationExternalWebhook] Event processing failed: {$e->getMessage()}", [
                'event' => $event,
                'partner_id' => $partner->id,
                'trace' => $e->getTraceAsString(),
            ]);

            DB::table('federation_external_partner_logs')
                ->where('id', $logId)
                ->update(['response_code' => 500, 'success' => false, 'error_message' => substr($e->getMessage(), 0, 1000)]);

            return $this->respondWithError('PROCESSING_FAILED', 'Webhook processing failed', null, 500);
        }
    }

    // ----------------------------------------------------------------
    // Signature verification
    // ----------------------------------------------------------------

    /**
     * Identify the external partner by verifying the HMAC signature against
     * each partner's signing_secret. Returns the matched partner or null.
     */
    private function identifyAndVerifyPartner(Request $request, string $rawBody): ?object
    {
        $signature = $request->header('X-Webhook-Signature')
            ?? $request->header('X-Federation-Signature');

        if (empty($signature)) {
            return null;
        }

        $timestamp = $request->header('X-Webhook-Timestamp')
            ?? $request->header('X-Federation-Timestamp');

        // Timestamp freshness check (if provided)
        if (!empty($timestamp) && abs(time() - (int) $timestamp) > self::TIMESTAMP_TOLERANCE) {
            Log::warning('[FederationExternalWebhook] Expired timestamp', [
                'timestamp' => $timestamp,
                'now' => time(),
            ]);
            return null;
        }

        // Try all partners with signing_secret configured
        $partners = DB::table('federation_external_partners')
            ->whereNotNull('signing_secret')
            ->where('signing_secret', '!=', '')
            ->get();

        foreach ($partners as $partner) {
            $secret = $partner->signing_secret;

            // Try simple body-only HMAC (TimeOverflow default)
            $expectedSimple = hash_hmac('sha256', $rawBody, $secret);
            if (hash_equals($expectedSimple, $signature)) {
                return $partner;
            }

            // Try Nexus format: METHOD\nPATH\nTIMESTAMP\nBODY
            if (!empty($timestamp)) {
                $stringToSign = implode("\n", [
                    $request->method(),
                    $request->getPathInfo(),
                    $timestamp,
                    $rawBody,
                ]);
                $expectedNexus = hash_hmac('sha256', $stringToSign, $secret);
                if (hash_equals($expectedNexus, $signature)) {
                    return $partner;
                }
            }
        }

        return null;
    }

    // ----------------------------------------------------------------
    // Event routing
    // ----------------------------------------------------------------

    private function handleEvent(string $event, array $data, object $partner): array
    {
        return match ($event) {
            'message.sent', 'message.received' => $this->handleInboundMessage($data, $partner),
            'transaction.completed' => $this->handleTransactionCompleted($data, $partner),
            'transaction.cancelled' => $this->handleTransactionCancelled($data, $partner),
            'transaction.requested' => $this->handleTransactionRequested($data, $partner),
            'partnership.activated', 'partnership.approved' => $this->handlePartnershipActivated($partner),
            'partnership.suspended' => $this->handlePartnershipSuspended($partner),
            'partnership.terminated' => $this->handlePartnershipTerminated($partner),
            'health_check' => ['status' => 'ok'],
            default => ['status' => 'unhandled', 'event' => $event],
        };
    }

    // ----------------------------------------------------------------
    // Message handling
    // ----------------------------------------------------------------

    private function handleInboundMessage(array $data, object $partner): array
    {
        if (!$partner->allow_messaging) {
            return ['status' => 'rejected', 'reason' => 'Messaging not enabled for this partner'];
        }

        // Map TimeOverflow webhook fields to Nexus fields.
        //
        // TimeOverflow webhook payload (data object):
        //   sender_id:            TimeOverflow member ID (integer, external to Nexus)
        //   sender_name:          TimeOverflow username (string)
        //   recipient_id:         The Nexus user ID that TimeOverflow is sending to
        //   subject:              Message subject (optional)
        //   body:                 Message body (required)
        //   external_message_id:  Unique ID from TimeOverflow (e.g., "to_msg_abc123")
        //   organization_id:      TimeOverflow org ID (informational)
        //   organization_name:    TimeOverflow org name (informational)
        //
        // IMPORTANT: recipient_id MUST be a valid Nexus user ID. TimeOverflow
        // obtains this when the Nexus user's ID is shared via the federation
        // member directory (e.g., when browsing partner members in the UI).
        $recipientId = $data['recipient_id'] ?? $data['local_member_id'] ?? null;
        $senderId = $data['sender_id'] ?? $data['remote_user_identifier'] ?? 0;
        $senderName = $data['sender_name'] ?? $data['remote_user_identifier'] ?? 'External User';
        $subject = $data['subject'] ?? '';
        $body = $data['body'] ?? $data['message'] ?? '';
        $externalMessageId = $data['external_message_id'] ?? $data['message_id'] ?? null;

        // Include the source organization for context in the sender name
        $orgName = $data['organization_name'] ?? null;
        if ($orgName && !str_contains($senderName, $orgName)) {
            $senderName .= " ({$orgName})";
        }

        if (empty($body)) {
            return ['status' => 'rejected', 'reason' => 'Message body is required'];
        }

        if (empty($recipientId)) {
            return ['status' => 'rejected', 'reason' => 'Recipient ID is required'];
        }

        $receiverUserId = (int) $recipientId;

        // Validate the receiver exists before calling storeExternalMessage
        $receiver = DB::table('users')
            ->where('id', $receiverUserId)
            ->where('tenant_id', TenantContext::getId())
            ->where('status', 'active')
            ->exists();

        if (!$receiver) {
            Log::warning('[FederationExternalWebhook] Recipient not found', [
                'recipient_id' => $recipientId,
                'partner' => $partner->name,
                'tenant_id' => TenantContext::getId(),
            ]);
            return ['status' => 'rejected', 'reason' => "Recipient user #{$recipientId} not found in this tenant"];
        }

        $result = FederatedMessageService::storeExternalMessage(
            receiverUserId: $receiverUserId,
            externalPartnerId: $partner->id,
            externalSenderId: is_numeric($senderId) ? (int) $senderId : 0,
            senderName: (string) $senderName,
            partnerName: $partner->name ?? 'External Partner',
            subject: (string) $subject,
            body: (string) $body,
            externalMessageId: $externalMessageId ? (string) $externalMessageId : null
        );

        // Update last_message_at on the partner
        DB::table('federation_external_partners')
            ->where('id', $partner->id)
            ->update(['last_message_at' => now()]);

        return $result;
    }

    // ----------------------------------------------------------------
    // Transaction handling
    // ----------------------------------------------------------------

    private function handleTransactionCompleted(array $data, object $partner): array
    {
        if (!$partner->allow_transactions) {
            return ['status' => 'rejected', 'reason' => 'Transactions not enabled for this partner'];
        }

        $externalTxId = $data['external_transaction_id'] ?? null;

        Log::info('[FederationExternalWebhook] Transaction completed', [
            'partner' => $partner->name,
            'external_transaction_id' => $externalTxId,
        ]);

        // Record the completed transaction in our federation_transactions table
        $recipientId = $data['recipient_id'] ?? $data['local_member_id'] ?? null;
        $senderId = $data['sender_id'] ?? 0;
        $amount = (float) ($data['amount'] ?? 0);
        $description = $data['description'] ?? '';

        if ($recipientId && $amount > 0) {
            $receiverUserId = (int) $recipientId;

            // Validate receiver exists in this tenant
            $receiver = DB::table('users')
                ->where('id', $receiverUserId)
                ->where('tenant_id', TenantContext::getId())
                ->where('status', 'active')
                ->first();

            if (!$receiver) {
                return ['status' => 'rejected', 'reason' => "Recipient user #{$recipientId} not found in this tenant"];
            }

            // Prevent duplicate recording using external_transaction_id
            if ($externalTxId) {
                $exists = DB::table('federation_transactions')
                    ->where('external_transaction_id', $externalTxId)
                    ->where('external_partner_id', $partner->id)
                    ->exists();
                if ($exists) {
                    return ['status' => 'duplicate', 'reason' => 'Transaction already recorded'];
                }
            }

            // Credit the receiver's balance and record the transaction
            DB::beginTransaction();
            try {
                DB::update("UPDATE users SET balance = balance + ? WHERE id = ?", [$amount, $receiverUserId]);

                DB::table('federation_transactions')->insert([
                    'sender_tenant_id'       => 0, // External origin
                    'sender_user_id'         => (int) $senderId,
                    'receiver_tenant_id'     => TenantContext::getId(),
                    'receiver_user_id'       => $receiverUserId,
                    'amount'                 => $amount,
                    'description'            => $description,
                    'status'                 => 'completed',
                    'completed_at'           => now(),
                    'external_partner_id'    => $partner->id,
                    'external_receiver_name' => $data['sender_name'] ?? 'External User',
                    'external_transaction_id' => $externalTxId,
                    'created_at'             => now(),
                ]);

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('[FederationExternalWebhook] Failed to record transaction', [
                    'error' => $e->getMessage(),
                    'partner' => $partner->name,
                    'external_transaction_id' => $externalTxId,
                ]);
                return ['status' => 'error', 'reason' => 'Failed to record transaction'];
            }
        }

        return ['status' => 'acknowledged'];
    }

    private function handleTransactionCancelled(array $data, object $partner): array
    {
        if (!$partner->allow_transactions) {
            return ['status' => 'rejected', 'reason' => 'Transactions not enabled for this partner'];
        }

        $externalTxId = $data['external_transaction_id'] ?? null;
        $reason = $data['reason'] ?? null;

        Log::info('[FederationExternalWebhook] Transaction cancelled', [
            'partner' => $partner->name,
            'external_transaction_id' => $externalTxId,
            'reason' => $reason,
        ]);

        // If we have a record of this transaction, mark it as cancelled and reverse the credit
        if ($externalTxId) {
            $tx = DB::table('federation_transactions')
                ->where('external_transaction_id', $externalTxId)
                ->where('external_partner_id', $partner->id)
                ->first();

            if ($tx && $tx->status === 'completed') {
                DB::beginTransaction();
                try {
                    // Reverse the credit
                    DB::update("UPDATE users SET balance = balance - ? WHERE id = ? AND balance >= ?",
                        [$tx->amount, $tx->receiver_user_id, $tx->amount]);

                    DB::table('federation_transactions')
                        ->where('id', $tx->id)
                        ->update([
                            'status'              => 'cancelled',
                            'cancelled_at'        => now(),
                            'cancellation_reason' => $reason,
                        ]);

                    DB::commit();
                    return ['status' => 'cancelled'];
                } catch (\Throwable $e) {
                    DB::rollBack();
                    Log::error('[FederationExternalWebhook] Failed to cancel transaction', [
                        'error' => $e->getMessage(),
                        'transaction_id' => $tx->id,
                    ]);
                    return ['status' => 'error', 'reason' => 'Failed to cancel transaction'];
                }
            }
        }

        return ['status' => 'acknowledged'];
    }

    private function handleTransactionRequested(array $data, object $partner): array
    {
        if (!$partner->allow_transactions) {
            return ['status' => 'rejected', 'reason' => 'Transactions not enabled for this partner'];
        }

        $externalTxId = $data['external_transaction_id'] ?? null;
        $amount = (float) ($data['amount'] ?? 0);
        $recipientId = $data['recipient_id'] ?? $data['local_member_id'] ?? null;

        Log::info('[FederationExternalWebhook] Transaction requested', [
            'partner' => $partner->name,
            'external_transaction_id' => $externalTxId,
            'amount' => $amount,
            'recipient_id' => $recipientId,
        ]);

        // Validate basic fields
        if (!$recipientId || $amount <= 0) {
            return ['status' => 'rejected', 'reason' => 'Missing recipient_id or invalid amount'];
        }

        // Validate receiver exists
        $receiverUserId = (int) $recipientId;
        $receiver = DB::table('users')
            ->where('id', $receiverUserId)
            ->where('tenant_id', TenantContext::getId())
            ->where('status', 'active')
            ->exists();

        if (!$receiver) {
            return ['status' => 'rejected', 'reason' => "Recipient user #{$recipientId} not found in this tenant"];
        }

        // Prevent duplicate
        if ($externalTxId) {
            $exists = DB::table('federation_transactions')
                ->where('external_transaction_id', $externalTxId)
                ->where('external_partner_id', $partner->id)
                ->exists();
            if ($exists) {
                return ['status' => 'duplicate', 'reason' => 'Transaction already recorded'];
            }
        }

        // Record as pending — awaits a transaction.completed event to credit the balance
        DB::table('federation_transactions')->insert([
            'sender_tenant_id'        => 0,
            'sender_user_id'          => (int) ($data['sender_id'] ?? 0),
            'receiver_tenant_id'      => TenantContext::getId(),
            'receiver_user_id'        => $receiverUserId,
            'amount'                  => $amount,
            'description'             => $data['description'] ?? '',
            'status'                  => 'pending',
            'external_partner_id'     => $partner->id,
            'external_receiver_name'  => $data['sender_name'] ?? 'External User',
            'external_transaction_id' => $externalTxId,
            'created_at'              => now(),
        ]);

        return ['status' => 'accepted'];
    }

    // ----------------------------------------------------------------
    // Partnership events
    // ----------------------------------------------------------------

    private function handlePartnershipActivated(object $partner): array
    {
        DB::table('federation_external_partners')
            ->where('id', $partner->id)
            ->update(['status' => 'active', 'verified_at' => now(), 'error_count' => 0, 'last_error' => null]);
        return ['status' => 'activated'];
    }

    private function handlePartnershipSuspended(object $partner): array
    {
        DB::table('federation_external_partners')
            ->where('id', $partner->id)
            ->update(['status' => 'suspended']);
        return ['status' => 'suspended'];
    }

    private function handlePartnershipTerminated(object $partner): array
    {
        DB::table('federation_external_partners')
            ->where('id', $partner->id)
            ->update(['status' => 'failed']);
        return ['status' => 'terminated'];
    }

    // ----------------------------------------------------------------
    // Logging
    // ----------------------------------------------------------------

    private function logWebhook(object $partner, string $event, array $payload): int
    {
        return DB::table('federation_external_partner_logs')->insertGetId([
            'partner_id' => $partner->id,
            'endpoint' => "/webhooks/receive [{$event}]",
            'method' => 'POST',
            'response_code' => 0,
            'success' => false,
            'request_body' => substr(json_encode($payload), 0, 10000),
            'response_body' => null,
            'response_time_ms' => 0,
            'created_at' => now(),
        ]);
    }
}
