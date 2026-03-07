<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services\Identity;

/**
 * OnfidoProvider — Integration with Onfido for identity verification.
 *
 * Onfido provides real-identity verification powered by Atlas AI,
 * covering 2,500+ document types across 195 countries. Supports document
 * checks, facial similarity, and liveness detection.
 *
 * REQUIRED ENV VARS (or per-tenant in provider_config):
 * - ONFIDO_API_TOKEN (live_... or sandbox_...)
 * - ONFIDO_WEBHOOK_SECRET
 *
 * @see https://documentation.onfido.com/
 */
class OnfidoProvider implements IdentityVerificationProviderInterface
{
    private const API_BASE = 'https://api.eu.onfido.com/v3.6';

    public function getSlug(): string
    {
        return 'onfido';
    }

    public function getName(): string
    {
        return 'Onfido';
    }

    public function getSupportedLevels(): array
    {
        return ['document_only', 'document_selfie'];
    }

    public function createSession(int $userId, int $tenantId, string $level, array $metadata = []): array
    {
        $apiToken = $metadata['api_key'] ?? $this->getGlobalApiToken();

        // Step 1: Create applicant
        $applicant = $this->apiRequest('POST', '/applicants', [
            'first_name' => $metadata['first_name'] ?? 'Applicant',
            'last_name' => $metadata['last_name'] ?? (string) $userId,
        ], $apiToken);

        $applicantId = $applicant['id'];

        // Step 2: Create workflow run (Onfido Studio) or check
        $reportNames = ['document'];
        if ($level === 'document_selfie') {
            $reportNames[] = 'facial_similarity_photo';
        }

        // Create SDK token for the embedded flow
        $sdkToken = $this->apiRequest('POST', '/sdk_token', [
            'applicant_id' => $applicantId,
            'referrer' => '*://*/*',
        ], $apiToken);

        return [
            'provider_session_id' => $applicantId,
            'redirect_url' => null,
            'client_token' => $sdkToken['token'] ?? null,
            'expires_at' => null,
            'report_names' => $reportNames,
        ];
    }

    public function getSessionStatus(string $providerSessionId): array
    {
        $apiToken = $this->getGlobalApiToken();

        // List checks for this applicant
        $checks = $this->apiRequest('GET', "/applicants/{$providerSessionId}/checks", [], $apiToken);
        $latestCheck = $checks['checks'][0] ?? null;

        if (!$latestCheck) {
            return [
                'status' => 'created',
                'decision' => null,
                'risk_score' => null,
                'failure_reason' => null,
            ];
        }

        $statusMap = [
            'complete' => $latestCheck['result'] === 'clear' ? 'passed' : 'failed',
            'in_progress' => 'processing',
            'awaiting_applicant' => 'started',
            'withdrawn' => 'cancelled',
        ];

        $status = $statusMap[$latestCheck['status']] ?? 'processing';

        return [
            'status' => $status,
            'decision' => $latestCheck['result'] ?? null,
            'risk_score' => null,
            'failure_reason' => $status === 'failed' ? ($latestCheck['result_uri'] ?? 'Verification check failed') : null,
        ];
    }

    public function handleWebhook(array $payload, array $headers): array
    {
        $resourceType = $payload['payload']['resource_type'] ?? '';
        $action = $payload['payload']['action'] ?? '';
        $object = $payload['payload']['object'] ?? [];

        if ($resourceType === 'check') {
            $statusMap = [
                'check.completed' => $object['result'] === 'clear' ? 'passed' : 'failed',
                'check.started' => 'processing',
                'check.form_opened' => 'started',
                'check.withdrawn' => 'cancelled',
            ];

            $eventKey = $resourceType . '.' . $action;
            $status = $statusMap[$eventKey] ?? 'processing';

            return [
                'provider_session_id' => $object['applicant_id'] ?? '',
                'status' => $status,
                'decision' => $status === 'passed' ? 'approved' : ($status === 'failed' ? 'declined' : null),
                'risk_score' => null,
                'failure_reason' => $status === 'failed' ? 'Identity check did not pass' : null,
                'raw_event_type' => $eventKey,
            ];
        }

        return [
            'provider_session_id' => $object['id'] ?? '',
            'status' => 'processing',
            'decision' => null,
            'risk_score' => null,
            'failure_reason' => null,
            'raw_event_type' => $resourceType . '.' . $action,
        ];
    }

    public function verifyWebhookSignature(string $rawBody, array $headers): bool
    {
        $secret = $this->getGlobalWebhookSecret();
        if (!$secret) {
            return false;
        }

        $signature = $headers['X-SHA2-Signature'] ?? $headers['x-sha2-signature'] ?? '';
        if (!$signature) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expectedSignature, $signature);
    }

    public function cancelSession(string $providerSessionId): bool
    {
        // Onfido doesn't support cancellation of applicants, only checks
        return true;
    }

    public function isAvailable(int $tenantId): bool
    {
        return !empty($this->getGlobalApiToken());
    }

    // ─── Private helpers ─────────────────────────────────────────────────

    private function getGlobalApiToken(): string
    {
        return \Nexus\Core\Env::get('ONFIDO_API_TOKEN') ?: '';
    }

    private function getGlobalWebhookSecret(): string
    {
        return \Nexus\Core\Env::get('ONFIDO_WEBHOOK_SECRET') ?: '';
    }

    private function apiRequest(string $method, string $path, array $params, string $apiToken): array
    {
        $url = self::API_BASE . $path;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Token token=' . $apiToken,
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
            throw new \RuntimeException("Onfido API request failed: {$error}");
        }

        $response = json_decode($responseBody, true);

        if ($httpCode >= 400) {
            $errorMessage = $response['error']['message'] ?? "Onfido API error (HTTP {$httpCode})";
            throw new \RuntimeException($errorMessage);
        }

        return $response ?? [];
    }
}
