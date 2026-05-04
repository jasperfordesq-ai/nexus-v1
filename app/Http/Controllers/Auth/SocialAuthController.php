<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use Illuminate\Routing\Controller;
use App\Services\Auth\SocialAuthService;
use App\Core\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * SOC13 — Social login (OAuth) HTTP layer.
 *
 * Endpoints:
 *  GET    /api/v2/auth/oauth/{provider}/redirect   public — returns redirect URL
 *  GET    /api/v2/auth/oauth/{provider}/callback   public — OAuth callback
 *  POST   /api/v2/auth/oauth/{provider}/link       auth — initiate link flow for current user
 *  DELETE /api/v2/auth/oauth/{provider}/unlink     auth — remove a linked provider
 *  GET    /api/v2/auth/oauth/me/identities         auth — list current user's identities
 */
class SocialAuthController extends Controller
{
    public function __construct(
        private readonly SocialAuthService $social,
    ) {
    }

    /**
     * Public endpoint — returns the list of OAuth providers enabled for the
     * current tenant. Empty array when OAUTH_ENABLED env flag is off, or when
     * the tenant has explicitly disabled all providers. Frontend uses this to
     * decide whether to render Google/Apple/Facebook buttons.
     */
    public function enabledProviders(Request $request): JsonResponse
    {
        $tenantId = TenantContext::getId() ?: (int) $request->input('tenant_id', 0);
        if ($tenantId <= 0) {
            return response()->json(['success' => true, 'providers' => []]);
        }
        return response()->json([
            'success' => true,
            'providers' => $this->social->enabledProviders($tenantId),
        ]);
    }

    public function redirect(Request $request, string $provider): JsonResponse
    {
        try {
            $tenantId = TenantContext::getId() ?: (int) $request->input('tenant_id', 0);
            if ($tenantId <= 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'tenant_required',
                    'message' => __('api.social_tenant_required'),
                ], 400);
            }
            $intent = (string) $request->input('intent', 'login');
            if (! in_array($intent, ['login', 'register'], true)) {
                $intent = 'login';
            }

            $result = $this->social->redirectUrl($provider, $tenantId, $intent);

            if (! empty($result['error']) && $result['error'] === 'socialite_not_installed') {
                return response()->json([
                    'success' => false,
                    'error' => 'socialite_not_installed',
                    'message' => __('api.social_oauth_unavailable'),
                ], 503);
            }

            return response()->json([
                'success' => true,
                'redirect_url' => $result['url'],
                'state' => $result['state'],
                'provider' => $provider,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[SocialAuth] redirect failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'oauth_redirect_failed',
                'message' => __('api.social_oauth_redirect_failed'),
            ], 400);
        }
    }

    public function callback(Request $request, string $provider)
    {
        $frontend = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', 'https://app.project-nexus.ie')), '/');

        try {
            $state = (string) ($request->input('state') ?? '');
            if ($state === '') {
                throw new \RuntimeException('OAuth state missing.');
            }

            $result = $this->social->handleCallback($provider, $state);
            /** @var \App\Models\User $user */
            $user = $result['user'];

            // Issue Sanctum token.
            $tokenResult = $user->createToken('oauth-' . $provider, ['*']);
            $accessToken = $tokenResult->plainTextToken;
            try {
                $tokenResult->accessToken->forceFill(['tenant_id' => (int) $user->tenant_id])->save();
            } catch (\Throwable $e) {
                // tenant_id column may be absent on personal_access_tokens in older envs
            }

            $params = http_build_query([
                'token' => $accessToken,
                'provider' => $provider,
                'is_new' => $result['is_new'] ? '1' : '0',
                'tenant_id' => $result['tenant_id'],
            ]);
            return redirect($frontend . '/auth/oauth/callback?' . $params);
        } catch (\Throwable $e) {
            Log::warning('[SocialAuth] callback failed: ' . $e->getMessage());
            $params = http_build_query([
                'error' => 'oauth_failed',
                'message' => __('api.social_oauth_link_failed'),
                'provider' => $provider,
            ]);
            return redirect($frontend . '/auth/oauth/callback?' . $params);
        }
    }

    public function link(Request $request, string $provider): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'error' => 'unauthenticated'], 401);
        }
        try {
            $tenantId = (int) $user->tenant_id;
            $redirect = $this->social->redirectUrl($provider, $tenantId, 'link', (int) $user->id);
            if (! empty($redirect['error']) && $redirect['error'] === 'socialite_not_installed') {
                return response()->json([
                    'success' => false,
                    'error' => 'socialite_not_installed',
                ], 503);
            }
            return response()->json([
                'success' => true,
                'redirect_url' => $redirect['url'],
                'state' => $redirect['state'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'oauth_link_failed',
                'message' => __('api.social_oauth_unlink_failed'),
            ], 400);
        }
    }

    public function unlink(Request $request, string $provider): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'error' => 'unauthenticated'], 401);
        }
        try {
            $this->social->unlinkProvider((int) $user->id, $provider);
            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'unlink_failed',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function identities(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'error' => 'unauthenticated'], 401);
        }
        $identities = $this->social->listIdentities((int) $user->id);
        $tenantId = (int) $user->tenant_id;
        $enabled = $this->social->enabledProviders($tenantId);
        return response()->json([
            'success' => true,
            'identities' => $identities,
            'enabled_providers' => $enabled,
            'supported_providers' => SocialAuthService::SUPPORTED_PROVIDERS,
        ]);
    }
}
