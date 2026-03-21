<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\User;
use App\Models\VolEmergencyAlert;
use App\Models\VolShift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VolEmergencyAlert>
 */
class VolEmergencyAlertFactory extends Factory
{
    protected $model = VolEmergencyAlert::class;

    public function definition(): array
    {
        return [
            'tenant_id'       => 2,
            'shift_id'        => VolShift::factory(),
            'created_by'      => User::factory(),
            'priority'        => $this->faker->randomElement(['low', 'medium', 'high', 'critical']),
            'message'         => $this->faker->sentence(),
            'required_skills' => $this->faker->optional()->randomElements(['first_aid', 'driving', 'cooking', 'childcare'], 2),
            'status'          => $this->faker->randomElement(['active', 'filled', 'expired', 'cancelled']),
            'expires_at'      => $this->faker->dateTimeBetween('now', '+24 hours'),
            'filled_at'       => null,
            'created_at'      => now(),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
