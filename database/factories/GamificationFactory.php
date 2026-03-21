<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\Gamification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GamificationFactory extends Factory
{
    protected $model = Gamification::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 2,
            'user_id'   => User::factory(),
            'action'    => $this->faker->randomElement([
                'listing_created', 'event_joined', 'transaction_completed',
                'connection_made', 'review_given', 'goal_completed',
            ]),
            'points'    => $this->faker->numberBetween(5, 100),
            'reason'    => $this->faker->optional()->sentence(),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
