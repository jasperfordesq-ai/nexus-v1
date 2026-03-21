<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\Menu;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MenuFactory extends Factory
{
    protected $model = Menu::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'tenant_id'    => 2,
            'name'         => ucfirst($name),
            'slug'         => Str::slug($name),
            'description'  => $this->faker->optional()->sentence(),
            'location'     => $this->faker->randomElement(['header', 'footer', 'sidebar', 'mobile']),
            'layout'       => $this->faker->randomElement(['horizontal', 'vertical', 'dropdown']),
            'min_plan_tier' => 0,
            'is_active'    => true,
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
