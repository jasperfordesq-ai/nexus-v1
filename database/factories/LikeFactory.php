<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\Like;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LikeFactory extends Factory
{
    protected $model = Like::class;

    public function definition(): array
    {
        return [
            'tenant_id'   => 2,
            'user_id'     => User::factory(),
            'target_type' => $this->faker->randomElement(['feed_post', 'comment', 'listing', 'event']),
            'target_id'   => $this->faker->numberBetween(1, 500),
            'created_at'  => $this->faker->dateTimeBetween('-1 month'),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
