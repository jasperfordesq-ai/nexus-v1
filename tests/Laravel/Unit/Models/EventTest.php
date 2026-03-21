<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Category;
use App\Models\Concerns\HasTenantScope;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\Laravel\TestCase;

class EventTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new Event();
        $this->assertEquals('events', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new Event();
        $expected = [
            'tenant_id', 'user_id', 'title', 'description', 'location',
            'latitude', 'longitude', 'start_time', 'end_time', 'group_id',
            'category_id', 'max_attendees', 'is_online', 'online_link',
            'image_url', 'federated_visibility',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new Event();
        $casts = $model->getCasts();
        $this->assertEquals('float', $casts['latitude']);
        $this->assertEquals('float', $casts['longitude']);
        $this->assertEquals('datetime', $casts['start_time']);
        $this->assertEquals('datetime', $casts['end_time']);
        $this->assertEquals('integer', $casts['max_attendees']);
        $this->assertEquals('boolean', $casts['is_online']);
    }

    public function test_appends_contains_expected_attributes(): void
    {
        $model = new Event();
        $appends = $model->getAppends();
        $this->assertContains('start_date', $appends);
        $this->assertContains('end_date', $appends);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(Event::class)
        );
    }

    public function test_uses_has_factory_trait(): void
    {
        $this->assertContains(
            HasFactory::class,
            class_uses_recursive(Event::class)
        );
    }

    public function test_user_relationship_returns_belongs_to(): void
    {
        $model = new Event();
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }

    public function test_category_relationship_returns_belongs_to(): void
    {
        $model = new Event();
        $this->assertInstanceOf(BelongsTo::class, $model->category());
    }

    public function test_group_relationship_returns_belongs_to(): void
    {
        $model = new Event();
        $this->assertInstanceOf(BelongsTo::class, $model->group());
    }

    public function test_rsvps_relationship_returns_has_many(): void
    {
        $model = new Event();
        $this->assertInstanceOf(HasMany::class, $model->rsvps());
    }

    public function test_scope_upcoming(): void
    {
        $query = Event::withoutGlobalScopes()->upcoming();
        $this->assertStringContainsString('`start_time`', $query->toSql());
    }

    public function test_scope_past(): void
    {
        $query = Event::withoutGlobalScopes()->past();
        $this->assertStringContainsString('`end_time`', $query->toSql());
    }
}
