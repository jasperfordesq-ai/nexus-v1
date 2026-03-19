<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startTime = fake()->dateTimeBetween('+1 day', '+3 months');
        $endTime   = (clone $startTime)->modify('+' . fake()->numberBetween(1, 4) . ' hours');

        return [
            'tenant_id'      => 2,
            'user_id'        => User::factory(),
            'title'          => fake()->sentence(3),
            'description'    => fake()->paragraph(),
            'location'       => fake()->optional()->address(),
            'latitude'       => fake()->optional()->latitude(),
            'longitude'      => fake()->optional()->longitude(),
            'start_date'     => $startTime,
            'end_date'       => $endTime,
            'max_attendees'  => fake()->optional()->numberBetween(5, 100),
            'is_virtual'     => fake()->boolean(30),
            'virtual_link'   => fake()->optional(0.3)->url(),
            'created_at'     => fake()->dateTimeBetween('-3 months'),
        ];
    }

    /**
     * Scope the event to a specific tenant.
     */
    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
