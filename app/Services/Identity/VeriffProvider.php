<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services\Identity;

/**
 * VeriffProvider — Integration with Veriff for identity verification.
 *
 * Veriff is a global identity verification platform supporting 230+ countries,
 * 12,000+ document types, with AI-powered verification and human review fallback.
 *
 * REQUIRED ENV VARS (or per-tenant in provider_config):
 * - VERIFF_API_KEY (public key)
 * - VERIFF_API_SECRET (shared secret for HMAC signatures)
 *
 * @see https://developers.veriff.com/
 */
class VeriffProvider implements IdentityVerificationProviderInterface
{
    private const API_BASE = 'https://stationapi.veriff.com/v1';

    public function getSlug(): string
    {
        return 'veriff';
    }

    public function getName(): string
    {
        return 'Veriff';
    }

    public function getSupportedLevels(): array
    {
        return ['document_only', 'document_selfie', 'manual_review'];
    }

    public function createSession(int $userId, int $tenantId, string $level, array $metadata = []): array
    {
        $apiKey = $metadata['api_key'] ?? $this->getGlobalApiKey();

        $frontendUrl = \App\Core\TenantContext::getFrontendUrl();
        $slugPrefix = \App\Core\TenantContext::getSlugPrefix();

        $params = [
            'verification' => [
                'callback' => $frontendUrl . $slugPrefix . '/verify-identity/callback',
                'person' => [
                    'firstName' => $metadata['first_name'] ?? '',
                    'lastName' => $metadata['last_name'] ?? '',
                ],
                'vendorData' => json_encode([
                    'nexus_user_id' => $userId,
                    'nexus_tenant_id' => $tenantId,
                ]),
            ],
        ];

        $response = $this->apiRequest('POST', '/sessions', $params, $apiKey);

        $session = $response['verification'] ?? [];

        return [
            'provider_session_id' => $session['id'] ?? '',
            'redirect_url' => $session['url'] ?? null,
            'client_token' => null,
            'expires_at' => null,
        ];
    }

    public function getSessionStatus(string $providerSessionId): array
    {
        $apiKey = $this->getGlobalApiKey();
        $response = $this->apiRequest('GET', "/sessions/{$providerSessionId}/decision", [], $apiKey);

        $statusMap = [
            'approved' => 'passed',
            'resubmission_requested' => 'failed',
            'declined' => 'failed',
            'expired' => 'expired',
            'abandoned' => 'expired',
            'review' => 'processing',
        ];

        $decision = $response['verification']['status'] ?? 'review';
        $status = $statusMap[$decision] ?? 'processing';

        return [
            'status' => $status,
            'decision' => $decision,
            'risk_score' => isset($response['verification']['riskScore']) ? (float) $response['verification']['riskScore'] : null,
            'failure_reason' => $response['verification']['reason'] ?? null,
        ];
    }

    public function handleWebhook(array $payload, array $headers): array
    {
        $sessionId = $payload['verification']['id'] ?? '';
        $decision = $payload['verification']['status'] ?? '';
        $vendorData = json_decode($payload['verification']['vendorData'] ?? '{}', true);

        $statusMap = [
            'approved' => 'passed',
            'resubmission_requested' => 'failed',
            'declined' => 'failed',
            'expired' => 'expired',
            'abandoned' => 'expired',
        ];

        $status = $statusMap[$decision] ?? 'processing';

        return [
            'provider_session_id' => $sessionId,
            'status' => $status,
            'decision' => $status === 'passed' ? 'approved' : ($status === 'failed' ? 'declined' : null),
            'risk_score' => isset($payload['verification']['riskScore']) ? (float) $payload['verification']['riskScore'] : null,
            'failure_reason' => $payload['verification']['reason'] ?? null,
            'raw_event_type' => 'veriff.decision.' . $decision,
        ];
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $secret = $this->getGlobalApiSecret();
        if (!$secret) {
            return false;
        }

        $signature = $headers['X-HMAC-Signature'] ?? $headers['x-hmac-signature'] ?? '';
        if (!$signature) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expectedSignature, $signature);
    }

    public function cancelSession(string $providerSessionId): bool
    {
        try {
            $this->apiRequest('DELETE', "/sessions/{$providerSessionId}", [], $this->getGlobalApiKey());
            return true;
        } catch (\Throwable $e) {
            error_log("[VeriffProvider] Failed to cancel session {$providerSessionId}: " . $e->getMessage());
            return false;
        }
    }

    public function isAvailable(int $tenantId): bool
    {
        if (!empty($this->getGlobalApiKey()) && !empty($this->getGlobalApiSecret())) {
            return true;
        }
        return TenantProviderCredentialService::hasCredentials($tenantId, $this->getSlug());
    }

    // ─── Private helpers ─────────────────────────────────────────────────

    private function getGlobalApiKey(): string
    {
        return \App\Core\Env::get('VERIFF_API_KEY') ?: '';
    }

    private function getGlobalApiSecret(): string
    {
        return \App\Core\Env::get('VERIFF_API_SECRET') ?: '';
    }

    private function apiRequest(string $method, string $path, array $params, string $apiKey): array
    {
        $url = self::API_BASE . $path;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-AUTH-CLIENT: ' . $apiKey,
            'Content-Type: application/json',
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        curl_setopt($ch, CURLOPT_URL, $url);

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("Veriff API request failed: {$error}");
        }

        $response = json_decode($responseBody, true);

        if ($httpCode >= 400) {
            $errorMessage = $response['message'] ?? "Veriff API error (HTTP {$httpCode})";
            throw new \RuntimeException($errorMessage);
        }

        return $response ?? [];
    }
}
