<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\OrgTransferRequest;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\Laravel\TestCase;

class OrgTransferRequestTest extends TestCase
{
    private OrgTransferRequest $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new OrgTransferRequest();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('org_transfer_requests', $this->model->getTable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('integer', $casts['organization_id']);
        $this->assertEquals('integer', $casts['requester_id']);
        $this->assertEquals('integer', $casts['recipient_id']);
        $this->assertEquals('integer', $casts['approved_by']);
        $this->assertEquals('float', $casts['amount']);
        $this->assertEquals('datetime', $casts['approved_at']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(OrgTransferRequest::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    public function test_organization_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->organization());
    }

    public function test_requester_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->requester());
    }

    public function test_recipient_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->recipient());
    }

    public function test_approver_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->approver());
    }

    public function test_transactions_relationship(): void
    {
        $this->assertInstanceOf(HasMany::class, $this->model->transactions());
    }
}
