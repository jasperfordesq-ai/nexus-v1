<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\JobApplication;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\Laravel\TestCase;

class JobApplicationTest extends TestCase
{
    private JobApplication $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new JobApplication();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('job_vacancy_applications', $this->model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = [
            'vacancy_id', 'user_id', 'message', 'status', 'stage',
            'reviewer_notes', 'reviewed_by', 'reviewed_at',
        ];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('integer', $casts['vacancy_id']);
        $this->assertEquals('integer', $casts['user_id']);
        $this->assertEquals('integer', $casts['reviewed_by']);
        $this->assertEquals('datetime', $casts['reviewed_at']);
    }

    public function test_does_not_use_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(JobApplication::class);
        $this->assertNotContains(\App\Models\Concerns\HasTenantScope::class, $traits);
    }

    public function test_vacancy_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->vacancy());
    }

    public function test_applicant_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->applicant());
    }

    public function test_reviewer_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->reviewer());
    }

    public function test_history_relationship(): void
    {
        $this->assertInstanceOf(HasMany::class, $this->model->history());
    }
}
