<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\NewsletterAnalytics;
use Tests\Laravel\TestCase;

class NewsletterAnalyticsTest extends TestCase
{
    private NewsletterAnalytics $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new NewsletterAnalytics();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('newsletter_engagement_patterns', $this->model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = [
            'tenant_id', 'email', 'opens_by_hour', 'clicks_by_hour',
            'total_opens', 'total_clicks', 'best_hour', 'last_updated',
        ];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('array', $casts['opens_by_hour']);
        $this->assertEquals('array', $casts['clicks_by_hour']);
        $this->assertEquals('integer', $casts['total_opens']);
        $this->assertEquals('integer', $casts['total_clicks']);
        $this->assertEquals('integer', $casts['best_hour']);
        $this->assertEquals('datetime', $casts['last_updated']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(NewsletterAnalytics::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }
}
