<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\User;
use App\Models\UserXpLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserXpLog>
 */
class UserXpLogFactory extends Factory
{
    protected $model = UserXpLog::class;

    public function definition(): array
    {
        return [
            'tenant_id'   => 2,
            'user_id'     => User::factory(),
            'xp_amount'   => $this->faker->numberBetween(5, 100),
            'action'      => $this->faker->randomElement(['listing_created', 'transaction_completed', 'event_attended', 'review_posted', 'badge_earned']),
            'description' => $this->faker->sentence(),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
