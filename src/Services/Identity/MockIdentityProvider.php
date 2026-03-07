<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services\Identity;

/**
 * MockIdentityProvider — Test/development provider for the identity verification system.
 *
 * Simulates verification flows without calling any external service.
 * Configurable behaviour via metadata:
 * - 'mock_result' => 'pass' | 'fail' | 'review' (default: 'pass')
 * - 'mock_delay'  => int seconds before result (default: 0)
 */
class MockIdentityProvider implements IdentityVerificationProviderInterface
{
    public function getSlug(): string
    {
        return 'mock';
    }

    public function getName(): string
    {
        return 'Mock Provider (Testing)';
    }

    public function getSupportedLevels(): array
    {
        return ['document_only', 'document_selfie', 'reusable_digital_id', 'manual_review'];
    }

    public function createSession(int $userId, int $tenantId, string $level, array $metadata = []): array
    {
        $sessionId = 'mock_' . bin2hex(random_bytes(16));
        $mockResult = $metadata['mock_result'] ?? 'pass';

        return [
            'provider_session_id' => $sessionId,
            'redirect_url' => null,
            'client_token' => 'mock_token_' . $sessionId,
            'expires_at' => date('Y-m-d\TH:i:s\Z', time() + 3600),
            'mock_result' => $mockResult,
        ];
    }

    public function getSessionStatus(string $providerSessionId): array
    {
        // Mock provider: parse expected result from session ID prefix or return passed
        // In real usage, RegistrationOrchestrationService stores mock_result in session metadata
        return [
            'status' => 'passed',
            'decision' => 'approved',
            'risk_score' => 0.05,
            'failure_reason' => null,
        ];
    }

    public function handleWebhook(array $payload, array $headers): array
    {
        $sessionId = $payload['session_id'] ?? '';
        $result = $payload['result'] ?? 'passed';

        return [
            'provider_session_id' => $sessionId,
            'status' => $result,
            'decision' => $result === 'passed' ? 'approved' : 'declined',
            'risk_score' => $result === 'passed' ? 0.05 : 0.95,
            'failure_reason' => $result === 'failed' ? 'Mock verification failed (test)' : null,
            'raw_event_type' => 'mock.verification.' . $result,
        ];
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        // Mock provider always trusts webhooks (testing only)
        // In production providers, this verifies HMAC/RSA signatures
        return true;
    }

    public function cancelSession(string $providerSessionId): bool
    {
        return true;
    }

    public function isAvailable(int $tenantId): bool
    {
        // Mock provider is always available
        return true;
    }
}
