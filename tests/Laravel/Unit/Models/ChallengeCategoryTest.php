<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\ChallengeCategory;
use App\Models\Concerns\HasTenantScope;
use Tests\Laravel\TestCase;

class ChallengeCategoryTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new ChallengeCategory();
        $this->assertEquals('challenge_categories', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new ChallengeCategory();
        $expected = [
            'tenant_id', 'name', 'slug', 'icon', 'color', 'sort_order',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new ChallengeCategory();
        $casts = $model->getCasts();
        $this->assertEquals('integer', $casts['sort_order']);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(ChallengeCategory::class)
        );
    }
}
