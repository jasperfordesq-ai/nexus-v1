<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\Deliverable;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeliverableFactory extends Factory
{
    protected $model = Deliverable::class;

    public function definition(): array
    {
        return [
            'tenant_id'                 => 2,
            'owner_id'                  => User::factory(),
            'title'                     => $this->faker->sentence(5),
            'description'               => $this->faker->paragraph(),
            'category'                  => $this->faker->randomElement(['feature', 'bug', 'improvement', 'task']),
            'priority'                  => $this->faker->randomElement(['low', 'medium', 'high', 'critical']),
            'assigned_to'               => null,
            'assigned_group_id'         => null,
            'start_date'                => $this->faker->optional()->dateTimeBetween('-1 month', 'now'),
            'due_date'                  => $this->faker->optional()->dateTimeBetween('now', '+3 months'),
            'status'                    => $this->faker->randomElement(['backlog', 'todo', 'in_progress', 'review', 'done']),
            'progress_percentage'       => $this->faker->randomFloat(1, 0, 100),
            'estimated_hours'           => $this->faker->optional()->randomFloat(1, 1, 40),
            'actual_hours'              => $this->faker->optional()->randomFloat(1, 0, 60),
            'parent_deliverable_id'     => null,
            'tags'                      => [$this->faker->word()],
            'custom_fields'             => [],
            'delivery_confidence'       => $this->faker->optional()->randomElement(['high', 'medium', 'low']),
            'risk_level'                => $this->faker->optional()->randomElement(['low', 'medium', 'high']),
            'risk_notes'                => null,
            'blocking_deliverable_ids'  => [],
            'depends_on_deliverable_ids' => [],
            'watchers'                  => [],
            'collaborators'             => [],
            'attachment_urls'           => [],
            'external_links'            => [],
            'completed_at'              => null,
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
