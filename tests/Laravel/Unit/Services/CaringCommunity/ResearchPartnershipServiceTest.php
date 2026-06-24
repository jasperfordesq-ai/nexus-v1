<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services\CaringCommunity;

use App\Core\TenantContext;
use App\Services\CaringCommunity\ResearchPartnershipService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;
use Tests\Laravel\TestCase;

class ResearchPartnershipServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        if (!Schema::hasTable('caring_research_partners')
            || !Schema::hasTable('caring_research_consents')
            || !Schema::hasTable('caring_research_dataset_exports')
        ) {
            $this->markTestSkipped('caring_research_* tables not present.');
        }

        TenantContext::setById($this->testTenantId);
    }

    private function service(): ResearchPartnershipService
    {
        return app(ResearchPartnershipService::class);
    }

    /** Insert a minimal partner row, returns the DB id. */
    private function insertPartner(array $overrides = []): int
    {
        $now = now();
        return (int) DB::table('caring_research_partners')->insertGetId(array_merge([
            'tenant_id'  => $this->testTenantId,
            'name'       => 'Test Partner',
            'institution' => 'Test University',
            'status'     => 'active',
            'data_scope' => json_encode(['datasets' => ['caring_community_aggregate_v1']]),
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides));
    }

    /** Insert a minimal export row, returns the DB id. */
    private function insertExport(int $partnerId, array $overrides = []): int
    {
        $now = now();
        return (int) DB::table('caring_research_dataset_exports')->insertGetId(array_merge([
            'tenant_id'             => $this->testTenantId,
            'partner_id'            => $partnerId,
            'requested_by'          => 1,
            'dataset_key'           => 'caring_community_aggregate_v1',
            'period_start'          => '2024-01-01',
            'period_end'            => '2024-03-31',
            'status'                => 'generated',
            'row_count'             => 0,
            'anonymization_version' => 'aggregate-v1',
            'data_hash'             => str_repeat('a', 64),
            'generated_at'          => $now,
            'metadata'              => json_encode(['partner_name' => 'Test Partner']),
            'created_at'            => $now,
            'updated_at'            => $now,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // isAvailable
    // -------------------------------------------------------------------------

    public function test_is_available_returns_true_when_tables_exist(): void
    {
        $this->assertTrue($this->service()->isAvailable());
    }

    // -------------------------------------------------------------------------
    // createPartner
    // -------------------------------------------------------------------------

    public function test_create_partner_persists_required_fields(): void
    {
        $result = $this->service()->createPartner($this->testTenantId, 1, [
            'name'        => 'ETH Zürich',
            'institution' => 'ETH Zürich Institute of Social Science',
            'status'      => 'draft',
        ]);

        $this->assertSame('ETH Zürich', $result['name']);
        $this->assertSame('ETH Zürich Institute of Social Science', $result['institution']);
        $this->assertSame('draft', $result['status']);
        $this->assertSame($this->testTenantId, $result['tenant_id']);
        $this->assertIsInt($result['id']);
        $this->assertGreaterThan(0, $result['id']);
    }

    public function test_create_partner_persists_optional_fields(): void
    {
        $result = $this->service()->createPartner($this->testTenantId, 1, [
            'name'                 => 'Pro Senectute',
            'institution'          => 'Pro Senectute Zürich',
            'contact_email'        => 'research@ps.ch',
            'agreement_reference'  => 'aggregate_dataset_v1',
            'methodology_url'      => 'https://ps.ch/methodology',
            'status'               => 'active',
            'starts_at'            => '2024-01-01',
            'ends_at'              => '2024-12-31',
            'data_scope'           => ['datasets' => ['caring_community_aggregate_v1']],
        ]);

        $this->assertSame('research@ps.ch', $result['contact_email']);
        $this->assertSame('aggregate_dataset_v1', $result['agreement_reference']);
        $this->assertSame('https://ps.ch/methodology', $result['methodology_url']);
        $this->assertSame('active', $result['status']);
        $this->assertSame('2024-01-01', $result['starts_at']);
        $this->assertSame('2024-12-31', $result['ends_at']);
        $this->assertSame(['datasets' => ['caring_community_aggregate_v1']], $result['data_scope']);
    }

    public function test_create_partner_throws_when_name_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service()->createPartner($this->testTenantId, 1, [
            'name'        => '',
            'institution' => 'Some University',
        ]);
    }

    public function test_create_partner_throws_when_institution_missing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service()->createPartner($this->testTenantId, 1, [
            'name'        => 'Valid Name',
            'institution' => '  ',
        ]);
    }

    public function test_create_partner_defaults_invalid_status_to_draft(): void
    {
        $result = $this->service()->createPartner($this->testTenantId, 1, [
            'name'        => 'FHNW',
            'institution' => 'FHNW Social Sciences',
            'status'      => 'unknown_status',
        ]);

        $this->assertSame('draft', $result['status']);
    }

    public function test_create_partner_normalises_non_array_data_scope(): void
    {
        $result = $this->service()->createPartner($this->testTenantId, 1, [
            'name'       => 'Age Stiftung',
            'institution' => 'Age Stiftung Basel',
            'data_scope' => 'not-an-array',
        ]);

        $this->assertIsArray($result['data_scope']);
        $this->assertArrayHasKey('datasets', $result['data_scope']);
        $this->assertSame(['caring_community_aggregate_v1'], $result['data_scope']['datasets']);
    }

    // -------------------------------------------------------------------------
    // listPartners
    // -------------------------------------------------------------------------

    public function test_list_partners_returns_only_own_tenant_rows(): void
    {
        $this->insertPartner(['name' => 'OurPartner', 'tenant_id' => $this->testTenantId]);
        $this->insertPartner(['name' => 'OtherTenant', 'tenant_id' => 999]);

        $results = $this->service()->listPartners($this->testTenantId);

        foreach ($results as $row) {
            $this->assertSame($this->testTenantId, $row['tenant_id']);
        }
        $names = array_column($results, 'name');
        $this->assertContains('OurPartner', $names);
        $this->assertNotContains('OtherTenant', $names);
    }

    // -------------------------------------------------------------------------
    // getConsent / recordConsent
    // -------------------------------------------------------------------------

    public function test_get_consent_returns_opted_out_default_for_unknown_user(): void
    {
        $result = $this->service()->getConsent($this->testTenantId, 999999);

        $this->assertSame('opted_out', $result['consent_status']);
        $this->assertSame('research-v1', $result['consent_version']);
        $this->assertNull($result['consented_at']);
        $this->assertNull($result['revoked_at']);
    }

    public function test_record_consent_opted_in_sets_consented_at(): void
    {
        $result = $this->service()->recordConsent($this->testTenantId, 888, 'opted_in', 'Happy to help');

        $this->assertSame('opted_in', $result['consent_status']);
        $this->assertNotNull($result['consented_at']);
        $this->assertNull($result['revoked_at']);
    }

    public function test_record_consent_revoked_sets_revoked_at_and_clears_consented_at(): void
    {
        // first opt in
        $this->service()->recordConsent($this->testTenantId, 777, 'opted_in');
        // now revoke
        $result = $this->service()->recordConsent($this->testTenantId, 777, 'revoked', 'Changed my mind');

        $this->assertSame('revoked', $result['consent_status']);
        $this->assertNotNull($result['revoked_at']);
        $this->assertNull($result['consented_at']);
    }

    public function test_record_consent_is_idempotent_upsert(): void
    {
        $this->service()->recordConsent($this->testTenantId, 666, 'opted_in');
        $second = $this->service()->recordConsent($this->testTenantId, 666, 'opted_out');

        // Only one row should exist (updateOrInsert).
        $count = DB::table('caring_research_consents')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', 666)
            ->count();
        $this->assertSame(1, $count);
        $this->assertSame('opted_out', $second['consent_status']);
    }

    public function test_record_consent_throws_for_invalid_status(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service()->recordConsent($this->testTenantId, 555, 'maybe');
    }

    // -------------------------------------------------------------------------
    // generateDatasetExport
    // -------------------------------------------------------------------------

    public function test_generate_dataset_export_throws_for_non_existent_partner(): void
    {
        $this->expectException(RuntimeException::class);
        $this->service()->generateDatasetExport($this->testTenantId, 999999, 1, '2024-01-01', '2024-03-31');
    }

    public function test_generate_dataset_export_throws_for_non_active_partner(): void
    {
        $draftId = $this->insertPartner(['status' => 'draft']);

        $this->expectException(RuntimeException::class);
        $this->service()->generateDatasetExport($this->testTenantId, $draftId, 1, '2024-01-01', '2024-03-31');
    }

    public function test_generate_dataset_export_succeeds_for_active_partner(): void
    {
        $activeId = $this->insertPartner(['status' => 'active']);

        $result = $this->service()->generateDatasetExport(
            $this->testTenantId,
            $activeId,
            1,
            '2024-01-01',
            '2024-03-31'
        );

        $this->assertArrayHasKey('export', $result);
        $this->assertArrayHasKey('dataset', $result);

        $export = $result['export'];
        $this->assertSame($this->testTenantId, $export['tenant_id']);
        $this->assertSame($activeId, $export['partner_id']);
        $this->assertSame('generated', $export['status']);
        $this->assertSame('caring_community_aggregate_v1', $export['dataset_key']);
        $this->assertSame('2024-01-01', $export['period_start']);
        $this->assertSame('2024-03-31', $export['period_end']);
        $this->assertSame('aggregate-v1', $export['anonymization_version']);
        $this->assertIsString($export['data_hash']);
        $this->assertSame(64, strlen($export['data_hash']));

        $dataset = $result['dataset'];
        $this->assertSame('caring_community_aggregate_v1', $dataset['dataset_key']);
        $this->assertFalse($dataset['anonymization']['direct_identifiers']);
        $this->assertFalse($dataset['anonymization']['row_level_member_records']);
        $this->assertSame(5, $dataset['anonymization']['suppression_threshold']);
    }

    // -------------------------------------------------------------------------
    // listDatasetExports
    // -------------------------------------------------------------------------

    public function test_list_dataset_exports_returns_only_own_tenant(): void
    {
        $partnerId = $this->insertPartner();
        $this->insertExport($partnerId, ['tenant_id' => $this->testTenantId]);
        $this->insertExport($partnerId, ['tenant_id' => 999]);

        $results = $this->service()->listDatasetExports($this->testTenantId);

        foreach ($results as $row) {
            $this->assertSame($this->testTenantId, $row['tenant_id']);
        }
    }

    public function test_list_dataset_exports_filters_by_partner_id(): void
    {
        $p1 = $this->insertPartner(['name' => 'PartnerA']);
        $p2 = $this->insertPartner(['name' => 'PartnerB']);
        $this->insertExport($p1);
        $this->insertExport($p2);

        $results = $this->service()->listDatasetExports($this->testTenantId, $p1);

        foreach ($results as $row) {
            $this->assertSame($p1, $row['partner_id']);
        }
        $this->assertGreaterThanOrEqual(1, count($results));
    }

    // -------------------------------------------------------------------------
    // revokeDatasetExport
    // -------------------------------------------------------------------------

    public function test_revoke_dataset_export_sets_status_to_revoked(): void
    {
        $partnerId = $this->insertPartner();
        $exportId  = $this->insertExport($partnerId);

        $result = $this->service()->revokeDatasetExport($this->testTenantId, $exportId, 1);

        $this->assertSame('revoked', $result['status']);
        $this->assertArrayHasKey('revoked_by', $result['metadata']);
        $this->assertArrayHasKey('revoked_at', $result['metadata']);
        $this->assertSame(1, $result['metadata']['revoked_by']);
    }

    public function test_revoke_dataset_export_throws_for_non_existent_export(): void
    {
        $this->expectException(RuntimeException::class);
        $this->service()->revokeDatasetExport($this->testTenantId, 999999, 1);
    }
}
