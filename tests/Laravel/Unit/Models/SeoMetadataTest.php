<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\SeoMetadata;
use Tests\Laravel\TestCase;

class SeoMetadataTest extends TestCase
{
    private SeoMetadata $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new SeoMetadata();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('seo_metadata', $this->model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = [
            'tenant_id', 'entity_type', 'entity_id', 'meta_title',
            'meta_description', 'meta_keywords', 'canonical_url',
            'og_image_url', 'noindex',
        ];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('boolean', $casts['noindex']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(SeoMetadata::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }
}
