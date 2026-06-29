<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

class NextPublicFrontendReadinessService
{
    private const REQUIRED_VITE_PRIVATE_PREFIXES = [
        'achievements',
        'activity',
        'admin',
        'auth',
        'broker',
        'chat',
        'connections',
        'dashboard',
        'exchanges',
        'feed',
        'federation',
        'goals',
        'group-exchanges',
        'leaderboard',
        'login',
        'matches',
        'me',
        'members',
        'messages',
        'nexus-score',
        'notifications',
        'onboarding',
        'password',
        'polls',
        'premium',
        'profile',
        'register',
        'reviews',
        'saved',
        'search',
        'settings',
        'skills',
        'super-admin',
        'users',
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
        '/advertise/campaigns',
        '/advertise/push-campaigns',
        '/groups/create',
        '/groups/edit/:id',
        '/caring-community/request-help',
        '/caring-community/offer-favour',
        '/caring-community/markt',
        '/caring-community/loyalty/history',
        '/caring-community/future-care-fund',
        '/caring-community/hour-transfer',
        '/caring-community/hour-gift',
        '/caring-community/safeguarding/report',
        '/caring-community/my-relationships',
        '/caring-community/my-trust-tier',
        '/caring-community/my-data-export',
        '/caring-community/safeguarding/my-reports',
        '/caring-community/providers',
        '/caring-community/warmth-pass',
        '/caring-community/caregiver',
        '/caring-community/caregiver/link',
        '/caring-community/caregiver/cover',
        '/caring-community/surveys',
        '/caring-community/surveys/:id',
        '/caring-community/projects',
        '/caring-community/projects/:id',
        '/caring-community/civic-digest',
        '/caring-community/success-stories',
        '/caring-community/feedback',
        '/clubs/:id/admin/import',
        '/clubs/:id/admin/dues',
        '/courses/my-learning',
        '/courses/instructor',
        '/courses/instructor/new',
        '/courses/instructor/:id/edit',
        '/courses/instructor/:id/analytics',
        '/courses/instructor/:id/grading',
        '/courses/:id/learn',
        '/federation/onboarding',
        '/group-exchanges/create',
        '/join/:code',
        '/newsletter/unsubscribe',
        '/partner-analytics/dashboard',
        '/pilot-inquiry',
        '/pilot-apply',
        '/pilot-apply/status/:token',
        '/podcasts/studio',
        '/jobs/new',
        '/jobs/create',
        '/jobs/:id/edit',
        '/jobs/:id/analytics',
        '/jobs/:id/kanban',
        '/jobs/employers/:userId',
        '/jobs/alerts',
        '/jobs/my-applications',
        '/jobs/talent-search',
        '/jobs/bias-audit',
        '/jobs/employer-onboarding',
        '/listings/new',
        '/listings/create',
        '/listings/:id/edit',
        '/listings/edit/:id',
        '/listings/:id/request-exchange',
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
        '/marketplace/seller/coupons',
        '/marketplace/seller/coupons/new',
        '/marketplace/seller/coupons/:id/edit',
        '/marketplace/:id/edit',
        '/organisations/new',
        '/organisations/register',
        '/organisations/:id/edit',
        '/volunteering/create',
        '/volunteering/guardian-consent/verify/:token',
        '/ideation/create',
        '/ideation/:id/edit',
        '/ideation/campaigns',
        '/ideation/campaigns/:id',
        '/ideation/outcomes',
        '/premium/manage',
        '/donations/:id/receipt',
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
        '/v2/coupons',
        '/v2/dashboard',
        '/v2/feed',
        '/v2/messages',
        '/v2/notifications',
        '/v2/settings',
        '/v2/super-admin',
        '/v2/wallet',
    ];

    private const FOUNDATION_PUBLIC_ROUTE_KEYS = [
        'home',
        'about',
        'features',
        'changelog',
        'help',
        'contact',
        'faq',
        'privacy',
        'privacyVersions',
        'terms',
        'termsVersions',
        'accessibility',
        'accessibilityVersions',
        'cookies',
        'cookiesVersions',
        'communityGuidelines',
        'communityGuidelinesVersions',
        'trustSafety',
        'acceptableUse',
        'acceptableUseVersions',
        'legal',
        'platformTerms',
        'platformPrivacy',
        'platformDisclaimer',
        'timebankingGuide',
        'developmentStatus',
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
        $edgeCanary = $this->edgeCanaryPreview($cutoverEnabled, $publicRoutes, $privatePrefixes, $privatePatterns);
        $apacheTemplateIncluded = (bool) ($edgeCanary['config_template']['included_by_deploy'] ?? true);

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
            'tenant_resolution' => $this->tenantResolutionContract(),
            'edge_canary' => $edgeCanary,
            'route_batches' => $this->routeBatches($publicRoutes, $privatePrefixes, $privatePatterns, $apiBackedRoutes),
            'cutover_artifacts' => $this->cutoverArtifactInventory(),
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
                    'npm run check:next-public:inert',
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
                ['key' => 'apache_canary_template_not_included', 'status' => $apacheTemplateIncluded ? 'blocker' : 'pass'],
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
            'cutover_gates' => $this->cutoverGates(),
            'operator_playbook' => $this->operatorPlaybook(),
        ];
    }

    /**
     * @return array{status: string, bootstrap_endpoint: string, bootstrap_route_status: string, source_of_truth: string, shared_host_slug_parameter: string, custom_domain_origin_forwarding: bool, next_queries_database: bool, examples: array<int, array{key: string, request_host: string, request_path: string, bootstrap_request: string, headers: array<int, string>}>}
     */
    private function tenantResolutionContract(): array
    {
        $bootstrapRouteStatus = $this->registeredPublicGetEndpointStatus('/v2/tenant/bootstrap');

        return [
            'status' => $bootstrapRouteStatus === 'public' ? 'pass' : 'blocker',
            'bootstrap_endpoint' => '/v2/tenant/bootstrap',
            'bootstrap_route_status' => $bootstrapRouteStatus,
            'source_of_truth' => 'laravel_tenant_bootstrap',
            'shared_host_slug_parameter' => 'slug',
            'custom_domain_origin_forwarding' => true,
            'next_queries_database' => false,
            'examples' => [
                [
                    'key' => 'shared_host_slug',
                    'request_host' => 'app.project-nexus.ie',
                    'request_path' => '/{tenantSlug}',
                    'bootstrap_request' => 'GET /v2/tenant/bootstrap?slug={tenantSlug}',
                    'headers' => ['Origin: https://app.project-nexus.ie'],
                ],
                [
                    'key' => 'custom_domain',
                    'request_host' => '<custom-domain>',
                    'request_path' => '/',
                    'bootstrap_request' => 'GET /v2/tenant/bootstrap',
                    'headers' => ['Origin: https://<custom-domain>'],
                ],
            ],
        ];
    }

    /**
     * @param array<int, mixed> $publicRoutes
     * @param array<int, mixed> $privatePrefixes
     * @param array<int, mixed> $privatePatterns
     * @return array{status: string, edge: string, routing_flag: string, routing_flag_enabled: bool, activation_available: bool, preview_only: bool, requires_explicit_cutover_instruction: bool, reviewed_config_required: bool, route_file_status: string, config_template: array{path: string, exists: bool, example_only: bool, included_by_deploy: bool, required_review_steps: array<int, string>}, route_audit: array{status: string, template_path: string, template_exists: bool, exact_path_count: int, public_only: bool, template_paths: array<int, string>, private_collisions: array<int, string>, unmatched_template_paths: array<int, string>, unsupported_rules: array<int, string>}, guardrails: array<int, string>}
     */
    private function edgeCanaryPreview(
        bool $cutoverEnabled,
        array $publicRoutes,
        array $privatePrefixes,
        array $privatePatterns,
    ): array
    {
        $templatePath = 'scripts/deploy/apache/next-public-foundation-canary.conf.example';

        return [
            'status' => $cutoverEnabled ? 'blocker' : 'blocked',
            'edge' => 'apache_plesk',
            'routing_flag' => 'NEXT_PUBLIC_FRONTEND_ROUTING_ENABLED',
            'routing_flag_enabled' => $cutoverEnabled,
            'activation_available' => false,
            'preview_only' => true,
            'requires_explicit_cutover_instruction' => true,
            'reviewed_config_required' => true,
            'route_file_status' => 'not_configured',
            'config_template' => [
                'path' => $templatePath,
                'exists' => File::isFile(base_path($templatePath)),
                'example_only' => true,
                'included_by_deploy' => $this->fileContains(
                    base_path('scripts/deploy/bluegreen-deploy.sh'),
                    'next-public-foundation-canary.conf.example',
                ) || $this->fileContains(
                    base_path('compose.bluegreen.yml'),
                    'next-public-foundation-canary.conf.example',
                ),
                'required_review_steps' => [
                    'explicit_cutover_instruction_required',
                    'next_shadow_checks_required',
                    'react_private_regression_required',
                    'apache_configtest_required',
                    'prerender_fallback_must_remain',
                ],
            ],
            'route_audit' => $this->apacheCanaryRouteAudit($templatePath, $publicRoutes, $privatePrefixes, $privatePatterns),
            'guardrails' => [
                'do_not_edit_plesk_vhosts_directly',
                'do_not_enable_routing_flag_without_cutover_instruction',
                'public_routes_only',
                'do_not_remove_prerender',
            ],
        ];
    }

    /**
     * @param array<int, mixed> $publicRoutes
     * @param array<int, mixed> $privatePrefixes
     * @param array<int, mixed> $privatePatterns
     * @return array{status: string, template_path: string, template_exists: bool, exact_path_count: int, public_only: bool, template_paths: array<int, string>, private_collisions: array<int, string>, unmatched_template_paths: array<int, string>, unsupported_rules: array<int, string>}
     */
    private function apacheCanaryRouteAudit(
        string $templatePath,
        array $publicRoutes,
        array $privatePrefixes,
        array $privatePatterns,
    ): array {
        [$templatePaths, $unsupportedRules] = $this->apacheCanaryTemplatePaths($templatePath);
        $publicPatterns = [];

        foreach ($publicRoutes as $route) {
            if (is_array($route) && is_string($route['pattern'] ?? null)) {
                $publicPatterns[(string) $route['pattern']] = true;
            }
        }

        $unmatchedTemplatePaths = [];
        $privateCollisions = [];

        foreach ($templatePaths as $path) {
            if (!isset($publicPatterns[$path])) {
                $unmatchedTemplatePaths[] = $path;
            }

            if ($this->pathCollidesWithPrivateOwnership($path, $privatePrefixes, $privatePatterns)) {
                $privateCollisions[] = $path;
            }
        }

        $templateExists = File::isFile(base_path($templatePath));
        $publicOnly = $templateExists
            && $unmatchedTemplatePaths === []
            && $privateCollisions === []
            && $unsupportedRules === [];

        return [
            'status' => $publicOnly ? 'pass' : 'blocker',
            'template_path' => $templatePath,
            'template_exists' => $templateExists,
            'exact_path_count' => count($templatePaths),
            'public_only' => $publicOnly,
            'template_paths' => $templatePaths,
            'private_collisions' => $privateCollisions,
            'unmatched_template_paths' => $unmatchedTemplatePaths,
            'unsupported_rules' => $unsupportedRules,
        ];
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function apacheCanaryTemplatePaths(string $templatePath): array
    {
        $fullPath = base_path($templatePath);

        if (!File::isFile($fullPath)) {
            return [[], ['template_missing']];
        }

        $paths = [];
        $unsupportedRules = [];
        $lines = preg_split('/\R/', (string) File::get($fullPath)) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || !str_starts_with($line, 'RewriteRule ')) {
                continue;
            }

            $parts = preg_split('/\s+/', $line);
            $sourcePattern = is_array($parts) ? (string) ($parts[1] ?? '') : '';
            $expandedPaths = $this->expandApacheRewriteSourcePattern($sourcePattern);

            if ($expandedPaths === []) {
                $unsupportedRules[] = $sourcePattern;
                continue;
            }

            foreach ($expandedPaths as $path) {
                $paths[] = $path;
            }
        }

        return [array_values(array_unique($paths)), $unsupportedRules];
    }

    /**
     * @return array<int, string>
     */
    private function expandApacheRewriteSourcePattern(string $sourcePattern): array
    {
        $body = $sourcePattern;

        if (!str_starts_with($body, '^') || !str_ends_with($body, '$')) {
            return [];
        }

        $body = substr($body, 1, -1);

        if (str_ends_with($body, '/?')) {
            $body = substr($body, 0, -2);
        }

        if ($body === '/') {
            return ['/'];
        }

        if (!preg_match('/^\([^()]+\)$/', $body) && !preg_match('/\([^()]+\)/', $body)) {
            return str_contains($body, '(') || str_contains($body, ')') ? [] : [$body];
        }

        if (!preg_match('/^(?<prefix>.*)\((?<options>[^()]+)\)(?<suffix>.*)$/', $body, $matches)) {
            return [];
        }

        $paths = [];
        $prefix = (string) $matches['prefix'];
        $suffix = (string) $matches['suffix'];

        foreach (explode('|', (string) $matches['options']) as $option) {
            if ($option === '' || preg_match('/[^A-Za-z0-9_-]/', $option)) {
                return [];
            }

            $paths[] = $prefix . $option . $suffix;
        }

        return $paths;
    }

    /**
     * @param array<int, mixed> $privatePrefixes
     * @param array<int, mixed> $privatePatterns
     */
    private function pathCollidesWithPrivateOwnership(string $path, array $privatePrefixes, array $privatePatterns): bool
    {
        $firstSegment = explode('/', trim($path, '/'))[0] ?? '';
        $privatePrefixSet = array_flip(array_filter($privatePrefixes, 'is_string'));

        if ($firstSegment !== '' && isset($privatePrefixSet[$firstSegment])) {
            return true;
        }

        foreach ($privatePatterns as $privatePattern) {
            if (!is_string($privatePattern)) {
                continue;
            }

            $regex = $this->routePatternRegex($privatePattern);

            if (preg_match($regex, $path) === 1) {
                return true;
            }
        }

        return false;
    }

    private function routePatternRegex(string $pattern): string
    {
        if ($pattern === '/') {
            return '#^/$#';
        }

        $segments = array_filter(explode('/', trim($pattern, '/')), static fn (string $segment): bool => $segment !== '');
        $regexSegments = array_map(
            static fn (string $segment): string => str_starts_with($segment, ':') ? '[^/]+' : preg_quote($segment, '#'),
            $segments,
        );

        return '#^/' . implode('/', $regexSegments) . '$#';
    }

    /**
     * @param array<int, mixed> $publicRoutes
     * @param array<int, mixed> $privatePrefixes
     * @param array<int, mixed> $privatePatterns
     * @param array<int, array{routeKey: string, endpoint: string, method: string}> $apiBackedRoutes
     * @return array<int, array{key: string, status: string, route_count: int, route_keys: array<int, string>, blockers: array<int, string>, verification_commands: array<int, string>}>
     */
    private function routeBatches(
        array $publicRoutes,
        array $privatePrefixes,
        array $privatePatterns,
        array $apiBackedRoutes,
    ): array {
        $manifestRouteKeys = [];

        foreach ($publicRoutes as $route) {
            if (is_array($route) && isset($route['routeKey'])) {
                $manifestRouteKeys[] = (string) $route['routeKey'];
            }
        }

        $manifestRouteKeySet = array_flip($manifestRouteKeys);
        $foundationRouteKeys = array_values(array_filter(
            self::FOUNDATION_PUBLIC_ROUTE_KEYS,
            static fn (string $routeKey): bool => isset($manifestRouteKeySet[$routeKey]),
        ));
        $apiBackedRouteKeys = array_values(array_unique(array_column($apiBackedRoutes, 'routeKey')));

        return [
            [
                'key' => 'foundation_public_pages',
                'status' => 'blocked',
                'route_count' => count($foundationRouteKeys),
                'route_keys' => $foundationRouteKeys,
                'blockers' => ['manual_shadow_review_required', 'explicit_cutover_instruction_required'],
                'verification_commands' => ['npm --prefix next-public-frontend run check:no-js-html'],
            ],
            [
                'key' => 'api_backed_public_content',
                'status' => 'blocked',
                'route_count' => count($apiBackedRouteKeys),
                'route_keys' => $apiBackedRouteKeys,
                'blockers' => ['public_api_parity_required', 'manual_shadow_review_required'],
                'verification_commands' => [
                    'npm --prefix next-public-frontend run check',
                    'vendor/bin/phpunit --no-coverage tests/Laravel/Unit/Services/NextPublicFrontendReadinessServiceTest.php tests/Laravel/Feature/Controllers/AdminNextPublicFrontendControllerTest.php',
                ],
            ],
            [
                'key' => 'vite_private_retained',
                'status' => 'pass',
                'route_count' => count($privatePrefixes) + count($privatePatterns),
                'route_keys' => [],
                'blockers' => [],
                'verification_commands' => [
                    'npm --prefix react-frontend run build',
                    'cd react-frontend && npx tsc --noEmit',
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{key: string, status: string, blockers: array<int, string>, verification_commands: array<int, string>}>
     */
    private function cutoverGates(): array
    {
        return [
            [
                'key' => 'verify_next_shadow_build',
                'status' => 'blocker',
                'blockers' => ['manual_verification_required'],
                'verification_commands' => ['npm --prefix next-public-frontend run check'],
            ],
            [
                'key' => 'verify_no_js_public_html',
                'status' => 'blocker',
                'blockers' => ['manual_verification_required', 'route_parity_required'],
                'verification_commands' => ['npm --prefix next-public-frontend run check:no-js-html'],
            ],
            [
                'key' => 'verify_private_vite_regression',
                'status' => 'blocker',
                'blockers' => ['manual_verification_required'],
                'verification_commands' => [
                    'npm --prefix react-frontend run build',
                    'cd react-frontend && npx tsc --noEmit',
                ],
            ],
            [
                'key' => 'prepare_apache_canary_routes',
                'status' => 'blocker',
                'blockers' => ['explicit_cutover_instruction_required', 'edge_routes_not_configured'],
                'verification_commands' => [],
            ],
            [
                'key' => 'enable_canary_for_public_routes_only',
                'status' => 'blocker',
                'blockers' => ['explicit_cutover_instruction_required', 'route_parity_required'],
                'verification_commands' => [],
            ],
            [
                'key' => 'monitor_and_keep_prerender_fallback',
                'status' => 'blocker',
                'blockers' => ['cutover_not_active', 'prerender_fallback_must_remain'],
                'verification_commands' => [],
            ],
        ];
    }

    /**
     * @return array{activation_available: bool, requires_explicit_cutover_instruction: bool, no_production_effect: bool, stages: array<int, array{key: string, status: string, commands: array<int, string>, notes: array<int, string>}>}
     */
    private function operatorPlaybook(): array
    {
        return [
            'activation_available' => false,
            'requires_explicit_cutover_instruction' => true,
            'no_production_effect' => true,
            'stages' => [
                [
                    'key' => 'verify_shadow_module',
                    'status' => 'blocked',
                    'commands' => [
                        'npm --prefix next-public-frontend run check',
                        'vendor/bin/phpunit --no-coverage tests/Laravel/Unit/Services/NextPublicFrontendReadinessServiceTest.php tests/Laravel/Feature/Controllers/AdminNextPublicFrontendControllerTest.php',
                    ],
                    'notes' => ['shadow_only', 'no_activation_control'],
                ],
                [
                    'key' => 'prepare_reviewed_edge_config',
                    'status' => 'blocked',
                    'commands' => [],
                    'notes' => ['explicit_cutover_instruction_required', 'no_activation_control'],
                ],
                [
                    'key' => 'run_private_route_regression',
                    'status' => 'blocked',
                    'commands' => [
                        'npm --prefix react-frontend run build',
                        'cd react-frontend && npx tsc --noEmit',
                    ],
                    'notes' => ['vite_private_routes_remain_primary'],
                ],
                [
                    'key' => 'canary_public_routes_only',
                    'status' => 'blocked',
                    'commands' => [],
                    'notes' => ['explicit_cutover_instruction_required', 'public_routes_only'],
                ],
                [
                    'key' => 'monitor_with_prerender_fallback',
                    'status' => 'blocked',
                    'commands' => [],
                    'notes' => ['do_not_remove_prerender', 'rollback_path_required'],
                ],
            ],
        ];
    }

    /**
     * @return array{production_effect: string, activation_available: bool, items: array<int, array{key: string, path: string, exists: bool, category: string, production_effect: string}>, required_commands: array<int, array{key: string, command: string, required_before_cutover: bool}>}
     */
    private function cutoverArtifactInventory(): array
    {
        $artifactPaths = [
            'route_ownership_manifest' => 'next-public-frontend/route-ownership.json',
            'content_sources_manifest' => 'next-public-frontend/content-sources.json',
            'apache_canary_template' => 'scripts/deploy/apache/next-public-foundation-canary.conf.example',
            'shadow_compose_profile' => 'compose.bluegreen.yml',
            'routing_flag_config' => 'config/app.php',
            'prerender_fallback' => 'react-frontend/scripts/prerender.mjs',
        ];

        return [
            'production_effect' => 'none',
            'activation_available' => false,
            'items' => [
                $this->cutoverArtifact('route_ownership_manifest', $artifactPaths['route_ownership_manifest'], 'manifest'),
                $this->cutoverArtifact('content_sources_manifest', $artifactPaths['content_sources_manifest'], 'manifest'),
                $this->cutoverArtifact('apache_canary_template', $artifactPaths['apache_canary_template'], 'edge_config'),
                $this->cutoverArtifact('shadow_compose_profile', $artifactPaths['shadow_compose_profile'], 'runtime'),
                $this->cutoverArtifact('routing_flag_config', $artifactPaths['routing_flag_config'], 'guard'),
                $this->cutoverArtifact('prerender_fallback', $artifactPaths['prerender_fallback'], 'fallback'),
            ],
            'required_commands' => [
                [
                    'key' => 'next_shadow_checks',
                    'command' => 'npm --prefix next-public-frontend run check',
                    'required_before_cutover' => true,
                ],
                [
                    'key' => 'no_js_public_html',
                    'command' => 'npm --prefix next-public-frontend run check:no-js-html',
                    'required_before_cutover' => true,
                ],
                [
                    'key' => 'next_public_inertness',
                    'command' => 'npm run check:next-public:inert',
                    'required_before_cutover' => true,
                ],
                [
                    'key' => 'react_private_regression',
                    'command' => 'npm --prefix react-frontend run build',
                    'required_before_cutover' => true,
                ],
                [
                    'key' => 'php_readiness_contract',
                    'command' => 'vendor/bin/phpunit --no-coverage tests/Laravel/Unit/Services/NextPublicFrontendReadinessServiceTest.php tests/Laravel/Feature/Controllers/AdminNextPublicFrontendControllerTest.php',
                    'required_before_cutover' => true,
                ],
                [
                    'key' => 'apache_configtest',
                    'command' => 'apachectl -t',
                    'required_before_cutover' => true,
                ],
            ],
        ];
    }

    /**
     * @return array{key: string, path: string, exists: bool, category: string, production_effect: string}
     */
    private function cutoverArtifact(string $key, string $path, string $category): array
    {
        return [
            'key' => $key,
            'path' => $path,
            'exists' => File::exists(base_path($path)),
            'category' => $category,
            'production_effect' => 'none',
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

    private function fileContains(string $path, string $needle): bool
    {
        return File::isFile($path)
            && str_contains((string) File::get($path), $needle);
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

            $publicRouteStatus = $this->registeredPublicGetEndpointStatus($route['endpoint']);

            if ($publicRouteStatus === 'missing') {
                $issues[] = [
                    'code' => 'api_backed_route_not_registered',
                    'severity' => 'blocker',
                    'context' => $route['routeKey'],
                ];
            }

            if ($publicRouteStatus === 'auth') {
                $issues[] = [
                    'code' => 'api_backed_route_requires_auth',
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

    private function registeredPublicGetEndpointStatus(string $endpoint): string
    {
        $routeUri = 'api/' . ltrim($endpoint, '/');

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if ($route->uri() !== $routeUri || !in_array('GET', $route->methods(), true)) {
                continue;
            }

            return in_array('auth:sanctum', $route->gatherMiddleware(), true)
                && !in_array('auth:sanctum', $route->excludedMiddleware(), true)
                    ? 'auth'
                    : 'public';
        }

        return 'missing';
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
