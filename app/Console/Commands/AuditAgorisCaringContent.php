<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only content audit for the Agoris/KISS Caring Community showcase.
 *
 * The goal is not to replace QA. It is a fast pre-demo confidence check:
 * are all caring-community surfaces populated, are the showcase envelopes in
 * place, and have obvious generic seed rows been replaced?
 */
class AuditAgorisCaringContent extends Command
{
    protected $signature = 'tenant:audit-agoris-caring-content
        {tenant_slug=agoris : Tenant slug to audit}
        {--expected-tenant-id=7 : Abort unless the tenant slug resolves to this id}';

    protected $description = 'Read-only scorecard for Agoris/KISS caring-community seeded content coverage';

    public function handle(): int
    {
        $slug = ltrim((string) $this->argument('tenant_slug'), '/');
        $tenant = DB::table('tenants')->where('slug', $slug)->first(['id', 'name', 'slug']);
        if (! $tenant) {
            $this->error("No tenant found for slug '{$slug}'.");
            return self::FAILURE;
        }

        $tenantId = (int) $tenant->id;
        $expectedTenantId = (int) $this->option('expected-tenant-id');
        if ($expectedTenantId > 0 && $tenantId !== $expectedTenantId) {
            $this->error("Safety stop: slug '{$slug}' resolved to tenant id {$tenantId}, expected {$expectedTenantId}.");
            return self::FAILURE;
        }

        $this->info("Tenant: {$tenant->name} (id={$tenantId}, slug={$tenant->slug})");
        $this->line('Mode: read-only');
        $this->newLine();

        $results = $this->auditTables($tenantId);
        $settings = $this->auditSettings($tenantId);
        $genericRows = $this->auditGenericRows($tenantId);
        $score = $this->score($results, $settings, $genericRows);

        $this->line('Caring table coverage');
        foreach ($results as $table => $result) {
            $status = $result['count'] >= $result['minimum'] ? 'OK' : 'LOW';
            $this->line(sprintf('  %-42s %5d / %-3d %s', $table, $result['count'], $result['minimum'], $status));
        }

        $this->newLine();
        $this->line('Showcase settings');
        foreach ($settings as $key => $present) {
            $this->line(sprintf('  %-42s %s', $key, $present ? 'OK' : 'MISSING'));
        }

        $this->newLine();
        $this->line('Generic-row polish');
        foreach ($genericRows as $label => $present) {
            $this->line(sprintf('  %-42s %s', $label, $present ? 'STILL PRESENT' : 'OK'));
        }

        $this->newLine();
        $this->info(sprintf('Agoris caring content score: %d / 1000', $score));

        if ($score < 900) {
            $this->warn('Below showcase threshold. Run the Agoris seed chain through tenant:seed-agoris-showcase, then audit again.');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, array{count: int, minimum: int}>
     */
    private function auditTables(int $tenantId): array
    {
        $minimums = [
            'caring_caregiver_links' => 4,
            'caring_care_providers' => 5,
            'caring_cover_requests' => 2,
            'caring_emergency_alerts' => 2,
            'caring_favours' => 10,
            'caring_federation_peers' => 3,
            'caring_help_requests' => 12,
            'caring_hour_estates' => 3,
            'caring_hour_gifts' => 4,
            'caring_hour_transfers' => 4,
            'caring_invite_codes' => 8,
            'caring_kiss_treffen' => 3,
            'caring_kpi_baselines' => 1,
            'caring_loyalty_redemptions' => 6,
            'caring_municipality_feedback' => 12,
            'caring_paper_onboarding_intakes' => 3,
            'caring_project_announcements' => 3,
            'caring_project_subscriptions' => 21,
            'caring_project_updates' => 6,
            'caring_regional_point_accounts' => 8,
            'caring_regional_point_transactions' => 22,
            'caring_research_consents' => 23,
            'caring_research_dataset_exports' => 2,
            'caring_research_partners' => 2,
            'caring_smart_nudges' => 10,
            'caring_sub_regions' => 6,
            'caring_support_relationships' => 5,
            'caring_tandem_suggestion_log' => 8,
            'caring_trust_tier_config' => 1,
        ];

        $results = [];
        foreach ($minimums as $table => $minimum) {
            $results[$table] = [
                'count' => $this->tenantCount($table, $tenantId),
                'minimum' => $minimum,
            ];
        }

        return $results;
    }

    /**
     * @return array<string, bool>
     */
    private function auditSettings(int $tenantId): array
    {
        $keys = [
            'caring.pilot_scoreboard',
            'caring.municipal_roi',
            'caring.demo_golden_paths',
            'caring.sales_narrative',
            'caring.showcase_image_generation_brief',
            'caring.isolated_node.deployment_mode',
            'caring.isolated_node.incident_runbook_url',
        ];

        $present = [];
        foreach ($keys as $key) {
            $present[$key] = Schema::hasTable('tenant_settings')
                && DB::table('tenant_settings')
                    ->where('tenant_id', $tenantId)
                    ->where('setting_key', $key)
                    ->exists();
        }

        return $present;
    }

    /**
     * @return array<string, bool>
     */
    private function auditGenericRows(int $tenantId): array
    {
        $titles = [
            'I can help with Mentoring',
            'I can help with Community Service',
            'I can help with Community Stories',
            'Looking for help with Platform Updates',
            'Clean house',
        ];

        $result = [];
        foreach ($titles as $title) {
            $result[$title] = Schema::hasTable('listings')
                && DB::table('listings')
                    ->where('tenant_id', $tenantId)
                    ->where('title', $title)
                    ->exists();
        }

        return $result;
    }

    /**
     * @param array<string, array{count: int, minimum: int}> $tables
     * @param array<string, bool> $settings
     * @param array<string, bool> $genericRows
     */
    private function score(array $tables, array $settings, array $genericRows): int
    {
        $score = 1000;

        foreach ($tables as $result) {
            if ($result['minimum'] <= 0) {
                continue;
            }
            if ($result['count'] === 0) {
                $score -= 35;
                continue;
            }
            if ($result['count'] < $result['minimum']) {
                $score -= 15;
            }
        }

        foreach ($settings as $present) {
            if (! $present) {
                $score -= 20;
            }
        }

        foreach ($genericRows as $present) {
            if ($present) {
                $score -= 12;
            }
        }

        return max(0, $score);
    }

    private function tenantCount(string $table, int $tenantId): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
            return 0;
        }

        return DB::table($table)->where('tenant_id', $tenantId)->count();
    }
}
