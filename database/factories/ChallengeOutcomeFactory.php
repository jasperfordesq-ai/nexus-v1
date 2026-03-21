<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\ChallengeOutcome;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChallengeOutcomeFactory extends Factory
{
    protected $model = ChallengeOutcome::class;

    public function definition(): array
    {
        return [
            'tenant_id'          => 2,
            'challenge_id'       => $this->faker->numberBetween(1, 100),
            'winning_idea_id'    => $this->faker->optional()->numberBetween(1, 100),
            'status'             => $this->faker->randomElement(['pending', 'announced', 'implemented']),
            'impact_description' => $this->faker->optional()->paragraph(),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
