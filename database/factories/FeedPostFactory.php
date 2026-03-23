<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\FeedPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeedPostFactory extends Factory
{
    protected $model = FeedPost::class;

    public function definition(): array
    {
        return [
            'tenant_id'   => 2,
            'user_id'     => User::factory(),
            'content'     => $this->faker->paragraph(),
            'emoji'       => $this->faker->optional()->randomElement(['thumbsup', 'heart', 'celebrate', 'thinking']),
            'image_url'   => $this->faker->optional()->imageUrl(800, 600),
            'type'        => $this->faker->randomElement(['text', 'image', 'link', 'poll']),
            'parent_id'   => null,
            'parent_type' => null,
            'visibility'  => $this->faker->randomElement(['public', 'connections', 'private']),
            'group_id'    => null,
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
