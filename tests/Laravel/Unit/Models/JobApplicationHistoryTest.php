<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\JobApplicationHistory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class JobApplicationHistoryTest extends TestCase
{
    private JobApplicationHistory $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new JobApplicationHistory();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('job_application_history', $this->model->getTable());
    }

    public function test_timestamps_disabled(): void
    {
        $this->assertFalse($this->model->usesTimestamps());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = [
            'tenant_id', 'application_id', 'from_status', 'to_status',
            'changed_by', 'changed_at', 'notes',
        ];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('integer', $casts['application_id']);
        $this->assertEquals('integer', $casts['changed_by']);
        $this->assertEquals('datetime', $casts['changed_at']);
    }

    public function test_application_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->application());
    }

    public function test_changer_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->changer());
    }
}
