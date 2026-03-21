<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\User;
use App\Models\VolExpense;
use App\Models\VolOpportunity;
use App\Models\VolOrganization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VolExpense>
 */
class VolExpenseFactory extends Factory
{
    protected $model = VolExpense::class;

    public function definition(): array
    {
        return [
            'tenant_id'         => 2,
            'user_id'           => User::factory(),
            'organization_id'   => VolOrganization::factory(),
            'opportunity_id'    => VolOpportunity::factory(),
            'shift_id'          => null,
            'expense_type'      => $this->faker->randomElement(['travel', 'meals', 'supplies', 'equipment', 'other']),
            'amount'            => $this->faker->randomFloat(2, 5, 200),
            'currency'          => $this->faker->randomElement(['EUR', 'GBP', 'USD']),
            'description'       => $this->faker->sentence(),
            'receipt_path'      => null,
            'receipt_filename'  => null,
            'status'            => $this->faker->randomElement(['submitted', 'approved', 'rejected', 'paid']),
            'reviewed_by'       => null,
            'review_notes'      => null,
            'reviewed_at'       => null,
            'paid_at'           => null,
            'payment_reference' => null,
            'submitted_at'      => $this->faker->dateTimeBetween('-3 months'),
        ];
    }

    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'      => 'approved',
            'reviewed_by' => User::factory(),
            'reviewed_at' => $this->faker->dateTimeBetween('-1 month'),
        ]);
    }
}
