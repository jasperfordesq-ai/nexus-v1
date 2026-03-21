<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\VolExpensePolicy;
use App\Models\VolOrganization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VolExpensePolicy>
 */
class VolExpensePolicyFactory extends Factory
{
    protected $model = VolExpensePolicy::class;

    public function definition(): array
    {
        return [
            'tenant_id'              => 2,
            'organization_id'        => VolOrganization::factory(),
            'expense_type'           => $this->faker->randomElement(['travel', 'meals', 'supplies', 'equipment', 'other']),
            'max_amount'             => $this->faker->randomFloat(2, 50, 500),
            'max_monthly'            => $this->faker->randomFloat(2, 200, 2000),
            'requires_receipt_above' => $this->faker->randomFloat(2, 10, 50),
            'requires_approval'      => $this->faker->boolean(70),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
