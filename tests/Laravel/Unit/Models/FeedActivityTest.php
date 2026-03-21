<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\FeedActivity;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class FeedActivityTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new FeedActivity();
        $this->assertEquals('feed_activity', $model->getTable());
    }

    public function test_timestamps_are_disabled(): void
    {
        $model = new FeedActivity();
        $this->assertFalse($model->usesTimestamps());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new FeedActivity();
        $expected = [
            'tenant_id', 'source_type', 'source_id', 'user_id',
            'title', 'content', 'image_url', 'metadata',
            'group_id', 'is_visible', 'is_hidden', 'created_at',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new FeedActivity();
        $casts = $model->getCasts();
        $this->assertEquals('integer', $casts['source_id']);
        $this->assertEquals('integer', $casts['user_id']);
        $this->assertEquals('integer', $casts['group_id']);
        $this->assertEquals('boolean', $casts['is_visible']);
        $this->assertEquals('boolean', $casts['is_hidden']);
        $this->assertEquals('array', $casts['metadata']);
        $this->assertEquals('datetime', $casts['created_at']);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(FeedActivity::class)
        );
    }

    public function test_user_relationship_returns_belongs_to(): void
    {
        $model = new FeedActivity();
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }
}
