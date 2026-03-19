<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id'   => 2,
            'reviewer_id' => User::factory(),
            'receiver_id' => User::factory(),
            'rating'      => fake()->numberBetween(1, 5),
            'comment'     => fake()->optional(0.8)->paragraph(),
            'status'      => 'published',
            'created_at'  => fake()->dateTimeBetween('-6 months'),
        ];
    }

    /**
     * Scope the review to a specific tenant.
     */
    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
