<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\MunicipalReportTemplateService;
use App\Core\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

/**
 * MunicipalReportTemplateServiceTest
 *
 * Tests CRUD operations, normalisation logic (audience/date_preset/sections
 * fallbacks, hour_value_chf clamping, include_social_value coercion),
 * and tenant isolation.
 *
 * Fixture strategy: direct DB::table insertGetId (no Eloquent models needed).
 * All rows rolled back by DatabaseTransactions.
 */
class MunicipalReportTemplateServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID  = 2;
    private const OTHER_TENANT = 9996;
    private const USER_ID    = 1;

    private MunicipalReportTemplateService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
        $this->svc = new MunicipalReportTemplateService();
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    /** Minimal valid input for create(). */
    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'name'                 => 'Q1 Municipal Report ' . uniqid(),
            'description'          => 'Quarterly summary for the municipality.',
            'audience'             => 'municipality',
            'date_preset'          => 'last_90_days',
            'include_social_value' => true,
            'hour_value_chf'       => 20,
            'sections'             => ['summary', 'hours', 'members'],
        ], $overrides);
    }

    // ── create ────────────────────────────────────────────────────────────────

    public function test_create_returns_formatted_array_with_id(): void
    {
        $result = $this->svc->create(self::TENANT_ID, self::USER_ID, $this->validInput());

        $this->assertIsInt($result['id']);
        $this->assertGreaterThan(0, $result['id']);
    }

    public function test_create_persists_all_fields_correctly(): void
    {
        $input  = $this->validInput([
            'name'                 => 'Test Template ABC',
            'audience'             => 'canton',
            'date_preset'          => 'year_to_date',
            'include_social_value' => true,
            'hour_value_chf'       => 35,
            'sections'             => ['summary', 'hours'],
        ]);

        $result = $this->svc->create(self::TENANT_ID, self::USER_ID, $input);

        $this->assertSame('Test Template ABC', $result['name']);
        $this->assertSame('canton', $result['audience']);
        $this->assertSame('year_to_date', $result['date_preset']);
        $this->assertTrue($result['include_social_value']);
        $this->assertSame(35, $result['hour_value_chf']);
        $this->assertSame(['summary', 'hours'], $result['sections']);
    }

    public function test_create_stores_null_description_when_empty_string(): void
    {
        $result = $this->svc->create(self::TENANT_ID, self::USER_ID, $this->validInput(['description' => '']));

        $this->assertNull($result['description']);
    }

    public function test_create_result_has_expected_keys(): void
    {
        $result = $this->svc->create(self::TENANT_ID, self::USER_ID, $this->validInput());

        foreach (['id', 'name', 'description', 'audience', 'date_preset',
                  'include_social_value', 'hour_value_chf', 'sections',
                  'created_at', 'updated_at'] as $key) {
            $this->assertArrayHasKey($key, $result, "Key '{$key}' missing from create result.");
        }
    }

    // ── get ───────────────────────────────────────────────────────────────────

    public function test_get_returns_null_for_unknown_id(): void
    {
        $result = $this->svc->get(self::TENANT_ID, 9999999);

        $this->assertNull($result);
    }

    public function test_get_returns_template_by_id(): void
    {
        $created = $this->svc->create(self::TENANT_ID, self::USER_ID, $this->validInput(['name' => 'Get Test']));

        $result = $this->svc->get(self::TENANT_ID, $created['id']);

        $this->assertSame($created['id'], $result['id']);
        $this->assertSame('Get Test', $result['name']);
    }

    public function test_get_is_tenant_scoped(): void
    {
        // Insert a row belonging to a different tenant directly.
        $id = DB::table('municipal_report_templates')->insertGetId([
            'tenant_id'           => self::OTHER_TENANT,
            'name'                => 'Other Tenant Template',
            'audience'            => 'municipality',
            'date_preset'         => 'last_90_days',
            'include_social_value'=> 1,
            'sections'            => json_encode(['summary']),
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        $result = $this->svc->get(self::TENANT_ID, $id);

        $this->assertNull($result, 'get() must not return rows belonging to a different tenant.');
    }

    // ── list ─────────────────────────────────────────────────────────────────

    public function test_list_returns_empty_array_when_no_templates(): void
    {
        // Use an isolated tenant that has no templates.
        $result = $this->svc->list(88881);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_list_returns_only_templates_for_given_tenant(): void
    {
        $this->svc->create(self::TENANT_ID, self::USER_ID, $this->validInput(['name' => 'List T1']));
        $this->svc->create(self::TENANT_ID, self::USER_ID, $this->validInput(['name' => 'List T2']));
        // Different tenant — should not appear.
        DB::table('municipal_report_templates')->insertOrIgnore([
            'tenant_id'           => self::OTHER_TENANT,
            'name'                => 'Other Tenant',
            'audience'            => 'municipality',
            'date_preset'         => 'last_90_days',
            'include_social_value'=> 1,
            'sections'            => json_encode(['summary']),
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        $list = $this->svc->list(self::TENANT_ID);

        $names = array_column($list, 'name');
        $this->assertContains('List T1', $names);
        $this->assertContains('List T2', $names);
        $this->assertNotContains('Other Tenant', $names);
    }

    // ── update ────────────────────────────────────────────────────────────────

    public function test_update_modifies_fields(): void
    {
        $created = $this->svc->create(self::TENANT_ID, self::USER_ID, $this->validInput(['name' => 'Before Update']));

        $updated = $this->svc->update(self::TENANT_ID, self::USER_ID, $created['id'], [
            'name'        => 'After Update',
            'audience'    => 'cooperative',
            'date_preset' => 'previous_quarter',
        ]);

        $this->assertSame('After Update', $updated['name']);
        $this->assertSame('cooperative', $updated['audience']);
        $this->assertSame('previous_quarter', $updated['date_preset']);
    }

    public function test_update_returns_null_for_unknown_id(): void
    {
        $result = $this->svc->update(self::TENANT_ID, self::USER_ID, 9999999, ['name' => 'X']);

        $this->assertNull($result);
    }

    public function test_update_is_tenant_scoped(): void
    {
        $id = DB::table('municipal_report_templates')->insertGetId([
            'tenant_id'           => self::OTHER_TENANT,
            'name'                => 'Cannot Update This',
            'audience'            => 'municipality',
            'date_preset'         => 'last_90_days',
            'include_social_value'=> 1,
            'sections'            => json_encode(['summary']),
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        $result = $this->svc->update(self::TENANT_ID, self::USER_ID, $id, ['name' => 'Hijacked']);

        $this->assertNull($result);
        // Row in other tenant unchanged.
        $row = DB::table('municipal_report_templates')->where('id', $id)->first();
        $this->assertSame('Cannot Update This', $row->name);
    }

    // ── delete ────────────────────────────────────────────────────────────────

    public function test_delete_removes_template_and_returns_true(): void
    {
        $created = $this->svc->create(self::TENANT_ID, self::USER_ID, $this->validInput());

        $result = $this->svc->delete(self::TENANT_ID, $created['id']);

        $this->assertTrue($result);
        $this->assertNull($this->svc->get(self::TENANT_ID, $created['id']));
    }

    public function test_delete_returns_false_for_unknown_id(): void
    {
        $result = $this->svc->delete(self::TENANT_ID, 9999999);

        $this->assertFalse($result);
    }

    public function test_delete_is_tenant_scoped(): void
    {
        $id = DB::table('municipal_report_templates')->insertGetId([
            'tenant_id'           => self::OTHER_TENANT,
            'name'                => 'Protected Template',
            'audience'            => 'municipality',
            'date_preset'         => 'last_90_days',
            'include_social_value'=> 1,
            'sections'            => json_encode(['summary']),
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        $result = $this->svc->delete(self::TENANT_ID, $id);

        $this->assertFalse($result);
        // Row still exists.
        $this->assertNotNull(DB::table('municipal_report_templates')->where('id', $id)->first());
    }

    // ── normalise: audience fallback ─────────────────────────────────────────

    public function test_create_falls_back_audience_to_municipality_for_invalid_value(): void
    {
        $result = $this->svc->create(self::TENANT_ID, self::USER_ID, $this->validInput(['audience' => 'invalid_audience']));

        $this->assertSame('municipality', $result['audience']);
    }

    public function test_create_accepts_all_valid_audiences(): void
    {
        foreach (['municipality', 'canton', 'cooperative', 'foundation'] as $audience) {
            $result = $this->svc->create(
                self::TENANT_ID,
                self::USER_ID,
                $this->validInput(['audience' => $audience, 'name' => 'Aud-' . $audience . '-' . uniqid()])
            );
            $this->assertSame($audience, $result['audience'], "Audience '{$audience}' was not stored.");
        }
    }

    // ── normalise: date_preset fallback ──────────────────────────────────────

    public function test_create_falls_back_date_preset_to_last_90_days_for_invalid_value(): void
    {
        $result = $this->svc->create(self::TENANT_ID, self::USER_ID, $this->validInput(['date_preset' => 'bad_preset']));

        $this->assertSame('last_90_days', $result['date_preset']);
    }

    // ── normalise: sections ──────────────────────────────────────────────────

    public function test_create_uses_all_sections_when_sections_is_empty_array(): void
    {
        $allSections = ['summary', 'hours', 'members', 'organisations', 'categories', 'trends', 'trust'];

        $result = $this->svc->create(self::TENANT_ID, self::USER_ID, $this->validInput(['sections' => []]));

        $this->assertSame($allSections, $result['sections']);
    }

    public function test_create_strips_unknown_sections(): void
    {
        $result = $this->svc->create(
            self::TENANT_ID,
            self::USER_ID,
            $this->validInput(['sections' => ['summary', 'unknown_section', 'hours']])
        );

        $this->assertSame(['summary', 'hours'], $result['sections']);
    }

    // ── normalise: hour_value_chf clamping ───────────────────────────────────

    public function test_create_clamps_hour_value_chf_to_500_maximum(): void
    {
        $result = $this->svc->create(self::TENANT_ID, self::USER_ID, $this->validInput(['hour_value_chf' => 9999]));

        $this->assertSame(500, $result['hour_value_chf']);
    }

    public function test_create_stores_null_hour_value_chf_when_not_provided(): void
    {
        $result = $this->svc->create(
            self::TENANT_ID,
            self::USER_ID,
            $this->validInput(['hour_value_chf' => ''])
        );

        $this->assertNull($result['hour_value_chf']);
    }

    // ── normalise: include_social_value coercion ─────────────────────────────

    public function test_create_coerces_include_social_value_to_bool(): void
    {
        $resultTrue  = $this->svc->create(
            self::TENANT_ID,
            self::USER_ID,
            $this->validInput(['include_social_value' => 1, 'name' => 'SVTrue-' . uniqid()])
        );
        $resultFalse = $this->svc->create(
            self::TENANT_ID,
            self::USER_ID,
            $this->validInput(['include_social_value' => 0, 'name' => 'SVFalse-' . uniqid()])
        );

        $this->assertTrue($resultTrue['include_social_value']);
        $this->assertFalse($resultFalse['include_social_value']);
    }
}
