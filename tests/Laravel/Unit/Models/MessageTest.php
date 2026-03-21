<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\Concerns\HasTenantScope;
use App\Models\Message;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class MessageTest extends TestCase
{
    private Message $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new Message();
    }

    public function test_table_name(): void
    {
        $this->assertEquals('messages', $this->model->getTable());
    }

    public function test_timestamps_disabled(): void
    {
        $this->assertFalse($this->model->usesTimestamps());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $expected = [
            'tenant_id', 'sender_id', 'receiver_id', 'listing_id',
            'body', 'is_read', 'is_edited', 'edited_at',
            'is_deleted_sender', 'is_deleted_receiver',
            'read_at', 'created_at',
        ];
        $this->assertEquals($expected, $this->model->getFillable());
    }

    public function test_casts_are_correct(): void
    {
        $casts = $this->model->getCasts();
        $this->assertEquals('boolean', $casts['is_read']);
        $this->assertEquals('boolean', $casts['is_edited']);
        $this->assertEquals('boolean', $casts['is_deleted_sender']);
        $this->assertEquals('boolean', $casts['is_deleted_receiver']);
        $this->assertEquals('datetime', $casts['created_at']);
        $this->assertEquals('datetime', $casts['edited_at']);
        $this->assertEquals('datetime', $casts['read_at']);
    }

    public function test_uses_has_tenant_scope(): void
    {
        $traits = class_uses_recursive(Message::class);
        $this->assertContains(HasTenantScope::class, $traits);
    }

    public function test_sender_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->sender());
    }

    public function test_receiver_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->receiver());
    }

    public function test_listing_relationship(): void
    {
        $this->assertInstanceOf(BelongsTo::class, $this->model->listing());
    }

    public function test_scope_unread(): void
    {
        $builder = Message::query()->unread();
        $this->assertInstanceOf(Builder::class, $builder);
    }

    public function test_scope_between_users(): void
    {
        $builder = Message::query()->betweenUsers(1, 2);
        $this->assertInstanceOf(Builder::class, $builder);
    }
}
