<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\ExchangeHistory;
use App\Models\ExchangeRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExchangeHistoryFactory extends Factory
{
    protected $model = ExchangeHistory::class;

    public function definition(): array
    {
        return [
            'exchange_id' => ExchangeRequest::factory(),
            'action'      => $this->faker->randomElement(['created', 'accepted', 'declined', 'confirmed', 'completed', 'cancelled']),
            'actor_id'    => User::factory(),
            'actor_role'  => $this->faker->randomElement(['requester', 'provider', 'broker', 'admin']),
            'old_status'  => $this->faker->optional()->randomElement(['pending', 'accepted', 'in_progress']),
            'new_status'  => $this->faker->randomElement(['pending', 'accepted', 'in_progress', 'completed', 'cancelled']),
            'notes'       => $this->faker->optional()->sentence(),
            'created_at'  => now(),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => []);
    }
}
