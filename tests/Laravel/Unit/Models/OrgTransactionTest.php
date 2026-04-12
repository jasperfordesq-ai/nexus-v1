<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\OrgTransaction;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class OrgTransactionTest extends TestCase
{
    private OrgTransaction $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new OrgTransaction();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('org_transactions', $this->model->getTable());
    }

    public function test_updated_at_is_null(): void
    {
        $this->assertNull(OrgTransaction::UPDATED_AT);
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('integer', $casts['organization_id']);
        $this->assertEquals('integer', $casts['transfer_request_id']);
        $this->assertEquals('integer', $casts['sender_id']);
        $this->assertEquals('integer', $casts['receiver_id']);
        $this->assertEquals('float', $casts['amount']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(OrgTransaction::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    public function test_organization_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->organization());
    }

    public function test_transfer_request_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->transferRequest());
    }
}
