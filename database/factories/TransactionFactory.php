<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id'   => 2,
            'sender_id'   => User::factory(),
            'receiver_id' => User::factory(),
            'amount'      => fake()->randomFloat(2, 0.25, 10),
            'description' => fake()->sentence(),
            'status'      => fake()->randomElement(['completed', 'pending', 'cancelled']),
            'deleted_for_sender'   => false,
            'deleted_for_receiver' => false,
            'created_at'  => fake()->dateTimeBetween('-6 months'),
        ];
    }

    /**
     * Scope the transaction to a specific tenant.
     */
    public function forTenant(int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $id,
        ]);
    }
}
