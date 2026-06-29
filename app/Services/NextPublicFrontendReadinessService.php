<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\File;

class NextPublicFrontendReadinessService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $appDir = base_path('next-public-frontend');
        $manifest = $this->readJson($appDir . DIRECTORY_SEPARATOR . 'route-ownership.json');
        $package = $this->readJson($appDir . DIRECTORY_SEPARATOR . 'package.json');

        $manifestMode = is_array($manifest) ? (string) ($manifest['mode'] ?? 'unknown') : 'missing';
        $cutoverEnabled = filter_var(
            env('NEXT_PUBLIC_FRONTEND_ROUTING_ENABLED', false),
            FILTER_VALIDATE_BOOLEAN,
        );

        return [
            'mode' => $manifestMode === 'shadow' ? 'shadow' : $manifestMode,
            'app' => [
                'exists' => File::isDirectory($appDir),
                'package_name' => is_array($package) ? ($package['name'] ?? null) : null,
                'version' => is_array($package) ? ($package['version'] ?? null) : null,
                'next_version' => is_array($package) ? ($package['dependencies']['next'] ?? null) : null,
                'react_version' => is_array($package) ? ($package['dependencies']['react'] ?? null) : null,
            ],
            'manifest' => [
                'exists' => is_array($manifest),
                'mode' => is_array($manifest) ? ($manifest['mode'] ?? null) : null,
                'public_routes' => is_array($manifest) ? array_values($manifest['nextPublicRoutes'] ?? []) : [],
                'vite_private_prefixes' => is_array($manifest) ? array_values($manifest['vitePrivatePrefixes'] ?? []) : [],
                'vite_private_patterns' => is_array($manifest) ? array_values($manifest['vitePrivatePatterns'] ?? []) : [],
            ],
            'production_routing' => [
                'active' => false,
                'route_cutover_enabled' => $cutoverEnabled,
                'edge_routes_configured' => false,
            ],
            'prerender' => [
                'status' => 'unchanged',
                'fallback_retained' => true,
            ],
            'shadow_runtime' => [
                'compose_profile' => 'next-public-shadow',
                'dev_command' => 'npm run dev:next-public',
                'build_command' => 'npm run build:next-public',
                'container_port' => 3000,
                'host_port_env' => 'NEXUS_NEXT_PUBLIC_PORT',
                'port_env' => 'NEXUS_NEXT_PUBLIC_PORT',
                'default_shadow_port' => 3200,
            ],
            'safety_checks' => [
                ['key' => 'route_cutover_disabled', 'status' => 'pass'],
                ['key' => 'prerender_retained', 'status' => 'pass'],
                ['key' => 'vite_private_routes_retained', 'status' => 'pass'],
                ['key' => 'public_edge_not_configured', 'status' => 'pass'],
                ['key' => 'parity_tests_required_before_cutover', 'status' => 'blocker'],
            ],
            'cutover_step_keys' => [
                'verify_next_shadow_build',
                'verify_no_js_public_html',
                'verify_private_vite_regression',
                'prepare_apache_canary_routes',
                'enable_canary_for_public_routes_only',
                'monitor_and_keep_prerender_fallback',
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readJson(string $path): ?array
    {
        if (!File::isFile($path)) {
            return null;
        }

        $decoded = json_decode((string) File::get($path), true);

        return is_array($decoded) ? $decoded : null;
    }
}
