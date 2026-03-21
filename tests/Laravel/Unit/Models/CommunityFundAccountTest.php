<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\CommunityFundAccount;
use App\Models\Concerns\HasTenantScope;
use Tests\Laravel\TestCase;

class CommunityFundAccountTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new CommunityFundAccount();
        $this->assertEquals('community_fund_accounts', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new CommunityFundAccount();
        $expected = [
            'tenant_id', 'balance', 'total_deposited', 'total_withdrawn',
            'total_donated', 'description',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new CommunityFundAccount();
        $casts = $model->getCasts();
        $this->assertEquals('float', $casts['balance']);
        $this->assertEquals('float', $casts['total_deposited']);
        $this->assertEquals('float', $casts['total_withdrawn']);
        $this->assertEquals('float', $casts['total_donated']);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(CommunityFundAccount::class)
        );
    }
}
