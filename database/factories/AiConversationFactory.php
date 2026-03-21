<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AiConversationFactory extends Factory
{
    protected $model = AiConversation::class;

    public function definition(): array
    {
        return [
            'tenant_id'    => 2,
            'user_id'      => User::factory(),
            'title'        => $this->faker->sentence(4),
            'provider'     => 'openai',
            'model'        => $this->faker->randomElement(['gpt-4', 'gpt-4o', 'gpt-3.5-turbo']),
            'context_type' => $this->faker->optional()->randomElement(['listing', 'event', 'general']),
            'context_id'   => null,
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
