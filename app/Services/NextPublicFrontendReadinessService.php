<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\File;

class NextPublicFrontendReadinessService
{
    private const REQUIRED_VITE_PRIVATE_PREFIXES = [
        'achievements',
        'admin',
        'auth',
        'broker',
        'connections',
        'dashboard',
        'feed',
        'goals',
        'groups',
        'leaderboard',
        'login',
        'matches',
        'members',
        'messages',
        'notifications',
        'onboarding',
        'password',
        'profile',
        'register',
        'settings',
        'super-admin',
        'verify-email',
        'verify-identity',
        'verify-identity-optional',
        'wallet',
    ];

    private const REQUIRED_VITE_PRIVATE_PATTERNS = [
        '/events/new',
        '/events/create',
        '/events/:id/edit',
        '/events/edit/:id',
        '/groups/create',
        '/groups/edit/:id',
        '/caring-community/my-relationships',
        '/caring-community/my-trust-tier',
        '/caring-community/my-data-export',
        '/caring-community/safeguarding/my-reports',
        '/courses/my-learning',
        '/courses/instructor',
        '/courses/instructor/new',
        '/courses/instructor/:id/edit',
        '/courses/instructor/:id/analytics',
        '/courses/instructor/:id/grading',
        '/courses/:id/learn',
        '/federation/onboarding',
        '/group-exchanges/create',
        '/podcasts/studio',
        '/jobs/new',
        '/jobs/create',
        '/jobs/:id/edit',
        '/jobs/:id/analytics',
        '/jobs/:id/kanban',
        '/jobs/alerts',
        '/jobs/my-applications',
        '/jobs/talent-search',
        '/jobs/bias-audit',
        '/jobs/employer-onboarding',
        '/listings/new',
        '/listings/create',
        '/listings/:id/edit',
        '/listings/edit/:id',
        '/marketplace/new',
        '/marketplace/sell',
        '/marketplace/my-listings',
        '/marketplace/my-offers',
        '/marketplace/orders',
        '/marketplace/orders/sales',
        '/marketplace/seller/onboard',
        '/marketplace/become-partner',
        '/marketplace/seller/onboarding',
        '/marketplace/seller/pickup-slots',
        '/marketplace/seller/pickup-scan',
        '/marketplace/me/pickups',
        '/marketplace/seller/coupons/new',
        '/marketplace/seller/coupons/:id/edit',
        '/marketplace/:id/edit',
        '/organisations/new',
        '/organisations/register',
        '/organisations/:id/edit',
        '/volunteering/create',
        '/ideation/create',
        '/ideation/:id/edit',
        '/premium/manage',
        '/reviews/create',
        '/volunteering/my-applications',
        '/volunteering/my-organisations',
        '/volunteering/org/:orgId/dashboard',
        '/resources/new',
        '/resources/:id/edit',
    ];

    private const PRIVATE_LARAVEL_V2_ENDPOINT_PREFIXES = [
        '/v2/admin',
        '/v2/auth',
        '/v2/broker',
        '/v2/dashboard',
        '/v2/feed',
        '/v2/messages',
        '/v2/notifications',
        '/v2/settings',
        '/v2/super-admin',
        '/v2/wallet',
    ];

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $appDir = base_path('next-public-frontend');
        $manifest = $this->readJson($appDir . DIRECTORY_SEPARATOR . 'route-ownership.json');
        $contentSourcesPath = $appDir . DIRECTORY_SEPARATOR . 'content-sources.json';
        $contentSources = $this->readJson($contentSourcesPath);
        $package = $this->readJson($appDir . DIRECTORY_SEPARATOR . 'package.json');
        $publicRoutes = is_array($manifest) ? array_values($manifest['nextPublicRoutes'] ?? []) : [];
        $privatePrefixes = is_array($manifest) ? array_values($manifest['vitePrivatePrefixes'] ?? []) : [];
        $privatePatterns = is_array($manifest) ? array_values($manifest['vitePrivatePatterns'] ?? []) : [];
        $apiBackedRoutes = $this->apiBackedRoutes($contentSources);
        $validation = $this->validateManifest(
            $manifest,
            $publicRoutes,
            $privatePrefixes,
            $apiBackedRoutes,
            $contentSources,
            $privatePatterns,
        );

        $manifestMode = is_array($manifest) ? (string) ($manifest['mode'] ?? 'unknown') : 'missing';
        $cutoverEnabled = (bool) config('app.next_public_frontend_routing_enabled', false);

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
                'route_readiness' => $this->routeReadiness($publicRoutes, $apiBackedRoutes),
            ],
            'content_sources' => [
                'manifest_exists' => is_array($contentSources),
                'manifest_path' => 'next-public-frontend/content-sources.json',
                'source_of_truth' => is_array($contentSources) ? (string) ($contentSources['sourceOfTruth'] ?? 'unknown') : 'missing',
                'database_queries_from_next' => is_array($contentSources)
                    ? (bool) ($contentSources['databaseQueriesFromNext'] ?? true)
                    : true,
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
                'verification_commands' => [
                    'npm --prefix next-public-frontend run check',
                    'npm --prefix react-frontend run build',
                    'cd react-frontend && npx tsc --noEmit',
                    'vendor/bin/phpunit --no-coverage tests/Laravel/Unit/Services/NextPublicFrontendReadinessServiceTest.php tests/Laravel/Feature/Controllers/AdminNextPublicFrontendControllerTest.php',
                ],
            ],
            'safety_checks' => [
                ['key' => 'route_cutover_disabled', 'status' => $cutoverEnabled ? 'blocker' : 'pass'],
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
    private function apiBackedRoutes(?array $contentSources): array
    {
        if (!is_array($contentSources) || !is_array($contentSources['apiBackedRoutes'] ?? null)) {
            return [];
        }

        $routes = [];

        foreach ($contentSources['apiBackedRoutes'] as $route) {
            if (!is_array($route)) {
                continue;
            }

            $routeKey = isset($route['routeKey']) ? (string) $route['routeKey'] : '';
            $endpoint = isset($route['endpoint']) ? (string) $route['endpoint'] : '';
            $method = isset($route['method']) ? (string) $route['method'] : '';

            if ($routeKey === '' || $endpoint === '' || $method === '') {
                continue;
            }

            $routes[] = [
                'routeKey' => $routeKey,
                'endpoint' => $endpoint,
                'method' => strtoupper($method),
            ];
        }

        return $routes;
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
            'check_manifests' => isset($scripts['check:manifests']),
            'check_no_js_html' => isset($scripts['check:no-js-html']),
        ];
    }

    private function composeProfileConfigured(string $profile): bool
    {
        $composePath = base_path('compose.bluegreen.yml');

        return File::isFile($composePath)
            && str_contains((string) File::get($composePath), $profile);
    }

    /**
     * @param array<int, mixed> $publicRoutes
     * @param array<int, array{routeKey: string, endpoint: string, method: string}> $apiBackedRoutes
     * @return array<int, array{pattern: string, routeKey: string, content_source: string, status: string, blockers: array<int, string>}>
     */
    private function routeReadiness(array $publicRoutes, array $apiBackedRoutes): array
    {
        $apiBackedRouteKeys = array_flip(array_column($apiBackedRoutes, 'routeKey'));
        $readiness = [];

        foreach ($publicRoutes as $route) {
            if (!is_array($route)) {
                continue;
            }

            $pattern = isset($route['pattern']) ? (string) $route['pattern'] : '';
            $routeKey = isset($route['routeKey']) ? (string) $route['routeKey'] : '';

            if ($pattern === '' || $routeKey === '') {
                continue;
            }

            $readiness[] = [
                'pattern' => $pattern,
                'routeKey' => $routeKey,
                'content_source' => isset($apiBackedRouteKeys[$routeKey])
                    ? 'laravel_public_api'
                    : 'static_or_tenant_bootstrap',
                'status' => 'blocker',
                'blockers' => ['parity_test_required'],
            ];
        }

        return $readiness;
    }

    /**
     * @param array<string, mixed>|null $manifest
     * @param array<int, mixed> $publicRoutes
     * @param array<int, mixed> $privatePrefixes
     * @param array<int, array{routeKey: string, endpoint: string, method: string}> $apiBackedRoutes
     * @param array<string, mixed>|null $contentSources
     * @param array<int, mixed> $privatePatterns
     * @return array{status: string, issues: array<int, array<string, string>>}
     */
    private function validateManifest(
        ?array $manifest,
        array $publicRoutes,
        array $privatePrefixes,
        array $apiBackedRoutes,
        ?array $contentSources = null,
        array $privatePatterns = [],
    ): array
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

        if (($contentSources['sourceOfTruth'] ?? null) !== 'laravel_public_api') {
            $issues[] = [
                'code' => 'content_sources_not_laravel_api',
                'severity' => 'blocker',
                'context' => (string) ($contentSources['sourceOfTruth'] ?? 'missing'),
            ];
        }

        if (($contentSources['databaseQueriesFromNext'] ?? true) !== false) {
            $issues[] = [
                'code' => 'content_sources_allow_next_database_queries',
                'severity' => 'blocker',
                'context' => 'databaseQueriesFromNext',
            ];
        }

        if (is_array($contentSources) && is_array($contentSources['apiBackedRoutes'] ?? null)) {
            foreach ($contentSources['apiBackedRoutes'] as $route) {
                if (!is_array($route)) {
                    $issues[] = [
                        'code' => 'api_backed_route_invalid',
                        'severity' => 'blocker',
                        'context' => 'non-object',
                    ];
                    continue;
                }

                $routeKey = isset($route['routeKey']) ? (string) $route['routeKey'] : '';
                $endpoint = isset($route['endpoint']) ? (string) $route['endpoint'] : '';
                $method = isset($route['method']) ? (string) $route['method'] : '';

                if ($routeKey === '' || $endpoint === '' || $method === '') {
                    $issues[] = [
                        'code' => 'api_backed_route_missing_fields',
                        'severity' => 'blocker',
                        'context' => $routeKey !== '' ? $routeKey : ($endpoint !== '' ? $endpoint : 'unknown'),
                    ];
                }
            }
        }

        $patterns = [];
        $routeKeys = [];
        $routeParamsByKey = [];
        $privatePrefixSet = array_flip(array_filter($privatePrefixes, 'is_string'));
        $privatePatternSet = array_flip(array_filter($privatePatterns, 'is_string'));

        foreach (self::REQUIRED_VITE_PRIVATE_PREFIXES as $prefix) {
            if (!isset($privatePrefixSet[$prefix])) {
                $issues[] = [
                    'code' => 'vite_private_prefix_missing_required',
                    'severity' => 'blocker',
                    'context' => $prefix,
                ];
            }
        }

        foreach (self::REQUIRED_VITE_PRIVATE_PATTERNS as $pattern) {
            if (!isset($privatePatternSet[$pattern])) {
                $issues[] = [
                    'code' => 'vite_private_pattern_missing_required',
                    'severity' => 'blocker',
                    'context' => $pattern,
                ];
            }
        }

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

            if ($routeKey !== '') {
                $routeParamsByKey[$routeKey] = $this->extractRouteParams($pattern);
            }

            $firstSegment = strtok(ltrim($pattern, '/'), '/');
            if (is_string($firstSegment) && $firstSegment !== '' && isset($privatePrefixSet[$firstSegment])) {
                $issues[] = ['code' => 'public_route_collides_with_private_prefix', 'severity' => 'blocker', 'context' => $pattern];
            }

            if ($pattern !== '' && isset($privatePatternSet[$pattern])) {
                $issues[] = ['code' => 'public_route_collides_with_private_pattern', 'severity' => 'blocker', 'context' => $pattern];
            }
        }

        $apiBackedRouteKeys = [];

        foreach ($apiBackedRoutes as $route) {
            if (isset($apiBackedRouteKeys[$route['routeKey']])) {
                $issues[] = [
                    'code' => 'api_backed_route_duplicate_key',
                    'severity' => 'blocker',
                    'context' => $route['routeKey'],
                ];
            }
            $apiBackedRouteKeys[$route['routeKey']] = true;

            if (!isset($routeKeys[$route['routeKey']])) {
                $issues[] = [
                    'code' => 'api_backed_route_not_in_manifest',
                    'severity' => 'blocker',
                    'context' => $route['routeKey'],
                ];
            }

            if ($route['method'] !== 'GET') {
                $issues[] = [
                    'code' => 'api_backed_route_not_get',
                    'severity' => 'blocker',
                    'context' => $route['method'] . ' ' . $route['endpoint'],
                ];
            }

            if (!str_starts_with($route['endpoint'], '/v2/')) {
                $issues[] = [
                    'code' => 'api_backed_route_not_laravel_v2_endpoint',
                    'severity' => 'blocker',
                    'context' => $route['routeKey'],
                ];
            }

            if (str_contains($route['endpoint'], '?') || str_contains($route['endpoint'], '#')) {
                $issues[] = [
                    'code' => 'api_backed_route_endpoint_not_plain_path',
                    'severity' => 'blocker',
                    'context' => $route['routeKey'],
                ];
            }

            if ($this->hasUnsafeEndpointPathSegments($route['endpoint'])) {
                $issues[] = [
                    'code' => 'api_backed_route_endpoint_has_path_traversal',
                    'severity' => 'blocker',
                    'context' => $route['routeKey'],
                ];
            }

            if ($this->isPrivateLaravelV2Endpoint($route['endpoint'])) {
                $issues[] = [
                    'code' => 'api_backed_route_private_endpoint',
                    'severity' => 'blocker',
                    'context' => $route['routeKey'],
                ];
            }

            if (!$this->sameStringSet($routeParamsByKey[$route['routeKey']] ?? [], $this->extractEndpointParams($route['endpoint']))) {
                $issues[] = [
                    'code' => 'api_backed_route_param_mismatch',
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

    /**
     * @return array<int, string>
     */
    private function extractRouteParams(string $pattern): array
    {
        preg_match_all('/(?:^|\/):([A-Za-z0-9_]+)/', $pattern, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * @return array<int, string>
     */
    private function extractEndpointParams(string $endpoint): array
    {
        preg_match_all('/\{([A-Za-z0-9_]+)\}/', $endpoint, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    private function isPrivateLaravelV2Endpoint(string $endpoint): bool
    {
        foreach (self::PRIVATE_LARAVEL_V2_ENDPOINT_PREFIXES as $prefix) {
            if ($endpoint === $prefix || str_starts_with($endpoint, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    private function hasUnsafeEndpointPathSegments(string $endpoint): bool
    {
        if (str_contains($endpoint, '\\')) {
            return true;
        }

        foreach (explode('/', $endpoint) as $segment) {
            $normalizedSegment = strtolower($segment);

            if (
                $normalizedSegment === '.'
                || $normalizedSegment === '..'
                || $normalizedSegment === '%2e'
                || $normalizedSegment === '%2e%2e'
                || str_contains($normalizedSegment, '%2f')
                || str_contains($normalizedSegment, '%5c')
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $left
     * @param array<int, string> $right
     */
    private function sameStringSet(array $left, array $right): bool
    {
        sort($left);
        sort($right);

        return $left === $right;
    }
}
