<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\OrgWallet;
use Tests\Laravel\TestCase;

class OrgWalletTest extends TestCase
{
    private OrgWallet $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new OrgWallet();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('org_wallets', $this->model->getTable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('integer', $casts['organization_id']);
        $this->assertEquals('decimal:2', $casts['balance']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(OrgWallet::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }
}
