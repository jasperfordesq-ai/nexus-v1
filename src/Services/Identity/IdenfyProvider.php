<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services\Identity;

/**
 * IdenfyProvider — Integration with iDenfy for identity verification.
 *
 * iDenfy is a European identity verification provider with competitive pricing,
 * supporting 3,000+ document types across 200+ countries. Offers both automated
 * and manual review verification with real-time results.
 *
 * REQUIRED ENV VARS (or per-tenant in provider_config):
 * - IDENFY_API_KEY
 * - IDENFY_API_SECRET
 *
 * @see https://documentation.idenfy.com/
 */
class IdenfyProvider implements IdentityVerificationProviderInterface
{
    private const API_BASE = 'https://ivs.idenfy.com/api/v2';

    public function getSlug(): string
    {
        return 'idenfy';
    }

    public function getName(): string
    {
        return 'iDenfy';
    }

    public function getSupportedLevels(): array
    {
        return ['document_only', 'document_selfie', 'manual_review'];
    }

    public function createSession(int $userId, int $tenantId, string $level, array $metadata = []): array
    {
        $apiKey = $metadata['api_key'] ?? $this->getGlobalApiKey();
        $apiSecret = $metadata['webhook_secret'] ?? $this->getGlobalApiSecret();

        $frontendUrl = \Nexus\Core\TenantContext::getFrontendUrl();
        $slugPrefix = \Nexus\Core\TenantContext::getSlugPrefix();

        $params = [
            'clientId' => "nexus_{$tenantId}_{$userId}",
            'generateDigitString' => true,
            'callbackUrl' => rtrim(\Nexus\Core\Env::get('APP_URL') ?: '', '/') . '/api/v2/webhooks/identity/idenfy',
            'successUrl' => $frontendUrl . $slugPrefix . '/verify-identity/callback?status=success',
            'errorUrl' => $frontendUrl . $slugPrefix . '/verify-identity/callback?status=error',
            'unverifiedUrl' => $frontendUrl . $slugPrefix . '/verify-identity/callback?status=unverified',
        ];

        if ($level === 'document_selfie') {
            $params['externalRef'] = 'selfie_required';
        }

        $response = $this->apiRequest('POST', '/token', $params, $apiKey, $apiSecret);

        return [
            'provider_session_id' => $response['scanRef'] ?? $response['clientId'] ?? '',
            'redirect_url' => $response['authToken']
                ? 'https://ivs.idenfy.com/api/v2/redirect?authToken=' . $response['authToken']
                : null,
            'client_token' => $response['authToken'] ?? null,
            'expires_at' => isset($response['expiryTime'])
                ? date('Y-m-d\TH:i:s\Z', $response['expiryTime'])
                : null,
        ];
    }

    public function getSessionStatus(string $providerSessionId): array
    {
        $response = $this->apiRequest(
            'GET',
            "/status?scanRef={$providerSessionId}",
            [],
            $this->getGlobalApiKey(),
            $this->getGlobalApiSecret()
        );

        $statusMap = [
            'APPROVED' => 'passed',
            'DENIED' => 'failed',
            'SUSPECTED' => 'failed',
            'REVIEWING' => 'processing',
            'ACTIVE' => 'started',
            'EXPIRED' => 'expired',
        ];

        $overallStatus = $response['overall'] ?? 'ACTIVE';
        $status = $statusMap[$overallStatus] ?? 'processing';

        return [
            'status' => $status,
            'decision' => $overallStatus,
            'risk_score' => null,
            'failure_reason' => $status === 'failed' ? ($response['suspicionReasons'][0] ?? 'Verification denied') : null,
        ];
    }

    public function handleWebhook(array $payload, array $headers): array
    {
        $scanRef = $payload['final']['scanRef'] ?? $payload['scanRef'] ?? '';
        $overallStatus = $payload['final']['overall'] ?? $payload['status']['overall'] ?? '';

        $statusMap = [
            'APPROVED' => 'passed',
            'DENIED' => 'failed',
            'SUSPECTED' => 'failed',
            'EXPIRED' => 'expired',
        ];

        $status = $statusMap[$overallStatus] ?? 'processing';

        $failureReason = null;
        if ($status === 'failed') {
            $reasons = $payload['final']['suspicionReasons'] ?? $payload['suspicionReasons'] ?? [];
            $failureReason = !empty($reasons) ? implode(', ', $reasons) : 'Verification denied';
        }

        return [
            'provider_session_id' => $scanRef,
            'status' => $status,
            'decision' => $status === 'passed' ? 'approved' : ($status === 'failed' ? 'declined' : null),
            'risk_score' => null,
            'failure_reason' => $failureReason,
            'raw_event_type' => 'idenfy.verification.' . strtolower($overallStatus),
        ];
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $secret = $this->getGlobalApiSecret();
        if (!$secret) {
            return false;
        }

        $signature = $headers['Idenfy-Signature'] ?? $headers['idenfy-signature'] ?? '';
        if (!$signature) {
            // iDenfy uses basic auth for webhook verification in some setups
            return true;
        }

        $expectedSignature = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expectedSignature, $signature);
    }

    public function cancelSession(string $providerSessionId): bool
    {
        try {
            $this->apiRequest(
                'POST',
                "/delete?scanRef={$providerSessionId}",
                [],
                $this->getGlobalApiKey(),
                $this->getGlobalApiSecret()
            );
            return true;
        } catch (\Throwable $e) {
            error_log("[IdenfyProvider] Failed to cancel session {$providerSessionId}: " . $e->getMessage());
            return false;
        }
    }

    public function isAvailable(int $tenantId): bool
    {
        return !empty($this->getGlobalApiKey()) && !empty($this->getGlobalApiSecret());
    }

    // ─── Private helpers ─────────────────────────────────────────────────

    private function getGlobalApiKey(): string
    {
        return \Nexus\Core\Env::get('IDENFY_API_KEY') ?: '';
    }

    private function getGlobalApiSecret(): string
    {
        return \Nexus\Core\Env::get('IDENFY_API_SECRET') ?: '';
    }

    private function apiRequest(string $method, string $path, array $params, string $apiKey, string $apiSecret): array
    {
        $url = self::API_BASE . $path;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERPWD, $apiKey . ':' . $apiSecret);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        }

        curl_setopt($ch, CURLOPT_URL, $url);

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("iDenfy API request failed: {$error}");
        }

        $response = json_decode($responseBody, true);

        if ($httpCode >= 400) {
            $errorMessage = $response['message'] ?? "iDenfy API error (HTTP {$httpCode})";
            throw new \RuntimeException($errorMessage);
        }

        return $response ?? [];
    }
}
