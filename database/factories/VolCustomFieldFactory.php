<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\VolCustomField;
use App\Models\VolOrganization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VolCustomField>
 */
class VolCustomFieldFactory extends Factory
{
    protected $model = VolCustomField::class;

    public function definition(): array
    {
        return [
            'tenant_id'       => 2,
            'organization_id' => VolOrganization::factory(),
            'field_key'       => $this->faker->unique()->slug(2),
            'field_label'     => $this->faker->words(3, true),
            'field_type'      => $this->faker->randomElement(['text', 'textarea', 'select', 'checkbox', 'date']),
            'applies_to'      => $this->faker->randomElement(['volunteer', 'opportunity', 'shift']),
            'is_required'     => $this->faker->boolean(30),
            'field_options'   => null,
            'display_order'   => $this->faker->numberBetween(0, 20),
            'placeholder'     => $this->faker->optional()->sentence(3),
            'help_text'       => $this->faker->optional()->sentence(),
            'is_active'       => true,
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
