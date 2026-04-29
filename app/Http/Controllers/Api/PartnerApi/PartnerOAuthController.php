<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api\PartnerApi;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\PartnerApi\PartnerApiAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AG60 — Public OAuth2 endpoints for the Partner API.
 *
 * Implements only the client_credentials grant — sufficient for
 * server-to-server integrations (banks, municipal admin systems).
 * No authorization-code flow / no end-user delegation in v1.
 */
class PartnerOAuthController extends BaseApiController
{
    /**
     * POST /api/partner/v1/oauth/token
     *
     * Body: { grant_type, client_id, client_secret, scope? }
     */
    public function token(Request $request): JsonResponse
    {
        $grant = (string) $request->input('grant_type', '');
        if ($grant !== 'client_credentials') {
            return $this->respondWithError('unsupported_grant_type',
                'Only client_credentials is supported.', null, 400);
        }

        $clientId = (string) $request->input('client_id', '');
        $clientSecret = (string) $request->input('client_secret', '');
        if ($clientId === '' || $clientSecret === '') {
            return $this->respondWithError('invalid_client',
                'client_id and client_secret are required.', null, 400);
        }

        $partner = PartnerApiAuthService::verifyClient($clientId, $clientSecret);
        if (! $partner) {
            return $this->respondWithError('invalid_client',
                'Client authentication failed.', null, 401);
        }

        $scopeParam = trim((string) $request->input('scope', ''));
        $requested = $scopeParam === '' ? null : array_values(array_filter(explode(' ', $scopeParam)));

        $token = PartnerApiAuthService::issueAccessToken($partner, $requested);

        return response()->json($token, 200, ['API-Version' => '2.0']);
    }

    /**
     * POST /api/partner/v1/oauth/revoke
     *
     * Body: { token }
     */
    public function revoke(Request $request): JsonResponse
    {
        $token = (string) $request->input('token', '');
        if ($token !== '') {
            PartnerApiAuthService::revokeAccessToken($token);
        }
        // RFC 7009: always return 200 even when the token didn't exist.
        return response()->json(['revoked' => true], 200, ['API-Version' => '2.0']);
    }
}
