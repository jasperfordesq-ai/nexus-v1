<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\User;
use App\Models\UserStreak;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserStreak>
 */
class UserStreakFactory extends Factory
{
    protected $model = UserStreak::class;

    public function definition(): array
    {
        return [
            'tenant_id'                 => 2,
            'user_id'                   => User::factory(),
            'streak_type'               => $this->faker->randomElement(['login', 'transaction', 'post']),
            'current_streak'            => $this->faker->numberBetween(0, 30),
            'longest_streak'            => $this->faker->numberBetween(5, 60),
            'last_activity_date'        => $this->faker->dateTimeBetween('-1 week'),
            'streak_freezes_remaining'  => $this->faker->numberBetween(0, 3),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
