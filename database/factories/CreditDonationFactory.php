<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\CreditDonation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CreditDonationFactory extends Factory
{
    protected $model = CreditDonation::class;

    public function definition(): array
    {
        return [
            'tenant_id'      => 2,
            'donor_id'       => User::factory(),
            'recipient_type' => $this->faker->randomElement(['user', 'community_fund']),
            'recipient_id'   => User::factory(),
            'amount'         => $this->faker->randomFloat(2, 0.5, 20),
            'message'        => $this->faker->optional()->sentence(),
            'transaction_id' => null,
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
