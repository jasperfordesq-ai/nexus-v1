<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\User;
use App\Models\VolAccessibilityNeed;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VolAccessibilityNeed>
 */
class VolAccessibilityNeedFactory extends Factory
{
    protected $model = VolAccessibilityNeed::class;

    public function definition(): array
    {
        return [
            'tenant_id'                => 2,
            'user_id'                  => User::factory(),
            'need_type'                => $this->faker->randomElement(['mobility', 'visual', 'hearing', 'cognitive', 'dietary', 'other']),
            'description'              => $this->faker->sentence(),
            'accommodations_required'  => $this->faker->sentence(),
            'emergency_contact_name'   => $this->faker->optional()->name(),
            'emergency_contact_phone'  => $this->faker->optional()->e164PhoneNumber(),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
