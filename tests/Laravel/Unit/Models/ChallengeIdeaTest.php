<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\ChallengeIdea;
use App\Models\Concerns\HasTenantScope;
use App\Models\IdeationChallenge;
use App\Models\IdeaMedia;
use App\Models\IdeaTeamLink;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\Laravel\TestCase;

class ChallengeIdeaTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new ChallengeIdea();
        $this->assertEquals('challenge_ideas', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new ChallengeIdea();
        $expected = [
            'challenge_id', 'user_id', 'title', 'description',
            'votes_count', 'comments_count', 'status', 'image_url',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new ChallengeIdea();
        $casts = $model->getCasts();
        $this->assertEquals('integer', $casts['votes_count']);
        $this->assertEquals('integer', $casts['comments_count']);
    }

    public function test_does_not_use_has_tenant_scope_trait(): void
    {
        $this->assertNotContains(
            HasTenantScope::class,
            class_uses_recursive(ChallengeIdea::class)
        );
    }

    public function test_challenge_relationship_returns_belongs_to(): void
    {
        $model = new ChallengeIdea();
        $this->assertInstanceOf(BelongsTo::class, $model->challenge());
        $this->assertEquals('challenge_id', $model->challenge()->getForeignKeyName());
    }

    public function test_media_relationship_returns_has_many(): void
    {
        $model = new ChallengeIdea();
        $this->assertInstanceOf(HasMany::class, $model->media());
        $this->assertEquals('idea_id', $model->media()->getForeignKeyName());
    }

    public function test_team_link_relationship_returns_has_many(): void
    {
        $model = new ChallengeIdea();
        $this->assertInstanceOf(HasMany::class, $model->teamLink());
        $this->assertEquals('idea_id', $model->teamLink()->getForeignKeyName());
    }
}
