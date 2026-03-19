<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\TotpService;
use Illuminate\Http\JsonResponse;

/**
 * TwoFactorController -- Two-factor authentication setup.
 *
 * All methods use Laravel DI services.
 */
class TwoFactorController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly TotpService $totpService,
    ) {}

    /** GET auth/2fa/status */
    public function status(): JsonResponse
    {
        $userId = $this->requireAuth();

        return $this->respondWithData([
            'enabled' => $this->totpService->isEnabled($userId),
            'setup_required' => $this->totpService->isSetupRequired($userId),
            'backup_codes_remaining' => $this->totpService->getBackupCodeCount($userId),
        ]);
    }

    /** POST auth/2fa/setup */
    public function setup(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('2fa_setup', 5, 300);

        if ($this->totpService->isEnabled($userId)) {
            return $this->respondWithError(
                'ALREADY_ENABLED',
                '2FA is already enabled on your account',
                null,
                409
            );
        }

        try {
            $result = $this->totpService->initializeSetup($userId);

            // Convert raw SVG to data URI for use in <img src="...">
            $svgDataUri = 'data:image/svg+xml;base64,' . base64_encode($result['qr_code']);

            return $this->respondWithData([
                'qr_code_url' => $svgDataUri,
                'secret' => $result['secret'],
                'backup_codes' => [],
            ]);
        } catch (\Exception $e) {
            return $this->respondWithError(
                'SETUP_FAILED',
                'Failed to initialize 2FA setup',
                null,
                500
            );
        }
    }

    /** POST auth/2fa/verify */
    public function verify(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('2fa_verify', 10, 300);

        $data = $this->getAllInput();
        $code = trim($data['code'] ?? '');

        if (empty($code)) {
            return $this->respondWithError(
                'VALIDATION_ERROR',
                'Verification code is required',
                'code',
                400
            );
        }

        $result = $this->totpService->completeSetup($userId, $code);

        if (!$result['success']) {
            return $this->respondWithError(
                'VERIFICATION_FAILED',
                $result['error'] ?? 'Invalid verification code',
                'code',
                400
            );
        }

        return $this->respondWithData([
            'backup_codes' => $result['backup_codes'] ?? [],
        ]);
    }

    /** POST auth/2fa/disable */
    public function disable(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('2fa_disable', 3, 3600);

        $data = $this->getAllInput();
        $password = $data['password'] ?? '';

        if (empty($password)) {
            return $this->respondWithError(
                'VALIDATION_ERROR',
                'Password is required',
                'password',
                400
            );
        }

        $result = $this->totpService->disable($userId, $password);

        if (!$result['success']) {
            return $this->respondWithError(
                'DISABLE_FAILED',
                $result['error'] ?? 'Failed to disable 2FA',
                'password',
                403
            );
        }

        return $this->respondWithData([
            'message' => 'Two-factor authentication has been disabled',
        ]);
    }
}
