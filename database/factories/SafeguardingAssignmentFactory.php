<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\SafeguardingAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SafeguardingAssignment>
 */
class SafeguardingAssignmentFactory extends Factory
{
    protected $model = SafeguardingAssignment::class;

    public function definition(): array
    {
        return [
            'tenant_id'        => 2,
            'guardian_user_id'  => User::factory(),
            'ward_user_id'     => User::factory(),
            'assigned_by'      => User::factory(),
            'assigned_at'      => $this->faker->dateTimeBetween('-6 months'),
            'consent_given_at' => $this->faker->optional()->dateTimeBetween('-6 months'),
            'revoked_at'       => null,
            'notes'            => $this->faker->optional()->sentence(),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
