<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\ResourceItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResourceItem>
 */
class ResourceItemFactory extends Factory
{
    protected $model = ResourceItem::class;

    public function definition(): array
    {
        return [
            'tenant_id'   => 2,
            'user_id'     => User::factory(),
            'title'       => $this->faker->words(4, true),
            'description' => $this->faker->paragraph(),
            'file_path'   => 'uploads/resources/' . $this->faker->uuid() . '.pdf',
            'file_type'   => $this->faker->randomElement(['pdf', 'doc', 'xlsx', 'png', 'jpg']),
            'file_size'   => $this->faker->numberBetween(1024, 10485760),
            'category_id' => null,
            'downloads'   => $this->faker->numberBetween(0, 200),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
