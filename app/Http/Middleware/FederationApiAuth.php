<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\FederationApiMiddleware;
use App\Core\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel middleware wrapper for FederationApiMiddleware.
 *
 * The existing FederationApiMiddleware uses static methods and returns
 * bool|JsonResponse. This wrapper adapts it to Laravel's standard
 * middleware contract (handle + next) so it can be used as route middleware.
 *
 * Used by: /v2/federation/komunitin/* and /v2/federation/cc/* routes.
 */
class FederationApiAuth
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $result = FederationApiMiddleware::authenticate();

        // authenticate() returns true on success, JsonResponse on failure
        if ($result !== true) {
            return $result;
        }

        $partner = FederationApiMiddleware::getPartner();
        $partnerTenantId = (int) ($partner['tenant_id'] ?? 0);
        if ($partnerTenantId <= 0) {
            return FederationApiMiddleware::sendError(401, __('api.federation.webhook_auth_failed'), 'INVALID_PARTNER_TENANT');
        }

        $resolvedTenantId = (int) TenantContext::getId();
        $explicitTenantRequested = $request->headers->has('X-Tenant-ID')
            || $request->headers->has('X-Tenant-Slug');

        if (($explicitTenantRequested || $resolvedTenantId > 1) && $resolvedTenantId !== $partnerTenantId) {
            Log::warning('[FederationApiAuth] Rejected tenant mismatch for federation API key', [
                'api_key_id' => $partner['id'] ?? null,
                'partner_tenant_id' => $partnerTenantId,
                'resolved_tenant_id' => $resolvedTenantId,
                'path' => $request->path(),
            ]);

            return FederationApiMiddleware::sendError(403, __('api.federation.tenant_mismatch'), 'TENANT_MISMATCH');
        }

        if (!TenantContext::setById($partnerTenantId)) {
            return FederationApiMiddleware::sendError(500, __('api.federation.webhook_tenant_error'), 'TENANT_ERROR');
        }

        $requiredPermissions = !empty($permissions)
            ? $permissions
            : $this->requiredPermissionsForRequest($request);

        if (!FederationApiMiddleware::hasAnyPermission($requiredPermissions)) {
            return FederationApiMiddleware::sendError(403, __('api.federation.permission_denied'), 'PERMISSION_DENIED');
        }

        return $next($request);
    }

    /**
     * Protocol routes are partner-to-platform APIs; empty API-key permissions
     * must not imply read or write access.
     *
     * @return array<int, string>
     */
    private function requiredPermissionsForRequest(Request $request): array
    {
        $method = strtoupper($request->method());
        $path = '/' . ltrim($request->path(), '/');

        if (str_contains($path, '/v2/federation/ingest/')) {
            return ['ingest:write'];
        }

        if (str_contains($path, '/v2/federation/cc/')) {
            return $method === 'GET'
                ? ['transactions:read', 'members:read']
                : ['transactions:write'];
        }

        if (str_contains($path, '/v2/federation/komunitin/')) {
            if (str_contains($path, '/transfers')) {
                return $method === 'GET'
                    ? ['transactions:read']
                    : ['transactions:write'];
            }

            if (str_contains($path, '/accounts')) {
                return $method === 'GET'
                    ? ['members:read']
                    : ['members:write', 'admin'];
            }

            return $method === 'GET'
                ? ['members:read', 'transactions:read']
                : ['admin'];
        }

        return ['admin'];
    }
}
