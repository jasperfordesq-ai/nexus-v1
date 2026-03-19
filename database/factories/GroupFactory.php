<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    protected $model = Group::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id'   => 2,
            'owner_id'    => User::factory(),
            'name'        => fake()->words(3, true),
            'description' => fake()->paragraph(),
            'visibility'  => fake()->randomElement(['public', 'private']),
            'location'    => fake()->optional()->city(),
            'latitude'    => fake()->optional()->latitude(),
            'longitude'   => fake()->optional()->longitude(),
            'is_featured' => fake()->boolean(10),
            'cached_member_count' => fake()->numberBetween(1, 50),
            'created_at'  => fake()->dateTimeBetween('-1 year'),
        ];
    }

    /**
     * Scope the group to a specific tenant.
     */
    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
