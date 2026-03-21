<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\GoalTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GoalTemplateFactory extends Factory
{
    protected $model = GoalTemplate::class;

    public function definition(): array
    {
        return [
            'tenant_id'            => 2,
            'title'                => $this->faker->sentence(4),
            'description'          => $this->faker->paragraph(),
            'category'             => $this->faker->randomElement(['health', 'skills', 'community', 'personal']),
            'default_target_value' => $this->faker->randomFloat(1, 1, 100),
            'default_milestones'   => [
                ['title' => 'Getting started', 'percent' => 25],
                ['title' => 'Halfway there', 'percent' => 50],
                ['title' => 'Almost done', 'percent' => 75],
            ],
            'is_public'            => true,
            'created_by'           => User::factory(),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
