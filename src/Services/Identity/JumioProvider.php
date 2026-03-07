<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services\Identity;

/**
 * JumioProvider — Integration with Jumio for identity verification.
 *
 * Jumio provides AI-powered identity verification with liveness detection,
 * covering 5,000+ document types across 200+ countries.
 *
 * REQUIRED ENV VARS (or per-tenant in provider_config):
 * - JUMIO_API_TOKEN
 * - JUMIO_API_SECRET
 *
 * @see https://docs.jumio.com/
 */
class JumioProvider implements IdentityVerificationProviderInterface
{
    private const API_BASE = 'https://account.amer-1.jumio.ai/api/v1';

    public function getSlug(): string
    {
        return 'jumio';
    }

    public function getName(): string
    {
        return 'Jumio';
    }

    public function getSupportedLevels(): array
    {
        return ['document_only', 'document_selfie'];
    }

    public function createSession(int $userId, int $tenantId, string $level, array $metadata = []): array
    {
        $apiToken = $metadata['api_key'] ?? $this->getGlobalApiToken();
        $apiSecret = $metadata['webhook_secret'] ?? $this->getGlobalApiSecret();

        $frontendUrl = \Nexus\Core\TenantContext::getFrontendUrl();
        $slugPrefix = \Nexus\Core\TenantContext::getSlugPrefix();

        $params = [
            'customerInternalReference' => "nexus_{$tenantId}_{$userId}",
            'userReference' => "user_{$userId}",
            'callbackUrl' => rtrim(\Nexus\Core\Env::get('APP_URL') ?: '', '/') . '/api/v2/webhooks/identity/jumio',
            'workflowDefinition' => [
                'key' => $level === 'document_selfie' ? 10 : 2, // 10=ID+Selfie, 2=ID only
            ],
        ];

        $response = $this->apiRequest('POST', '/accounts', $params, $apiToken, $apiSecret);

        return [
            'provider_session_id' => $response['account']['id'] ?? '',
            'redirect_url' => $response['web']['href'] ?? null,
            'client_token' => $response['sdk']['token'] ?? null,
            'expires_at' => null,
        ];
    }

    public function getSessionStatus(string $providerSessionId): array
    {
        $response = $this->apiRequest(
            'GET',
            "/accounts/{$providerSessionId}",
            [],
            $this->getGlobalApiToken(),
            $this->getGlobalApiSecret()
        );

        $statusMap = [
            'APPROVED' => 'passed',
            'REJECTED' => 'failed',
            'PENDING' => 'processing',
            'EXPIRED' => 'expired',
        ];

        $decision = $response['decision']['type'] ?? 'PENDING';
        $status = $statusMap[$decision] ?? 'processing';

        return [
            'status' => $status,
            'decision' => $decision,
            'risk_score' => isset($response['decision']['riskScore']) ? (float) $response['decision']['riskScore'] : null,
            'failure_reason' => $response['decision']['details']['label'] ?? null,
        ];
    }

    public function handleWebhook(array $payload, array $headers): array
    {
        $accountId = $payload['accountId'] ?? '';
        $decision = $payload['decision']['type'] ?? '';

        $statusMap = [
            'APPROVED' => 'passed',
            'REJECTED' => 'failed',
            'EXPIRED' => 'expired',
            'NOT_EXECUTED' => 'failed',
        ];

        $status = $statusMap[$decision] ?? 'processing';

        return [
            'provider_session_id' => $accountId,
            'status' => $status,
            'decision' => $status === 'passed' ? 'approved' : ($status === 'failed' ? 'declined' : null),
            'risk_score' => isset($payload['decision']['riskScore']) ? (float) $payload['decision']['riskScore'] : null,
            'failure_reason' => $payload['decision']['details']['label'] ?? null,
            'raw_event_type' => 'jumio.workflow.' . strtolower($decision),
        ];
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $secret = $this->getGlobalApiSecret();
        if (!$secret) {
            return false;
        }

        $signature = $headers['Jumio-Signature'] ?? $headers['jumio-signature'] ?? '';
        if (!$signature) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expectedSignature, $signature);
    }

    public function cancelSession(string $providerSessionId): bool
    {
        // Jumio doesn't support explicit session cancellation
        return true;
    }

    public function isAvailable(int $tenantId): bool
    {
        return !empty($this->getGlobalApiToken()) && !empty($this->getGlobalApiSecret());
    }

    // ─── Private helpers ─────────────────────────────────────────────────

    private function getGlobalApiToken(): string
    {
        return \Nexus\Core\Env::get('JUMIO_API_TOKEN') ?: '';
    }

    private function getGlobalApiSecret(): string
    {
        return \Nexus\Core\Env::get('JUMIO_API_SECRET') ?: '';
    }

    private function apiRequest(string $method, string $path, array $params, string $apiToken, string $apiSecret): array
    {
        $url = self::API_BASE . $path;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . base64_encode($apiToken . ':' . $apiSecret),
            'Content-Type: application/json',
            'User-Agent: NexusPlatform/1.0',
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
            throw new \RuntimeException("Jumio API request failed: {$error}");
        }

        $response = json_decode($responseBody, true);

        if ($httpCode >= 400) {
            $errorMessage = $response['message'] ?? "Jumio API error (HTTP {$httpCode})";
            throw new \RuntimeException($errorMessage);
        }

        return $response ?? [];
    }
}
