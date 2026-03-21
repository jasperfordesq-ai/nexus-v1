<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\ExchangeRequest;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExchangeRequestFactory extends Factory
{
    protected $model = ExchangeRequest::class;

    public function definition(): array
    {
        return [
            'tenant_id'                => 2,
            'listing_id'               => Listing::factory(),
            'requester_id'             => User::factory(),
            'provider_id'              => User::factory(),
            'proposed_hours'           => $this->faker->randomFloat(2, 0.5, 10),
            'requester_notes'          => $this->faker->optional()->sentence(),
            'status'                   => $this->faker->randomElement(['pending', 'accepted', 'in_progress', 'completed', 'cancelled', 'disputed']),
            'broker_id'                => null,
            'broker_notes'             => null,
            'requester_confirmed_at'   => null,
            'requester_confirmed_hours' => null,
            'provider_confirmed_at'    => null,
            'provider_confirmed_hours' => null,
            'final_hours'              => null,
            'transaction_id'           => null,
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
