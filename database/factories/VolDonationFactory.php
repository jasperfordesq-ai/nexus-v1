<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\User;
use App\Models\VolDonation;
use App\Models\VolOpportunity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VolDonation>
 */
class VolDonationFactory extends Factory
{
    protected $model = VolDonation::class;

    public function definition(): array
    {
        return [
            'tenant_id'         => 2,
            'user_id'           => User::factory(),
            'opportunity_id'    => VolOpportunity::factory(),
            'giving_day_id'     => null,
            'amount'            => $this->faker->randomFloat(2, 5, 500),
            'currency'          => $this->faker->randomElement(['EUR', 'GBP', 'USD']),
            'payment_method'    => $this->faker->randomElement(['card', 'paypal', 'bank_transfer']),
            'payment_reference' => $this->faker->uuid(),
            'message'           => $this->faker->optional()->sentence(),
            'is_anonymous'      => $this->faker->boolean(20),
            'status'            => $this->faker->randomElement(['completed', 'pending', 'failed']),
            'created_at'        => $this->faker->dateTimeBetween('-6 months'),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
