<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\NexusScoreCache;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class NexusScoreCacheTest extends TestCase
{
    private NexusScoreCache $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new NexusScoreCache();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('nexus_score_cache', $this->model->getTable());
    }

    public function test_timestamps_disabled(): void
    {
        $this->assertFalse($this->model->usesTimestamps());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = [
            'tenant_id', 'user_id', 'total_score',
            'engagement_score', 'quality_score', 'volunteer_score',
            'activity_score', 'badge_score', 'impact_score',
            'percentile', 'tier', 'calculated_at',
        ];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('float', $casts['total_score']);
        $this->assertEquals('float', $casts['engagement_score']);
        $this->assertEquals('float', $casts['quality_score']);
        $this->assertEquals('float', $casts['volunteer_score']);
        $this->assertEquals('float', $casts['activity_score']);
        $this->assertEquals('float', $casts['badge_score']);
        $this->assertEquals('float', $casts['impact_score']);
        $this->assertEquals('integer', $casts['percentile']);
        $this->assertEquals('datetime', $casts['calculated_at']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(NexusScoreCache::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    public function test_user_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->user());
    }
}
