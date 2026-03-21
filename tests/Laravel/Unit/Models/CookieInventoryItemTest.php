<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\CookieInventoryItem;
use App\Models\Concerns\HasTenantScope;
use Tests\Laravel\TestCase;

class CookieInventoryItemTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new CookieInventoryItem();
        $this->assertEquals('cookie_inventory', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new CookieInventoryItem();
        $expected = [
            'cookie_name', 'category', 'purpose', 'duration',
            'third_party', 'tenant_id', 'is_active',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new CookieInventoryItem();
        $casts = $model->getCasts();
        $this->assertEquals('boolean', $casts['is_active']);
        $this->assertEquals('integer', $casts['tenant_id']);
    }

    public function test_does_not_use_has_tenant_scope_trait(): void
    {
        $this->assertNotContains(
            HasTenantScope::class,
            class_uses_recursive(CookieInventoryItem::class)
        );
    }
}
