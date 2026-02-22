<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\Mailer;
use Nexus\Services\EmailMonitorService;
use Nexus\Services\RedisCache;

class EmailAdminApiController extends BaseApiController
{

    /**
     * GET /api/v2/admin/email/status
     * Returns email provider status, metrics, and health info
     */
    public function status()
    {
        $mailer = new Mailer();
        $providerType = $mailer->getProviderType();

        $response = [
            'provider' => $providerType,
            'health' => EmailMonitorService::getHealthSummary(),
            'redis' => RedisCache::getStats(),
        ];

        // Add Gmail-specific info if enabled
        if ($providerType === 'gmail_api') {
            $circuitBreakerExpiry = RedisCache::get('gmail_oauth_circuit_breaker', null);
            $refreshAttempts = (int) RedisCache::get('gmail_oauth_refresh_attempts', null);
            $failureCount = (int) RedisCache::get('gmail_oauth_failure_count', null);
            $tokenExpiry = RedisCache::get('gmail_oauth_token_expiry', null);

            $response['gmail_api'] = [
                'token_cached' => $tokenExpiry !== null && $tokenExpiry > time(),
                'token_expires_in' => $tokenExpiry ? max(0, $tokenExpiry - time()) : null,
                'circuit_breaker_open' => $circuitBreakerExpiry && time() < $circuitBreakerExpiry,
                'circuit_breaker_expires_in' => $circuitBreakerExpiry ? max(0, $circuitBreakerExpiry - time()) : null,
                'refresh_attempts_this_hour' => $refreshAttempts,
                'failure_count' => $failureCount,
            ];
        }

        return $this->jsonResponse(['data' => $response]);
    }

    /**
     * POST /api/v2/admin/email/test
     * Send a test email to verify configuration
     */
    public function test()
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $to = $input['to'] ?? '';

        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $this->jsonResponse(['error' => 'Valid email address required'], 400);
        }

        $mailer = new Mailer();
        $provider = $mailer->getProviderType();

        $result = $mailer->send(
            $to,
            'Project NEXUS — Email Test',
            '<h2>Email Test</h2>' .
            '<p>This test email was sent via <strong>' . strtoupper($provider) . '</strong> at ' . date('Y-m-d H:i:s') . '.</p>' .
            '<p>If you received this, email delivery is working.</p>'
        );

        if ($result) {
            return $this->jsonResponse(['data' => ['success' => true, 'provider' => $provider, 'to' => $to]]);
        }

        return $this->jsonResponse(['error' => 'Failed to send test email. Check server logs.'], 500);
    }

    /**
     * POST /api/v2/admin/email/test-gmail
     * Test Gmail API connection specifically (regardless of USE_GMAIL_API setting)
     */
    public function testGmail()
    {
        $result = Mailer::testGmailConnection();
        return $this->jsonResponse(['data' => $result]);
    }
}
