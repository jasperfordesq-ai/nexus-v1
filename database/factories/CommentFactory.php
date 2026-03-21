<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommentFactory extends Factory
{
    protected $model = Comment::class;

    public function definition(): array
    {
        return [
            'tenant_id'   => 2,
            'user_id'     => User::factory(),
            'target_type' => $this->faker->randomElement(['listing', 'event', 'feed_post', 'blog']),
            'target_id'   => $this->faker->numberBetween(1, 500),
            'content'     => $this->faker->paragraph(),
            'parent_id'   => null,
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
