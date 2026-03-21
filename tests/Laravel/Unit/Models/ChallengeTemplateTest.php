<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\ChallengeTemplate;
use App\Models\ChallengeCategory;
use App\Models\Concerns\HasTenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class ChallengeTemplateTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new ChallengeTemplate();
        $this->assertEquals('challenge_templates', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new ChallengeTemplate();
        $expected = [
            'tenant_id', 'title', 'description', 'default_tags', 'default_category_id',
            'evaluation_criteria', 'prize_description', 'max_ideas_per_user', 'created_by',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new ChallengeTemplate();
        $casts = $model->getCasts();
        $this->assertEquals('array', $casts['default_tags']);
        $this->assertEquals('array', $casts['evaluation_criteria']);
        $this->assertEquals('integer', $casts['max_ideas_per_user']);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(ChallengeTemplate::class)
        );
    }

    public function test_creator_relationship_returns_belongs_to(): void
    {
        $model = new ChallengeTemplate();
        $this->assertInstanceOf(BelongsTo::class, $model->creator());
        $this->assertEquals('created_by', $model->creator()->getForeignKeyName());
    }

    public function test_category_relationship_returns_belongs_to(): void
    {
        $model = new ChallengeTemplate();
        $this->assertInstanceOf(BelongsTo::class, $model->category());
        $this->assertEquals('default_category_id', $model->category()->getForeignKeyName());
    }
}
