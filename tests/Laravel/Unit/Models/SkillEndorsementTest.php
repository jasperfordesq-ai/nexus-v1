<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\SkillEndorsement;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class SkillEndorsementTest extends TestCase
{
    private SkillEndorsement $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new SkillEndorsement();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('skill_endorsements', $this->model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = [
            'tenant_id', 'endorser_id', 'endorsed_id', 'skill_id',
            'skill_name', 'comment',
        ];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('integer', $casts['endorser_id']);
        $this->assertEquals('integer', $casts['endorsed_id']);
        $this->assertEquals('integer', $casts['skill_id']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(SkillEndorsement::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    public function test_endorser_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->endorser());
    }

    public function test_endorsed_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->endorsed());
    }
}
