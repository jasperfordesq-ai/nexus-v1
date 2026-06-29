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
        $response->assertJsonPath('data.manifest.route_counts.public_routes', 76);
        $response->assertJsonPath('data.manifest.route_counts.api_backed_public_routes', 28);
        $response->assertJsonPath('data.manifest.route_counts.vite_private_prefixes', 38);
        $response->assertJsonPath('data.manifest.route_counts.vite_private_patterns', 100);
        $response->assertJsonPath('data.manifest.validation.status', 'pass');
        $response->assertJsonPath('data.manifest.validation.issues', []);
        $response->assertJsonPath('data.content_sources.source_of_truth', 'laravel_public_api');
        $response->assertJsonPath('data.content_sources.database_queries_from_next', false);
        $response->assertJsonPath('data.content_sources.manifest_exists', true);
        $response->assertJsonPath('data.content_sources.manifest_path', 'next-public-frontend/content-sources.json');
        $response->assertJsonPath('data.tenant_resolution.status', 'pass');
        $response->assertJsonPath('data.tenant_resolution.bootstrap_endpoint', '/v2/tenant/bootstrap');
        $response->assertJsonPath('data.tenant_resolution.bootstrap_route_status', 'public');
        $response->assertJsonPath('data.tenant_resolution.source_of_truth', 'laravel_tenant_bootstrap');
        $response->assertJsonPath('data.tenant_resolution.shared_host_slug_parameter', 'slug');
        $response->assertJsonPath('data.tenant_resolution.custom_domain_origin_forwarding', true);
        $response->assertJsonPath('data.tenant_resolution.next_queries_database', false);
        $response->assertJsonPath('data.edge_canary.status', 'blocked');
        $response->assertJsonPath('data.edge_canary.edge', 'apache_plesk');
        $response->assertJsonPath('data.edge_canary.routing_flag', 'NEXT_PUBLIC_FRONTEND_ROUTING_ENABLED');
        $response->assertJsonPath('data.edge_canary.routing_flag_enabled', false);
        $response->assertJsonPath('data.edge_canary.activation_available', false);
        $response->assertJsonPath('data.edge_canary.preview_only', true);
        $response->assertJsonPath('data.edge_canary.route_file_status', 'not_configured');
        $response->assertJsonPath('data.edge_canary.config_template.path', 'scripts/deploy/apache/next-public-foundation-canary.conf.example');
        $response->assertJsonPath('data.edge_canary.config_template.exists', true);
        $response->assertJsonPath('data.edge_canary.config_template.example_only', true);
        $response->assertJsonPath('data.edge_canary.config_template.included_by_deploy', false);
        $response->assertJsonPath('data.edge_canary.route_audit.status', 'pass');
        $response->assertJsonPath('data.edge_canary.route_audit.exact_path_count', 26);
        $response->assertJsonPath('data.edge_canary.route_audit.public_only', true);
        $response->assertJsonPath('data.edge_canary.route_audit.private_collisions', []);
        $response->assertJsonPath('data.edge_canary.route_audit.unmatched_template_paths', []);
        $response->assertJsonPath('data.cutover_artifacts.production_effect', 'none');
        $response->assertJsonPath('data.cutover_artifacts.activation_available', false);

        $payload = $response->json('data');
        $publicPatterns = array_column($payload['manifest']['public_routes'], 'pattern');
        $apiBackedRouteKeys = array_column($payload['content_sources']['api_backed_routes'], 'routeKey');
        $routeReadiness = $payload['manifest']['route_readiness'];
        $cutoverGatesByKey = array_column($payload['cutover_gates'], null, 'key');
        $playbookStagesByKey = array_column($payload['operator_playbook']['stages'], null, 'key');
        $tenantResolutionExamplesByKey = array_column($payload['tenant_resolution']['examples'], null, 'key');
        $routeReadinessByKey = [];
        $routeBatchesByKey = array_column($payload['route_batches'], null, 'key');
        $cutoverArtifactsByKey = array_column($payload['cutover_artifacts']['items'], null, 'key');
        $cutoverCommandsByKey = array_column($payload['cutover_artifacts']['required_commands'], null, 'key');

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
        $this->assertContains('/groups/:id', $publicPatterns);
        $this->assertContains('/courses/:idOrSlug', $publicPatterns);
        $this->assertContains('/podcasts/:showSlug/:episodeSlug', $publicPatterns);
        $this->assertContains('/coupons/:id', $publicPatterns);
        $this->assertContains('/caring-community', $publicPatterns);
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
        $this->assertContains('groupDetail', $apiBackedRouteKeys);
        $this->assertContains('courseDetail', $apiBackedRouteKeys);
        $this->assertContains('podcastEpisode', $apiBackedRouteKeys);
        $this->assertContains('dashboard', $payload['manifest']['vite_private_prefixes']);
        $this->assertContains('activity', $payload['manifest']['vite_private_prefixes']);
        $this->assertContains('auth', $payload['manifest']['vite_private_prefixes']);
        $this->assertContains('federation', $payload['manifest']['vite_private_prefixes']);
        $this->assertContains('login', $payload['manifest']['vite_private_prefixes']);
        $this->assertContains('me', $payload['manifest']['vite_private_prefixes']);
        $this->assertContains('onboarding', $payload['manifest']['vite_private_prefixes']);
        $this->assertContains('password', $payload['manifest']['vite_private_prefixes']);
        $this->assertContains('search', $payload['manifest']['vite_private_prefixes']);
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
        $this->assertContains('/caring-community/request-help', $payload['manifest']['vite_private_patterns']);
        $this->assertContains('/clubs/:id/admin/import', $payload['manifest']['vite_private_patterns']);
        $this->assertContains('/jobs/employers/:userId', $payload['manifest']['vite_private_patterns']);
        $this->assertContains('/marketplace/seller/coupons', $payload['manifest']['vite_private_patterns']);
        $this->assertContains('/ideation/campaigns', $payload['manifest']['vite_private_patterns']);
        $this->assertContains('/donations/:id/receipt', $payload['manifest']['vite_private_patterns']);
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
        $this->assertSame('blocker', $cutoverGatesByKey['prepare_apache_canary_routes']['status']);
        $this->assertContains(
            'explicit_cutover_instruction_required',
            $cutoverGatesByKey['prepare_apache_canary_routes']['blockers'],
        );
        $this->assertContains(
            'edge_routes_not_configured',
            $cutoverGatesByKey['prepare_apache_canary_routes']['blockers'],
        );
        $this->assertSame(
            ['npm --prefix next-public-frontend run check'],
            $cutoverGatesByKey['verify_next_shadow_build']['verification_commands'],
        );
        $this->assertFalse($payload['operator_playbook']['activation_available']);
        $this->assertTrue($payload['operator_playbook']['requires_explicit_cutover_instruction']);
        $this->assertTrue($payload['operator_playbook']['no_production_effect']);
        $this->assertSame('blocked', $playbookStagesByKey['prepare_reviewed_edge_config']['status']);
        $this->assertContains('no_activation_control', $playbookStagesByKey['prepare_reviewed_edge_config']['notes']);
        $this->assertContains('do_not_remove_prerender', $playbookStagesByKey['monitor_with_prerender_fallback']['notes']);
        $this->assertSame(
            'GET /v2/tenant/bootstrap?slug={tenantSlug}',
            $tenantResolutionExamplesByKey['shared_host_slug']['bootstrap_request'],
        );
        $this->assertContains(
            'Origin: https://<custom-domain>',
            $tenantResolutionExamplesByKey['custom_domain']['headers'],
        );
        $this->assertContains('do_not_edit_plesk_vhosts_directly', $payload['edge_canary']['guardrails']);
        $this->assertContains('do_not_remove_prerender', $payload['edge_canary']['guardrails']);
        $this->assertContains('apache_configtest_required', $payload['edge_canary']['config_template']['required_review_steps']);
        $this->assertContains('/platform/disclaimer', $payload['edge_canary']['route_audit']['template_paths']);
        $this->assertSame(
            'scripts/deploy/apache/next-public-foundation-canary.conf.example',
            $cutoverArtifactsByKey['apache_canary_template']['path'],
        );
        $this->assertSame('none', $cutoverArtifactsByKey['apache_canary_template']['production_effect']);
        $this->assertTrue($cutoverArtifactsByKey['prerender_fallback']['exists']);
        $this->assertSame(
            'npm --prefix next-public-frontend run check:no-js-html',
            $cutoverCommandsByKey['no_js_public_html']['command'],
        );
        $this->assertSame('blocked', $routeBatchesByKey['foundation_public_pages']['status']);
        $this->assertContains('home', $routeBatchesByKey['foundation_public_pages']['route_keys']);
        $this->assertContains('manual_shadow_review_required', $routeBatchesByKey['foundation_public_pages']['blockers']);
        $this->assertSame('blocked', $routeBatchesByKey['api_backed_public_content']['status']);
        $this->assertContains('listingDetail', $routeBatchesByKey['api_backed_public_content']['route_keys']);
        $this->assertContains('public_api_parity_required', $routeBatchesByKey['api_backed_public_content']['blockers']);
        $this->assertSame('pass', $routeBatchesByKey['vite_private_retained']['status']);
        $this->assertSame([], $routeBatchesByKey['vite_private_retained']['blockers']);
        $this->assertSame('static_or_tenant_bootstrap', $routeReadinessByKey['about']['content_source']);
        $this->assertSame('laravel_public_api', $routeReadinessByKey['listingDetail']['content_source']);
        $this->assertSame('laravel_public_api', $routeReadinessByKey['volunteeringOpportunityDetail']['content_source']);
        $this->assertSame('laravel_public_api', $routeReadinessByKey['ideationDetail']['content_source']);
        $this->assertSame('laravel_public_api', $routeReadinessByKey['groupDetail']['content_source']);
        $this->assertSame('laravel_public_api', $routeReadinessByKey['courseDetail']['content_source']);
        $this->assertSame('laravel_public_api', $routeReadinessByKey['podcastEpisode']['content_source']);
        $this->assertSame('static_or_tenant_bootstrap', $routeReadinessByKey['couponDetail']['content_source']);
        $this->assertSame('blocker', $routeReadinessByKey['listingDetail']['status']);
        $this->assertContains('parity_test_required', $routeReadinessByKey['listingDetail']['blockers']);
    }
}
