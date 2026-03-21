<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\ChallengeCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ChallengeCategoryFactory extends Factory
{
    protected $model = ChallengeCategory::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'tenant_id'  => 2,
            'name'       => ucfirst($name),
            'slug'       => Str::slug($name),
            'icon'       => $this->faker->randomElement(['zap', 'target', 'flag', 'award']),
            'color'      => $this->faker->hexColor(),
            'sort_order' => $this->faker->numberBetween(1, 20),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
