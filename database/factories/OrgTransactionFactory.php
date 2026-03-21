<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\OrgTransaction;
use App\Models\VolOrganization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrgTransaction>
 */
class OrgTransactionFactory extends Factory
{
    protected $model = OrgTransaction::class;

    public function definition(): array
    {
        return [
            'tenant_id'           => 2,
            'organization_id'     => VolOrganization::factory(),
            'transfer_request_id' => null,
            'sender_type'         => $this->faker->randomElement(['user', 'organization']),
            'sender_id'           => $this->faker->randomNumber(3),
            'receiver_type'       => $this->faker->randomElement(['user', 'organization']),
            'receiver_id'         => $this->faker->randomNumber(3),
            'amount'              => $this->faker->randomFloat(2, 0.5, 100),
            'description'         => $this->faker->sentence(),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
