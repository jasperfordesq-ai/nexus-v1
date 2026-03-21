<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\VolCustomField;
use App\Models\VolCustomFieldValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VolCustomFieldValue>
 */
class VolCustomFieldValueFactory extends Factory
{
    protected $model = VolCustomFieldValue::class;

    public function definition(): array
    {
        return [
            'tenant_id'       => 2,
            'custom_field_id' => VolCustomField::factory(),
            'entity_type'     => $this->faker->randomElement(['volunteer', 'opportunity']),
            'entity_id'       => $this->faker->randomNumber(3),
            'field_value'     => $this->faker->sentence(3),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
