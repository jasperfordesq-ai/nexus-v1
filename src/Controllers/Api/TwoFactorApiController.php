<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Controllers\Api;

use Nexus\Services\TotpService;

/**
 * V2 Two-Factor Authentication API Controller
 *
 * Bearer-token-compatible endpoints for managing 2FA from the React SPA.
 *
 * Endpoints:
 * - GET  /api/v2/auth/2fa/status  - Check 2FA status
 * - POST /api/v2/auth/2fa/setup   - Initialize 2FA setup (generate QR code)
 * - POST /api/v2/auth/2fa/verify  - Verify code to complete setup
 * - POST /api/v2/auth/2fa/disable - Disable 2FA (requires password)
 */
class TwoFactorApiController extends BaseApiController
{
    /**
     * GET /api/v2/auth/2fa/status
     * Get 2FA status for the authenticated user
     */
    public function status(): void
    {
        $userId = $this->getUserId();

        $this->respondWithData([
            'enabled' => TotpService::isEnabled($userId),
            'setup_required' => TotpService::isSetupRequired($userId),
            'backup_codes_remaining' => TotpService::getBackupCodeCount($userId),
        ]);
    }

    /**
     * POST /api/v2/auth/2fa/setup
     * Initialize 2FA setup — generates secret + QR code
     *
     * Response: { qr_code_url: string (data URI), secret: string, backup_codes: [] }
     */
    public function setup(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('2fa_setup', 5, 300);

        if (TotpService::isEnabled($userId)) {
            $this->respondWithError('ALREADY_ENABLED', '2FA is already enabled on your account', null, 409);
        }

        try {
            $result = TotpService::initializeSetup($userId);

            // Convert raw SVG to data URI for use in <img src="...">
            $svgDataUri = 'data:image/svg+xml;base64,' . base64_encode($result['qr_code']);

            $this->respondWithData([
                'qr_code_url' => $svgDataUri,
                'secret' => $result['secret'],
                'backup_codes' => [],
            ]);
        } catch (\Exception $e) {
            $this->respondWithError('SETUP_FAILED', 'Failed to initialize 2FA setup', null, 500);
        }
    }

    /**
     * POST /api/v2/auth/2fa/verify
     * Verify TOTP code to complete 2FA setup
     *
     * Request Body: { "code": "123456" }
     * Response: { backup_codes: string[] }
     */
    public function verify(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('2fa_verify', 10, 300);

        $data = $this->getAllInput();
        $code = trim($data['code'] ?? '');

        if (empty($code)) {
            $this->respondWithError('VALIDATION_ERROR', 'Verification code is required', 'code', 400);
        }

        $result = TotpService::completeSetup($userId, $code);

        if (!$result['success']) {
            $this->respondWithError('VERIFICATION_FAILED', $result['error'] ?? 'Invalid verification code', 'code', 400);
        }

        $this->respondWithData([
            'backup_codes' => $result['backup_codes'] ?? [],
        ]);
    }

    /**
     * POST /api/v2/auth/2fa/disable
     * Disable 2FA (requires password verification)
     *
     * Request Body: { "password": "..." }
     */
    public function disable(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('2fa_disable', 3, 3600);

        $data = $this->getAllInput();
        $password = $data['password'] ?? '';

        if (empty($password)) {
            $this->respondWithError('VALIDATION_ERROR', 'Password is required', 'password', 400);
        }

        $result = TotpService::disable($userId, $password);

        if (!$result['success']) {
            $this->respondWithError('DISABLE_FAILED', $result['error'] ?? 'Failed to disable 2FA', 'password', 403);
        }

        $this->respondWithData([
            'message' => 'Two-factor authentication has been disabled',
        ]);
    }
}
