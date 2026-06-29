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
        );
    }
}
