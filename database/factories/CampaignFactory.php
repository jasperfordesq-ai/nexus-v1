<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('-1 month', '+1 month');

        return [
            'tenant_id'   => 2,
            'title'       => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'cover_image' => $this->faker->optional()->imageUrl(800, 400),
            'status'      => $this->faker->randomElement(['draft', 'active', 'completed', 'cancelled']),
            'start_date'  => $start,
            'end_date'    => $this->faker->dateTimeBetween($start, '+3 months'),
            'created_by'  => User::factory(),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
