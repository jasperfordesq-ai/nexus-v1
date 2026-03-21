<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\GroupDiscussionSubscriber;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class GroupDiscussionSubscriberTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new GroupDiscussionSubscriber();
        $this->assertEquals('notification_settings', $model->getTable());
    }

    public function test_timestamps_are_disabled(): void
    {
        $model = new GroupDiscussionSubscriber();
        $this->assertFalse($model->usesTimestamps());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new GroupDiscussionSubscriber();
        $expected = [
            'user_id', 'context_type', 'context_id', 'frequency',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new GroupDiscussionSubscriber();
        $casts = $model->getCasts();
        $this->assertEquals('integer', $casts['user_id']);
        $this->assertEquals('integer', $casts['context_id']);
    }

    public function test_does_not_use_has_tenant_scope_trait(): void
    {
        $this->assertNotContains(
            HasTenantScope::class,
            class_uses_recursive(GroupDiscussionSubscriber::class)
        );
    }

    public function test_user_relationship_returns_belongs_to(): void
    {
        $model = new GroupDiscussionSubscriber();
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }
}
