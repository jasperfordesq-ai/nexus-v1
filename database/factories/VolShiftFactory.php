<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\VolOpportunity;
use App\Models\VolShift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VolShift>
 */
class VolShiftFactory extends Factory
{
    protected $model = VolShift::class;

    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('now', '+2 months');

        return [
            'tenant_id'      => 2,
            'opportunity_id' => VolOpportunity::factory(),
            'start_time'     => $start,
            'end_time'       => (clone $start)->modify('+' . $this->faker->numberBetween(2, 8) . ' hours'),
            'capacity'       => $this->faker->numberBetween(1, 20),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
