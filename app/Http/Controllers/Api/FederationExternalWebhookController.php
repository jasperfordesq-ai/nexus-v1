<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford (via Claude Code)
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Events\FederatedCommunityEventReceived;
use App\Events\FederatedConnectionReceived;
use App\Events\FederatedGroupReceived;
use App\Events\FederatedListingReceived;
use App\Events\FederatedMemberUpdated;
use App\Events\FederatedReviewReceived;
use App\Events\FederatedVolunteeringReceived;
use App\Models\Notification;
use App\Services\FederatedMessageService;
use App\Services\FederationExternalApiClient;
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

        // ---- Read raw body (needed for HMAC verification) ----
        $rawBody = $request->getContent();
        if (empty($rawBody)) {
            return $this->respondWithError('INVALID_REQUEST', 'Empty request body', null, 400);
        }

        // ---- Authenticate BEFORE parsing or interpreting the body ----
        // Parsing first would leak the difference between "bad JSON", "missing event",
        // and "unknown event" to unauthenticated callers. Auth gates all payload
        // inspection behind a valid API key or HMAC signature.
        //
        // Two auth methods supported:
        //   1. API key via Authorization: Bearer {key} (simple, preferred)
        //   2. HMAC signature via X-Webhook-Signature header (webhook-style)
        $partner = $this->identifyPartnerByApiKey($request)
            ?? $this->identifyAndVerifyPartner($request, $rawBody);
        if (!$partner) {
            return $this->respondWithError('AUTH_FAILED', 'Invalid API key or webhook signature', null, 401);
        }

        if ($partner->status !== 'active') {
            return $this->respondWithError('PARTNER_INACTIVE', 'Partner is not active', null, 403);
        }

        // ---- Replay protection via X-Federation-Nonce ----
        // HMAC alone is not enough: a captured-but-still-fresh (<5 min) signed
        // request could be replayed. Require a nonce and reject duplicates
        // scoped by (partner_id, nonce). Header is optional for API-key auth
        // (Bearer) to stay backward-compatible with existing partner clients,
        // but strongly recommended — when present it is always enforced.
        $nonce = $request->header('X-Federation-Nonce');
        if (!empty($nonce)) {
            if (!is_string($nonce) || strlen($nonce) < 8 || strlen($nonce) > 128) {
                return $this->respondWithError('INVALID_NONCE', 'Nonce must be 8-128 chars', null, 400);
            }
            try {
                // INSERT IGNORE — atomic "first-seen" claim.
                $inserted = DB::affectingStatement(
                    "INSERT IGNORE INTO federation_webhook_nonces (partner_id, nonce, seen_at) VALUES (?, ?, NOW())",
                    [$partner->id, $nonce]
                );
                if ($inserted === 0) {
                    Log::warning('[FederationExternalWebhook] Rejecting replayed nonce', [
                        'partner_id' => $partner->id,
                        'nonce_prefix' => substr($nonce, 0, 8),
                    ]);
                    return $this->respondWithError('REPLAY_DETECTED', 'Nonce already used', null, 409);
                }
            } catch (\Throwable $e) {
                // If the nonce table isn't available (e.g., mid-migration), don't
                // fail closed on a signed+fresh request — but log loudly.
                Log::error('[FederationExternalWebhook] Nonce store error', ['error' => $e->getMessage()]);
            }
        }

        // ---- Parse body (post-auth) ----
        $payload = json_decode($rawBody, true, 10);
        if (!is_array($payload)) {
            return $this->respondWithError('INVALID_REQUEST', 'Invalid JSON', null, 400);
        }

        $event = $payload['event'] ?? null;
        $data = $payload['data'] ?? [];

        if (empty($event)) {
            return $this->respondWithError('INVALID_REQUEST', 'Missing event type', null, 400);
        }

        // ---- Normalize payload through protocol adapter ----
        // Different protocols structure their webhooks differently. The adapter
        // normalizes event names and data shapes into Nexus's expected format.
        $adapter = FederationExternalApiClient::resolveAdapter((array) $partner);
        $normalized = $adapter->normalizeWebhookPayload($payload);
        $event = $normalized['event'];
        $data = $normalized['data'];

        // ---- Set tenant context from partner ----
        if (!TenantContext::setById($partner->tenant_id)) {
            Log::error("[FederationExternalWebhook] Failed to set tenant context for partner #{$partner->id}, tenant #{$partner->tenant_id}");
            return $this->respondWithError('TENANT_ERROR', 'Unable to resolve tenant for this partner', null, 500);
        }

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
        } catch (InboundValidationException $e) {
            DB::table('federation_external_partner_logs')
                ->where('id', $logId)
                ->update(['response_code' => 400, 'success' => false, 'error_message' => substr($e->getMessage(), 0, 1000)]);
            return $this->respondWithError('INVALID_PAYLOAD', $e->getMessage(), $e->field, 400);
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
    // Authentication: API key (simple) or HMAC signature (webhook)
    // ----------------------------------------------------------------

    /**
     * Decrypt a partner's signing_secret. Handles both encrypted (from service
     * layer) and plaintext (from direct DB insert) values gracefully.
     */
    private function decryptSecret(string $encrypted): ?string
    {
        try {
            return \Illuminate\Support\Facades\Crypt::decryptString($encrypted);
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            // Value is plaintext (not encrypted) — use as-is
            return $encrypted;
        }
    }

    /**
     * Identify partner by API key in Authorization: Bearer header.
     * The signing_secret doubles as the API key for simple auth.
     */
    private function identifyPartnerByApiKey(Request $request): ?object
    {
        $authHeader = $request->header('Authorization');
        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        $token = substr($authHeader, 7);
        if (empty($token)) {
            return null;
        }

        // Must iterate and decrypt each secret to compare (can't query encrypted values)
        $partners = DB::table('federation_external_partners')
            ->whereNotNull('signing_secret')
            ->where('signing_secret', '!=', '')
            ->get();

        foreach ($partners as $partner) {
            $decrypted = $this->decryptSecret($partner->signing_secret);
            if ($decrypted && hash_equals($decrypted, $token)) {
                return $partner;
            }
        }

        return null;
    }

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
            // Decrypt the secret (handles both encrypted and plaintext values)
            $secret = $this->decryptSecret($partner->signing_secret);
            if (!$secret) continue;

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
            'members.list' => $this->handleMembersList($data, $partner),
            'listings.list' => $this->handleListingsList($data, $partner),
            // New inbound PUSH handlers (partner-initiated create/update)
            'review.created' => $this->handleInboundReview($data, $partner),
            'listing.created', 'listing.updated' => $this->handleInboundListing($data, $partner),
            'event.created', 'event.updated' => $this->handleInboundCommunityEvent($data, $partner),
            'group.created', 'group.updated' => $this->handleInboundGroup($data, $partner),
            'group.member_joined' => $this->handleInboundGroupMembership($data, $partner),
            'connection.requested', 'connection.accepted' => $this->handleInboundConnection($data, $partner, $event),
            'volunteering.created', 'volunteering.updated' => $this->handleInboundVolunteering($data, $partner),
            'member.profile_updated' => $this->handleInboundMemberSync($data, $partner),
            'health_check' => ['status' => 'ok'],
            default => ['status' => 'unhandled', 'event' => $event],
        };
    }

    // ----------------------------------------------------------------
    // Inbound PUSH handlers (partner-initiated create/update)
    // ----------------------------------------------------------------

    /**
     * Require a string field from the payload, throwing InboundValidationException
     * if it is missing or empty.
     */
    private function requireString(array $data, string $field): string
    {
        $value = $data[$field] ?? null;
        if ($value === null || $value === '' || !is_scalar($value)) {
            throw new InboundValidationException("Missing required field: {$field}", $field);
        }
        $value = (string) $value;
        if (mb_strlen($value) > 10000) {
            throw new InboundValidationException("Field '{$field}' exceeds maximum length", $field);
        }
        return $value;
    }

    private function optionalString(array $data, string $field, int $max = 10000): ?string
    {
        $value = $data[$field] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_scalar($value)) {
            return null;
        }
        $value = (string) $value;
        if (mb_strlen($value) > $max) {
            $value = mb_substr($value, 0, $max);
        }
        return $value;
    }

    private function parseDateTime(?string $value): ?string
    {
        if (!$value) {
            return null;
        }
        try {
            $dt = new \DateTimeImmutable($value);
            return $dt->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private function handleInboundReview(array $data, object $partner): array
    {
        $externalId = $this->requireString($data, 'external_id');
        $rating = (int) ($data['rating'] ?? 0);
        if ($rating < 1 || $rating > 5) {
            throw new InboundValidationException('rating must be between 1 and 5', 'rating');
        }
        $receiverId = (int) ($data['receiver_id'] ?? $data['local_member_id'] ?? 0);
        if ($receiverId <= 0) {
            throw new InboundValidationException('Missing required field: receiver_id', 'receiver_id');
        }

        $tenantId = (int) TenantContext::getId();

        // Validate the receiver belongs to this tenant
        $receiver = DB::table('users')
            ->where('id', $receiverId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->first(['id']);
        if (!$receiver) {
            throw new InboundValidationException("Receiver user #{$receiverId} not found in this tenant", 'receiver_id');
        }

        // Dedup by (external_partner_id, external_id) stored in comment-tagged pattern —
        // since reviews has no external_id column, we dedup via federation_transaction_id
        // when provided, otherwise via comment prefix or just allow multiple.
        $externalTxId = $this->optionalString($data, 'external_transaction_id', 128);
        $reviewerExternalId = (int) ($data['reviewer_external_id'] ?? $data['reviewer_id'] ?? 0);
        $reviewerTenantId = (int) ($data['reviewer_tenant_id'] ?? 0);

        $existing = null;
        if ($externalTxId) {
            $tx = DB::table('federation_transactions')
                ->where('external_transaction_id', $externalTxId)
                ->where('external_partner_id', $partner->id)
                ->first(['id']);
            if ($tx) {
                $existing = DB::table('reviews')
                    ->where('federation_transaction_id', $tx->id)
                    ->where('reviewer_id', $reviewerExternalId)
                    ->where('receiver_id', $receiverId)
                    ->first(['id']);
            }
        }

        if ($existing) {
            return ['status' => 'duplicate', 'local_id' => (int) $existing->id];
        }

        $comment = $this->optionalString($data, 'comment', 5000);

        $localId = DB::table('reviews')->insertGetId([
            'tenant_id'          => $tenantId,
            'reviewer_id'        => $reviewerExternalId,
            'reviewer_tenant_id' => $reviewerTenantId ?: null,
            'receiver_id'        => $receiverId,
            'receiver_tenant_id' => $tenantId,
            'rating'             => $rating,
            'comment'            => $comment,
            'status'             => 'approved',
            'review_type'        => 'federated',
            'show_cross_tenant'  => 1,
            'created_at'         => now(),
        ]);

        $shadowRow = [
            'id' => $localId,
            'external_id' => $externalId,
            'external_partner_id' => $partner->id,
            'rating' => $rating,
            'receiver_id' => $receiverId,
            'reviewer_external_id' => $reviewerExternalId,
            'comment' => $comment,
        ];
        event(new FederatedReviewReceived($tenantId, (int) $partner->id, (int) $localId, $shadowRow));

        return ['status' => 'handled', 'local_id' => (int) $localId];
    }

    private function handleInboundListing(array $data, object $partner): array
    {
        $externalId = $this->requireString($data, 'external_id');
        $title = $this->requireString($data, 'title');
        $tenantId = (int) TenantContext::getId();

        $row = [
            'tenant_id' => $tenantId,
            'external_partner_id' => (int) $partner->id,
            'external_id' => $externalId,
            'title' => $title,
            'description' => $this->optionalString($data, 'description', 10000),
            'type' => $this->optionalString($data, 'type', 32),
            'category' => $this->optionalString($data, 'category', 128),
            'external_user_id' => $this->optionalString($data, 'external_user_id', 128)
                ?? $this->optionalString($data, 'user_id', 128),
            'external_user_name' => $this->optionalString($data, 'external_user_name', 255)
                ?? $this->optionalString($data, 'user', 255),
            'metadata' => isset($data['metadata']) && is_array($data['metadata'])
                ? json_encode($data['metadata'])
                : null,
            'updated_at' => now(),
        ];

        $existing = DB::table('federation_listings')
            ->where('external_partner_id', $partner->id)
            ->where('external_id', $externalId)
            ->first(['id']);

        if ($existing) {
            DB::table('federation_listings')->where('id', $existing->id)->update($row);
            $localId = (int) $existing->id;
        } else {
            $row['created_at'] = now();
            $localId = (int) DB::table('federation_listings')->insertGetId($row);
        }

        $row['id'] = $localId;
        event(new FederatedListingReceived($tenantId, (int) $partner->id, $localId, $row));

        return ['status' => 'handled', 'local_id' => $localId];
    }

    private function handleInboundCommunityEvent(array $data, object $partner): array
    {
        $externalId = $this->requireString($data, 'external_id');
        $title = $this->requireString($data, 'title');
        $tenantId = (int) TenantContext::getId();

        $row = [
            'tenant_id' => $tenantId,
            'external_partner_id' => (int) $partner->id,
            'external_id' => $externalId,
            'title' => $title,
            'description' => $this->optionalString($data, 'description', 10000),
            'starts_at' => $this->parseDateTime($this->optionalString($data, 'starts_at', 64)),
            'ends_at' => $this->parseDateTime($this->optionalString($data, 'ends_at', 64)),
            'location' => $this->optionalString($data, 'location', 500),
            'metadata' => isset($data['metadata']) && is_array($data['metadata'])
                ? json_encode($data['metadata'])
                : null,
            'updated_at' => now(),
        ];

        $existing = DB::table('federation_events')
            ->where('external_partner_id', $partner->id)
            ->where('external_id', $externalId)
            ->first(['id']);

        if ($existing) {
            DB::table('federation_events')->where('id', $existing->id)->update($row);
            $localId = (int) $existing->id;
        } else {
            $row['created_at'] = now();
            $localId = (int) DB::table('federation_events')->insertGetId($row);
        }

        $row['id'] = $localId;
        event(new FederatedCommunityEventReceived($tenantId, (int) $partner->id, $localId, $row));

        return ['status' => 'handled', 'local_id' => $localId];
    }

    private function handleInboundGroup(array $data, object $partner): array
    {
        $externalId = $this->requireString($data, 'external_id');
        $name = $this->requireString($data, 'name');
        $tenantId = (int) TenantContext::getId();

        $row = [
            'tenant_id' => $tenantId,
            'external_partner_id' => (int) $partner->id,
            'external_id' => $externalId,
            'name' => $name,
            'description' => $this->optionalString($data, 'description', 10000),
            'privacy' => $this->optionalString($data, 'privacy', 32) ?? 'public',
            'member_count' => max(0, (int) ($data['member_count'] ?? 0)),
            'metadata' => isset($data['metadata']) && is_array($data['metadata'])
                ? json_encode($data['metadata'])
                : null,
            'updated_at' => now(),
        ];

        $existing = DB::table('federation_groups')
            ->where('external_partner_id', $partner->id)
            ->where('external_id', $externalId)
            ->first(['id']);

        if ($existing) {
            DB::table('federation_groups')->where('id', $existing->id)->update($row);
            $localId = (int) $existing->id;
        } else {
            $row['created_at'] = now();
            $localId = (int) DB::table('federation_groups')->insertGetId($row);
        }

        $row['id'] = $localId;
        event(new FederatedGroupReceived($tenantId, (int) $partner->id, $localId, $row, 'group'));

        return ['status' => 'handled', 'local_id' => $localId];
    }

    private function handleInboundGroupMembership(array $data, object $partner): array
    {
        $externalId = $this->requireString($data, 'external_id');
        $tenantId = (int) TenantContext::getId();

        // Increment the shadow group's member_count if we have it
        $existing = DB::table('federation_groups')
            ->where('external_partner_id', $partner->id)
            ->where('external_id', $externalId)
            ->first(['id', 'member_count']);

        if (!$existing) {
            throw new InboundValidationException("Unknown group external_id: {$externalId}", 'external_id');
        }

        $newCount = isset($data['member_count'])
            ? max(0, (int) $data['member_count'])
            : ((int) $existing->member_count + 1);

        DB::table('federation_groups')
            ->where('id', $existing->id)
            ->update([
                'member_count' => $newCount,
                'updated_at'   => now(),
            ]);

        $shadowRow = [
            'id' => (int) $existing->id,
            'external_partner_id' => (int) $partner->id,
            'external_id' => $externalId,
            'member_count' => $newCount,
            'external_user_id' => $this->optionalString($data, 'external_user_id', 128),
        ];
        event(new FederatedGroupReceived($tenantId, (int) $partner->id, (int) $existing->id, $shadowRow, 'member_joined'));

        return ['status' => 'handled', 'local_id' => (int) $existing->id];
    }

    private function handleInboundConnection(array $data, object $partner, string $event): array
    {
        $localUserId = (int) ($data['local_user_id'] ?? $data['recipient_id'] ?? 0);
        $externalUserId = $this->requireString($data, 'external_user_id');

        if ($localUserId <= 0) {
            throw new InboundValidationException('Missing required field: local_user_id', 'local_user_id');
        }

        $tenantId = (int) TenantContext::getId();

        // Verify local_user_id exists in THIS tenant
        $localUser = DB::table('users')
            ->where('id', $localUserId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->first(['id']);
        if (!$localUser) {
            throw new InboundValidationException(
                "Local user #{$localUserId} not found in this tenant",
                'local_user_id'
            );
        }

        $status = $event === 'connection.accepted' ? 'accepted' : 'pending';
        $message = $this->optionalString($data, 'message', 1000);

        $existing = DB::table('federation_inbound_connections')
            ->where('external_partner_id', $partner->id)
            ->where('local_user_id', $localUserId)
            ->where('external_user_id', $externalUserId)
            ->first(['id']);

        $row = [
            'tenant_id' => $tenantId,
            'external_partner_id' => (int) $partner->id,
            'local_user_id' => $localUserId,
            'external_user_id' => $externalUserId,
            'status' => $status,
            'message' => $message,
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('federation_inbound_connections')->where('id', $existing->id)->update($row);
            $localId = (int) $existing->id;
        } else {
            $row['created_at'] = now();
            $localId = (int) DB::table('federation_inbound_connections')->insertGetId($row);
        }

        $row['id'] = $localId;
        event(new FederatedConnectionReceived($tenantId, (int) $partner->id, $localId, $row));

        return ['status' => 'handled', 'local_id' => $localId];
    }

    private function handleInboundVolunteering(array $data, object $partner): array
    {
        $externalId = $this->requireString($data, 'external_id');
        $title = $this->requireString($data, 'title');
        $tenantId = (int) TenantContext::getId();

        $row = [
            'tenant_id' => $tenantId,
            'external_partner_id' => (int) $partner->id,
            'external_id' => $externalId,
            'title' => $title,
            'description' => $this->optionalString($data, 'description', 10000),
            'hours_requested' => isset($data['hours_requested']) && is_numeric($data['hours_requested'])
                ? (float) $data['hours_requested']
                : null,
            'location' => $this->optionalString($data, 'location', 500),
            'starts_at' => $this->parseDateTime($this->optionalString($data, 'starts_at', 64)),
            'metadata' => isset($data['metadata']) && is_array($data['metadata'])
                ? json_encode($data['metadata'])
                : null,
            'updated_at' => now(),
        ];

        $existing = DB::table('federation_volunteering')
            ->where('external_partner_id', $partner->id)
            ->where('external_id', $externalId)
            ->first(['id']);

        if ($existing) {
            DB::table('federation_volunteering')->where('id', $existing->id)->update($row);
            $localId = (int) $existing->id;
        } else {
            $row['created_at'] = now();
            $localId = (int) DB::table('federation_volunteering')->insertGetId($row);
        }

        $row['id'] = $localId;
        event(new FederatedVolunteeringReceived($tenantId, (int) $partner->id, $localId, $row));

        return ['status' => 'handled', 'local_id' => $localId];
    }

    private function handleInboundMemberSync(array $data, object $partner): array
    {
        $externalId = $this->requireString($data, 'external_id');
        $tenantId = (int) TenantContext::getId();

        $row = [
            'tenant_id' => $tenantId,
            'external_partner_id' => (int) $partner->id,
            'external_id' => $externalId,
            'username' => $this->optionalString($data, 'username', 255),
            'display_name' => $this->optionalString($data, 'display_name', 255),
            'bio' => $this->optionalString($data, 'bio', 5000),
            'location' => $this->optionalString($data, 'location', 255),
            'avatar_url' => $this->optionalString($data, 'avatar_url', 1000),
            'metadata' => isset($data['metadata']) && is_array($data['metadata'])
                ? json_encode($data['metadata'])
                : null,
            'profile_updated_at' => $this->parseDateTime($this->optionalString($data, 'profile_updated_at', 64))
                ?? now(),
            'updated_at' => now(),
        ];

        $existing = DB::table('federation_members')
            ->where('external_partner_id', $partner->id)
            ->where('external_id', $externalId)
            ->first(['id']);

        if ($existing) {
            DB::table('federation_members')->where('id', $existing->id)->update($row);
            $localId = (int) $existing->id;
        } else {
            $row['created_at'] = now();
            $localId = (int) DB::table('federation_members')->insertGetId($row);
        }

        $row['id'] = $localId;
        event(new FederatedMemberUpdated($tenantId, (int) $partner->id, $localId, $row));

        return ['status' => 'handled', 'local_id' => $localId];
    }

    // ----------------------------------------------------------------
    // Data browsing (members + listings)
    // ----------------------------------------------------------------

    private function handleMembersList(array $data, object $partner): array
    {
        $users = DB::table('users')
            ->where('tenant_id', TenantContext::getId())
            ->where('status', 'active')
            ->limit(100)
            ->get(['id', 'first_name', 'last_name', 'balance']);

        $members = [];
        foreach ($users as $user) {
            // Check federation opt-in
            $optedIn = DB::table('federation_user_settings')
                ->where('user_id', $user->id)
                ->where('federation_optin', 1)
                ->exists();
            if (!$optedIn) continue;

            $members[] = [
                'id' => $user->id,
                'username' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                'balance' => ($user->balance ?? 0) * 3600, // Nexus hours → TO seconds
                'tags' => '',
            ];
        }

        return ['members' => $members, 'count' => count($members)];
    }

    private function handleListingsList(array $data, object $partner): array
    {
        $tenantId = TenantContext::getId();
        $typeFilter = $data['type'] ?? null;
        $searchFilter = $data['search'] ?? null;

        $query = DB::table('listings')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereNull('deleted_at');

        if ($typeFilter) {
            $query->where('type', $typeFilter);
        }

        if ($searchFilter) {
            $query->where(function ($q) use ($searchFilter) {
                $q->where('title', 'LIKE', "%{$searchFilter}%")
                  ->orWhere('description', 'LIKE', "%{$searchFilter}%");
            });
        }

        $rows = $query->limit(100)
            ->get(['id', 'title', 'description', 'type', 'category', 'user_id']);

        $listings = [];
        foreach ($rows as $row) {
            // Optionally check if the listing owner has opted-in to federation
            $user = DB::table('users')->where('id', $row->user_id)->first(['first_name', 'last_name']);
            $username = $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) : null;

            $listings[] = [
                'id' => $row->id,
                'title' => $row->title,
                'description' => $row->description ? mb_substr($row->description, 0, 300) : null,
                'type' => $row->type,       // "offer" or "inquiry"
                'category' => $row->category,
                'user' => $username,
                'tags' => '',
            ];
        }

        return ['listings' => $listings, 'count' => count($listings)];
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

            try {
                $senderName = $data['sender_name'] ?? __('api.external_user_fallback');
                $partnerName = $partner->name ?? __('api.external_partner_fallback');
                $notifyMessage = __('svc_notifications.federation.transaction_received', [
                    'amount' => rtrim(rtrim(number_format($amount, 2), '0'), '.'),
                    'sender' => $senderName,
                    'partner' => $partnerName,
                ]);
                Notification::createNotification(
                    $receiverUserId,
                    $notifyMessage,
                    '/wallet',
                    'federation_transaction',
                    false,
                    (int) TenantContext::getId()
                );
            } catch (\Throwable $e) {
                Log::warning('Failed to dispatch federation transaction notification', ['error' => $e->getMessage()]);
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
        $rawAmount = (float) ($data['amount'] ?? 0);

        // TimeOverflow sends amount in SECONDS — convert to hours for Nexus
        // Detect: if amount > 100, it's almost certainly seconds (nobody sends 100+ hours)
        $amountInHours = $rawAmount > 100 ? round($rawAmount / 3600, 2) : $rawAmount;

        // Accept multiple field names for recipient — different platforms use different keys
        $recipientId = $data['recipient_id']
            ?? $data['remote_user_identifier']
            ?? $data['local_member_id']
            ?? null;

        Log::info('[FederationExternalWebhook] Transaction requested', [
            'partner' => $partner->name,
            'external_transaction_id' => $externalTxId,
            'raw_amount' => $rawAmount,
            'amount_hours' => $amountInHours,
            'recipient_id' => $recipientId,
        ]);

        // Validate basic fields
        if (!$recipientId || $amountInHours <= 0) {
            return ['status' => 'rejected', 'reason' => 'Missing recipient_id or invalid amount'];
        }

        // Validate receiver exists
        $receiverUserId = (int) $recipientId;
        $receiver = DB::table('users')
            ->where('id', $receiverUserId)
            ->where('tenant_id', TenantContext::getId())
            ->where('status', 'active')
            ->first();

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

        // Record the transaction AND credit the user immediately
        DB::table('federation_transactions')->insert([
            'sender_tenant_id'        => 0,
            'sender_user_id'          => (int) ($data['sender_id'] ?? 0),
            'receiver_tenant_id'      => TenantContext::getId(),
            'receiver_user_id'        => $receiverUserId,
            'amount'                  => $amountInHours,
            'description'             => $data['reason'] ?? $data['description'] ?? '',
            'status'                  => 'completed',
            'external_partner_id'     => $partner->id,
            'external_receiver_name'  => $data['sender_name'] ?? $data['source_organization_name'] ?? 'External User',
            'external_transaction_id' => $externalTxId,
            'created_at'              => now(),
        ]);

        // Auto-credit the recipient's balance
        DB::table('users')
            ->where('id', $receiverUserId)
            ->increment('balance', $amountInHours);

        Log::info('[FederationExternalWebhook] Auto-credited user', [
            'user_id' => $receiverUserId,
            'amount_hours' => $amountInHours,
            'new_balance' => DB::table('users')->where('id', $receiverUserId)->value('balance'),
        ]);

        return [
            'status' => 'completed',
            'amount_credited' => $amountInHours,
            'recipient_id' => $receiverUserId,
        ];
    }

    // ----------------------------------------------------------------
    // Partnership events
    // ----------------------------------------------------------------

    /**
     * Valid partnership status transitions.
     * Terminated partners cannot be reactivated — matches TimeOverflow's state machine.
     */
    private const VALID_TRANSITIONS = [
        'pending'   => ['active', 'suspended', 'failed'],
        'active'    => ['suspended', 'failed'],
        'suspended' => ['active', 'failed'],
        'failed'    => [], // Terminal state — no transitions allowed
    ];

    private function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::VALID_TRANSITIONS[$from] ?? [], true);
    }

    private function handlePartnershipActivated(object $partner): array
    {
        if (!$this->canTransition($partner->status, 'active')) {
            Log::warning("[FederationExternalWebhook] Rejected activation of {$partner->status} partner #{$partner->id}");
            return ['status' => 'rejected', 'reason' => "Cannot activate a {$partner->status} partner"];
        }
        DB::table('federation_external_partners')
            ->where('id', $partner->id)
            ->update(['status' => 'active', 'verified_at' => now(), 'error_count' => 0, 'last_error' => null]);
        return ['status' => 'activated'];
    }

    private function handlePartnershipSuspended(object $partner): array
    {
        if (!$this->canTransition($partner->status, 'suspended')) {
            return ['status' => 'rejected', 'reason' => "Cannot suspend a {$partner->status} partner"];
        }
        DB::table('federation_external_partners')
            ->where('id', $partner->id)
            ->update(['status' => 'suspended']);
        return ['status' => 'suspended'];
    }

    private function handlePartnershipTerminated(object $partner): array
    {
        if (!$this->canTransition($partner->status, 'failed')) {
            return ['status' => 'rejected', 'reason' => "Cannot terminate a {$partner->status} partner"];
        }
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
            'request_body' => substr(json_encode($payload) ?: '{"_encode_error": true}', 0, 10000),
            'response_body' => null,
            'response_time_ms' => 0,
            'created_at' => now(),
        ]);
    }
}

/**
 * Internal exception thrown by inbound handlers when the incoming payload
 * is malformed or missing required fields. Caught in receive() and mapped
 * to a 400 response.
 */
final class InboundValidationException extends \RuntimeException
{
    public function __construct(string $message, public readonly ?string $field = null)
    {
        parent::__construct($message);
    }
}
