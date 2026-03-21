<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\CommunityFundTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommunityFundTransactionFactory extends Factory
{
    protected $model = CommunityFundTransaction::class;

    public function definition(): array
    {
        return [
            'tenant_id'     => 2,
            'fund_id'       => $this->faker->numberBetween(1, 10),
            'user_id'       => User::factory(),
            'type'          => $this->faker->randomElement(['deposit', 'withdrawal', 'donation', 'admin_adjustment']),
            'amount'        => $this->faker->randomFloat(2, 0.5, 100),
            'balance_after' => $this->faker->randomFloat(2, 0, 5000),
            'description'   => $this->faker->sentence(),
            'admin_id'      => null,
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
