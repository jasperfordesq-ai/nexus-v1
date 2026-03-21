<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Campaign;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class CampaignTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new Campaign();
        $this->assertEquals('campaigns', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new Campaign();
        $expected = [
            'tenant_id', 'title', 'description', 'cover_image',
            'status', 'start_date', 'end_date', 'created_by',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new Campaign();
        $casts = $model->getCasts();
        $this->assertEquals('date', $casts['start_date']);
        $this->assertEquals('date', $casts['end_date']);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(Campaign::class)
        );
    }

    public function test_creator_relationship_returns_belongs_to(): void
    {
        $model = new Campaign();
        $this->assertInstanceOf(BelongsTo::class, $model->creator());
        $this->assertEquals('created_by', $model->creator()->getForeignKeyName());
    }
}
