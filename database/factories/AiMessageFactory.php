<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\AiConversation;
use App\Models\AiMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

class AiMessageFactory extends Factory
{
    protected $model = AiMessage::class;

    public function definition(): array
    {
        return [
            'tenant_id'       => 2,
            'conversation_id' => AiConversation::factory(),
            'role'            => $this->faker->randomElement(['user', 'assistant', 'system']),
            'content'         => $this->faker->paragraph(),
            'tokens_used'     => $this->faker->numberBetween(10, 2000),
            'model'           => 'gpt-4o',
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
