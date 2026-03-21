<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\CommunityFundTransaction;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class CommunityFundTransactionTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new CommunityFundTransaction();
        $this->assertEquals('community_fund_transactions', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new CommunityFundTransaction();
        $expected = [
            'tenant_id', 'fund_id', 'user_id', 'type', 'amount',
            'balance_after', 'description', 'admin_id',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new CommunityFundTransaction();
        $casts = $model->getCasts();
        $this->assertEquals('float', $casts['amount']);
        $this->assertEquals('float', $casts['balance_after']);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(CommunityFundTransaction::class)
        );
    }

    public function test_user_relationship_returns_belongs_to(): void
    {
        $model = new CommunityFundTransaction();
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }

    public function test_admin_relationship_returns_belongs_to(): void
    {
        $model = new CommunityFundTransaction();
        $this->assertInstanceOf(BelongsTo::class, $model->admin());
        $this->assertEquals('admin_id', $model->admin()->getForeignKeyName());
    }
}
