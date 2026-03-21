<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\AbuseAlert;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AbuseAlertFactory extends Factory
{
    protected $model = AbuseAlert::class;

    public function definition(): array
    {
        return [
            'tenant_id'        => 2,
            'alert_type'       => $this->faker->randomElement(['fraud', 'abuse', 'spam', 'harassment']),
            'severity'         => $this->faker->randomElement(['low', 'medium', 'high', 'critical']),
            'user_id'          => User::factory(),
            'transaction_id'   => null,
            'details'          => ['reason' => $this->faker->sentence()],
            'status'           => $this->faker->randomElement(['open', 'investigating', 'resolved', 'dismissed']),
            'resolved_by'      => null,
            'resolved_at'      => null,
            'resolution_notes' => null,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'           => 'resolved',
            'resolved_by'      => User::factory(),
            'resolved_at'      => now(),
            'resolution_notes' => $this->faker->sentence(),
        ]);
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
