<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * AdminEmailController -- Email settings and test email sending.
 *
 * All methods require admin authentication.
 */
class AdminEmailController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct() {}

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
            if (!in_array($key, $allowed, true)) {
                continue;
            }
            DB::statement(
                'INSERT INTO tenant_settings (tenant_id, setting_key, setting_value) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
                [$tenantId, $key, $value]
            );
            $updated++;
        }

        return $this->respondWithData(['updated' => $updated]);
    }

    /** POST /api/v2/admin/email/test */
    public function testEmail(): JsonResponse
    {
        $this->requireAdmin();
        $this->rateLimit('admin_test_email', 5, 300);

        $to = $this->requireInput('to');

        try {
            Mail::raw('This is a test email from Project NEXUS.', function ($message) use ($to) {
                $message->to($to)->subject('Test Email -- Project NEXUS');
            });
        } catch (\Throwable $e) {
            return $this->respondWithError('EMAIL_FAILED', 'Failed to send test email: ' . $e->getMessage(), null, 500);
        }

        return $this->respondWithData(['sent_to' => $to]);
    }

    /**
     * Delegate to legacy controller via output buffering.
     */
    private function delegate(string $legacyClass, string $method, array $params = []): JsonResponse
    {
        $controller = new $legacyClass();
        ob_start();
        $controller->$method(...$params);
        $output = ob_get_clean();
        $status = http_response_code();
        return response()->json(json_decode($output, true) ?: $output, $status ?: 200);
    }


    public function status(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\EmailAdminApiController::class, 'status');
    }


    public function test(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\EmailAdminApiController::class, 'test');
    }


    public function testGmail(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\EmailAdminApiController::class, 'testGmail');
    }


    public function getConfig(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\EmailAdminApiController::class, 'getConfig');
    }


    public function updateConfig(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\EmailAdminApiController::class, 'updateConfig');
    }


    public function testProvider(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\EmailAdminApiController::class, 'testProvider');
    }

}
