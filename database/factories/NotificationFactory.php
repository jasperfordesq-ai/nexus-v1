<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'tenant_id'  => 2,
            'user_id'    => User::factory(),
            'type'       => $this->faker->randomElement(['info', 'message', 'connection', 'transaction', 'event', 'listing']),
            'message'    => $this->faker->sentence(),
            'link'       => $this->faker->optional()->url(),
            'is_read'    => false,
            'created_at' => $this->faker->dateTimeBetween('-1 month'),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }

    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => true,
        ]);
    }
}
