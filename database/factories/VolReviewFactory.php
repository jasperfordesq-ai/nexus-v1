<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\User;
use App\Models\VolReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VolReview>
 */
class VolReviewFactory extends Factory
{
    protected $model = VolReview::class;

    public function definition(): array
    {
        return [
            'tenant_id'   => 2,
            'reviewer_id' => User::factory(),
            'target_type' => $this->faker->randomElement(['opportunity', 'organization', 'volunteer']),
            'target_id'   => $this->faker->randomNumber(3),
            'rating'      => $this->faker->numberBetween(1, 5),
            'comment'     => $this->faker->optional()->paragraph(),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
