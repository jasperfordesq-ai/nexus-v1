<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\User;
use App\Models\VolApplication;
use App\Models\VolOpportunity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VolApplication>
 */
class VolApplicationFactory extends Factory
{
    protected $model = VolApplication::class;

    public function definition(): array
    {
        return [
            'tenant_id'      => 2,
            'opportunity_id' => VolOpportunity::factory(),
            'user_id'        => User::factory(),
            'message'        => $this->faker->paragraph(),
            'shift_id'       => null,
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
