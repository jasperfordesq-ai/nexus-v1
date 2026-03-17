<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Nexus\Core\ApiErrorCodes;
use App\Services\Identity\IdentityProviderRegistry;
use App\Services\Identity\IdentityVerificationSessionService;
use App\Services\Identity\RegistrationOrchestrationService;
use App\Services\RateLimitService;

/**
 * IdentityWebhookController -- Identity provider webhook handler.
 *
 * Converted from delegation to direct service calls.
 * Legacy: src/Controllers/Api/IdentityWebhookController.php
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
        $this->rateLimitService->increment("webhook:identity:$ip", 60);

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
            error_log("[IdentityWebhook] Signature verification failed for provider '{$providerSlug}' from IP {$ip}");
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
            error_log("[IdentityWebhook] Provider '{$providerSlug}' handleWebhook() failed: " . $e->getMessage());
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
            error_log("[IdentityWebhook] No provider_session_id in result from '{$providerSlug}'");
            // Acknowledge receipt but log the issue
            return $this->respondWithData(['received' => true, 'warning' => 'no_session_match']);
        }

        $session = $this->sessionService->findByProviderSession($providerSlug, $providerSessionId);
        if (!$session) {
            error_log("[IdentityWebhook] Session not found for provider '{$providerSlug}', session '{$providerSessionId}'");
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

        $this->orchestrationService->handleVerificationResult(
            (int) $session['id'],
            $status,
            $result
        );

        // Always return 200 to the webhook provider
        return $this->respondWithData(['received' => true, 'status' => $status]);
    }
}
