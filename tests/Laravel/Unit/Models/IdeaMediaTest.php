<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\IdeaMedia;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class IdeaMediaTest extends TestCase
{
    private IdeaMedia $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new IdeaMedia();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('idea_media', $this->model->getTable());
    }

    public function test_timestamps_disabled(): void
    {
        $this->assertFalse($this->model->usesTimestamps());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = ['tenant_id', 'idea_id', 'media_type', 'url', 'caption', 'sort_order'];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('integer', $casts['sort_order']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(IdeaMedia::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    public function test_idea_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->idea());
    }
}
