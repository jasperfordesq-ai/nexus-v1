<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\GroupCustomFieldService;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class GroupCustomFieldServiceTest extends TestCase
{
    // ── getFields ────────────────────────────────────────────────────

    public function test_getFields_scopes_to_tenant_and_decodes_options(): void
    {
        DB::shouldReceive('table')->with('group_custom_fields')->andReturnSelf();
        DB::shouldReceive('where')->with('tenant_id', $this->testTenantId)->andReturnSelf();
        DB::shouldReceive('orderBy')->with('sort_order')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([
            (object) [
                'id' => 1,
                'field_name' => 'Region',
                'field_key' => 'region',
                'options' => '["North","South"]',
            ],
        ]));

        $result = GroupCustomFieldService::getFields();
        $this->assertCount(1, $result);
        $this->assertSame(['North', 'South'], $result[0]['options']);
    }

    // ── createField ──────────────────────────────────────────────────

    public function test_createField_inserts_with_tenant_and_default_values(): void
    {
        DB::shouldReceive('table')->with('group_custom_fields')->andReturnSelf();
        DB::shouldReceive('insertGetId')->once()
            ->withArgs(function ($data) {
                return $data['tenant_id'] === $this->testTenantId
                    && $data['field_name'] === 'Budget'
                    && $data['field_key'] === 'budget'
                    && $data['field_type'] === 'text'
                    && $data['is_required'] === false
                    && $data['sort_order'] === 0;
            })
            ->andReturn(42);

        $id = GroupCustomFieldService::createField([
            'field_name' => 'Budget',
        ]);

        $this->assertSame(42, $id);
    }

    public function test_createField_encodes_options_array(): void
    {
        DB::shouldReceive('table')->with('group_custom_fields')->andReturnSelf();
        DB::shouldReceive('insertGetId')->once()
            ->withArgs(function ($data) {
                return $data['options'] === json_encode(['a', 'b']);
            })
            ->andReturn(1);

        GroupCustomFieldService::createField([
            'field_name' => 'Choice',
            'options' => ['a', 'b'],
        ]);

        $this->assertTrue(true);
    }

    // ── deleteField ──────────────────────────────────────────────────

    public function test_deleteField_returns_true_when_deleted(): void
    {
        DB::shouldReceive('table')->with('group_custom_field_values')->once()->andReturnSelf();
        DB::shouldReceive('where')->with('field_id', 5)->once()->andReturnSelf();
        DB::shouldReceive('delete')->andReturn(2, 1);

        DB::shouldReceive('table')->with('group_custom_fields')->once()->andReturnSelf();
        DB::shouldReceive('where')->with('id', 5)->once()->andReturnSelf();
        DB::shouldReceive('where')->with('tenant_id', $this->testTenantId)->once()->andReturnSelf();

        $this->assertTrue(GroupCustomFieldService::deleteField(5));
    }

    public function test_deleteField_returns_false_when_nothing_deleted(): void
    {
        DB::shouldReceive('table')->with('group_custom_field_values')->once()->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('delete')->andReturn(0, 0);
        DB::shouldReceive('table')->with('group_custom_fields')->once()->andReturnSelf();

        $this->assertFalse(GroupCustomFieldService::deleteField(5));
    }

    // ── setValues ────────────────────────────────────────────────────

    public function test_setValues_skips_unknown_field_keys(): void
    {
        DB::shouldReceive('table')->with('group_custom_fields')->once()->andReturnSelf();
        DB::shouldReceive('where')->with('tenant_id', $this->testTenantId)->once()->andReturnSelf();
        DB::shouldReceive('pluck')->with('id', 'field_key')->once()->andReturn(collect(['region' => 10]));
        DB::shouldReceive('toArray')->andReturn(['region' => 10]);

        // Only the 'region' field should be upserted — 'unknown' is skipped.
        DB::shouldReceive('table')->with('group_custom_field_values')->once()->andReturnSelf();
        DB::shouldReceive('updateOrInsert')->once();

        GroupCustomFieldService::setValues(7, [
            'region' => 'North',
            'unknown' => 'should skip',
        ]);

        $this->assertTrue(true);
    }
}
