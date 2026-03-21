<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\BadgeCollectionItem;
use App\Models\BadgeCollection;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class BadgeCollectionItemTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new BadgeCollectionItem();
        $this->assertEquals('badge_collection_items', $model->getTable());
    }

    public function test_timestamps_are_disabled(): void
    {
        $model = new BadgeCollectionItem();
        $this->assertFalse($model->usesTimestamps());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new BadgeCollectionItem();
        $expected = [
            'collection_id', 'badge_key', 'display_order',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new BadgeCollectionItem();
        $casts = $model->getCasts();
        $this->assertEquals('integer', $casts['display_order']);
    }

    public function test_does_not_use_has_tenant_scope_trait(): void
    {
        $this->assertNotContains(
            HasTenantScope::class,
            class_uses_recursive(BadgeCollectionItem::class)
        );
    }

    public function test_collection_relationship_returns_belongs_to(): void
    {
        $model = new BadgeCollectionItem();
        $this->assertInstanceOf(BelongsTo::class, $model->collection());
        $this->assertEquals('collection_id', $model->collection()->getForeignKeyName());
    }
}
