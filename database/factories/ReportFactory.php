<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    protected $model = Report::class;

    public function definition(): array
    {
        return [
            'tenant_id'   => 2,
            'reporter_id' => User::factory(),
            'target_type' => $this->faker->randomElement(['user', 'listing', 'post', 'event', 'message']),
            'target_id'   => $this->faker->randomNumber(3),
            'reason'      => $this->faker->sentence(),
            'status'      => $this->faker->randomElement(['pending', 'reviewed', 'resolved', 'dismissed']),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
