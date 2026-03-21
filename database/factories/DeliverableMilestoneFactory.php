<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\Deliverable;
use App\Models\DeliverableMilestone;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeliverableMilestoneFactory extends Factory
{
    protected $model = DeliverableMilestone::class;

    public function definition(): array
    {
        return [
            'tenant_id'                => 2,
            'deliverable_id'           => Deliverable::factory(),
            'title'                    => $this->faker->sentence(3),
            'description'              => $this->faker->optional()->sentence(),
            'order_position'           => $this->faker->numberBetween(1, 10),
            'status'                   => $this->faker->randomElement(['pending', 'in_progress', 'completed']),
            'due_date'                 => $this->faker->optional()->dateTimeBetween('now', '+2 months'),
            'estimated_hours'          => $this->faker->optional()->randomFloat(1, 1, 20),
            'completed_at'             => null,
            'completed_by'             => null,
            'depends_on_milestone_ids' => [],
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
