<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\SeoMetadata;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoMetadata>
 */
class SeoMetadataFactory extends Factory
{
    protected $model = SeoMetadata::class;

    public function definition(): array
    {
        return [
            'tenant_id'        => 2,
            'entity_type'      => $this->faker->randomElement(['page', 'post', 'listing', 'event']),
            'entity_id'        => $this->faker->randomNumber(3),
            'meta_title'       => $this->faker->sentence(6),
            'meta_description' => $this->faker->sentence(15),
            'meta_keywords'    => implode(', ', $this->faker->words(5)),
            'canonical_url'    => $this->faker->optional()->url(),
            'og_image_url'     => $this->faker->optional()->imageUrl(),
            'noindex'          => false,
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
