<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\XpShopItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<XpShopItem>
 */
class XpShopItemFactory extends Factory
{
    protected $model = XpShopItem::class;

    public function definition(): array
    {
        return [
            'tenant_id'      => 2,
            'item_key'       => $this->faker->unique()->slug(2),
            'name'           => $this->faker->words(3, true),
            'description'    => $this->faker->sentence(),
            'icon'           => $this->faker->randomElement(['star', 'gift', 'crown', 'sparkles', 'trophy']),
            'item_type'      => $this->faker->randomElement(['badge', 'theme', 'title', 'avatar_frame']),
            'xp_cost'        => $this->faker->numberBetween(50, 1000),
            'stock_limit'    => $this->faker->optional()->numberBetween(10, 100),
            'per_user_limit' => $this->faker->optional()->numberBetween(1, 5),
            'display_order'  => $this->faker->numberBetween(0, 50),
            'is_active'      => true,
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
