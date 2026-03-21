<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActivityLogFactory extends Factory
{
    protected $model = ActivityLog::class;

    public function definition(): array
    {
        return [
            'user_id'     => User::factory(),
            'action'      => $this->faker->randomElement(['login', 'logout', 'listing_created', 'event_joined', 'message_sent']),
            'details'     => $this->faker->sentence(),
            'is_public'   => $this->faker->boolean(30),
            'link_url'    => $this->faker->optional()->url(),
            'ip_address'  => $this->faker->ipv4(),
            'action_type' => $this->faker->randomElement(['system', 'user', 'admin']),
            'entity_type' => $this->faker->optional()->randomElement(['listing', 'event', 'group', 'user']),
            'entity_id'   => $this->faker->optional()->numberBetween(1, 1000),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
