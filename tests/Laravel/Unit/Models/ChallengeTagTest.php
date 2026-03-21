<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\ChallengeTag;
use App\Models\Concerns\HasTenantScope;
use Tests\Laravel\TestCase;

class ChallengeTagTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new ChallengeTag();
        $this->assertEquals('challenge_tags', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new ChallengeTag();
        $expected = [
            'tenant_id', 'name', 'slug', 'tag_type',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(ChallengeTag::class)
        );
    }
}
