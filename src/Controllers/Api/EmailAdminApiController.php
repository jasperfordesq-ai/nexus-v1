<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\Mailer;
use Nexus\Core\TenantContext;
use Nexus\Models\EmailSettings;
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
        $this->requireAdmin();
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
        $this->requireAdmin();
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
        $this->requireAdmin();
        $result = Mailer::testGmailConnection();
        return $this->jsonResponse(['data' => $result]);
    }

    /**
     * GET /api/v2/admin/email/config
     * Returns tenant email provider settings (API keys are masked)
     */
    public function getConfig(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $settings = EmailSettings::getAllForTenant($tenantId);

        // Determine what the platform default uses
        $platformMailer = new Mailer();
        $platformProvider = $platformMailer->getProviderType();

        $response = [
            'provider' => $settings['email_provider'] ?? 'platform_default',
            'sendgrid' => [
                'api_key_set' => EmailSettings::has($tenantId, 'sendgrid_api_key'),
                'api_key_masked' => EmailSettings::getMasked($tenantId, 'sendgrid_api_key'),
                'from_email' => $settings['sendgrid_from_email'] ?? '',
                'from_name' => $settings['sendgrid_from_name'] ?? '',
            ],
            'smtp' => [
                'host' => $settings['smtp_host'] ?? '',
                'port' => $settings['smtp_port'] ?? '587',
                'user' => $settings['smtp_user'] ?? '',
                'password_set' => EmailSettings::has($tenantId, 'smtp_password'),
                'encryption' => $settings['smtp_encryption'] ?? 'tls',
                'from_email' => $settings['smtp_from_email'] ?? '',
                'from_name' => $settings['smtp_from_name'] ?? '',
            ],
            'gmail_api' => [
                'client_id' => $settings['gmail_client_id'] ?? '',
                'client_secret_set' => EmailSettings::has($tenantId, 'gmail_client_secret'),
                'refresh_token_set' => EmailSettings::has($tenantId, 'gmail_refresh_token'),
                'sender_email' => $settings['gmail_sender_email'] ?? '',
                'sender_name' => $settings['gmail_sender_name'] ?? '',
            ],
            'platform_default' => [
                'provider' => $platformProvider,
                'description' => 'Uses platform-wide environment configuration',
            ],
            'webhook_url' => rtrim($_ENV['APP_URL'] ?? 'https://api.project-nexus.ie', '/') . '/api/webhooks/sendgrid/events',
        ];

        $this->jsonResponse(['data' => $response]);
    }

    /**
     * PUT /api/v2/admin/email/config
     * Update tenant email provider settings
     */
    public function updateConfig(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $input = $this->getAllInput();

        // Validate provider value
        if (isset($input['email_provider'])) {
            $validProviders = ['platform_default', 'sendgrid', 'gmail_api', 'smtp'];
            if (!in_array($input['email_provider'], $validProviders, true)) {
                $this->respondWithError('VALIDATION_ERROR', 'Invalid email provider. Must be one of: ' . implode(', ', $validProviders), null, 422);
                return;
            }
        }

        $allowedKeys = [
            'email_provider',
            'sendgrid_api_key', 'sendgrid_from_email', 'sendgrid_from_name',
            'sendgrid_webhook_signing_key',
            'smtp_host', 'smtp_port', 'smtp_user', 'smtp_password', 'smtp_encryption',
            'smtp_from_email', 'smtp_from_name',
            'gmail_client_id', 'gmail_client_secret', 'gmail_refresh_token',
            'gmail_sender_email', 'gmail_sender_name',
        ];

        $toSave = [];
        foreach ($input as $key => $value) {
            if (!in_array($key, $allowedKeys, true)) {
                continue;
            }

            // Skip masked values (user didn't change the secret)
            if (is_string($value) && preg_match('/^\*+.{0,4}$/', $value)) {
                continue;
            }

            $toSave[$key] = $value === null ? null : (string) $value;
        }

        if (empty($toSave)) {
            $this->respondWithError('VALIDATION_ERROR', 'No valid settings provided', null, 422);
            return;
        }

        EmailSettings::setMultiple($tenantId, $toSave);
        $this->jsonResponse(['data' => ['success' => true, 'saved_keys' => array_keys($toSave)]]);
    }

    /**
     * POST /api/v2/admin/email/test-provider
     * Send a test email using the tenant's configured provider
     */
    public function testProvider(): void
    {
        $this->requireAdmin();
        $input = $this->getAllInput();
        $to = $input['to'] ?? '';

        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->respondWithError('VALIDATION_ERROR', 'Valid email address required', null, 400);
            return;
        }

        $mailer = Mailer::forCurrentTenant();
        $provider = $mailer->getProviderType();

        $result = $mailer->send(
            $to,
            'Project NEXUS — Email Provider Test',
            '<h2>Email Provider Test</h2>' .
            '<p>Sent via <strong>' . htmlspecialchars(strtoupper($provider)) . '</strong> at ' . date('Y-m-d H:i:s') . '.</p>' .
            '<p>Tenant-specific configuration is working correctly.</p>'
        );

        if ($result) {
            $this->jsonResponse(['data' => ['success' => true, 'provider' => $provider, 'to' => $to]]);
        } else {
            $this->jsonResponse(['error' => 'Failed to send test email via ' . $provider . '. Check server logs.'], 500);
        }
    }
}
