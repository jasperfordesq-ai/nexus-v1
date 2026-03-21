<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\GroupType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class GroupTypeFactory extends Factory
{
    protected $model = GroupType::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'tenant_id'   => 2,
            'name'        => ucfirst($name),
            'slug'        => Str::slug($name),
            'description' => $this->faker->sentence(),
            'icon'        => $this->faker->randomElement(['users', 'briefcase', 'heart', 'globe']),
            'color'       => $this->faker->hexColor(),
            'image_url'   => null,
            'sort_order'  => $this->faker->numberBetween(1, 20),
            'is_active'   => true,
            'is_hub'      => false,
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
