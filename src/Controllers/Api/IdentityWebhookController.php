<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\ApiErrorCodes;
use Nexus\Services\Identity\IdentityProviderRegistry;
use Nexus\Services\Identity\IdentityVerificationSessionService;
use Nexus\Services\Identity\RegistrationOrchestrationService;

/**
 * IdentityWebhookController — Receives webhooks from identity verification providers.
 *
 * Endpoint: POST /api/v2/webhooks/identity/{provider_slug}
 *
 * This endpoint is PUBLIC (no auth token required) but protected by:
 * 1. Provider-specific webhook signature verification
 * 2. Rate limiting
 * 3. Provider slug validation
 */
class IdentityWebhookController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * POST /api/v2/webhooks/identity/{provider_slug}
     *
     * Process an incoming webhook from an identity verification provider.
     */
    public function handleWebhook(): void
    {
        // Rate limit webhooks (generous but protective)
        $ip = \Nexus\Core\ClientIp::get();
        if (\Nexus\Services\RateLimitService::check("webhook:identity:$ip", 60, 60)) {
            header('Retry-After: 60');
            $this->respondWithError(ApiErrorCodes::RATE_LIMIT_EXCEEDED, 'Too many webhook requests', null, 429);
        }
        \Nexus\Services\RateLimitService::increment("webhook:identity:$ip", 60);

        // Extract provider slug from URL
        $providerSlug = $this->getRouteParam('provider_slug');
        if (!$providerSlug || !IdentityProviderRegistry::has($providerSlug)) {
            $this->respondWithError(ApiErrorCodes::NOT_FOUND, 'Unknown identity provider', null, 404);
        }

        $provider = IdentityProviderRegistry::get($providerSlug);

        // Get raw body for signature verification
        $rawBody = file_get_contents('php://input');
        $headers = getallheaders() ?: [];

        // Verify webhook signature
        if (!$provider->verifyWebhookSignature($rawBody, $headers)) {
            error_log("[IdentityWebhook] Signature verification failed for provider '{$providerSlug}' from IP {$ip}");
            $this->respondWithError(ApiErrorCodes::FORBIDDEN, 'Invalid webhook signature', null, 403);
        }

        // Parse payload
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_INVALID_FORMAT, 'Invalid webhook payload', null, 400);
        }

        // Process through provider adapter
        try {
            $result = $provider->handleWebhook($payload, $headers);
        } catch (\Throwable $e) {
            error_log("[IdentityWebhook] Provider '{$providerSlug}' handleWebhook() failed: " . $e->getMessage());
            $this->respondWithError(ApiErrorCodes::SERVER_INTERNAL_ERROR, 'Webhook processing failed', null, 500);
        }

        // Find the matching session
        $providerSessionId = $result['provider_session_id'] ?? '';
        if (empty($providerSessionId)) {
            error_log("[IdentityWebhook] No provider_session_id in result from '{$providerSlug}'");
            // Acknowledge receipt but log the issue
            $this->respondWithData(['received' => true, 'warning' => 'no_session_match']);
            return;
        }

        $session = IdentityVerificationSessionService::findByProviderSession($providerSlug, $providerSessionId);
        if (!$session) {
            error_log("[IdentityWebhook] Session not found for provider '{$providerSlug}', session '{$providerSessionId}'");
            // Acknowledge receipt — the session may have been cancelled
            $this->respondWithData(['received' => true, 'warning' => 'session_not_found']);
            return;
        }

        // Route to orchestration service
        $status = $result['status'] ?? 'processing';
        RegistrationOrchestrationService::handleVerificationResult(
            (int) $session['id'],
            $status,
            $result
        );

        // Always return 200 to the webhook provider
        $this->respondWithData(['received' => true, 'status' => $status]);
    }
}
