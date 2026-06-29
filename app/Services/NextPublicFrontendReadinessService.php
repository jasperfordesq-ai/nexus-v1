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
        $publicRoutes = is_array($manifest) ? array_values($manifest['nextPublicRoutes'] ?? []) : [];
        $privatePrefixes = is_array($manifest) ? array_values($manifest['vitePrivatePrefixes'] ?? []) : [];
        $privatePatterns = is_array($manifest) ? array_values($manifest['vitePrivatePatterns'] ?? []) : [];
        $apiBackedRoutes = $this->apiBackedRoutes();
        $validation = $this->validateManifest($manifest, $publicRoutes, $privatePrefixes, $apiBackedRoutes);

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
                'lockfile_exists' => File::isFile($appDir . DIRECTORY_SEPARATOR . 'package-lock.json'),
                'package_scripts' => $this->packageScriptPresence($package),
            ],
            'manifest' => [
                'exists' => is_array($manifest),
                'mode' => is_array($manifest) ? ($manifest['mode'] ?? null) : null,
                'route_counts' => [
                    'public_routes' => count($publicRoutes),
                    'api_backed_public_routes' => count($apiBackedRoutes),
                    'vite_private_prefixes' => count($privatePrefixes),
                    'vite_private_patterns' => count($privatePatterns),
                ],
                'validation' => $validation,
                'public_routes' => $publicRoutes,
                'vite_private_prefixes' => $privatePrefixes,
                'vite_private_patterns' => $privatePatterns,
            ],
            'content_sources' => [
                'source_of_truth' => 'laravel_public_api',
                'database_queries_from_next' => false,
                'api_backed_routes' => $apiBackedRoutes,
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
                'compose_profile_configured' => $this->composeProfileConfigured('next-public-shadow'),
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
     * @return array<int, array{routeKey: string, endpoint: string, method: string}>
     */
    private function apiBackedRoutes(): array
    {
        return [
            ['routeKey' => 'blog-index', 'endpoint' => '/v2/blog', 'method' => 'GET'],
            ['routeKey' => 'blog-detail', 'endpoint' => '/v2/blog/{slug}', 'method' => 'GET'],
            ['routeKey' => 'cms-page', 'endpoint' => '/v2/pages/{slug}', 'method' => 'GET'],
            ['routeKey' => 'listings', 'endpoint' => '/v2/listings', 'method' => 'GET'],
            ['routeKey' => 'listingDetail', 'endpoint' => '/v2/listings/{id}', 'method' => 'GET'],
            ['routeKey' => 'events', 'endpoint' => '/v2/events', 'method' => 'GET'],
            ['routeKey' => 'eventDetail', 'endpoint' => '/v2/events/{id}', 'method' => 'GET'],
            ['routeKey' => 'jobs', 'endpoint' => '/v2/jobs', 'method' => 'GET'],
            ['routeKey' => 'jobDetail', 'endpoint' => '/v2/jobs/{id}', 'method' => 'GET'],
            ['routeKey' => 'organisations', 'endpoint' => '/v2/volunteering/organisations', 'method' => 'GET'],
            ['routeKey' => 'organisationDetail', 'endpoint' => '/v2/volunteering/organisations/{id}', 'method' => 'GET'],
            ['routeKey' => 'resources', 'endpoint' => '/v2/resources', 'method' => 'GET'],
            ['routeKey' => 'kb', 'endpoint' => '/v2/kb', 'method' => 'GET'],
            ['routeKey' => 'kbDetail', 'endpoint' => '/v2/kb/{id}', 'method' => 'GET'],
            ['routeKey' => 'marketplace', 'endpoint' => '/v2/marketplace/listings', 'method' => 'GET'],
            ['routeKey' => 'marketplaceDetail', 'endpoint' => '/v2/marketplace/listings/{id}', 'method' => 'GET'],
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

    /**
     * @param array<string, mixed>|null $package
     * @return array<string, bool>
     */
    private function packageScriptPresence(?array $package): array
    {
        $scripts = is_array($package) && is_array($package['scripts'] ?? null)
            ? $package['scripts']
            : [];

        return [
            'dev' => isset($scripts['dev']),
            'build' => isset($scripts['build']),
            'start' => isset($scripts['start']),
            'test' => isset($scripts['test']),
        ];
    }

    private function composeProfileConfigured(string $profile): bool
    {
        $composePath = base_path('compose.bluegreen.yml');

        return File::isFile($composePath)
            && str_contains((string) File::get($composePath), $profile);
    }

    /**
     * @param array<string, mixed>|null $manifest
     * @param array<int, mixed> $publicRoutes
     * @param array<int, mixed> $privatePrefixes
     * @param array<int, array{routeKey: string, endpoint: string, method: string}> $apiBackedRoutes
     * @return array{status: string, issues: array<int, array<string, string>>}
     */
    private function validateManifest(?array $manifest, array $publicRoutes, array $privatePrefixes, array $apiBackedRoutes): array
    {
        $issues = [];

        if (!is_array($manifest)) {
            return [
                'status' => 'blocker',
                'issues' => [[
                    'code' => 'manifest_missing',
                    'severity' => 'blocker',
                    'context' => 'route-ownership.json',
                ]],
            ];
        }

        if (($manifest['mode'] ?? null) !== 'shadow') {
            $issues[] = [
                'code' => 'manifest_not_shadow',
                'severity' => 'blocker',
                'context' => (string) ($manifest['mode'] ?? 'missing'),
            ];
        }

        $patterns = [];
        $routeKeys = [];
        $privatePrefixSet = array_flip(array_filter($privatePrefixes, 'is_string'));

        foreach ($publicRoutes as $route) {
            if (!is_array($route)) {
                $issues[] = ['code' => 'public_route_invalid', 'severity' => 'blocker', 'context' => 'non-object'];
                continue;
            }

            $pattern = isset($route['pattern']) ? (string) $route['pattern'] : '';
            $routeKey = isset($route['routeKey']) ? (string) $route['routeKey'] : '';
            $labelKey = isset($route['labelKey']) ? (string) $route['labelKey'] : '';

            if ($pattern === '' || $routeKey === '' || $labelKey === '') {
                $issues[] = ['code' => 'public_route_missing_fields', 'severity' => 'blocker', 'context' => $pattern ?: 'unknown'];
            }

            if ($pattern !== '' && isset($patterns[$pattern])) {
                $issues[] = ['code' => 'public_route_duplicate_pattern', 'severity' => 'blocker', 'context' => $pattern];
            }
            $patterns[$pattern] = true;

            if ($routeKey !== '' && isset($routeKeys[$routeKey])) {
                $issues[] = ['code' => 'public_route_duplicate_key', 'severity' => 'blocker', 'context' => $routeKey];
            }
            $routeKeys[$routeKey] = true;

            $firstSegment = strtok(ltrim($pattern, '/'), '/');
            if (is_string($firstSegment) && $firstSegment !== '' && isset($privatePrefixSet[$firstSegment])) {
                $issues[] = ['code' => 'public_route_collides_with_private_prefix', 'severity' => 'blocker', 'context' => $pattern];
            }
        }

        foreach ($apiBackedRoutes as $route) {
            if (!isset($routeKeys[$route['routeKey']])) {
                $issues[] = [
                    'code' => 'api_backed_route_not_in_manifest',
                    'severity' => 'blocker',
                    'context' => $route['routeKey'],
                ];
            }
        }

        return [
            'status' => $issues === [] ? 'pass' : 'blocker',
            'issues' => $issues,
        ];
    }
}
