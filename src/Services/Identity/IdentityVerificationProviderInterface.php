<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services\Identity;

/**
 * IdentityVerificationProviderInterface
 *
 * All identity verification providers (Stripe Identity, Veriff, Jumio, etc.)
 * implement this contract. The Registration Orchestration Service uses this
 * interface to remain provider-agnostic.
 *
 * Providers may support hosted flows (redirect to provider), embedded SDKs
 * (client token), webhook callbacks, and/or polling for results.
 */
interface IdentityVerificationProviderInterface
{
    /**
     * Unique provider slug used in DB and routing (e.g. 'stripe_identity', 'veriff', 'mock').
     */
    public function getSlug(): string;

    /**
     * Human-readable provider name (e.g. 'Stripe Identity', 'Veriff').
     */
    public function getName(): string;

    /**
     * List of verification levels this provider supports.
     *
     * @return string[] e.g. ['document_only', 'document_selfie']
     */
    public function getSupportedLevels(): array;

    /**
     * Create a verification session with the provider.
     *
     * Returns an array with at minimum:
     * - 'provider_session_id' => string  (external session ID)
     * - 'redirect_url'        => ?string (for hosted/redirect flows)
     * - 'client_token'        => ?string (for embedded SDK flows)
     * - 'expires_at'          => ?string (ISO 8601 expiry)
     *
     * @param int    $userId    The NEXUS user ID
     * @param int    $tenantId  The tenant ID
     * @param string $level     Verification level (document_only, document_selfie, etc.)
     * @param array  $metadata  Provider-specific options (e.g. allowed_countries)
     * @return array Session data
     * @throws \RuntimeException If session creation fails
     */
    public function createSession(int $userId, int $tenantId, string $level, array $metadata = []): array;

    /**
     * Check the current status of a verification session (for polling).
     *
     * Returns an array with at minimum:
     * - 'status'        => string  ('created'|'started'|'processing'|'passed'|'failed'|'expired'|'cancelled')
     * - 'decision'      => ?string (provider's decision if available)
     * - 'risk_score'    => ?float
     * - 'failure_reason' => ?string
     *
     * @param string $providerSessionId External session ID
     * @return array Status data
     */
    public function getSessionStatus(string $providerSessionId): array;

    /**
     * Process an incoming webhook payload from the provider.
     *
     * Returns a normalized result array:
     * - 'provider_session_id' => string
     * - 'status'              => string ('passed'|'failed'|'processing'|'expired')
     * - 'decision'            => ?string
     * - 'risk_score'          => ?float
     * - 'failure_reason'      => ?string
     * - 'raw_event_type'      => string (provider's event type for logging)
     *
     * @param array $payload Decoded webhook body
     * @param array $headers HTTP headers for context
     * @return array Normalized result
     */
    public function handleWebhook(array $payload, array $headers): array;

    /**
     * Verify the webhook signature to ensure authenticity.
     *
     * @param string $rawBody   Raw HTTP request body
     * @param array  $headers   HTTP headers
     * @return bool True if signature is valid
     */
    public function verifyWebhookSignature(string $rawBody, array $headers): bool;

    /**
     * Cancel a pending verification session.
     *
     * @param string $providerSessionId External session ID
     * @return bool True if successfully cancelled
     */
    public function cancelSession(string $providerSessionId): bool;

    /**
     * Check if this provider is properly configured and available for a tenant.
     *
     * @param int $tenantId
     * @return bool
     */
    public function isAvailable(int $tenantId): bool;
}
