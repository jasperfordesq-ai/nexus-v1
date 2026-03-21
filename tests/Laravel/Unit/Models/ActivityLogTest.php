<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\ActivityLog;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class ActivityLogTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new ActivityLog();
        $this->assertEquals('activity_log', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new ActivityLog();
        $expected = [
            'user_id', 'action', 'details', 'is_public', 'link_url',
            'ip_address', 'action_type', 'entity_type', 'entity_id',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new ActivityLog();
        $casts = $model->getCasts();
        $this->assertEquals('integer', $casts['user_id']);
        $this->assertEquals('boolean', $casts['is_public']);
        $this->assertEquals('integer', $casts['entity_id']);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(ActivityLog::class)
        );
    }

    public function test_user_relationship_returns_belongs_to(): void
    {
        $model = new ActivityLog();
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }
}
