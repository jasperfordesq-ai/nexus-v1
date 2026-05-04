<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\JobAlert;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class JobAlertFactory extends Factory
{
    protected $model = JobAlert::class;

    public function definition(): array
    {
        return [
            'tenant_id'       => 2,
            'user_id'         => User::factory(),
            'keywords'        => $this->faker->optional()->words(3, true),
            'categories'      => $this->faker->optional()->word(),
            'type'            => $this->faker->optional()->randomElement(['volunteer', 'paid', 'timebank']),
            'commitment'      => $this->faker->optional()->randomElement(['full_time', 'part_time', 'flexible']),
            'location'        => $this->faker->optional()->city(),
            'is_remote_only'  => $this->faker->boolean(20),
            'is_active'       => true,
            'last_notified_at' => null,
            'created_at'      => now(),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
