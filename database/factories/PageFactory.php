<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        $title = $this->faker->words(4, true);

        return [
            'tenant_id'     => 2,
            'title'         => $title,
            'slug'          => Str::slug($title),
            'content'       => $this->faker->paragraphs(3, true),
            'is_published'  => true,
            'publish_at'    => $this->faker->optional()->dateTimeBetween('-1 month', 'now'),
            'show_in_menu'  => $this->faker->boolean(30),
            'menu_location' => $this->faker->optional()->randomElement(['header', 'footer']),
            'sort_order'    => $this->faker->numberBetween(0, 100),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
