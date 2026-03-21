<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\SkillEndorsement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SkillEndorsement>
 */
class SkillEndorsementFactory extends Factory
{
    protected $model = SkillEndorsement::class;

    public function definition(): array
    {
        return [
            'tenant_id'   => 2,
            'endorser_id' => User::factory(),
            'endorsed_id' => User::factory(),
            'skill_id'    => $this->faker->numberBetween(1, 50),
            'skill_name'  => $this->faker->randomElement(['Gardening', 'Cooking', 'Tutoring', 'IT Support', 'Carpentry', 'Childcare']),
            'comment'     => $this->faker->optional()->sentence(),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
