<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\Group;
use App\Models\GroupFeedback;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class GroupFeedbackTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new GroupFeedback();
        $this->assertEquals('group_feedback', $model->getTable());
    }

    public function test_updated_at_is_null(): void
    {
        $this->assertNull(GroupFeedback::UPDATED_AT);
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new GroupFeedback();
        $expected = [
            'group_id', 'user_id', 'rating', 'comment',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new GroupFeedback();
        $casts = $model->getCasts();
        $this->assertEquals('integer', $casts['group_id']);
        $this->assertEquals('integer', $casts['user_id']);
        $this->assertEquals('integer', $casts['rating']);
        $this->assertEquals('datetime', $casts['created_at']);
    }

    public function test_does_not_use_has_tenant_scope_trait(): void
    {
        $this->assertNotContains(
            HasTenantScope::class,
            class_uses_recursive(GroupFeedback::class)
        );
    }

    public function test_group_relationship_returns_belongs_to(): void
    {
        $model = new GroupFeedback();
        $this->assertInstanceOf(BelongsTo::class, $model->group());
    }

    public function test_user_relationship_returns_belongs_to(): void
    {
        $model = new GroupFeedback();
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }
}
