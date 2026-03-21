<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\User;
use App\Models\UserInterest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserInterest>
 */
class UserInterestFactory extends Factory
{
    protected $model = UserInterest::class;

    public function definition(): array
    {
        return [
            'tenant_id'     => 2,
            'user_id'       => User::factory(),
            'category_id'   => $this->faker->numberBetween(1, 20),
            'interest_type' => $this->faker->randomElement(['offer', 'request', 'both']),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
