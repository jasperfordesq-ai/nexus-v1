<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\User;
use App\Models\UserChallengeProgress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserChallengeProgress>
 */
class UserChallengeProgressFactory extends Factory
{
    protected $model = UserChallengeProgress::class;

    public function definition(): array
    {
        return [
            'tenant_id'      => 2,
            'user_id'        => User::factory(),
            'challenge_id'   => $this->faker->randomNumber(3),
            'current_count'  => $this->faker->numberBetween(0, 10),
            'completed_at'   => null,
            'reward_claimed' => false,
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at'   => $this->faker->dateTimeBetween('-1 month'),
            'reward_claimed' => true,
        ]);
    }
}
