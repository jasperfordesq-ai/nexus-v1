<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\ContentModerationQueue;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class ContentModerationQueueTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new ContentModerationQueue();
        $this->assertEquals('content_moderation_queue', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new ContentModerationQueue();
        $expected = [
            'tenant_id', 'content_type', 'content_id', 'author_id',
            'title', 'status', 'reviewer_id', 'reviewed_at',
            'rejection_reason', 'auto_flagged', 'flag_reason',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new ContentModerationQueue();
        $casts = $model->getCasts();
        $this->assertEquals('integer', $casts['content_id']);
        $this->assertEquals('integer', $casts['author_id']);
        $this->assertEquals('integer', $casts['reviewer_id']);
        $this->assertEquals('boolean', $casts['auto_flagged']);
        $this->assertEquals('datetime', $casts['reviewed_at']);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(ContentModerationQueue::class)
        );
    }

    public function test_author_relationship_returns_belongs_to(): void
    {
        $model = new ContentModerationQueue();
        $this->assertInstanceOf(BelongsTo::class, $model->author());
        $this->assertEquals('author_id', $model->author()->getForeignKeyName());
    }

    public function test_reviewer_relationship_returns_belongs_to(): void
    {
        $model = new ContentModerationQueue();
        $this->assertInstanceOf(BelongsTo::class, $model->reviewer());
        $this->assertEquals('reviewer_id', $model->reviewer()->getForeignKeyName());
    }
}
