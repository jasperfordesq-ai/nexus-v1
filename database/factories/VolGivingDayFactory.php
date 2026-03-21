<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\User;
use App\Models\VolGivingDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VolGivingDay>
 */
class VolGivingDayFactory extends Factory
{
    protected $model = VolGivingDay::class;

    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('now', '+3 months');

        return [
            'tenant_id'     => 2,
            'title'         => $this->faker->words(4, true),
            'description'   => $this->faker->paragraph(),
            'start_date'    => $startDate,
            'end_date'      => $this->faker->dateTimeBetween($startDate, '+6 months'),
            'goal_amount'   => $this->faker->randomFloat(2, 500, 10000),
            'raised_amount' => $this->faker->randomFloat(2, 0, 5000),
            'is_active'     => true,
            'created_by'    => User::factory(),
            'created_at'    => now(),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
