<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class MenuItemFactory extends Factory
{
    protected $model = MenuItem::class;

    public function definition(): array
    {
        return [
            'menu_id'          => Menu::factory(),
            'parent_id'        => null,
            'type'             => $this->faker->randomElement(['link', 'route', 'page', 'separator']),
            'label'            => $this->faker->words(2, true),
            'url'              => $this->faker->optional()->url(),
            'route_name'       => null,
            'page_id'          => null,
            'icon'             => $this->faker->optional()->randomElement(['home', 'users', 'settings', 'mail', 'star']),
            'css_class'        => null,
            'target'           => '_self',
            'sort_order'       => $this->faker->numberBetween(1, 20),
            'visibility_rules' => null,
            'is_active'        => true,
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => []);
    }
}
