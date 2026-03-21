<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\User;
use App\Models\VolShift;
use App\Models\VolShiftCheckin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<VolShiftCheckin>
 */
class VolShiftCheckinFactory extends Factory
{
    protected $model = VolShiftCheckin::class;

    public function definition(): array
    {
        return [
            'tenant_id'      => 2,
            'shift_id'       => VolShift::factory(),
            'user_id'        => User::factory(),
            'qr_token'       => Str::random(32),
            'status'         => $this->faker->randomElement(['checked_in', 'checked_out', 'no_show']),
            'checked_in_at'  => $this->faker->dateTimeBetween('-1 month'),
            'checked_out_at' => $this->faker->optional()->dateTimeBetween('-1 month'),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
