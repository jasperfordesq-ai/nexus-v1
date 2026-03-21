<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\HelpArticle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class HelpArticleFactory extends Factory
{
    protected $model = HelpArticle::class;

    public function definition(): array
    {
        $title = $this->faker->sentence(5);

        return [
            'title'      => $title,
            'slug'       => Str::slug($title),
            'content'    => $this->faker->paragraphs(3, true),
            'module_tag' => $this->faker->randomElement(['core', 'listings', 'events', 'groups', 'wallet', 'messages', 'getting_started']),
            'is_public'  => true,
            'view_count' => $this->faker->numberBetween(0, 500),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => []);
    }
}
