<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\GroupDiscussionSubscriber;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupDiscussionSubscriberFactory extends Factory
{
    protected $model = GroupDiscussionSubscriber::class;

    public function definition(): array
    {
        return [
            'user_id'      => User::factory(),
            'context_type' => $this->faker->randomElement(['discussion', 'group', 'global']),
            'context_id'   => $this->faker->numberBetween(1, 100),
            'frequency'    => $this->faker->randomElement(['instant', 'daily', 'weekly', 'off']),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => []);
    }
}
