<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Mailer;
use Nexus\Core\TenantContext;
use Nexus\Models\EmailSettings;
use Nexus\Services\EmailMonitorService;
use Nexus\Services\RedisCache;

/**
 * AdminEmailController -- Email settings, provider config, test emails, and deliverability status.
 *
 * All methods require admin authentication.
 * Email-sending methods delegate to legacy for Mailer integration.
 */
class AdminEmailController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct() {}

    // =========================================================================
    // Settings
    // =========================================================================

    /** GET /api/v2/admin/email/settings */
    public function getSettings(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $settings = DB::select(
            "SELECT setting_key, setting_value FROM tenant_settings WHERE tenant_id = ? AND setting_key LIKE 'email_%'",
            [$tenantId]
        );

        $result = [];
        foreach ($settings as $s) {
            $result[$s->setting_key] = $s->setting_value;
        }

        return $this->respondWithData($result);
    }

    /** PUT /api/v2/admin/email/settings */
    public function updateSettings(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $data = $this->getAllInput();

        $allowed = ['email_from_name', 'email_from_address', 'email_reply_to', 'email_footer_text'];
        $updated = 0;

        foreach ($data as $key => $value) {
            if (!in_array($key, $allowed, true)) continue;
            DB::statement(
                'INSERT INTO tenant_settings (tenant_id, setting_key, setting_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
                [$tenantId, $key, $value]
            );
            $updated++;
        }

        return $this->respondWithData(['updated' => $updated]);
    }

    // =========================================================================
    // Status & Config
    // =========================================================================

    /** GET /api/v2/admin/email/status */
    public function status(): JsonResponse
    {
        $this->requireAdmin();
        $mailer = new Mailer();
        $providerType = $mailer->getProviderType();

        $response = [
            'provider' => $providerType,
            'health' => EmailMonitorService::getHealthSummary(),
            'redis' => RedisCache::getStats(),
        ];

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

        return $this->respondWithData($response);
    }

    /** GET /api/v2/admin/email/config */
    public function getConfig(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $settings = EmailSettings::getAllForTenant($tenantId);
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
            'platform_default' => ['provider' => $platformProvider, 'description' => 'Uses platform-wide environment configuration'],
            'webhook_url' => rtrim($_ENV['APP_URL'] ?? 'https://api.project-nexus.ie', '/') . '/api/webhooks/sendgrid/events',
        ];

        return $this->respondWithData($response);
    }

    /** PUT /api/v2/admin/email/config */
    public function updateConfig(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $input = $this->getAllInput();

        if (isset($input['email_provider'])) {
            $validProviders = ['platform_default', 'sendgrid', 'gmail_api', 'smtp'];
            if (!in_array($input['email_provider'], $validProviders, true)) {
                return $this->respondWithError('VALIDATION_ERROR', 'Invalid email provider', null, 422);
            }
        }

        $allowedKeys = [
            'email_provider', 'sendgrid_api_key', 'sendgrid_from_email', 'sendgrid_from_name', 'sendgrid_webhook_signing_key',
            'smtp_host', 'smtp_port', 'smtp_user', 'smtp_password', 'smtp_encryption', 'smtp_from_email', 'smtp_from_name',
            'gmail_client_id', 'gmail_client_secret', 'gmail_refresh_token', 'gmail_sender_email', 'gmail_sender_name',
        ];

        $toSave = [];
        foreach ($input as $key => $value) {
            if (!in_array($key, $allowedKeys, true)) continue;
            if (is_string($value) && preg_match('/^\*+.{0,4}$/', $value)) continue;
            $toSave[$key] = $value === null ? null : (string) $value;
        }

        if (empty($toSave)) {
            return $this->respondWithError('VALIDATION_ERROR', 'No valid settings provided', null, 422);
        }

        EmailSettings::setMultiple($tenantId, $toSave);

        return $this->respondWithData(['success' => true, 'saved_keys' => array_keys($toSave)]);
    }

    // =========================================================================
    // Test Emails (email-sending — delegate to legacy for Mailer)
    // =========================================================================

    /** POST /api/v2/admin/email/test */
    public function test(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\EmailAdminApiController::class, 'test');
    }

    /** POST /api/v2/admin/email/test (v1 wrapper) */
    public function testEmail(): JsonResponse
    {
        return $this->test();
    }

    /** POST /api/v2/admin/email/test-gmail */
    public function testGmail(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\EmailAdminApiController::class, 'testGmail');
    }

    /** POST /api/v2/admin/email/test-provider */
    public function testProvider(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\EmailAdminApiController::class, 'testProvider');
    }

    private function delegate(string $legacyClass, string $method, array $params = []): JsonResponse
    {
        $controller = new $legacyClass();
        ob_start();
        $controller->$method(...$params);
        $output = ob_get_clean();
        $status = http_response_code();
        return response()->json(json_decode($output, true) ?: $output, $status ?: 200);
    }
}
