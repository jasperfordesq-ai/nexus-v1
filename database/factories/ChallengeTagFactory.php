<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\ChallengeTag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ChallengeTagFactory extends Factory
{
    protected $model = ChallengeTag::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->word();

        return [
            'tenant_id' => 2,
            'name'      => ucfirst($name),
            'slug'      => Str::slug($name),
            'tag_type'  => $this->faker->randomElement(['topic', 'skill', 'theme']),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
