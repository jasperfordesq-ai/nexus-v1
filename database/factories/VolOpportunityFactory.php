<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\User;
use App\Models\VolOpportunity;
use App\Models\VolOrganization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VolOpportunity>
 */
class VolOpportunityFactory extends Factory
{
    protected $model = VolOpportunity::class;

    public function definition(): array
    {
        return [
            'tenant_id'       => 2,
            'created_by'      => User::factory(),
            'organization_id' => VolOrganization::factory(),
            'title'           => $this->faker->sentence(4),
            'description'     => $this->faker->paragraphs(2, true),
            'location'        => $this->faker->city() . ', ' . $this->faker->country(),
            'skills_needed'   => implode(', ', $this->faker->words(3)),
            'start_date'      => $this->faker->dateTimeBetween('now', '+1 month'),
            'end_date'        => $this->faker->dateTimeBetween('+1 month', '+6 months'),
            'category_id'     => null,
            'status'          => $this->faker->randomElement(['open', 'closed', 'filled']),
            'is_active'       => true,
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
