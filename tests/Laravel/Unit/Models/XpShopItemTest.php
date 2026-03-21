<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\XpShopItem;
use Tests\Laravel\TestCase;

class XpShopItemTest extends TestCase
{
    private XpShopItem $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new XpShopItem();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('xp_shop_items', $this->model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = [
            'tenant_id', 'item_key', 'name', 'description', 'icon',
            'item_type', 'xp_cost', 'stock_limit', 'per_user_limit',
            'display_order', 'is_active',
        ];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('integer', $casts['xp_cost']);
        $this->assertEquals('integer', $casts['stock_limit']);
        $this->assertEquals('integer', $casts['per_user_limit']);
        $this->assertEquals('integer', $casts['display_order']);
        $this->assertEquals('boolean', $casts['is_active']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(XpShopItem::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }
}
