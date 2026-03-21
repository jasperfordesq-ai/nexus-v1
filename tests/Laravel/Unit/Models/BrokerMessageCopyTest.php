<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\BrokerMessageCopy;
use App\Models\Concerns\HasTenantScope;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class BrokerMessageCopyTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new BrokerMessageCopy();
        $this->assertEquals('broker_message_copies', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new BrokerMessageCopy();
        $expected = [
            'tenant_id', 'original_message_id', 'conversation_key',
            'sender_id', 'receiver_id', 'message_body', 'sent_at',
            'copy_reason', 'related_listing_id',
            'reviewed_by', 'reviewed_at', 'flagged',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new BrokerMessageCopy();
        $casts = $model->getCasts();
        $this->assertEquals('boolean', $casts['flagged']);
        $this->assertEquals('datetime', $casts['reviewed_at']);
        $this->assertEquals('datetime', $casts['sent_at']);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(BrokerMessageCopy::class)
        );
    }

    public function test_sender_relationship_returns_belongs_to(): void
    {
        $model = new BrokerMessageCopy();
        $this->assertInstanceOf(BelongsTo::class, $model->sender());
        $this->assertEquals('sender_id', $model->sender()->getForeignKeyName());
    }

    public function test_receiver_relationship_returns_belongs_to(): void
    {
        $model = new BrokerMessageCopy();
        $this->assertInstanceOf(BelongsTo::class, $model->receiver());
        $this->assertEquals('receiver_id', $model->receiver()->getForeignKeyName());
    }

    public function test_reviewer_relationship_returns_belongs_to(): void
    {
        $model = new BrokerMessageCopy();
        $this->assertInstanceOf(BelongsTo::class, $model->reviewer());
        $this->assertEquals('reviewed_by', $model->reviewer()->getForeignKeyName());
    }

    public function test_original_message_relationship_returns_belongs_to(): void
    {
        $model = new BrokerMessageCopy();
        $this->assertInstanceOf(BelongsTo::class, $model->originalMessage());
        $this->assertEquals('original_message_id', $model->originalMessage()->getForeignKeyName());
    }
}
