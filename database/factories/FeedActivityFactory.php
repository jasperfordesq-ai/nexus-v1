<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\FeedActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeedActivityFactory extends Factory
{
    protected $model = FeedActivity::class;

    public function definition(): array
    {
        return [
            'tenant_id'   => 2,
            'source_type' => $this->faker->randomElement(['listing', 'event', 'transaction', 'connection', 'group', 'achievement']),
            'source_id'   => $this->faker->numberBetween(1, 500),
            'user_id'     => User::factory(),
            'title'       => $this->faker->sentence(4),
            'content'     => $this->faker->optional()->paragraph(),
            'image_url'   => $this->faker->optional()->imageUrl(640, 480),
            'metadata'    => [],
            'group_id'    => null,
            'is_visible'  => true,
            'is_hidden'   => false,
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
