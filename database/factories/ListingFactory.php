<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
{
    protected $model = Listing::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id'    => 2,
            'user_id'      => User::factory()->forTenant(2),
            'title'        => fake()->sentence(4),
            'description'  => fake()->paragraph(),
            'type'         => fake()->randomElement(['offer', 'request']),
            'status'       => 'active',
            'location'     => fake()->optional()->city(),
            'latitude'     => fake()->optional()->latitude(),
            'longitude'    => fake()->optional()->longitude(),
            'price'        => fake()->randomFloat(2, 0.5, 10),
            'hours_estimate' => fake()->randomFloat(2, 0.5, 8),
            'service_type' => fake()->randomElement(['in-person', 'remote', 'either']),
            'view_count'   => fake()->numberBetween(0, 100),
            'contact_count' => fake()->numberBetween(0, 20),
            'created_at'   => fake()->dateTimeBetween('-6 months'),
        ];
    }

    /**
     * Indicate the listing is an offer.
     */
    public function offer(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'offer',
        ]);
    }

    /**
     * Indicate the listing is a request.
     */
    public function request(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'request',
        ]);
    }

    /**
     * Scope the listing to a specific tenant.
     */
    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
            'user_id'   => $attributes['user_id'] ?? User::factory()->forTenant($id),
        ]);
    }
}
