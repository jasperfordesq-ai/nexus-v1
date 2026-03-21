<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\NewsletterBounce;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class NewsletterBounceTest extends TestCase
{
    private NewsletterBounce $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new NewsletterBounce();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('newsletter_bounces', $this->model->getTable());
    }

    public function test_timestamps_disabled(): void
    {
        $this->assertFalse($this->model->usesTimestamps());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = [
            'tenant_id', 'email', 'newsletter_id', 'queue_id',
            'bounce_type', 'bounce_reason', 'bounce_code', 'bounced_at',
        ];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('integer', $casts['newsletter_id']);
        $this->assertEquals('integer', $casts['queue_id']);
        $this->assertEquals('datetime', $casts['bounced_at']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(NewsletterBounce::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    public function test_newsletter_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->newsletter());
    }
}
