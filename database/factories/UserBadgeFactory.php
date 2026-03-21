<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserBadge>
 */
class UserBadgeFactory extends Factory
{
    protected $model = UserBadge::class;

    public function definition(): array
    {
        return [
            'user_id'        => User::factory(),
            'badge_key'      => $this->faker->randomElement(['first_listing', 'first_transaction', 'helper', 'connector', 'volunteer_star', 'early_adopter']),
            'name'           => $this->faker->words(2, true),
            'icon'           => $this->faker->randomElement(['star', 'heart', 'award', 'zap', 'shield']),
            'is_showcased'   => false,
            'showcase_order' => 0,
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }

    public function showcased(int $order = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'is_showcased'   => true,
            'showcase_order' => $order,
        ]);
    }
}
