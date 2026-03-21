<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\Listing;
use App\Models\ListingSkillTag;
use Illuminate\Database\Eloquent\Factories\Factory;

class ListingSkillTagFactory extends Factory
{
    protected $model = ListingSkillTag::class;

    public function definition(): array
    {
        return [
            'tenant_id'  => 2,
            'listing_id' => Listing::factory(),
            'tag'        => $this->faker->randomElement([
                'gardening', 'cooking', 'tutoring', 'carpentry', 'cleaning',
                'pet-care', 'tech-support', 'childcare', 'driving', 'painting',
            ]),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
