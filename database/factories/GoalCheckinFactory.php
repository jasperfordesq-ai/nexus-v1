<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\Goal;
use App\Models\GoalCheckin;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GoalCheckinFactory extends Factory
{
    protected $model = GoalCheckin::class;

    public function definition(): array
    {
        return [
            'tenant_id'        => 2,
            'goal_id'          => Goal::factory(),
            'user_id'          => User::factory(),
            'progress_percent' => $this->faker->randomFloat(1, 0, 100),
            'note'             => $this->faker->optional()->sentence(),
            'mood'             => $this->faker->optional()->randomElement(['great', 'good', 'okay', 'struggling']),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
