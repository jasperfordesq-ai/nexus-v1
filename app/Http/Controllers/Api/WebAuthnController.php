<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

class WebAuthnController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function registerChallenge(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\WebAuthnApiController::class, 'registerChallenge');
    }

    public function registerVerify(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\WebAuthnApiController::class, 'registerVerify');
    }

    public function authChallenge(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\WebAuthnApiController::class, 'authChallenge');
    }

    public function authVerify(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\WebAuthnApiController::class, 'authVerify');
    }

    public function remove(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\WebAuthnApiController::class, 'remove');
    }

    public function rename(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\WebAuthnApiController::class, 'rename');
    }

    public function removeAll(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\WebAuthnApiController::class, 'removeAll');
    }

    public function credentials(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\WebAuthnApiController::class, 'credentials');
    }

    public function status(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\WebAuthnApiController::class, 'status');
    }

    private function delegate(string $legacyClass, string $method, array $params = []): JsonResponse
    {
        $controller = new $legacyClass();
        ob_start();
        $controller->$method(...$params);
        $output = ob_get_clean();
        $status = http_response_code() ?: 200;
        return response()->json(json_decode($output, true) ?: $output, $status);
    }
}
