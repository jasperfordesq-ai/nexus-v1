<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\ChallengeTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChallengeTemplateFactory extends Factory
{
    protected $model = ChallengeTemplate::class;

    public function definition(): array
    {
        return [
            'tenant_id'            => 2,
            'title'                => $this->faker->sentence(4),
            'description'          => $this->faker->paragraph(),
            'default_tags'         => [$this->faker->word(), $this->faker->word()],
            'default_category_id'  => null,
            'evaluation_criteria'  => ['quality' => 'High impact', 'feasibility' => 'Achievable'],
            'prize_description'    => $this->faker->optional()->sentence(),
            'max_ideas_per_user'   => $this->faker->numberBetween(1, 10),
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
