<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services\CaringCommunity;

use App\Services\CaringCommunity\ResearchAgreementTemplateService;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Tests\Laravel\TestCase;

class ResearchAgreementTemplateServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function service(): ResearchAgreementTemplateService
    {
        return app(ResearchAgreementTemplateService::class);
    }

    // -------------------------------------------------------------------------
    // listTemplates
    // -------------------------------------------------------------------------

    public function test_list_templates_returns_at_least_four_entries(): void
    {
        $catalog = $this->service()->listTemplates();

        $this->assertIsArray($catalog);
        $this->assertGreaterThanOrEqual(4, count($catalog));
    }

    public function test_list_templates_each_entry_has_required_keys(): void
    {
        $catalog = $this->service()->listTemplates();

        foreach ($catalog as $entry) {
            $this->assertArrayHasKey('key', $entry);
            $this->assertArrayHasKey('title', $entry);
            $this->assertArrayHasKey('summary', $entry);
            $this->assertArrayHasKey('suitable_for', $entry);
            $this->assertArrayHasKey('placeholders', $entry);
            $this->assertIsString($entry['key']);
            $this->assertNotEmpty($entry['key']);
            $this->assertIsArray($entry['suitable_for']);
            $this->assertIsArray($entry['placeholders']);
        }
    }

    public function test_list_templates_includes_expected_keys(): void
    {
        $catalog = $this->service()->listTemplates();
        $keys    = array_column($catalog, 'key');

        $this->assertContains('aggregate_dataset_v1', $keys);
        $this->assertContains('longitudinal_cohort_v1', $keys);
        $this->assertContains('pilot_evaluation_v1', $keys);
        $this->assertContains('cross_node_federation_v1', $keys);
    }

    public function test_list_templates_placeholders_are_strings(): void
    {
        $catalog = $this->service()->listTemplates();

        foreach ($catalog as $entry) {
            foreach ($entry['placeholders'] as $ph) {
                $this->assertIsString($ph);
            }
        }
    }

    // -------------------------------------------------------------------------
    // render — happy path with all placeholders supplied
    // -------------------------------------------------------------------------

    public function test_render_aggregate_dataset_v1_replaces_all_placeholders(): void
    {
        $values = [
            'partner_name'    => 'Pro Senectute',
            'partner_institution' => 'PS Zürich',
            'tenant_name'     => 'hOUR Timebank',
            'dpo_name'        => 'Jasper Ford',
            'dpo_email'       => 'dpo@hour-timebank.ie',
            'period_start'    => '2024-01-01',
            'period_end'      => '2024-12-31',
            'jurisdiction'    => 'Switzerland',
        ];

        $result = $this->service()->render('aggregate_dataset_v1', $values);

        $this->assertSame('aggregate_dataset_v1', $result['key']);
        $this->assertIsString($result['title']);
        $this->assertIsString($result['markdown']);

        // All 8 placeholders should be in 'used' and none in 'missing'.
        $this->assertCount(0, $result['placeholders_missing']);
        $this->assertCount(8, $result['placeholders_used']);

        // Actual replacement in markdown body.
        $this->assertStringContainsString('Pro Senectute', $result['markdown']);
        $this->assertStringContainsString('hOUR Timebank', $result['markdown']);
        $this->assertStringContainsString('Jasper Ford', $result['markdown']);
        $this->assertStringNotContainsString('{{partner_name}}', $result['markdown']);
        $this->assertStringNotContainsString('{{tenant_name}}', $result['markdown']);
    }

    public function test_render_longitudinal_cohort_v1_replaces_all_placeholders(): void
    {
        $values = [
            'partner_name'        => 'ETH Zürich',
            'partner_institution' => 'ETH Institute',
            'tenant_name'         => 'hOUR Timebank',
            'dpo_name'            => 'Jasper Ford',
            'dpo_email'           => 'dpo@example.com',
            'cohort_window_years' => '3',
            'jurisdiction'        => 'Switzerland',
        ];

        $result = $this->service()->render('longitudinal_cohort_v1', $values);

        $this->assertCount(0, $result['placeholders_missing']);
        $this->assertCount(7, $result['placeholders_used']);
        $this->assertStringContainsString('ETH Zürich', $result['markdown']);
        $this->assertStringNotContainsString('{{cohort_window_years}}', $result['markdown']);
    }

    public function test_render_pilot_evaluation_v1_replaces_all_placeholders(): void
    {
        $values = [
            'partner_name'        => 'Cantonal Office',
            'partner_institution' => 'Canton AG',
            'tenant_name'         => 'hOUR Timebank',
            'dpo_name'            => 'Jasper Ford',
            'dpo_email'           => 'dpo@example.com',
            'pilot_region'        => 'Aarau',
            'period_start'        => '2024-01-01',
            'period_end'          => '2024-06-30',
        ];

        $result = $this->service()->render('pilot_evaluation_v1', $values);

        $this->assertCount(0, $result['placeholders_missing']);
        $this->assertCount(8, $result['placeholders_used']);
        $this->assertStringContainsString('Aarau', $result['markdown']);
        $this->assertStringNotContainsString('{{pilot_region}}', $result['markdown']);
    }

    public function test_render_cross_node_federation_v1_replaces_all_placeholders(): void
    {
        $values = [
            'partner_name'        => 'Fondation KISS',
            'partner_institution' => 'KISS National',
            'tenant_name'         => 'hOUR Timebank',
            'dpo_name'            => 'Jasper Ford',
            'dpo_email'           => 'dpo@example.com',
            'study_title'         => 'Cross-Canton Caring Impact Study',
            'jurisdiction'        => 'Switzerland',
        ];

        $result = $this->service()->render('cross_node_federation_v1', $values);

        $this->assertCount(0, $result['placeholders_missing']);
        $this->assertCount(7, $result['placeholders_used']);
        $this->assertStringContainsString('Fondation KISS', $result['markdown']);
        $this->assertStringNotContainsString('{{study_title}}', $result['markdown']);
    }

    // -------------------------------------------------------------------------
    // render — missing placeholders left as {{token}}
    // -------------------------------------------------------------------------

    public function test_render_with_no_values_leaves_all_placeholders_in_markdown(): void
    {
        $result = $this->service()->render('aggregate_dataset_v1', []);

        // All 8 placeholders are missing.
        $this->assertCount(8, $result['placeholders_missing']);
        $this->assertCount(0, $result['placeholders_used']);

        // The markdown still contains the raw tokens.
        $this->assertStringContainsString('{{partner_name}}', $result['markdown']);
        $this->assertStringContainsString('{{tenant_name}}', $result['markdown']);
    }

    public function test_render_with_partial_values_tracks_used_and_missing_separately(): void
    {
        $values = [
            'partner_name'    => 'Pro Senectute',
            'partner_institution' => 'PS Zürich',
            // remaining 6 not supplied
        ];

        $result = $this->service()->render('aggregate_dataset_v1', $values);

        $this->assertContains('partner_name', $result['placeholders_used']);
        $this->assertContains('partner_institution', $result['placeholders_used']);
        $this->assertContains('tenant_name', $result['placeholders_missing']);
        $this->assertCount(2, $result['placeholders_used']);
        $this->assertCount(6, $result['placeholders_missing']);

        // Supplied values replaced, missing ones left as tokens.
        $this->assertStringContainsString('Pro Senectute', $result['markdown']);
        $this->assertStringContainsString('{{tenant_name}}', $result['markdown']);
        $this->assertStringNotContainsString('{{partner_name}}', $result['markdown']);
    }

    public function test_render_ignores_extra_values_not_in_template(): void
    {
        $values = [
            'partner_name'        => 'ETH Zürich',
            'partner_institution' => 'ETH Institute',
            'tenant_name'         => 'hOUR Timebank',
            'dpo_name'            => 'Jasper Ford',
            'dpo_email'           => 'dpo@example.com',
            'period_start'        => '2024-01-01',
            'period_end'          => '2024-12-31',
            'jurisdiction'        => 'Switzerland',
            'this_key_doesnt_exist' => 'should be ignored',
        ];

        // Should not throw; extra key is silently ignored.
        $result = $this->service()->render('aggregate_dataset_v1', $values);

        $this->assertCount(0, $result['placeholders_missing']);
        $this->assertNotContains('this_key_doesnt_exist', $result['placeholders_used']);
    }

    // -------------------------------------------------------------------------
    // render — unknown template key
    // -------------------------------------------------------------------------

    public function test_render_throws_for_unknown_template_key(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/unknown research agreement template/i');
        $this->service()->render('nonexistent_template_xyz');
    }

    // -------------------------------------------------------------------------
    // Whitespace / edge cases
    // -------------------------------------------------------------------------

    public function test_render_trims_whitespace_from_values_before_deciding_missing(): void
    {
        // A value that is only whitespace should be treated as empty (missing).
        $result = $this->service()->render('aggregate_dataset_v1', [
            'partner_name' => '   ',
        ]);

        $this->assertContains('partner_name', $result['placeholders_missing']);
        $this->assertStringContainsString('{{partner_name}}', $result['markdown']);
    }
}
