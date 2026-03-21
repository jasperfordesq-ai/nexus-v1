<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\OrgMember;
use App\Models\User;
use App\Models\VolOrganization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrgMember>
 */
class OrgMemberFactory extends Factory
{
    protected $model = OrgMember::class;

    public function definition(): array
    {
        return [
            'tenant_id'       => 2,
            'organization_id' => VolOrganization::factory(),
            'user_id'         => User::factory(),
            'role'            => $this->faker->randomElement(['member', 'admin', 'coordinator']),
            'status'          => $this->faker->randomElement(['active', 'pending', 'inactive']),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
