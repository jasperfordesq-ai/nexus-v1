<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\OrgTransferRequest;
use App\Models\User;
use App\Models\VolOrganization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrgTransferRequest>
 */
class OrgTransferRequestFactory extends Factory
{
    protected $model = OrgTransferRequest::class;

    public function definition(): array
    {
        return [
            'tenant_id'       => 2,
            'organization_id' => VolOrganization::factory(),
            'requester_id'    => User::factory(),
            'recipient_id'    => User::factory(),
            'amount'          => $this->faker->randomFloat(2, 1, 50),
            'description'     => $this->faker->sentence(),
            'status'          => $this->faker->randomElement(['pending', 'approved', 'rejected']),
            'approved_by'     => null,
            'approved_at'     => null,
            'rejection_reason' => null,
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'      => 'approved',
            'approved_by' => User::factory(),
            'approved_at' => $this->faker->dateTimeBetween('-1 week'),
        ]);
    }
}
