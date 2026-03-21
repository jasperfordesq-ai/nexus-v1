<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\NewsletterSubscriber;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class NewsletterSubscriberTest extends TestCase
{
    private NewsletterSubscriber $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new NewsletterSubscriber();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('newsletter_subscribers', $this->model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = [
            'tenant_id', 'email', 'first_name', 'last_name', 'user_id',
            'source', 'status', 'confirmation_token', 'unsubscribe_token',
            'confirmed_at', 'unsubscribed_at', 'unsubscribe_reason',
        ];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('integer', $casts['user_id']);
        $this->assertEquals('datetime', $casts['confirmed_at']);
        $this->assertEquals('datetime', $casts['unsubscribed_at']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(NewsletterSubscriber::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    public function test_user_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->user());
    }
}
