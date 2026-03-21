<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\Group;
use App\Models\GroupFeedback;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupFeedbackFactory extends Factory
{
    protected $model = GroupFeedback::class;

    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'user_id'  => User::factory(),
            'rating'   => $this->faker->numberBetween(1, 5),
            'comment'  => $this->faker->optional()->paragraph(),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => []);
    }
}
