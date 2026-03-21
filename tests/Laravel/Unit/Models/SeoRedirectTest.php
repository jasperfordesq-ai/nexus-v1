<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\SeoRedirect;
use Tests\Laravel\TestCase;

class SeoRedirectTest extends TestCase
{
    private SeoRedirect $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new SeoRedirect();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('seo_redirects', $this->model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = ['tenant_id', 'source_url', 'destination_url', 'hits'];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('integer', $casts['hits']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(SeoRedirect::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }
}
