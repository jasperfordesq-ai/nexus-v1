<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\NextPublicFrontendReadinessService;
use ReflectionMethod;
use Tests\Laravel\TestCase;

class NextPublicFrontendReadinessServiceTest extends TestCase
{
    public function test_summary_marks_cutover_env_flag_as_blocker(): void
    {
        config(['app.next_public_frontend_routing_enabled' => true]);

        $summary = (new NextPublicFrontendReadinessService())->summary();

        $safetyChecks = array_column($summary['safety_checks'], 'status', 'key');

        $this->assertTrue($summary['production_routing']['route_cutover_enabled']);
        $this->assertSame('blocker', $safetyChecks['route_cutover_disabled']);
    }

    public function test_summary_reports_cutover_gates_as_blocked_until_manual_cutover(): void
    {
        $summary = (new NextPublicFrontendReadinessService())->summary();

        $gates = array_column($summary['cutover_gates'], null, 'key');

        $this->assertSame(
            [
                'verify_next_shadow_build',
                'verify_no_js_public_html',
                'verify_private_vite_regression',
                'prepare_apache_canary_routes',
                'enable_canary_for_public_routes_only',
                'monitor_and_keep_prerender_fallback',
            ],
            array_keys($gates),
        );
        $this->assertSame('blocker', $gates['prepare_apache_canary_routes']['status']);
        $this->assertContains('explicit_cutover_instruction_required', $gates['prepare_apache_canary_routes']['blockers']);
        $this->assertContains('edge_routes_not_configured', $gates['prepare_apache_canary_routes']['blockers']);
        $this->assertSame('blocker', $gates['enable_canary_for_public_routes_only']['status']);
        $this->assertContains('explicit_cutover_instruction_required', $gates['enable_canary_for_public_routes_only']['blockers']);
        $this->assertSame(
            ['npm --prefix next-public-frontend run check'],
            $gates['verify_next_shadow_build']['verification_commands'],
        );
        $this->assertContains(
            'npm --prefix react-frontend run build',
            $gates['verify_private_vite_regression']['verification_commands'],
        );
    }

    public function test_summary_reports_operator_playbook_without_activation_controls(): void
    {
        $summary = (new NextPublicFrontendReadinessService())->summary();

        $playbook = $summary['operator_playbook'];
        $stages = array_column($playbook['stages'], null, 'key');

        $this->assertFalse($playbook['activation_available']);
        $this->assertTrue($playbook['requires_explicit_cutover_instruction']);
        $this->assertTrue($playbook['no_production_effect']);
        $this->assertSame(
            [
                'verify_shadow_module',
                'prepare_reviewed_edge_config',
                'run_private_route_regression',
                'canary_public_routes_only',
                'monitor_with_prerender_fallback',
            ],
            array_keys($stages),
        );
        $this->assertSame('blocked', $stages['prepare_reviewed_edge_config']['status']);
        $this->assertContains('no_activation_control', $stages['prepare_reviewed_edge_config']['notes']);
        $this->assertContains('do_not_remove_prerender', $stages['monitor_with_prerender_fallback']['notes']);
        $this->assertContains('npm --prefix next-public-frontend run check', $stages['verify_shadow_module']['commands']);
    }

    public function test_manifest_validation_blocks_api_routes_outside_laravel_v2_public_api(): void
    {
        $validation = $this->validateManifest([
            'mode' => 'shadow',
        ], [
            [
                'pattern' => '/events',
                'routeKey' => 'events',
                'labelKey' => 'pages.events.title',
            ],
        ], [], [
            [
                'routeKey' => 'events',
                'endpoint' => 'https://example.test/v2/events',
                'method' => 'GET',
            ],
        ]);

        $this->assertSame('blocker', $validation['status']);
        $this->assertContains([
            'code' => 'api_backed_route_not_laravel_v2_endpoint',
            'severity' => 'blocker',
            'context' => 'events',
        ], $validation['issues']);
    }

    public function test_manifest_validation_blocks_api_routes_in_private_laravel_v2_namespaces(): void
    {
        $validation = $this->validateManifest([
            'mode' => 'shadow',
        ], [
            [
                'pattern' => '/events',
                'routeKey' => 'events',
                'labelKey' => 'pages.events.title',
            ],
        ], [], [
            [
                'routeKey' => 'events',
                'endpoint' => '/v2/admin/events',
                'method' => 'GET',
            ],
        ]);

        $this->assertSame('blocker', $validation['status']);
        $this->assertContains([
            'code' => 'api_backed_route_private_endpoint',
            'severity' => 'blocker',
            'context' => 'events',
        ], $validation['issues']);
    }

    public function test_manifest_validation_blocks_api_routes_in_auth_only_coupon_namespace(): void
    {
        $validation = $this->validateManifest([
            'mode' => 'shadow',
        ], [
            [
                'pattern' => '/events',
                'routeKey' => 'events',
                'labelKey' => 'pages.events.title',
            ],
        ], [], [
            [
                'routeKey' => 'events',
                'endpoint' => '/v2/coupons',
                'method' => 'GET',
            ],
        ]);

        $this->assertSame('blocker', $validation['status']);
        $this->assertContains([
            'code' => 'api_backed_route_private_endpoint',
            'severity' => 'blocker',
            'context' => 'events',
        ], $validation['issues']);
    }

    public function test_manifest_validation_blocks_unregistered_laravel_api_routes(): void
    {
        $validation = $this->validateManifest([
            'mode' => 'shadow',
        ], [
            [
                'pattern' => '/events',
                'routeKey' => 'events',
                'labelKey' => 'pages.events.title',
            ],
        ], [], [
            [
                'routeKey' => 'events',
                'endpoint' => '/v2/not-a-real-public-content-source',
                'method' => 'GET',
            ],
        ]);

        $this->assertSame('blocker', $validation['status']);
        $this->assertContains([
            'code' => 'api_backed_route_not_registered',
            'severity' => 'blocker',
            'context' => 'events',
        ], $validation['issues']);
    }

    public function test_manifest_validation_blocks_registered_api_routes_that_require_auth(): void
    {
        $validation = $this->validateManifest([
            'mode' => 'shadow',
        ], [
            [
                'pattern' => '/jobs',
                'routeKey' => 'jobs',
                'labelKey' => 'pages.jobs.title',
            ],
        ], [], [
            [
                'routeKey' => 'jobs',
                'endpoint' => '/v2/jobs/my-applications',
                'method' => 'GET',
            ],
        ]);

        $this->assertSame('blocker', $validation['status']);
        $this->assertContains([
            'code' => 'api_backed_route_requires_auth',
            'severity' => 'blocker',
            'context' => 'jobs',
        ], $validation['issues']);
    }

    public function test_manifest_validation_blocks_api_routes_with_query_strings_or_fragments(): void
    {
        $validation = $this->validateManifest([
            'mode' => 'shadow',
        ], [
            [
                'pattern' => '/events',
                'routeKey' => 'events',
                'labelKey' => 'pages.events.title',
            ],
        ], [], [
            [
                'routeKey' => 'events',
                'endpoint' => '/v2/events?include_private=1',
                'method' => 'GET',
            ],
        ]);

        $this->assertSame('blocker', $validation['status']);
        $this->assertContains([
            'code' => 'api_backed_route_endpoint_not_plain_path',
            'severity' => 'blocker',
            'context' => 'events',
        ], $validation['issues']);
    }

    /**
     * @dataProvider unsafeEndpointProvider
     */
    public function test_manifest_validation_blocks_api_routes_with_path_traversal_segments(string $endpoint): void
    {
        $validation = $this->validateManifest([
            'mode' => 'shadow',
        ], [
            [
                'pattern' => '/events',
                'routeKey' => 'events',
                'labelKey' => 'pages.events.title',
            ],
        ], [], [
            [
                'routeKey' => 'events',
                'endpoint' => $endpoint,
                'method' => 'GET',
            ],
        ]);

        $this->assertSame('blocker', $validation['status']);
        $this->assertContains([
            'code' => 'api_backed_route_endpoint_has_path_traversal',
            'severity' => 'blocker',
            'context' => 'events',
        ], $validation['issues']);
    }

    /**
     * @return array<int, array{0: string}>
     */
    public static function unsafeEndpointProvider(): array
    {
        return [
            ['/v2/../admin/events'],
            ['/v2/%2e%2e/admin/events'],
            ['/v2/events%2fadmin'],
        ];
    }

    public function test_manifest_validation_blocks_non_get_api_backed_routes(): void
    {
        $validation = $this->validateManifest([
            'mode' => 'shadow',
        ], [
            [
                'pattern' => '/events',
                'routeKey' => 'events',
                'labelKey' => 'pages.events.title',
            ],
        ], [], [
            [
                'routeKey' => 'events',
                'endpoint' => '/v2/events',
                'method' => 'POST',
            ],
        ]);

        $this->assertSame('blocker', $validation['status']);
        $this->assertContains([
            'code' => 'api_backed_route_not_get',
            'severity' => 'blocker',
            'context' => 'POST /v2/events',
        ], $validation['issues']);
    }

    public function test_manifest_validation_blocks_api_route_parameter_drift(): void
    {
        $validation = $this->validateManifest([
            'mode' => 'shadow',
        ], [
            [
                'pattern' => '/events/:id',
                'routeKey' => 'eventDetail',
                'labelKey' => 'pages.eventDetail.title',
            ],
        ], [], [
            [
                'routeKey' => 'eventDetail',
                'endpoint' => '/v2/events/{slug}',
                'method' => 'GET',
            ],
        ]);

        $this->assertSame('blocker', $validation['status']);
        $this->assertContains([
            'code' => 'api_backed_route_param_mismatch',
            'severity' => 'blocker',
            'context' => 'eventDetail',
        ], $validation['issues']);
    }

    public function test_manifest_validation_blocks_duplicate_api_backed_route_keys(): void
    {
        $validation = $this->validateManifest([
            'mode' => 'shadow',
        ], [
            [
                'pattern' => '/events',
                'routeKey' => 'events',
                'labelKey' => 'pages.events.title',
            ],
        ], [], [
            [
                'routeKey' => 'events',
                'endpoint' => '/v2/events',
                'method' => 'GET',
            ],
            [
                'routeKey' => 'events',
                'endpoint' => '/v2/events/archive',
                'method' => 'GET',
            ],
        ]);

        $this->assertSame('blocker', $validation['status']);
        $this->assertContains([
            'code' => 'api_backed_route_duplicate_key',
            'severity' => 'blocker',
            'context' => 'events',
        ], $validation['issues']);
    }

    public function test_manifest_validation_blocks_missing_required_private_prefixes(): void
    {
        $validation = $this->validateManifest([
            'mode' => 'shadow',
        ], [], ['dashboard', 'admin'], []);

        $this->assertSame('blocker', $validation['status']);
        $this->assertContains([
            'code' => 'vite_private_prefix_missing_required',
            'severity' => 'blocker',
            'context' => 'login',
        ], $validation['issues']);
    }

    public function test_manifest_validation_blocks_missing_required_private_patterns(): void
    {
        $validation = $this->validateManifest([
            'mode' => 'shadow',
        ], [], [], [], null, [
            '/events/edit/:id',
        ]);

        $this->assertSame('blocker', $validation['status']);
        $this->assertContains([
            'code' => 'vite_private_pattern_missing_required',
            'severity' => 'blocker',
            'context' => '/events/create',
        ], $validation['issues']);
    }

    public function test_manifest_validation_blocks_public_routes_that_collide_with_private_patterns(): void
    {
        $validation = $this->validateManifest([
            'mode' => 'shadow',
        ], [
            [
                'pattern' => '/events/create',
                'routeKey' => 'eventCreate',
                'labelKey' => 'pages.events.title',
            ],
        ], [], [], null, [
            '/events/create',
        ]);

        $this->assertSame('blocker', $validation['status']);
        $this->assertContains([
            'code' => 'public_route_collides_with_private_pattern',
            'severity' => 'blocker',
            'context' => '/events/create',
        ], $validation['issues']);
    }

    public function test_manifest_validation_blocks_content_sources_outside_laravel_public_api(): void
    {
        $validation = $this->validateManifest([
            'mode' => 'shadow',
        ], [], [], [], [
            'sourceOfTruth' => 'next_database',
            'databaseQueriesFromNext' => false,
        ]);

        $this->assertSame('blocker', $validation['status']);
        $this->assertContains([
            'code' => 'content_sources_not_laravel_api',
            'severity' => 'blocker',
            'context' => 'next_database',
        ], $validation['issues']);
    }

    public function test_manifest_validation_blocks_next_database_queries(): void
    {
        $validation = $this->validateManifest([
            'mode' => 'shadow',
        ], [], [], [], [
            'sourceOfTruth' => 'laravel_public_api',
            'databaseQueriesFromNext' => true,
        ]);

        $this->assertSame('blocker', $validation['status']);
        $this->assertContains([
            'code' => 'content_sources_allow_next_database_queries',
            'severity' => 'blocker',
            'context' => 'databaseQueriesFromNext',
        ], $validation['issues']);
    }

    public function test_manifest_validation_blocks_invalid_api_backed_route_entries(): void
    {
        $validation = $this->validateManifest([
            'mode' => 'shadow',
        ], [], [], [], [
            'sourceOfTruth' => 'laravel_public_api',
            'databaseQueriesFromNext' => false,
            'apiBackedRoutes' => ['not-an-object'],
        ]);

        $this->assertSame('blocker', $validation['status']);
        $this->assertContains([
            'code' => 'api_backed_route_invalid',
            'severity' => 'blocker',
            'context' => 'non-object',
        ], $validation['issues']);
    }

    public function test_manifest_validation_blocks_api_backed_route_entries_with_missing_fields(): void
    {
        $validation = $this->validateManifest([
            'mode' => 'shadow',
        ], [], [], [], [
            'sourceOfTruth' => 'laravel_public_api',
            'databaseQueriesFromNext' => false,
            'apiBackedRoutes' => [
                [
                    'routeKey' => 'events',
                    'method' => 'GET',
                ],
            ],
        ]);

        $this->assertSame('blocker', $validation['status']);
        $this->assertContains([
            'code' => 'api_backed_route_missing_fields',
            'severity' => 'blocker',
            'context' => 'events',
        ], $validation['issues']);
    }

    /**
     * @param array<string, mixed>|null $manifest
     * @param array<int, mixed> $publicRoutes
     * @param array<int, mixed> $privatePrefixes
     * @param array<int, array{routeKey: string, endpoint: string, method: string}> $apiBackedRoutes
     * @param array<string, mixed>|null $contentSources
     * @return array{status: string, issues: array<int, array<string, string>>}
     */
    private function validateManifest(
        ?array $manifest,
        array $publicRoutes,
        array $privatePrefixes,
        array $apiBackedRoutes,
        ?array $contentSources = null,
        ?array $privatePatterns = null,
    ): array
    {
        $method = new ReflectionMethod(NextPublicFrontendReadinessService::class, 'validateManifest');
        $method->setAccessible(true);

        return $method->invoke(
            new NextPublicFrontendReadinessService(),
            $manifest,
            $publicRoutes,
            $privatePrefixes,
            $apiBackedRoutes,
            $contentSources,
            $privatePatterns ?? [],
        );
    }
}
