<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\Challenge;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChallengeFactory extends Factory
{
    protected $model = Challenge::class;

    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('-1 week', '+1 week');

        return [
            'tenant_id'      => 2,
            'title'          => $this->faker->sentence(4),
            'description'    => $this->faker->paragraph(),
            'challenge_type' => $this->faker->randomElement(['individual', 'team', 'community']),
            'action_type'    => $this->faker->randomElement(['transactions', 'listings', 'events', 'connections']),
            'target_count'   => $this->faker->numberBetween(1, 50),
            'xp_reward'      => $this->faker->numberBetween(10, 500),
            'badge_reward'   => $this->faker->optional()->slug(2),
            'category'       => $this->faker->optional()->word(),
            'start_date'     => $start,
            'end_date'       => $this->faker->dateTimeBetween($start, '+2 months'),
            'starts_at'      => null,
            'ends_at'        => null,
            'status'         => $this->faker->randomElement(['draft', 'active', 'completed', 'expired']),
            'is_active'      => true,
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
