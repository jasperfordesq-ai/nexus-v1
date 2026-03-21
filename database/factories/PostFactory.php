<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        $title = $this->faker->sentence();

        return [
            'tenant_id'      => 2,
            'author_id'      => User::factory(),
            'title'          => $title,
            'slug'           => Str::slug($title),
            'excerpt'        => $this->faker->paragraph(),
            'content'        => $this->faker->paragraphs(5, true),
            'featured_image' => null,
            'status'         => $this->faker->randomElement(['draft', 'published']),
            'category_id'    => null,
            'content_json'   => null,
            'html_render'    => null,
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
        ]);
    }
}
