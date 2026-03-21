<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\PayPlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PayPlan>
 */
class PayPlanFactory extends Factory
{
    protected $model = PayPlan::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->randomElement(['Starter', 'Growth', 'Professional', 'Enterprise']);

        return [
            'name'            => $name,
            'slug'            => Str::slug($name),
            'description'     => $this->faker->sentence(),
            'tier_level'      => $this->faker->numberBetween(1, 4),
            'price_monthly'   => $this->faker->randomFloat(2, 0, 199),
            'price_yearly'    => $this->faker->randomFloat(2, 0, 1999),
            'features'        => ['listings', 'events', 'messages'],
            'allowed_layouts' => ['default', 'modern'],
            'max_menus'       => $this->faker->numberBetween(1, 10),
            'max_menu_items'  => $this->faker->numberBetween(5, 50),
            'is_active'       => true,
        ];
    }
}
