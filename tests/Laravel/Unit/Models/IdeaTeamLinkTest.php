<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\IdeaTeamLink;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class IdeaTeamLinkTest extends TestCase
{
    private IdeaTeamLink $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new IdeaTeamLink();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('idea_team_links', $this->model->getTable());
    }

    public function test_timestamps_disabled(): void
    {
        $this->assertFalse($this->model->usesTimestamps());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = ['tenant_id', 'idea_id', 'group_id', 'challenge_id', 'converted_by'];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(IdeaTeamLink::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    public function test_idea_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->idea());
    }

    public function test_group_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->group());
    }
}
