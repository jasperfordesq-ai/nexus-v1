<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\User;
use App\Models\VolLog;
use App\Models\VolOpportunity;
use App\Models\VolOrganization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VolLog>
 */
class VolLogFactory extends Factory
{
    protected $model = VolLog::class;

    public function definition(): array
    {
        return [
            'tenant_id'       => 2,
            'user_id'         => User::factory(),
            'organization_id' => VolOrganization::factory(),
            'opportunity_id'  => VolOpportunity::factory(),
            'date_logged'     => $this->faker->dateTimeBetween('-3 months'),
            'hours'           => $this->faker->randomFloat(2, 0.5, 8),
            'description'     => $this->faker->sentence(),
            'status'          => $this->faker->randomElement(['pending', 'approved', 'rejected']),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
