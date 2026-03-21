<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\CookieInventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class CookieInventoryItemFactory extends Factory
{
    protected $model = CookieInventoryItem::class;

    public function definition(): array
    {
        return [
            'cookie_name' => $this->faker->unique()->slug(2),
            'category'    => $this->faker->randomElement(['essential', 'functional', 'analytics', 'marketing']),
            'purpose'     => $this->faker->sentence(),
            'duration'    => $this->faker->randomElement(['session', '1 day', '30 days', '1 year', 'persistent']),
            'third_party' => $this->faker->optional()->company(),
            'tenant_id'   => 2,
            'is_active'   => true,
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
