<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\BadgeCollection;
use App\Models\BadgeCollectionItem;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\Laravel\TestCase;

class BadgeCollectionTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new BadgeCollection();
        $this->assertEquals('badge_collections', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new BadgeCollection();
        $expected = [
            'tenant_id', 'collection_key', 'name', 'description',
            'icon', 'bonus_xp', 'bonus_badge_key', 'display_order',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new BadgeCollection();
        $casts = $model->getCasts();
        $this->assertEquals('integer', $casts['bonus_xp']);
        $this->assertEquals('integer', $casts['display_order']);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(BadgeCollection::class)
        );
    }

    public function test_items_relationship_returns_has_many(): void
    {
        $model = new BadgeCollection();
        $this->assertInstanceOf(HasMany::class, $model->items());
        $this->assertEquals('collection_id', $model->items()->getForeignKeyName());
    }
}
