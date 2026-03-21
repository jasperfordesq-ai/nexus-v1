<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\Newsletter;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class NewsletterTest extends TestCase
{
    private Newsletter $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new Newsletter();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('newsletters', $this->model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = [
            'tenant_id', 'subject', 'preview_text', 'content', 'status',
            'scheduled_at', 'sent_at', 'created_by', 'total_recipients',
            'total_sent', 'total_failed', 'total_opens', 'unique_opens',
            'total_clicks', 'unique_clicks', 'target_audience', 'segment_id',
            'is_recurring', 'recurring_frequency', 'recurring_day',
            'recurring_day_of_month', 'recurring_time', 'recurring_end_date',
            'last_recurring_sent', 'template_id', 'ab_test_enabled',
            'subject_b', 'ab_split_percentage', 'ab_winner', 'ab_winner_metric',
            'ab_auto_select_winner', 'ab_auto_select_after_hours',
        ];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('datetime', $casts['scheduled_at']);
        $this->assertEquals('datetime', $casts['sent_at']);
        $this->assertEquals('datetime', $casts['last_recurring_sent']);
        $this->assertEquals('date', $casts['recurring_end_date']);
        $this->assertEquals('boolean', $casts['is_recurring']);
        $this->assertEquals('boolean', $casts['ab_test_enabled']);
        $this->assertEquals('boolean', $casts['ab_auto_select_winner']);
        $this->assertEquals('integer', $casts['total_recipients']);
        $this->assertEquals('integer', $casts['total_sent']);
        $this->assertEquals('integer', $casts['total_failed']);
        $this->assertEquals('integer', $casts['total_opens']);
        $this->assertEquals('integer', $casts['unique_opens']);
        $this->assertEquals('integer', $casts['total_clicks']);
        $this->assertEquals('integer', $casts['unique_clicks']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(Newsletter::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    public function test_creator_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->creator());
    }
}
