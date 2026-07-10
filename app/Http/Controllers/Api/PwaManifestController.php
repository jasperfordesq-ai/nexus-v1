<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/** Serve the install manifest with an identity and URLs scoped to the active tenant. */
final class PwaManifestController
{
    public function show(Request $request): Response
    {
        $manifest = $this->baseManifest();
        [$tenant, $prefix] = $this->resolveTenantAndPrefix($request);
        $scope = $prefix === '' ? '/' : $prefix . '/';

        if (!empty($tenant['name'])) {
            $manifest['name'] = (string) $tenant['name'];
            $manifest['short_name'] = mb_substr((string) $tenant['name'], 0, 30);
        }

        $manifest['id'] = $scope;
        $manifest['start_url'] = $scope;
        $manifest['scope'] = $scope;

        foreach (($manifest['shortcuts'] ?? []) as $index => $shortcut) {
            if (!is_array($shortcut) || empty($shortcut['url'])) {
                continue;
            }

            $path = '/' . ltrim((string) $shortcut['url'], '/');
            $manifest['shortcuts'][$index]['url'] = $prefix . $path;
        }

        $json = json_encode(
            $manifest,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        return response($json, 200, [
            'Content-Type' => 'application/manifest+json; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300, stale-while-revalidate=60',
            'Vary' => 'Host',
        ]);
    }

    /** @return array<string, mixed> */
    private function baseManifest(): array
    {
        $path = base_path('react-frontend/public/manifest.json');
        $manifest = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return is_array($manifest) ? $manifest : [];
    }

    /** @return array{0: array<string, mixed>, 1: string} */
    private function resolveTenantAndPrefix(Request $request): array
    {
        $tenant = (array) (TenantContext::get() ?? []);
        $prefix = TenantContext::getSlugPrefix();
        $requestedPath = (string) $request->query('path', '/');
        $path = (string) (parse_url($requestedPath, PHP_URL_PATH) ?: '/');
        $firstSegment = explode('/', trim($path, '/'))[0] ?? '';

        if ($firstSegment !== '') {
            $pathTenantQuery = DB::table('tenants')
                ->where('slug', $firstSegment)
                ->where('is_active', true);

            // A dedicated-domain parent deliberately has no slug prefix. On
            // that host, only a direct child slug may qualify the requested
            // path; an ordinary route such as `/listings` must never switch to
            // an unrelated tenant with the same slug. The master/shared host
            // has no such domain identity, so it may resolve any active path
            // tenant from the manifest request's explicit `path` parameter.
            $isDedicatedDomainTenant = (int) ($tenant['id'] ?? 0) > 1
                && trim((string) ($tenant['domain'] ?? '')) !== ''
                && $prefix === '';

            if ($isDedicatedDomainTenant) {
                $pathTenantQuery->where('parent_id', (int) $tenant['id']);
            }

            $pathTenant = $pathTenantQuery->first();

            if ($pathTenant !== null) {
                $tenant = (array) $pathTenant;
                $prefix = '/' . ltrim((string) $pathTenant->slug, '/');
            }
        }

        return [$tenant, rtrim($prefix, '/')];
    }
}
