<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\FadpComplianceService;
use App\Core\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

/**
 * FadpComplianceServiceTest
 *
 * Tests Swiss FADP / nDSG compliance service: consent ledger recording,
 * history retrieval, retention config upsert/defaults, processing activities
 * CRUD, processing register export, disclosure pack generation, and CSV export.
 *
 * Uses a dedicated high-range tenant ID (99042) to avoid colliding with real
 * tenant-2 data. DatabaseTransactions rolls back every test.
 */
class FadpComplianceServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99042;
    private const USER_ID   = 99042;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure test tenant row exists (insertOrIgnore is idempotent inside a
        // transaction; it will be rolled back after every test).
        DB::table('tenants')->insertOrIgnore([
            'id'                => self::TENANT_ID,
            'name'              => 'FADP Test Tenant',
            'slug'              => 'fadp-test-99042',
            'is_active'         => true,
            'depth'             => 0,
            'allows_subtenants' => false,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        TenantContext::setById(self::TENANT_ID);
    }

    // =========================================================================
    // isAvailable
    // =========================================================================

    public function test_isAvailable_returns_true_when_tables_exist(): void
    {
        // All three FADP tables are in the schema dump, so they must exist.
        $this->assertTrue(FadpComplianceService::isAvailable());
    }

    // =========================================================================
    // recordConsent + getConsentHistory
    // =========================================================================

    public function test_recordConsent_inserts_row_and_getConsentHistory_returns_it(): void
    {
        FadpComplianceService::recordConsent(
            self::USER_ID,
            self::TENANT_ID,
            'marketing_email',
            'granted',
            ['ip_address' => '1.2.3.4', 'consent_version' => 'v2']
        );

        $history = FadpComplianceService::getConsentHistory(self::USER_ID, self::TENANT_ID);

        $this->assertCount(1, $history);
        $this->assertSame('marketing_email', $history[0]['consent_type']);
        $this->assertSame('granted', $history[0]['action']);
        $this->assertSame('1.2.3.4', $history[0]['ip_address']);
        $this->assertSame('v2', $history[0]['consent_version']);
    }

    public function test_recordConsent_stores_optional_meta_as_json(): void
    {
        FadpComplianceService::recordConsent(
            self::USER_ID,
            self::TENANT_ID,
            'analytics',
            'granted',
            ['extra' => ['source' => 'onboarding', 'page' => '/welcome']]
        );

        $row = DB::table('fadp_consent_records')
            ->where('user_id', self::USER_ID)
            ->where('tenant_id', self::TENANT_ID)
            ->first();

        $this->assertNotNull($row);
        $decoded = json_decode($row->metadata, true);
        $this->assertSame('onboarding', $decoded['source'] ?? null);
    }

    public function test_recordConsent_withdrawal_persisted_correctly(): void
    {
        FadpComplianceService::recordConsent(self::USER_ID, self::TENANT_ID, 'push_notifications', 'granted');
        FadpComplianceService::recordConsent(self::USER_ID, self::TENANT_ID, 'push_notifications', 'withdrawn');

        $history = FadpComplianceService::getConsentHistory(self::USER_ID, self::TENANT_ID);

        // Both records exist; collect distinct actions regardless of sort order
        // (same-second inserts can have identical created_at, making DESC order indeterminate)
        $this->assertCount(2, $history);
        $actions = array_column($history, 'action');
        $this->assertContains('granted', $actions);
        $this->assertContains('withdrawn', $actions);
    }

    public function test_getConsentHistory_is_scoped_to_tenant_and_user(): void
    {
        // Insert for our tenant/user
        FadpComplianceService::recordConsent(self::USER_ID, self::TENANT_ID, 'marketing_email', 'granted');
        // Insert for a different tenant (raw insert, not via service to avoid setting TenantContext)
        DB::table('fadp_consent_records')->insert([
            'tenant_id'    => self::TENANT_ID + 1,
            'user_id'      => self::USER_ID,
            'consent_type' => 'marketing_email',
            'action'       => 'granted',
            'created_at'   => now(),
        ]);

        $history = FadpComplianceService::getConsentHistory(self::USER_ID, self::TENANT_ID);

        $this->assertCount(1, $history);
        $this->assertSame(self::TENANT_ID, (int) $history[0]['tenant_id']);
    }

    // =========================================================================
    // getConsentLedger (paginated admin view)
    // =========================================================================

    public function test_getConsentLedger_returns_paginated_structure(): void
    {
        // Insert 3 records
        for ($i = 0; $i < 3; $i++) {
            FadpComplianceService::recordConsent(self::USER_ID + $i, self::TENANT_ID, 'marketing_email', 'granted');
        }

        $result = FadpComplianceService::getConsentLedger(self::TENANT_ID, 1, 2);

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('per_page', $result);
        $this->assertArrayHasKey('last_page', $result);

        $this->assertSame(3, $result['total']);
        $this->assertSame(1, $result['page']);
        $this->assertSame(2, $result['per_page']);
        $this->assertSame(2, $result['last_page']);
        $this->assertCount(2, $result['items']);
    }

    public function test_getConsentLedger_second_page(): void
    {
        for ($i = 0; $i < 3; $i++) {
            FadpComplianceService::recordConsent(self::USER_ID + $i, self::TENANT_ID, 'analytics', 'granted');
        }

        $result = FadpComplianceService::getConsentLedger(self::TENANT_ID, 2, 2);

        $this->assertSame(2, $result['page']);
        $this->assertCount(1, $result['items']);
    }

    // =========================================================================
    // exportConsentLedger
    // =========================================================================

    public function test_exportConsentLedger_returns_all_records_for_tenant(): void
    {
        FadpComplianceService::recordConsent(self::USER_ID,     self::TENANT_ID, 'analytics',       'granted');
        FadpComplianceService::recordConsent(self::USER_ID + 1, self::TENANT_ID, 'marketing_email', 'granted');
        // Noise for another tenant
        DB::table('fadp_consent_records')->insert([
            'tenant_id' => self::TENANT_ID + 1, 'user_id' => 1,
            'consent_type' => 'analytics', 'action' => 'granted', 'created_at' => now(),
        ]);

        $export = FadpComplianceService::exportConsentLedger(self::TENANT_ID);

        $this->assertCount(2, $export);
        foreach ($export as $row) {
            $this->assertSame(self::TENANT_ID, (int) $row['tenant_id']);
        }
    }

    // =========================================================================
    // consentLedgerSummary
    // =========================================================================

    public function test_consentLedgerSummary_aggregates_by_type_and_action(): void
    {
        FadpComplianceService::recordConsent(self::USER_ID,     self::TENANT_ID, 'analytics', 'granted');
        FadpComplianceService::recordConsent(self::USER_ID + 1, self::TENANT_ID, 'analytics', 'granted');
        FadpComplianceService::recordConsent(self::USER_ID + 2, self::TENANT_ID, 'analytics', 'withdrawn');

        $summary = FadpComplianceService::consentLedgerSummary(self::TENANT_ID);

        $this->assertSame(3, $summary['total']);
        $this->assertNotNull($summary['latest_at']);
        $this->assertIsArray($summary['by_type_and_action']);

        // Find the analytics/granted bucket
        $grantedBucket = null;
        foreach ($summary['by_type_and_action'] as $bucket) {
            if ($bucket['consent_type'] === 'analytics' && $bucket['action'] === 'granted') {
                $grantedBucket = $bucket;
                break;
            }
        }

        $this->assertNotNull($grantedBucket, 'analytics/granted bucket missing from summary');
        $this->assertSame(2, $grantedBucket['total']);
    }

    // =========================================================================
    // getRetentionConfig (defaults)
    // =========================================================================

    public function test_getRetentionConfig_returns_defaults_when_no_row_exists(): void
    {
        $config = FadpComplianceService::getRetentionConfig(self::TENANT_ID);

        $this->assertArrayHasKey('config', $config);
        $this->assertArrayHasKey('data_residency', $config);
        $this->assertArrayHasKey('dpa_contact_email', $config);

        // Swiss FADP conservative minimum defaults
        $this->assertSame(7,  $config['config']['member_data_years']);
        $this->assertSame(10, $config['config']['transaction_data_years']);
        $this->assertSame(3,  $config['config']['activity_logs_years']);
        $this->assertSame(2,  $config['config']['messages_years']);
        $this->assertSame(1,  $config['config']['ai_embeddings_years']);
        $this->assertSame('EU', $config['data_residency']);
        $this->assertNull($config['dpa_contact_email']);
    }

    // =========================================================================
    // updateRetentionConfig + getRetentionConfig (persisted)
    // =========================================================================

    public function test_updateRetentionConfig_persists_and_getRetentionConfig_reads_back(): void
    {
        $input = [
            'config' => [
                'member_data_years'      => 10,
                'transaction_data_years' => 10,
                'activity_logs_years'    => 5,
                'messages_years'         => 3,
                'ai_embeddings_years'    => 2,
            ],
            'data_residency'    => 'Switzerland',
            'dpa_contact_email' => 'dpa@example.ch',
        ];

        FadpComplianceService::updateRetentionConfig(self::TENANT_ID, $input);

        $config = FadpComplianceService::getRetentionConfig(self::TENANT_ID);

        $this->assertSame('Switzerland', $config['data_residency']);
        $this->assertSame('dpa@example.ch', $config['dpa_contact_email']);
        $this->assertSame(10, $config['config']['member_data_years']);
        $this->assertSame(5,  $config['config']['activity_logs_years']);
    }

    public function test_updateRetentionConfig_seeds_default_activities_on_first_save(): void
    {
        // Confirm no activities yet
        $countBefore = DB::table('fadp_processing_activities')
            ->where('tenant_id', self::TENANT_ID)
            ->count();
        $this->assertSame(0, $countBefore);

        FadpComplianceService::updateRetentionConfig(self::TENANT_ID, [
            'data_residency' => 'EU',
        ]);

        $countAfter = DB::table('fadp_processing_activities')
            ->where('tenant_id', self::TENANT_ID)
            ->count();

        // 6 default activities are seeded
        $this->assertSame(6, $countAfter);
    }

    public function test_updateRetentionConfig_does_not_double_seed_on_second_call(): void
    {
        FadpComplianceService::updateRetentionConfig(self::TENANT_ID, ['data_residency' => 'EU']);
        FadpComplianceService::updateRetentionConfig(self::TENANT_ID, ['data_residency' => 'Switzerland']);

        $count = DB::table('fadp_processing_activities')
            ->where('tenant_id', self::TENANT_ID)
            ->count();

        // Still exactly 6 — no duplication
        $this->assertSame(6, $count);
    }

    // =========================================================================
    // getProcessingActivities
    // =========================================================================

    public function test_getProcessingActivities_returns_only_active_for_tenant(): void
    {
        // Seed via service (creates 6 activities)
        FadpComplianceService::updateRetentionConfig(self::TENANT_ID, ['data_residency' => 'EU']);

        // Soft-delete one
        $firstId = (int) DB::table('fadp_processing_activities')
            ->where('tenant_id', self::TENANT_ID)
            ->orderBy('id')
            ->value('id');

        FadpComplianceService::deleteProcessingActivity($firstId, self::TENANT_ID);

        $activities = FadpComplianceService::getProcessingActivities(self::TENANT_ID);

        $this->assertCount(5, $activities);
        foreach ($activities as $a) {
            $this->assertTrue($a['is_active']);
        }
    }

    public function test_getProcessingActivities_decodes_json_fields(): void
    {
        FadpComplianceService::updateRetentionConfig(self::TENANT_ID, ['data_residency' => 'EU']);

        $activities = FadpComplianceService::getProcessingActivities(self::TENANT_ID);

        $this->assertNotEmpty($activities);
        foreach ($activities as $activity) {
            $this->assertIsArray($activity['data_categories']);
            $this->assertIsArray($activity['recipients']);
            $this->assertIsBool($activity['is_automated_profiling']);
        }
    }

    // =========================================================================
    // upsertProcessingActivity (insert)
    // =========================================================================

    public function test_upsertProcessingActivity_insert_creates_row_and_returns_it(): void
    {
        $data = [
            'activity_name'         => 'Member wellbeing surveys',
            'purpose'               => 'Periodic optional feedback surveys',
            'data_categories'       => ['survey_responses', 'wellbeing_score'],
            'recipients'            => ['research_team'],
            'retention_period'      => '3 years',
            'legal_basis'           => 'consent',
            'is_automated_profiling' => false,
            'sort_order'            => 10,
        ];

        $result = FadpComplianceService::upsertProcessingActivity(self::TENANT_ID, $data);

        $this->assertNotEmpty($result);
        $this->assertSame('Member wellbeing surveys', $result['activity_name']);
        $this->assertSame(['survey_responses', 'wellbeing_score'], $result['data_categories']);
        $this->assertSame(['research_team'], $result['recipients']);
        $this->assertTrue($result['is_active']);
        $this->assertFalse($result['is_automated_profiling']);
    }

    // =========================================================================
    // upsertProcessingActivity (update)
    // =========================================================================

    public function test_upsertProcessingActivity_update_modifies_existing_row(): void
    {
        $inserted = FadpComplianceService::upsertProcessingActivity(self::TENANT_ID, [
            'activity_name'    => 'Old name',
            'purpose'          => 'Old purpose',
            'data_categories'  => ['email'],
            'retention_period' => '1 year',
            'legal_basis'      => 'consent',
        ]);

        $id = (int) $inserted['id'];

        $updated = FadpComplianceService::upsertProcessingActivity(self::TENANT_ID, [
            'id'               => $id,
            'activity_name'    => 'New name',
            'purpose'          => 'New purpose',
            'data_categories'  => ['email', 'phone'],
            'retention_period' => '2 years',
            'legal_basis'      => 'contract',
        ]);

        $this->assertSame($id, (int) $updated['id']);
        $this->assertSame('New name', $updated['activity_name']);
        $this->assertSame(['email', 'phone'], $updated['data_categories']);
    }

    public function test_upsertProcessingActivity_update_ignores_other_tenants_row(): void
    {
        // Insert a row directly for a different tenant
        $otherId = (int) DB::table('fadp_processing_activities')->insertGetId([
            'tenant_id'              => self::TENANT_ID + 1,
            'activity_name'          => 'Other tenant activity',
            'purpose'                => 'Other purpose',
            'data_categories'        => json_encode(['name']),
            'retention_period'       => '1 year',
            'legal_basis'            => 'consent',
            'is_automated_profiling' => false,
            'is_active'              => true,
            'sort_order'             => 0,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        // Attempt to update with a different tenant
        $result = FadpComplianceService::upsertProcessingActivity(self::TENANT_ID, [
            'id'               => $otherId,
            'activity_name'    => 'Hijacked name',
            'purpose'          => 'X',
            'data_categories'  => [],
            'retention_period' => '1 year',
            'legal_basis'      => 'consent',
        ]);

        // Should return empty because the row doesn't belong to self::TENANT_ID
        $this->assertSame([], $result);

        // The original row must be untouched
        $raw = DB::table('fadp_processing_activities')->where('id', $otherId)->first();
        $this->assertSame('Other tenant activity', $raw->activity_name);
    }

    // =========================================================================
    // deleteProcessingActivity (soft-delete)
    // =========================================================================

    public function test_deleteProcessingActivity_sets_is_active_false(): void
    {
        $activity = FadpComplianceService::upsertProcessingActivity(self::TENANT_ID, [
            'activity_name'    => 'To be deleted',
            'purpose'          => 'Temporary',
            'data_categories'  => [],
            'retention_period' => '1 year',
            'legal_basis'      => 'consent',
        ]);

        $id = (int) $activity['id'];
        FadpComplianceService::deleteProcessingActivity($id, self::TENANT_ID);

        $row = DB::table('fadp_processing_activities')->where('id', $id)->first();
        $this->assertSame(0, (int) $row->is_active);
    }

    // =========================================================================
    // generateProcessingRegister
    // =========================================================================

    public function test_generateProcessingRegister_returns_required_keys(): void
    {
        FadpComplianceService::updateRetentionConfig(self::TENANT_ID, ['data_residency' => 'Switzerland']);

        $register = FadpComplianceService::generateProcessingRegister(self::TENANT_ID);

        $this->assertArrayHasKey('tenant_id', $register);
        $this->assertArrayHasKey('tenant_name', $register);
        $this->assertArrayHasKey('generated_at', $register);
        $this->assertArrayHasKey('data_residency', $register);
        $this->assertArrayHasKey('dpa_contact_email', $register);
        $this->assertArrayHasKey('retention_config', $register);
        $this->assertArrayHasKey('processing_activities', $register);
        $this->assertArrayHasKey('total_activities', $register);
        $this->assertArrayHasKey('automated_profiling_count', $register);

        $this->assertSame(self::TENANT_ID, $register['tenant_id']);
        $this->assertSame('Switzerland', $register['data_residency']);
    }

    public function test_generateProcessingRegister_counts_automated_profiling_correctly(): void
    {
        // Insert one automated-profiling activity directly
        DB::table('fadp_processing_activities')->insert([
            'tenant_id'              => self::TENANT_ID,
            'activity_name'          => 'AI matching',
            'purpose'                => 'Profiling',
            'data_categories'        => json_encode(['embeddings']),
            'retention_period'       => '1 year',
            'legal_basis'            => 'consent',
            'is_automated_profiling' => true,
            'is_active'              => true,
            'sort_order'             => 1,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);
        // Insert one non-profiling activity
        DB::table('fadp_processing_activities')->insert([
            'tenant_id'              => self::TENANT_ID,
            'activity_name'          => 'Manual logging',
            'purpose'                => 'Records',
            'data_categories'        => json_encode(['name']),
            'retention_period'       => '7 years',
            'legal_basis'            => 'contract',
            'is_automated_profiling' => false,
            'is_active'              => true,
            'sort_order'             => 2,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $register = FadpComplianceService::generateProcessingRegister(self::TENANT_ID);

        $this->assertSame(2, $register['total_activities']);
        $this->assertSame(1, $register['automated_profiling_count']);
    }

    // =========================================================================
    // generateDisclosurePack
    // =========================================================================

    public function test_generateDisclosurePack_returns_required_top_level_keys(): void
    {
        FadpComplianceService::updateRetentionConfig(self::TENANT_ID, [
            'data_residency'    => 'Switzerland',
            'dpa_contact_email' => 'dpa@example.ch',
        ]);
        FadpComplianceService::recordConsent(self::USER_ID, self::TENANT_ID, 'analytics', 'granted');

        $pack = FadpComplianceService::generateDisclosurePack(self::TENANT_ID);

        $this->assertArrayHasKey('pack_name', $pack);
        $this->assertArrayHasKey('generated_at', $pack);
        $this->assertArrayHasKey('tenant', $pack);
        $this->assertArrayHasKey('data_residency_declaration', $pack);
        $this->assertArrayHasKey('retention_config', $pack);
        $this->assertArrayHasKey('processing_register', $pack);
        $this->assertArrayHasKey('consent_ledger_summary', $pack);
        $this->assertArrayHasKey('member_rights', $pack);
        $this->assertArrayHasKey('operator_controls', $pack);
    }

    public function test_generateDisclosurePack_isolated_node_true_for_switzerland(): void
    {
        FadpComplianceService::updateRetentionConfig(self::TENANT_ID, ['data_residency' => 'Switzerland']);

        $pack = FadpComplianceService::generateDisclosurePack(self::TENANT_ID);

        $this->assertTrue($pack['data_residency_declaration']['isolated_node_supported']);
    }

    public function test_generateDisclosurePack_isolated_node_false_for_eu(): void
    {
        FadpComplianceService::updateRetentionConfig(self::TENANT_ID, ['data_residency' => 'EU']);

        $pack = FadpComplianceService::generateDisclosurePack(self::TENANT_ID);

        $this->assertFalse($pack['data_residency_declaration']['isolated_node_supported']);
    }

    public function test_generateDisclosurePack_member_rights_includes_erasure(): void
    {
        FadpComplianceService::updateRetentionConfig(self::TENANT_ID, ['data_residency' => 'EU']);

        $pack = FadpComplianceService::generateDisclosurePack(self::TENANT_ID);

        $this->assertContains('erasure_or_restriction_where_applicable', $pack['member_rights']);
        $this->assertContains('data_portability', $pack['member_rights']);
        $this->assertContains('withdrawal_of_consent', $pack['member_rights']);
    }

    // =========================================================================
    // processingRegisterCsv
    // =========================================================================

    public function test_processingRegisterCsv_returns_csv_string_with_header(): void
    {
        FadpComplianceService::updateRetentionConfig(self::TENANT_ID, ['data_residency' => 'EU']);

        $csv = FadpComplianceService::processingRegisterCsv(self::TENANT_ID);

        $this->assertIsString($csv);
        $this->assertStringContainsString('"activity_name"', $csv);
        $this->assertStringContainsString('"legal_basis"', $csv);
        $this->assertStringContainsString('"automated_profiling"', $csv);
    }

    public function test_processingRegisterCsv_contains_activity_data_rows(): void
    {
        FadpComplianceService::updateRetentionConfig(self::TENANT_ID, ['data_residency' => 'EU']);

        $csv = FadpComplianceService::processingRegisterCsv(self::TENANT_ID);

        // The seeded default activities include 'Member account management'
        $this->assertStringContainsString('Member account management', $csv);
    }

    public function test_processingRegisterCsv_marks_automated_profiling_yes(): void
    {
        DB::table('fadp_processing_activities')->insert([
            'tenant_id'              => self::TENANT_ID,
            'activity_name'          => 'Profiling activity',
            'purpose'                => 'Matching',
            'data_categories'        => json_encode(['embeddings']),
            'retention_period'       => '1 year',
            'legal_basis'            => 'consent',
            'is_automated_profiling' => true,
            'is_active'              => true,
            'sort_order'             => 99,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $csv = FadpComplianceService::processingRegisterCsv(self::TENANT_ID);

        $this->assertStringContainsString('"yes"', $csv);
    }
}
