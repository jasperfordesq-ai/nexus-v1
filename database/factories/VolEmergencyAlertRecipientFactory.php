<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\User;
use App\Models\VolEmergencyAlert;
use App\Models\VolEmergencyAlertRecipient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VolEmergencyAlertRecipient>
 */
class VolEmergencyAlertRecipientFactory extends Factory
{
    protected $model = VolEmergencyAlertRecipient::class;

    public function definition(): array
    {
        return [
            'alert_id'     => VolEmergencyAlert::factory(),
            'tenant_id'    => 2,
            'user_id'      => User::factory(),
            'notified_at'  => $this->faker->dateTimeBetween('-1 day'),
            'response'     => $this->faker->optional()->randomElement(['accepted', 'declined', 'no_response']),
            'responded_at' => $this->faker->optional()->dateTimeBetween('-1 day'),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
