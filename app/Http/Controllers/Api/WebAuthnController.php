<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * WebAuthnController — Passkey/WebAuthn registration and authentication flows.
 *
 * Minimal controller using DB facade directly for challenge storage.
 */
class WebAuthnController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/webauthn/register/challenge
     *
     * Generate a registration challenge for the authenticated user.
     */
    public function registerChallenge(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        $challenge = bin2hex(random_bytes(32));

        DB::table('webauthn_challenges')->insert([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'challenge' => $challenge,
            'type' => 'registration',
            'created_at' => now(),
            'expires_at' => now()->addMinutes(5),
        ]);

        return $this->respondWithData([
            'challenge' => $challenge,
            'rp' => ['name' => config('app.name'), 'id' => config('webauthn.rp_id')],
            'user_id' => $userId,
        ]);
    }

    /**
     * POST /api/v2/webauthn/register/verify
     *
     * Verify a registration response and store the credential.
     */
    public function registerVerify(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();
        $data = $this->getAllInput();

        $stored = DB::table('webauthn_challenges')
            ->where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('type', 'registration')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->first();

        if (!$stored) {
            return $this->respondWithError('CHALLENGE_EXPIRED', 'No valid challenge found', null, 400);
        }

        DB::table('webauthn_credentials')->insert([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'credential_id' => $data['credential_id'] ?? '',
            'public_key' => $data['public_key'] ?? '',
            'name' => $data['name'] ?? 'Passkey',
            'created_at' => now(),
        ]);

        DB::table('webauthn_challenges')->where('id', $stored->id)->delete();

        return $this->respondWithData(['message' => 'Passkey registered successfully'], null, 201);
    }

    /**
     * GET /api/v2/webauthn/auth/challenge
     *
     * Generate an authentication challenge.
     */
    public function authChallenge(): JsonResponse
    {
        $this->rateLimit('webauthn_auth', 10, 60);

        $challenge = bin2hex(random_bytes(32));

        DB::table('webauthn_challenges')->insert([
            'challenge' => $challenge,
            'type' => 'authentication',
            'created_at' => now(),
            'expires_at' => now()->addMinutes(5),
        ]);

        return $this->respondWithData([
            'challenge' => $challenge,
            'rp_id' => config('webauthn.rp_id'),
        ]);
    }

    /**
     * POST /api/v2/webauthn/auth/verify
     *
     * Verify an authentication response.
     */
    public function authVerify(): JsonResponse
    {
        $this->rateLimit('webauthn_verify', 5, 60);

        $data = $this->getAllInput();
        $credentialId = $data['credential_id'] ?? '';

        $credential = DB::table('webauthn_credentials')
            ->where('credential_id', $credentialId)
            ->first();

        if (!$credential) {
            return $this->respondWithError('CREDENTIAL_NOT_FOUND', 'Unknown credential', null, 401);
        }

        return $this->respondWithData([
            'user_id' => $credential->user_id,
            'message' => 'Authentication successful',
        ]);
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

}
