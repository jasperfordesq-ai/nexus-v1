<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\AiUsage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AiUsageFactory extends Factory
{
    protected $model = AiUsage::class;

    public function definition(): array
    {
        return [
            'tenant_id'     => 2,
            'user_id'       => User::factory(),
            'provider'      => 'openai',
            'feature'       => $this->faker->randomElement(['chat', 'embeddings', 'moderation', 'matching']),
            'tokens_input'  => $this->faker->numberBetween(50, 5000),
            'tokens_output' => $this->faker->numberBetween(50, 3000),
            'cost_usd'      => $this->faker->randomFloat(6, 0.0001, 0.5),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
