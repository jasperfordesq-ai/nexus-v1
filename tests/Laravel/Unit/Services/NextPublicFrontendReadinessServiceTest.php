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

    public function test_summary_reports_pre_cutover_dry_runs_without_activation_controls(): void
    {
        $summary = (new NextPublicFrontendReadinessService())->summary();

        $dryRuns = $summary['pre_cutover_dry_runs'];
        $items = array_column($dryRuns['items'], null, 'key');

        $this->assertSame('none', $dryRuns['production_effect']);
        $this->assertFalse($dryRuns['activation_available']);
        $this->assertTrue($dryRuns['requires_explicit_cutover_instruction']);
        $this->assertSame(
            [
                'shadow_manifest_and_html',
                'remaining_static_manual_review',
                'auth_only_public_contract_review',
                'private_vite_regression',
                'inertness_guard',
            ],
            array_keys($items),
        );

        $this->assertSame('blocked', $items['shadow_manifest_and_html']['status']);
        $this->assertContains('manual_verification_required', $items['shadow_manifest_and_html']['blockers']);
        $this->assertContains('npm --prefix next-public-frontend run check', $items['shadow_manifest_and_html']['commands']);
        $this->assertContains('npm --prefix next-public-frontend run check:no-js-html', $items['shadow_manifest_and_html']['commands']);

        $this->assertSame('blocked', $items['remaining_static_manual_review']['status']);
        $this->assertNotContains('platformTerms', $items['remaining_static_manual_review']['route_keys']);
        $this->assertNotContains('platformPrivacy', $items['remaining_static_manual_review']['route_keys']);
        $this->assertNotContains('platformDisclaimer', $items['remaining_static_manual_review']['route_keys']);
        $this->assertContains('authoritative_content_source_required', $items['remaining_static_manual_review']['blockers']);

        $this->assertSame('blocked', $items['auth_only_public_contract_review']['status']);
        $this->assertContains('couponDetail', $items['auth_only_public_contract_review']['route_keys']);
        $this->assertContains('ideationIdeaDetail', $items['auth_only_public_contract_review']['route_keys']);
        $this->assertContains('privacy_review_required_before_public_api', $items['auth_only_public_contract_review']['blockers']);

        $this->assertSame('blocked', $items['private_vite_regression']['status']);
        $this->assertContains('npm --prefix react-frontend run build', $items['private_vite_regression']['commands']);
        $this->assertContains('cd react-frontend && npx tsc --noEmit', $items['private_vite_regression']['commands']);

        $this->assertSame('blocked', $items['inertness_guard']['status']);
        $this->assertContains('npm run check:next-public:inert', $items['inertness_guard']['commands']);
        $this->assertContains('no_activation_control', $items['inertness_guard']['notes']);
    }

    public function test_summary_reports_cutover_eligibility_without_activation_controls(): void
    {
        $summary = (new NextPublicFrontendReadinessService())->summary();

        $eligibility = $summary['cutover_eligibility'];

        $this->assertSame('blocked', $eligibility['status']);
        $this->assertFalse($eligibility['eligible']);
        $this->assertSame('none', $eligibility['production_effect']);
        $this->assertFalse($eligibility['activation_available']);
        $this->assertTrue($eligibility['requires_explicit_cutover_instruction']);
        $this->assertSame(76, $eligibility['counts']['public_routes']);
        $this->assertSame(72, $eligibility['counts']['api_backed_public_routes']);
        $this->assertSame(4, $eligibility['counts']['remaining_public_routes']);
        $this->assertContains('remaining_public_route_work', $eligibility['blockers']);
        $this->assertContains('route_parity_required', $eligibility['blockers']);
        $this->assertContains('edge_routes_not_configured', $eligibility['blockers']);
        $this->assertContains('explicit_cutover_instruction_required', $eligibility['blockers']);
        $this->assertContains('complete_remaining_public_route_work', $eligibility['required_actions']);
        $this->assertContains('keep_prerender_fallback', $eligibility['required_actions']);
    }

    public function test_summary_reports_cutover_artifact_inventory_without_activation_controls(): void
    {
        $summary = (new NextPublicFrontendReadinessService())->summary();

        $inventory = $summary['cutover_artifacts'];
        $items = array_column($inventory['items'], null, 'key');
        $commands = array_column($inventory['required_commands'], null, 'key');

        $this->assertSame('none', $inventory['production_effect']);
        $this->assertFalse($inventory['activation_available']);
        $this->assertSame('next-public-frontend/route-ownership.json', $items['route_ownership_manifest']['path']);
        $this->assertTrue($items['route_ownership_manifest']['exists']);
        $this->assertSame('scripts/deploy/apache/next-public-foundation-canary.conf.example', $items['apache_canary_template']['path']);
        $this->assertSame('none', $items['apache_canary_template']['production_effect']);
        $this->assertTrue($items['prerender_fallback']['exists']);
        $this->assertSame('npm run check:next-public:dry-run', $commands['next_public_dry_run']['command']);
        $this->assertSame('npm --prefix next-public-frontend run check:no-js-html', $commands['no_js_public_html']['command']);
        $this->assertSame('npm run check:next-public:inert', $commands['next_public_inertness']['command']);
        $this->assertTrue($commands['react_private_regression']['required_before_cutover']);
    }

    public function test_summary_reports_next_public_inertness_verification_command(): void
    {
        $summary = (new NextPublicFrontendReadinessService())->summary();

        $this->assertContains('npm run check:next-public:inert', $summary['shadow_runtime']['verification_commands']);
        $this->assertContains('npm run check:next-public:dry-run', $summary['shadow_runtime']['verification_commands']);
    }

    public function test_summary_reports_tenant_resolution_contract(): void
    {
        $summary = (new NextPublicFrontendReadinessService())->summary();

        $tenantResolution = $summary['tenant_resolution'];
        $examples = array_column($tenantResolution['examples'], null, 'key');

        $this->assertSame('pass', $tenantResolution['status']);
        $this->assertSame('/v2/tenant/bootstrap', $tenantResolution['bootstrap_endpoint']);
        $this->assertSame('public', $tenantResolution['bootstrap_route_status']);
        $this->assertSame('laravel_tenant_bootstrap', $tenantResolution['source_of_truth']);
        $this->assertSame('slug', $tenantResolution['shared_host_slug_parameter']);
        $this->assertTrue($tenantResolution['custom_domain_origin_forwarding']);
        $this->assertFalse($tenantResolution['next_queries_database']);
        $this->assertSame('GET /v2/tenant/bootstrap?slug={tenantSlug}', $examples['shared_host_slug']['bootstrap_request']);
        $this->assertContains('Origin: https://app.project-nexus.ie', $examples['shared_host_slug']['headers']);
        $this->assertSame('GET /v2/tenant/bootstrap', $examples['custom_domain']['bootstrap_request']);
        $this->assertContains('Origin: https://<custom-domain>', $examples['custom_domain']['headers']);
    }

    public function test_summary_reports_edge_canary_preview_without_activation_controls(): void
    {
        $summary = (new NextPublicFrontendReadinessService())->summary();

        $edgeCanary = $summary['edge_canary'];
        $safetyChecks = array_column($summary['safety_checks'], 'status', 'key');

        $this->assertSame('blocked', $edgeCanary['status']);
        $this->assertSame('apache_plesk', $edgeCanary['edge']);
        $this->assertSame('NEXT_PUBLIC_FRONTEND_ROUTING_ENABLED', $edgeCanary['routing_flag']);
        $this->assertFalse($edgeCanary['routing_flag_enabled']);
        $this->assertFalse($edgeCanary['activation_available']);
        $this->assertTrue($edgeCanary['preview_only']);
        $this->assertTrue($edgeCanary['requires_explicit_cutover_instruction']);
        $this->assertTrue($edgeCanary['reviewed_config_required']);
        $this->assertSame('not_configured', $edgeCanary['route_file_status']);
        $this->assertContains('do_not_edit_plesk_vhosts_directly', $edgeCanary['guardrails']);
        $this->assertContains('do_not_remove_prerender', $edgeCanary['guardrails']);
        $this->assertSame('scripts/deploy/apache/next-public-foundation-canary.conf.example', $edgeCanary['config_template']['path']);
        $this->assertTrue($edgeCanary['config_template']['exists']);
        $this->assertTrue($edgeCanary['config_template']['example_only']);
        $this->assertFalse($edgeCanary['config_template']['included_by_deploy']);
        $this->assertContains('explicit_cutover_instruction_required', $edgeCanary['config_template']['required_review_steps']);
        $this->assertSame('pass', $safetyChecks['apache_canary_template_not_included']);
    }

    public function test_summary_audits_apache_canary_template_against_public_route_ownership(): void
    {
        $summary = (new NextPublicFrontendReadinessService())->summary();

        $audit = $summary['edge_canary']['route_audit'];

        $this->assertSame('pass', $audit['status']);
        $this->assertSame('scripts/deploy/apache/next-public-foundation-canary.conf.example', $audit['template_path']);
        $this->assertSame(26, $audit['exact_path_count']);
        $this->assertTrue($audit['public_only']);
        $this->assertSame([], $audit['private_collisions']);
        $this->assertSame([], $audit['unmatched_template_paths']);
        $this->assertSame([], $audit['unsupported_rules']);
        $this->assertContains('/', $audit['template_paths']);
        $this->assertContains('/about', $audit['template_paths']);
        $this->assertContains('/privacy/versions', $audit['template_paths']);
        $this->assertContains('/platform/disclaimer', $audit['template_paths']);
    }

    public function test_summary_reports_route_batches_for_future_canary_planning(): void
    {
        $summary = (new NextPublicFrontendReadinessService())->summary();

        $batches = array_column($summary['route_batches'], null, 'key');

        $this->assertSame(
            [
                'foundation_public_pages',
                'api_backed_public_content',
                'vite_private_retained',
            ],
            array_keys($batches),
        );
        $this->assertSame('blocked', $batches['foundation_public_pages']['status']);
        $this->assertContains('home', $batches['foundation_public_pages']['route_keys']);
        $this->assertContains('about', $batches['foundation_public_pages']['route_keys']);
        $this->assertContains('manual_shadow_review_required', $batches['foundation_public_pages']['blockers']);
        $this->assertContains('npm --prefix next-public-frontend run check:no-js-html', $batches['foundation_public_pages']['verification_commands']);

        $this->assertSame('blocked', $batches['api_backed_public_content']['status']);
        $this->assertContains('listingDetail', $batches['api_backed_public_content']['route_keys']);
        $this->assertContains('public_api_parity_required', $batches['api_backed_public_content']['blockers']);

        $this->assertSame('pass', $batches['vite_private_retained']['status']);
        $this->assertSame([], $batches['vite_private_retained']['blockers']);
        $this->assertContains('npm --prefix react-frontend run build', $batches['vite_private_retained']['verification_commands']);
    }

    public function test_summary_reports_remaining_public_route_work_without_activation(): void
    {
        $summary = (new NextPublicFrontendReadinessService())->summary();

        $remaining = $summary['remaining_public_route_work'];
        $groups = array_column($remaining['groups'], null, 'key');

        $this->assertSame('none', $remaining['production_effect']);
        $this->assertFalse($remaining['activation_available']);
        $this->assertSame(76, $remaining['counts']['public_routes']);
        $this->assertSame(72, $remaining['counts']['api_backed_public_routes']);
        $this->assertSame(4, $remaining['counts']['remaining_public_routes']);
        $this->assertSame(0, $remaining['counts']['unclassified_manifest_only_routes']);
        $this->assertNotContains('changelog', $groups['static_manual_review']['route_keys']);
        $this->assertNotContains('home', $groups['static_manual_review']['route_keys']);
        $this->assertNotContains('about', $groups['static_manual_review']['route_keys']);
        $this->assertNotContains('features', $groups['static_manual_review']['route_keys']);
        $this->assertNotContains('contact', $groups['static_manual_review']['route_keys']);
        $this->assertNotContains('trustSafety', $groups['static_manual_review']['route_keys']);
        $this->assertNotContains('timebankingGuide', $groups['static_manual_review']['route_keys']);
        $this->assertNotContains('legal', $groups['static_manual_review']['route_keys']);
        $this->assertNotContains('developers', $groups['static_manual_review']['route_keys']);
        $this->assertNotContains('developersAuth', $groups['static_manual_review']['route_keys']);
        $this->assertNotContains('developersEndpoints', $groups['static_manual_review']['route_keys']);
        $this->assertNotContains('developersWebhooks', $groups['static_manual_review']['route_keys']);
        $this->assertNotContains('regionalAnalytics', $groups['static_manual_review']['route_keys']);
        $this->assertNotContains('caringCommunity', $groups['static_manual_review']['route_keys']);
        $this->assertNotContains('hourPartner', $groups['static_manual_review']['route_keys']);
        $this->assertNotContains('hourSocialPrescribing', $groups['static_manual_review']['route_keys']);
        $this->assertNotContains('hourImpactSummary', $groups['static_manual_review']['route_keys']);
        $this->assertNotContains('hourImpactReport', $groups['static_manual_review']['route_keys']);
        $this->assertNotContains('hourStrategicPlan', $groups['static_manual_review']['route_keys']);
        $this->assertNotContains('platformTerms', $groups['static_manual_review']['route_keys']);
        $this->assertNotContains('platformPrivacy', $groups['static_manual_review']['route_keys']);
        $this->assertNotContains('platformDisclaimer', $groups['static_manual_review']['route_keys']);
        $this->assertNotContains('developmentStatus', $groups['static_manual_review']['route_keys']);
        $this->assertContains('couponDetail', $groups['auth_only_backend']['route_keys']);
        $this->assertContains('ideationIdeaDetail', $groups['auth_only_backend']['route_keys']);
        $this->assertNotContains('ideationIdeaDetail', $groups['backend_contract_missing']['route_keys']);
        $this->assertSame('authoritative_static_content_missing', $groups['static_manual_review']['reason']);
        $this->assertSame('public_api_would_expand_auth_scope', $groups['auth_only_backend']['reason']);
        $this->assertContains('keep_vite_or_prerender_until_public_contract', $groups['auth_only_backend']['required_actions']);
        $this->assertContains('public_visibility_decision_required', $groups['auth_only_backend']['required_actions']);
        $this->assertContains('privacy_review_required_before_public_api', $groups['auth_only_backend']['required_actions']);
        $this->assertContains('npm --prefix next-public-frontend run check:no-js-html', $groups['static_manual_review']['verification_commands']);
        $this->assertSame(
            'route_status_has_no_production_effect',
            $remaining['guardrails'][0],
        );
    }

    public function test_remaining_public_route_work_reports_unclassified_manifest_only_routes(): void
    {
        $method = new ReflectionMethod(NextPublicFrontendReadinessService::class, 'remainingPublicRouteWork');
        $method->setAccessible(true);

        $remaining = $method->invoke(new NextPublicFrontendReadinessService(), [
            [
                'pattern' => '/future-public-route',
                'routeKey' => 'futurePublicRoute',
                'labelKey' => 'pages.future_public_route.title',
            ],
        ], []);
        $groups = array_column($remaining['groups'], null, 'key');

        $this->assertContains('futurePublicRoute', $groups['unclassified_manifest_only']['route_keys']);
        $this->assertSame('manifest_only_unclassified', $groups['unclassified_manifest_only']['reason']);
        $this->assertContains('classify_route_before_cutover', $groups['unclassified_manifest_only']['required_actions']);
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

    public function test_registered_public_get_endpoint_status_matches_laravel_route_parameters(): void
    {
        $method = new ReflectionMethod(NextPublicFrontendReadinessService::class, 'registeredPublicGetEndpointStatus');
        $method->setAccessible(true);

        $status = $method->invoke(new NextPublicFrontendReadinessService(), '/v2/legal/privacy');

        $this->assertSame('public', $status);
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
