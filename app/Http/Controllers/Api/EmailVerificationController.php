<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * EmailVerificationController -- Email verification endpoints.
 *
 * Delegates to legacy: EmailVerificationApiController
 */
class EmailVerificationController extends BaseApiController
{
    protected bool $isV2Api = true;

    /** POST /api/auth/verify-email */
    public function verifyEmail(): JsonResponse
    {

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\EmailVerificationApiController();
            $controller->verifyEmail();
        } catch (\Throwable $e) {
            ob_end_clean();
            return $this->respondWithError(
                'INTERNAL_ERROR', $e->getMessage(), null, 500
            );
        }
        $output = ob_get_clean();
        $data = json_decode($output, true);

        if ($data === null) {
            return $this->respondWithData([]);
        }

        return response()->json($data);
    }

    /** POST /api/auth/resend-verification */
    public function resendVerification(): JsonResponse
    {
        $this->requireAuth();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\EmailVerificationApiController();
            $controller->resendVerification();
        } catch (\Throwable $e) {
            ob_end_clean();
            return $this->respondWithError(
                'INTERNAL_ERROR', $e->getMessage(), null, 500
            );
        }
        $output = ob_get_clean();
        $data = json_decode($output, true);

        if ($data === null) {
            return $this->respondWithData([]);
        }

        return response()->json($data);
    }

    /** POST /api/auth/resend-verification-by-email */
    public function resendVerificationByEmail(): JsonResponse
    {

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\EmailVerificationApiController();
            $controller->resendVerificationByEmail();
        } catch (\Throwable $e) {
            ob_end_clean();
            return $this->respondWithError(
                'INTERNAL_ERROR', $e->getMessage(), null, 500
            );
        }
        $output = ob_get_clean();
        $data = json_decode($output, true);

        if ($data === null) {
            return $this->respondWithData([]);
        }

        return response()->json($data);
    }
}
