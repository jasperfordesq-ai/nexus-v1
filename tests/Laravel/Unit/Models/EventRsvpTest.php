<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class EventRsvpTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new EventRsvp();
        $this->assertEquals('event_rsvps', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new EventRsvp();
        $expected = [
            'tenant_id', 'event_id', 'user_id', 'status',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new EventRsvp();
        $casts = $model->getCasts();
        $this->assertEquals('integer', $casts['event_id']);
        $this->assertEquals('integer', $casts['user_id']);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(EventRsvp::class)
        );
    }

    public function test_event_relationship_returns_belongs_to(): void
    {
        $model = new EventRsvp();
        $this->assertInstanceOf(BelongsTo::class, $model->event());
    }

    public function test_user_relationship_returns_belongs_to(): void
    {
        $model = new EventRsvp();
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }
}
