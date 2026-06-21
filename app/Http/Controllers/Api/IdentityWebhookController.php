<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Core\ApiErrorCodes;
use App\Core\TenantContext;
use App\Services\Identity\IdentityProviderRegistry;
use App\Services\Identity\IdentityVerificationSessionService;
use App\Services\Identity\RegistrationOrchestrationService;
use App\Services\NotificationDispatcher;
use App\Services\RateLimitService;

/**
 * IdentityWebhookController -- Identity provider webhook handler.
 */
class IdentityWebhookController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly IdentityProviderRegistry $identityProviderRegistry,
        private readonly IdentityVerificationSessionService $sessionService,
        private readonly RateLimitService $rateLimitService,
        private readonly RegistrationOrchestrationService $orchestrationService,
    ) {}

    /** POST webhooks/identity/{provider_slug} */
    public function handleWebhook(): JsonResponse
    {
        // Rate limit webhooks (generous but protective)
        $ip = \App\Core\ClientIp::get();
        if ($this->rateLimitService->check("webhook:identity:$ip", 60, 60)) {
            return $this->respondWithError(
                ApiErrorCodes::RATE_LIMIT_EXCEEDED,
                'Too many webhook requests',
                null,
                429
            );
        }

        // Extract provider slug from route
        $providerSlug = request()->route('provider_slug');
        if (!$providerSlug || !$this->identityProviderRegistry->has($providerSlug)) {
            return $this->respondWithError(
                ApiErrorCodes::RESOURCE_NOT_FOUND,
                'Unknown identity provider',
                null,
                404
            );
        }

        $provider = $this->identityProviderRegistry->get($providerSlug);

        // Get raw body for signature verification — use request()->getContent() instead of php://input
        $rawBody = request()->getContent();
        $headers = getallheaders() ?: [];

        // Verify webhook signature
        if (!$provider->verifyWebhookSignature($rawBody, $headers)) {
            \Illuminate\Support\Facades\Log::warning("[IdentityWebhook] Signature verification failed for provider '{$providerSlug}' from IP {$ip}");
            return $this->respondWithError(
                ApiErrorCodes::FORBIDDEN,
                'Invalid webhook signature',
                null,
                403
            );
        }

        // Parse payload
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return $this->respondWithError(
                ApiErrorCodes::VALIDATION_INVALID_FORMAT,
                'Invalid webhook payload',
                null,
                400
            );
        }

        // Process through provider adapter
        try {
            $result = $provider->handleWebhook($payload, $headers);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("[IdentityWebhook] Provider '{$providerSlug}' handleWebhook() failed: " . $e->getMessage());
            return $this->respondWithError(
                ApiErrorCodes::SERVER_INTERNAL_ERROR,
                'Webhook processing failed',
                null,
                500
            );
        }

        // Find the matching session
        $providerSessionId = $result['provider_session_id'] ?? '';
        if (empty($providerSessionId)) {
            \Illuminate\Support\Facades\Log::warning("[IdentityWebhook] No provider_session_id in result from '{$providerSlug}'");
            // Acknowledge receipt but log the issue
            return $this->respondWithData(['received' => true, 'warning' => 'no_session_match']);
        }

        $session = $this->sessionService->findByProviderSession($providerSlug, $providerSessionId);
        if (!$session) {
            \Illuminate\Support\Facades\Log::warning("[IdentityWebhook] Session not found for provider '{$providerSlug}', session '{$providerSessionId}'");
            // Acknowledge receipt — the session may have been cancelled
            return $this->respondWithData(['received' => true, 'warning' => 'session_not_found']);
        }

        // Route to orchestration service
        $status = $result['status'] ?? 'processing';

        // Idempotency check: skip if this session already has this terminal status
        $currentStatus = $session['status'] ?? '';
        $isTerminal = in_array($currentStatus, ['passed', 'failed', 'expired', 'cancelled'], true);
        if ($isTerminal && $currentStatus === $status) {
            // Duplicate webhook — acknowledge but skip processing
            return $this->respondWithData(['received' => true, 'status' => $status, 'duplicate' => true]);
        }

        $sessionUserId   = (int) $session['user_id'];
        $sessionTenantId = (int) $session['tenant_id'];

        // Ensure TenantContext is set for email sending
        if (TenantContext::getId() !== $sessionTenantId) {
            TenantContext::setById($sessionTenantId);
        }

        // H4: a "passed" document is not enough — the verified name/DOB must match
        // the user's profile, exactly as the poll + cron paths already enforce.
        // Fetch the verified outputs and downgrade a mismatched pass to 'failed'
        // BEFORE recording the result, so a mismatch never grants the trust badge
        // (and the user/admins get a single, consistent "failed" notification).
        if ($status === 'passed') {
            try {
                $verifiedOutputs = $provider->getSessionStatus($providerSessionId);
            } catch (\Throwable $e) {
                // Couldn't fetch verified outputs — fall back to the webhook result
                // (carries no name/DOB, so the check is a no-op, matching old behaviour).
                $verifiedOutputs = $result;
            }

            $mismatch = OptionalIdentityVerificationController::checkNameDobMismatch(
                $sessionUserId,
                $sessionTenantId,
                is_array($verifiedOutputs) ? $verifiedOutputs : []
            );

            if ($mismatch !== null) {
                $status = 'failed';
                $result['failure_reason'] = $mismatch;
            }
        }

        $this->orchestrationService->handleVerificationResult(
            (int) $session['id'],
            $status,
            $result
        );

        // Auto-grant the ID Verified badge ONLY when verification passed AND the
        // name/DOB matched (a mismatch was already downgraded to 'failed' above).
        if ($status === 'passed') {
            try {
                OptionalIdentityVerificationController::grantIdVerifiedBadge(
                    $sessionUserId,
                    $sessionTenantId
                );
            } catch (\Throwable $e) {
                // Non-critical — log but don't fail the webhook
                \Illuminate\Support\Facades\Log::warning("[IdentityWebhook] Failed to grant id_verified badge: " . $e->getMessage());
            }
        }

        // Always return 200 to the webhook provider
        return $this->respondWithData(['received' => true, 'status' => $status]);
    }
}
