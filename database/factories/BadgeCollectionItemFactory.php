<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\BadgeCollection;
use App\Models\BadgeCollectionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class BadgeCollectionItemFactory extends Factory
{
    protected $model = BadgeCollectionItem::class;

    public function definition(): array
    {
        return [
            'collection_id' => BadgeCollection::factory(),
            'badge_key'     => $this->faker->slug(2),
            'display_order' => $this->faker->numberBetween(1, 10),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => []);
    }
}
