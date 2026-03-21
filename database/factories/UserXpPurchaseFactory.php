<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\User;
use App\Models\UserXpPurchase;
use App\Models\XpShopItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserXpPurchase>
 */
class UserXpPurchaseFactory extends Factory
{
    protected $model = UserXpPurchase::class;

    public function definition(): array
    {
        return [
            'tenant_id'  => 2,
            'user_id'    => User::factory(),
            'item_id'    => XpShopItem::factory(),
            'xp_spent'   => $this->faker->numberBetween(50, 500),
            'is_active'  => true,
            'expires_at' => $this->faker->optional()->dateTimeBetween('now', '+3 months'),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
