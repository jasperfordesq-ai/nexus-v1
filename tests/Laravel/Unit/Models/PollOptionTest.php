<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\PollOption;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class PollOptionTest extends TestCase
{
    private PollOption $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new PollOption();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('poll_options', $this->model->getTable());
    }

    public function test_uses_has_tenant_scope(): void
    {
        // PollOption was tenant-scoped in the platform audit (commit 81ab54907)
        // to close a cross-tenant IDOR; it now carries HasTenantScope + tenant_id.
        $traits = class_uses_recursive(PollOption::class);
        $this->assertContains(\App\Models\Concerns\HasTenantScope::class, $traits);
    }

    public function test_poll_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->poll());
    }
}
