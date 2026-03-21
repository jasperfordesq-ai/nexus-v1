<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\AccountRelationship;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountRelationshipFactory extends Factory
{
    protected $model = AccountRelationship::class;

    public function definition(): array
    {
        return [
            'tenant_id'         => 2,
            'parent_user_id'    => User::factory(),
            'child_user_id'     => User::factory(),
            'relationship_type' => $this->faker->randomElement(['parent', 'guardian', 'carer']),
            'permissions'       => ['view_profile' => true, 'manage_listings' => false],
            'status'            => $this->faker->randomElement(['pending', 'approved', 'rejected']),
            'approved_at'       => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'      => 'approved',
            'approved_at' => now(),
        ]);
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
