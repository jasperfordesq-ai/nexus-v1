<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\VolOrganization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\Laravel\TestCase;

class VolOrganizationTest extends TestCase
{
    private VolOrganization $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new VolOrganization();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('vol_organizations', $this->model->getTable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('boolean', $casts['auto_pay_enabled']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(VolOrganization::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    public function test_owner_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->owner());
    }

    public function test_opportunities_relationship(): void
    {
        $this->assertInstanceOf(HasMany::class, $this->model->opportunities());
    }
}
