<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\AiUserLimit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AiUserLimitFactory extends Factory
{
    protected $model = AiUserLimit::class;

    public function definition(): array
    {
        return [
            'tenant_id'          => 2,
            'user_id'            => User::factory(),
            'daily_limit'        => 50,
            'monthly_limit'      => 1000,
            'daily_used'         => $this->faker->numberBetween(0, 20),
            'monthly_used'       => $this->faker->numberBetween(0, 200),
            'last_reset_daily'   => now()->toDateString(),
            'last_reset_monthly' => now()->toDateString(),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
