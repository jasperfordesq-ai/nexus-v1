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
use Illuminate\Support\Facades\RateLimiter;

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
        [$clientId, $clientSecret] = $this->clientCredentials($request);
        $rateLimitKey = 'partner_oauth_token:' . $request->ip() . ':' . hash('sha256', $clientId);
        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            return $this->respondWithError('rate_limited', __('api.rate_limit_exceeded'), null, 429);
        }

        $grant = (string) $request->input('grant_type', '');
        if ($grant !== 'client_credentials') {
            RateLimiter::hit($rateLimitKey, 300);
            return $this->respondWithError('unsupported_grant_type',
                'Only client_credentials is supported.', null, 400);
        }

        if ($clientId === '' || $clientSecret === '') {
            RateLimiter::hit($rateLimitKey, 300);
            return $this->respondWithError('invalid_client',
                'client_id and client_secret are required.', null, 400);
        }

        $partner = PartnerApiAuthService::verifyClient($clientId, $clientSecret);
        if (! $partner) {
            RateLimiter::hit($rateLimitKey, 300);
            return $this->respondWithError('invalid_client',
                'Client authentication failed.', null, 401);
        }
        RateLimiter::clear($rateLimitKey);

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
        [$clientId, $clientSecret] = $this->clientCredentials($request);
        if ($clientId === '' || $clientSecret === '') {
            return $this->respondWithError('invalid_client',
                'client_id and client_secret are required.', null, 401);
        }

        $partner = PartnerApiAuthService::verifyClient($clientId, $clientSecret);
        if (! $partner) {
            return $this->respondWithError('invalid_client',
                'Client authentication failed.', null, 401);
        }

        $token = (string) $request->input('token', '');
        if ($token !== '') {
            PartnerApiAuthService::revokeAccessTokenForPartner($token, (int) $partner['id']);
        }
        // RFC 7009: always return 200 even when the token didn't exist.
        return response()->json(['revoked' => true], 200, ['API-Version' => '2.0']);
    }

    /**
     * @return array{0:string, 1:string}
     */
    private function clientCredentials(Request $request): array
    {
        $clientId = (string) ($request->input('client_id') ?? $request->getUser() ?? '');
        $clientSecret = (string) ($request->input('client_secret') ?? $request->getPassword() ?? '');

        return [$clientId, $clientSecret];
    }
}
