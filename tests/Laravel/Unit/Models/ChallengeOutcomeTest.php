<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\ChallengeOutcome;
use App\Models\Concerns\HasTenantScope;
use App\Models\IdeationChallenge;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class ChallengeOutcomeTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new ChallengeOutcome();
        $this->assertEquals('challenge_outcomes', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new ChallengeOutcome();
        $expected = [
            'tenant_id', 'challenge_id', 'winning_idea_id', 'status', 'impact_description',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(ChallengeOutcome::class)
        );
    }

    public function test_challenge_relationship_returns_belongs_to(): void
    {
        $model = new ChallengeOutcome();
        $this->assertInstanceOf(BelongsTo::class, $model->challenge());
        $this->assertEquals('challenge_id', $model->challenge()->getForeignKeyName());
    }
}
