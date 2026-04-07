<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\RegistrationService;
use Illuminate\Http\JsonResponse;

/**
 * RegistrationController — User registration, email verification.
 */
class RegistrationController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly RegistrationService $registrationService,
    ) {}

    /**
     * POST /api/v2/register
     *
     * Register a new user account.
     * Body: name, email, password, password_confirmation, phone (optional).
     */
    public function register(): JsonResponse
    {
        $this->rateLimit('registration', 3, 300);

        $data = $this->getAllInput();
        $tenantId = $this->getTenantId();

        $result = $this->registrationService->register($data, $tenantId);

        if (isset($result['error'])) {
            return $this->respondWithError('REGISTRATION_FAILED', $result['error'], null, 422);
        }

        return $this->respondWithData($result, null, 201);
    }

    /**
     * POST /api/v2/register/verify
     *
     * Verify an email address using a verification token.
     * Body: token (required).
     */
    public function verify(): JsonResponse
    {
        $token = $this->requireInput('token');

        $result = $this->registrationService->verifyEmail($token);

        if (!$result) {
            return $this->respondWithError('VERIFICATION_FAILED', __('api.invalid_verification_token'), null, 400);
        }

        return $this->respondWithData(['message' => __('api_controllers_2.registration.email_verified')]);
    }

    /**
     * POST /api/v2/register/resend-verification
     *
     * Resend the verification email.
     * Body: email (required).
     */
    public function resendVerification(): JsonResponse
    {
        $this->rateLimit('resend_verification', 2, 300);

        $email = $this->requireInput('email');

        $this->registrationService->resendVerification($email, $this->getTenantId());

        return $this->respondWithData(['message' => __('api_controllers_2.registration.verification_sent')]);
    }
}
