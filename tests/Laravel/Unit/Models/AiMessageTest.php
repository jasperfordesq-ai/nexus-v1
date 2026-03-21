<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\AiMessage;
use App\Models\AiConversation;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Laravel\TestCase;

class AiMessageTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new AiMessage();
        $this->assertEquals('ai_messages', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new AiMessage();
        $expected = [
            'conversation_id', 'role', 'content',
            'tokens_used', 'model', 'tenant_id',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new AiMessage();
        $casts = $model->getCasts();
        $this->assertEquals('integer', $casts['tokens_used']);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(AiMessage::class)
        );
    }

    public function test_conversation_relationship_returns_belongs_to(): void
    {
        $model = new AiMessage();
        $this->assertInstanceOf(BelongsTo::class, $model->conversation());
        $this->assertEquals('conversation_id', $model->conversation()->getForeignKeyName());
    }
}
