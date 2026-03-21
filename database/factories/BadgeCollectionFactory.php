<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\BadgeCollection;
use Illuminate\Database\Eloquent\Factories\Factory;

class BadgeCollectionFactory extends Factory
{
    protected $model = BadgeCollection::class;

    public function definition(): array
    {
        return [
            'tenant_id'       => 2,
            'collection_key'  => $this->faker->unique()->slug(2),
            'name'            => $this->faker->words(3, true),
            'description'     => $this->faker->sentence(),
            'icon'            => $this->faker->randomElement(['trophy', 'star', 'medal', 'award']),
            'bonus_xp'        => $this->faker->numberBetween(50, 500),
            'bonus_badge_key' => $this->faker->optional()->slug(2),
            'display_order'   => $this->faker->numberBetween(1, 20),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
