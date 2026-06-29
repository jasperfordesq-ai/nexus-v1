<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

class AdminNextPublicFrontendControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_readiness_requires_authentication(): void
    {
        $response = $this->apiGet('/v2/admin/config/next-public-frontend');

        $response->assertStatus(401);
    }

    public function test_readiness_requires_admin(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/config/next-public-frontend');

        $response->assertStatus(403);
    }

    public function test_readiness_reports_shadow_mode_without_cutover(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/config/next-public-frontend');

        $response->assertStatus(200);
        $response->assertJsonPath('data.mode', 'shadow');
        $response->assertJsonPath('data.production_routing.active', false);
        $response->assertJsonPath('data.production_routing.route_cutover_enabled', false);
        $response->assertJsonPath('data.prerender.status', 'unchanged');
        $response->assertJsonPath('data.prerender.fallback_retained', true);
        $response->assertJsonPath('data.app.exists', true);
        $response->assertJsonPath('data.app.lockfile_exists', true);
        $response->assertJsonPath('data.app.package_scripts.build', true);
        $response->assertJsonPath('data.app.package_scripts.start', true);
        $response->assertJsonPath('data.app.package_scripts.check_manifests', true);
        $response->assertJsonPath('data.app.package_scripts.check_no_js_html', true);
        $response->assertJsonPath('data.shadow_runtime.compose_profile', 'next-public-shadow');
        $response->assertJsonPath('data.shadow_runtime.compose_profile_configured', true);
        $response->assertJsonPath('data.shadow_runtime.port_env', 'NEXUS_NEXT_PUBLIC_PORT');
        $response->assertJsonPath('data.manifest.route_counts.public_routes', 61);
        $response->assertJsonPath('data.manifest.route_counts.api_backed_public_routes', 20);
        $response->assertJsonPath('data.manifest.route_counts.vite_private_prefixes', 25);
        $response->assertJsonPath('data.manifest.route_counts.vite_private_patterns', 71);
        $response->assertJsonPath('data.manifest.validation.status', 'pass');
        $response->assertJsonPath('data.manifest.validation.issues', []);
        $response->assertJsonPath('data.content_sources.source_of_truth', 'laravel_public_api');
        $response->assertJsonPath('data.content_sources.database_queries_from_next', false);
        $response->assertJsonPath('data.content_sources.manifest_exists', true);
        $response->assertJsonPath('data.content_sources.manifest_path', 'next-public-frontend/content-sources.json');

        $payload = $response->json('data');
        $publicPatterns = array_column($payload['manifest']['public_routes'], 'pattern');
        $apiBackedRouteKeys = array_column($payload['content_sources']['api_backed_routes'], 'routeKey');
        $routeReadiness = $payload['manifest']['route_readiness'];
        $routeReadinessByKey = [];

        foreach ($routeReadiness as $route) {
            $routeReadinessByKey[$route['routeKey']] = $route;
        }

        $this->assertContains('/about', $publicPatterns);
        $this->assertContains('/features', $publicPatterns);
        $this->assertContains('/blog/:slug', $publicPatterns);
        $this->assertContains('/terms/versions', $publicPatterns);
        $this->assertContains('/cookies', $publicPatterns);
        $this->assertContains('/legal', $publicPatterns);
        $this->assertContains('/platform/privacy', $publicPatterns);
        $this->assertContains('/listings/:id', $publicPatterns);
        $this->assertContains('/marketplace/:id', $publicPatterns);
        $this->assertContains('/marketplace/search', $publicPatterns);
        $this->assertContains('/marketplace/map', $publicPatterns);
        $this->assertContains('/developers', $publicPatterns);
        $this->assertContains('/regional-analytics', $publicPatterns);
        $this->assertContains('/volunteering/opportunities/:id', $publicPatterns);
        $this->assertContains('/ideation/:id', $publicPatterns);
        $this->assertContains('blog-index', $apiBackedRouteKeys);
        $this->assertContains('cms-page', $apiBackedRouteKeys);
        $this->assertContains('listings', $apiBackedRouteKeys);
        $this->assertContains('listingDetail', $apiBackedRouteKeys);
        $this->assertContains('resources', $apiBackedRouteKeys);
        $this->assertContains('marketplaceDetail', $apiBackedRouteKeys);
        $this->assertContains('volunteeringOpportunityDetail', $apiBackedRouteKeys);
        $this->assertContains('ideationDetail', $apiBackedRouteKeys);
        $this->assertContains('dashboard', $payload['manifest']['vite_private_prefixes']);
        $this->assertContains('auth', $payload['manifest']['vite_private_prefixes']);
        $this->assertContains('login', $payload['manifest']['vite_private_prefixes']);
        $this->assertContains('onboarding', $payload['manifest']['vite_private_prefixes']);
        $this->assertContains('password', $payload['manifest']['vite_private_prefixes']);
        $this->assertContains('register', $payload['manifest']['vite_private_prefixes']);
        $this->assertContains('verify-email', $payload['manifest']['vite_private_prefixes']);
        $this->assertContains('/events/new', $payload['manifest']['vite_private_patterns']);
        $this->assertContains('/events/create', $payload['manifest']['vite_private_patterns']);
        $this->assertContains('/jobs/alerts', $payload['manifest']['vite_private_patterns']);
        $this->assertContains('/jobs/my-applications', $payload['manifest']['vite_private_patterns']);
        $this->assertContains('/listings/edit/:id', $payload['manifest']['vite_private_patterns']);
        $this->assertContains('/marketplace/sell', $payload['manifest']['vite_private_patterns']);
        $this->assertContains('/organisations/register', $payload['manifest']['vite_private_patterns']);
        $this->assertContains('/courses/my-learning', $payload['manifest']['vite_private_patterns']);
        $this->assertContains('/group-exchanges/create', $payload['manifest']['vite_private_patterns']);
        $this->assertContains('/volunteering/org/:orgId/dashboard', $payload['manifest']['vite_private_patterns']);
        $this->assertContains('/caring-community/my-relationships', $payload['manifest']['vite_private_patterns']);
        $this->assertContains('/pilot-apply/status/:token', $payload['manifest']['vite_private_patterns']);
        $this->assertContains('/newsletter/unsubscribe', $payload['manifest']['vite_private_patterns']);
        $this->assertContains('/join/:code', $payload['manifest']['vite_private_patterns']);
        $this->assertContains('route_cutover_disabled', array_column($payload['safety_checks'], 'key'));
        $this->assertContains('npm --prefix next-public-frontend run check', $payload['shadow_runtime']['verification_commands']);
        $this->assertContains('npm --prefix react-frontend run build', $payload['shadow_runtime']['verification_commands']);
        $this->assertContains(
            'vendor/bin/phpunit --no-coverage tests/Laravel/Unit/Services/NextPublicFrontendReadinessServiceTest.php tests/Laravel/Feature/Controllers/AdminNextPublicFrontendControllerTest.php',
            $payload['shadow_runtime']['verification_commands'],
        );
        $this->assertSame('static_or_tenant_bootstrap', $routeReadinessByKey['about']['content_source']);
        $this->assertSame('laravel_public_api', $routeReadinessByKey['listingDetail']['content_source']);
        $this->assertSame('laravel_public_api', $routeReadinessByKey['volunteeringOpportunityDetail']['content_source']);
        $this->assertSame('laravel_public_api', $routeReadinessByKey['ideationDetail']['content_source']);
        $this->assertSame('blocker', $routeReadinessByKey['listingDetail']['status']);
        $this->assertContains('parity_test_required', $routeReadinessByKey['listingDetail']['blockers']);
    }
}
