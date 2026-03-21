<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Concerns\HasTenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\Laravel\TestCase;

class AiConversationTest extends TestCase
{
    public function test_table_name(): void
    {
        $model = new AiConversation();
        $this->assertEquals('ai_conversations', $model->getTable());
    }

    public function test_fillable_contains_expected_fields(): void
    {
        $model = new AiConversation();
        $expected = [
            'tenant_id', 'user_id', 'title', 'provider',
            'model', 'context_type', 'context_id',
        ];
        $this->assertEquals($expected, $model->getFillable());
    }

    public function test_casts_contain_correct_types(): void
    {
        $model = new AiConversation();
        $casts = $model->getCasts();
        $this->assertEquals('integer', $casts['user_id']);
    }

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(
            HasTenantScope::class,
            class_uses_recursive(AiConversation::class)
        );
    }

    public function test_user_relationship_returns_belongs_to(): void
    {
        $model = new AiConversation();
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }

    public function test_messages_relationship_returns_has_many(): void
    {
        $model = new AiConversation();
        $this->assertInstanceOf(HasMany::class, $model->messages());
        $this->assertEquals('conversation_id', $model->messages()->getForeignKeyName());
    }
}
