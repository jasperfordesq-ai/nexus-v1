<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * RegistrationPolicyController -- Registration policy and identity verification.
 *
 * Delegates to legacy: RegistrationPolicyApiController
 */
class RegistrationPolicyController extends BaseApiController
{
    protected bool $isV2Api = true;

    /** GET admin/registration-policy */
    public function getPolicy(): JsonResponse
    {
        $this->requireAdmin();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\RegistrationPolicyApiController();
            $controller->getPolicy();
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

    /** PUT admin/registration-policy */
    public function updatePolicy(): JsonResponse
    {
        $this->requireAdmin();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\RegistrationPolicyApiController();
            $controller->updatePolicy();
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

    /** GET admin/identity/providers */
    public function listProviders(): JsonResponse
    {
        $this->requireAdmin();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\RegistrationPolicyApiController();
            $controller->listProviders();
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

    /** GET auth/verification-status */
    public function getVerificationStatus(): JsonResponse
    {
        $this->requireAuth();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\RegistrationPolicyApiController();
            $controller->getVerificationStatus();
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

    /** POST auth/start-verification */
    public function startVerification(): JsonResponse
    {
        $this->requireAuth();

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\RegistrationPolicyApiController();
            $controller->startVerification();
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

    /** POST auth/validate-invite */
    public function validateInviteCode(): JsonResponse
    {

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\RegistrationPolicyApiController();
            $controller->validateInviteCode();
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

    /** GET auth/registration-info */
    public function getRegistrationInfo(): JsonResponse
    {

        ob_start();
        try {
            $controller = new \Nexus\Controllers\Api\RegistrationPolicyApiController();
            $controller->getRegistrationInfo();
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


    public function listSessions(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\RegistrationPolicyApiController::class, 'listSessions');
    }


    public function getAuditLog(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\RegistrationPolicyApiController::class, 'getAuditLog');
    }


    public function adminApproveVerification($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\RegistrationPolicyApiController::class, 'adminApproveVerification', [$id]);
    }


    public function adminRejectVerification($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\RegistrationPolicyApiController::class, 'adminRejectVerification', [$id]);
    }


    public function listProviderCredentials(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\RegistrationPolicyApiController::class, 'listProviderCredentials');
    }


    public function saveProviderCredentials($slug): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\RegistrationPolicyApiController::class, 'saveProviderCredentials', [$slug]);
    }


    public function deleteProviderCredentials($slug): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\RegistrationPolicyApiController::class, 'deleteProviderCredentials', [$slug]);
    }


    public function listInviteCodes(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\RegistrationPolicyApiController::class, 'listInviteCodes');
    }


    public function generateInviteCodes(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\RegistrationPolicyApiController::class, 'generateInviteCodes');
    }


    public function deactivateInviteCode($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\RegistrationPolicyApiController::class, 'deactivateInviteCode', [$id]);
    }

}
