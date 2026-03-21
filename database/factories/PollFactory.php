<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\Poll;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Poll>
 */
class PollFactory extends Factory
{
    protected $model = Poll::class;

    public function definition(): array
    {
        return [
            'tenant_id'   => 2,
            'user_id'     => User::factory(),
            'question'    => $this->faker->sentence() . '?',
            'description' => $this->faker->optional()->paragraph(),
            'end_date'    => $this->faker->dateTimeBetween('now', '+1 month'),
            'is_active'   => true,
            'category'    => $this->faker->optional()->word(),
            'poll_type'   => $this->faker->randomElement(['single', 'multiple']),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
